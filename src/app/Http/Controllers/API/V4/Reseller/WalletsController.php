<?php

namespace App\Http\Controllers\API\V4\Reseller;

use App\Discount;
use App\Wallet;
use Illuminate\Http\Request;

class WalletsController extends \App\Http\Controllers\API\V4\WalletsController
{
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
