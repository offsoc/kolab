<?php

namespace App;

use App\Traits\UuidStrKeyTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * The eloquent definition of a Transaction.
 *
 * @property int    $amount
 * @property string $description
 * @property string $id
 * @property string $object_id
 * @property string $object_type
 * @property string $type
 * @property string $transaction_id
 * @property string $user_email
 */
class Transaction extends Model
{
    use UuidStrKeyTrait;

    public const ENTITLEMENT_BILLED = 'billed';
    public const ENTITLEMENT_CREATED = 'created';
    public const ENTITLEMENT_DELETED = 'deleted';

    public const WALLET_AWARD = 'award';
    public const WALLET_CREDIT = 'credit';
    public const WALLET_DEBIT = 'debit';
    public const WALLET_PENALTY = 'penalty';
    public const WALLET_REFUND = 'refund';
    public const WALLET_CHARGEBACK = 'chback';

    /** @var array<int, string> The attributes that are mass assignable */
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

    /** @var array<string, string> Casts properties as type */
    protected $casts = [
        'amount' => 'integer',
    ];


    /**
     * Returns the entitlement to which the transaction is assigned (if any)
     *
     * @return \App\Entitlement|null The entitlement
     */
    public function entitlement(): ?Entitlement
    {
        if ($this->object_type !== Entitlement::class) {
            return null;
        }

        return Entitlement::withTrashed()->find($this->object_id);
    }

    /**
     * Transaction type mutator
     *
     * @throws \Exception
     */
    public function setTypeAttribute($value): void
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
            case self::WALLET_REFUND:
            case self::WALLET_CHARGEBACK:
                // TODO: This must be a wallet.
                $this->attributes['type'] = $value;
                break;

            default:
                throw new \Exception("Invalid type value");
        }
    }

    /**
     * Returns a short text describing the transaction.
     *
     * @return string The description
     */
    public function shortDescription(): string
    {
        $label = $this->objectTypeToLabelString() . '-' . $this->{'type'} . '-short';

        $result = \trans("transactions.{$label}", $this->descriptionParams());

        return trim($result, ': ');
    }

    /**
     * Returns a text describing the transaction.
     *
     * @return string The description
     */
    public function toString(): string
    {
        $label = $this->objectTypeToLabelString() . '-' . $this->{'type'};

        return \trans("transactions.{$label}", $this->descriptionParams());
    }

    /**
     * Returns a wallet to which the transaction is assigned (if any)
     *
     * @return \App\Wallet|null The wallet
     */
    public function wallet(): ?Wallet
    {
        if ($this->object_type !== Wallet::class) {
            return null;
        }

        return Wallet::find($this->object_id);
    }

    /**
     * Collect transaction parameters used in (localized) descriptions
     *
     * @return array Parameters
     */
    private function descriptionParams(): array
    {
        $result = [
            'user_email' => $this->user_email,
            'description' => $this->description,
        ];

        $amount = $this->amount * ($this->amount < 0 ? -1 : 1);

        if ($entitlement = $this->entitlement()) {
            $wallet = $entitlement->wallet;
            $cost = $entitlement->cost;
            $discount = $entitlement->wallet->getDiscountRate();

            $result['entitlement_cost'] = $cost * $discount;
            $result['object'] = $entitlement->entitleableTitle();
            $result['sku_title'] = $entitlement->sku->title;
        } else {
            $wallet = $this->wallet();
        }

        $result['wallet'] = $wallet->description ?: 'Default wallet';
        $result['amount'] = $wallet->money($amount);

        return $result;
    }

    /**
     * Get a string for use in translation tables derived from the object type.
     *
     * @return string|null
     */
    private function objectTypeToLabelString(): ?string
    {
        if ($this->object_type == Entitlement::class) {
            return 'entitlement';
        }

        if ($this->object_type == Wallet::class) {
            return 'wallet';
        }

        return null;
    }
}
