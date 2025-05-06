<?php

namespace App\Providers\Payment;

use App\Jobs\Mail\PaymentJob;
use App\Jobs\Wallet\ChargeJob;
use App\Payment;
use App\Providers\PaymentProvider;
use App\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Stripe as StripeAPI;

class Stripe extends PaymentProvider
{
    /**
     * Class constructor.
     */
    public function __construct()
    {
        StripeAPI\Stripe::setApiKey(\config('services.stripe.key'));
    }

    /**
     * Get a link to the customer in the provider's control panel
     *
     * @param Wallet $wallet The wallet
     *
     * @return string|null The string representing <a> tag
     */
    public function customerLink(Wallet $wallet): ?string
    {
        $customer_id = self::stripeCustomerId($wallet, false);

        if (!$customer_id) {
            return null;
        }

        $location = 'https://dashboard.stripe.com';

        $key = \config('services.stripe.key');

        if (str_starts_with($key, 'sk_test_')) {
            $location .= '/test';
        }

        return sprintf(
            '<a href="%s/customers/%s" target="_blank">%s</a>',
            $location,
            $customer_id,
            $customer_id
        );
    }

    /**
     * Create a new auto-payment mandate for a wallet.
     *
     * @param Wallet $wallet  The wallet
     * @param array  $payment Payment data:
     *                        - amount: Value in cents (not used)
     *                        - currency: The operation currency
     *                        - description: Operation desc.
     *                        - redirectUrl: The location to goto after checkout
     *
     * @return array Provider payment/session data:
     *               - id: Session identifier
     */
    public function createMandate(Wallet $wallet, array $payment): ?array
    {
        // Register the user in Stripe, if not yet done
        $customer_id = self::stripeCustomerId($wallet, true);

        $request = [
            'customer' => $customer_id,
            'cancel_url' => $payment['redirectUrl'] ?? self::redirectUrl(), // required
            'success_url' => $payment['redirectUrl'] ?? self::redirectUrl(), // required
            'payment_method_types' => ['card'], // required
            'locale' => 'en',
            'mode' => 'setup',
        ];

        // Note: Stripe does not allow to set amount for 'setup' operation
        // We'll dispatch Wallet\ChargeJob when we receive a webhook request

        $session = StripeAPI\Checkout\Session::create($request);

        $payment['amount'] = 0;
        $payment['credit_amount'] = 0;
        $payment['currency_amount'] = 0;
        $payment['vat_rate_id'] = null;
        $payment['id'] = $session->setup_intent;
        $payment['type'] = Payment::TYPE_MANDATE;

        $this->storePayment($payment, $wallet->id);

        return [
            'id' => $session->id,
        ];
    }

    /**
     * Revoke the auto-payment mandate.
     *
     * @param Wallet $wallet The wallet
     *
     * @return bool True on success, False on failure
     */
    public function deleteMandate(Wallet $wallet): bool
    {
        // Get the Mandate info
        $mandate = self::stripeMandate($wallet);

        if ($mandate) {
            // Remove the reference
            $wallet->setSetting('stripe_mandate_id', null);

            // Detach the payment method on Stripe
            $pm = StripeAPI\PaymentMethod::retrieve($mandate->payment_method);
            $pm->detach();
        }

        return true;
    }

    /**
     * Get a auto-payment mandate for a wallet.
     *
     * @param Wallet $wallet The wallet
     *
     * @return array|null Mandate information:
     *                    - id: Mandate identifier
     *                    - method: user-friendly payment method desc.
     *                    - isPending: the process didn't complete yet
     *                    - isValid: the mandate is valid
     */
    public function getMandate(Wallet $wallet): ?array
    {
        // Get the Mandate info
        $mandate = self::stripeMandate($wallet);

        if (empty($mandate)) {
            return null;
        }

        $pm = StripeAPI\PaymentMethod::retrieve($mandate->payment_method);

        $result = [
            'id' => $mandate->id,
            'isPending' => $mandate->status != 'succeeded' && $mandate->status != 'canceled',
            'isValid' => $mandate->status == 'succeeded',
            'method' => self::paymentMethod($pm, 'Unknown method'),
        ];

        return $result;
    }

