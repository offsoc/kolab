<?php

namespace App\Traits;

use App\Utils;

trait UuidIntKeyTrait
{
    /**
     * Boot function from Laravel.
     */
    protected static function bootUuidIntKeyTrait()
    {
        static::creating(static function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $allegedly_unique = Utils::uuidInt();

                // Verify if unique
                if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model))) {
                    while ($model->withTrashed()->find($allegedly_unique)) {
                        $allegedly_unique = Utils::uuidInt();
                    }
                } else {
                    while ($model->find($allegedly_unique)) {
                        $allegedly_unique = Utils::uuidInt();
                    }
                }

                $model->{$model->getKeyName()} = $allegedly_unique;
            }
        });
    }

    /**
     * Get if the key is incrementing.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }
}
