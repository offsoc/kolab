<?php

namespace App\Http\Controllers\API\V4\Reseller;

use App\Domain;
use App\User;
use App\UserAlias;
use App\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            $owner = User::where('id', $owner)
                ->withUserTenant()
                ->whereNull('role')
                ->first();

            if ($owner) {
                $result = $owner->users(false)->whereNull('role')->orderBy('email')->get();
            }
        } elseif (strpos($search, '@')) {
            // Search by email
            $result = User::withTrashed()->where('email', $search)
                ->withUserTenant()
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

                if (!$user_ids->isEmpty()) {
                    $result = User::withTrashed()->whereIn('id', $user_ids)
                        ->withUserTenant()
                        ->whereNull('role')
                        ->orderBy('email')
                        ->get();
                }
            }
        } elseif (is_numeric($search)) {
            // Search by user ID
            $user = User::withTrashed()->where('id', $search)
                ->withUserTenant()
                ->whereNull('role')
                ->first();

            if ($user) {
                $result->push($user);
            }
        } elseif (!empty($search)) {
            // Search by domain
            $domain = Domain::withTrashed()->where('namespace', $search)
                ->withUserTenant()
                ->first();

            if ($domain) {
                if (
                    ($wallet = $domain->wallet())
                    && ($owner = $wallet->owner()->withTrashed()->withUserTenant()->first())
                    && empty($owner->role)
                ) {
                    $result->push($owner);
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

    /**
     * Update user data.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @params string                  $id      User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $user = User::where('id', $id)->withUserTenant()->first();

        if (empty($user) || $user->role == 'admin') {
            return $this->errorResponse(404);
        }

        // For now admins can change only user external email address

        $rules = [];

        if (array_key_exists('external_email', $request->input())) {
            $rules['external_email'] = 'email';
        }

        // Validate input
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        // Update user settings
        $settings = $request->only(array_keys($rules));

        if (!empty($settings)) {
            $user->setSettings($settings);
        }

        return response()->json([
                'status' => 'success',
                'message' => __('app.user-update-success'),
        ]);
    }
}
