<?php

namespace Yuga\Repl\Console;

use Yuga\Console\Command;
use Yuga\Repl\Console\Parser;

class ReplCommand extends Command
{
    protected $name = 'repl:simple';

    // protected $output;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interact with Your Application';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $input = $this->prompt();
        $buffer = null;

        while ($input != 'exit;') {
            try {
                $buffer .= $input;

                if ((new Parser)->statements($buffer)) {
                    ob_start();
                    // $app = $this->container;
                    eval($buffer);
                    $response = ob_get_clean();
                    
                    if (!empty($response)) $this->output->writeln(trim($response));

                    $buffer = null;
                }
                $buffer = null;
            } catch (\Throwable $err) {
                $buffer = null;
                $this->error($err->getMessage());
            }

            $input = $this->prompt($buffer !== null);
        }

        if ($input == 'exit;') exit;
    }

    /**
     * Prompt the user for an input
     * 
     * @param  boolean $indent
     * @return string
     */
    protected function prompt($indent = false)
    {
        $indent = $indent ? '*' : null;

        if ($indent === '*') {
            @ob_end_clean();
        }
        return $this->ask("<info>yuga{$indent}> </info>");
    }
}
