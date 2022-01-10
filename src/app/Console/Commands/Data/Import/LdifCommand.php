<?php

namespace App\Console\Commands\Data\Import;

use App\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LdifCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import:ldif {file} {owner} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from an LDIF file';

    /** @var array Aliases email addresses of the owner */
    protected $aliases = [];

    /** @var array List of imported domains */
    protected $domains = [];

    /** @var ?string LDAP DN of the account owner */
    protected $ownerDN;

    /** @var array Packages information */
    protected $packages = [];

    /** @var ?\App\Wallet A wallet of the account owner */
    protected $wallet;

    /** @var string Temp table name */
    protected static $table = 'tmp_ldif_import';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set("memory_limit", "2048M");

        // (Re-)create temporary table
        Schema::dropIfExists(self::$table);
        Schema::create(
            self::$table,
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->text('dn')->index();
                $table->string('type')->nullable()->index();
                $table->text('data')->nullable();
                $table->text('error')->nullable();
                $table->text('warning')->nullable();
            }
        );

        // Import data from the file to the temp table
        $this->loadFromFile();

        // Check for errors in the data, print them and abort (if not using --force)
        if ($this->printErrors()) {
            return 1;
        }

        // Prepare packages/skus information
        $this->preparePackagesAndSkus();

        // Import the account owner first
        $this->importOwner();

        // Import domains first
        $this->importDomains();

        // Import other objects
        $this->importUsers();
        $this->importSharedFolders();
        $this->importResources();
        $this->importGroups();

        // Print warnings collected in the whole process
        $this->printWarnings();

        // Finally, drop the temp table
        Schema::dropIfExists(self::$table);
    }

    /**
     * Check if a domain exists
     */
    protected function domainExists($domain): bool
    {
        return in_array($domain, $this->domains);
    }

    /**
     * Load data from the LDIF file into the temp table
     */
    protected function loadFromFile(): void
    {
        $file = $this->argument('file');

        $numLines = \App\Utils::countLines($file);

        $bar = $this->createProgressBar($numLines, "Parsing input file");

        $fh = fopen($file, 'r');

        $inserts = [];
        $entry = [];
        $lastAttr = null;

        $insertFunc = function ($limit = 0) use (&$entry, &$inserts) {
            if (!empty($entry)) {
                if ($entry = $this->parseLDAPEntry($entry)) {
                    $inserts[] = $entry;
                }
                $entry = [];
            }

            if (count($inserts) > $limit) {
                DB::table(self::$table)->insert($inserts);
                $inserts = [];
            }
        };

        while (!feof($fh)) {
            $line = rtrim(fgets($fh));

            $bar->advance();

            if (trim($line) === '' || $line[0] === '#') {
                continue;
            }

            if (substr($line, 0, 3) == 'dn:') {
                $insertFunc(20);
                $entry['dn'] = strtolower(substr($line, 4));
                $lastAttr = 'dn';
            } elseif (substr($line, 0, 1) == ' ') {
                if (is_array($entry[$lastAttr])) {
                    $elemNum = count($entry[$lastAttr]) - 1;
                    $entry[$lastAttr][$elemNum] .= ltrim($line);
                } else {
                    $entry[$lastAttr] .= ltrim($line);
                }
            } else {
                list ($attr, $remainder) = explode(':', $line, 2);
                $attr = strtolower($attr);

                if ($remainder[0] === ':') {
                    $remainder = base64_decode(substr($remainder, 2));
                } else {
                    $remainder = ltrim($remainder);
                }

                if (array_key_exists($attr, $entry)) {
                    if (!is_array($entry[$attr])) {
                        $entry[$attr] = [$entry[$attr]];
                    }

                    $entry[$attr][] = $remainder;
                } else {
                    $entry[$attr] = $remainder;
                }

                $lastAttr = $attr;
            }
        }

        $insertFunc();

        $bar->finish();

        $this->info("DONE");
    }

    /**
     * Import domains from the temp table
     */
    protected function importDomains(): void
    {
        $domains = DB::table(self::$table)->where('type', 'domain')->whereNull('error')->get();

        $bar = $this->createProgressBar(count($domains), "Importing domains");

        foreach ($domains as $_domain) {
            $bar->advance();

            $data = json_decode($_domain->data);

            $domain = \App\Domain::withTrashed()->where('namespace', $data->namespace)->first();

            if ($domain) {
                $this->setImportWarning($_domain->id, "Domain already exists");
                continue;
            }

            $domain = \App\Domain::create([
                    'namespace' => $data->namespace,
                    'type' => \App\Domain::TYPE_EXTERNAL,
            ]);

            // Entitlements
            $domain->assignPackageAndWallet($this->packages['domain'], $this->wallet);

            $this->domains[] = $domain->namespace;

            if (!empty($data->aliases)) {
                foreach ($data->aliases as $alias) {
                    $alias = strtolower($alias);
                    $domain = \App\Domain::withTrashed()->where('namespace', $alias)->first();

                    if ($domain) {
                        $this->setImportWarning($_domain->id, "Domain already exists");
                        continue;
                    }

                    $domain = \App\Domain::create([
                            'namespace' => $alias,
                            'type' => \App\Domain::TYPE_EXTERNAL,
                    ]);

                    // Entitlements
                    $domain->assignPackageAndWallet($this->packages['domain'], $this->wallet);

                    $this->domains[] = $domain->namespace;
                }
            }
        }

        $bar->finish();

        $this->info("DONE");
    }

    /**
     * Import groups from the temp table
     */
    protected function importGroups(): void
    {
        $groups = DB::table(self::$table)->where('type', 'group')->whereNull('error')->get();

        $bar = $this->createProgressBar(count($groups), "Importing groups");

        foreach ($groups as $_group) {
            $bar->advance();

            $data = json_decode($_group->data);

            // Collect group member email addresses
            $members = $this->resolveUserDNs($data->members);

            if (empty($members)) {
                $this->setImportWarning($_group->id, "Members resolve to an empty array");
                continue;
            }

            $group = \App\Group::withTrashed()->where('email', $data->email)->first();

            if ($group) {
                $this->setImportWarning($_group->id, "Group already exists");
                continue;
            }

            // Make sure the domain exists
            if (!$this->domainExists($data->domain)) {
                $this->setImportWarning($_group->id, "Domain not found");
                continue;
            }

            $group = \App\Group::create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'members' => $members,
            ]);

            $group->assignToWallet($this->wallet);

            // Sender policy
            if (!empty($data->sender_policy)) {
                $group->setSetting('sender_policy', json_encode($data->sender_policy));
            }
        }

        $bar->finish();

        $this->info("DONE");
    }

    /**
     * Import resources from the temp table
     */
    protected function importResources(): void
    {
        $resources = DB::table(self::$table)->where('type', 'resource')->whereNull('error')->get();

        $bar = $this->createProgressBar(count($resources), "Importing resources");

        foreach ($resources as $_resource) {
            $bar->advance();

            $data = json_decode($_resource->data);

            $resource = \App\Resource::withTrashed()
                ->where('name', $data->name)
                ->where('email', 'like', '%@' . $data->domain)
                ->first();

            if ($resource) {
                $this->setImportWarning($_resource->id, "Resource already exists");
                continue;
            }

            // Resource invitation policy
            if (!empty($data->invitation_policy) && $data->invitation_policy == 'manual') {
                $members = empty($data->owner) ? [] : $this->resolveUserDNs([$data->owner]);

                if (empty($members)) {
                    $this->setImportWarning($_resource->id, "Failed to resolve the resource owner");
                    $data->invitation_policy = null;
                } else {
                    $data->invitation_policy = 'manual:' . $members[0];
                }
            }

            // Make sure the domain exists
            if (!$this->domainExists($data->domain)) {
                $this->setImportWarning($_resource->id, "Domain not found");
                continue;
            }

            $resource = new \App\Resource();
            $resource->name = $data->name;
            $resource->domain = $data->domain;
            $resource->save();

            $resource->assignToWallet($this->wallet);

            // Invitation policy
            if (!empty($data->invitation_policy)) {
                $resource->setSetting('invitation_policy', $data->invitation_policy);
            }

            // Target folder
            if (!empty($data->folder)) {
                $resource->setSetting('folder', $data->folder);
            }
        }

        $bar->finish();

        $this->info("DONE");
    }

    /**
     * Import shared folders from the temp table
     */
    protected function importSharedFolders(): void
    {
        $folders = DB::table(self::$table)->where('type', 'sharedFolder')->whereNull('error')->get();

        $bar = $this->createProgressBar(count($folders), "Importing shared folders");

        foreach ($folders as $_folder) {
            $bar->advance();

            $data = json_decode($_folder->data);

            $folder = \App\SharedFolder::withTrashed()
                ->where('name', $data->name)
                ->where('email', 'like', '%@' . $data->domain)
                ->first();

            if ($folder) {
                $this->setImportWarning($_folder->id, "Folder already exists");
                continue;
            }

            // Make sure the domain exists
            if (!$this->domainExists($data->domain)) {
                $this->setImportWarning($_folder->id, "Domain not found");
                continue;
            }

            $folder = new \App\SharedFolder();
            $folder->name = $data->name;
            $folder->type = $data->type ?? 'mail';
            $folder->domain = $data->domain;
            $folder->save();

            $folder->assignToWallet($this->wallet);

            // Invitation policy
            if (!empty($data->acl)) {
                $folder->setSetting('acl', json_encode($data->acl));
            }

            // Target folder
            if (!empty($data->folder)) {
                $folder->setSetting('folder', $data->folder);
            }
        }

        $bar->finish();

        $this->info("DONE");
    }

    /**
     * Import users from the temp table
     */
    protected function importUsers(): void
    {
        $users = DB::table(self::$table)->where('type', 'user')->whereNull('error');

        // Skip the (already imported) account owner
        if ($this->ownerDN) {
            $users->whereNotIn('dn', [$this->ownerDN]);
        }

        // Import aliases of the owner, we got from importOwner() call
        if (!empty($this->aliases) && $this->wallet) {
            $this->setUserAliases($this->wallet->owner, $this->aliases);
        }

        $bar = $this->createProgressBar($users->count(), "Importing users");

        foreach ($users->cursor() as $_user) {
            $bar->advance();

            $this->importSingleUser($_user);
        }

        $bar->finish();

        $this->info("DONE");
    }

    /**
     * Import the account owner (or find it among the existing accounts)
     */
    protected function importOwner(): void
    {
        // The owner email not found in the import data, try existing users
        $user = $this->getUser($this->argument('owner'));

        if (!$user && $this->ownerDN) {
            // The owner email found in the import data
            $bar = $this->createProgressBar(1, "Importing account owner");

            $user = DB::table(self::$table)->where('dn', $this->ownerDN)->first();
            $user = $this->importSingleUser($user);

            // TODO: We should probably make sure the user's domain is to be imported too
            //       and/or create it automatically.

            $bar->advance();
            $bar->finish();

            $this->info("DONE");
        }

        if (!$user) {
            $this->error("Unable to find the specified account owner");
            exit(1);
        }

        $this->wallet = $user->wallets->first();
    }

    /**
     * A helper that imports a single user record
     */
    protected function importSingleUser($ldap_user)
    {
        $data = json_decode($ldap_user->data);

        $user = \App\User::withTrashed()->where('email', $data->email)->first();

        if ($user) {
            $this->setImportWarning($ldap_user->id, "User already exists");
            return;
        }

        // Make sure the domain exists
        if ($this->wallet && !$this->domainExists($data->domain)) {
            $this->setImportWarning($ldap_user->id, "Domain not found");
            return;
        }

        $user = \App\User::create(['email' => $data->email]);

        // Entitlements
        $user->assignPackageAndWallet($this->packages['user'], $this->wallet ?: $user->wallets()->first());

        if (!empty($data->quota)) {
            $quota = ceil($data->quota / 1024 / 1024) - $this->packages['quota'];
            if ($quota > 0) {
                $user->assignSku($this->packages['storage'], $quota);
            }
        }

        // User settings
        if (!empty($data->settings)) {
            $settings = [];
            foreach ($data->settings as $key => $value) {
                $settings[] = [
                    'user_id' => $user->id,
                    'key' => $key,
                    'value' => $value,
                ];
            }

            DB::table('user_settings')->insert($settings);
        }

        // Update password
        if ($data->password != $user->password_ldap) {
            \App\User::where('id', $user->id)->update(['password_ldap' => $data->password]);
        }

        // Import aliases
        if (!empty($data->aliases)) {
            if (!$this->wallet) {
                // This is the account owner creation, at this point we likely do not have
                // domain records yet, save the aliases to be inserted later (in importUsers())
                $this->aliases = $data->aliases;
            } else {
                $this->setUserAliases($user, $data->aliases);
            }
        }

        return $user;
    }

    /**
     * Convert LDAP entry into an object supported by the migration tool
     *
     * @param array $entry LDAP entry attributes
     *
     * @return array Record data for inserting to the temp table
     */
    protected function parseLDAPEntry(array $entry): array
    {
        $type = null;
        $data = null;
        $error = null;

        $ouTypeMap = [
            'Shared Folders' => 'sharedfolder',
            'Resources' => 'resource',
            'Groups' => 'group',
            'People' => 'user',
            'Domains' => 'domain',
        ];

        foreach ($ouTypeMap as $ou => $_type) {
            if (stripos($entry['dn'], ",ou={$ou}")) {
                $type = $_type;
                break;
            }
        }

        if (!$type) {
            $error = "Unknown record type";
        }

        if (empty($error)) {
            $method = 'parseLDAP' . ucfirst($type);
            list($data, $error) = $this->{$method}($entry);

            if (empty($data['domain']) && !empty($data['email'])) {
                $data['domain'] = explode('@', $data['email'])[1];
            }
        }

        return [
            'dn' => $entry['dn'],
            'type' => $type,
            'data' => json_encode($data),
            'error' => $error,
        ];
    }

    /**
     * Convert LDAP domain data into Kolab4 "format"
     */
    protected function parseLDAPDomain($entry)
    {
        $error = null;
        $result = [];

        if (empty($entry['associateddomain'])) {
            $error = "Missing 'associatedDomain' attribute";
        } elseif (!empty($entry['inetdomainstatus']) && $entry['inetdomainstatus'] == 'deleted') {
            $error = "Domain deleted";
        } else {
            $result['namespace'] = strtolower($this->attrStringValue($entry, 'associateddomain'));

            if (is_array($entry['associateddomain']) && count($entry['associateddomain']) > 1) {
                $result['aliases'] = array_slice($entry['associateddomain'], 1);
            }

            // TODO: inetdomainstatus = suspended ???
        }

        return [$result, $error];
    }

    /**
     * Convert LDAP group data into Kolab4 "format"
     */
    protected function parseLDAPGroup($entry)
    {
        $error = null;
        $result = [];

        if (empty($entry['cn'])) {
            $error = "Missing 'cn' attribute";
        } elseif (empty($entry['mail'])) {
            $error = "Missing 'mail' attribute";
        } elseif (empty($entry['uniquemember'])) {
            $error = "Missing 'uniqueMember' attribute";
        } else {
            $result['name'] = $this->attrStringValue($entry, 'cn');
            $result['email'] = strtolower($this->attrStringValue($entry, 'mail'));
            $result['members'] = $this->attrArrayValue($entry, 'uniquemember');

            if (!empty($entry['kolaballowsmtpsender'])) {
                $policy = $this->attrArrayValue($entry, 'kolaballowsmtpsender');
                $result['sender_policy'] = $this->parseSenderPolicy($policy);
            }
        }

        return [$result, $error];
    }

    /**
     * Convert LDAP resource data into Kolab4 "format"
     */
    protected function parseLDAPResource($entry)
    {
        $error = null;
        $result = [];

        if (empty($entry['cn'])) {
            $error = "Missing 'cn' attribute";
        } elseif (empty($entry['mail'])) {
            $error = "Missing 'mail' attribute";
        } else {
            $result['name'] = $this->attrStringValue($entry, 'cn');
            $result['email'] = strtolower($this->attrStringValue($entry, 'mail'));

            if (!empty($entry['kolabtargetfolder'])) {
                $result['folder'] = $this->attrStringValue($entry, 'kolabtargetfolder');
            }

            if (!empty($entry['owner'])) {
                $result['owner'] = $this->attrStringValue($entry, 'owner');
            }

            if (!empty($entry['kolabinvitationpolicy'])) {
                $policy = $this->attrArrayValue($entry, 'kolabinvitationpolicy');
                $result['invitation_policy'] = $this->parseInvitationPolicy($policy);
            }
        }

        return [$result, $error];
    }

    /**
     * Convert LDAP shared folder data into Kolab4 "format"
     */
    protected function parseLDAPSharedFolder($entry)
    {
        $error = null;
        $result = [];

        if (empty($entry['cn'])) {
            $error = "Missing 'cn' attribute";
        } elseif (empty($entry['mail'])) {
            $error = "Missing 'mail' attribute";
        } else {
            $result['name'] = $this->attrStringValue($entry, 'cn');
            $result['email'] = strtolower($this->attrStringValue($entry, 'mail'));

            if (!empty($entry['kolabfoldertype'])) {
                $result['type'] = $this->attrStringValue($entry, 'kolabfoldertype');
            }

            if (!empty($entry['kolabtargetfolder'])) {
                $result['folder'] = $this->attrStringValue($entry, 'kolabtargetfolder');
            }

            if (!empty($entry['acl'])) {
                $result['acl'] = $this->parseACL($this->attrArrayValue($entry, 'acl'));
            }
        }

        return [$result, $error];
    }

    /**
     * Convert LDAP user data into Kolab4 "format"
     */
    protected function parseLDAPUser($entry)
    {
        $error = null;
        $result = [];

        $settingAttrs = [
            'givenname' => 'first_name',
            'sn' => 'last_name',
            'telephonenumber' => 'phone',
            'mailalternateaddress' => 'external_email',
            'mobile' => 'phone',
            'o' => 'organization',
            // 'address' => 'billing_address'
        ];

        if (empty($entry['mail'])) {
            $error = "Missing 'mail' attribute";
        } else {
            $result['email'] = strtolower($this->attrStringValue($entry, 'mail'));
            $result['settings'] = [];
            $result['aliases'] = [];

            foreach ($settingAttrs as $attr => $setting) {
                if (!empty($entry[$attr])) {
                    $result['settings'][$setting] = $this->attrStringValue($entry, $attr);
                }
            }

            if (!empty($entry['alias'])) {
                $result['aliases'] = $this->attrArrayValue($entry, 'alias');
            }

            if (!empty($entry['userpassword'])) {
                $result['password'] = $this->attrStringValue($entry, 'userpassword');
            }

            if (!empty($entry['mailquota'])) {
                $result['quota'] = $this->attrStringValue($entry, 'mailquota');
            }

            if ($result['email'] == $this->argument('owner')) {
                $this->ownerDN = $entry['dn'];
            }
        }

        return [$result, $error];
    }

    /**
     * Print import errors
     */
    protected function printErrors(): bool
    {
        if ($this->option('force')) {
            return false;
        }

        $errors = DB::table(self::$table)->whereNotNull('error')->orderBy('id')
            ->get()
            ->map(function ($record) {
                $this->error("ERROR {$record->dn}: {$record->error}");
                return $record->id;
            })
            ->all();

        return !empty($errors);
    }

    /**
     * Print import warnings (for records that do not have an error specified)
     */
    protected function printWarnings(): void
    {
        DB::table(self::$table)->whereNotNull('warning')->whereNull('error')->orderBy('id')
            ->each(function ($record) {
                $this->warn("WARNING {$record->dn}: {$record->warning}");
                return $record->id;
            });
    }

    /**
     * Convert ldap attribute value to an array
     */
    protected static function attrArrayValue($entry, $attribute)
    {
        return is_array($entry[$attribute]) ? $entry[$attribute] : [$entry[$attribute]];
    }

    /**
     * Convert ldap attribute to a string
     */
    protected static function attrStringValue($entry, $attribute)
    {
        return is_array($entry[$attribute]) ? $entry[$attribute][0] : $entry[$attribute];
    }

    /**
     * Resolve a list of user DNs into email addresses. Makes sure
     * the returned addresses exist in Kolab4 database.
     */
    protected function resolveUserDNs($user_dns): array
    {
        // Get email addresses from the import data
        $users = DB::table(self::$table)->whereIn('dn', $user_dns)
            ->where('type', 'user')
            ->whereNull('error')
            ->get()
            ->map(function ($user) {
                $mdata = json_decode($user->data);
                return $mdata->email;
            })
            // Make sure to skip these with unknown domains
            ->filter(function ($email) {
                return $this->domainExists(explode('@', $email)[1]);
            })
            ->all();

        // Get email addresses for existing Kolab4 users
        if (!empty($users)) {
            $users = \App\User::whereIn('email', $users)->get()->pluck('email')->all();
        }

        return $users;
    }

    /**
     * Validate/convert acl to Kolab4 format
     */
    protected static function parseACL(array $acl): array
    {
        $map = [
            'lrswipkxtecdn' => 'full',
            'lrs' => 'read-only',
            'read' => 'read-only',
            'lrswitedn' => 'read-write',
        ];

        $supportedRights = ['full', 'read-only', 'read-write'];

        foreach ($acl as $idx => $entry) {
            $parts = explode(',', $entry);
            $entry = null;

            if (count($parts) == 2) {
                $label = trim($parts[0]);
                $rights = trim($parts[1]);
                $rights = $map[$rights] ?? $rights;

                if (in_array($rights, $supportedRights) && ($label === 'anyone' || strpos($label, '@'))) {
                    $entry = "{$label}, {$rights}";
                }

                // TODO: Throw an error or log a warning on unsupported acl entry?
            }

            $acl[$idx] = $entry;
        }

        return array_values(array_filter($acl));
    }

    /**
     * Validate/convert invitation policy to Kolab4 format
     */
    protected static function parseInvitationPolicy(array $policies): ?string
    {
        foreach ($policies as $policy) {
            if ($policy == 'ACT_MANUAL') {
                // 'owner' attribute handling in another place
                return 'manual';
            }

            if ($policy == 'ACT_ACCEPT_AND_NOTIFY') {
                break; // use the default 'accept' (null) policy
            }

            if ($policy == 'ACT_REJECT') {
                return 'reject';
            }
        }

        return null;
    }

    /**
     * Validate/convert sender policy to Kolab4 format
     */
    protected static function parseSenderPolicy(array $rules): array
    {
        foreach ($rules as $idx => $rule) {
            $entry = trim($rule);
            $rule = null;

            // 'deny' rules aren't supported
            if (isset($entry[0]) && $entry[0] !== '-') {
                $rule = $entry;
            }

            $rules[$idx] = $rule;
        }

        $rules = array_values(array_filter($rules));

        if (!empty($rules) && $rules[count($rules) - 1] != '-') {
            $rules[] = '-';
        }

        return $rules;
    }

    /**
     * Get/prepare packages/skus information
     */
    protected function preparePackagesAndSkus(): void
    {
        // Find the tenant
        if (empty($this->ownerDN)) {
            if ($user = $this->getUser($this->argument('owner'))) {
                $tenant_id = $user->tenant_id;
            }
        }

        // TODO: Tenant id could be a command option

        if (empty($tenant_id)) {
            $tenant_id = \config('app.tenant_id');
        }

        // TODO: We should probably make package titles configurable with command options

        $this->packages = [
            'user' => \App\Package::where('title', 'kolab')->where('tenant_id', $tenant_id)->first(),
            'domain' => \App\Package::where('title', 'domain-hosting')->where('tenant_id', $tenant_id)->first(),
        ];

        // Count storage skus
        $sku = $this->packages['user']->skus()->where('title', 'storage')->first();

        $this->packages['quota'] = $sku ? $sku->pivot->qty : 0;
        $this->packages['storage'] = \App\Sku::where('title', 'storage')->where('tenant_id', $tenant_id)->first();
    }

    /**
     * Set aliases for the user
     */
    protected function setUserAliases(\App\User $user, array $aliases = [])
    {
        if (!empty($aliases)) {
            // Some users might have alias entry with their main address, remove it
            $aliases = array_map('strtolower', $aliases);
            $aliases = array_diff(array_unique($aliases), [$user->email]);

            // Remove aliases for domains that do not exist
            if (!empty($aliases)) {
                $aliases = array_filter(
                    $aliases,
                    function ($alias) {
                        return $this->domainExists(explode('@', $alias)[1]);
                    }
                );
            }

            if (!empty($aliases)) {
                $user->setAliases($aliases);
            }
        }
    }

    /**
     * Set error message for specified import data record
     */
    protected static function setImportError($id, $error): void
    {
        DB::table(self::$table)->where('id', $id)->update(['error' => $error]);
    }

    /**
     * Set warning message for specified import data record
     */
    protected static function setImportWarning($id, $warning): void
    {
        DB::table(self::$table)->where('id', $id)->update(['warning' => $warning]);
    }
}
