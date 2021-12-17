<?php

namespace App;

use App\User;
use App\Traits\SettingsTrait;
use App\Traits\UuidStrKeyTrait;
use Carbon\Carbon;
use Iatstuti\Database\Support\NullableFields;
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

    public $timestamps = false;

    /**
     * The attributes' default values.
     *
     * @var array
     */
    protected $attributes = [
        'balance' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'currency',
        'description'
    ];

    /**
     * The attributes that can be not set.
     *
     * @var array
     */
    protected $nullable = [
        'description',
    ];

    /**
     * The types of attributes to which its values will be cast
     *
     * @var array
     */
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
     * Charge entitlements in the wallet
     *
     * @param bool $apply Set to false for a dry-run mode
     *
     * @return int Charged amount in cents
     */
    public function chargeEntitlements($apply = true): int
    {
        // This wallet has been created less than a month ago, this is the trial period
        if ($this->owner->created_at >= Carbon::now()->subMonthsWithoutOverflow(1)) {
            // Move all the current entitlement's updated_at timestamps forward to one month after
            // this wallet was created.
            $freeMonthEnds = $this->owner->created_at->copy()->addMonthsWithoutOverflow(1);

            foreach ($this->entitlements()->get()->fresh() as $entitlement) {
                if ($entitlement->updated_at < $freeMonthEnds) {
                    $entitlement->updated_at = $freeMonthEnds;
                    $entitlement->save();
                }
            }

            return 0;
        }

        $profit = 0;
        $charges = 0;
        $discount = $this->getDiscountRate();
        $isDegraded = $this->owner->isDegraded();

        if ($apply) {
            DB::beginTransaction();
        }

        // used to parent individual entitlement billings to the wallet debit.
        $entitlementTransactions = [];

        foreach ($this->entitlements()->get() as $entitlement) {
            // This entitlement has been created less than or equal to 14 days ago (this is at
            // maximum the fourteenth 24-hour period).
            if ($entitlement->created_at > Carbon::now()->subDays(14)) {
                continue;
            }

            // This entitlement was created, or billed last, less than a month ago.
            if ($entitlement->updated_at > Carbon::now()->subMonthsWithoutOverflow(1)) {
                continue;
            }

            // updated last more than a month ago -- was it billed?
            if ($entitlement->updated_at <= Carbon::now()->subMonthsWithoutOverflow(1)) {
                $diff = $entitlement->updated_at->diffInMonths(Carbon::now());

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

                $entitlement->updated_at = $entitlement->updated_at->copy()
                    ->addMonthsWithoutOverflow($diff);

                $entitlement->save();

                if ($cost == 0) {
                    continue;
                }

                $entitlementTransactions[] = $entitlement->createTransaction(
                    \App\Transaction::ENTITLEMENT_BILLED,
                    $cost
                );
            }
        }

        if ($apply) {
            $this->debit($charges, '', $entitlementTransactions);

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

        // retrieve any expected charges
        $expectedCharge = $this->expectedCharges();

        // get the costs per day for all entitlements billed against this wallet
        $costsPerDay = $this->costsPerDay();

        if (!$costsPerDay) {
            return null;
        }

        // the number of days this balance, minus the expected charges, would last
        $daysDelta = ($this->balance - $expectedCharge) / $costsPerDay;

        // calculate from the last entitlement billed
        $entitlement = $this->entitlements()->orderBy('updated_at', 'desc')->first();

        $until = $entitlement->updated_at->copy()->addDays($daysDelta);

        // Don't return dates from the past
        if ($until < Carbon::now() && !$until->isToday()) {
            return null;
        }

        return $until;
    }

    /**
     * Controllers of this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function controllers()
    {
        return $this->belongsToMany(
            'App\User',         // The foreign object definition
            'user_accounts',    // The table name
            'wallet_id',      // The local foreign key
            'user_id'         // The remote foreign key
        );
    }

    /**
     * Retrieve the costs per day of everything charged to this wallet.
     *
     * @return float
     */
    public function costsPerDay()
    {
        $costs = (float) 0;

        foreach ($this->entitlements as $entitlement) {
            $costs += $entitlement->costsPerDay();
        }

        return $costs;
    }

    /**
     * Add an amount of pecunia to this wallet's balance.
     *
     * @param int    $amount      The amount of pecunia to add (in cents).
     * @param string $description The transaction description
     *
     * @return Wallet Self
     */
    public function credit(int $amount, string $description = ''): Wallet
    {
        $this->balance += $amount;

        $this->save();

        \App\Transaction::create(
            [
                'object_id' => $this->id,
                'object_type' => \App\Wallet::class,
                'type' => \App\Transaction::WALLET_CREDIT,
                'amount' => $amount,
                'description' => $description
            ]
        );

        return $this;
    }

    /**
     * Deduct an amount of pecunia from this wallet's balance.
     *
     * @param int    $amount      The amount of pecunia to deduct (in cents).
     * @param string $description The transaction description
     * @param array  $eTIDs       List of transaction IDs for the individual entitlements
     *                            that make up this debit record, if any.
     * @return Wallet Self
     */
    public function debit(int $amount, string $description = '', array $eTIDs = []): Wallet
    {
        if ($amount == 0) {
            return $this;
        }

        $this->balance -= $amount;

        $this->save();

        $transaction = \App\Transaction::create(
            [
                'object_id' => $this->id,
                'object_type' => \App\Wallet::class,
                'type' => \App\Transaction::WALLET_DEBIT,
                'amount' => $amount * -1,
                'description' => $description
            ]
        );

        if (!empty($eTIDs)) {
            \App\Transaction::whereIn('id', $eTIDs)->update(['transaction_id' => $transaction->id]);
        }

        return $this;
    }

    /**
     * The discount assigned to the wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function discount()
    {
        return $this->belongsTo('App\Discount', 'discount_id', 'id');
    }

    /**
     * Entitlements billed to this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement');
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
     * Ranges from 0 - 100.
     *
     * @return int
     */
    public function getDiscount()
    {
        return $this->discount ? $this->discount->discount : 0;
    }

    /**
     * The actual discount rate for use in multiplication
     *
     * Ranges from 0.00 to 1.00.
     */
    public function getDiscountRate()
    {
        return (100 - $this->getDiscount()) / 100;
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
        $amount = round($amount / 100, 2);

        $nf = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $result = $nf->formatCurrency($amount, $this->currency);
        // Replace non-breaking space
        return str_replace("\xC2\xA0", " ", $result);
    }

    /**
     * The owner of the wallet -- the wallet is in his/her back pocket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    /**
     * Payments on this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany('App\Payment');
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
     * Retrieve the transactions against this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Builder Query builder
     */
    public function transactions()
    {
        return \App\Transaction::where(
            [
                'object_id' => $this->id,
                'object_type' => \App\Wallet::class
            ]
        );
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
                \App\Transaction::ENTITLEMENT_BILLED,
                $cost
            );
        }

        if ($charges > 0) {
            $this->debit($charges, '', $entitlementTransactions);
        }

        DB::commit();

        return $charges;
    }
}
