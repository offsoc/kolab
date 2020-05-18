<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A collection of settings for a Wallet.
 *
 * @property int    $id
 * @property string $wallet_id
 * @property string $key
 * @property string $value
 */
class WalletSetting extends Model
{
    protected $fillable = [
        'wallet_id', 'key', 'value'
    ];

    /**
     * The wallet to which this setting belongs.
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
