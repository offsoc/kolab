<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\RelationController;
use App\Domain;
use App\Group;
use App\Rules\GroupName;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GroupsController extends RelationController
{
    /** @var string Resource localization label */
    protected $label = 'distlist';

    /** @var string Resource model name */
    protected $model = Group::class;

    /** @var array Resource listing order (column names) */
    protected $order = ['name', 'email'];

    /** @var array Common object properties in the API response */
    protected $objectProps = ['email', 'name'];


    /**
     * Group status (extended) information
     *
     * @param \App\Group $group Group object
     *
     * @return array Status information
     */
    public static function statusInfo($group): array
    {
        return self::processStateInfo(
            $group,
            [
                'distlist-new' => true,
                'distlist-ldap-ready' => $group->isLdapReady(),
            ]
        );
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

        $email = $request->input('email');
        $members = $request->input('members');
        $errors = [];
        $rules = [
            'name' => 'required|string|max:191',
        ];

        // Validate group address
        if ($error = GroupsController::validateGroupEmail($email, $owner)) {
            $errors['email'] = $error;
        } else {
            list(, $domainName) = explode('@', $email);
            $rules['name'] = ['required', 'string', new GroupName($owner, $domainName)];
        }

        // Validate the group name
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            $errors = array_merge($errors, $v->errors()->toArray());
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
        $group->name = $request->input('name');
        $group->email = $email;
        $group->members = $members;
        $group->save();

        $group->assignToWallet($owner->wallets->first());

        DB::commit();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.distlist-create-success'),
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

        if (!$this->checkTenant($group)) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();

        if (!$current_user->canUpdate($group)) {
            return $this->errorResponse(403);
        }

        $owner = $group->wallet()->owner;
        $name = $request->input('name');
        $members = $request->input('members');
        $errors = [];

        // Validate the group name
        if ($name !== null && $name != $group->name) {
            list(, $domainName) = explode('@', $group->email);
            $rules = ['name' => ['required', 'string', new GroupName($owner, $domainName)]];

            $v = Validator::make($request->all(), $rules);

            if ($v->fails()) {
                $errors = array_merge($errors, $v->errors()->toArray());
            } else {
                $group->name = $name;
            }
        }

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

        // SkusController::updateEntitlements($group, $request->skus);

        $group->members = $members;
        $group->save();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.distlist-update-success'),
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
