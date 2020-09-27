<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

class DbCreateCommand extends Command
{
    protected static $defaultName = 'db-create';
    protected array $config;
    protected string $directory;

    public function __construct(array $ftlConfig, string $name = null)
    {
        parent::__construct($name);
        $this->config = $ftlConfig;
        $this->setDescription('Create an empty database');
    }

    protected function configure()
    {
        $this
            // Options
            ->addArgument(
                'database',
                InputArgument::REQUIRED,
                'The database to create'
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

        $env = $this->readEnv();
        $database = $input->getArgument('database');

        $cmd = ['mysql', '-h', $env['DB_HOST'], '-P', $env['DB_PORT'], '-u', 'root'];

        $createCmd = sprintf(
            "CREATE DATABASE IF NOT EXISTS %s; GRANT ALL PRIVILEGES ON %s.* TO '%s'@'%%' IDENTIFIED by '%s';",
            $database,
            $database,
            $env['DB_USERNAME'],
            $env['DB_PASSWORD'],
        );

        $process = new Process(
            array_merge($cmd, ['-e', $createCmd]),
            $this->directory
        );
        $process->setTimeout(null);
        $process->run();
        if ($poutput = $process->getOutput()) {
            $output->writeln($poutput);
        }

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