    /**
     * Get a provider name
     *
     * @return string Provider name
     */
    public function name(): string
    {
        return 'stripe';
    }

    /**
     * Create a new payment.
     *
     * @param Wallet $wallet  The wallet
     * @param array  $payment payment data:
     *                        - amount: Value in cents
     *                        - currency: The operation currency
     *                        - type: first/oneoff/recurring
     *                        - description: Operation desc
     *
     * @return array Provider payment/session data:
     *               - id: Session identifier
     */
    public function payment(Wallet $wallet, array $payment): ?array
    {
        if ($payment['type'] == Payment::TYPE_RECURRING) {
            return $this->paymentRecurring($wallet, $payment);
        }

        // Register the user in Stripe, if not yet done
        $customer_id = self::stripeCustomerId($wallet, true);

        $amount = $this->exchange($payment['amount'], $wallet->currency, $payment['currency']);
        $payment['currency_amount'] = $amount;

        $request = [
            'customer' => $customer_id,
            'cancel_url' => self::redirectUrl(), // required
            'success_url' => self::redirectUrl(), // required
            'payment_method_types' => ['card'], // required
            'locale' => 'en',
            'line_items' => [
                [
                    'name' => $payment['description'],
                    'amount' => $amount,
                    'currency' => \strtolower($payment['currency']),
                    'quantity' => 1,
                ],
            ],
        ];

        $session = StripeAPI\Checkout\Session::create($request);

        // Store the payment reference in database
        $payment['id'] = $session->payment_intent;

        $this->storePayment($payment, $wallet->id);

        return [
            'id' => $session->id,
        ];
    }

    /**
     * Create a new automatic payment operation.
     *
     * @param Wallet $wallet  The wallet
     * @param array  $payment Payment data (see self::payment())
     *
     * @return array Provider payment/session data:
     *               - id: Session identifier
     */
    protected function paymentRecurring(Wallet $wallet, array $payment): ?array
    {
        // Check if there's a valid mandate
        $mandate = self::stripeMandate($wallet);

        if (empty($mandate)) {
            return null;
        }

        $amount = $this->exchange($payment['amount'], $wallet->currency, $payment['currency']);
        $payment['currency_amount'] = $amount;

        $request = [
            'amount' => $amount,
            'currency' => \strtolower($payment['currency']),
            'description' => $payment['description'],
            'receipt_email' => $wallet->owner->email,
            'customer' => $mandate->customer,
            'payment_method' => $mandate->payment_method,
            'off_session' => true,
            'confirm' => true,
        ];

        $intent = StripeAPI\PaymentIntent::create($request);

        // Store the payment reference in database
        $payment['id'] = $intent->id;

        $this->storePayment($payment, $wallet->id);

        return [
            'id' => $payment['id'],
        ];
    }

