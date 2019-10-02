<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    const STATUS_NEW        = 0 << 1;
    const STATUS_ACTIVE     = 0 << 2;
    const STATUS_CONFIRMED  = 0 << 3;
    const STATUS_SUSPENDED  = 0 << 4;
    const STATUS_DELETED    = 0 << 5;

    protected $fillable = [
        'namespace'
    ];

    protected $guarded = [
        "status"
    ];

    public function setStatusAttribute($status)
    {
        $_status = 0;

        switch ($status) {
            case "new":
                $_status &= self::STATUS_NEW;
                break;
            case "active":
                $_status &= self::STATUS_ACTIVE;
                break;
            case "confirmed":
                $_status &= self::STATUS_CONFIRMED;
                break;
            case "suspended":
                $_status &= self::STATUS_SUSPENDED;
                break;
            case "deleted":
                $_status &= self::STATUS_DELETED;
                break;
            default:
                throw new \Exception("Invalid domain status: {$status}");
                break;
        }
    }
}
