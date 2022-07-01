<?php

namespace App\Console;

/**
 * This abstract class provides a means to treat objects in our model using CRUD.
 */
abstract class ObjectCreateCommand extends ObjectCommand
{
    public function __construct()
    {
        $this->description = "Create a {$this->objectName}";
        $this->signature = sprintf(
            "%s%s:create",
            $this->commandPrefix ? $this->commandPrefix . ":" : "",
            $this->objectName
        );

        $class = new $this->objectClass();

        foreach ($class->getFillable() as $fillable) {
            $this->signature .= " {--{$fillable}=}";
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

        foreach ($class->getFillable() as $fillable) {
            $this->properties[$fillable] = $this->option($fillable);
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
        $this->getProperties();

        $class = new $this->objectClass();

        $object = $this->objectClass::create($this->properties);

        if ($object) {
            $this->info($object->{$class->getKeyName()});
        } else {
            $this->error("Object could not be created.");
        }
    }
}
