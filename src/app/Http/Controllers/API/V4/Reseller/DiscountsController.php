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

        $discounts = $user->tenant->discounts()
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
