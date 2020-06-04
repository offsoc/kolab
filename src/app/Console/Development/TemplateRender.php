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
    protected $signature = 'template:render {template} {--html} {--pdf}';

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
        $template = str_replace("/", "\\", $template);

        $class = "\\App\\{$template}";

        // Invalid template, list all templates
        if (!class_exists($class)) {
            $this->info("Invalid template name. Available templates:");

            foreach (glob(app_path() . '/Documents/*.php') as $file) {
                $file = basename($file, '.php');
                $this->info("Documents/$file");
            }

            foreach (glob(app_path() . '/Mail/*.php') as $file) {
                $file = basename($file, '.php');
                $this->info("Mail/$file");
            }

            return 1;
        }

        $mode = 'html';
        if (!empty($this->option('pdf'))) {
            $mode = 'pdf';
        }

        echo $class::fakeRender($mode);
    }
}