    /**
     * Update payment status (and balance).
     *
     * @return int HTTP response code
     */
    public function webhook(): int
    {
        // We cannot just use php://input as it's already "emptied" by the framework
        // $payload = file_get_contents('php://input');
        $request = Request::instance();
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        // Parse and validate the input
        try {
            $event = StripeAPI\Webhook::constructEvent(
                $payload,
                $sig_header,
                \config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            \Log::error("Invalid payload: " . $e->getMessage());
            // Invalid payload
            return 400;
        }

        switch ($event->type) {
            case StripeAPI\Event::PAYMENT_INTENT_CANCELED:
            case StripeAPI\Event::PAYMENT_INTENT_PAYMENT_FAILED:
            case StripeAPI\Event::PAYMENT_INTENT_SUCCEEDED:
                $intent = $event->data->object; // @phpstan-ignore-line
                $payment = Payment::find($intent->id);

                if (empty($payment) || $payment->type == Payment::TYPE_MANDATE) {
                    return 404;
                }

                switch ($intent->status) {
                    case StripeAPI\PaymentIntent::STATUS_CANCELED:
                        $status = Payment::STATUS_CANCELED;
                        break;
                    case StripeAPI\PaymentIntent::STATUS_SUCCEEDED:
                        $status = Payment::STATUS_PAID;
                        break;
                    default:
                        $status = Payment::STATUS_FAILED;
                }

                DB::beginTransaction();

                if ($status == Payment::STATUS_PAID) {
                    // Update the balance, if it wasn't already
                    if ($payment->status != Payment::STATUS_PAID) {
                        $this->creditPayment($payment, $intent);
                    }
                } else {
                    if (!empty($intent->last_payment_error)) {
                        // See https://stripe.com/docs/error-codes for more info
                        \Log::info(sprintf(
                            'Stripe payment failed (%s): %s',
                            $payment->id,
                            json_encode($intent->last_payment_error)
                        ));
                    }
                }

                if ($payment->status != Payment::STATUS_PAID) {
                    $payment->status = $status;
                    $payment->save();

                    if ($status != Payment::STATUS_CANCELED && $payment->type == Payment::TYPE_RECURRING) {
                        // Disable the mandate
                        if ($status == Payment::STATUS_FAILED) {
                            $payment->wallet->setSetting('mandate_disabled', '1');
                        }

                        // Notify the user
                        PaymentJob::dispatch($payment);
                    }
                }

                DB::commit();

                break;
            case StripeAPI\Event::SETUP_INTENT_SUCCEEDED:
            case StripeAPI\Event::SETUP_INTENT_SETUP_FAILED:
            case StripeAPI\Event::SETUP_INTENT_CANCELED:
                $intent = $event->data->object; // @phpstan-ignore-line
                $payment = Payment::find($intent->id);

                if (empty($payment) || $payment->type != Payment::TYPE_MANDATE) {
                    return 404;
                }

                switch ($intent->status) {
                    case StripeAPI\SetupIntent::STATUS_CANCELED:
                        $status = Payment::STATUS_CANCELED;
                        break;
                    case StripeAPI\SetupIntent::STATUS_SUCCEEDED:
                        $status = Payment::STATUS_PAID;
                        break;
                    default:
                        $status = Payment::STATUS_FAILED;
                }

                if ($status == Payment::STATUS_PAID) {
                    $payment->wallet->setSetting('stripe_mandate_id', $intent->id);
                    $threshold = (int) round((float) $payment->wallet->getSetting('mandate_balance') * 100);

                    // Call credit() so wallet/account state is updated
                    $this->creditPayment($payment, $intent);

                    // Top-up the wallet if balance is below the threshold
                    if ($payment->wallet->balance < $threshold && $payment->status != Payment::STATUS_PAID) {
                        ChargeJob::dispatch($payment->wallet->id);
                    }
                }

                $payment->status = $status;
                $payment->save();

                break;
            default:
                \Log::debug("Unhandled Stripe event: " . var_export($payload, true));
                break;
        }

        return 200;
    }

    /**
     * Get Stripe customer identifier for specified wallet.
     * Create one if does not exist yet.
     *
     * @param Wallet $wallet The wallet
     * @param bool   $create Create the customer if does not exist yet
     *
     * @return string|null Stripe customer identifier
     */
    protected static function stripeCustomerId(Wallet $wallet, bool $create = false): ?string
    {
        $customer_id = $wallet->getSetting('stripe_id');

        // Register the user in Stripe
        if (empty($customer_id) && $create) {
            $customer = StripeAPI\Customer::create([
                'name' => $wallet->owner->name(),
                // Stripe will display the email on Checkout page, editable,
                // and use it to send the receipt (?), use the user email here
                // 'email' => $wallet->id . '@private.' . \config('app.domain'),
                'email' => $wallet->owner->email,
            ]);

            $customer_id = $customer->id;

            $wallet->setSetting('stripe_id', $customer->id);
        }

        return $customer_id;
    }

    /**
     * Get the active Stripe auto-payment mandate (Setup Intent)
     */
    protected static function stripeMandate(Wallet $wallet)
    {
        // Note: Stripe also has 'Mandate' objects, but we do not use these

        if ($mandate_id = $wallet->getSetting('stripe_mandate_id')) {
            $mandate = StripeAPI\SetupIntent::retrieve($mandate_id);
            if ($mandate && $mandate->status != 'canceled') {
                return $mandate;
            }
        }
    }

    /**
     * Apply the successful payment's pecunia to the wallet
     */
    protected static function creditPayment(Payment $payment, $intent)
    {
        $method = 'Stripe';

        // Extract the payment method for transaction description
        if (
            !empty($intent->charges)
            && ($charge = $intent->charges->data[0])
            && ($pm = $charge->payment_method_details)
        ) {
            $method = self::paymentMethod($pm);
        }

        $payment->credit($method);
    }

    /**
     * Extract payment method description from Stripe payment details
     */
    protected static function paymentMethod($details, $default = ''): string
    {
        switch ($details->type) {
            case 'card':
                // TODO: card number
                return \sprintf(
                    '%s (**** **** **** %s)',
                    \ucfirst($details->card->brand) ?: 'Card',
                    $details->card->last4
                );
        }

        return $default;
    }

    /**
     * List supported payment methods.
     *
     * @param string $type     the payment type for which we require a method (oneoff/recurring)
     * @param string $currency Currency code
     *
     * @return array Array of array with available payment methods:
     *               - id: id of the method
     *               - name: User readable name of the payment method
     *               - minimumAmount: Minimum amount to be charged in cents
     *               - currency: Currency used for the method
     *               - exchangeRate: The projected exchange rate (actual rate is determined during payment)
     *               - icon: An icon (icon name) representing the method
     */
    public function providerPaymentMethods(string $type, string $currency): array
    {
        // TODO get this from the stripe API?
        $availableMethods = [];
        switch ($type) {
            case Payment::TYPE_ONEOFF:
                $availableMethods = [
                    self::METHOD_CREDITCARD => [
                        'id' => self::METHOD_CREDITCARD,
                        'name' => "Credit Card",
                        'minimumAmount' => Payment::MIN_AMOUNT,
                        'currency' => $currency,
                        'exchangeRate' => 1.0,
                    ],
                    self::METHOD_PAYPAL => [
                        'id' => self::METHOD_PAYPAL,
                        'name' => "PayPal",
                        'minimumAmount' => Payment::MIN_AMOUNT,
                        'currency' => $currency,
                        'exchangeRate' => 1.0,
                    ],
                ];
                break;
            case Payment::TYPE_RECURRING:
                $availableMethods = [
                    self::METHOD_CREDITCARD => [
                        'id' => self::METHOD_CREDITCARD,
                        'name' => "Credit Card",
                        'minimumAmount' => Payment::MIN_AMOUNT, // Converted to cents,
                        'currency' => $currency,
                        'exchangeRate' => 1.0,
                    ],
                ];
                break;
        }

        return $availableMethods;
    }

    /**
     * Get a payment.
     *
     * @param string $paymentId Payment identifier
     *
     * @return array Payment information:
     *               - id: Payment identifier
     *               - status: Payment status
     *               - isCancelable: The payment can be canceled
     *               - checkoutUrl: The checkout url to complete the payment or null if none
     */
    public function getPayment($paymentId): array
    {
        \Log::info("Stripe::getPayment does not yet retrieve a checkoutUrl.");

        $payment = StripeAPI\PaymentIntent::retrieve($paymentId);
        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'isCancelable' => false,
            'checkoutUrl' => null,
        ];
    }
}
