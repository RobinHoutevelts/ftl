<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

class DbImportCommand extends Command
{
    protected static $defaultName = 'db-import';
    protected array $config;
    protected string $directory;

    public function __construct(array $ftlConfig, string $name = null)
    {
        parent::__construct($name);
        $this->config = $ftlConfig;
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = getcwd();
        if (!file_exists('.lando.yml')) {
            $directory = exec('git rev-parse --show-toplevel 2> /dev/null') ?: '';
            if (!$directory) {
                throw new \RuntimeException(
                    'Can\'t find root directory of project. Did you run "git init"?'
                );
            }
        }
        $this->directory = $directory;

        $file = $input->getArgument('file');

        $importCmd = __DIR__ . '/../util/sql-import.sh';

        $env = $this->readEnv();
        $database = $input->getOption('database') ?: $env['DB_DATABASE'];

        $process = new Process(
            [$importCmd, $file],
            $this->directory,
            [
                'MYSQL_DATABASE' => $database,
                'MYSQL_PORT' => $env['DB_PORT'],
            ]
        );
        $process->setTimeout(null);
        $process->start();
        foreach ($process as $type => $data) {
            if ($type === $process::OUT) {
                $output->write($data);
            } else {
                // err
                $output->write($data);
            }
        }
        $process->wait();

        return $process->getExitCode();
    }

    protected function readEnv(): array
    {
        $dotEnvFile = sprintf('%s/.env', $this->directory);
        if (!file_exists($dotEnvFile)) {
            throw new \RuntimeException(sprintf('Could not find your .env file at "%s"', $dotEnvFile));
        }
        $env = (new Dotenv())->parse(file_get_contents($dotEnvFile));

        if (
            !isset($env['DB_HOST'])
            || !isset($env['DB_USERNAME'])
            || !isset($env['DB_PASSWORD'])
        ) {
            throw new \RuntimeException('Could not find DB_* in your .env file.');
        }

        return $env;
    }
}
