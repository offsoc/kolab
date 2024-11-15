<?php

namespace App\Console\Commands\Data;

use App\DataMigrator;
use Illuminate\Console\Command;

/**
 * Migrate user data from an external service to Kolab.
 *
 * Example usage:
 *
 * ```
 * php artisan data:migrate \
 *   "ews://$user@$server?client_id=$client_id&client_secret=$client_secret&tenant_id=$tenant_id" \
 *   "dav://$dest_user:$dest_pass@$dest_server"
 * ```
 *
 * Supported account types: ews, dav (davs), imap (tls, ssl, imaps)
 */
class MigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:migrate
                                {src : Source account}
                                {dst : Destination account}
                                {--type= : Object type(s)}
                                {--sync : Execute migration synchronously}
                                {--force : Force existing queue removal}
                                {--dry : Dry run}
                                {--folder-filter=* : Exact folder name match before mapping}
                                {--folder-mapping=* : Folder mapping in the form "source:target"}';
//                                {--export-only : Only export data}
//                                {--import-only : Only import previously exported data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate user data from an external service';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $src = new DataMigrator\Account($this->argument('src'));
        $dst = new DataMigrator\Account($this->argument('dst'));

        $folderMapping = [];
        foreach ($this->option('folder-mapping') as $mapping) {
            $arr = explode(":", $mapping);
            $folderMapping[$arr[0]] = $arr[1];
        }

        $options = [
            'type' => $this->option('type'),
            'force' => $this->option('force'),
            'dry' => $this->option('dry'),
            'sync' => $this->option('sync'),
            'folderMapping' => $folderMapping,
            'folderFilter' => $this->option('folder-filter'),
            'stdout' => true,
        ];

        $migrator = new DataMigrator\Engine();
        $migrator->migrate($src, $dst, $options);
    }
}
