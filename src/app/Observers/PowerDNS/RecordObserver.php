<?php

namespace App\Observers\PowerDNS;

class RecordObserver
{
    public function created($record)
    {
        if ($record->{'type'} == "SOA") {
            return;
        }

        $record->domain->bumpSerial();
    }

    public function deleted($record)
    {
        if ($record->{'type'} == "SOA") {
            return;
        }

        $record->domain->bumpSerial();
    }

    public function updated($record)
    {
        if ($record->{'type'} == "SOA") {
            return;
        }

        $record->domain->bumpSerial();
    }
}
