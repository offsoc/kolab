<?php

namespace App;

use App\Traits\SettingsTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Tenant.
 *
 * @property int    $id
 * @property string $title
 */
class Tenant extends Model
{
    use SettingsTrait;

    protected $fillable = [
        'id',
        'title',
    ];

    protected $keyType = 'bigint';

    /**
     * Utility method to get tenant-specific system setting.
     * If the setting is not specified for the tenant a system-wide value will be returned.
     *
     * @param int    $tenantId Tenant identifier
     * @param string $key      Setting name
     *
     * @return mixed Setting value
     */
    public static function getConfig($tenantId, string $key)
    {
        // Cache the tenant instance in memory
        static $tenant;

        if (empty($tenant) || $tenant->id != $tenantId) {
            $tenant = null;
            if ($tenantId) {
                $tenant = self::findOrFail($tenantId);
            }
        }

        // Supported options (TODO: document this somewhere):
        // - app.name (tenants.title will be returned)
        // - app.public_url and app.url
        // - app.support_url
        // - mail.from.address and mail.from.name
        // - mail.reply_to.address and mail.reply_to.name
        // - app.kb.account_delete and app.kb.account_suspended
        // - pgp.enable

        if ($key == 'app.name') {
            return $tenant ? $tenant->title : \config($key);
        }

        $value = $tenant ? $tenant->getSetting($key) : null;

        return $value !== null ? $value : \config($key);
    }

    /**
     * Discounts assigned to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function discounts()
    {
        return $this->hasMany('App\Discount');
    }

    /**
     * SignupInvitations assigned to this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function signupInvitations()
    {
        return $this->hasMany('App\SignupInvitation');
    }

    /*
     * Returns the wallet of the tanant (reseller's wallet).
     *
     * @return ?\App\Wallet A wallet object
     */
    public function wallet(): ?Wallet
    {
        $user = \App\User::where('role', 'reseller')->where('tenant_id', $this->id)->first();

        return $user ? $user->wallets->first() : null;
    }
}
