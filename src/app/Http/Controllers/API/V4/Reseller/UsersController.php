<?php

namespace App\Http\Controllers\API\V4\Reseller;

use App\Domain;
use App\Group;
use App\Resource;
use App\SharedFolder;
use App\SharedFolderAlias;
use App\User;
use App\UserAlias;
use App\UserSetting;
use Illuminate\Http\JsonResponse;

class UsersController extends \App\Http\Controllers\API\V4\Admin\UsersController
{
    /**
     * Searching of user accounts.
     *
     * @return JsonResponse
     */
    public function index()
    {
        $search = trim(request()->input('search'));
        $owner = trim(request()->input('owner'));
        $result = collect([]);

        if ($owner) {
            $owner = User::where('id', $owner)
                ->withSubjectTenantContext()
                ->whereNull('role')
                ->first();

            if ($owner) {
                $result = $owner->users(false)->whereNull('role')->orderBy('email')->get();
            }
        } elseif (strpos($search, '@')) {
            // Search by email
            $result = User::withTrashed()->where('email', $search)
                ->withSubjectTenantContext()
                ->whereNull('role')
                ->orderBy('email')
                ->get();

            if ($result->isEmpty()) {
                // Search by an alias
                $user_ids = UserAlias::where('alias', $search)->get()->pluck('user_id');

                // Search by an external email
                $ext_user_ids = UserSetting::where('key', 'external_email')
                    ->where('value', $search)
                    ->get()
                    ->pluck('user_id');

                $user_ids = $user_ids->merge($ext_user_ids)->unique();

                // Search by an email of a group, resource, shared folder, etc.
                if ($group = Group::withTrashed()->where('email', $search)->first()) {
                    $user_ids = $user_ids->merge([$group->wallet()->user_id])->unique();
                } elseif ($resource = Resource::withTrashed()->where('email', $search)->first()) {
                    $user_ids = $user_ids->merge([$resource->wallet()->user_id])->unique();
                } elseif ($folder = SharedFolder::withTrashed()->where('email', $search)->first()) {
                    $user_ids = $user_ids->merge([$folder->wallet()->user_id])->unique();
                } elseif ($alias = SharedFolderAlias::where('alias', $search)->first()) {
                    $user_ids = $user_ids->merge([$alias->sharedFolder->wallet()->user_id])->unique();
                }

                if (!$user_ids->isEmpty()) {
                    $result = User::withTrashed()->whereIn('id', $user_ids)
                        ->withSubjectTenantContext()
                        ->whereNull('role')
                        ->orderBy('email')
                        ->get();
                }
            }
        } elseif (is_numeric($search)) {
            // Search by user ID
            $user = User::withTrashed()->where('id', $search)
                ->withSubjectTenantContext()
                ->whereNull('role')
                ->first();

            if ($user) {
                $result->push($user);
            }
        } elseif (!empty($search)) {
            // Search by domain
            $domain = Domain::withTrashed()->where('namespace', $search)
                ->withSubjectTenantContext()
                ->first();

            if ($domain) {
                $wallet = $domain->wallet();
                if ($wallet && ($owner = $wallet->owner()->withTrashed()->withSubjectTenantContext()->first())) {
                    $result->push($owner);
                }
            }
        }

        // Process the result
        $result = $result->map(
            function ($user) {
                return $this->objectToClient($user, true);
            }
        );

        $result = [
            'list' => $result,
            'count' => count($result),
            'message' => self::trans('app.search-foundxusers', ['x' => count($result)]),
        ];

        return response()->json($result);
    }
}
