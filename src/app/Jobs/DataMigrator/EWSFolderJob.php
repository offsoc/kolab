<?php

namespace App\Jobs\DataMigrator;

use App\DataMigrator\EWS;

class EWSFolderJob extends EWSItemJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $ews = new EWS;
        $ews->processFolder($this->data);
    }
}
