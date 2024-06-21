<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Resource;
use App\User;
use Illuminate\Http\Request;

class ResourcesController extends \App\Http\Controllers\API\V4\ResourcesController
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
            if ($owner = User::find($owner)) {
                $result = $owner->resources(false)->orderBy('name')->get();
            }
        } elseif (!empty($search)) {
            if ($resource = Resource::where('email', $search)->first()) {
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
            'message' => self::trans('app.search-foundxresources', ['x' => count($result)]),
        ];

        return response()->json($result);
    }

    /**
     * Create a new resource.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function store(Request $request)
    {
        return $this->errorResponse(404);
    }
}
