<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RelationController extends ResourceController
{
    /** @var array Common object properties in the API response */
    protected $objectProps = [];

    /** @var string Resource localization label */
    protected $label = '';

    /** @var string Resource model name */
    protected $model = '';

    /** @var array Resource listing order (column names) */
    protected $order = [];

    /** @var array Resource relation method arguments */
    protected $relationArgs = [];

    /**
     * Delete a resource.
     *
     * @param string $id Resource identifier
     *
     * @return JsonResponse The response
     */
    public function destroy($id)
    {
        $resource = $this->model::find($id);

        if (!$this->checkTenant($resource)) {
            \Log::info("Tenant mismatch");
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canDelete($resource)) {
            return $this->errorResponse(403);
        }

        $resource->delete();

        return response()->json([
            'status' => 'success',
            'message' => \trans("app.{$this->label}-delete-success"),
        ]);
    }

    /**
     * Listing of resources belonging to the authenticated user.
     *
     * The resource entitlements billed to the current user wallet(s)
     *
     * @return JsonResponse
     */
    public function index()
    {
        $user = $this->guard()->user();

        $method = Str::plural(\lcfirst(\class_basename($this->model)));

        $query = call_user_func_array([$user, $method], $this->relationArgs);

        if (!empty($this->order)) {
            foreach ($this->order as $col) {
                $query->orderBy($col);
            }
        }

        // TODO: Search and paging

        $result = $query->get()
            ->map(function ($resource) {
                return $this->objectToClient($resource);
            });

        $result = [
            'list' => $result,
            'count' => count($result),
            'hasMore' => false,
            'message' => \trans("app.search-foundx{$this->label}s", ['x' => count($result)]),
        ];

        return response()->json($result);
    }

    /**
     * Prepare resource statuses for the UI
     *
     * @param object $resource Resource object
     *
     * @return array Statuses array
     */
    protected static function objectState($resource): array
    {
        $state = [];

        $reflect = new \ReflectionClass($resource::class);

        foreach (array_keys($reflect->getConstants()) as $const) {
            if (str_starts_with($const, 'STATUS_') && $const != 'STATUS_NEW') {
                $method = Str::camel('is_' . strtolower(substr($const, 7)));
                $state[$method] = $resource->{$method}();
            }
        }

        $with_ldap = \config('app.with_ldap');

        $state['isReady'] = (!isset($state['isActive']) || $state['isActive'])
            && (!isset($state['isImapReady']) || $state['isImapReady'])
            && (!$with_ldap || !isset($state['isLdapReady']) || $state['isLdapReady'])
            && (!isset($state['isVerified']) || $state['isVerified'])
            && (!isset($state['isConfirmed']) || $state['isConfirmed']);

        if (!$with_ldap) {
            unset($state['isLdapReady']);
        }

        if (empty($state['isDeleted']) && method_exists($resource, 'trashed')) {
            $state['isDeleted'] = $resource->trashed();
        }

        return $state;
    }

    /**
     * Prepare a resource object for the UI.
     *
     * @param object $object An object
     * @param bool   $full   Include all object properties
     *
     * @return array Object information
     */
    protected function objectToClient($object, bool $full = false): array
    {
        if ($full) {
            $result = $object->toArray();

            unset($result['tenant_id']);
        } else {
            $result = ['id' => $object->id];

            foreach ($this->objectProps as $prop) {
                $result[$prop] = $object->{$prop};
            }
        }

        $result = array_merge($result, $this->objectState($object));

        return $result;
    }

    /**
     * Object status' process information.
     *
     * @param object $object The object to process
     * @param array  $steps  The steps definition
     *
     * @return array Process state information
     */
    protected static function processStateInfo($object, array $steps): array
    {
        $process = [];
        $withLdap = \config('app.with_ldap');

        // Create a process check list
        foreach ($steps as $step_name => $state) {
            // Remove LDAP related steps if the backend is disabled
            if (!$withLdap && strpos($step_name, '-ldap-')) {
                continue;
            }

            $step = [
                'label' => $step_name,
                'title' => \trans("app.process-{$step_name}"),
            ];

            if (is_array($state)) {
                $step['link'] = $state[1];
                $state = $state[0];
            }

            $step['state'] = $state;

            $process[] = $step;
        }

        // Add domain specific steps
        if (method_exists($object, 'domain')) {
            $domain = $object->domain();

            // If that is not a public domain
            if ($domain && !$domain->isPublic()) {
                $domain_status = API\V4\DomainsController::statusInfo($domain);
                $process = array_merge($process, $domain_status['process']);
            }
        }

        $all = count($process);
        $checked = count(array_filter($process, static function ($v) {
            return $v['state'];
        }));

        $state = $all === $checked ? 'done' : 'running';

        // After 180 seconds assume the process is in failed state,
        // this should unlock the Refresh button in the UI
        if ($all !== $checked && $object->created_at->diffInSeconds(Carbon::now()) > 180) {
            $state = 'failed';
        }

        return [
            'process' => $process,
            'processState' => $state,
            'isDone' => $all === $checked,
        ];
    }

    /**
     * Object status' process information update.
     *
     * @param object $object The object to process
     *
     * @return array Process state information
     */
    protected function processStateUpdate($object): array
    {
        $response = $this->statusInfo($object);

        if (!empty(request()->input('refresh'))) {
            $updated = false;
            $async = false;
            $last_step = 'none';

            foreach ($response['process'] as $idx => $step) {
                $last_step = $step['label'];

                if (!$step['state']) {
                    $exec = $this->execProcessStep($object, $step['label']); // @phpstan-ignore-line

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
                $response = $this->statusInfo($object);
            }

            $success = $response['isDone'];
            $suffix = $success ? 'success' : 'error-' . $last_step;

            $response['status'] = $success ? 'success' : 'error';
            $response['message'] = \trans('app.process-' . $suffix);

            if ($async && !$success) {
                $response['processState'] = 'waiting';
                $response['status'] = 'success';
                $response['message'] = \trans('app.process-async');
            }
        }

        return $response;
    }

    /**
     * Set the resource configuration.
     *
     * @param int $id Resource identifier
     *
     * @return JsonResponse|void
     */
    public function setConfig($id)
    {
        $resource = $this->model::find($id);

        if (!method_exists($this->model, 'setConfig')) {
            \Log::info("Tenant mismatch");
            return $this->errorResponse(404);
        }

        if (!$this->checkTenant($resource)) {
            return $this->errorResponse(404);
        }

        // Only wallet controller can do this, therefor canDelete() not canUpdate()
        // TODO: Consider changes in canUpdate() or introduce isController()
        if (!$this->guard()->user()->canDelete($resource)) {
            return $this->errorResponse(403);
        }

        $errors = $resource->setConfig(request()->input());

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => \trans("app.{$this->label}-setconfig-success"),
        ]);
    }

    /**
     * Display information of a resource specified by $id.
     *
     * @param string $id the resource to show information for
     *
     * @return JsonResponse
     */
    public function show($id)
    {
        $resource = $this->model::find($id);

        if (!$this->checkTenant($resource)) {
            \Log::info("Tenant mismatch");
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($resource)) {
            return $this->errorResponse(403);
        }

        $response = $this->objectToClient($resource, true);

        if (!empty($statusInfo = $this->statusInfo($resource))) {
            $response['statusInfo'] = $statusInfo;
        }

        // Resource configuration, e.g. sender_policy, invitation_policy, acl
        if (method_exists($resource, 'getConfig')) {
            $response['config'] = $resource->getConfig();
        }

        if (method_exists($resource, 'aliases')) {
            $response['aliases'] = $resource->aliases()->pluck('alias')->all();
        }

        // Entitlements/Wallet info
        if (method_exists($resource, 'wallet')) {
            API\V4\SkusController::objectEntitlements($resource, $response);
        }

        return response()->json($response);
    }

    /**
     * Get a list of SKUs available to the resource.
     *
     * @param int $id Resource identifier
     *
     * @return JsonResponse
     */
    public function skus($id)
    {
        $resource = $this->model::find($id);

        if (!$this->checkTenant($resource)) {
            \Log::info("Tenant mismatch");
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($resource)) {
            return $this->errorResponse(403);
        }

        return API\V4\SkusController::objectSkus($resource);
    }

    /**
     * Fetch resource status (and reload setup process)
     *
     * @param int $id Resource identifier
     *
     * @return JsonResponse
     */
    public function status($id)
    {
        $resource = $this->model::find($id);

        if (!$this->checkTenant($resource)) {
            \Log::info("Tenant mismatch");
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($resource)) {
            return $this->errorResponse(403);
        }

        $response = $this->processStateUpdate($resource);
        $response = array_merge($response, $this->objectState($resource));

        return response()->json($response);
    }

    /**
     * Resource status (extended) information
     *
     * @param object $resource Resource object
     *
     * @return array Status information
     */
    public static function statusInfo($resource): array
    {
        return [];
    }
}
