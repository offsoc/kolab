<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\ResourceController;
use App\Sku;
use Illuminate\Http\Request;

class SkusController extends ResourceController
{
    /**
     * Get a list of SKUs available to the domain.
     *
     * @param int $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function domainSkus($id)
    {
        $domain = \App\Domain::find($id);

        if (!$this->checkTenant($domain)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($domain)) {
            return $this->errorResponse(403);
        }

        return $this->objectSkus($domain);
    }

    /**
     * Get a list of active SKUs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Note: Order by title for consistent ordering in tests
        $skus = Sku::withSubjectTenantContext()->where('active', true)->orderBy('title')->get();

        $response = [];

        foreach ($skus as $sku) {
            if ($data = $this->skuElement($sku)) {
                $response[] = $data;
            }
        }

        usort($response, function ($a, $b) {
            return ($b['prio'] <=> $a['prio']);
        });

        return response()->json($response);
    }

    /**
     * Get a list of SKUs available to the user.
     *
     * @param int $id User identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userSkus($id)
    {
        $user = \App\User::find($id);

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($user)) {
            return $this->errorResponse(403);
        }

        return $this->objectSkus($user);
    }

    /**
     * Return SKUs available to the specified user/domain.
     *
     * @param object $object User or Domain object
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected static function objectSkus($object)
    {
        $type = $object instanceof \App\Domain ? 'domain' : 'user';
        $response = [];

        // Note: Order by title for consistent ordering in tests
        $skus = Sku::withObjectTenantContext($object)->orderBy('title')->get();

        foreach ($skus as $sku) {
            if (!class_exists($sku->handler_class)) {
                continue;
            }

            if (!$sku->handler_class::isAvailable($sku, $object)) {
                continue;
            }

            if ($data = self::skuElement($sku)) {
                if ($type != $data['type']) {
                    continue;
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
