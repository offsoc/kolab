<?php

namespace App\Console;

/**
 * This abstract class provides a means to treat objects in our model using CRUD.
 */
abstract class ObjectCreateCommand extends ObjectCommand
{
    /** @var ?array Object properties */
    protected $properties;

    public function __construct()
    {
        $this->description = "Create a {$this->objectName}";
        $this->signature = sprintf(
            "%s%s:create",
            $this->commandPrefix ? $this->commandPrefix . ":" : "",
            $this->objectName
        );

        foreach ($this->getClassProperties() as $fillable) {
            $this->signature .= " {--{$fillable}=}";
        }

        parent::__construct();
    }

    /**
     * Return list of fillable properties for the specified object type
     */
    protected function getClassProperties(): array
    {
        $class = new $this->objectClass();

        $properties = $class->getFillable();

        if ($this->commandPrefix == 'scalpel'
            && in_array(\App\Traits\BelongsToTenantTrait::class, class_uses($this->objectClass))
        ) {
            $properties[] = 'tenant_id';
        }

        return $properties;
    }

    /**
     * Return object properties from the input
     */
    protected function getProperties(): array
    {
        if (is_array($this->properties)) {
            return $this->properties;
        }

        $this->properties = [];

        foreach ($this->getClassProperties() as $fillable) {
            $this->properties[$fillable] = $this->option($fillable);
        }

        return $this->properties;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $object = new $this->objectClass();

        try {
            foreach ($this->getProperties() as $name => $value) {
                $object->{$name} = $value;
            }

            $object->save();

            $this->info($object->{$object->getKeyName()});
        } catch (\Exception $e) {
            $this->error("Object could not be created.");
            return 1;
        }
    }
}
