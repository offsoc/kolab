<?php

namespace App\Http\Controllers\API\V4\Reseller;

use App\Resource;
use App\User;

class ResourcesController extends \App\Http\Controllers\API\V4\Admin\ResourcesController
{
    /**
     * Search for resources
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $search = trim(request()->input('search'));
        $owner = trim(request()->input('owner'));
        $result = collect([]);

        if ($owner) {
            if ($owner = User::withSubjectTenantContext()->find($owner)) {
                $result = $owner->resources(false)->orderBy('name')->get();
            }
        } elseif (!empty($search)) {
            if ($resource = Resource::withSubjectTenantContext()->where('email', $search)->first()) {
                $result->push($resource);
            }
        }

        // Process the result
        $result = $result->map(
            function ($resource) {
                return $this->objectToClient($resource);
            }
        );

        $result = [
            'list' => $result,
            'count' => count($result),
            'message' => \trans('app.search-foundxresources', ['x' => count($result)]),
        ];

        return response()->json($result);
    }
}
