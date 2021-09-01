<?php

namespace App\Commands;

use App\Service\Config;
use App\Service\DotEnv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DbCreateCommand extends Command
{
    protected static $defaultName = 'db-create';
    private Config $config;
    private DotEnv $dotenv;

    public function __construct(Config $config, DotEnv $dotenv, string $name = null)
    {
        parent::__construct($name);
        $this->setDescription('Create an empty database');

        $this->config = $config;
        $this->dotenv = $dotenv;
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
            $this->config->projectDir
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
        $env = $this->dotenv->readEnv();

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
