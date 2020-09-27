<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;

class DbOpenCommand extends Command
{
    protected static $defaultName = 'db';
    protected array $config;
    protected string $directory;

    public function __construct(array $ftlConfig, string $name = null)
    {
        parent::__construct($name);
        $this->config = $ftlConfig;
        $this->setDescription('Open your database');
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

        if ($openCmd = $this->openDatabase()) {
            exec('open "' . $openCmd . '"');
        }

        return 0;
    }

    protected function openDatabase()
    {
        $dotEnvFile = sprintf('%s/.env', $this->directory);
        if (!file_exists($dotEnvFile)) {
            return '';
        }
        $env = (new Dotenv())->parse(file_get_contents($dotEnvFile));

        if (
            !isset($env['DB_HOST'], $env['DB_USERNAME'], $env['DB_PASSWORD'])
        ) {
            return '';
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
