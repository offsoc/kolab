<?php

namespace App\Http\Controllers\API\V4;

use App\Domain;
use App\Http\Controllers\Controller;
use App\Backends\LDAP;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DomainsController extends Controller
{
    /**
     * Return a list of domains owned by the current user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::guard()->user();
        $list = [];

        foreach ($user->domains() as $domain) {
            if (!$domain->isPublic()) {
                $data = $domain->toArray();
                $data = array_merge($data, self::domainStatuses($domain));
                $list[] = $data;
            }
        }

        return response()->json($list);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        return $this->errorResponse(404);
    }

    /**
     * Confirm ownership of the specified domain (via DNS check).
     *
     * @param int $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function confirm($id)
    {
        $domain = Domain::findOrFail($id);

        // Only owner (or admin) has access to the domain
        if (!Auth::guard()->user()->canRead($domain)) {
            return $this->errorResponse(403);
        }

        if (!$domain->confirm()) {
            return response()->json([
                    'status' => 'error',
                    'message' => \trans('app.domain-verify-error'),
            ]);
        }

        return response()->json([
                'status' => 'success',
                'statusInfo' => self::statusInfo($domain),
                'message' => \trans('app.domain-verify-success'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Set the domain configuration.
     *
     * @param int $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function setConfig($id)
    {
        $domain = Domain::find($id);

        if (empty($domain)) {
            return $this->errorResponse(404);
        }

        // Only owner (or admin) has access to the domain
        if (!Auth::guard()->user()->canRead($domain)) {
            return $this->errorResponse(403);
        }

        $errors = $domain->setConfig(request()->input());

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.domain-setconfig-success'),
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return $this->errorResponse(404);
    }

    /**
     * Get the information about the specified domain.
     *
     * @param int $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function show($id)
    {
        $domain = Domain::withEnvTenant()->findOrFail($id);

        // Only owner (or admin) has access to the domain
        if (!Auth::guard()->user()->canRead($domain)) {
            return $this->errorResponse(403);
        }

        $response = $domain->toArray();

        // Add hash information to the response
        $response['hash_text'] = $domain->hash(Domain::HASH_TEXT);
        $response['hash_cname'] = $domain->hash(Domain::HASH_CNAME);
        $response['hash_code'] = $domain->hash(Domain::HASH_CODE);

        // Add DNS/MX configuration for the domain
        $response['dns'] = self::getDNSConfig($domain);
        $response['mx'] = self::getMXConfig($domain->namespace);

        // Domain configuration, e.g. spf whitelist
        $response['config'] = $domain->getConfig();

        // Status info
        $response['statusInfo'] = self::statusInfo($domain);

        $response = array_merge($response, self::domainStatuses($domain));

        return response()->json($response);
    }

    /**
     * Fetch domain status (and reload setup process)
     *
     * @param int $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status($id)
    {
        $domain = Domain::withEnvTenant()->findOrFail($id);

        // Only owner (or admin) has access to the domain
        if (!Auth::guard()->user()->canRead($domain)) {
            return $this->errorResponse(403);
        }

        $response = self::statusInfo($domain);

        if (!empty(request()->input('refresh'))) {
            $updated = false;
            $last_step = 'none';

            foreach ($response['process'] as $idx => $step) {
                $last_step = $step['label'];

                if (!$step['state']) {
                    if (!$this->execProcessStep($domain, $step['label'])) {
                        break;
                    }

                    $updated = true;
                }
            }

            if ($updated) {
                $response = self::statusInfo($domain);
            }

            $success = $response['isReady'];
            $suffix = $success ? 'success' : 'error-' . $last_step;

            $response['status'] = $success ? 'success' : 'error';
            $response['message'] = \trans('app.process-' . $suffix);
        }

        $response = array_merge($response, self::domainStatuses($domain));

        return response()->json($response);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Provide DNS MX information to configure specified domain for
     */
    protected static function getMXConfig(string $namespace): array
    {
        $entries = [];

        // copy MX entries from an existing domain
        if ($master = \config('dns.copyfrom')) {
            // TODO: cache this lookup
            foreach ((array) dns_get_record($master, DNS_MX) as $entry) {
                $entries[] = sprintf(
                    "@\t%s\t%s\tMX\t%d %s.",
                    \config('dns.ttl', $entry['ttl']),
                    $entry['class'],
                    $entry['pri'],
                    $entry['target']
                );
            }
        } elseif ($static = \config('dns.static')) {
            $entries[] = strtr($static, array('\n' => "\n", '%s' => $namespace));
        }

        // display SPF settings
        if ($spf = \config('dns.spf')) {
            $entries[] = ';';
            foreach (['TXT', 'SPF'] as $type) {
                $entries[] = sprintf(
                    "@\t%s\tIN\t%s\t\"%s\"",
                    \config('dns.ttl'),
                    $type,
                    $spf
                );
            }
        }

        return $entries;
    }

    /**
     * Provide sample DNS config for domain confirmation
     */
    protected static function getDNSConfig(Domain $domain): array
    {
        $serial = date('Ymd01');
        $hash_txt = $domain->hash(Domain::HASH_TEXT);
        $hash_cname = $domain->hash(Domain::HASH_CNAME);
        $hash = $domain->hash(Domain::HASH_CODE);

        return [
            "@   IN  SOA ns1.dnsservice.com. hostmaster.{$domain->namespace}. (",
            "        {$serial}  10800  3600  604800  86400 )",
            ";",
            "@       IN  A   <some-ip>",
            "www     IN  A   <some-ip>",
            ";",
            "{$hash_cname}.{$domain->namespace}. IN CNAME {$hash}.{$domain->namespace}.",
            "@   3600    TXT \"{$hash_txt}\"",
        ];
    }

    /**
     * Prepare domain statuses for the UI
     *
     * @param \App\Domain $domain Domain object
     *
     * @return array Statuses array
     */
    protected static function domainStatuses(Domain $domain): array
    {
        return [
            'isLdapReady' => $domain->isLdapReady(),
            'isConfirmed' => $domain->isConfirmed(),
            'isVerified' => $domain->isVerified(),
            'isSuspended' => $domain->isSuspended(),
            'isActive' => $domain->isActive(),
            'isDeleted' => $domain->isDeleted() || $domain->trashed(),
        ];
    }

    /**
     * Domain status (extended) information.
     *
     * @param \App\Domain $domain Domain object
     *
     * @return array Status information
     */
    public static function statusInfo(Domain $domain): array
    {
        $process = [];

        // If that is not a public domain, add domain specific steps
        $steps = [
            'domain-new' => true,
            'domain-ldap-ready' => $domain->isLdapReady(),
            'domain-verified' => $domain->isVerified(),
            'domain-confirmed' => $domain->isConfirmed(),
        ];

        $count = count($steps);

        // Create a process check list
        foreach ($steps as $step_name => $state) {
            $step = [
                'label' => $step_name,
                'title' => \trans("app.process-{$step_name}"),
                'state' => $state,
            ];

            if ($step_name == 'domain-confirmed' && !$state) {
                $step['link'] = "/domain/{$domain->id}";
            }

            $process[] = $step;

            if ($state) {
                $count--;
            }
        }

        $state = $count === 0 ? 'done' : 'running';

        // After 180 seconds assume the process is in failed state,
        // this should unlock the Refresh button in the UI
        if ($count > 0 && $domain->created_at->diffInSeconds(Carbon::now()) > 180) {
            $state = 'failed';
        }

        return [
            'process' => $process,
            'processState' => $state,
            'isReady' => $count === 0,
        ];
    }

    /**
     * Execute (synchronously) specified step in a domain setup process.
     *
     * @param \App\Domain $domain Domain object
     * @param string      $step   Step identifier (as in self::statusInfo())
     *
     * @return bool True if the execution succeeded, False otherwise
     */
    public static function execProcessStep(Domain $domain, string $step): bool
    {
        try {
            switch ($step) {
                case 'domain-ldap-ready':
                    // Domain not in LDAP, create it
                    if (!$domain->isLdapReady()) {
                        LDAP::createDomain($domain);
                        $domain->status |= Domain::STATUS_LDAP_READY;
                        $domain->save();
                    }
                    return $domain->isLdapReady();

                case 'domain-verified':
                    // Domain existence not verified
                    $domain->verify();
                    return $domain->isVerified();

                case 'domain-confirmed':
                    // Domain ownership confirmation
                    $domain->confirm();
                    return $domain->isConfirmed();
            }
        } catch (\Exception $e) {
            \Log::error($e);
        }

        return false;
    }
}
