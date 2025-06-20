<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Jobs\Wallet\ChargeJob;
use App\Payment;
use App\Providers\PaymentProvider;
use App\Tenant;
use App\Utils;
use App\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class PaymentsController extends Controller
{
    /**
     * Get the auto-payment mandate info.
     *
     * @return JsonResponse The response
     */
    public function mandate()
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        $mandate = self::walletMandate($wallet);

        return response()->json($mandate);
    }

    /**
     * Create a new auto-payment mandate.
     *
     * @param Request $request the API request
     *
     * @return JsonResponse The response
     */
    public function mandateCreate(Request $request)
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        // Input validation
        if ($errors = self::mandateValidate($request, $wallet)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $wallet->setSettings([
            'mandate_amount' => $request->amount,
            'mandate_balance' => $request->balance,
        ]);

        $mandate = [
            'currency' => $wallet->currency,

            'description' => Tenant::getConfig($user->tenant_id, 'app.name')
                . ' ' . self::trans('app.mandate-description-suffix'),

            'methodId' => $request->methodId ?: PaymentProvider::METHOD_CREDITCARD,
        ];

        // Normally the auto-payment setup operation is 0, if the balance is below the threshold
        // we'll top-up the wallet with the configured auto-payment amount
        if ($wallet->balance < round($request->balance * 100)) {
            $mandate['amount'] = (int) round($request->amount * 100);

            $mandate = $wallet->paymentRequest($mandate);
        }

        $provider = PaymentProvider::factory($wallet);

        $result = $provider->createMandate($wallet, $mandate);

        $result['status'] = 'success';

        return response()->json($result);
    }

    /**
     * Revoke the auto-payment mandate.
     *
     * @return JsonResponse The response
     */
    public function mandateDelete()
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        $provider = PaymentProvider::factory($wallet);

        $provider->deleteMandate($wallet);

        $wallet->setSetting('mandate_disabled', null);

        return response()->json([
            'status' => 'success',
            'message' => self::trans('app.mandate-delete-success'),
        ]);
    }

    /**
     * Update a new auto-payment mandate.
     *
     * @param Request $request the API request
     *
     * @return JsonResponse The response
     */
    public function mandateUpdate(Request $request)
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        // Input validation
        if ($errors = self::mandateValidate($request, $wallet)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $wallet->setSettings([
            'mandate_amount' => $request->amount,
            'mandate_balance' => $request->balance,
            // Re-enable the mandate to give it a chance to charge again
            // after it has been disabled (e.g. because the mandate amount was too small)
            'mandate_disabled' => null,
        ]);

        // Trigger auto-payment if the balance is below the threshold
        if ($wallet->balance < round($request->balance * 100)) {
            ChargeJob::dispatch($wallet->id);
        }

        $result = self::walletMandate($wallet);
        $result['status'] = 'success';
        $result['message'] = self::trans('app.mandate-update-success');

        return response()->json($result);
    }

    /**
     * Reset the auto-payment mandate, create a new payment for it.
     *
     * @param Request $request the API request
     *
     * @return JsonResponse The response
     */
    public function mandateReset(Request $request)
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        $mandate = [
            'currency' => $wallet->currency,

            'description' => Tenant::getConfig($user->tenant_id, 'app.name')
                . ' ' . self::trans('app.mandate-description-suffix'),

            'methodId' => $request->methodId ?: PaymentProvider::METHOD_CREDITCARD,
            'redirectUrl' => Utils::serviceUrl('/payment/status', $user->tenant_id),
        ];

        $provider = PaymentProvider::factory($wallet);

        $result = $provider->createMandate($wallet, $mandate);

        $result['status'] = 'success';

        return response()->json($result);
    }

    /**
     * Validate an auto-payment mandate request.
     *
     * @param Request $request the API request
     * @param Wallet  $wallet  The wallet
     *
     * @return array|null List of errors on error or Null on success
     */
    protected static function mandateValidate(Request $request, Wallet $wallet)
    {
        $rules = [
            'amount' => 'required|numeric',
            'balance' => 'required|numeric|min:0',
        ];

        // Check required fields
        $v = Validator::make($request->all(), $rules);

        // TODO: allow comma as a decimal point?

        if ($v->fails()) {
            return $v->errors()->toArray();
        }

        $amount = (int) round($request->amount * 100);

        // Validate the minimum value
        // It has to be at least minimum payment amount and must cover current debt,
        // and must be more than a yearly/monthly payment (according to the plan)
        $min = $wallet->getMinMandateAmount();
        $label = 'minamount';

        if ($wallet->balance < 0 && $wallet->balance < $min * -1) {
            $min = $wallet->balance * -1;
            $label = 'minamountdebt';
        }

        if ($amount < $min) {
            return ['amount' => self::trans("validation.{$label}", ['amount' => $wallet->money($min)])];
        }

        return null;
    }

    /**
     * Get status of the last payment.
     *
     * @return JsonResponse The response
     */
    public function paymentStatus()
    {
        $user = $this->guard()->user();
        $wallet = $user->wallets()->first();

        $payment = $wallet->payments()->orderBy('created_at', 'desc')->first();

        if (empty($payment)) {
            return $this->errorResponse(404);
        }

        $done = [Payment::STATUS_PAID, Payment::STATUS_CANCELED, Payment::STATUS_FAILED, Payment::STATUS_EXPIRED];

        if (in_array($payment->status, $done)) {
            $label = "app.payment-status-{$payment->status}";
        } else {
            $label = "app.payment-status-checking";
        }

        return response()->json([
            'id' => $payment->id,
            'status' => $payment->status,
            'type' => $payment->type,
            'statusMessage' => self::trans($label),
            'description' => $payment->description,
        ]);
    }

    /**
     * Create a new payment.
     *
     * @param Request $request the API request
     *
     * @return JsonResponse The response
     */
    public function store(Request $request)
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        $rules = [
            'amount' => 'required|numeric',
        ];

        // Check required fields
        $v = Validator::make($request->all(), $rules);

        // TODO: allow comma as a decimal point?

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $amount = (int) round($request->amount * 100);

        // Validate the minimum value
        if ($amount < Payment::MIN_AMOUNT) {
            $min = $wallet->money(Payment::MIN_AMOUNT);
            $errors = ['amount' => self::trans('validation.minamount', ['amount' => $min])];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $currency = $request->currency;

        $request = $wallet->paymentRequest([
            'type' => Payment::TYPE_ONEOFF,
            'currency' => $currency,
            'amount' => $amount,
            'methodId' => $request->methodId ?: PaymentProvider::METHOD_CREDITCARD,
            'description' => Tenant::getConfig($user->tenant_id, 'app.name') . ' Payment',
        ]);

        $provider = PaymentProvider::factory($wallet, $currency);

        $result = $provider->payment($wallet, $request);

        $result['status'] = 'success';

        return response()->json($result);
    }

    /**
     * Delete a pending payment.
     *
     * @return JsonResponse The response
     */
    // TODO currently unused
    // public function cancel(Request $request)
    // {
    //     $user = $this->guard()->user();

    //     // TODO: Wallet selection
    //     $wallet = $user->wallets()->first();

    //     $paymentId = $request->payment;

    //     $user_owns_payment = Payment::where('id', $paymentId)
    //         ->where('wallet_id', $wallet->id)
    //         ->exists();

    //     if (!$user_owns_payment) {
    //         return $this->errorResponse(404);
    //     }

    //     $provider = PaymentProvider::factory($wallet);
    //     if ($provider->cancel($wallet, $paymentId)) {
    //         $result = ['status' => 'success'];
    //         return response()->json($result);
    //     }

    //     return $this->errorResponse(404);
    // }

    /**
     * Update payment status (and balance).
     *
     * @param string $provider Provider name
     *
     * @return Response The response
     */
    public function webhook($provider)
    {
        $code = 200;

        if ($provider = PaymentProvider::factory($provider)) {
            $code = $provider->webhook();
        }

        return response($code < 400 ? 'Success' : 'Server error', $code);
    }

    /**
     * Returns auto-payment mandate info for the specified wallet
     *
     * @param Wallet $wallet A wallet object
     *
     * @return array A mandate metadata
     */
    public static function walletMandate(Wallet $wallet): array
    {
        $provider = PaymentProvider::factory($wallet);
        $settings = $wallet->getSettings(['mandate_disabled', 'mandate_balance', 'mandate_amount']);

        // Get the Mandate info
        $mandate = (array) $provider->getMandate($wallet);

        $mandate['amount'] = $mandate['minAmount'] = round($wallet->getMinMandateAmount() / 100, 2);
        $mandate['balance'] = 0;
        $mandate['isDisabled'] = !empty($mandate['id']) && $settings['mandate_disabled'];
        $mandate['isValid'] = !empty($mandate['isValid']);

        foreach (['amount', 'balance'] as $key) {
            if (($value = $settings["mandate_{$key}"]) !== null) {
                $mandate[$key] = $value;
            }
        }

        // Unrestrict the wallet owner if mandate is valid
        if (!empty($mandate['isValid']) && $wallet->owner->isRestricted()) {
            $wallet->owner->unrestrict();
        }

        return $mandate;
    }

    /**
     * List supported payment methods.
     *
     * @param Request $request the API request
     *
     * @return JsonResponse The response
     */
    public function paymentMethods(Request $request)
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        $methods = PaymentProvider::paymentMethods($wallet, $request->type);

        \Log::debug("Provider methods" . var_export(json_encode($methods), true));

        return response()->json($methods);
    }

    /**
     * Check for pending payments.
     *
     * @param Request $request the API request
     *
     * @return JsonResponse The response
     */
    public function hasPayments(Request $request)
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        $exists = Payment::where('wallet_id', $wallet->id)
            ->where('type', Payment::TYPE_ONEOFF)
            ->whereIn('status', [
                Payment::STATUS_OPEN,
                Payment::STATUS_PENDING,
                Payment::STATUS_AUTHORIZED,
            ])
            ->exists();

        return response()->json([
            'status' => 'success',
            'hasPending' => $exists,
        ]);
    }

    /**
     * List pending payments.
     *
     * @param Request $request the API request
     *
     * @return JsonResponse The response
     */
    public function payments(Request $request)
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        $pageSize = 10;
        $page = (int) (request()->input('page')) ?: 1;
        $hasMore = false;
        $result = Payment::where('wallet_id', $wallet->id)
            ->where('type', Payment::TYPE_ONEOFF)
            ->whereIn('status', [
                Payment::STATUS_OPEN,
                Payment::STATUS_PENDING,
                Payment::STATUS_AUTHORIZED,
            ])
            ->orderBy('created_at', 'desc')
            ->limit($pageSize + 1)
            ->offset($pageSize * ($page - 1))
            ->get();

        if (count($result) > $pageSize) {
            $result->pop();
            $hasMore = true;
        }

        $result = $result->map(static function ($item) use ($wallet) {
            $provider = PaymentProvider::factory($item->provider);
            $payment = $provider->getPayment($item->id);
            $entry = [
                'id' => $item->id,
                'createdAt' => $item->created_at->format('Y-m-d H:i'),
                'type' => $item->type,
                'description' => $item->description,
                'amount' => $item->amount,
                'currency' => $wallet->currency,
                // note: $item->currency/$item->currency_amount might be different
                'status' => $item->status,
                'isCancelable' => $payment['isCancelable'],
                'checkoutUrl' => $payment['checkoutUrl'],
            ];

            return $entry;
        });

        return response()->json([
            'status' => 'success',
            'list' => $result,
            'count' => count($result),
            'hasMore' => $hasMore,
            'page' => $page,
        ]);
    }
}
