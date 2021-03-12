<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * The eloquent definition of a Discount.
 *
 * @property bool   $active
 * @property string $code
 * @property string $description
 * @property int    $discount
 * @property int    $tenant_id
 */
class Discount extends Model
{
    use HasTranslations;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'discount' => 'integer',
    ];

    protected $fillable = [
        'active',
        'code',
        'description',
        'discount',
        'tenant_id',
    ];

    /** @var array Translatable properties */
    public $translatable = [
        'description',
    ];

    /**
     * Discount value mutator
     *
     * @throws \Exception
     */
    public function setDiscountAttribute($discount)
    {
        $discount = (int) $discount;

        if ($discount < 0) {
            \Log::warning("Expecting a discount rate >= 0");
            $discount = 0;
        }

        if ($discount > 100) {
            \Log::warning("Expecting a discount rate <= 100");
            $discount = 100;
        }

        $this->attributes['discount'] = $discount;
    }

    /**
     * List of wallets with this discount assigned.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallets()
    {
        return $this->hasMany('App\Wallet');
    }
}
