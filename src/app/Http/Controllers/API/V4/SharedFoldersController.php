<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\SharedFolder;
use App\Rules\SharedFolderName;
use App\Rules\SharedFolderType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SharedFoldersController extends Controller
{
    /** @var array Common object properties in the API response */
    protected static $objectProps = ['email', 'name', 'type'];

    /**
     * Show the form for creating a new shared folder.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        return $this->errorResponse(404);
    }

    /**
     * Delete a shared folder.
     *
     * @param int $id Shared folder identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function destroy($id)
    {
        $folder = SharedFolder::find($id);

        if (!$this->checkTenant($folder)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canDelete($folder)) {
            return $this->errorResponse(403);
        }

        $folder->delete();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.shared-folder-delete-success'),
        ]);
    }

    /**
     * Show the form for editing the specified shared folder.
     *
     * @param int $id Shared folder identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Listing of a shared folders belonging to the authenticated user.
     *
     * The shared-folder entitlements billed to the current user wallet(s)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = $this->guard()->user();

        $result = $user->sharedFolders()->orderBy('name')->get()
            ->map(function (SharedFolder $folder) {
                return $this->objectToClient($folder);
            });

        return response()->json($result);
    }

    /**
     * Set the shared folder configuration.
     *
     * @param int $id Shared folder identifier
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function setConfig($id)
    {
        $folder = SharedFolder::find($id);

        if (!$this->checkTenant($folder)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canUpdate($folder)) {
            return $this->errorResponse(403);
        }

        $errors = $folder->setConfig(request()->input());

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.shared-folder-setconfig-success'),
        ]);
    }

    /**
     * Display information of a shared folder specified by $id.
     *
     * @param int $id Shared folder identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $folder = SharedFolder::find($id);

        if (!$this->checkTenant($folder)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($folder)) {
            return $this->errorResponse(403);
        }

        $response = $this->objectToClient($folder, true);

        $response['statusInfo'] = self::statusInfo($folder);

        // Shared folder configuration, e.g. acl
        $response['config'] = $folder->getConfig();

        return response()->json($response);
    }

    /**
     * Fetch a shared folder status (and reload setup process)
     *
     * @param int $id Shared folder identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status($id)
    {
        $folder = SharedFolder::find($id);

        if (!$this->checkTenant($folder)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($folder)) {
            return $this->errorResponse(403);
        }

        $response = $this->processStateUpdate($folder);
        $response = array_merge($response, self::objectState($folder));

        return response()->json($response);
    }

    /**
     * SharedFolder status (extended) information
     *
     * @param \App\SharedFolder $folder SharedFolder object
     *
     * @return array Status information
     */
    public static function statusInfo(SharedFolder $folder): array
    {
        return self::processStateInfo(
            $folder,
            [
                'shared-folder-new' => true,
                'shared-folder-ldap-ready' => $folder->isLdapReady(),
                'shared-folder-imap-ready' => $folder->isImapReady(),
            ]
        );
    }

    /**
     * Create a new shared folder record.
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

        $domain = request()->input('domain');

        $rules = [
            'name' => ['required', 'string', new SharedFolderName($owner, $domain)],
            'type' => ['required', 'string', new SharedFolderType()]
        ];

        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        DB::beginTransaction();

        // Create the shared folder
        $folder = new SharedFolder();
        $folder->name = request()->input('name');
        $folder->type = request()->input('type');
        $folder->domain = $domain;
        $folder->save();

        $folder->assignToWallet($owner->wallets->first());

        DB::commit();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.shared-folder-create-success'),
        ]);
    }

    /**
     * Update a shared folder.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      Shared folder identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $folder = SharedFolder::find($id);

        if (!$this->checkTenant($folder)) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();

        if (!$current_user->canUpdate($folder)) {
            return $this->errorResponse(403);
        }

        $owner = $folder->wallet()->owner;

        $name = $request->input('name');
        $errors = [];

        // Validate the folder name
        if ($name !== null && $name != $folder->name) {
            $domainName = explode('@', $folder->email, 2)[1];
            $rules = ['name' => ['required', 'string', new SharedFolderName($owner, $domainName)]];

            $v = Validator::make($request->all(), $rules);

            if ($v->fails()) {
                $errors = $v->errors()->toArray();
            } else {
                $folder->name = $name;
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $folder->save();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.shared-folder-update-success'),
        ]);
    }

    /**
     * Execute (synchronously) specified step in a shared folder setup process.
     *
     * @param \App\SharedFolder $folder Shared folder object
     * @param string            $step   Step identifier (as in self::statusInfo())
     *
     * @return bool|null True if the execution succeeded, False if not, Null when
     *                   the job has been sent to the worker (result unknown)
     */
    public static function execProcessStep(SharedFolder $folder, string $step): ?bool
    {
        try {
            if (strpos($step, 'domain-') === 0) {
                return DomainsController::execProcessStep($folder->domain(), $step);
            }

            switch ($step) {
                case 'shared-folder-ldap-ready':
                    // Shared folder not in LDAP, create it
                    $job = new \App\Jobs\SharedFolder\CreateJob($folder->id);
                    $job->handle();

                    $folder->refresh();

                    return $folder->isLdapReady();

                case 'shared-folder-imap-ready':
                    // Shared folder not in IMAP? Verify again
                    // Do it synchronously if the imap admin credentials are available
                    // otherwise let the worker do the job
                    if (!\config('imap.admin_password')) {
                        \App\Jobs\SharedFolder\VerifyJob::dispatch($folder->id);

                        return null;
                    }

                    $job = new \App\Jobs\SharedFolder\VerifyJob($folder->id);
                    $job->handle();

                    $folder->refresh();

                    return $folder->isImapReady();
            }
        } catch (\Exception $e) {
            \Log::error($e);
        }

        return false;
    }

    /**
     * Prepare shared folder statuses for the UI
     *
     * @param \App\SharedFolder $folder Shared folder object
     *
     * @return array Statuses array
     */
    protected static function objectState(SharedFolder $folder): array
    {
        return [
            'isLdapReady' => $folder->isLdapReady(),
            'isImapReady' => $folder->isImapReady(),
            'isActive' => $folder->isActive(),
            'isDeleted' => $folder->isDeleted() || $folder->trashed(),
        ];
    }
}
