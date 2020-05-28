<?php

namespace App\Providers\Payment;

use App\Payment;
use App\Utils;
use App\Wallet;
use App\WalletSetting;
use Stripe as StripeAPI;

class Stripe extends \App\Providers\PaymentProvider
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
     * @param \App\Wallet $wallet The wallet
     *
     * @return string|null The string representing <a> tag
     */
    public function customerLink(Wallet $wallet): ?string
    {
        $customer_id = self::stripeCustomerId($wallet);

        $location = 'https://dashboard.stripe.com';

        $key = \config('services.stripe.key');

        if (strpos($key, 'sk_test_') === 0) {
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
     * @param \App\Wallet $wallet  The wallet
     * @param array       $payment Payment data:
     *                             - amount: Value in cents
     *                             - currency: The operation currency
     *                             - description: Operation desc.
     *
     * @return array Provider payment/session data:
     *               - id: Session identifier
     */
    public function createMandate(Wallet $wallet, array $payment): ?array
    {
        // Register the user in Stripe, if not yet done
        $customer_id = self::stripeCustomerId($wallet);

        $request = [
            'customer' => $customer_id,
            'cancel_url' => \url('/wallet'), // required
            'success_url' => \url('/wallet'), // required
            'payment_method_types' => ['card'], // required
            'locale' => 'en',
            'mode' => 'setup',
        ];

        $session = StripeAPI\Checkout\Session::create($request);

        return [
            'id' => $session->id,
        ];
    }

    /**
     * Revoke the auto-payment mandate.
     *
     * @param \App\Wallet $wallet The wallet
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
     * @param \App\Wallet $wallet The wallet
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
        ];

        switch ($pm->type) {
            case 'card':
                // TODO: card number
                $result['method'] = \sprintf(
                    '%s (**** **** **** %s)',
                    // @phpstan-ignore-next-line
                    \ucfirst($pm->card->brand) ?: 'Card',
                    // @phpstan-ignore-next-line
                    $pm->card->last4
                );

                break;

            default:
                $result['method'] = 'Unknown method';
        }

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
     * @param \App\Wallet $wallet  The wallet
     * @param array       $payment Payment data:
     *                             - amount: Value in cents
     *                             - currency: The operation currency
     *                             - type: first/oneoff/recurring
     *                             - description: Operation desc.
     *
     * @return array Provider payment/session data:
     *               - id: Session identifier
     */
    public function payment(Wallet $wallet, array $payment): ?array
    {
        if ($payment['type'] == self::TYPE_RECURRING) {
            return $this->paymentRecurring($wallet, $payment);
        }

        // Register the user in Stripe, if not yet done
        $customer_id = self::stripeCustomerId($wallet);

        $request = [
            'customer' => $customer_id,
            'cancel_url' => \url('/wallet'), // required
            'success_url' => \url('/wallet'), // required
            'payment_method_types' => ['card'], // required
            'locale' => 'en',
            'line_items' => [
                [
                    'name' => $payment['description'],
                    'amount' => $payment['amount'],
                    'currency' => \strtolower($payment['currency']),
                    'quantity' => 1,
                ]
            ]
        ];

        $session = StripeAPI\Checkout\Session::create($request);

        // Store the payment reference in database
        $payment['status'] = self::STATUS_OPEN;
        $payment['id'] = $session->payment_intent;

        self::storePayment($payment, $wallet->id);

        return [
            'id' => $session->id,
        ];
    }

    /**
     * Create a new automatic payment operation.
     *
     * @param \App\Wallet $wallet  The wallet
     * @param array       $payment Payment data (see self::payment())
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

        $request = [
            'amount' => $payment['amount'],
            'currency' => \strtolower($payment['currency']),
            'description' => $payment['description'],
            'locale' => 'en',
            'off_session' => true,
            'receipt_email' => $wallet->owner->email,
            'customer' => $mandate->customer,
            'payment_method' => $mandate->payment_method,
        ];

        $intent = StripeAPI\PaymentIntent::create($request);

        // Store the payment reference in database
        $payment['status'] = self::STATUS_OPEN;
        $payment['id'] = $intent->id;

        self::storePayment($payment, $wallet->id);

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
        $payload = file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

        // Parse and validate the input
        try {
            $event = StripeAPI\Webhook::constructEvent(
                $payload,
                $sig_header,
                \config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return 400;
        }

        switch ($event->type) {
            case StripeAPI\Event::PAYMENT_INTENT_CANCELED:
            case StripeAPI\Event::PAYMENT_INTENT_PAYMENT_FAILED:
            case StripeAPI\Event::PAYMENT_INTENT_SUCCEEDED:
                $intent = $event->data->object; // @phpstan-ignore-line
                $payment = Payment::find($intent->id);

                switch ($intent->status) {
                    case StripeAPI\PaymentIntent::STATUS_CANCELED:
                        $status = self::STATUS_CANCELED;
                        break;
                    case StripeAPI\PaymentIntent::STATUS_SUCCEEDED:
                        $status = self::STATUS_PAID;
                        break;
                    default:
                        $status = self::STATUS_PENDING;
                }

                if ($status == self::STATUS_PAID) {
                    // Update the balance, if it wasn't already
                    if ($payment->status != self::STATUS_PAID) {
                        $payment->wallet->credit($payment->amount);
                    }
                } elseif (!empty($intent->last_payment_error)) {
                    // See https://stripe.com/docs/error-codes for more info
                    \Log::info(sprintf(
                        'Stripe payment failed (%s): %s',
                        $payment->id,
                        json_encode($intent->last_payment_error)
                    ));
                }

                if ($payment->status != self::STATUS_PAID) {
                    $payment->status = $status;
                    $payment->save();
                }

                break;

            case StripeAPI\Event::SETUP_INTENT_SUCCEEDED:
                $intent = $event->data->object; // @phpstan-ignore-line

                // Find the wallet
                // TODO: This query is potentially slow, we should find another way
                //       Maybe use payment/transactions table to store the reference
                $setting = WalletSetting::where('key', 'stripe_id')
                    ->where('value', $intent->customer)->first();

                if ($setting) {
                    $setting->wallet->setSetting('stripe_mandate_id', $intent->id);
                }

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
     * @param \App\Wallet $wallet The wallet
     *
     * @return string Stripe customer identifier
     */
    protected static function stripeCustomerId(Wallet $wallet): string
    {
        $customer_id = $wallet->getSetting('stripe_id');

        // Register the user in Stripe
        if (empty($customer_id)) {
            $customer = StripeAPI\Customer::create([
                    'name'  => $wallet->owner->name(),
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
            // @phpstan-ignore-next-line
            if ($mandate && $mandate->status != 'canceled') {
                return $mandate;
            }
        }
    }
}
