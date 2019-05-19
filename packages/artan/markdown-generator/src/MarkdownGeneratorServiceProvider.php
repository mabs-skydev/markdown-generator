<?php

namespace Artan\MarkdownGenerator;

use Illuminate\Support\ServiceProvider;

use Artan\MarkdownGenerator\Commands\MarkdownGeneratorCommand;

class MarkdownGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publishes
        $this->publishes([
            __DIR__.'\config\markdown-generator.php' => config_path('markdown-generator.php'),
        ]);

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MarkdownGeneratorCommand::class,
            ]);
        }
    }
}
