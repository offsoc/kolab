<?php

namespace App\Providers;

use App\Payment;
use App\Wallet;

abstract class PaymentProvider
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PAID = 'paid';

    public const TYPE_ONEOFF = 'oneoff';
    public const TYPE_RECURRING = 'recurring';
    public const TYPE_MANDATE = 'mandate';

    /** const int Minimum amount of money in a single payment (in cents) */
    public const MIN_AMOUNT = 1000;

    /**
     * Factory method
     *
     * @param \App\Wallet|string|null $provider_or_wallet
     */
    public static function factory($provider_or_wallet = null)
    {
        if ($provider_or_wallet instanceof Wallet) {
            if ($provider_or_wallet->getSetting('stripe_id')) {
                $provider = 'stripe';
            } elseif ($provider_or_wallet->getSetting('mollie_id')) {
                $provider = 'mollie';
            }
        } else {
            $provider = $provider_or_wallet;
        }

        if (empty($provider)) {
            $provider = \config('services.payment_provider') ?: 'mollie';
        }

        switch (\strtolower($provider)) {
            case 'stripe':
                return new \App\Providers\Payment\Stripe();

            case 'mollie':
                return new \App\Providers\Payment\Mollie();

            default:
                throw new \Exception("Invalid payment provider: {$provider}");
        }
    }

    /**
     * Create a new auto-payment mandate for a wallet.
     *
     * @param \App\Wallet $wallet  The wallet
     * @param array       $payment Payment data:
     *                             - amount: Value in cents
     *                             - currency: The operation currency
     *                             - description: Operation desc.
     *
     * @return array Provider payment data:
     *               - id: Operation identifier
     *               - redirectUrl: the location to redirect to
     */
    abstract public function createMandate(Wallet $wallet, array $payment): ?array;

    /**
     * Revoke the auto-payment mandate for a wallet.
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return bool True on success, False on failure
     */
    abstract public function deleteMandate(Wallet $wallet): bool;

    /**
     * Get a auto-payment mandate for a wallet.
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return array|null Mandate information:
     *                    - id: Mandate identifier
     *                    - method: user-friendly payment method desc.
     *                    - isPending: the process didn't complete yet
     *                    - isValid: the mandate is valid
     */
    abstract public function getMandate(Wallet $wallet): ?array;

    /**
     * Get a link to the customer in the provider's control panel
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return string|null The string representing <a> tag
     */
    abstract public function customerLink(Wallet $wallet): ?string;

    /**
     * Get a provider name
     *
     * @return string Provider name
     */
    abstract public function name(): string;

    /**
     * Create a new payment.
     *
     * @param \App\Wallet $wallet  The wallet
     * @param array       $payment Payment data:
     *                             - amount: Value in cents
     *                             - currency: The operation currency
     *                             - type: first/oneoff/recurring
     *                             - description: Operation description
     *
     * @return array Provider payment/session data:
     *               - id: Operation identifier
     *               - redirectUrl
     */
    abstract public function payment(Wallet $wallet, array $payment): ?array;

    /**
     * Update payment status (and balance).
     *
     * @return int HTTP response code
     */
    abstract public function webhook(): int;

    /**
     * Create a payment record in DB
     *
     * @param array  $payment   Payment information
     * @param string $wallet_id Wallet ID
     *
     * @return \App\Payment Payment object
     */
    protected function storePayment(array $payment, $wallet_id): Payment
    {
        $db_payment = new Payment();
        $db_payment->id = $payment['id'];
        $db_payment->description = $payment['description'] ?? '';
        $db_payment->status = $payment['status'] ?? self::STATUS_OPEN;
        $db_payment->amount = $payment['amount'] ?? 0;
        $db_payment->type = $payment['type'];
        $db_payment->wallet_id = $wallet_id;
        $db_payment->provider = $this->name();
        $db_payment->save();

        return $db_payment;
    }
}
