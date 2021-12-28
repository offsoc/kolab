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
        $classes = class_uses_recursive($this->objectClass);

        if (in_array(SoftDeletes::class, $classes) && $this->option('with-deleted')) {
            $objects = $this->objectClass::withTrashed();
        } else {
            $objects = new $this->objectClass();
        }

        $objects->each(
            function ($object) {
                if ($object->deleted_at) {
                    $this->info("{$this->toString($object)} (deleted at {$object->deleted_at}");
                } else {
                    $this->info("{$this->toString($object)}");
                }
            }
        );
    }
}
