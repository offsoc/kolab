<?php

namespace App\Http\Controllers\API\V4\Reseller;

use App\Discount;
use App\Http\Controllers\Controller;

class DiscountsController extends Controller
{
    /**
     * Returns (active) discounts defined in the system.
     *
     * @return \Illuminate\Http\JsonResponse JSON response
     */
    public function index()
    {
        $user = auth()->user();
        $tenantId = $user->tenant->id;
        $discounts = [];

        Discount::where([
                'active' => true,
                'tenant_id' => $tenantId
            ])
            ->orderBy('discount')
            ->get()
            ->map(function ($discount) use (&$discounts) {
                $label = $discount->discount . '% - ' . $discount->description;

                if ($discount->code) {
                    $label .= " [{$discount->code}]";
                }

                $discounts[] = [
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
