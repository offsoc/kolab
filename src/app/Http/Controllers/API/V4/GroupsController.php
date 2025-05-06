<?php

namespace App\Http\Controllers\API\V4;

use App\Domain;
use App\Group;
use App\Http\Controllers\RelationController;
use App\Jobs\Group\CreateJob;
use App\Rules\ExternalEmail;
use App\Rules\GroupName;
use App\Rules\UserEmailLocal;
use App\User;
use Illuminate\Http\JsonResponse;
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
     * @param Group $group Group object
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
     * @param Request $request the API request
     *
     * @return JsonResponse The response
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
        if ($error = self::validateGroupEmail($email, $owner)) {
            $errors['email'] = $error;
        } else {
            [, $domainName] = explode('@', $email);
            $rules['name'] = ['required', 'string', new GroupName($owner, $domainName)];
        }

        // Validate the group name
        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            $errors = array_merge($errors, $v->errors()->toArray());
        }

        // Validate members' email addresses
        if (empty($members) || !is_array($members)) {
            $errors['members'] = self::trans('validation.listmembersrequired');
        } else {
            foreach ($members as $i => $member) {
                if (is_string($member) && !empty($member)) {
                    if ($error = self::validateMemberEmail($member, $owner)) {
                        $errors['members'][$i] = $error;
                    } elseif (\strtolower($member) === \strtolower($email)) {
                        $errors['members'][$i] = self::trans('validation.memberislist');
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
            'message' => self::trans('app.distlist-create-success'),
        ]);
    }

    /**
     * Update a group.
     *
     * @param Request $request the API request
     * @param string  $id      Group identifier
     *
     * @return JsonResponse The response
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
            [, $domainName] = explode('@', $group->email);
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
            $errors['members'] = self::trans('validation.listmembersrequired');
        } else {
            foreach ((array) $members as $i => $member) {
                if (is_string($member) && !empty($member)) {
                    if ($error = self::validateMemberEmail($member, $owner)) {
                        $errors['members'][$i] = $error;
                    } elseif (\strtolower($member) === $group->email) {
                        $errors['members'][$i] = self::trans('validation.memberislist');
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
            'message' => self::trans('app.distlist-update-success'),
        ]);
    }

    /**
     * Execute (synchronously) specified step in a group setup process.
     *
     * @param Group  $group Group object
     * @param string $step  Step identifier (as in self::statusInfo())
     *
     * @return bool|null True if the execution succeeded, False if not, Null when
     *                   the job has been sent to the worker (result unknown)
     */
    public static function execProcessStep(Group $group, string $step): ?bool
    {
        try {
            if (str_starts_with($step, 'domain-')) {
                return DomainsController::execProcessStep($group->domain(), $step);
            }

            switch ($step) {
                case 'distlist-ldap-ready':
                    // Group not in LDAP, create it
                    CreateJob::dispatch($group->id);
                    return null;
            }
        } catch (\Exception $e) {
            \Log::error($e);
        }

        return false;
    }

    /**
     * Validate an email address for use as a group email
     *
     * @param string $email Email address
     * @param User   $user  The group owner
     *
     * @return ?string Error message on validation error
     */
    public static function validateGroupEmail($email, User $user): ?string
    {
        if (empty($email)) {
            return self::trans('validation.required', ['attribute' => 'email']);
        }

        if (!str_contains($email, '@')) {
            return self::trans('validation.entryinvalid', ['attribute' => 'email']);
        }

        [$login, $domain] = explode('@', \strtolower($email));

        if ($login === '' || $domain === '') {
            return self::trans('validation.entryinvalid', ['attribute' => 'email']);
        }

        // Check if domain exists
        $domain = Domain::where('namespace', $domain)->first();

        if (empty($domain)) {
            return self::trans('validation.domaininvalid');
        }

        $wallet = $domain->wallet();

        // The domain must be owned by the user
        if (!$wallet || !$user->wallets()->find($wallet->id)) {
            return self::trans('validation.domainnotavailable');
        }

        // Validate login part alone
        $v = Validator::make(
            ['email' => $login],
            ['email' => [new UserEmailLocal(true)]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }

        // Check if a user with specified address already exists
        if (User::emailExists($email)) {
            return self::trans('validation.entryexists', ['attribute' => 'email']);
        }

        // Check if an alias with specified address already exists.
        if (User::aliasExists($email)) {
            return self::trans('validation.entryexists', ['attribute' => 'email']);
        }

        if (Group::emailExists($email)) {
            return self::trans('validation.entryexists', ['attribute' => 'email']);
        }

        return null;
    }

    /**
     * Validate an email address for use as a group member
     *
     * @param string $email Email address
     * @param User   $user  The group owner
     *
     * @return ?string Error message on validation error
     */
    public static function validateMemberEmail($email, User $user): ?string
    {
        $v = Validator::make(
            ['email' => $email],
            ['email' => [new ExternalEmail()]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }

        // A local domain user must exist
        if (!User::where('email', \strtolower($email))->first()) {
            [$login, $domain] = explode('@', \strtolower($email));

            $domain = Domain::where('namespace', $domain)->first();

            // We return an error only if the domain belongs to the group owner
            if ($domain && ($wallet = $domain->wallet()) && $user->wallets()->find($wallet->id)) {
                return self::trans('validation.notalocaluser');
            }
        }

        return null;
    }
}
