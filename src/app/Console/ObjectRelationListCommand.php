<?php

namespace App\Console;

/**
 * This abstract class provides a means to treat objects in our model using CRUD, with the exception that
 * this particular abstract class lists objects' relations.
 */
abstract class ObjectRelationListCommand extends ObjectCommand
{
    /**
     * The "relation" -- a method or property.
     *
     * @var string
     */
    protected $objectRelation;

    /**
     * Supplement the base command constructor with a derived or generated signature and
     * description.
     *
     * @return mixed
     */
    public function __construct()
    {
        $this->description = "List {$this->objectRelation} for a {$this->objectName}";

        $this->signature = sprintf(
            "%s%s:%s {%s}",
            $this->commandPrefix ? $this->commandPrefix . ":" : "",
            $this->objectName,
            $this->objectRelation,
            $this->objectName
        );

        $this->signature .= " {--attr=* : Attributes other than the primary unique key to include}";

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $argument = $this->argument($this->objectName);

        $object = $this->getObject(
            $this->objectClass,
            $argument,
            $this->objectTitle
        );

        if (!$object) {
            $this->error("No such {$this->objectName} {$argument}");
            return 1;
        }

        if (method_exists($object, $this->objectRelation)) {
            $result = call_user_func([$object, $this->objectRelation]);
        } elseif (property_exists($object, $this->objectRelation)) {
            $result = $object->{"{$this->objectRelation}"};
        } else {
            $this->error("No such relation {$this->objectRelation}");
            return 1;
        }

        // Convert query builder into a collection
        if ($result instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
            $result = $result->get();
        }

        // Print the result
        if (
            ($result instanceof \Illuminate\Database\Eloquent\Collection)
            || is_array($result)
        ) {
            foreach ($result as $entry) {
                $this->info($this->toString($entry));
            }
        } else {
            $this->info($this->toString($result));
        }
    }
}
