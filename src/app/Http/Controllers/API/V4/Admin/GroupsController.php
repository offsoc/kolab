<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Group;
use App\User;
use Illuminate\Http\Request;

class GroupsController extends \App\Http\Controllers\API\V4\GroupsController
{
    /**
     * Search for groups
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
                foreach ($owner->wallets as $wallet) {
                    $wallet->entitlements()->where('entitleable_type', Group::class)->get()
                        ->each(function ($entitlement) use ($result) {
                            $result->push($entitlement->entitleable);
                        });
                }

                $result = $result->sortBy('name')->values();
            }
        } elseif (!empty($search)) {
            if ($group = Group::where('email', $search)->first()) {
                $result->push($group);
            }
        }

        // Process the result
        $result = $result->map(
            function ($group) {
                return $this->objectToClient($group);
            }
        );

        $result = [
            'list' => $result,
            'count' => count($result),
            'message' => \trans('app.search-foundxdistlists', ['x' => count($result)]),
        ];

        return response()->json($result);
    }

    /**
     * Create a new group.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function store(Request $request)
    {
        return $this->errorResponse(404);
    }

    /**
     * Suspend a group
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      Group identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function suspend(Request $request, $id)
    {
        $group = Group::find($id);

        if (!$this->checkTenant($group)) {
            return $this->errorResponse(404);
        }

        $group->suspend();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.distlist-suspend-success'),
        ]);
    }

    /**
     * Un-Suspend a group
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      Group identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function unsuspend(Request $request, $id)
    {
        $group = Group::find($id);

        if (!$this->checkTenant($group)) {
            return $this->errorResponse(404);
        }

        $group->unsuspend();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.distlist-unsuspend-success'),
        ]);
    }
}
