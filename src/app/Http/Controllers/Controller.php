<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /** @var array Common object properties in the API response */
    protected static $objectProps = [];


    /**
     * Common error response builder for API (JSON) responses
     *
     * @param int    $code    Error code
     * @param string $message Error message
     * @param array  $data    Additional response data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function errorResponse(int $code, string $message = null, array $data = [])
    {
        $errors = [
            400 => "Bad request",
            401 => "Unauthorized",
            403 => "Access denied",
            404 => "Not found",
            405 => "Method not allowed",
            422 => "Input validation error",
            429 => "Too many requests",
            500 => "Internal server error",
        ];

        $response = [
            'status' => 'error',
            'message' => $message ?: (isset($errors[$code]) ? $errors[$code] : "Server error"),
        ];

        if (!empty($data)) {
            $response = $response + $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Check if current user has access to the specified object
     * by being an admin or existing in the same tenant context.
     *
     * @param ?object $object Model object
     *
     * @return bool
     */
    protected function checkTenant(object $object = null): bool
    {
        if (empty($object)) {
            return false;
        }

        $user = $this->guard()->user();

        if ($user->role == 'admin') {
            return true;
        }

        return $object->tenant_id == $user->tenant_id;
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\Guard
     */
    protected function guard()
    {
        return Auth::guard();
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

        // Create a process check list
        foreach ($steps as $step_name => $state) {
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
        $checked = count(array_filter($process, function ($v) {
                return $v['state'];
        }));

        $state = $all === $checked ? 'done' : 'running';

        // After 180 seconds assume the process is in failed state,
        // this should unlock the Refresh button in the UI
        if ($all !== $checked && $object->created_at->diffInSeconds(\Carbon\Carbon::now()) > 180) {
            $state = 'failed';
        }

        return [
            'process' => $process,
            'processState' => $state,
            'isReady' => $all === $checked,
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
        $response = $this->statusInfo($object); // @phpstan-ignore-line

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
                $response = $this->statusInfo($object); // @phpstan-ignore-line
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

        return $response;
    }

    /**
     * Prepare an object for the UI.
     *
     * @param object $object An object
     * @param bool   $full   Include all object properties
     *
     * @return array Object information
     */
    protected static function objectToClient($object, bool $full = false): array
    {
        if ($full) {
            $result = $object->toArray();
        } else {
            $result = ['id' => $object->id];

            foreach (static::$objectProps as $prop) {
                $result[$prop] = $object->{$prop};
            }
        }

        $result = array_merge($result, static::objectState($object)); // @phpstan-ignore-line

        return $result;
    }
}
