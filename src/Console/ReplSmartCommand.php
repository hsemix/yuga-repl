<?php

namespace Yuga\Repl\Console;

use Psy\Shell;
use Psy\Configuration;
use Yuga\Console\Command;

class ReplSmartCommand extends Command
{
    protected $name = 'repl:smart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interact with Your Application (with a smart interactivity).';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $shell = new Shell(new Configuration([]));

        try
        {
            $shell->run();
        }
        catch (\Exception $e)
        {
            echo $e->getMessage() . PHP_EOL;
        }
    }
}
