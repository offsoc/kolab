<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\SharedFolder;
use App\User;
use Illuminate\Http\Request;

class SharedFoldersController extends \App\Http\Controllers\API\V4\SharedFoldersController
{
    /**
     * Search for shared folders
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
            'message' => \trans('app.search-foundxsharedfolders', ['x' => count($result)]),
        ];

        return response()->json($result);
    }

    /**
     * Create a new shared folder.
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
