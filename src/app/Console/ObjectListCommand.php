<?php

namespace App\Console;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * This abstract class provides a means to treat objects in our model using CRUD, with the exception that
 * this particular abstract class lists objects.
 */
abstract class ObjectListCommand extends ObjectCommand
{
    public function __construct()
    {
        $this->description = "List all {$this->objectName} objects";

        $this->signature = $this->commandPrefix ? $this->commandPrefix . ":" : "";

        if (!empty($this->objectNamePlural)) {
            $this->signature .= "{$this->objectNamePlural}";
        } else {
            $this->signature .= "{$this->objectName}s";
        }

        $classes = class_uses_recursive($this->objectClass);

        if (in_array(SoftDeletes::class, $classes)) {
            $this->signature .= " {--with-deleted : Include deleted {$this->objectName}s}";
        }

        $this->signature .= " {--attr=* : Attributes other than the primary unique key to include}"
            . "{--filter=* : Additional filter(s) or a raw SQL WHERE clause}";

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $classes = class_uses_recursive($this->objectClass);

        if (in_array(SoftDeletes::class, $classes) && $this->option('with-deleted')) {
            $objects = $this->objectClass::withTrashed();
        } else {
            $objects = new $this->objectClass();
        }

        foreach ($this->option('filter') as $filter) {
            $objects = $this->applyFilter($objects, $filter);
        }

        foreach ($objects->cursor() as $object) {
            if ($object->deleted_at) {
                $this->info("{$this->toString($object)} (deleted at {$object->deleted_at}");
            } else {
                $this->info("{$this->toString($object)}");
            }
        }
    }

    /**
     * Apply pre-configured filter or raw WHERE clause to the main query.
     *
     * @param object $query  Query builder
     * @param string $filter Pre-defined filter identifier or raw SQL WHERE clause
     *
     * @return object Query builder
     */
    public function applyFilter($query, string $filter)
    {
        // Get objects marked as deleted, i.e. --filter=TRASHED
        // Note: For use with --with-deleted option
        if (strtolower($filter) === 'trashed') {
            return $query->whereNotNull('deleted_at');
        }

        // Get objects with specified status, e.g. --filter=STATUS:SUSPENDED
        if (preg_match('/^status:([a-z]+)$/i', $filter, $matches)) {
            $status = strtoupper($matches[1]);
            $const = "{$this->objectClass}::STATUS_{$status}";

            if (defined($const)) {
                return $query->where('status', '&', constant($const));
            }

            throw new \Exception("Unknown status in --filter={$filter}");
        }

        // Get objects older/younger than specified time, e.g. --filter=MIN-AGE:1Y
        if (preg_match('/^(min|max)-age:([0-9]+)([mdy])$/i', $filter, $matches)) {
            $operator = strtolower($matches[1]) == 'min' ? '<=' : '>=';
            $count = $matches[2];
            $period = strtolower($matches[3]);
            $date = \Carbon\Carbon::now();

            if ($period == 'y') {
                $date->subYearsWithoutOverflow($count);
            } elseif ($period == 'm') {
                $date->subMonthsWithoutOverflow($count);
            } else {
                $date->subDays($count);
            }

            return $query->where('created_at', $operator, $date);
        }

        return $query->whereRaw($filter);
    }
}
