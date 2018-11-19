<?php

namespace CohesionDrupalArchive\Command;

use Symfony\Component\Console\Input\{InputInterface, InputArgument, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

class RestoreCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('cda:restore')
            ->setDescription('Restores an archive of a previously dumped drupal website.')
            ->addArgument('source', InputArgument::REQUIRED, "Path to your drupal website archive (the .tar or .gz)\tex: /tmp/backup.tar.gz")
            ->addArgument('destination', InputArgument::REQUIRED, "Where the extracted drupal website should end up\t\tex: /var/www/html")
            ->addOption('use-drush', null, InputOption::VALUE_OPTIONAL, 'Whether you want to use drush or not', "false")
            ->addOption('overwrite', null, InputOption::VALUE_OPTIONAL, 'Overwrite the destination?', "false")
            ->addOption('db-url', null, InputOption::VALUE_REQUIRED, 'Database credentials for the import (format: mysql://user:password@localhost/db_name)', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output   = $output;
        $source         = $input->getArgument('source');
        $destination    = $input->getArgument('destination');
        $useDrush       = $input->getOption('use-drush');
        $overwrite      = $input->getOption('overwrite');
        $overwrite      = (null === $overwrite || in_array($overwrite, ['y', '1', 'true', 'yes']));
        $useDrush       = (null === $useDrush || in_array($useDrush, ['y', '1', 'true', 'yes']));
        $databasePath   = $input->getOption('db-url');

        if (empty($databasePath) || !($databaseUrl = parse_url($databasePath))) {
            return $this->output->writeln("<error>The parameter db-url is required or incorrect! Ex: --db-url=mysql://user:password@localhost/db_name</error>");
        }

        // Fill in defaults to prevent notices.
        $databaseUrl += array('scheme' => NULL, 'user' => NULL, 'pass' => NULL, 'host' => NULL, 'port' => NULL, 'path' => NULL);
        $databaseUrl = (object) array_map('urldecode', $databaseUrl);
        $specs = array(
            'driver' => $databaseUrl->scheme == 'mysqli' ? 'mysql' : $databaseUrl->scheme,
            'username' => $databaseUrl->user,
            'password' => $databaseUrl->pass,
            'host' => $databaseUrl->host,
            'port' => $databaseUrl->port,
            'database' => ltrim($databaseUrl->path, '/'),
        );

        // Check for required parameters in the db-url
        $passed = true;
        foreach (['username', 'password', 'host', 'database'] as $value) {
            if (empty($specs[$value])) {
                $this->output->writeln(sprintf("<error>You are missing the '%s' parameter in the db-url</error>", $value));
                $passed = false;
            }
        }

        // Error messages have been printed at this point
        if (!$passed) {
            return;
        }

        if ($useDrush) {
            // Make sure we are running an old version of drush
            if (!$this->checkRunnableDrush()) {
                return;
            }

            $command = sprintf('drush archive-restore %s --destination=%s --db-url=%s ', $source, $destination, $databasePath);
            $command .= $overwrite ? '--overwrite' : '--no-overwrite';

            if ($this->output->isVerbose()) {
                $command .= ' -vvv';
            }
            $this->runCommand($command);
            return $this->output->writeln(sprintf("<info>Your backup has been restored in %s !</info>", $destination));
        }

        if (is_dir($destination)) {
            if (!$overwrite) {
                return $this->output->writeln(sprintf("<error>The destination folder %s already exists! Maybe use --overwrite ?</error>", $destination));
            }
            // Chmod so we can delete all files
            $this->runCommand(sprintf('chmod -R 0777 %s && rm -rf %s', $destination, $destination));
        }

        // Create a temporary folder where we'll work
        define('TMP_DRUPAL_FOLDER', '/tmp/drush_restore_'.date('U'));

        if (!file_exists(TMP_DRUPAL_FOLDER)) {
            mkdir(TMP_DRUPAL_FOLDER);
        }

        // Extract archive to tmp folder folder
        $command = sprintf('tar -C %s -xzf %s', TMP_DRUPAL_FOLDER, $source);
        $this->runCommand($command);

        // Find the html, docroot or web folder from our archive
        $extractedFolder = $this->runCommand(sprintf('find %s/* -maxdepth 0 -type d', TMP_DRUPAL_FOLDER));

        // Move folder to destination
        $this->runCommand(sprintf('mkdir -p %s && cp -rT %s %s', $destination, $extractedFolder, $destination));

        // Find the sql dump
        $extractedSql = $this->runCommand(sprintf('find %s/* -maxdepth 0 -type f -name \'*.sql\'', TMP_DRUPAL_FOLDER));

        try {
            // Restore Database - 1. Create Database if not exists
            $this->runCommand(sprintf('mysqladmin --host=%s --user=%s --password=%s create %s',
                $specs['host'],
                $specs['username'],
                $specs['password'],
                $specs['database']
            ));
        } catch (\Exception $exc) {
            // The above command will fail if the database already exists and that's fine.
            $this->output->writeln(sprintf("<comment>%s</comment>", $exc->getMessage()));
        }

        // Restore Database - 2. Load the dump
        $this->runCommand(sprintf('mysql --host=%s --user=%s --password=%s %s < %s',
            $specs['host'],
            $specs['username'],
            $specs['password'],
            $specs['database'],
            $extractedSql
        ));

        // Make sure settings.php exists
        $settingsFile = $this->runCommand(sprintf('find %s/sites -type f -name settings.php', $destination));
        if (!file_exists($settingsFile)) {
            $folder = dirname($settingsFile);
            $this->runCommand(sprintf('cp %s/default.settings.php %s/settings.php', $folder, $folder));
        }

        // Append new DB info to settings.php
        chmod($settingsFile, 0664);
        file_put_contents($settingsFile, "\n// Appended by drupal-archive cda:restore command.\n", FILE_APPEND);

        $drupalDb = ['default' => ['default' => $specs]];
        file_put_contents($settingsFile, '$databases = ' . var_export($drupalDb, TRUE) . ";\n", FILE_APPEND);

        // Clean up our mess in /tmp
        $this->runCommand(sprintf('cd /tmp && chmod -R 0777 %s && rm -rf %s', TMP_DRUPAL_FOLDER, TMP_DRUPAL_FOLDER));
    }
}