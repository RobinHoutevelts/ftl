<?php

namespace App\Commands;

use App\Service\DotEnv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbOpenCommand extends Command
{
    protected static $defaultName = 'db';
    private DotEnv $dotenv;

    public function __construct(DotEnv $dotenv, string $name = null)
    {
        parent::__construct($name);
        $this->setDescription('Open your database');

        $this->dotenv = $dotenv;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($openCmd = $this->openDatabase()) {
            exec('open "' . $openCmd . '"');
        }

        return 0;
    }

    protected function openDatabase(): ?string
    {
        $env = $this->dotenv->readEnv();

        if (
            !isset($env['DB_HOST'], $env['DB_USERNAME'], $env['DB_PASSWORD'])
        ) {
            return null;
        }

        return sprintf(
            'mysql://%s:%s@%s:%s/%s',
            $env['DB_USERNAME'],
            $env['DB_PASSWORD'],
            $env['DB_HOST'],
            $env['DB_PORT'] ?? 3306,
            $env['DB_DATABASE'] ?? $env['DB_USERNAME'],
        );
    }
}
