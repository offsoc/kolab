<?php

namespace App\Console;

/**
 * This abstract class provides a means to treat objects in our model using CRUD.
 */
abstract class ObjectReadCommand extends ObjectCommand
{
    public function __construct()
    {
        $this->description = "Read a {$this->objectName}";
        $this->signature = sprintf(
            "%s%s:read {%s}",
            $this->commandPrefix ? $this->commandPrefix . ":" : "",
            $this->objectName,
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

        $object = $this->getObject($this->objectClass, $argument, $this->objectTitle);

        if (!$object) {
            $this->error("No such {$this->objectName} {$argument}");
            return 1;
        }

        $this->info($this->toString($object));
    }
}
