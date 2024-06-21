<?php

namespace App\Traits;

trait UuidIntKeyTrait
{
    /**
     * Boot function from Laravel.
     */
    protected static function bootUuidIntKeyTrait()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $allegedly_unique = \App\Utils::uuidInt();

                // Verify if unique
                if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model))) {
                    while ($model->withTrashed()->find($allegedly_unique)) {
                        $allegedly_unique = \App\Utils::uuidInt();
                    }
                } else {
                    while ($model->find($allegedly_unique)) {
                        $allegedly_unique = \App\Utils::uuidInt();
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
