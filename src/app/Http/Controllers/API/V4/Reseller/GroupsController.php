<?php

namespace App\Http\Controllers\API\V4\Reseller;

use App\Group;
use App\User;

class GroupsController extends \App\Http\Controllers\API\V4\Admin\GroupsController
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
            if ($owner = User::withSubjectTenantContext()->find($owner)) {
                foreach ($owner->wallets as $wallet) {
                    $wallet->entitlements()->where('entitleable_type', Group::class)->get()
                        ->each(function ($entitlement) use ($result) {
                            $result->push($entitlement->entitleable);
                        });
                }

                $result = $result->sortBy('name')->values();
            }
        } elseif (!empty($search)) {
            if ($group = Group::withSubjectTenantContext()->where('email', $search)->first()) {
                $result->push($group);
            }
        }

        // Process the result
        $result = $result->map(function ($group) {
            $data = [
                'id' => $group->id,
                'email' => $group->email,
                'name' => $group->name,
            ];

            $data = array_merge($data, self::groupStatuses($group));
            return $data;
        });

        $result = [
            'list' => $result,
            'count' => count($result),
            'message' => \trans('app.search-foundxdistlists', ['x' => count($result)]),
        ];

        return response()->json($result);
    }
}
