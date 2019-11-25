<?php

namespace App\Jobs;

use App\DataMigrator\EWS;

class DataMigratorEWSFolder extends DataMigratorEWSItem
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
