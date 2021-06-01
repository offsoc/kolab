<?php

namespace App\Providers;

use App\Transaction;
use App\Payment;
use App\Wallet;
use Illuminate\Support\Facades\Cache;

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
    public const TYPE_REFUND = 'refund';
    public const TYPE_CHARGEBACK = 'chargeback';

    public const METHOD_CREDITCARD = 'creditcard';
    public const METHOD_PAYPAL = 'paypal';
    public const METHOD_BANKTRANSFER = 'banktransfer';
    public const METHOD_DIRECTDEBIT = 'directdebit';

    public const PROVIDER_MOLLIE = 'mollie';
    public const PROVIDER_STRIPE = 'stripe';

    /** const int Minimum amount of money in a single payment (in cents) */
    public const MIN_AMOUNT = 1000;

    private static $paymentMethodIcons = [
        self::METHOD_CREDITCARD => ['prefix' => 'far', 'name' => 'credit-card'],
        self::METHOD_PAYPAL => ['prefix' => 'fab', 'name' => 'paypal'],
        self::METHOD_BANKTRANSFER => ['prefix' => 'fas', 'name' => 'university']
    ];

    /**
     * Detect the name of the provider
     *
     * @param \App\Wallet|string|null $provider_or_wallet
     * @return string The name of the provider
     */
    private static function providerName($provider_or_wallet = null): string
    {
        if ($provider_or_wallet instanceof Wallet) {
            if ($provider_or_wallet->getSetting('stripe_id')) {
                $provider = self::PROVIDER_STRIPE;
            } elseif ($provider_or_wallet->getSetting('mollie_id')) {
                $provider = self::PROVIDER_MOLLIE;
            }
        } else {
            $provider = $provider_or_wallet;
        }

        if (empty($provider)) {
            $provider = \config('services.payment_provider') ?: self::PROVIDER_MOLLIE;
        }

        return \strtolower($provider);
    }

    /**
     * Factory method
     *
     * @param \App\Wallet|string|null $provider_or_wallet
     */
    public static function factory($provider_or_wallet = null)
    {
        switch (self::providerName($provider_or_wallet)) {
            case self::PROVIDER_STRIPE:
                return new \App\Providers\Payment\Stripe();

            case self::PROVIDER_MOLLIE:
                return new \App\Providers\Payment\Mollie();

            default:
                throw new \Exception("Invalid payment provider: {$provider_or_wallet}");
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
     *                             - methodId: Payment method
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
     *                    - methodId: Payment method
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
     *                             - methodId: Payment method
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
        $db_payment->currency = $payment['currency'];
        $db_payment->currency_amount = $payment['currency_amount'];
        $db_payment->save();

        return $db_payment;
    }

    /**
     * Retrieve an exchange rate.
     *
     * @param string $sourceCurrency Currency from which to convert
     * @param string $targetCurrency Currency to convert to
     *
     * @return float Exchange rate
     */
    protected function exchangeRate(string $sourceCurrency, string $targetCurrency): float
    {
        if (strcasecmp($sourceCurrency, $targetCurrency)) {
            throw new \Exception("Currency conversion is not yet implemented.");
            //FIXME Not yet implemented
        }
        return 1.0;
    }

    /**
     * Convert a value from $sourceCurrency to $targetCurrency
     *
     * @param int    $amount         Amount in cents of $sourceCurrency
     * @param string $sourceCurrency Currency from which to convert
     * @param string $targetCurrency Currency to convert to
     *
     * @return int Exchanged amount in cents of $targetCurrency
     */
    protected function exchange(int $amount, string $sourceCurrency, string $targetCurrency): int
    {
        return intval(round($amount * $this->exchangeRate($sourceCurrency, $targetCurrency)));
    }

    /**
     * Deduct an amount of pecunia from the wallet.
     * Creates a payment and transaction records for the refund/chargeback operation.
     *
     * @param \App\Wallet $wallet A wallet object
     * @param array       $refund A refund or chargeback data (id, type, amount, description)
     *
     * @return void
     */
    protected function storeRefund(Wallet $wallet, array $refund): void
    {
        if (empty($refund) || empty($refund['amount'])) {
            return;
        }

        // Preserve originally refunded amount
        $refund['currency_amount'] = $refund['amount'];

        // Convert amount to wallet currency
        // TODO We should possibly be using the same exchange rate as for the original payment?
        $amount = $this->exchange($refund['amount'], $refund['currency'], $wallet->currency);

        $wallet->balance -= $amount;
        $wallet->save();

        if ($refund['type'] == self::TYPE_CHARGEBACK) {
            $transaction_type = Transaction::WALLET_CHARGEBACK;
        } else {
            $transaction_type = Transaction::WALLET_REFUND;
        }

        Transaction::create([
                'object_id' => $wallet->id,
                'object_type' => Wallet::class,
                'type' => $transaction_type,
                'amount' => $amount * -1,
                'description' => $refund['description'] ?? '',
        ]);

        $refund['status'] = self::STATUS_PAID;
        $refund['amount'] = -1 * $amount;

        // FIXME: Refunds/chargebacks are out of the reseller comissioning for now

        $this->storePayment($refund, $wallet->id);
    }

    /**
     * List supported payment methods from this provider
     *
     * @param string $type The payment type for which we require a method (oneoff/recurring).
     *
     * @return array Array of array with available payment methods:
     *               - id: id of the method
     *               - name: User readable name of the payment method
     *               - minimumAmount: Minimum amount to be charged in cents
     *               - currency: Currency used for the method
     *               - exchangeRate: The projected exchange rate (actual rate is determined during payment)
     *               - icon: An icon (icon name) representing the method
     */
    abstract public function providerPaymentMethods($type): array;

    /**
     * Get a payment.
     *
     * @param string $paymentId Payment identifier
     *
     * @return array Payment information:
     *                    - id: Payment identifier
     *                    - status: Payment status
     *                    - isCancelable: The payment can be canceled
     *                    - checkoutUrl: The checkout url to complete the payment or null if none
     */
    abstract public function getPayment($paymentId): array;

    /**
     * Return an array of whitelisted payment methods with override values.
     *
     * @param string $type The payment type for which we require a method.
     *
     * @return array Array of methods
     */
    protected static function paymentMethodsWhitelist($type): array
    {
        switch ($type) {
            case self::TYPE_ONEOFF:
                return [
                    self::METHOD_CREDITCARD => [
                        'id' => self::METHOD_CREDITCARD,
                        'icon' => self::$paymentMethodIcons[self::METHOD_CREDITCARD]
                    ],
                    self::METHOD_PAYPAL => [
                        'id' => self::METHOD_PAYPAL,
                        'icon' => self::$paymentMethodIcons[self::METHOD_PAYPAL]
                    ],
                    // TODO Enable once we're ready to offer them
                    // self::METHOD_BANKTRANSFER => [
                    //     'id' => self::METHOD_BANKTRANSFER,
                    //     'icon' => self::$paymentMethodIcons[self::METHOD_BANKTRANSFER]
                    // ]
                ];
            case PaymentProvider::TYPE_RECURRING:
                return [
                    self::METHOD_CREDITCARD => [
                        'id' => self::METHOD_CREDITCARD,
                        'icon' => self::$paymentMethodIcons[self::METHOD_CREDITCARD]
                    ]
                ];
        }

        \Log::error("Unknown payment type: " . $type);
        return [];
    }

    /**
     * Return an array of whitelisted payment methods with override values.
     *
     * @param string $type The payment type for which we require a method.
     *
     * @return array Array of methods
     */
    private static function applyMethodWhitelist($type, $availableMethods): array
    {
        $methods = [];

        // Use only whitelisted methods, and apply values from whitelist (overriding the backend)
        $whitelistMethods = self::paymentMethodsWhitelist($type);
        foreach ($whitelistMethods as $id => $whitelistMethod) {
            if (array_key_exists($id, $availableMethods)) {
                $methods[] = array_merge($availableMethods[$id], $whitelistMethod);
            }
        }

        return $methods;
    }

    /**
     * List supported payment methods for $wallet
     *
     * @param \App\Wallet $wallet The wallet
     * @param string      $type   The payment type for which we require a method (oneoff/recurring).
     *
     * @return array Array of array with available payment methods:
     *               - id: id of the method
     *               - name: User readable name of the payment method
     *               - minimumAmount: Minimum amount to be charged in cents
     *               - currency: Currency used for the method
     *               - exchangeRate: The projected exchange rate (actual rate is determined during payment)
     *               - icon: An icon (icon name) representing the method
     */
    public static function paymentMethods(Wallet $wallet, $type): array
    {
        $providerName = self::providerName($wallet);

        $cacheKey = "methods-" . $providerName . '-' . $type;

        if ($methods = Cache::get($cacheKey)) {
            \Log::debug("Using payment method cache" . var_export($methods, true));
            return $methods;
        }

        $provider = PaymentProvider::factory($providerName);
        $methods = self::applyMethodWhitelist($type, $provider->providerPaymentMethods($type));

        Cache::put($cacheKey, $methods, now()->addHours(1));

        return $methods;
    }

    /**
     * Returns the full URL for the wallet page, used when returning from an external payment page.
     * Depending on the request origin it will return a URL for the User or Reseller UI.
     *
     * @return string The redirect URL
     */
    public static function redirectUrl(): string
    {
        $url = \App\Utils::serviceUrl('/wallet');
        $domain = preg_replace('/:[0-9]+$/', '', request()->getHttpHost());

        if (strpos($domain, 'reseller') === 0) {
            $url = preg_replace('|^(https?://)([^/]+)|', '\\1' . $domain, $url);
        }

        return $url;
    }
}
