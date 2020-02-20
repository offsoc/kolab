<?php

namespace App;

use App\User;
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
        'balance' => 0.00,
        'currency' => 'CHF'
    ];

    protected $fillable = [
        'currency'
    ];

    protected $nullable = [
        'description'
    ];

    protected $casts = [
        'balance' => 'float',
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
     * @param float $amount The amount of pecunia to add.
     *
     * @return Wallet
     */
    public function credit(float $amount)
    {
        $this->balance += $amount;

        $this->save();

        return $this;
    }

    /**
     * Deduct an amount of pecunia from this wallet's balance.
     *
     * @param float $amount The amount of pecunia to deduct.
     *
     * @return Wallet
     */
    public function debit(float $amount)
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
}
