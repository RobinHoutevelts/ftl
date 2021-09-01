<?php

namespace App\Commands;

use App\Service\Config;
use App\Service\DotEnv;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DbImportCommand extends DbExportCommand
{
    protected static $defaultName = 'db-import';

    public function __construct(Config $config, DotEnv $dotenv, string $name = null)
    {
        parent::__construct($config, $dotenv, $name);
        $this->setDescription('Import an existing database');
    }

    protected function configure()
    {
        $this
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'The dump to import. ( .sql .gz .zip )'
            )
            // Options
            ->addOption(
                'database',
                'db',
                InputOption::VALUE_OPTIONAL,
                'The database to import to'
            )
        ;
    }

    protected function getCommand(InputInterface $input): array
    {
        $file = $input->getArgument('file');
        return [__DIR__ . '/../util/sql-import.sh', $file];
    }
}
