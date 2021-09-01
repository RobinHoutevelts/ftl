<?php

namespace App\Commands;

use App\Service\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;

class DbOpenCommand extends Command
{
    protected static $defaultName = 'db';
    private Config $config;

    public function __construct(Config $config, string $name = null)
    {
        parent::__construct($name);
        $this->setDescription('Open your database');

        $this->config = $config;
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
        $dotEnvFile = sprintf('%s/.env', $this->config->projectDir);
        if (!file_exists($dotEnvFile)) {
            return '';
        }
        $env = (new Dotenv())->parse(file_get_contents($dotEnvFile));

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
