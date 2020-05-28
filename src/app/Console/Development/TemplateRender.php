<?php

namespace App\Console\Development;

use Illuminate\Console\Command;

class TemplateRender extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'template:render {template}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Render a email template.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $template = $this->argument('template');
        $template = str_replace('/', '\\', $template);

        $class = '\\App\\' . $template;

        echo $class::fakeRender();
    }
}
