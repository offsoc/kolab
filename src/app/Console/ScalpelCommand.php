<?php

namespace App\Console;

class ScalpelCommand extends ObjectCommand
{
    public function handle()
    {
        if ($this->dangerous) {
            $this->warn(
                "This command is a dangerous scalpel command with potentially significant unintended consequences"
            );

            $confirmation = $this->confirm("Are you sure you understand what's about to happen?");

            if (!$confirmation) {
                $this->info("Better safe than sorry.");
                return false;
            }

            $this->info("Vámonos!");
        }

        return true;
    }
}
