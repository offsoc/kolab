<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UuidStrKeyTrait;

/**
 * The eloquent definition of an Entitlement.
 *
 * Owned by a {@link \App\User}, billed to a {@link \App\Wallet}.
 *
 * @property int                   $cost
 * @property ?string               $description
 * @property \App\Domain|\App\User $entitleable      The entitled object (receiver of the entitlement).
 * @property int                   $entitleable_id
 * @property string                $entitleable_type
 * @property int                   $fee
 * @property string                $id
 * @property \App\User             $owner            The owner of this entitlement (subject).
 * @property \App\Sku              $sku              The SKU to which this entitlement applies.
 * @property string                $sku_id
 * @property \App\Wallet           $wallet           The wallet to which this entitlement is charged.
 * @property string                $wallet_id
 */
class Entitlement extends Model
{
    use SoftDeletes;
    use UuidStrKeyTrait;

    /**
     * The fillable columns for this Entitlement
     *
     * @var array
     */
    protected $fillable = [
        'sku_id',
        'wallet_id',
        'entitleable_id',
        'entitleable_type',
        'cost',
        'description',
        'fee',
    ];

    protected $casts = [
        'cost' => 'integer',
        'fee' => 'integer'
    ];

    /**
     * Return the costs per day for this entitlement.
     *
     * @return float
     */
    public function costsPerDay()
    {
        if ($this->cost == 0) {
            return (float) 0;
        }

        $discount = $this->wallet->getDiscountRate();

        $daysInLastMonth = \App\Utils::daysInLastMonth();

        $costsPerDay = (float) ($this->cost * $discount) / $daysInLastMonth;

        return $costsPerDay;
    }

    /**
     * Create a transaction record for this entitlement.
     *
     * @param string $type The type of transaction ('created', 'billed', 'deleted'), but use the
     *                     \App\Transaction constants.
     * @param int $amount  The amount involved in cents
     *
     * @return string The transaction ID
     */
    public function createTransaction($type, $amount = null)
    {
        $transaction = \App\Transaction::create(
            [
                'object_id' => $this->id,
                'object_type' => \App\Entitlement::class,
                'type' => $type,
                'amount' => $amount
            ]
        );

        return $transaction->id;
    }

    /**
     * Principally entitleable object such as Domain, User, Group.
     * Note that it may be trashed (soft-deleted).
     *
     * @return mixed
     */
    public function entitleable()
    {
        return $this->morphTo()->withTrashed(); // @phpstan-ignore-line
    }

    /**
     * Returns entitleable object title (e.g. email or domain name).
     *
     * @return string|null An object title/name
     */
    public function entitleableTitle(): ?string
    {
        if ($this->entitleable instanceof \App\Domain) {
            return $this->entitleable->namespace;
        }

        return $this->entitleable->email;
    }

    /**
     * The SKU concerned.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sku()
    {
        return $this->belongsTo('App\Sku');
    }

    /**
     * The wallet this entitlement is being billed to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function wallet()
    {
        return $this->belongsTo('App\Wallet');
    }

    /**
     * Cost mutator. Make sure cost is integer.
     */
    public function setCostAttribute($cost): void
    {
        $this->attributes['cost'] = round($cost);
    }
}
