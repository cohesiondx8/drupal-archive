<?php

namespace CohesionDrupalArchive\Command;

use Symfony\Component\Console\Input\{InputInterface, InputArgument, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('cda:dump')
            ->setDescription('Creates an archive of your drupal website.')
            ->addArgument('source', InputArgument::REQUIRED, 'Path to your drupal root folder')
            ->addArgument('destination', InputArgument::REQUIRED, 'Where the archive should end up')
            ->addOption('overwrite', null, InputOption::VALUE_OPTIONAL, 'Overwrite the archive?', "true")
            ->addOption('use-drush', null, InputOption::VALUE_OPTIONAL, 'Whether you want to use drush or not', "true")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output   = $output;
        $source         = $input->getArgument('source');
        $destination    = $input->getArgument('destination');
        $overwrite      = false;
        $useDrush       = false;
        $finalName      = basename($destination);
        $destFolder     = dirname($destination);

        if (in_array($input->getOption('overwrite'), ['y', '1', 'true', 'yes'])) {
            $overwrite = true;
        }

        if (in_array($input->getOption('use-drush'), ['y', '1', 'true', 'yes'])) {
            $useDrush = true;
        }

        // Check if file already exists and we don't want to overwrite it
        if (false === $overwrite && file_exists($destination)) {
            return $this->output->writeln(sprintf("<error>The file '%s' already exists! Use --overwrite if you want to overwrite it.</error>", $destination));
        }

        if ($useDrush) {

            // Find the drush version - we can use drush archive-dump up to drush 8.1.17
            try {
                $command = 'drush --version';
                $drushVersion = $this->runCommand($command);
            } catch (\Exception $exc) {
                return $this->output->writeln(sprintf("<error>The command '%s' failed!\n\n%s</error>", $command, $exc->getMessage()));
            }

            preg_match('`[7-9]\.[0-9]+\.[0-9]+`', $drushVersion, $versionFound);

            if (empty($versionFound[0])) {
                return $this->output->writeln("<error>Could not find your drush version...</error>");
            }

            if ($versionFound[0] > "8.1.17") {
                return $this->output->writeln(sprintf("<error>Your drush is too new and does not have drush archive-dump (needs to be 8.1.17 or lower and you have %s)</error>", $versionFound[0]));
            }

            $command = sprintf('cd %s && drush archive-dump --destination=%s ', $source, $destination);
            if ($overwrite) {
                $command .= '--overwrite';
            } else {
                $command .= '--no-overwrite';
            }

            $this->runCommand($command);
            return $this->output->writeln(sprintf("<info>Your backup is finished! %s</info>", $destination));
        }

        // Dodgy! But this way I can access database credentials without using drush sql command
        @require_once($source.'/sites/default/settings.php');
        if (!isset($databases, $databases['default'], $databases['default']['default'])) {
            return $this->output->writeln("<error>Could not find database credentials in your sites/default/settings.php!</error>");
        }

        $database = $databases['default']['default'];

        // Create database dump
        $sqlDump = 'database_'.date('U').'.sql';
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s --databases %s > /tmp/%s',
            $database['username'],
            $database['password'],
            $database['host'],
            $database['port'],
            $database['database'],
            $sqlDump
        );
        $this->runCommand($command);

        $docroot = dirname($source);
        $folderToCompress = basename($source);
        // Tar folders
        $command = sprintf('cd %s && tar --exclude="%s/sites/*/files" --dereference -cf %s %s', $docroot, $folderToCompress, $destination, $folderToCompress);
        $this->runCommand($command);

        // Add sql
        $command = sprintf('cd /tmp && tar --dereference -rf %s %s', $destination, $sqlDump);
        $this->runCommand($command);

        // GZIP
        $command = sprintf('cd %s && gzip --no-name -f %s', $destFolder, $finalName);
        $this->runCommand($command);

        // Rename gzip to destination
        $command = sprintf('cd %s && mv %s.gz %s', $destFolder, $finalName, $finalName);
        $this->runCommand($command);

        return $this->output->writeln(sprintf("<info>Your backup is finished! %s</info>", $destination));
    }
}