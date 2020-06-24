<?php

namespace App\Http\Controllers\API\V4\Admin;

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
            if ($owner = User::find($owner)) {
                $result = $owner->users(false)->orderBy('email')->get();
            }
        } elseif (strpos($search, '@')) {
            // Search by email
            $user = User::where('email', $search)->first();
            if ($user) {
                $result->push($user);
            } else {
                // Search by an alias
                $user_ids = UserAlias::where('alias', $search)->get()->pluck('user_id');
                if ($user_ids->isEmpty()) {
                    // Search by an external email
                    $user_ids = UserSetting::where('key', 'external_email')
                        ->where('value', $search)->get()->pluck('user_id');
                }

                if (!$user_ids->isEmpty()) {
                    $result = User::whereIn('id', $user_ids)->orderBy('email')->get();
                }
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

    /**
     * Suspend the user
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @params string                  $id      User identifier
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
     * @params string                  $id      User identifier
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
     * @params string                  $id      User identifier
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
