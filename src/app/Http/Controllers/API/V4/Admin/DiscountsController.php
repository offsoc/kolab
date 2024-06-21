<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Discount;
use App\Http\Controllers\Controller;

class DiscountsController extends Controller
{
    /**
     * Returns (active) discounts defined in the system for the user context.
     *
     * @param int $id User identifier
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function userDiscounts($id)
    {
        $user = \App\User::find($id);

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        $discounts = Discount::withObjectTenantContext($user)
            ->where('active', true)
            ->orderBy('discount')
            ->get()
            ->map(function ($discount) {
                $label = $discount->discount . '% - ' . $discount->description;

                if ($discount->code) {
                    $label .= " [{$discount->code}]";
                }

                return [
                    'id' => $discount->id,
                    'discount' => $discount->discount,
                    'code' => $discount->code,
                    'description' => $discount->description,
                    'label' => $label,
                ];
            });

        return response()->json([
                'status' => 'success',
                'list' => $discounts,
                'count' => count($discounts),
        ]);
    }
}
