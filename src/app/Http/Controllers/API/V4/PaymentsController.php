<?php

namespace App\Http\Controllers\API\V4;

use App\Payment;
use App\Wallet;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentsController extends Controller
{
    /**
     * Create a new payment.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function store(Request $request)
    {
        $current_user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $current_user->wallets()->first();

        // Check required fields
        $v = Validator::make(
            $request->all(),
            [
                'amount' => 'required|int|min:1',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Register the user in Mollie, if not yet done
        // FIXME: Maybe Mollie ID should be bound to a wallet, but then
        //        The same customer could technicly have multiple
        //        Mollie IDs, then we'd need to use some "virtual" email
        //        address (e.g. <wallet-id>@<user-domain>) instead of the user email address
        $customer_id = $current_user->getSetting('mollie_id');
        $seq_type = 'oneoff';

        if (empty($customer_id)) {
            $customer = mollie()->customers()->create([
                    'name'  => $current_user->name,
                    'email' => $current_user->email,
            ]);

            $seq_type = 'first';
            $customer_id = $customer->id;
            $current_user->setSetting('mollie_id', $customer_id);
        }

        $payment_request = [
            'amount' => [
                'currency' => 'CHF',
                // a number with two decimals is required
                'value' => sprintf('%.2f', $request->amount / 100),
            ],
            'customerId' => $customer_id,
            'sequenceType' => $seq_type,            // 'first' / 'oneoff' / 'recurring'
            'description' => 'Kolab Now Payment',   // required
            'redirectUrl' => \url('/wallet'),       // required for non-recurring payments
            'webhookUrl' => self::serviceUrl('/api/webhooks/payment/mollie'),
            'locale' => 'en_US',
        ];

        // Create the payment in Mollie
        $payment = mollie()->payments()->create($payment_request);

        // Store the payment reference in database
        self::storePayment($payment, $wallet->id, $request->amount);

        return response()->json([
                'status' => 'success',
                'redirectUrl' => $payment->getCheckoutUrl(),
        ]);
    }

    /**
     * Update payment status (and balance).
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\Response The response
     */
    public function webhook(Request $request)
    {
        $db_payment = Payment::find($request->id);

        // Mollie recommends to return "200 OK" even if the payment does not exist
        if (empty($db_payment)) {
            return response('Success', 200);
        }

        // Get the payment details from Mollie
        $payment = mollie()->payments()->get($request->id);

        if (empty($payment)) {
            return response('Success', 200);
        }

        if ($payment->isPaid()) {
            if (!$payment->hasRefunds() && !$payment->hasChargebacks()) {
                // The payment is paid and isn't refunded or charged back.
                // Update the balance, if it wasn't already
                if ($db_payment->status != 'paid') {
                    $db_payment->wallet->credit($db_payment->amount);
                }
            } elseif ($payment->hasRefunds()) {
                // The payment has been (partially) refunded.
                // The status of the payment is still "paid"
                // TODO: Update balance
            } elseif ($payment->hasChargebacks()) {
                // The payment has been (partially) charged back.
                // The status of the payment is still "paid"
                // TODO: Update balance
            }
        }

        // This is a sanity check, just in case the payment provider api
        // sent us open -> paid -> open -> paid. So, we lock the payment after it's paid.
        if ($db_payment->status != 'paid') {
            $db_payment->status = $payment->status;
            $db_payment->save();
        }

        return response('Success', 200);
    }

    /**
     * Charge a wallet with a "recurring" payment.
     *
     * @param \App\Wallet $wallet The wallet to charge
     * @param int         $amount The amount of money in cents
     *
     * @return bool
     */
    public static function directCharge(Wallet $wallet, $amount): bool
    {
        $customer_id = $wallet->owner->getSetting('mollie_id');

        if (empty($customer_id)) {
            return false;
        }

        // Check if there's at least one valid mandate
        $mandates = mollie()->mandates()->listFor($customer_id)->filter(function ($mandate) {
            return $mandate->isValid();
        });

        if (empty($mandates)) {
            return false;
        }

        $payment_request = [
            'amount' => [
                'currency' => 'CHF',
                // a number with two decimals is required
                'value' => sprintf('%.2f', $amount / 100),
            ],
            'customerId' => $customer_id,
            'sequenceType' => 'recurring',
            'description' => 'Kolab Now Recurring Payment',
            'webhookUrl' => self::serviceUrl('/api/webhooks/payment/mollie'),
        ];

        // Create the payment in Mollie
        $payment = mollie()->payments()->create($payment_request);

        // Store the payment reference in database
        self::storePayment($payment, $wallet->id, $amount);

        return true;
    }

    /**
     * Create self URL
     *
     * @param string $route Route/Path
     *
     * @return string Full URL
     */
    protected static function serviceUrl(string $route): string
    {
        $url = \url($route);

        $app_url = trim(\config('app.url'), '/');
        $pub_url = trim(\config('app.public_url'), '/');

        if ($pub_url != $app_url) {
            $url = str_replace($app_url, $pub_url, $url);
        }

        return $url;
    }

    /**
     * Create a payment record in DB
     *
     * @param object $payment   Mollie payment
     * @param string $wallet_id Wallet ID
     * @param int    $amount    Amount of money in cents
     */
    protected static function storePayment($payment, $wallet_id, $amount): void
    {
        $db_payment = new Payment();
        $db_payment->id = $payment->id;
        $db_payment->description = $payment->description;
        $db_payment->status = $payment->status;
        $db_payment->amount = $amount;
        $db_payment->wallet_id = $wallet_id;
        $db_payment->save();
    }
}
