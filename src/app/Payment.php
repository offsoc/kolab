<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A payment operation on a wallet.
 *
 * @property int    $amount      Amount of money in cents
 * @property string $description Payment description
 * @property string $id          Mollie's Payment ID
 * @property int    $wallet_id   The ID of the wallet
 */
class Payment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'amount' => 'integer'
    ];

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
