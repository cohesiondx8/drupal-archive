<?php

namespace CohesionDrupalArchive\Command;

use Symfony\Component\Console\Input\{InputInterface, InputArgument, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('cda:dump')
            ->setDescription('Creates an archive (tar\'ed and gzip\'ed) of your drupal website.')
            ->addArgument('source', InputArgument::REQUIRED, 'Path to your drupal root folder. ex: /var/www/html')
            ->addArgument('destination', InputArgument::REQUIRED, 'Where the archive should end up. ex: /tmp/backup.tar.gz')
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
        $destFolder     = realpath(dirname($destination));
        $destination    = ('/' === substr($destFolder, -1) ? $destFolder : $destFolder.'/').$finalName;

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

            // Make sure we are running an old version of drush
            if (!$this->checkRunnableDrush()) {
                return;
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
        $port = '';
        if (!empty($database['port'])) {
            $port = sprintf('--port=%s', $database['port']);
        }
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s --databases %s > /tmp/%s',
            $database['username'],
            $database['password'],
            $database['host'],
            $port,
            $database['database'],
            $sqlDump
        );
        $this->runCommand($command);

        $docroot = realpath($source.'/..');
        $folderToCompress = basename(realpath($source));

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