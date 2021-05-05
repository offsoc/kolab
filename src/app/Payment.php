<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A payment operation on a wallet.
 *
 * @property int         $amount      Amount of money in cents of CHF
 * @property string      $description Payment description
 * @property string      $id          Mollie's Payment ID
 * @property \App\Wallet $wallet      The wallet
 * @property string      $wallet_id   The ID of the wallet
 * @property string      $currency    Currency of this payment
 * @property int         $currency_amount      Amount of money in cents of $currency
 */
class Payment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'amount' => 'integer'
    ];

    protected $fillable = [
        'id',
        'wallet_id',
        'amount',
        'description',
        'provider',
        'status',
        'type',
        'currency',
        'currency_amount',
    ];


    /**
     * Ensure the currency is appropriately cased.
     */
    public function setCurrencyAttribute($currency)
    {
        $this->attributes['currency'] = strtoupper($currency);
    }

    /**
     * The wallet to which this payment belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function wallet()
    {
        return $this->belongsTo(
            '\App\Wallet',
            'wallet_id', /* local */
            'id' /* remote */
        );
    }
}
