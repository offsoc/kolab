<?php

namespace App\Http\Controllers\API\V4;

use App\Domain;
use App\Http\Controllers\RelationController;
use App\Rules\UserEmailDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DomainsController extends RelationController
{
    /** @var string Resource localization label */
    protected $label = 'domain';

    /** @var string Resource model name */
    protected $model = Domain::class;

    /** @var array Common object properties in the API response */
    protected $objectProps = ['namespace', 'type'];

    /** @var array Resource listing order (column names) */
    protected $order = ['namespace'];

    /** @var array Resource relation method arguments */
    protected $relationArgs = [true, false];


    /**
     * Confirm ownership of the specified domain (via DNS check).
     *
     * @param int $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function confirm($id)
    {
        $domain = Domain::find($id);

        if (!$this->checkTenant($domain)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($domain)) {
            return $this->errorResponse(403);
        }

        if (!$domain->confirm()) {
            return response()->json([
                    'status' => 'error',
                    'message' => self::trans('app.domain-verify-error'),
            ]);
        }

        return response()->json([
                'status' => 'success',
                'statusInfo' => self::statusInfo($domain),
                'message' => self::trans('app.domain-verify-success'),
        ]);
    }

    /**
     * Remove the specified domain.
     *
     * @param string $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $domain = Domain::withEnvTenantContext()->find($id);

        if (empty($domain)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canDelete($domain)) {
            return $this->errorResponse(403);
        }

        // It is possible to delete domain only if there are no users/aliases/groups using it.
        if (!$domain->isEmpty()) {
            $response = ['status' => 'error', 'message' => self::trans('app.domain-notempty-error')];
            return response()->json($response, 422);
        }

        $domain->delete();

        return response()->json([
                'status' => 'success',
                'message' => self::trans('app.domain-delete-success'),
        ]);
    }

    /**
     * Create a domain.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $current_user = $this->guard()->user();
        $owner = $current_user->wallet()->owner;

        if ($owner->id != $current_user->id) {
            return $this->errorResponse(403);
        }

        // Validate the input
        $v = Validator::make(
            $request->all(),
            [
                'namespace' => ['required', 'string', new UserEmailDomain()]
            ]
        );

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $namespace = \strtolower(request()->input('namespace'));

        // Domain already exists
        if ($domain = Domain::withTrashed()->where('namespace', $namespace)->first()) {
            // Check if the domain is soft-deleted and belongs to the same user
            $deleteBeforeCreate = $domain->trashed() && ($wallet = $domain->wallet())
                && $wallet->owner && $wallet->owner->id == $owner->id;

            if (!$deleteBeforeCreate) {
                $errors = ['namespace' => self::trans('validation.domainnotavailable')];
                return response()->json(['status' => 'error', 'errors' => $errors], 422);
            }
        }

        if (empty($request->package) || !($package = \App\Package::withEnvTenantContext()->find($request->package))) {
            $errors = ['package' => self::trans('validation.packagerequired')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        if (!$package->isDomain()) {
            $errors = ['package' => self::trans('validation.packageinvalid')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        DB::beginTransaction();

        // Force-delete the existing domain if it is soft-deleted and belongs to the same user
        if (!empty($deleteBeforeCreate)) {
            $domain->forceDelete();
        }

        // Create the domain
        $domain = Domain::create([
                'namespace' => $namespace,
                'type' => \App\Domain::TYPE_EXTERNAL,
        ]);

        $domain->assignPackage($package, $owner);

        DB::commit();

        return response()->json([
                'status' => 'success',
                'message' => self::trans('app.domain-create-success'),
        ]);
    }

    /**
     * Get the information about the specified domain.
     *
     * @param string $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function show($id)
    {
        $domain = Domain::find($id);

        if (!$this->checkTenant($domain)) {
            return $this->errorResponse(404);
        }

        if (!$this->guard()->user()->canRead($domain)) {
            return $this->errorResponse(403);
        }

        $response = $this->objectToClient($domain, true);

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

        // Entitlements/Wallet info
        SkusController::objectEntitlements($domain, $response);

        return response()->json($response);
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
     * Domain status (extended) information.
     *
     * @param \App\Domain $domain Domain object
     *
     * @return array Status information
     */
    public static function statusInfo($domain): array
    {
        // If that is not a public domain, add domain specific steps
        return self::processStateInfo(
            $domain,
            [
                'domain-new' => true,
                'domain-ldap-ready' => $domain->isLdapReady(),
                'domain-verified' => $domain->isVerified(),
                'domain-confirmed' => [$domain->isConfirmed(), "/domain/{$domain->id}"],
            ]
        );
    }

    /**
     * Execute (synchronously) specified step in a domain setup process.
     *
     * @param \App\Domain $domain Domain object
     * @param string      $step   Step identifier (as in self::statusInfo())
     *
     * @return bool|null True if the execution succeeded, False if not, Null when
     *                   the job has been sent to the worker (result unknown)
     */
    public static function execProcessStep(Domain $domain, string $step): ?bool
    {
        try {
            switch ($step) {
                case 'domain-ldap-ready':
                    // Use worker to do the job
                    \App\Jobs\Domain\CreateJob::dispatch($domain->id);
                    return null;

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
