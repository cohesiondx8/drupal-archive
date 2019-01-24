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
    protected function runCommand(string $command, $timeout = 600)
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

    /**
     * Check whether the local version of drush is below 8.1.17 so the commands can run
     * @return bool
     */
    protected function checkRunnableDrush()
    {
        try {
            $command = 'drush --version';
            $drushVersion = $this->runCommand($command);
        } catch (\Exception $exc) {
            $this->output->writeln(sprintf("<error>The command '%s' failed!\n\n%s</error>", $command, $exc->getMessage()));
            return false;
        }

        preg_match('`[7-9]\.[0-9]+\.[0-9]+`', $drushVersion, $versionFound);

        if (empty($versionFound[0])) {
            $this->output->writeln("<error>Could not find your drush version...</error>");
            return false;
        }

        if ($versionFound[0] > "8.1.17") {
            $this->output->writeln(sprintf("<error>Your drush is too new and does not have drush archive-dump (needs to be 8.1.17 or lower and you have %s)\nMaybe use --use-drush=false ?</error>", $versionFound[0]));
            return false;
        }

        return true;
    }
}