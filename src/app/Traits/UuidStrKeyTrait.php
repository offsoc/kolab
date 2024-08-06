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
                $finder = $model;
                if (\App\Utils::isSoftDeletable($model)) {
                    $finder = $finder->withTrashed();
                }

                while ($finder->find($allegedly_unique)) {
                    $allegedly_unique = \App\Utils::uuidStr();
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
