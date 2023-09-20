<?php

namespace App;

use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The eloquent definition of an Entitlement.
 *
 * Owned by a {@link \App\User}, billed to a {@link \App\Wallet}.
 *
 * @property int          $cost
 * @property ?string      $description
 * @property ?object      $entitleable      The entitled object (receiver of the entitlement).
 * @property int          $entitleable_id
 * @property string       $entitleable_type
 * @property int          $fee
 * @property string       $id
 * @property \App\User    $owner            The owner of this entitlement (subject).
 * @property \App\Sku     $sku              The SKU to which this entitlement applies.
 * @property string       $sku_id
 * @property \App\Wallet  $wallet           The wallet to which this entitlement is charged.
 * @property string       $wallet_id
 */
class Entitlement extends Model
{
    use SoftDeletes;
    use UuidStrKeyTrait;

    /** @var array<int, string> The attributes that are mass assignable */
    protected $fillable = [
        'sku_id',
        'wallet_id',
        'entitleable_id',
        'entitleable_type',
        'cost',
        'description',
        'fee',
    ];

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'cost' => 'integer',
        'fee' => 'integer'
    ];

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
        $transaction = Transaction::create(
            [
                'object_id' => $this->id,
                'object_type' => Entitlement::class,
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
        return $this->morphTo()->withTrashed();
    }

    /**
     * Simplified Entitlement/SKU information for a specified entitleable object
     *
     * @param object $object Entitleable object
     *
     * @return array Skus list with some metadata
     */
    public static function objectEntitlementsSummary($object): array
    {
        $skus = [];

        // TODO: I agree this format may need to be extended in future

        foreach ($object->entitlements as $ent) {
            $sku = $ent->sku;

            if (!isset($skus[$sku->id])) {
                $skus[$sku->id] = ['costs' => [], 'count' => 0];
            }

            $skus[$sku->id]['count']++;
            $skus[$sku->id]['costs'][] = $ent->cost;
        }

        return $skus;
    }

    /**
     * The SKU concerned.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sku()
    {
        return $this->belongsTo(Sku::class);
    }

    /**
     * The wallet this entitlement is being billed to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Cost mutator. Make sure cost is integer.
     */
    public function setCostAttribute($cost): void
    {
        $this->attributes['cost'] = round($cost);
    }
}
