<?php

namespace App\Console;

/**
 * This abstract class provides a means to treat objects in our model using CRUD.
 */
abstract class ObjectDeleteCommand extends ObjectCommand
{
    public function __construct()
    {
        $this->description = "Delete a {$this->objectName}";
        $this->signature = sprintf(
            "%s%s:delete {%s}",
            $this->commandPrefix ? $this->commandPrefix . ":" : "",
            $this->objectName,
            $this->objectName
        );

        if ($this->isSoftDeletable($this->objectClass)) {
            $this->signature .= " {--with-deleted : Consider deleted {$this->objectName}s}";
        }

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $result = parent::handle();

        if (!$result) {
            return 1;
        }

        $argument = $this->argument($this->objectName);

        $object = $this->getObject($this->objectClass, $argument, $this->objectTitle);

        if (!$object) {
            $this->error("No such {$this->objectName} {$argument}");
            return 1;
        }

        if ($this->commandPrefix == 'scalpel') {
            if ($object->deleted_at) {
                $object->forceDeleteQuietly();
            } else {
                $object->deleteQuietly();
            }
        } else {
            if ($object->deleted_at) {
                $object->forceDelete();
            } else {
                $object->delete();
            }
        }
    }
}
