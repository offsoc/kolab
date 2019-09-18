<?php

namespace App;

use Iatstuti\Database\Support\NullableFields;
use Illuminate\Database\Eloquent\Model;

/**
    The eloquent definition of a wallet -- a container with a chunk of change.

    A wallet is owned by an {@link \App\User}.
 */
class Wallet extends Model
{
    use NullableFields;

    /**
        Our table name for the shall be 'wallet'.

        @var string
     */
    /**
        {@inheritDoc}
     */
    public $incrementing = false;
    /**
        {@inheritDoc}
     */
    protected $keyType = 'string';

    /**
        {@inheritDoc}
     */
    public $timestamps = false;

    /**
        {@inheritDoc}
     */
    protected $attributes = [
        'balance' => 0.00,
        'currency' => 'CHF'
    ];

    /**
        {@inheritDoc}
     */
    protected $fillable = [
        'currency'
    ];

    /**
        {@inheritDoc}
     */
    protected $nullable = [
        'description'
    ];

    /**
        {@inheritDoc}
     */
    protected $casts = [
        'balance' => 'float',
    ];

    /**
        {@inheritDoc}
     */
    protected $guarded = ['balance'];

    /**
        Provide a custom ID (uuid) property.

        @todo migrate to observers

        @return void
     */
    protected static function boot()
    {
        parent::boot();

        // retrieved, creating, created, updating, updated, saving, saved, deleting, deleted,
        // restoring, restored
        static::creating(
            function ($wallet) {
                $wallet->{$wallet->getKeyName()} = \App\Utils::uuidStr();
            }
        );

        // Prevent a wallet with a positive of negative balance from being deleted.
        static::deleting(
            function ($wallet) {
                // can't delete a wallet that has any balance on it (positive and negative).
                if ($wallet->balance != 0.00) {
                    return false;
                }

                if (!$wallet->owner) {
                    throw new \Exception("Wallet: " . var_export($wallet, true));
                }

                // can't remove the last wallet for the owner.
                if ($wallet->owner->wallets()->count() <= 1) {
                    return false;
                }

                // can't remove a wallet that has billable entitlements attached.
                if ($wallet->entitlements()->count() > 0) {
                    return false;
                }
            }
        );
    }

    /**
        Add a controller to this wallet.

        @param User $user The user to add as a controller to this wallet.

        @return void
     */
    public function addController($user)
    {
        if (!$this->controllers()->get()->contains($user)) {
            return $this->controllers()->save($user);
        }
    }

    /**
        Remove a controller from this wallet.

        @param User $user The user to remove as a controller from this wallet.

        @return void
     */
    public function removeController($user)
    {
        if ($this->controllers()->get()->contains($user)) {
            return $this->controllers()->detach($user);
        }
    }

    /**
        Add an amount of pecunia to this wallet's balance.

        @param float $amount The amount of pecunia to add.

        @return Wallet
     */
    public function credit(float $amount)
    {
        $this->balance += $amount;

        $this->save();

        return $this;
    }

    /**
        Deduct an amount of pecunia from this wallet's balance.

        @param float $amount The amount of pecunia to deduct.

        @return Wallet
     */
    public function debit(float $amount)
    {
        $this->balance -= $amount;

        $this->save();

        return $this;
    }

    /**
        Controllers of this wallet.

        @return User[]
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
        Entitlements billed to this wallet.

        @return Entitlement[]
     */
    public function entitlements()
    {
        return $this->hasMany('App\Entitlement');
    }

    /**
        The owner of the wallet -- the wallet is in his/her back pocket.

        @return User
     */
    public function owner()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }
}
