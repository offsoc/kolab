<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Discount;
use App\Http\Controllers\API\V4\PaymentsController;
use App\Providers\PaymentProvider;
use App\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WalletsController extends \App\Http\Controllers\API\V4\WalletsController
{
    /**
     * Return data of the specified wallet.
     *
     * @param string $id A wallet identifier
     *
     * @return JsonResponse The response
     */
    public function show($id)
    {
        $wallet = Wallet::find($id);

        if (empty($wallet) || !$this->checkTenant($wallet->owner)) {
            return $this->errorResponse(404);
        }

        $result = $wallet->toArray();

        $result['discount'] = 0;
        $result['discount_description'] = '';

        if ($wallet->discount) {
            $result['discount'] = $wallet->discount->discount;
            $result['discount_description'] = $wallet->discount->description;
        }

        $result['mandate'] = PaymentsController::walletMandate($wallet);

        $provider = PaymentProvider::factory($wallet);

        $result['provider'] = $provider->name();
        $result['providerLink'] = $provider->customerLink($wallet);
        $result['notice'] = $this->getWalletNotice($wallet); // for resellers

        return response()->json($result);
    }

    /**
     * Award/penalize a wallet.
     *
     * @param Request $request the API request
     * @param string  $id      Wallet identifier
     *
     * @return JsonResponse The response
     */
    public function oneOff(Request $request, $id)
    {
        $wallet = Wallet::find($id);
        $user = $this->guard()->user();

        if (empty($wallet) || !$this->checkTenant($wallet->owner)) {
            return $this->errorResponse(404);
        }

        // Check required fields
        $v = Validator::make(
            $request->all(),
            [
                'amount' => 'required|numeric',
                'description' => 'required|string|max:1024',
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $amount = (int) round($request->amount * 100);
        $method = $amount > 0 ? 'award' : 'penalty';

        DB::beginTransaction();

        $wallet->{$method}(abs($amount), $request->description);

        if ($user->role == 'reseller') {
            if ($user->tenant && ($tenant_wallet = $user->tenant->wallet())) {
                $desc = ($amount > 0 ? 'Awarded' : 'Penalized') . " user {$wallet->owner->email}";
                $tenant_method = $amount > 0 ? 'debit' : 'credit';
                $tenant_wallet->{$tenant_method}(abs($amount), $desc);
            }
        }

        DB::commit();

        $response = [
            'status' => 'success',
            'message' => self::trans("app.wallet-{$method}-success"),
            'balance' => $wallet->balance,
        ];

        return response()->json($response);
    }

    /**
     * Update wallet data.
     *
     * @param Request $request the API request
     * @param string  $id      Wallet identifier
     *
     * @return JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $wallet = Wallet::find($id);

        if (empty($wallet) || !$this->checkTenant($wallet->owner)) {
            return $this->errorResponse(404);
        }

        if (array_key_exists('discount', $request->input())) {
            if (empty($request->discount)) {
                $wallet->discount()->dissociate();
                $wallet->save();
            } elseif ($discount = Discount::withObjectTenantContext($wallet->owner)->find($request->discount)) {
                $wallet->discount()->associate($discount);
                $wallet->save();
            }
        }

        $response = $wallet->toArray();

        if ($wallet->discount) {
            $response['discount'] = $wallet->discount->discount;
            $response['discount_description'] = $wallet->discount->description;
        }

        $response['status'] = 'success';
        $response['message'] = self::trans('app.wallet-update-success');

        return response()->json($response);
    }
}
