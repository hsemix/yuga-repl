<?php

namespace Yuga\Sockets\Providers;

use Yuga\Providers\ServiceProvider;
use Yuga\Sockets\Console\StartCommand;
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
            $app->singleton('command.sockets.start', function ($app) {
                return new ReplCommand;
            });

            $this->commands('command.sockets.start');
        }
    }

    public function boot()
    {
        
    }
}
