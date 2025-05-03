<?php

namespace App\Http\Controllers\API\V4\User;

use App\Delegation;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait DelegationTrait
{
    /**
     * Listing of delegations.
     *
     * @param string $id The user to get delegatees for
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delegations($id)
    {
        $user = User::find($id);

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();
        if ($user->id != $current_user->id && !$current_user->canRead($user)) {
            return $this->errorResponse(403);
        }

        $result = $user->delegatees()->orderBy('email')->get()
            ->map(function (User $user) {
                return [
                    'email' => $user->email,
                    'options' => $user->delegation->options ?? [],
                ];
            });

        $result = [
            'list' => $result,
            'count' => count($result),
            'hasMore' => false, // TODO
        ];

        return response()->json($result);
    }

    /**
     * Get a delegation info for the current user (for use by a webmail plugin)
     *
     * @param string $id The user to get delegators for
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function delegators($id)
    {
        $user = User::find($id);

        if (!$this->checkTenant($user) || $user->role) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();
        if ($user->id != $current_user->id && !$current_user->canDelete($user)) {
            return $this->errorResponse(403);
        }

        $delegators = $user->delegators()->orderBy('email')->get()
            ->map(function (User $user) {
                return [
                    'email' => $user->email,
                    'aliases' => $user->aliases()->pluck('alias'),
                    'name' => $user->name(),
                ];
            });

        return response()->json([
            'list' => $delegators,
            'count' => count($delegators),
            'hasMore' => false, // TODO
        ]);
    }

    /**
     * Delete a delegation.
     *
     * @param string $id    User identifier
     * @param string $email Delegatee's email address
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function deleteDelegation($id, $email)
    {
        $user = User::find($id);

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();
        if ($user->id != $current_user->id && !$current_user->canDelete($user)) {
            return $this->errorResponse(403);
        }

        $delegatee = User::where('email', $email)->first();

        if (!$delegatee) {
            return $this->errorResponse(404);
        }

        $delegation = Delegation::where('user_id', $user->id)->where('delegatee_id', $delegatee->id)->first();

        if (!$delegation) {
            return $this->errorResponse(404);
        }

        $delegation->delete();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.delegation-delete-success'),
        ]);
    }

    /**
     * Create a new delegation.
     *
     * @param string $id User identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function createDelegation($id)
    {
        $user = User::find($id);

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();
        if ($user->id != $current_user->id && !$current_user->canDelete($user)) {
            return $this->errorResponse(403);
        }

        $request = request();
        $rules = [
            'email' => 'required|email',
            'options' => 'required|array',
        ];

        // Validate input
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            $errors = $v->errors()->toArray();
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $errors = [];
        $options = [];
        $request->email = strtolower($request->email);

        if (
            $request->email == $user->email
            || !($delegatee = User::where('email', $request->email)->first())
            || $delegatee->domainNamespace() != $user->domainNamespace()
            || $user->delegatees()->where('delegatee_id', $delegatee->id)->exists()
        ) {
            $errors['email'] = [self::trans('validation.delegateeinvalid')];
        }

        foreach ($request->options as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if (!Delegation::validateOption($key, $value)) {
                $errors['options'] = [self::trans('validation.delegationoptioninvalid')];
                break;
            }

            $options[$key] = $value;
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $delegation = new Delegation();
        $delegation->user_id = $user->id;
        $delegation->delegatee_id = $delegatee->id; // @phpstan-ignore-line
        $delegation->options = $options;
        $delegation->save();

        return response()->json([
            'status' => 'success',
            'message' => self::trans('app.delegation-create-success'),
        ]);
    }
}
