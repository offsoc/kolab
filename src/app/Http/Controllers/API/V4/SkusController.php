<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Sku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SkusController extends Controller
{
    /**
     * Show the form for creating a new sku.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Remove the specified sku from storage.
     *
     * @param int $id SKU identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Show the form for editing the specified sku.
     *
     * @param int $id SKU identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Get a list of active SKUs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Note: Order by title for consistent ordering in tests
        $skus = Sku::where('active', true)->orderBy('title')->get();

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
     * Store a newly created sku in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Display the specified sku.
     *
     * @param int $id SKU identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Update the specified sku in storage.
     *
     * @param \Illuminate\Http\Request $request Request object
     * @param int                      $id      SKU identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // TODO
        return $this->errorResponse(404);
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
        $user = \App\User::withEnvTenant()->find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        if (!Auth::guard()->user()->canRead($user)) {
            return $this->errorResponse(403);
        }

        $type = request()->input('type');
        $response = [];

        // Note: Order by title for consistent ordering in tests
        $skus = Sku::orderBy('title')->get();

        foreach ($skus as $sku) {
            if (!class_exists($sku->handler_class)) {
                continue;
            }

            if (!$sku->handler_class::isAvailable($sku, $user)) {
                continue;
            }

            if ($data = $this->skuElement($sku)) {
                if ($type && $type != $data['type']) {
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
    protected function skuElement($sku): ?array
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
