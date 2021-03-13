<?php

namespace App;

use App\User;
use App\Traits\SettingsTrait;
use Carbon\Carbon;
use Iatstuti\Database\Support\NullableFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The eloquent definition of a wallet -- a container with a chunk of change.
 *
 * A wallet is owned by an {@link \App\User}.
 *
 * @property integer $balance
 */
class Wallet extends Model
{
    use NullableFields;
    use SettingsTrait;

    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $attributes = [
        'balance' => 0,
        'currency' => 'CHF'
    ];

    protected $fillable = [
        'currency'
    ];

    protected $nullable = [
        'description',
    ];

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

    public function chargeEntitlements($apply = true)
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

        $charges = 0;
        $discount = $this->getDiscountRate();

        DB::beginTransaction();

        // used to parent individual entitlement billings to the wallet debit.
        $entitlementTransactions = [];

        foreach ($this->entitlements()->get()->fresh() as $entitlement) {
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

                $charges += $cost;

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
            $this->debit($charges, $entitlementTransactions);
        }

        DB::commit();

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
     * @param int   $amount The amount of pecunia to deduct (in cents).
     * @param array $eTIDs  List of transaction IDs for the individual entitlements that make up
     *                      this debit record, if any.
     * @return Wallet Self
     */
    public function debit(int $amount, array $eTIDs = []): Wallet
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
                'amount' => $amount * -1
            ]
        );

        \App\Transaction::whereIn('id', $eTIDs)->update(['transaction_id' => $transaction->id]);

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

        // Prefer intl extension's number formatter
        if (class_exists('NumberFormatter')) {
            $nf = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $result = $nf->formatCurrency($amount, $this->currency);
            // Replace non-breaking space
            return str_replace("\xC2\xA0", " ", $result);
        }

        return sprintf('%.2f %s', $amount, $this->currency);
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
     * Any (additional) properties of this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function settings()
    {
        return $this->hasMany('App\WalletSetting');
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
}
