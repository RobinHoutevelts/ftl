<?php

namespace App\Commands;

use App\Service\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

class DbExportCommand extends Command
{
    protected static $defaultName = 'db-export';
    private Config $config;

    public function __construct(Config $config, string $name = null)
    {
        parent::__construct($name);
        $this->setDescription('Export the database');

        $this->config = $config;
    }

    protected function configure()
    {
        $this
            // Options
            ->addOption(
                'database',
                'db',
                InputOption::VALUE_OPTIONAL,
                'The database to export from'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $env = $this->readEnv();
        $database = $input->getOption('database') ?: $env['DB_DATABASE'];

        $importCmd = __DIR__ . '/../util/sql-export.sh';

        $process = new Process(
            [$importCmd],
            $this->config->projectDir,
            [
                'MYSQL_DATABASE' => $database,
                'MYSQL_PORT' => $env['DB_PORT'] ?? 3306,
                'MYSQL_USER' => $env['DB_USERNAME'] ?? 'root',
                'MYSQL_PASSWORD' => $env['DB_PASSWORD'] ?? '',
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
        $dotEnvFile = sprintf('%s/.env', $this->config->projectDir);
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
