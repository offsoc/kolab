<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Quota extends Model
{
    public function entitlement()
    {
        return $this->morphOne('App\Entitlement', 'entitleable');
    }
}
