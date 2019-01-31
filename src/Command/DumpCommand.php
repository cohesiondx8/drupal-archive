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
            ->addOption('overwrite', null, InputOption::VALUE_OPTIONAL, 'Overwrite the archive?', "false")
            ->addOption('use-drush', null, InputOption::VALUE_OPTIONAL, 'Whether you want to use drush or not', "false")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output   = $output;
        $source         = $input->getArgument('source');
        $destination    = $input->getArgument('destination');
        $overwrite      = $input->getOption('overwrite');
        $useDrush       = $input->getOption('use-drush');
        $finalName      = basename($destination);
        $destFolder     = realpath(dirname($destination));
        $destination    = ('/' === substr($destFolder, -1) ? $destFolder : $destFolder.'/').$finalName;

        $overwrite      = (null === $overwrite || in_array($overwrite, ['y', '1', 'true', 'yes']));
        $useDrush       = (null === $useDrush || in_array($useDrush, ['y', '1', 'true', 'yes']));

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
            $command .= $overwrite ? '--overwrite' : '--no-overwrite';

            if ($this->output->isVerbose()) {
                $command .= ' -vvv';
            }
            $this->runCommand($command);
            return $this->output->writeln(sprintf("<info>Your backup is finished! %s</info>", $destination));
        }

        $phpSettingsFile = $source.'/sites/default/settings.php';
        if (!file_exists($phpSettingsFile)) {
            $phpSettingsFile = $source.'/docroot/sites/default/settings.php';
            if (!file_exists($phpSettingsFile)) {
                return $this->output->writeln("<error>Could not find your sites/default/settings.php!</error>");
            }
        }
        // Dodgy! But this way I can access database credentials without using drush sql command
        require_once($phpSettingsFile);
        if (!isset($databases, $databases['default'], $databases['default']['default'])) {
            return $this->output->writeln("<error>Could not find database credentials in your sites/default/settings.php!</error>");
        }

        $docroot = realpath($source.'/..');
        $folderToCompress = basename(realpath($source));

        // Remove destination if we are overwritting and it exists
        if (file_exists($destination)) {
            @unlink($destination);
        }

        $tmpFolder = sys_get_temp_dir();

        // Tar folders
        $command = sprintf('cd %s && tar --dereference -cf %s %s', $docroot, $destination, $folderToCompress);
        $this->runCommand($command);

        $database = $databases['default']['default'];

        // Create database dump
        $sqlDump = 'database_'.date('U').'.sql';
        $port = '';
        if (!empty($database['port'])) {
            $port = sprintf('--port=%s', $database['port']);
        }
        // @see https://dev.mysql.com/doc/mysql-backup-excerpt/5.7/en/mysqldump-sql-format.html
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s %s > %s',
            $database['username'],
            $database['password'],
            $database['host'],
            $port,
            $database['database'],
            $tmpFolder.'/'.$sqlDump
        );
        $this->runCommand($command);

        // Add sql
        $command = sprintf('cd %s && tar --dereference -rf %s %s', $tmpFolder, $destination, $sqlDump);
        $this->runCommand($command);

        // GZIP
        $command = sprintf('cd %s && gzip --no-name -f %s', $destFolder, $finalName);
        $this->runCommand($command);

        // Rename gzip to destination
        $command = sprintf('cd %s && mv %s.gz %s', $destFolder, $finalName, $finalName);
        $this->runCommand($command);

        // Delete the sqlDump file
        @unlink($tmpFolder.'/'.$sqlDump);

        return $this->output->writeln(sprintf("<info>Your backup is finished! %s</info>", $destination));
    }
}