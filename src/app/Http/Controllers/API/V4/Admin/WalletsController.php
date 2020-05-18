<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Discount;
use App\Http\Controllers\API\V4\PaymentsController;
use App\Providers\PaymentProvider;
use App\Wallet;
use Illuminate\Http\Request;

class WalletsController extends \App\Http\Controllers\API\V4\WalletsController
{
    /**
     * Return data of the specified wallet.
     *
     * @param string $id A wallet identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function show($id)
    {
        $wallet = Wallet::find($id);

        if (empty($wallet)) {
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

        return $result;
    }

    /**
     * Update wallet data.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @params string                  $id      Wallet identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $wallet = Wallet::find($id);

        if (empty($wallet)) {
            return $this->errorResponse(404);
        }

        if (array_key_exists('discount', $request->input())) {
            if (empty($request->discount)) {
                $wallet->discount()->dissociate();
                $wallet->save();
            } elseif ($discount = Discount::find($request->discount)) {
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
        $response['message'] = \trans('app.wallet-update-success');

        return response()->json($response);
    }
}
