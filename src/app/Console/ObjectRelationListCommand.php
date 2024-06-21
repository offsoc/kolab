<?php

namespace App\Console;

use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

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
     * Optional arguments for $objectRelation method
     *
     * @var array
     */
    protected $objectRelationArgs = [];

    /**
     * The "relation" model class.
     *
     * @var string
     */
    protected $objectRelationClass;

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
            Str::kebab($this->objectRelation),
            $this->objectName
        );

        if (empty($this->objectRelationClass)) {
            $this->objectRelationClass = "App\\" . rtrim(ucfirst($this->objectRelation), 's');
        }

        if ($this->isSoftDeletable($this->objectRelationClass)) {
            $this->signature .= " {--with-deleted : Include deleted objects}";
        }

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
            $this->objectTitle,
            true
        );

        if (!$object) {
            $this->error("No such {$this->objectName} {$argument}");
            return 1;
        }

        if (method_exists($object, $this->objectRelation)) {
            $result = call_user_func_array([$object, $this->objectRelation], $this->objectRelationArgs);
        } elseif (property_exists($object, $this->objectRelation)) {
            $result = $object->{"{$this->objectRelation}"};
        } else {
            $this->error("No such relation {$this->objectRelation}");
            return 1;
        }

        // Convert query builder into a collection
        if (
            ($result instanceof \Illuminate\Database\Eloquent\Relations\Relation)
            || ($result instanceof \Illuminate\Database\Eloquent\Builder)
        ) {
            // @phpstan-ignore-next-line
            if ($this->isSoftDeletable($this->objectRelationClass) && $this->option('with-deleted')) {
                $result->withoutGlobalScope(SoftDeletingScope::class);
            }

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
