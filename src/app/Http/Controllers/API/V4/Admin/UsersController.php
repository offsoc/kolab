<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Domain;
use App\Group;
use App\Sku;
use App\User;
use App\UserAlias;
use App\UserSetting;
use App\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UsersController extends \App\Http\Controllers\API\V4\UsersController
{
    /**
     * Delete a user.
     *
     * @param int $id User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function destroy($id)
    {
        return $this->errorResponse(404);
    }

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
            $owner = User::find($owner);

            if ($owner) {
                $result = $owner->users(false)->orderBy('email')->get();
            }
        } elseif (strpos($search, '@')) {
            // Search by email
            $result = User::withTrashed()->where('email', $search)
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

                // Search by a distribution list email
                if ($group = Group::withTrashed()->where('email', $search)->first()) {
                    $user_ids = $user_ids->merge([$group->wallet()->user_id])->unique();
                }

                if (!$user_ids->isEmpty()) {
                    $result = User::withTrashed()->whereIn('id', $user_ids)
                        ->orderBy('email')
                        ->get();
                }
            }
        } elseif (is_numeric($search)) {
            // Search by user ID
            $user = User::withTrashed()->where('id', $search)
                ->first();

            if ($user) {
                $result->push($user);
            }
        } elseif (strpos($search, '.') !== false) {
            // Search by domain
            $domain = Domain::withTrashed()->where('namespace', $search)
                ->first();

            if ($domain) {
                if (($wallet = $domain->wallet()) && ($owner = $wallet->owner()->withTrashed()->first())) {
                    $result->push($owner);
                }
            }
        // A mollie customer ID
        } elseif (substr($search, 0, 4) == 'cst_') {
            $setting = \App\WalletSetting::where(
                [
                    'key' => 'mollie_id',
                    'value' => $search
                ]
            )->first();

            if ($setting) {
                if ($wallet = $setting->wallet) {
                    if ($owner = $wallet->owner()->withTrashed()->first()) {
                        $result->push($owner);
                    }
                }
            }
        // A mollie transaction ID
        } elseif (substr($search, 0, 3) == 'tr_') {
            $payment = \App\Payment::find($search);

            if ($payment) {
                if ($owner = $payment->wallet->owner()->withTrashed()->first()) {
                    $result->push($owner);
                }
            }
        } elseif (!empty($search)) {
            $wallet = Wallet::find($search);

            if ($wallet) {
                if ($owner = $wallet->owner()->withTrashed()->first()) {
                    $result->push($owner);
                }
            }
        }

        // Process the result
        $result = $result->map(
            function ($user) {
                $data = $user->toArray();
                $data = array_merge($data, self::userStatuses($user));
                return $data;
            }
        );

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

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canUpdate($user)) {
            return $this->errorResponse(403);
        }

        $sku = Sku::withObjectTenantContext($user)->where('title', '2fa')->first();

        // Note: we do select first, so the observer can delete
        //       2FA preferences from Roundcube database, so don't
        //       be tempted to replace first() with delete() below
        $entitlement = $user->entitlements()->where('sku_id', $sku->id)->first();
        $entitlement->delete();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.user-reset-2fa-success'),
        ]);
    }

    /**
     * Set/Add a SKU for the user
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      User identifier
     * @param string                   $sku     SKU title
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function setSku(Request $request, $id, $sku)
    {
        // For now we allow adding the 'beta' SKU only
        if ($sku != 'beta') {
            return $this->errorResponse(404);
        }

        $user = User::find($id);

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canUpdate($user)) {
            return $this->errorResponse(403);
        }

        $sku = Sku::withObjectTenantContext($user)->where('title', $sku)->first();

        if (!$sku) {
            return $this->errorResponse(404);
        }

        if ($user->entitlements()->where('sku_id', $sku->id)->first()) {
            return $this->errorResponse(422, \trans('app.user-set-sku-already-exists'));
        }

        $user->assignSku($sku);
        $entitlement = $user->entitlements()->where('sku_id', $sku->id)->first();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.user-set-sku-success'),
                'sku' => [
                    'cost' => $entitlement->cost,
                    'name' => $sku->name,
                    'id' => $sku->id,
                ]
        ]);
    }

    /**
     * Display information on the user account specified by $id.
     *
     * @param int $id The account to show information for.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($user)) {
            return $this->errorResponse(403);
        }

        $response = $this->userResponse($user);

        // Simplified Entitlement/SKU information,
        // TODO: I agree this format may need to be extended in future
        $response['skus'] = [];
        foreach ($user->entitlements as $ent) {
            $sku = $ent->sku;
            if (!isset($response['skus'][$sku->id])) {
                $response['skus'][$sku->id] = ['costs' => [], 'count' => 0];
            }
            $response['skus'][$sku->id]['count']++;
            $response['skus'][$sku->id]['costs'][] = $ent->cost;
        }

        $response['config'] = $user->getConfig();

        return response()->json($response);
    }

    /**
     * Create a new user record.
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

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canUpdate($user)) {
            return $this->errorResponse(403);
        }

        $user->suspend();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.user-suspend-success'),
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

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canUpdate($user)) {
            return $this->errorResponse(403);
        }

        $user->unsuspend();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.user-unsuspend-success'),
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

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canUpdate($user)) {
            return $this->errorResponse(403);
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
                'message' => \trans('app.user-update-success'),
        ]);
    }
}
