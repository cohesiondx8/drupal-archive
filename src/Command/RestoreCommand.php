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
            ->addArgument('source', InputArgument::REQUIRED, 'Path to your drupal website archive (the .tar or .gz)')
            ->addArgument('destination', InputArgument::REQUIRED, 'Where the extracted drupal website should end up')
            ->addOption('use-drush', null, InputOption::VALUE_OPTIONAL, 'Whether you want to use drush or not', "true")
            ->addOption('db-url', null, InputOption::VALUE_REQUIRED, 'Database credentials for the import (format: mysql://user:password@localhost/db_name)', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Coming soon
    }
}