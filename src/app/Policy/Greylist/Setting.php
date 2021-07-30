<?php

namespace App\Policy\Greylist;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'greylist_settings';

    protected $fillable = [
        'object_id',
        'object_type',
        'key',
        'value'
    ];
}
