<?php

namespace App\Http\Controllers\API\V4\Reseller;

use App\SharedFolder;
use App\User;

class SharedFoldersController extends \App\Http\Controllers\API\V4\Admin\SharedFoldersController
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
            if ($owner = User::withSubjectTenantContext()->find($owner)) {
                $result = $owner->sharedFolders(false)->orderBy('name')->get();
            }
        } elseif (!empty($search)) {
            if ($folder = SharedFolder::withSubjectTenantContext()->where('email', $search)->first()) {
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
}
