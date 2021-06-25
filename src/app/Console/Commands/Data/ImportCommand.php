<?php

namespace App\Console\Commands\Data;

use Illuminate\Console\Command;

class ImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $commands = [
            #Import\CountriesCommand::class,
            Import\OpenExchangeRatesCommand::class,
            #Import\IP4NetsCommand::class,
            #Import\IP6NetsCommand::class
        ];

        foreach ($commands as $command) {
            $execution = new $command();
            $execution->output = $this->output;
            $execution->handle();
        }

        return 0;
    }
}
