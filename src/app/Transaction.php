<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        // actor, if any
        'user_email',

        // entitlement, wallet
        'object_id',
        'object_type',

        // entitlement: created, deleted, billed
        // wallet: debit, credit, award, penalty
        'type',

        'amount',

        'description',

        // parent, for example wallet debit is parent for entitlements charged.
        'transaction_id'
    ];

    /** @var array Casts properties as type */
    protected $casts = [
        'amount' => 'integer',
    ];

    /** @var boolean This model uses an automatically incrementing integer primary key? */
    public $incrementing = false;

    /** @var string The type of the primary key */
    protected $keyType = 'string';

    public const ENTITLEMENT_BILLED = 'billed';
    public const ENTITLEMENT_CREATED = 'created';
    public const ENTITLEMENT_DELETED = 'deleted';

    public const WALLET_AWARD = 'award';
    public const WALLET_CREDIT = 'credit';
    public const WALLET_DEBIT = 'debit';
    public const WALLET_PENALTY = 'penalty';

    public function entitlement()
    {
        if ($this->object_type !== \App\Entitlement::class) {
            return null;
        }

        return \App\Entitlement::withTrashed()->where('id', $this->object_id)->first();
    }

    public function setTypeAttribute($value)
    {
        switch ($value) {
            case self::ENTITLEMENT_BILLED:
            case self::ENTITLEMENT_CREATED:
            case self::ENTITLEMENT_DELETED:
                // TODO: Must be an entitlement.
                $this->attributes['type'] = $value;
                break;

            case self::WALLET_AWARD:
            case self::WALLET_CREDIT:
            case self::WALLET_DEBIT:
            case self::WALLET_PENALTY:
                // TODO: This must be a wallet.
                $this->attributes['type'] = $value;
                break;

            default:
                throw new \Exception("Invalid type value");
        }
    }

    public function toArray()
    {
        $result = [
            'user_email' => $this->user_email,
            'entitlement_cost' => $this->getEntitlementCost(),
            'object_email' => $this->getEntitlementObjectEmail(),
            'sku_title' => $this->getEntitlementSkuTitle(),
            'wallet_description' => $this->getWalletDescription(),
            'description' => $this->{'description'},
            'amount' => $this->amount
        ];

        return $result;
    }

    public function toString()
    {
        $label = $this->objectTypeToLabelString() . '-' . $this->{'type'};

        return \trans("transactions.{$label}", $this->toArray());
    }

    public function wallet()
    {
        if ($this->object_type !== \App\Wallet::class) {
            return null;
        }

        return \App\Wallet::where('id', $this->object_id)->first();
    }

    /**
     * Return the costs for this entitlement.
     *
     * @return int|null
     */
    private function getEntitlementCost(): ?int
    {
        if (!$this->entitlement()) {
            return null;
        }

        // FIXME: without wallet discount
        // FIXME: in cents
        // FIXME: without wallet currency
        $cost = $this->entitlement()->cost;

        $discount = $this->entitlement()->wallet->getDiscountRate();

        return $cost * $discount;
    }

    /**
     * Return the object email if any. This is the email for the target user entitlement.
     *
     * @return string|null
     */
    private function getEntitlementObjectEmail(): ?string
    {
        $entitlement = $this->entitlement();

        if (!$entitlement) {
            return null;
        }

        $entitleable = $entitlement->entitleable;

        if (!$entitleable) {
            \Log::debug("No entitleable for {$entitlement->id} ?");
            return null;
        }

        return $entitleable->email;
    }

    /**
     * Return the title for the SKU this entitlement is for.
     *
     * @return string|null
     */
    private function getEntitlementSkuTitle(): ?string
    {
        if (!$this->entitlement()) {
            return null;
        }

        return $this->entitlement()->sku->{'title'};
    }

    /**
     * Return the description for the wallet, if any, or 'default wallet'.
     *
     * @return string
     */
    public function getWalletDescription()
    {
        $description = null;

        if ($entitlement = $this->entitlement()) {
            $description = $entitlement->wallet->{'description'};
        }

        if ($wallet = $this->wallet()) {
            $description = $wallet->{'description'};
        }

        return $description ?: 'Default wallet';
    }

    /**
     * Get a string for use in translation tables derived from the object type.
     *
     * @return string|null
     */
    private function objectTypeToLabelString(): ?string
    {
        if ($this->object_type == \App\Entitlement::class) {
            return 'entitlement';
        }

        if ($this->object_type == \App\Wallet::class) {
            return 'wallet';
        }

        return null;
    }
}
