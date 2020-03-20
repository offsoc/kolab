<?php

namespace App;

use App\User;
use Carbon\Carbon;
use Iatstuti\Database\Support\NullableFields;
use Illuminate\Database\Eloquent\Model;

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
        'description'
    ];

    protected $casts = [
        'balance' => 'integer',
    ];

    protected $guarded = ['balance'];

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
        $charges = 0;

        foreach ($this->entitlements()->get()->fresh() as $entitlement) {
            // This entitlement has been created less than or equal to 14 days ago (this is at
            // maximum the fourteenth 24-hour period).
            if ($entitlement->created_at > Carbon::now()->subDays(14)) {
                continue;
            }

            // This entitlement was created, or billed last, less than a month ago.
            if ($entitlement->updated_at > Carbon::now()->subMonths(1)) {
                continue;
            }

            // created more than a month ago -- was it billed?
            if ($entitlement->updated_at <= Carbon::now()->subMonths(1)) {
                $diff = $entitlement->updated_at->diffInMonths(Carbon::now());

                $charges += $entitlement->cost * $diff;

                // if we're in dry-run, you know...
                if (!$apply) {
                    continue;
                }

                $entitlement->updated_at = $entitlement->updated_at->copy()->addMonths($diff);
                $entitlement->save();

                $this->debit($entitlement->cost * $diff);
            }
        }

        return $charges;
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
     * Add an amount of pecunia to this wallet's balance.
     *
     * @param int $amount The amount of pecunia to add (in cents).
     *
     * @return Wallet Self
     */
    public function credit(int $amount): Wallet
    {
        $this->balance += $amount;

        $this->save();

        return $this;
    }

    /**
     * Deduct an amount of pecunia from this wallet's balance.
     *
     * @param int $amount The amount of pecunia to deduct (in cents).
     *
     * @return Wallet Self
     */
    public function debit(int $amount): Wallet
    {
        $this->balance -= $amount;

        $this->save();

        return $this;
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
     * Entitlements billed to this wallet.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement');
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
}
