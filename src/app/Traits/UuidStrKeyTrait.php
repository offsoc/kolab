<?php

namespace App\Traits;

trait UuidStrKeyTrait
{
    /**
     * Boot function from Laravel.
     */
    protected static function bootUuidStrKeyTrait()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $allegedly_unique = \App\Utils::uuidStr();

                // Verify if unique
                if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model))) {
                    while ($model->withTrashed()->find($allegedly_unique)) {
                        $allegedly_unique = \App\Utils::uuidStr();
                    }
                } else {
                    while ($model->find($allegedly_unique)) {
                        $allegedly_unique = \App\Utils::uuidStr();
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

    /**
     * Get the key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }
}
