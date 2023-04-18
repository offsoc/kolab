<?php

namespace App;

use App\Traits\SettingsTrait;
use App\Traits\UuidStrKeyTrait;
use Carbon\Carbon;
use Dyrynda\Database\Support\NullableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The eloquent definition of a wallet -- a container with a chunk of change.
 *
 * A wallet is owned by an {@link \App\User}.
 *
 * @property int        $balance     Current balance in cents
 * @property string     $currency    Currency code
 * @property ?string    $description Description
 * @property string     $id          Unique identifier
 * @property ?\App\User $owner       Owner (can be null when owner is deleted)
 * @property int        $user_id     Owner's identifier
 */
class Wallet extends Model
{
    use NullableFields;
    use SettingsTrait;
    use UuidStrKeyTrait;

    /** @var bool Indicates that the model should be timestamped or not */
    public $timestamps = false;

    /** @var array The attributes' default values */
    protected $attributes = [
        'balance' => 0,
    ];

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'currency',
        'description'
    ];

    /** @var array<int, string> The attributes that can be not set */
    protected $nullable = [
        'description',
    ];

    /** @var array<string, string> The types of attributes to which its values will be cast */
    protected $casts = [
        'balance' => 'integer',
    ];


    /**
     * Add a controller to this wallet.
     *
     * @param \App\User $user The user to add as a controller to this wallet.
     *
     * @return void
     */
    public function addController(User $user)
    {
        if (!$this->controllers->contains($user)) {
            $this->controllers()->save($user);
        }
    }

    /**
     * Add an award to this wallet's balance.
     *
     * @param int|\App\Payment $amount      The amount of award (in cents) or Payment object
     * @param string           $description The transaction description
     *
     * @return Wallet Self
     */
    public function award(int|Payment $amount, string $description = ''): Wallet
    {
        return $this->balanceUpdate(Transaction::WALLET_AWARD, $amount, $description);
    }

    /**
     * Charge a specific entitlement (for use on entitlement delete).
     *
     * @param \App\Entitlement $entitlement The entitlement.
     */
    public function chargeEntitlement(Entitlement $entitlement): void
    {
        // Sanity checks
        if ($entitlement->trashed() || $entitlement->wallet->id != $this->id || !$this->owner) {
            return;
        }

        // Start calculating the costs for the consumption of this entitlement if the
        // existing consumption spans >= 14 days.
        //
        // Effect is that anything's free for the first 14 days
        if ($entitlement->created_at >= Carbon::now()->subDays(14)) {
            return;
        }

        if ($this->owner->isDegraded()) {
            return;
        }

        $now = Carbon::now();

        // Determine if we're still within the trial period
        $trial = $this->trialInfo();
        if (
            !empty($trial)
            && $entitlement->updated_at < $trial['end']
            && in_array($entitlement->sku_id, $trial['skus'])
        ) {
            if ($trial['end'] >= $now) {
                return;
            }

            $entitlement->updated_at = $trial['end'];
        }

        // get the discount rate applied to the wallet.
        $discount = $this->getDiscountRate();

        // just in case this had not been billed yet, ever
        $diffInMonths = $entitlement->updated_at->diffInMonths($now);
        $cost = (int) ($entitlement->cost * $discount * $diffInMonths);
        $fee = (int) ($entitlement->fee * $diffInMonths);

        // this moves the hypothetical updated at forward to however many months past the original
        $updatedAt = $entitlement->updated_at->copy()->addMonthsWithoutOverflow($diffInMonths);

        // now we have the diff in days since the last "billed" period end.
        // This may be an entitlement paid up until February 28th, 2020, with today being March
        // 12th 2020. Calculating the costs for the entitlement is based on the daily price

        // the price per day is based on the number of days in the last month
        // or the current month if the period does not overlap with the previous month
        // FIXME: This really should be simplified to $daysInMonth=30

        $diffInDays = $updatedAt->diffInDays($now);

        if ($now->day >= $diffInDays) {
            $daysInMonth = $now->daysInMonth;
        } else {
            $daysInMonth = \App\Utils::daysInLastMonth();
        }

        $pricePerDay = $entitlement->cost / $daysInMonth;
        $feePerDay = $entitlement->fee / $daysInMonth;

        $cost += (int) (round($pricePerDay * $discount * $diffInDays, 0));
        $fee += (int) (round($feePerDay * $diffInDays, 0));

        $profit = $cost - $fee;

        if ($profit != 0 && $this->owner->tenant && ($wallet = $this->owner->tenant->wallet())) {
            $desc = "Charged user {$this->owner->email}";
            $method = $profit > 0 ? 'credit' : 'debit';
            $wallet->{$method}(abs($profit), $desc);
        }

        if ($cost == 0) {
            return;
        }

        // TODO: Create per-entitlement transaction record?

        $this->debit($cost);
    }

    /**
     * Charge entitlements in the wallet
     *
     * @param bool $apply Set to false for a dry-run mode
     *
     * @return int Charged amount in cents
     */
    public function chargeEntitlements($apply = true): int
    {
        $transactions = [];
        $profit = 0;
        $charges = 0;
        $discount = $this->getDiscountRate();
        $isDegraded = $this->owner->isDegraded();
        $trial = $this->trialInfo();

        if ($apply) {
            DB::beginTransaction();
        }

        // Get all entitlements...
        $entitlements = $this->entitlements()
            // Skip entitlements created less than or equal to 14 days ago (this is at
            // maximum the fourteenth 24-hour period).
            // ->where('created_at', '<=', Carbon::now()->subDays(14))
            // Skip entitlements created, or billed last, less than a month ago.
            ->where('updated_at', '<=', Carbon::now()->subMonthsWithoutOverflow(1))
            ->get();

        foreach ($entitlements as $entitlement) {
            // If in trial, move entitlement's updated_at timestamps forward to the trial end.
            if (
                !empty($trial)
                && $entitlement->updated_at < $trial['end']
                && in_array($entitlement->sku_id, $trial['skus'])
            ) {
                // TODO: Consider not updating the updated_at to a future date, i.e. bump it
                // as many months as possible, but not into the future
                // if we're in dry-run, you know...
                if ($apply) {
                    $entitlement->updated_at = $trial['end'];
                    $entitlement->save();
                }

                continue;
            }

            $diff = $entitlement->updated_at->diffInMonths(Carbon::now());

            if ($diff <= 0) {
                continue;
            }

            $cost = (int) ($entitlement->cost * $discount * $diff);
            $fee = (int) ($entitlement->fee * $diff);

            if ($isDegraded) {
                $cost = 0;
            }

            $charges += $cost;
            $profit += $cost - $fee;

            // if we're in dry-run, you know...
            if (!$apply) {
                continue;
            }

            $entitlement->updated_at = $entitlement->updated_at->copy()->addMonthsWithoutOverflow($diff);
            $entitlement->save();

            if ($cost == 0) {
                continue;
            }

            $transactions[] = $entitlement->createTransaction(Transaction::ENTITLEMENT_BILLED, $cost);
        }

        if ($apply) {
            $this->debit($charges, '', $transactions);

            // Credit/debit the reseller
            if ($profit != 0 && $this->owner->tenant) {
                // FIXME: Should we have a simpler way to skip this for non-reseller tenant(s)
                if ($wallet = $this->owner->tenant->wallet()) {
                    $desc = "Charged user {$this->owner->email}";
                    $method = $profit > 0 ? 'credit' : 'debit';
                    $wallet->{$method}(abs($profit), $desc);
                }
            }

            DB::commit();
        }

        return $charges;
    }

    /**
     * Calculate for how long the current balance will last.
     *
     * Returns NULL for balance < 0 or discount = 100% or on a fresh account
     *
     * @return \Carbon\Carbon|null Date
     */
    public function balanceLastsUntil()
    {
        if ($this->balance < 0 || $this->getDiscount() == 100) {
            return null;
        }

        $balance = $this->balance;
        $discount = $this->getDiscountRate();
        $trial = $this->trialInfo();

        // Get all entitlements...
        $entitlements = $this->entitlements()->orderBy('updated_at')->get()
            ->filter(function ($entitlement) {
                return $entitlement->cost > 0;
            })
            ->map(function ($entitlement) {
                return [
                    'date' => $entitlement->updated_at ?: $entitlement->created_at,
                    'cost' => $entitlement->cost,
                    'sku_id' => $entitlement->sku_id,
                ];
            })
            ->all();

        $max = 12 * 25;
        while ($max > 0) {
            foreach ($entitlements as &$entitlement) {
                $until = $entitlement['date'] = $entitlement['date']->addMonthsWithoutOverflow(1);

                if (
                    !empty($trial)
                    && $entitlement['date'] < $trial['end']
                    && in_array($entitlement['sku_id'], $trial['skus'])
                ) {
                    continue;
                }

                $balance -= (int) ($entitlement['cost'] * $discount);

                if ($balance < 0) {
                    break 2;
                }
            }

            $max--;
        }

        if (empty($until)) {
            return null;
        }

        // Don't return dates from the past
        if ($until <= Carbon::now() && !$until->isToday()) {
            return null;
        }

        return $until;
    }

    /**
     * Chargeback an amount of pecunia from this wallet's balance.
     *
     * @param int|\App\Payment $amount      The amount of pecunia to charge back (in cents) or Payment object
     * @param string           $description The transaction description
     *
     * @return Wallet Self
     */
    public function chargeback(int|Payment $amount, string $description = ''): Wallet
    {
        return $this->balanceUpdate(Transaction::WALLET_CHARGEBACK, $amount, $description);
    }

    /**
     * Controllers of this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function controllers()
    {
        return $this->belongsToMany(
            User::class,      // The foreign object definition
            'user_accounts',  // The table name
            'wallet_id',      // The local foreign key
            'user_id'         // The remote foreign key
        );
    }

    /**
     * Add an amount of pecunia to this wallet's balance.
     *
     * @param int|\App\Payment $amount      The amount of pecunia to add (in cents) or Payment object
     * @param string           $description The transaction description
     *
     * @return Wallet Self
     */
    public function credit(int|Payment $amount, string $description = ''): Wallet
    {
        return $this->balanceUpdate(Transaction::WALLET_CREDIT, $amount, $description);
    }

    /**
     * Deduct an amount of pecunia from this wallet's balance.
     *
     * @param int|\App\Payment $amount      The amount of pecunia to deduct (in cents) or Payment object
     * @param string           $description The transaction description
     * @param array            $eTIDs       List of transaction IDs for the individual entitlements
     *                                      that make up this debit record, if any.
     * @return Wallet Self
     */
    public function debit(int|Payment $amount, string $description = '', array $eTIDs = []): Wallet
    {
        return $this->balanceUpdate(Transaction::WALLET_DEBIT, $amount, $description, $eTIDs);
    }

    /**
     * The discount assigned to the wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id', 'id');
    }

    /**
     * Entitlements billed to this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function entitlements()
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * Calculate the expected charges to this wallet.
     *
     * @return int
     */
    public function expectedCharges()
    {
        return $this->chargeEntitlements(false);
    }

    /**
     * Return the exact, numeric version of the discount to be applied.
     *
     * @return int Discount in percent, ranges from 0 - 100.
     */
    public function getDiscount(): int
    {
        return $this->discount ? $this->discount->discount : 0;
    }

    /**
     * The actual discount rate for use in multiplication
     *
     * @return float Discount rate, ranges from 0.00 to 1.00.
     */
    public function getDiscountRate(): float
    {
        return (100 - $this->getDiscount()) / 100;
    }

    /**
     * The minimum amount of an auto-payment mandate
     *
     * @return int Amount in cents
     */
    public function getMinMandateAmount(): int
    {
        $min = Payment::MIN_AMOUNT;

        if ($plan = $this->plan()) {
            $planCost = (int) ($plan->cost() * $this->getDiscountRate());

            if ($planCost > $min) {
                $min = $planCost;
            }
        }

        return $min;
    }

    /**
     * Check if the specified user is a controller to this wallet.
     *
     * @param \App\User $user The user object.
     *
     * @return bool True if the user is one of the wallet controllers (including user), False otherwise
     */
    public function isController(User $user): bool
    {
        return $user->id == $this->user_id || $this->controllers->contains($user);
    }

    /**
     * A helper to display human-readable amount of money using
     * the wallet currency and specified locale.
     *
     * @param int    $amount A amount of money (in cents)
     * @param string $locale A locale for the output
     *
     * @return string String representation, e.g. "9.99 CHF"
     */
    public function money(int $amount, $locale = 'de_DE')
    {
        return \App\Utils::money($amount, $this->currency, $locale);
    }

    /**
     * The owner of the wallet -- the wallet is in his/her back pocket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Payments on this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Add a penalty to this wallet's balance.
     *
     * @param int|\App\Payment $amount      The amount of penalty (in cents) or Payment object
     * @param string           $description The transaction description
     *
     * @return Wallet Self
     */
    public function penalty(int|Payment $amount, string $description = ''): Wallet
    {
        return $this->balanceUpdate(Transaction::WALLET_PENALTY, $amount, $description);
    }

    /**
     * Plan of the wallet.
     *
     * @return ?\App\Plan
     */
    public function plan()
    {
        $planId = $this->owner->getSetting('plan_id');

        return $planId ? Plan::find($planId) : null;
    }

    /**
     * Remove a controller from this wallet.
     *
     * @param \App\User $user The user to remove as a controller from this wallet.
     *
     * @return void
     */
    public function removeController(User $user)
    {
        if ($this->controllers->contains($user)) {
            $this->controllers()->detach($user);
        }
    }

    /**
     * Refund an amount of pecunia from this wallet's balance.
     *
     * @param int|\App\Payment $amount      The amount of pecunia to refund (in cents) or Payment object
     * @param string           $description The transaction description
     *
     * @return Wallet Self
     */
    public function refund($amount, string $description = ''): Wallet
    {
        return $this->balanceUpdate(Transaction::WALLET_REFUND, $amount, $description);
    }

    /**
     * Get the VAT rate for the wallet owner country.
     *
     * @param ?\DateTime $start Get the rate valid for the specified date-time,
     *                          without it the current rate will be returned (if exists).
     *
     * @return ?\App\VatRate VAT rate
     */
    public function vatRate(\DateTime $start = null): ?VatRate
    {
        $owner = $this->owner;

        // Make it working with deleted accounts too
        if (!$owner) {
            $owner = $this->owner()->withTrashed()->first();
        }

        $country = $owner->getSetting('country');

        if (!$country) {
            return null;
        }

        return VatRate::where('country', $country)
            ->where('start', '<=', ($start ?: now())->format('Y-m-d h:i:s'))
            ->orderByDesc('start')
            ->limit(1)
            ->first();
    }

    /**
     * Retrieve the transactions against this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function transactions()
    {
        return Transaction::where('object_id', $this->id)->where('object_type', Wallet::class);
    }

    /**
     * Returns trial related information.
     *
     * @return ?array Plan ID, plan SKUs, trial end date, number of free months (planId, skus, end, months)
     */
    public function trialInfo(): ?array
    {
        $plan = $this->plan();
        $freeMonths = $plan ? $plan->free_months : 0;
        $trialEnd = $freeMonths ? $this->owner->created_at->copy()->addMonthsWithoutOverflow($freeMonths) : null;

        if ($trialEnd) {
            // Get all SKUs assigned to the plan (they are free in trial)
            // TODO: We could store the list of plan's SKUs in the wallet settings, for two reasons:
            //       - performance
            //       - if we change plan definition at some point in time, the old users would use
            //         the old definition, instead of the current one
            // TODO: The same for plan's free_months value
            $trialSkus = \App\Sku::select('id')
                ->whereIn('id', function ($query) use ($plan) {
                    $query->select('sku_id')
                        ->from('package_skus')
                        ->whereIn('package_id', function ($query) use ($plan) {
                            $query->select('package_id')
                                ->from('plan_packages')
                                ->where('plan_id', $plan->id);
                        });
                })
                ->whereNot('title', 'storage')
                ->pluck('id')
                ->all();

            return [
                'end' => $trialEnd,
                'skus' => $trialSkus,
                'planId' => $plan->id,
                'months' => $freeMonths,
            ];
        }

        return null;
    }

    /**
     * Force-update entitlements' updated_at, charge if needed.
     *
     * @param bool $withCost When enabled the cost will be charged
     *
     * @return int Charged amount in cents
     */
    public function updateEntitlements($withCost = true): int
    {
        $charges = 0;
        $discount = $this->getDiscountRate();
        $now = Carbon::now();

        DB::beginTransaction();

        // used to parent individual entitlement billings to the wallet debit.
        $entitlementTransactions = [];

        foreach ($this->entitlements()->get() as $entitlement) {
            $cost = 0;
            $diffInDays = $entitlement->updated_at->diffInDays($now);

            // This entitlement has been created less than or equal to 14 days ago (this is at
            // maximum the fourteenth 24-hour period).
            if ($entitlement->created_at > Carbon::now()->subDays(14)) {
                // $cost=0
            } elseif ($withCost && $diffInDays > 0) {
                // The price per day is based on the number of days in the last month
                // or the current month if the period does not overlap with the previous month
                // FIXME: This really should be simplified to constant $daysInMonth=30
                if ($now->day >= $diffInDays && $now->month == $entitlement->updated_at->month) {
                    $daysInMonth = $now->daysInMonth;
                } else {
                    $daysInMonth = \App\Utils::daysInLastMonth();
                }

                $pricePerDay = $entitlement->cost / $daysInMonth;

                $cost = (int) (round($pricePerDay * $discount * $diffInDays, 0));
            }

            if ($diffInDays > 0) {
                $entitlement->updated_at = $entitlement->updated_at->setDateFrom($now);
                $entitlement->save();
            }

            if ($cost == 0) {
                continue;
            }

            $charges += $cost;

            // FIXME: Shouldn't we store also cost=0 transactions (to have the full history)?
            $entitlementTransactions[] = $entitlement->createTransaction(
                Transaction::ENTITLEMENT_BILLED,
                $cost
            );
        }

        if ($charges > 0) {
            $this->debit($charges, '', $entitlementTransactions);
        }

        DB::commit();

        return $charges;
    }

    /**
     * Update the wallet balance, and create a transaction record
     */
    protected function balanceUpdate(string $type, int|Payment $amount, $description = null, array $eTIDs = [])
    {
        if ($amount instanceof Payment) {
            $amount = $amount->credit_amount;
        }

        if ($amount === 0) {
            return $this;
        }

        if (in_array($type, [Transaction::WALLET_CREDIT, Transaction::WALLET_AWARD])) {
            $amount = abs($amount);
        } else {
            $amount = abs($amount) * -1;
        }

        $this->balance += $amount;
        $this->save();

        $transaction = Transaction::create([
                'user_email' => \App\Utils::userEmailOrNull(),
                'object_id' => $this->id,
                'object_type' => Wallet::class,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
        ]);

        if (!empty($eTIDs)) {
            Transaction::whereIn('id', $eTIDs)->update(['transaction_id' => $transaction->id]);
        }

        return $this;
    }
}
