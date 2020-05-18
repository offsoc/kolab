<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Providers\PaymentProvider;
use App\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets->first();

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
        $current_user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $current_user->wallets->first();

        $rules = [
            'amount' => 'required|numeric',
            'balance' => 'required|numeric|min:0',
        ];

        // Check required fields
        $v = Validator::make($request->all(), $rules);

        // TODO: allow comma as a decimal point?

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $amount = (int) ($request->amount * 100);

        // Validate the minimum value
        if ($amount < PaymentProvider::MIN_AMOUNT) {
            $min = intval(PaymentProvider::MIN_AMOUNT / 100) . ' CHF';
            $errors = ['amount' => \trans('validation.minamount', ['amount' => $min])];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $wallet->setSetting('mandate_amount', $request->amount);
        $wallet->setSetting('mandate_balance', $request->balance);

        $request = [
            'currency' => 'CHF',
            'amount' => $amount,
            'description' => \config('app.name') . ' Auto-Payment Setup',
        ];

        $provider = PaymentProvider::factory($wallet);

        $result = $provider->createMandate($wallet, $request);

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
        $user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $user->wallets->first();

        $provider = PaymentProvider::factory($wallet);

        $provider->deleteMandate($wallet);

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
        $current_user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $current_user->wallets->first();

        $rules = [
            'amount' => 'required|numeric',
            'balance' => 'required|numeric|min:0',
        ];

        // Check required fields
        $v = Validator::make($request->all(), $rules);

        // TODO: allow comma as a decimal point?

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $amount = (int) ($request->amount * 100);

        // Validate the minimum value
        if ($amount < PaymentProvider::MIN_AMOUNT) {
            $min = intval(PaymentProvider::MIN_AMOUNT / 100) . ' CHF';
            $errors = ['amount' => \trans('validation.minamount', ['amount' => $min])];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $wallet->setSetting('mandate_amount', $request->amount);
        $wallet->setSetting('mandate_balance', $request->balance);

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.mandate-update-success'),
        ]);
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
        $current_user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $current_user->wallets->first();

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
        if ($amount < PaymentProvider::MIN_AMOUNT) {
            $min = intval(PaymentProvider::MIN_AMOUNT / 100) . ' CHF';
            $errors = ['amount' => \trans('validation.minamount', ['amount' => $min])];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $request = [
            'type' => PaymentProvider::TYPE_ONEOFF,
            'currency' => 'CHF',
            'amount' => $amount,
            'description' => \config('app.name') . ' Payment',
        ];

        $provider = PaymentProvider::factory($wallet);

        $result = $provider->payment($wallet, $request);

        $result['status'] = 'success';

        return response()->json($result);
    }

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
     * Charge a wallet with a "recurring" payment.
     *
     * @param \App\Wallet $wallet The wallet to charge
     * @param int         $amount The amount of money in cents
     *
     * @return bool
     */
    public static function directCharge(Wallet $wallet, $amount): bool
    {
        $request = [
            'type' => PaymentProvider::TYPE_RECURRING,
            'currency' => 'CHF',
            'amount' => $amount,
            'description' => \config('app.name') . ' Recurring Payment',
        ];

        $provider = PaymentProvider::factory($wallet);

        if ($result = $provider->payment($wallet, $request)) {
            return true;
        }

        return false;
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

        // Get the Mandate info
        $mandate = (array) $provider->getMandate($wallet);

        $mandate['amount'] = (int) (PaymentProvider::MIN_AMOUNT / 100);
        $mandate['balance'] = 0;

        foreach (['amount', 'balance'] as $key) {
            if (($value = $wallet->getSetting("mandate_{$key}")) !== null) {
                $mandate[$key] = $value;
            }
        }

        return $mandate;
    }
}
