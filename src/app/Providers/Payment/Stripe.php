<?php

namespace App\Providers\Payment;

use App\Payment;
use App\Utils;
use App\Wallet;
use App\WalletSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
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
        $customer_id = self::stripeCustomerId($wallet, false);

        if (!$customer_id) {
            return null;
        }

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
        $customer_id = self::stripeCustomerId($wallet, true);

        $request = [
            'customer' => $customer_id,
            'cancel_url' => Utils::serviceUrl('/wallet'), // required
            'success_url' => Utils::serviceUrl('/wallet'), // required
            'payment_method_types' => ['card'], // required
            'locale' => 'en',
            'mode' => 'setup',
        ];

        $session = StripeAPI\Checkout\Session::create($request);

        $payment = [
            'id' => $session->setup_intent,
            'type' => self::TYPE_MANDATE,
        ];

        $this->storePayment($payment, $wallet->id);

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
            'method' => self::paymentMethod($pm, 'Unknown method')
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
        $customer_id = self::stripeCustomerId($wallet, true);

        $request = [
            'customer' => $customer_id,
            'cancel_url' => Utils::serviceUrl('/wallet'), // required
            'success_url' => Utils::serviceUrl('/wallet'), // required
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
        $payment['id'] = $session->payment_intent;

        $this->storePayment($payment, $wallet->id);

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
            // Invalid payload
            return 400;
        }

        switch ($event->type) {
            case StripeAPI\Event::PAYMENT_INTENT_CANCELED:
            case StripeAPI\Event::PAYMENT_INTENT_PAYMENT_FAILED:
            case StripeAPI\Event::PAYMENT_INTENT_SUCCEEDED:
                $intent = $event->data->object; // @phpstan-ignore-line
                $payment = Payment::find($intent->id);

                if (empty($payment) || $payment->type == self::TYPE_MANDATE) {
                    return 404;
                }

                switch ($intent->status) {
                    case StripeAPI\PaymentIntent::STATUS_CANCELED:
                        $status = self::STATUS_CANCELED;
                        break;
                    case StripeAPI\PaymentIntent::STATUS_SUCCEEDED:
                        $status = self::STATUS_PAID;
                        break;
                    default:
                        $status = self::STATUS_FAILED;
                }

                DB::beginTransaction();

                if ($status == self::STATUS_PAID) {
                    // Update the balance, if it wasn't already
                    if ($payment->status != self::STATUS_PAID) {
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

                if ($payment->status != self::STATUS_PAID) {
                    $payment->status = $status;
                    $payment->save();

                    if ($status != self::STATUS_CANCELED && $payment->type == self::TYPE_RECURRING) {
                        // Disable the mandate
                        if ($status == self::STATUS_FAILED) {
                            $payment->wallet->setSetting('mandate_disabled', 1);
                        }

                        // Notify the user
                        \App\Jobs\PaymentEmail::dispatch($payment);
                    }
                }

                DB::commit();

                break;

            case StripeAPI\Event::SETUP_INTENT_SUCCEEDED:
            case StripeAPI\Event::SETUP_INTENT_SETUP_FAILED:
            case StripeAPI\Event::SETUP_INTENT_CANCELED:
                $intent = $event->data->object; // @phpstan-ignore-line
                $payment = Payment::find($intent->id);

                if (empty($payment) || $payment->type != self::TYPE_MANDATE) {
                    return 404;
                }

                switch ($intent->status) {
                    case StripeAPI\SetupIntent::STATUS_CANCELED:
                        $status = self::STATUS_CANCELED;
                        break;
                    case StripeAPI\SetupIntent::STATUS_SUCCEEDED:
                        $status = self::STATUS_PAID;
                        break;
                    default:
                        $status = self::STATUS_FAILED;
                }

                if ($status == self::STATUS_PAID) {
                    $payment->wallet->setSetting('stripe_mandate_id', $intent->id);
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
     * @param \App\Wallet $wallet The wallet
     * @param bool        $create Create the customer if does not exist yet
     *
     * @return string|null Stripe customer identifier
     */
    protected static function stripeCustomerId(Wallet $wallet, bool $create = false): ?string
    {
        $customer_id = $wallet->getSetting('stripe_id');

        // Register the user in Stripe
        if (empty($customer_id) && $create) {
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

        // TODO: Localization?
        $description = $payment->type == self::TYPE_RECURRING ? 'Auto-payment' : 'Payment';
        $description .= " transaction {$payment->id} using {$method}";

        $payment->wallet->credit($payment->amount, $description);

        // Unlock the disabled auto-payment mandate
        if ($payment->wallet->balance >= 0) {
            $payment->wallet->setSetting('mandate_disabled', null);
        }
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
}
