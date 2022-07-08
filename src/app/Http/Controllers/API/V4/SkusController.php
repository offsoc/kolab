<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\ResourceController;
use App\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SkusController extends ResourceController
{
    /**
     * Get a list of active SKUs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $type = request()->input('type');

        // Note: Order by title for consistent ordering in tests
        $response = Sku::withSubjectTenantContext()->where('active', true)->orderBy('title')
            ->get()
            ->transform(function ($sku) {
                return $this->skuElement($sku);
            })
            ->filter(function ($sku) use ($type) {
                return !$type || $sku['type'] === $type;
            })
            ->sortByDesc('prio')
            ->values();

        if ($type) {
            $wallet = $this->guard()->user()->wallet();

            // Figure out the cost for a new object of the specified type
            $response = $response->map(function ($sku) use ($wallet) {
                $sku['nextCost'] = $sku['cost'];
                if ($sku['cost'] && $sku['units_free']) {
                    $count = $wallet->entitlements()->where('sku_id', $sku['id'])->count();

                    if ($count < $sku['units_free']) {
                        $sku['nextCost'] = 0;
                    }
                }

                return $sku;
            });
        }

        return response()->json($response->all());
    }

    /**
     * Return SKUs available to the specified entitleable object.
     *
     * @param object $object Entitleable object
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function objectSkus($object)
    {
        $response = [];

        // Note: Order by title for consistent ordering in tests
        $skus = Sku::withObjectTenantContext($object)->orderBy('title')->get();

        foreach ($skus as $sku) {
            if (!class_exists($sku->handler_class)) {
                continue;
            }

            if ($object::class != $sku->handler_class::entitleableClass()) {
                continue;
            }

            if (!$sku->handler_class::isAvailable($sku, $object)) {
                continue;
            }

            if ($data = self::skuElement($sku)) {
                if (!empty($data['controllerOnly'])) {
                    $user = Auth::guard()->user();
                    if (!$user->wallet()->isController($user)) {
                        continue;
                    }
                }

                $response[] = $data;
            }
        }

        usort($response, function ($a, $b) {
            return ($b['prio'] <=> $a['prio']);
        });

        return response()->json($response);
    }

    /**
     * Include SKUs/Wallet information in the object's response.
     *
     * @param object $object   User/Domain/etc object
     * @param array  $response The response to put the data into
     */
    public static function objectEntitlements($object, &$response = []): void
    {
        // Object's entitlements information
        $response['skus'] = \App\Entitlement::objectEntitlementsSummary($object);

        // Some basic information about the object's wallet
        $wallet = $object->wallet();
        $response['wallet'] = $wallet->toArray();
        if ($wallet->discount) {
            $response['wallet']['discount'] = $wallet->discount->discount;
            $response['wallet']['discount_description'] = $wallet->discount->description;
        }
    }

    /**
     * Update object entitlements.
     *
     * @param object       $object The object for update
     * @param array        $rSkus  List of SKU IDs requested for the object in the form [id=>qty]
     * @param ?\App\Wallet $wallet The target wallet
     */
    public static function updateEntitlements($object, $rSkus, $wallet = null): void
    {
        if (!is_array($rSkus)) {
            return;
        }

        // list of skus, [id=>obj]
        $skus = Sku::withEnvTenantContext()->get()->mapWithKeys(
            function ($sku) {
                return [$sku->id => $sku];
            }
        );

        // existing entitlement's SKUs
        $eSkus = [];

        $object->entitlements()->groupBy('sku_id')
            ->selectRaw('count(*) as total, sku_id')->each(
                function ($e) use (&$eSkus) {
                    $eSkus[$e->sku_id] = $e->total;
                }
            );

        foreach ($skus as $skuID => $sku) {
            $e = array_key_exists($skuID, $eSkus) ? $eSkus[$skuID] : 0;
            $r = array_key_exists($skuID, $rSkus) ? $rSkus[$skuID] : 0;

            if (!is_a($object, $sku->handler_class::entitleableClass())) {
                continue;
            }

            if ($sku->handler_class == \App\Handlers\Mailbox::class) {
                if ($r != 1) {
                    throw new \Exception("Invalid quantity of mailboxes");
                }
            }

            if ($e > $r) {
                // remove those entitled more than existing
                $object->removeSku($sku, ($e - $r));
            } elseif ($e < $r) {
                // add those requested more than entitled
                $object->assignSku($sku, ($r - $e), $wallet);
            }
        }
    }

    /**
     * Convert SKU information to metadata used by UI to
     * display the form control
     *
     * @param \App\Sku $sku SKU object
     *
     * @return array|null Metadata
     */
    protected static function skuElement($sku): ?array
    {
        if (!class_exists($sku->handler_class)) {
            return null;
        }

        $data = array_merge($sku->toArray(), $sku->handler_class::metadata($sku));

        // ignore incomplete handlers
        if (empty($data['type'])) {
            return null;
        }

        // Use localized value, toArray() does not get them right
        $data['name'] = $sku->name;
        $data['description'] = $sku->description;

        unset($data['handler_class'], $data['created_at'], $data['updated_at'], $data['fee'], $data['tenant_id']);

        return $data;
    }
}
