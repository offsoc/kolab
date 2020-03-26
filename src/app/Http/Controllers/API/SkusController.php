<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Sku;
use Illuminate\Http\Request;

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
     * Display a listing of the sku.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $response = [];
        $skus = Sku::select()->get();

        // Note: we do not limit the result to active SKUs only.
        //       It's because we might need users assigned to old SKUs,
        //       we need to display these old SKUs on the entitlements list

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
     * Convert SKU information to metadata used by UI to
     * display the form control
     *
     * @param \App\Sku $sku SKU object
     *
     * @return array|null Metadata
     */
    protected function skuElement($sku): ?array
    {
        $type = $sku->handler_class::entitleableClass();

        // ignore incomplete handlers
        if (!$type) {
            return null;
        }

        $type = explode('\\', $type);
        $type = strtolower(end($type));

        $handler = explode('\\', $sku->handler_class);
        $handler = strtolower(end($handler));

        $data = $sku->toArray();

        $data['type'] = $type;
        $data['handler'] = $handler;
        $data['readonly'] = false;
        $data['enabled'] = false;
        $data['prio'] = $sku->handler_class::priority();

        // Use localized value, toArray() does not get them right
        $data['name'] = $sku->name;
        $data['description'] = $sku->description;

        unset($data['handler_class']);

        switch ($handler) {
            case 'activesync':
                $data['required'] = ['groupware'];
                break;

            case 'auth2f':
                $data['forbidden'] = ['activesync'];
                break;

            case 'storage':
                // Quota range input
                $data['readonly'] = true; // only the checkbox will be disabled, not range
                $data['enabled'] = true;
                $data['range'] = [
                    'min' => $data['units_free'],
                    'max' => $sku->handler_class::MAX_ITEMS,
                    'unit' => $sku->handler_class::ITEM_UNIT,
                ];
                break;

            case 'mailbox':
                // Mailbox is always enabled and cannot be unset
                $data['readonly'] = true;
                $data['enabled'] = true;
                break;
        }

        return $data;
    }
}
