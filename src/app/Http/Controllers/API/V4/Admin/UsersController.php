<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Domain;
use App\User;
use App\UserSetting;

class UsersController extends \App\Http\Controllers\API\V4\UsersController
{
    /**
     * Searching of user accounts.
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
                $result = $owner->users(false)->orderBy('email')->get();
            }
        } elseif (strpos($search, '@')) {
            // Search by email
            if ($user = User::findByEmail($search, false)) {
                $result->push($user);
            } else {
                // Search by an external email
                // TODO: This is not optimal (external email should be in users table)
                $user_ids = UserSetting::where('key', 'external_email')->where('value', $search)
                    ->get()->pluck('user_id');

                // TODO: Sort order
                $result = User::find($user_ids);
            }
        } elseif (is_numeric($search)) {
            // Search by user ID
            if ($user = User::find($search)) {
                $result->push($user);
            }
        } elseif (!empty($search)) {
            // Search by domain
            if ($domain = Domain::where('namespace', $search)->first()) {
                if ($wallet = $domain->wallet()) {
                    $result->push($wallet->owner);
                }
            }
        }

        // Process the result
        $result = $result->map(function ($user) {
            $data = $user->toArray();
            $data = array_merge($data, self::userStatuses($user));
            return $data;
        });

        $result = [
            'list' => $result,
            'count' => count($result),
            'message' => \trans('app.search-foundxusers', ['x' => count($result)]),
        ];

        return response()->json($result);
    }
}
