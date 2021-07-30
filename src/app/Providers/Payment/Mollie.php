<?php

namespace App\Providers\Payment;

use App\Payment;
use App\Utils;
use App\Wallet;
use Illuminate\Support\Facades\DB;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Types;

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
        $customer_id = self::mollieCustomerId($wallet, false);

        if (!$customer_id) {
            return null;
        }

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
     *                             - amount: Value in cents (optional)
     *                             - currency: The operation currency
     *                             - description: Operation desc.
     *                             - methodId: Payment method
     *
     * @return array Provider payment data:
     *               - id: Operation identifier
     *               - redirectUrl: the location to redirect to
     */
    public function createMandate(Wallet $wallet, array $payment): ?array
    {
        // Register the user in Mollie, if not yet done
        $customer_id = self::mollieCustomerId($wallet, true);

        if (!isset($payment['amount'])) {
            $payment['amount'] = 0;
        }

        $amount = $this->exchange($payment['amount'], $wallet->currency, $payment['currency']);
        $payment['currency_amount'] = $amount;

        $request = [
            'amount' => [
                'currency' => $payment['currency'],
                'value' => sprintf('%.2f', $amount / 100),
            ],
            'customerId' => $customer_id,
            'sequenceType' => 'first',
            'description' => $payment['description'],
            'webhookUrl' => Utils::serviceUrl('/api/webhooks/payment/mollie'),
            'redirectUrl' => self::redirectUrl(),
            'locale' => 'en_US',
            'method' => $payment['methodId']
        ];

        // Create the payment in Mollie
        $response = mollie()->payments()->create($request);

        if ($response->mandateId) {
            $wallet->setSetting('mollie_mandate_id', $response->mandateId);
        }

        // Store the payment reference in database
        $payment['status'] = $response->status;
        $payment['id'] = $response->id;
        $payment['type'] = self::TYPE_MANDATE;

        $this->storePayment($payment, $wallet->id);

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
     *                    - methodId: Payment method
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
            'method' => self::paymentMethod($mandate, 'Unknown method'),
            'methodId' => $mandate->method
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
     *                             - methodId: Payment method
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
        $customer_id = self::mollieCustomerId($wallet, true);

        $amount = $this->exchange($payment['amount'], $wallet->currency, $payment['currency']);
        $payment['currency_amount'] = $amount;

        // Note: Required fields: description, amount/currency, amount/value
        $request = [
            'amount' => [
                'currency' => $payment['currency'],
                // a number with two decimals is required (note that JPK and ISK don't require decimals,
                // but we're not using them currently)
                'value' => sprintf('%.2f', $amount / 100),
            ],
            'customerId' => $customer_id,
            'sequenceType' => $payment['type'],
            'description' => $payment['description'],
            'webhookUrl' => Utils::serviceUrl('/api/webhooks/payment/mollie'),
            'locale' => 'en_US',
            'method' => $payment['methodId'],
            'redirectUrl' => self::redirectUrl() // required for non-recurring payments
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
     * Cancel a pending payment.
     *
     * @param \App\Wallet $wallet  The wallet
     * @param string      $paymentId Payment Id
     *
     * @return bool True on success, False on failure
     */
    public function cancel(Wallet $wallet, $paymentId): bool
    {
        $response = mollie()->payments()->delete($paymentId);

        $db_payment = Payment::find($paymentId);
        $db_payment->status = $response->status;
        $db_payment->save();

        return true;
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

        $customer_id = self::mollieCustomerId($wallet, true);

        // Note: Required fields: description, amount/currency, amount/value
        $amount = $this->exchange($payment['amount'], $wallet->currency, $payment['currency']);
        $payment['currency_amount'] = $amount;

        $request = [
            'amount' => [
                'currency' => $payment['currency'],
                // a number with two decimals is required
                'value' => sprintf('%.2f', $amount / 100),
            ],
            'customerId' => $customer_id,
            'sequenceType' => $payment['type'],
            'description' => $payment['description'],
            'webhookUrl' => Utils::serviceUrl('/api/webhooks/payment/mollie'),
            'locale' => 'en_US',
            'method' => $payment['methodId'],
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
        // TODO: Consider https://github.com/mollie/mollie-api-php/issues/502 when it's fixed
        $mollie_payment = mollie()->payments()->get($payment_id);

        if (empty($mollie_payment)) {
            // Mollie recommends to return "200 OK" even if the payment does not exist
            return 200;
        }

        $refunds = [];

        if ($mollie_payment->isPaid()) {
            // The payment is paid. Update the balance, and notify the user
            if ($payment->status != self::STATUS_PAID && $payment->amount > 0) {
                $credit = true;
                $notify = $payment->type == self::TYPE_RECURRING;
            }

            // The payment has been (partially) refunded.
            // Let's process refunds with status "refunded".
            if ($mollie_payment->hasRefunds()) {
                foreach ($mollie_payment->refunds() as $refund) {
                    if ($refund->isTransferred() && $refund->amount->value) {
                        $refunds[] = [
                            'id' => $refund->id,
                            'description' => $refund->description,
                            'amount' => round(floatval($refund->amount->value) * 100),
                            'type' => self::TYPE_REFUND,
                            'currency' => $refund->amount->currency
                        ];
                    }
                }
            }

            // The payment has been (partially) charged back.
            // Let's process chargebacks (they have no states as refunds)
            if ($mollie_payment->hasChargebacks()) {
                foreach ($mollie_payment->chargebacks() as $chargeback) {
                    if ($chargeback->amount->value) {
                        $refunds[] = [
                            'id' => $chargeback->id,
                            'amount' => round(floatval($chargeback->amount->value) * 100),
                            'type' => self::TYPE_CHARGEBACK,
                            'currency' => $chargeback->amount->currency
                        ];
                    }
                }
            }

            // In case there were multiple auto-payment setup requests (e.g. caused by a double
            // form submission) we end up with multiple payment records and mollie_mandate_id
            // pointing to the one from the last payment not the successful one.
            // We make sure to use mandate id from the successful "first" payment.
            if (
                $payment->type == self::TYPE_MANDATE
                && $mollie_payment->mandateId
                && $mollie_payment->sequenceType == Types\SequenceType::SEQUENCETYPE_FIRST
            ) {
                $payment->wallet->setSetting('mollie_mandate_id', $mollie_payment->mandateId);
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

        foreach ($refunds as $refund) {
            $this->storeRefund($payment->wallet, $refund);
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
     * @param bool        $create Create the customer if does not exist yet
     *
     * @return ?string Mollie customer identifier
     */
    protected static function mollieCustomerId(Wallet $wallet, bool $create = false): ?string
    {
        $customer_id = $wallet->getSetting('mollie_id');

        // Register the user in Mollie
        if (empty($customer_id) && $create) {
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
            try {
                return mollie()->mandates()->getForId($customer_id, $mandate_id);
            } catch (ApiException $e) {
                // FIXME: What about 404?
                if ($e->getCode() == 410) {
                    // The mandate is gone, remove the reference
                    $wallet->setSetting('mollie_mandate_id', null);
                    return null;
                }

                // TODO: Maybe we shouldn't always throw? It make sense in the job
                //       but for example when we're just fetching wallet info...
                throw $e;
            }
        }
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
            case self::METHOD_CREDITCARD:
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

            case self::METHOD_DIRECTDEBIT:
                return sprintf('Direct Debit (%s)', $details->customerAccount);

            case self::METHOD_PAYPAL:
                return sprintf('PayPal (%s)', $details->consumerAccount);
        }

        return $default;
    }

    /**
     * List supported payment methods.
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
    public function providerPaymentMethods($type): array
    {
        $providerMethods = array_merge(
            // Fallback to EUR methods (later provider methods will override earlier ones)
            (array) mollie()->methods()->allActive(
                [
                    'sequenceType' => $type,
                    'amount' => [
                        'value' => '1.00',
                        'currency' => 'EUR'
                    ]
                ]
            ),
            // Prefer CHF methods
            (array) mollie()->methods()->allActive(
                [
                    'sequenceType' => $type,
                    'amount' => [
                        'value' => '1.00',
                        'currency' => 'CHF'
                    ]
                ]
            )
        );

        $availableMethods = [];

        foreach ($providerMethods as $method) {
            $availableMethods[$method->id] = [
                'id' => $method->id,
                'name' => $method->description,
                'minimumAmount' => round(floatval($method->minimumAmount->value) * 100), // Converted to cents
                'currency' => $method->minimumAmount->currency,
                'exchangeRate' => \App\Utils::exchangeRate('CHF', $method->minimumAmount->currency)
            ];
        }

        return $availableMethods;
    }

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
    public function getPayment($paymentId): array
    {
        $payment = mollie()->payments()->get($paymentId);

        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'isCancelable' => $payment->isCancelable,
            'checkoutUrl' => $payment->getCheckoutUrl()
        ];
    }
}
