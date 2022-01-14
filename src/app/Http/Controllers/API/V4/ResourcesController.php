<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\RelationController;
use App\Resource;
use App\Rules\ResourceName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ResourcesController extends RelationController
{
    /** @var string Resource localization label */
    protected $label = 'resource';

    /** @var string Resource model name */
    protected $model = Resource::class;

    /** @var array Resource listing order (column names) */
    protected $order = ['name'];

    /** @var array Common object properties in the API response */
    protected $objectProps = ['email', 'name'];


    /**
     * Resource status (extended) information
     *
     * @param \App\Resource $resource Resource object
     *
     * @return array Status information
     */
    public static function statusInfo($resource): array
    {
        return self::processStateInfo(
            $resource,
            [
                'resource-new' => true,
                'resource-ldap-ready' => $resource->isLdapReady(),
                'resource-imap-ready' => $resource->isImapReady(),
            ]
        );
    }

    /**
     * Create a new resource record.
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

        $rules = ['name' => ['required', 'string', new ResourceName($owner, $domain)]];

        $v = Validator::make($request->all(), $rules);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        DB::beginTransaction();

        // Create the resource
        $resource = new Resource();
        $resource->name = request()->input('name');
        $resource->domain = $domain;
        $resource->save();

        $resource->assignToWallet($owner->wallets->first());

        DB::commit();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.resource-create-success'),
        ]);
    }

    /**
     * Update a resource.
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      Resource identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function update(Request $request, $id)
    {
        $resource = Resource::find($id);

        if (!$this->checkTenant($resource)) {
            return $this->errorResponse(404);
        }

        $current_user = $this->guard()->user();

        if (!$current_user->canUpdate($resource)) {
            return $this->errorResponse(403);
        }

        $owner = $resource->wallet()->owner;

        $name = $request->input('name');
        $errors = [];

        // Validate the resource name
        if ($name !== null && $name != $resource->name) {
            $domainName = explode('@', $resource->email, 2)[1];
            $rules = ['name' => ['required', 'string', new ResourceName($owner, $domainName)]];

            $v = Validator::make($request->all(), $rules);

            if ($v->fails()) {
                $errors = $v->errors()->toArray();
            } else {
                $resource->name = $name;
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $resource->save();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.resource-update-success'),
        ]);
    }

    /**
     * Execute (synchronously) specified step in a resource setup process.
     *
     * @param \App\Resource $resource Resource object
     * @param string        $step     Step identifier (as in self::statusInfo())
     *
     * @return bool|null True if the execution succeeded, False if not, Null when
     *                   the job has been sent to the worker (result unknown)
     */
    public static function execProcessStep(Resource $resource, string $step): ?bool
    {
        try {
            if (strpos($step, 'domain-') === 0) {
                return DomainsController::execProcessStep($resource->domain(), $step);
            }

            switch ($step) {
                case 'resource-ldap-ready':
                    // Resource not in LDAP, create it
                    $job = new \App\Jobs\Resource\CreateJob($resource->id);
                    $job->handle();

                    $resource->refresh();

                    return $resource->isLdapReady();

                case 'resource-imap-ready':
                    // Resource not in IMAP? Verify again
                    // Do it synchronously if the imap admin credentials are available
                    // otherwise let the worker do the job
                    if (!\config('imap.admin_password')) {
                        \App\Jobs\Resource\VerifyJob::dispatch($resource->id);

                        return null;
                    }

                    $job = new \App\Jobs\Resource\VerifyJob($resource->id);
                    $job->handle();

                    $resource->refresh();

                    return $resource->isImapReady();
            }
        } catch (\Exception $e) {
            \Log::error($e);
        }

        return false;
    }
}
