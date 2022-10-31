<?php

namespace Yuga\Repl\Providers;

use Yuga\Repl\Console\ReplCommand;
use Yuga\Providers\ServiceProvider;
use Yuga\Repl\Console\ReplSmartCommand;
use Yuga\Interfaces\Application\Application;
use Yuga\Providers\Shared\MakesCommandsTrait;

class ReplServiceProvider extends ServiceProvider
{
    use MakesCommandsTrait;

    /**
     * Register a service to the application.
     *
     * @param \Yuga\Interfaces\Application\Application
     *
     * @return mixed
     */
    public function load(Application $app)
    {
        if ($app->runningInConsole()) {
            $app->singleton('command.repl', function ($app) {
                return new ReplCommand;
            });

            $app->singleton('command.repl.smart', function ($app) {
                return new ReplSmartCommand;
            });

            $this->commands('command.repl', 'command.repl.smart');
        }
    }

    public function boot()
    {
        
    }
}
