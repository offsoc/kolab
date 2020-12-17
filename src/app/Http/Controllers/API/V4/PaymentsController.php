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
        $current_user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $current_user->wallets()->first();

        // Input validation
        if ($errors = self::mandateValidate($request, $wallet)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $wallet->setSettings([
                'mandate_amount' => $request->amount,
                'mandate_balance' => $request->balance,
        ]);

        $mandate = [
            'currency' => 'CHF',
            'description' => \config('app.name') . ' Auto-Payment Setup',
        ];

        // Normally the auto-payment setup operation is 0, if the balance is below the threshold
        // we'll top-up the wallet with the configured auto-payment amount
        if ($wallet->balance < intval($request->balance * 100)) {
            $mandate['amount'] = intval($request->amount * 100);
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
        $user = Auth::guard()->user();

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
        $current_user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $current_user->wallets()->first();

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
        // It has to be at least minimum payment amount and must cover current debt
        if (
            $wallet->balance < 0
            && $wallet->balance * -1 > PaymentProvider::MIN_AMOUNT
            && $wallet->balance + $amount < 0
        ) {
            return ['amount' => \trans('validation.minamountdebt')];
        }

        if ($amount < PaymentProvider::MIN_AMOUNT) {
            $min = intval(PaymentProvider::MIN_AMOUNT / 100) . ' CHF';
            return ['amount' => \trans('validation.minamount', ['amount' => $min])];
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
        $current_user = Auth::guard()->user();

        // TODO: Wallet selection
        $wallet = $current_user->wallets()->first();

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
     * Top up a wallet with a "recurring" payment.
     *
     * @param \App\Wallet $wallet The wallet to charge
     *
     * @return bool True if the payment has been initialized
     */
    public static function topUpWallet(Wallet $wallet): bool
    {
        if ((bool) $wallet->getSetting('mandate_disabled')) {
            return false;
        }

        $min_balance = (int) (floatval($wallet->getSetting('mandate_balance')) * 100);
        $amount = (int) (floatval($wallet->getSetting('mandate_amount')) * 100);

        // The wallet balance is greater than the auto-payment threshold
        if ($wallet->balance >= $min_balance) {
            // Do nothing
            return false;
        }

        $provider = PaymentProvider::factory($wallet);
        $mandate = (array) $provider->getMandate($wallet);

        if (empty($mandate['isValid'])) {
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
            'type' => PaymentProvider::TYPE_RECURRING,
            'currency' => 'CHF',
            'amount' => $amount,
            'description' => \config('app.name') . ' Recurring Payment',
        ];

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

        // Get the Mandate info
        $mandate = (array) $provider->getMandate($wallet);

        $mandate['amount'] = (int) (PaymentProvider::MIN_AMOUNT / 100);
        $mandate['balance'] = 0;
        $mandate['isDisabled'] = !empty($mandate['id']) && $wallet->getSetting('mandate_disabled');

        foreach (['amount', 'balance'] as $key) {
            if (($value = $wallet->getSetting("mandate_{$key}")) !== null) {
                $mandate[$key] = $value;
            }
        }

        return $mandate;
    }
}
