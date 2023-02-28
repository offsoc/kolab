<?php

namespace App;

use App\Providers\PaymentProvider;
use Dyrynda\Database\Support\NullableFields;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment operation on a wallet.
 *
 * @property int         $amount            Amount of money in cents of system currency (payment provider)
 * @property int         $credit_amount     Amount of money in cents of system currency (wallet balance)
 * @property string      $description       Payment description
 * @property string      $id                Mollie's Payment ID
 * @property ?string     $vat_rate_id       VAT rate identifier
 * @property \App\Wallet $wallet            The wallet
 * @property string      $wallet_id         The ID of the wallet
 * @property string      $currency          Currency of this payment
 * @property int         $currency_amount   Amount of money in cents of $currency
 */
class Payment extends Model
{
    use NullableFields;

    /** @var bool Indicates that the model should be timestamped or not */
    public $incrementing = false;

    /** @var string The "type" of the auto-incrementing ID */
    protected $keyType = 'string';

    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'amount' => 'integer',
        'credit_amount' => 'integer',
        'currency_amount' => 'integer',
    ];

    /** @var array<int,string> The attributes that are mass assignable */
    protected $fillable = [
        'id',
        'wallet_id',
        'amount',
        'credit_amount',
        'description',
        'provider',
        'status',
        'vat_rate_id',
        'type',
        'currency',
        'currency_amount',
    ];

    /** @var array<int, string> The attributes that can be not set */
    protected $nullable = [
        'vat_rate_id',
    ];


    /**
     * Create a payment record in DB from array.
     *
     * @param array $payment Payment information (required: id, type, wallet_id, currency, amount, currency_amount)
     *
     * @return \App\Payment Payment object
     */
    public static function createFromArray(array $payment): Payment
    {
        $db_payment = new Payment();
        $db_payment->id = $payment['id'];
        $db_payment->description = $payment['description'] ?? '';
        $db_payment->status = $payment['status'] ?? PaymentProvider::STATUS_OPEN;
        $db_payment->amount = $payment['amount'] ?? 0;
        $db_payment->credit_amount = $payment['credit_amount'] ?? ($payment['amount'] ?? 0);
        $db_payment->vat_rate_id = $payment['vat_rate_id'] ?? null;
        $db_payment->type = $payment['type'];
        $db_payment->wallet_id = $payment['wallet_id'];
        $db_payment->provider = $payment['provider'] ?? '';
        $db_payment->currency = $payment['currency'];
        $db_payment->currency_amount = $payment['currency_amount'];
        $db_payment->save();

        return $db_payment;
    }

    /**
     * Creates a payment and transaction records for the refund/chargeback operation.
     * Deducts an amount of pecunia from the wallet.
     *
     * @param array $refund A refund or chargeback data (id, type, amount, currency, description)
     *
     * @return ?\App\Payment A payment object for the refund
     */
    public function refund(array $refund): ?Payment
    {
        if (empty($refund) || empty($refund['amount'])) {
            return null;
        }

        // Convert amount to wallet currency (use the same exchange rate as for the original payment)
        // Note: We assume a refund is always using the same currency
        $exchange_rate = $this->amount / $this->currency_amount;
        $credit_amount = $amount = (int) round($refund['amount'] * $exchange_rate);

        // Set appropriate credit_amount if original credit_amount != original amount
        if ($this->amount != $this->credit_amount) {
            $credit_amount = (int) round($amount * ($this->credit_amount / $this->amount));
        }

        // Apply the refund to the wallet balance
        $method = $refund['type'] == PaymentProvider::TYPE_CHARGEBACK ? 'chargeback' : 'refund';

        $this->wallet->{$method}($credit_amount, $refund['description'] ?? '');

        $refund['amount'] = $amount * -1;
        $refund['credit_amount'] = $credit_amount * -1;
        $refund['currency_amount'] = round($amount * -1 / $exchange_rate);
        $refund['currency'] = $this->currency;
        $refund['wallet_id'] = $this->wallet_id;
        $refund['provider'] = $this->provider;
        $refund['vat_rate_id'] = $this->vat_rate_id;
        $refund['status'] = PaymentProvider::STATUS_PAID;

        // FIXME: Refunds/chargebacks are out of the reseller comissioning for now

        return self::createFromArray($refund);
    }

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
        return $this->belongsTo(Wallet::class, 'wallet_id', 'id');
    }

    /**
     * The VAT rate assigned to this payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vatRate()
    {
        return $this->belongsTo(VatRate::class, 'vat_rate_id', 'id');
    }
}
