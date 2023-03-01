<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Payment;
use App\Providers\PaymentProvider;
use App\Tenant;
use App\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentsController extends Controller
{
    /**
     * Get the auto-payment mandate info.
     *
     * @return \Illuminate\Http\JsonResponse The response
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
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
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
            'description' => Tenant::getConfig($user->tenant_id, 'app.name') . ' Auto-Payment Setup',
            'methodId' => $request->methodId ?: PaymentProvider::METHOD_CREDITCARD,
        ];

        // Normally the auto-payment setup operation is 0, if the balance is below the threshold
        // we'll top-up the wallet with the configured auto-payment amount
        if ($wallet->balance < intval($request->balance * 100)) {
            $mandate['amount'] = intval($request->amount * 100);

            self::addTax($wallet, $mandate);
        }

        $provider = PaymentProvider::factory($wallet);

        $result = $provider->createMandate($wallet, $mandate);

        $result['status'] = 'success';

        return response()->json($result);
    }

    /**
     * Revoke the auto-payment mandate.
     *
     * @return \Illuminate\Http\JsonResponse The response
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
                'message' => \trans('app.mandate-delete-success'),
        ]);
    }

    /**
     * Update a new auto-payment mandate.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
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
        if ($wallet->balance < intval($request->balance * 100)) {
            \App\Jobs\WalletCharge::dispatch($wallet);
        }

        $result = self::walletMandate($wallet);
        $result['status'] = 'success';
        $result['message'] = \trans('app.mandate-update-success');

        return response()->json($result);
    }

    /**
     * Validate an auto-payment mandate request.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param \App\Wallet              $wallet  The wallet
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

        $amount = (int) ($request->amount * 100);

        // Validate the minimum value
        // It has to be at least minimum payment amount and must cover current debt,
        // and must be more than a yearly/monthly payment (according to the plan)
        $min = Payment::MIN_AMOUNT;
        $label = 'minamount';

        if ($plan = $wallet->owner->plan()) {
            // TODO: $min = 
        }

        if ($wallet->balance < 0 && $wallet->balance < $min * -1) {
            $min = $wallet->balance * -1;
            $label = 'minamountdebt';
        }

        if ($amount < $min) {
            return ['amount' => \trans("validation.{$label}", ['amount' => $wallet->money($min)])];
        }

        return null;
    }

    /**
     * Create a new payment.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
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

        $amount = (int) ($request->amount * 100);

        // Validate the minimum value
        if ($amount < Payment::MIN_AMOUNT) {
            $min = $wallet->money(Payment::MIN_AMOUNT);
            $errors = ['amount' => \trans('validation.minamount', ['amount' => $min])];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $currency = $request->currency;

        $request = [
            'type' => Payment::TYPE_ONEOFF,
            'currency' => $currency,
            'amount' => $amount,
            'methodId' => $request->methodId ?: PaymentProvider::METHOD_CREDITCARD,
            'description' => Tenant::getConfig($user->tenant_id, 'app.name') . ' Payment',
        ];

        self::addTax($wallet, $request);

        $provider = PaymentProvider::factory($wallet, $currency);

        $result = $provider->payment($wallet, $request);

        $result['status'] = 'success';

        return response()->json($result);
    }

    /**
     * Delete a pending payment.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
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
     * @return \Illuminate\Http\Response The response
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
     * Top up a wallet with a "recurring" payment.
     *
     * @param \App\Wallet $wallet The wallet to charge
     *
     * @return bool True if the payment has been initialized
     */
    public static function topUpWallet(Wallet $wallet): bool
    {
        $settings = $wallet->getSettings(['mandate_disabled', 'mandate_balance', 'mandate_amount']);

        \Log::debug("Requested top-up for wallet {$wallet->id}");

        if (!empty($settings['mandate_disabled'])) {
            \Log::debug("Top-up for wallet {$wallet->id}: mandate disabled");
            return false;
        }

        $min_balance = (int) (floatval($settings['mandate_balance']) * 100);
        $amount = (int) (floatval($settings['mandate_amount']) * 100);

        // The wallet balance is greater than the auto-payment threshold
        if ($wallet->balance >= $min_balance) {
            // Do nothing
            return false;
        }

        $provider = PaymentProvider::factory($wallet);
        $mandate = (array) $provider->getMandate($wallet);

        if (empty($mandate['isValid'])) {
            \Log::debug("Top-up for wallet {$wallet->id}: mandate invalid");
            return false;
        }

        // The defined top-up amount is not enough
        // Disable auto-payment and notify the user
        if ($wallet->balance + $amount < 0) {
            // Disable (not remove) the mandate
            $wallet->setSetting('mandate_disabled', 1);
            \App\Jobs\PaymentMandateDisabledEmail::dispatch($wallet);
            return false;
        }

        $request = [
            'type' => Payment::TYPE_RECURRING,
            'currency' => $wallet->currency,
            'amount' => $amount,
            'methodId' => PaymentProvider::METHOD_CREDITCARD,
            'description' => Tenant::getConfig($wallet->owner->tenant_id, 'app.name') . ' Recurring Payment',
        ];

        self::addTax($wallet, $request);

        $result = $provider->payment($wallet, $request);

        return !empty($result);
    }

    /**
     * Returns auto-payment mandate info for the specified wallet
     *
     * @param \App\Wallet $wallet A wallet object
     *
     * @return array A mandate metadata
     */
    public static function walletMandate(Wallet $wallet): array
    {
        $provider = PaymentProvider::factory($wallet);
        $settings = $wallet->getSettings(['mandate_disabled', 'mandate_balance', 'mandate_amount']);

        // Get the Mandate info
        $mandate = (array) $provider->getMandate($wallet);

        $mandate['amount'] = (int) (Payment::MIN_AMOUNT / 100);
        $mandate['balance'] = 0;
        $mandate['isDisabled'] = !empty($mandate['id']) && $settings['mandate_disabled'];

        foreach (['amount', 'balance'] as $key) {
            if (($value = $settings["mandate_{$key}"]) !== null) {
                $mandate[$key] = $value;
            }
        }

        if (($plan = $wallet->owner->plan()) /* && $plan->months > 0*/) {
            // TODO $mandate['minAmount'] = 100;
        }
        $mandate['minAmount'] = 100; // test

        // Unrestrict the wallet owner if mandate is valid
        if (!empty($mandate['isValid']) && $wallet->owner->isRestricted()) {
            $wallet->owner->unrestrict();
        }

        return $mandate;
    }

    /**
     * List supported payment methods.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
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
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
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
                    Payment::STATUS_AUTHORIZED
            ])
            ->exists();

        return response()->json([
            'status' => 'success',
            'hasPending' => $exists
        ]);
    }

    /**
     * List pending payments.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function payments(Request $request)
    {
        $user = $this->guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets()->first();

        $pageSize = 10;
        $page = intval(request()->input('page')) ?: 1;
        $hasMore = false;
        $result = Payment::where('wallet_id', $wallet->id)
            ->where('type', Payment::TYPE_ONEOFF)
            ->whereIn('status', [
                    Payment::STATUS_OPEN,
                    Payment::STATUS_PENDING,
                    Payment::STATUS_AUTHORIZED
            ])
            ->orderBy('created_at', 'desc')
            ->limit($pageSize + 1)
            ->offset($pageSize * ($page - 1))
            ->get();

        if (count($result) > $pageSize) {
            $result->pop();
            $hasMore = true;
        }

        $result = $result->map(function ($item) use ($wallet) {
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
                'checkoutUrl' => $payment['checkoutUrl']
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

    /**
     * Calculates tax for the payment, fills the request with additional properties
     */
    protected static function addTax(Wallet $wallet, array &$request): void
    {
        $request['vat_rate_id'] = null;
        $request['credit_amount'] = $request['amount'];

        if ($rate = $wallet->vatRate()) {
            $request['vat_rate_id'] = $rate->id;

            switch (\config('app.vat.mode')) {
            case 1:
                // In this mode tax is added on top of the payment. The amount
                // to pay grows, but we keep wallet balance without tax.
                $request['amount'] = $request['amount'] + round($request['amount'] * $rate->rate / 100);
                break;

            default:
                // In this mode tax is "swallowed" by the vendor. The payment
                // amount does not change
                break;
            }
        }
    }
}
