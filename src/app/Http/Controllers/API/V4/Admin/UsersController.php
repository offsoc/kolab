<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Domain;
use App\Group;
use App\Sku;
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
            if ($owner = User::find($owner)) {
                $result = $owner->users(false)->orderBy('email')->get();
            }
        } elseif (strpos($search, '@')) {
            // Search by email
            $result = User::withTrashed()->where('email', $search)
                ->orderBy('email')->get();

            if ($result->isEmpty()) {
                // Search by an alias
                $user_ids = UserAlias::where('alias', $search)->get()->pluck('user_id');

                // Search by an external email
                $ext_user_ids = UserSetting::where('key', 'external_email')
                    ->where('value', $search)->get()->pluck('user_id');

                $user_ids = $user_ids->merge($ext_user_ids)->unique();

                // Search by a distribution list email
                if ($group = Group::withTrashed()->where('email', $search)->first()) {
                    $user_ids = $user_ids->merge([$group->wallet()->user_id])->unique();
                }

                if (!$user_ids->isEmpty()) {
                    $result = User::withTrashed()->whereIn('id', $user_ids)
                        ->orderBy('email')->get();
                }
            }
        } elseif (is_numeric($search)) {
            // Search by user ID
            if ($user = User::withTrashed()->find($search)) {
                $result->push($user);
            }
        } elseif (!empty($search)) {
            // Search by domain
            if ($domain = Domain::withTrashed()->where('namespace', $search)->first()) {
                if ($wallet = $domain->wallet()) {
                    $result->push($wallet->owner()->withTrashed()->first());
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
     * Reset 2-Factor Authentication for the user
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function reset2FA(Request $request, $id)
    {
        $user = User::find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        $sku = Sku::where('title', '2fa')->first();

        // Note: we do select first, so the observer can delete
        //       2FA preferences from Roundcube database, so don't
        //       be tempted to replace first() with delete() below
        $entitlement = $user->entitlements()->where('sku_id', $sku->id)->first();
        $entitlement->delete();

        return response()->json([
                'status' => 'success',
                'message' => __('app.user-reset-2fa-success'),
        ]);
    }

    /**
     * Suspend the user
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function suspend(Request $request, $id)
    {
        $user = User::find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        $user->suspend();

        return response()->json([
                'status' => 'success',
                'message' => __('app.user-suspend-success'),
        ]);
    }

    /**
     * Un-Suspend the user
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function unsuspend(Request $request, $id)
    {
        $user = User::find($id);

        if (empty($user)) {
            return $this->errorResponse(404);
        }

        $user->unsuspend();

        return response()->json([
                'status' => 'success',
                'message' => __('app.user-unsuspend-success'),
        ]);
    }

    /**
     * Update user data.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (empty($user)) {
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
