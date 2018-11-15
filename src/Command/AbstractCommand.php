<?php

namespace CohesionDrupalArchive\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

class AbstractCommand extends Command
{
    protected $output;

    /**
     * Run process on the server and return whatever the command is supposed to return
     * @example echo $this->runCommand('drush --version');
     */
    protected function runCommand(string $command, $timeout = 300)
    {
        $output = $this->output;
        $output->writeln(sprintf('<comment>Running command "%s"</comment>', $command), $output::VERBOSITY_VERBOSE);

        $process = new Process($command);
        $process->setTimeout($timeout);

        $process->mustRun(function ($type, $buffer) use ($output) {
            $output->writeln($buffer, $output::VERBOSITY_VERBOSE);
        });

        return trim($process->getOutput());
    }
}