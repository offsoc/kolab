<?php

namespace App\Console;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

/**
 * This abstract class provides a means to treat objects in our model using CRUD.
 */
abstract class ObjectUpdateCommand extends ObjectCommand
{
    public function __construct()
    {
        $this->description = "Update a {$this->objectName}";
        $this->signature = sprintf(
            "%s%s:update {%s}",
            $this->commandPrefix ? $this->commandPrefix . ":" : "",
            $this->objectName,
            $this->objectName
        );

        $class = new $this->objectClass();

        try {
            foreach (Schema::getColumnListing($class->getTable()) as $column) {
                if ($column == "id") {
                    continue;
                }

                $this->signature .= " {--{$column}=}";
            }
        } catch (\Exception $e) {
            \Log::error("Could not extract options: {$e->getMessage()}");
        }

        $classes = class_uses_recursive($this->objectClass);

        if (in_array(SoftDeletes::class, $classes)) {
            $this->signature .= " {--with-deleted : Include deleted {$this->objectName}s}";
        }

        parent::__construct();
    }

    public function getProperties()
    {
        if (!empty($this->properties)) {
            return $this->properties;
        }

        $class = new $this->objectClass();

        $this->properties = [];

        foreach (Schema::getColumnListing($class->getTable()) as $column) {
            if ($column == "id") {
                continue;
            }

            if (($value = $this->option($column)) !== null) {
                $this->properties[$column] = $value;
            }
        }

        return $this->properties;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $argument = $this->argument($this->objectName);

        $object = $this->getObject($this->objectClass, $argument, $this->objectTitle);

        if (!$object) {
            $this->error("No such {$this->objectName} {$argument}");
            return 1;
        }

        foreach ($this->getProperties() as $property => $value) {
            if ($property == "deleted_at" && $value == "null") {
                $value = null;
            }

            $object->{$property} = $value;
        }

        $object->timestamps = false;

        $object->save(['timestamps' => false]);

        $this->cacheRefresh($object);
    }
}
