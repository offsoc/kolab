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
    /** @var list<string> The attributes that are mass assignable */
    protected $fillable = ['wallet_id', 'key', 'value'];

    /**
     * The wallet to which this setting belongs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Wallet, $this>
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'id');
    }
}
