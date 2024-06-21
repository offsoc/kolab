<?php

namespace App\Providers\Payment;

use App\Payment;
use App\Utils;
use App\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class Coinbase extends \App\Providers\PaymentProvider
{
    /** @var \GuzzleHttp\Client|null HTTP client instance */
    private $client = null;
    /** @var \GuzzleHttp\Client|null test HTTP client instance */
    public static $testClient = null;

    private const SATOSHI_MULTIPLIER = 10000000;


    /**
     * Get a link to the customer in the provider's control panel
     *
     * @param \App\Wallet $wallet The wallet
     *
     * @return string|null The string representing <a> tag
     */
    public function customerLink(Wallet $wallet): ?string
    {
        return null;
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
        throw new \Exception("not implemented");
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
        throw new \Exception("not implemented");
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
        throw new \Exception("not implemented");
    }

    /**
     * Get a provider name
     *
     * @return string Provider name
     */
    public function name(): string
    {
        return 'coinbase';
    }

    /**
     * Creates HTTP client for connections to coinbase
     *
     * @return \GuzzleHttp\Client HTTP client instance
     */
    private function client()
    {
        if (self::$testClient) {
            return self::$testClient;
        }
        if (!$this->client) {
            $this->client = new \GuzzleHttp\Client(
                [
                    'http_errors' => false, // No exceptions from Guzzle
                    'base_uri' => 'https://api.commerce.coinbase.com/',
                    'verify' => \config('services.coinbase.api_verify_tls'),
                    'headers' => [
                        'X-CC-Api-Key' => \config('services.coinbase.key'),
                        'X-CC-Version' => '2018-03-22',
                    ],
                    'connect_timeout' => 10,
                    'timeout' => 10,
                    'on_stats' => function (\GuzzleHttp\TransferStats $stats) {
                        $threshold = \config('logging.slow_log');
                        if ($threshold && ($sec = $stats->getTransferTime()) > $threshold) {
                            $url = $stats->getEffectiveUri();
                            $method = $stats->getRequest()->getMethod();
                            \Log::warning(sprintf("[STATS] %s %s: %.4f sec.", $method, $url, $sec));
                        }
                    },
                ]
            );
        }
        return $this->client;
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
        if ($payment['type'] == Payment::TYPE_RECURRING) {
            throw new \Exception("not supported");
        }

        $amount = $payment['amount'] / 100;

        $post = [
            'json' => [
                "name" => \config('app.name'),
                "description" => $payment['description'],
                "pricing_type" => "fixed_price",
                'local_price' => [
                    'currency' => $wallet->currency,
                    'amount' => sprintf('%.2F', $amount),
                ],
                'redirect_url' => self::redirectUrl()
            ]
        ];

        $response = $this->client()->request('POST', '/charges/', $post);

        $code = $response->getStatusCode();
        if ($code == 429) {
            $this->logError("Ratelimiting", $response);
            throw new \Exception("Failed to create coinbase charge due to rate-limiting: {$code}");
        }
        if ($code !== 201) {
            $this->logError("Failed to create coinbase charge", $response);
            throw new \Exception("Failed to create coinbase charge: {$code}");
        }

        $json = json_decode($response->getBody(), true);

        // Store the payment reference in database
        $payment['status'] = Payment::STATUS_OPEN;
        //We take the code instead of the id because it fits into our current db schema and the id doesn't
        $payment['id'] = $json['data']['code'];
        //We store in satoshis (the database stores it as INTEGER type)
        $payment['currency_amount'] = $json['data']['pricing']['bitcoin']['amount'] * self::SATOSHI_MULTIPLIER;
        $payment['currency'] = 'BTC';

        $this->storePayment($payment, $wallet->id);

        return [
            'id' => $payment['id'],
            'newWindowUrl' => $json['data']['hosted_url']
        ];
    }


    /**
     * Log an error for a failed request to the meet server
     *
     * @param string $str      The error string
     * @param object $response Guzzle client response
     */
    private function logError(string $str, $response)
    {
        $code = $response->getStatusCode();
        if ($code != 200 && $code != 201) {
            \Log::error(var_export($response, true));

            $decoded = json_decode($response->getBody(), true);
            $message = '';
            if (
                is_array($decoded) && array_key_exists('error', $decoded) &&
                is_array($decoded['error']) && array_key_exists('message', $decoded['error'])
            ) {
                $message = $decoded['error']['message'];
            }

            \Log::error("$str [$code]: $message");
        }
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
        $response = $this->client()->request('POST', "/charges/{$paymentId}/cancel");

        if ($response->getStatusCode() == 200) {
            $db_payment = Payment::find($paymentId);
            $db_payment->status = Payment::STATUS_CANCELED;
            $db_payment->save();
        } else {
            $this->logError("Failed to cancel payment", $response);
            return false;
        }

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
        throw new \Exception("not available with coinbase");
    }


    private static function verifySignature($payload, $sigHeader)
    {
        $secret = \config('services.coinbase.webhook_secret');
        $computedSignature = \hash_hmac('sha256', $payload, $secret);

        if (!\hash_equals($sigHeader, $computedSignature)) {
            throw new \Exception("Coinbase request signature verification failed");
        }
    }

    /**
     * Update payment status (and balance).
     *
     * @return int HTTP response code
     */
    public function webhook(): int
    {
        // We cannot just use php://input as it's already "emptied" by the framework
        $request = Request::instance();
        $payload = $request->getContent();
        $sigHeader = $request->header('X-CC-Webhook-Signature');

        self::verifySignature($payload, $sigHeader);

        $data = \json_decode($payload, true);
        $event = $data['event'];

        $type = $event['type'];
        \Log::info("Coinbase webhook called " . $type);

        if ($type == 'charge:created') {
            return 200;
        }
        if ($type == 'charge:confirmed') {
            return 200;
        }
        if ($type == 'charge:pending') {
            return 200;
        }

        $payment_id = $event['data']['code'];

        if (empty($payment_id)) {
            \Log::warning(sprintf('Failed to find the payment for (%s)', $payment_id));
            return 200;
        }

        $payment = Payment::find($payment_id);

        if (empty($payment)) {
            return 200;
        }

        $newStatus = Payment::STATUS_PENDING;

        // Even if we receive the payment delayed, we still have the money, and therefore credit it.
        if ($type == 'charge:resolved' || $type == 'charge:delayed') {
            // The payment is paid. Update the balance
            if ($payment->status != Payment::STATUS_PAID && $payment->amount > 0) {
                $credit = true;
            }
            $newStatus = Payment::STATUS_PAID;
        } elseif ($type == 'charge:failed') {
            // Note: I didn't find a way to get any description of the problem with a payment
            \Log::info(sprintf('Coinbase payment failed (%s)', $payment->id));
            $newStatus = Payment::STATUS_FAILED;
        }

        DB::beginTransaction();

        // This is a sanity check, just in case the payment provider api
        // sent us open -> paid -> open -> paid. So, we lock the payment after
        // recivied a "final" state.
        $pending_states = [Payment::STATUS_OPEN, Payment::STATUS_PENDING, Payment::STATUS_AUTHORIZED];
        if (in_array($payment->status, $pending_states)) {
            $payment->status = $newStatus;
            $payment->save();
        }

        if (!empty($credit)) {
            $payment->credit('Coinbase');
        }

        DB::commit();

        return 200;
    }

    /**
     * List supported payment methods.
     *
     * @param string $type The payment type for which we require a method (oneoff/recurring).
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
        $availableMethods = [];

        if ($type == Payment::TYPE_ONEOFF) {
            $availableMethods['bitcoin'] = [
                'id' => 'bitcoin',
                'name' => "Bitcoin",
                'minimumAmount' => 0.001,
                'currency' => 'BTC'
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
        $payment = Payment::find($paymentId);

        return [
            'id' => $payment->id,
            'status' => $payment->status,
            'isCancelable' => true,
            'checkoutUrl' => "https://commerce.coinbase.com/charges/{$paymentId}"
        ];
    }
}
