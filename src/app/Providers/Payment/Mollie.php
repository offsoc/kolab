<?php

namespace App\Providers\Payment;

use App\Payment;
use App\Utils;
use App\Wallet;
use Illuminate\Support\Facades\DB;

class Mollie extends \App\Providers\PaymentProvider
{
    /**
     * Get a link to the customer in the provider's control panel
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return string|null The string representing <a> tag
     */
    public function customerLink(Wallet $wallet): ?string
    {
        $customer_id = self::mollieCustomerId($wallet);

        return sprintf(
            '<a href="https://www.mollie.com/dashboard/customers/%s" target="_blank">%s</a>',
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
     * @return array Provider payment data:
     *               - id: Operation identifier
     *               - redirectUrl: the location to redirect to
     */
    public function createMandate(Wallet $wallet, array $payment): ?array
    {
        // Register the user in Mollie, if not yet done
        $customer_id = self::mollieCustomerId($wallet);

        $request = [
            'amount' => [
                'currency' => $payment['currency'],
                'value' => '0.00',
            ],
            'customerId' => $customer_id,
            'sequenceType' => 'first',
            'description' => $payment['description'],
            'webhookUrl' => Utils::serviceUrl('/api/webhooks/payment/mollie'),
            'redirectUrl' => \url('/wallet'),
            'locale' => 'en_US',
            // 'method' => 'creditcard',
        ];

        // Create the payment in Mollie
        $response = mollie()->payments()->create($request);

        if ($response->mandateId) {
            $wallet->setSetting('mollie_mandate_id', $response->mandateId);
        }

        return [
            'id' => $response->id,
            'redirectUrl' => $response->getCheckoutUrl(),
        ];
    }

    /**
     * Revoke the auto-payment mandate for the wallet.
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return bool True on success, False on failure
     */
    public function deleteMandate(Wallet $wallet): bool
    {
        // Get the Mandate info
        $mandate = self::mollieMandate($wallet);

        // Revoke the mandate on Mollie
        if ($mandate) {
            $mandate->revoke();

            $wallet->setSetting('mollie_mandate_id', null);
        }

        return true;
    }

    /**
     * Get a auto-payment mandate for the wallet.
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
        $mandate = self::mollieMandate($wallet);

        if (empty($mandate)) {
            return null;
        }

        $result = [
            'id' => $mandate->id,
            'isPending' => $mandate->isPending(),
            'isValid' => $mandate->isValid(),
            'method' => self::paymentMethod($mandate, 'Unknown method')
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
        return 'mollie';
    }

    /**
     * Create a new payment.
     *
     * @param \App\Wallet $wallet  The wallet
     * @param array       $payment Payment data:
     *                             - amount: Value in cents
     *                             - currency: The operation currency
     *                             - type: oneoff/recurring
     *                             - description: Operation desc.
     *
     * @return array Provider payment data:
     *               - id: Operation identifier
     *               - redirectUrl: the location to redirect to
     */
    public function payment(Wallet $wallet, array $payment): ?array
    {
        if ($payment['type'] == self::TYPE_RECURRING) {
            return $this->paymentRecurring($wallet, $payment);
        }

        // Register the user in Mollie, if not yet done
        $customer_id = self::mollieCustomerId($wallet);

        // Note: Required fields: description, amount/currency, amount/value

        $request = [
            'amount' => [
                'currency' => $payment['currency'],
                // a number with two decimals is required
                'value' => sprintf('%.2f', $payment['amount'] / 100),
            ],
            'customerId' => $customer_id,
            'sequenceType' => $payment['type'],
            'description' => $payment['description'],
            'webhookUrl' => Utils::serviceUrl('/api/webhooks/payment/mollie'),
            'locale' => 'en_US',
            // 'method' => 'creditcard',
            'redirectUrl' => \url('/wallet') // required for non-recurring payments
        ];

        // TODO: Additional payment parameters for better fraud protection:
        //   billingEmail - for bank transfers, Przelewy24, but not creditcard
        //   billingAddress (it is a structured field not just text)

        // Create the payment in Mollie
        $response = mollie()->payments()->create($request);

        // Store the payment reference in database
        $payment['status'] = $response->status;
        $payment['id'] = $response->id;

        $this->storePayment($payment, $wallet->id);

        return [
            'id' => $payment['id'],
            'redirectUrl' => $response->getCheckoutUrl(),
        ];
    }

    /**
     * Create a new automatic payment operation.
     *
     * @param \App\Wallet $wallet  The wallet
     * @param array       $payment Payment data (see self::payment())
     *
     * @return array Provider payment/session data:
     *               - id: Operation identifier
     */
    protected function paymentRecurring(Wallet $wallet, array $payment): ?array
    {
        // Check if there's a valid mandate
        $mandate = self::mollieMandate($wallet);

        if (empty($mandate) || !$mandate->isValid() || $mandate->isPending()) {
            return null;
        }

        $customer_id = self::mollieCustomerId($wallet);

        // Note: Required fields: description, amount/currency, amount/value

        $request = [
            'amount' => [
                'currency' => $payment['currency'],
                // a number with two decimals is required
                'value' => sprintf('%.2f', $payment['amount'] / 100),
            ],
            'customerId' => $customer_id,
            'sequenceType' => $payment['type'],
            'description' => $payment['description'],
            'webhookUrl' => Utils::serviceUrl('/api/webhooks/payment/mollie'),
            'locale' => 'en_US',
            // 'method' => 'creditcard',
            'mandateId' => $mandate->id
        ];

        // Create the payment in Mollie
        $response = mollie()->payments()->create($request);

        // Store the payment reference in database
        $payment['status'] = $response->status;
        $payment['id'] = $response->id;

        DB::beginTransaction();

        $payment = $this->storePayment($payment, $wallet->id);

        // Mollie can return 'paid' status immediately, so we don't
        // have to wait for the webhook. What's more, the webhook would ignore
        // the payment because it will be marked as paid before the webhook.
        // Let's handle paid status here too.
        if ($response->isPaid()) {
            self::creditPayment($payment, $response);
            $notify = true;
        } elseif ($response->isFailed()) {
            // Note: I didn't find a way to get any description of the problem with a payment
            \Log::info(sprintf('Mollie payment failed (%s)', $response->id));

            // Disable the mandate
            $wallet->setSetting('mandate_disabled', 1);
            $notify = true;
        }

        DB::commit();

        if (!empty($notify)) {
            \App\Jobs\PaymentEmail::dispatch($payment);
        }

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
        $payment_id = \request()->input('id');

        if (empty($payment_id)) {
            return 200;
        }

        $payment = Payment::find($payment_id);

        if (empty($payment)) {
            // Mollie recommends to return "200 OK" even if the payment does not exist
            return 200;
        }

        // Get the payment details from Mollie
        $mollie_payment = mollie()->payments()->get($payment_id);

        if (empty($mollie_payment)) {
            // Mollie recommends to return "200 OK" even if the payment does not exist
            return 200;
        }

        if ($mollie_payment->isPaid()) {
            if (!$mollie_payment->hasRefunds() && !$mollie_payment->hasChargebacks()) {
                // The payment is paid and isn't refunded or charged back.
                // Update the balance, if it wasn't already
                if ($payment->status != self::STATUS_PAID && $payment->amount > 0) {
                    $credit = true;
                    $notify = $payment->type == self::TYPE_RECURRING;
                }
            } elseif ($mollie_payment->hasRefunds()) {
                // The payment has been (partially) refunded.
                // The status of the payment is still "paid"
                // TODO: Update balance
            } elseif ($mollie_payment->hasChargebacks()) {
                // The payment has been (partially) charged back.
                // The status of the payment is still "paid"
                // TODO: Update balance
            }
        } elseif ($mollie_payment->isFailed()) {
            // Note: I didn't find a way to get any description of the problem with a payment
            \Log::info(sprintf('Mollie payment failed (%s)', $payment->id));

            // Disable the mandate
            if ($payment->type == self::TYPE_RECURRING) {
                $notify = true;
                $payment->wallet->setSetting('mandate_disabled', 1);
            }
        }

        DB::beginTransaction();

        // This is a sanity check, just in case the payment provider api
        // sent us open -> paid -> open -> paid. So, we lock the payment after
        // recivied a "final" state.
        $pending_states = [self::STATUS_OPEN, self::STATUS_PENDING, self::STATUS_AUTHORIZED];
        if (in_array($payment->status, $pending_states)) {
            $payment->status = $mollie_payment->status;
            $payment->save();
        }

        if (!empty($credit)) {
            self::creditPayment($payment, $mollie_payment);
        }

        DB::commit();

        if (!empty($notify)) {
            \App\Jobs\PaymentEmail::dispatch($payment);
        }

        return 200;
    }

    /**
     * Get Mollie customer identifier for specified wallet.
     * Create one if does not exist yet.
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return string Mollie customer identifier
     */
    protected static function mollieCustomerId(Wallet $wallet): string
    {
        $customer_id = $wallet->getSetting('mollie_id');

        // Register the user in Mollie
        if (empty($customer_id)) {
            $customer = mollie()->customers()->create([
                    'name'  => $wallet->owner->name(),
                    'email' => $wallet->id . '@private.' . \config('app.domain'),
            ]);

            $customer_id = $customer->id;

            $wallet->setSetting('mollie_id', $customer->id);
        }

        return $customer_id;
    }

    /**
     * Get the active Mollie auto-payment mandate
     */
    protected static function mollieMandate(Wallet $wallet)
    {
        $customer_id = $wallet->getSetting('mollie_id');
        $mandate_id = $wallet->getSetting('mollie_mandate_id');

        // Get the manadate reference we already have
        if ($customer_id && $mandate_id) {
            $mandate = mollie()->mandates()->getForId($customer_id, $mandate_id);
            if ($mandate) {// && ($mandate->isValid() || $mandate->isPending())) {
                return $mandate;
            }
        }

        // Get all mandates from Mollie and find the active one
        /*
        foreach ($customer->mandates() as $mandate) {
            if ($mandate->isValid() || $mandate->isPending()) {
                $wallet->setSetting('mollie_mandate_id', $mandate->id);
                return $mandate;
            }
        }
        */
    }

    /**
     * Apply the successful payment's pecunia to the wallet
     */
    protected static function creditPayment($payment, $mollie_payment)
    {
        // Extract the payment method for transaction description
        $method = self::paymentMethod($mollie_payment, 'Mollie');

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
     * Extract payment method description from Mollie payment/mandate details
     */
    protected static function paymentMethod($object, $default = ''): string
    {
        $details = $object->details;

        // Mollie supports 3 methods here
        switch ($object->method) {
            case 'creditcard':
                // If the customer started, but never finished the 'first' payment
                // card details will be empty, and mandate will be 'pending'.
                if (empty($details->cardNumber)) {
                    return 'Credit Card';
                }

                return sprintf(
                    '%s (**** **** **** %s)',
                    $details->cardLabel ?: 'Card', // @phpstan-ignore-line
                    $details->cardNumber
                );

            case 'directdebit':
                return sprintf('Direct Debit (%s)', $details->customerAccount);

            case 'paypal':
                return sprintf('PayPal (%s)', $details->consumerAccount);
        }

        return $default;
    }
}
