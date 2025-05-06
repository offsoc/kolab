<?php

namespace App\Console\Commands\SharedFolder;

use App\Console\Command;
use App\Rules\SharedFolderName;
use App\Rules\SharedFolderType;
use App\SharedFolder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Create a shared folder.
 *
 * @see \App\Console\Commands\Scalpel\SharedFolder\CreateCommand
 */
class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sharedfolder:create {domain} {name} {--type=} {--acl=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a shared folder.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domainName = $this->argument('domain');
        $name = $this->argument('name');
        $type = $this->option('type');
        $acl = $this->option('acl');

        if (empty($type)) {
            $type = 'mail';
        }

        $domain = $this->getDomain($domainName);

        if (!$domain) {
            $this->error("No such domain {$domainName}.");
            return 1;
        }

        if ($domain->isPublic()) {
            $this->error("Domain {$domainName} is public.");
            return 1;
        }

        $owner = $domain->wallet()->owner;

        // Validate folder name and type
        $rules = [
            'name' => ['required', 'string', new SharedFolderName($owner, $domain->namespace)],
            'type' => ['required', 'string', new SharedFolderType()],
        ];

        $v = Validator::make(['name' => $name, 'type' => $type], $rules);

        if ($v->fails()) {
            $this->error($v->errors()->all()[0]);
            return 1;
        }

        DB::beginTransaction();

        // Create the shared folder
        $folder = new SharedFolder();
        $folder->name = $name;
        $folder->type = $type;
        $folder->domainName = $domainName;
        $folder->tenant_id = $domain->tenant_id;
        $folder->save();

        $folder->assignToWallet($owner->wallets->first());

        if (!empty($acl)) {
            $errors = $folder->setConfig(['acl' => $acl]);

            if (!empty($errors)) {
                $this->error("Invalid --acl entry.");
                DB::rollBack();
                return 1;
            }
        }

        DB::commit();

        $this->info((string) $folder->id);
    }
}
