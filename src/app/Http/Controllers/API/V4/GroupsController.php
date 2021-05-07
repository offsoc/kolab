<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Domain;
use App\Group;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GroupsController extends Controller
{
    /**
     * Show the form for creating a new group.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        return $this->errorResponse(404);
    }

    /**
     * Delete a group.
     *
     * @param int $id Group identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function destroy($id)
    {
        $group = Group::find($id);

        if (empty($group)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canDelete($group)) {
            return $this->errorResponse(403);
        }

        $group->delete();

        return response()->json([
                'status' => 'success',
                'message' => __('app.distlist-delete-success'),
        ]);
    }

    /**
     * Show the form for editing the specified group.
     *
     * @param int $id Group identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Listing of groups belonging to the authenticated user.
     *
     * The group-entitlements billed to the current user wallet(s)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = $this->guard()->user();

        $result = $user->groups()->orderBy('email')->get()
            ->map(function (Group $group) {
                $data = [
                    'id' => $group->id,
                    'email' => $group->email,
                ];

                $data = array_merge($data, self::groupStatuses($group));
                return $data;
            });

        return response()->json($result);
    }

    /**
     * Display information of a group specified by $id.
     *
     * @param int $id The group to show information for.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $group = Group::find($id);

        if (empty($group)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($group)) {
            return $this->errorResponse(403);
        }

        $response = $group->toArray();

        $response = array_merge($response, self::groupStatuses($group));
        $response['statusInfo'] = self::statusInfo($group);

        return response()->json($response);
    }

    /**
     * Fetch group status (and reload setup process)
     *
     * @param int $id Group identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status($id)
    {
        $group = Group::find($id);

        if (empty($group)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($group)) {
            return $this->errorResponse(403);
        }

        $response = self::statusInfo($group);

        if (!empty(request()->input('refresh'))) {
            $updated = false;
            $async = false;
            $last_step = 'none';

            foreach ($response['process'] as $idx => $step) {
                $last_step = $step['label'];

                if (!$step['state']) {
                    $exec = $this->execProcessStep($group, $step['label']);

                    if (!$exec) {
                        if ($exec === null) {
                            $async = true;
                        }

                        break;
                    }

                    $updated = true;
                }
            }

            if ($updated) {
                $response = self::statusInfo($group);
            }

            $success = $response['isReady'];
            $suffix = $success ? 'success' : 'error-' . $last_step;

            $response['status'] = $success ? 'success' : 'error';
            $response['message'] = \trans('app.process-' . $suffix);

            if ($async && !$success) {
                $response['processState'] = 'waiting';
                $response['status'] = 'success';
                $response['message'] = \trans('app.process-async');
            }
        }

        $response = array_merge($response, self::groupStatuses($group));

        return response()->json($response);
    }

    /**
     * Group status (extended) information
     *
     * @param \App\Group $group Group object
     *
     * @return array Status information
     */
    public static function statusInfo(Group $group): array
    {
        $process = [];
        $steps = [
            'distlist-new' => true,
            'distlist-ldap-ready' => $group->isLdapReady(),
        ];

        // Create a process check list
        foreach ($steps as $step_name => $state) {
            $step = [
                'label' => $step_name,
                'title' => \trans("app.process-{$step_name}"),
                'state' => $state,
            ];

            $process[] = $step;
        }

        $domain = $group->domain();

        // If that is not a public domain, add domain specific steps
        if ($domain && !$domain->isPublic()) {
            $domain_status = DomainsController::statusInfo($domain);
            $process = array_merge($process, $domain_status['process']);
        }

        $all = count($process);
        $checked = count(array_filter($process, function ($v) {
                return $v['state'];
        }));

        $state = $all === $checked ? 'done' : 'running';

        // After 180 seconds assume the process is in failed state,
        // this should unlock the Refresh button in the UI
        if ($all !== $checked && $group->created_at->diffInSeconds(Carbon::now()) > 180) {
            $state = 'failed';
        }

        return [
            'process' => $process,
            'processState' => $state,
            'isReady' => $all === $checked,
        ];
    }

    /**
     * Create a new group record.
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function store(Request $request)
    {
        $current_user = $this->guard()->user();
        $owner = $current_user->wallet()->owner;

        if ($owner->id != $current_user->id) {
            return $this->errorResponse(403);
        }

        $email = request()->input('email');
        $members = request()->input('members');
        $errors = [];

        // Validate group address
        if ($error = GroupsController::validateGroupEmail($email, $owner)) {
            $errors['email'] = $error;
        }

        // Validate members' email addresses
        if (empty($members) || !is_array($members)) {
            $errors['members'] = \trans('validation.listmembersrequired');
        } else {
            foreach ($members as $i => $member) {
                if (is_string($member) && !empty($member)) {
                    if ($error = GroupsController::validateMemberEmail($member, $owner)) {
                        $errors['members'][$i] = $error;
                    } elseif (\strtolower($member) === \strtolower($email)) {
                        $errors['members'][$i] = \trans('validation.memberislist');
                    }
                } else {
                    unset($members[$i]);
                }
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        DB::beginTransaction();

        // Create the group
        $group = new Group();
        $group->email = $email;
        $group->members = $members;
        $group->save();

        $group->assignToWallet($owner->wallets->first());

        DB::commit();

        return response()->json([
                'status' => 'success',
                'message' => __('app.distlist-create-success'),
        ]);
    }

    /**
     * Update a group.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      Group identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $group = Group::find($id);

        if (empty($group)) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();

        if (!$current_user->canUpdate($group)) {
            return $this->errorResponse(403);
        }

        $owner = $group->wallet()->owner;

        // It is possible to update members property only for now
        $members = request()->input('members');
        $errors = [];

        // Validate members' email addresses
        if (empty($members) || !is_array($members)) {
            $errors['members'] = \trans('validation.listmembersrequired');
        } else {
            foreach ((array) $members as $i => $member) {
                if (is_string($member) && !empty($member)) {
                    if ($error = GroupsController::validateMemberEmail($member, $owner)) {
                        $errors['members'][$i] = $error;
                    } elseif (\strtolower($member) === $group->email) {
                        $errors['members'][$i] = \trans('validation.memberislist');
                    }
                } else {
                    unset($members[$i]);
                }
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $group->members = $members;
        $group->save();

        return response()->json([
                'status' => 'success',
                'message' => __('app.distlist-update-success'),
        ]);
    }

    /**
     * Execute (synchronously) specified step in a group setup process.
     *
     * @param \App\Group $group Group object
     * @param string     $step  Step identifier (as in self::statusInfo())
     *
     * @return bool|null True if the execution succeeded, False if not, Null when
     *                   the job has been sent to the worker (result unknown)
     */
    public static function execProcessStep(Group $group, string $step): ?bool
    {
        try {
            if (strpos($step, 'domain-') === 0) {
                return DomainsController::execProcessStep($group->domain(), $step);
            }

            switch ($step) {
                case 'distlist-ldap-ready':
                    // Group not in LDAP, create it
                    $job = new \App\Jobs\Group\CreateJob($group->id);
                    $job->handle();

                    $group->refresh();

                    return $group->isLdapReady();
            }
        } catch (\Exception $e) {
            \Log::error($e);
        }

        return false;
    }

    /**
     * Prepare group statuses for the UI
     *
     * @param \App\Group $group Group object
     *
     * @return array Statuses array
     */
    protected static function groupStatuses(Group $group): array
    {
        return [
            'isLdapReady' => $group->isLdapReady(),
            'isSuspended' => $group->isSuspended(),
            'isActive' => $group->isActive(),
            'isDeleted' => $group->isDeleted() || $group->trashed(),
        ];
    }

    /**
     * Validate an email address for use as a group email
     *
     * @param string    $email Email address
     * @param \App\User $user  The group owner
     *
     * @return ?string Error message on validation error
     */
    public static function validateGroupEmail($email, \App\User $user): ?string
    {
        if (empty($email)) {
            return \trans('validation.required', ['attribute' => 'email']);
        }

        if (strpos($email, '@') === false) {
            return \trans('validation.entryinvalid', ['attribute' => 'email']);
        }

        list($login, $domain) = explode('@', \strtolower($email));

        if (strlen($login) === 0 || strlen($domain) === 0) {
            return \trans('validation.entryinvalid', ['attribute' => 'email']);
        }

        // Check if domain exists
        $domain = Domain::where('namespace', $domain)->first();

        if (empty($domain)) {
            return \trans('validation.domaininvalid');
        }

        $wallet = $domain->wallet();

        // The domain must be owned by the user
        if (!$wallet || !$user->wallets()->find($wallet->id)) {
            return \trans('validation.domainnotavailable');
        }

        // Validate login part alone
        $v = Validator::make(
            ['email' => $login],
            ['email' => [new \App\Rules\UserEmailLocal(true)]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }

        // Check if a user with specified address already exists
        if (User::emailExists($email)) {
            return \trans('validation.entryexists', ['attribute' => 'email']);
        }

        // Check if an alias with specified address already exists.
        if (User::aliasExists($email)) {
            return \trans('validation.entryexists', ['attribute' => 'email']);
        }

        if (Group::emailExists($email)) {
            return \trans('validation.entryexists', ['attribute' => 'email']);
        }

        return null;
    }

    /**
     * Validate an email address for use as a group member
     *
     * @param string    $email Email address
     * @param \App\User $user  The group owner
     *
     * @return ?string Error message on validation error
     */
    public static function validateMemberEmail($email, \App\User $user): ?string
    {
        $v = Validator::make(
            ['email' => $email],
            ['email' => [new \App\Rules\ExternalEmail()]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }

        // A local domain user must exist
        if (!User::where('email', \strtolower($email))->first()) {
            list($login, $domain) = explode('@', \strtolower($email));

            $domain = Domain::where('namespace', $domain)->first();

            // We return an error only if the domain belongs to the group owner
            if ($domain && ($wallet = $domain->wallet()) && $user->wallets()->find($wallet->id)) {
                return \trans('validation.notalocaluser');
            }
        }

        return null;
    }
}
