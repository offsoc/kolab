<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\SharedFolder;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedFoldersController extends \App\Http\Controllers\API\V4\SharedFoldersController
{
    /**
     * Search for shared folders
     *
     * @return JsonResponse
     */
    public function index()
    {
        $search = trim(request()->input('search'));
        $owner = trim(request()->input('owner'));
        $result = collect([]);

        if ($owner) {
            if ($owner = User::find($owner)) {
                $result = $owner->sharedFolders(false)->orderBy('name')->get();
            }
        } elseif (!empty($search)) {
            if ($folder = SharedFolder::where('email', $search)->first()) {
                $result->push($folder);
            }
        }

        // Process the result
        $result = $result->map(
            function ($folder) {
                return $this->objectToClient($folder);
            }
        );

        $result = [
            'list' => $result,
            'count' => count($result),
            'message' => self::trans('app.search-foundxshared-folders', ['x' => count($result)]),
        ];

        return response()->json($result);
    }

    /**
     * Create a new shared folder.
     *
     * @param Request $request the API request
     *
     * @return JsonResponse The response
     */
    public function store(Request $request)
    {
        return $this->errorResponse(404);
    }
}
