<?php

namespace App\Commands;

use App\Service\Caddy;
use App\Service\Config;
use App\Service\Docker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends Command
{
    protected static $defaultName = 'start';
    private Config $config;
    private Caddy $caddy;
    private Docker $docker;

    public function __construct(Config $config, Caddy $caddy, Docker $docker, string $name = null)
    {
        parent::__construct($name);
        $this->setDescription('Start your development environment');

        $this->config = $config;
        $this->caddy = $caddy;
        $this->docker = $docker;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Adding sites to Caddy');
        $this->caddy->addSiteToCaddy();
        $output->writeln('Restarting caddy');
        $this->caddy->restartCaddy();

        $output->writeln('Starting docker services ( redis and mysql )');
        $this->docker->startDockerServices();
        $ports = $this->docker->getPortForwards();
        if ($ports) {
            $output->writeln('Updating .env');
            $this->replaceDotEnv($ports);
        }

        $output->writeln('');
        $this->caddy->checkRootCert($output);

        $output->writeln('Ready to go make some moolah:');
        foreach ($this->config->hosts as $hostname) {
            $output->writeln(sprintf('- https://%s', $hostname));
        }
        return 0;
    }

    protected function replaceDotEnv(array $ports): void
    {
        $dotEnvFile = sprintf('%s/.env', $this->config->projectDir);
        if (!file_exists($dotEnvFile)) {
            return;
        }

        $replaced = false;
        $lines = file($dotEnvFile);
        foreach ($lines as &$line) {
            if (strpos($line, 'DB_PORT=') === 0 && strpos($line, 'DB_PORT=3306') !== 0) {
                $line = sprintf('DB_PORT=%s', $ports['database']) . "\n";
                $replaced = true;
                continue;
            }
            if (strpos($line, 'REDIS_PORT=') === 0 && strpos($line, 'REDIS_PORT=6379') !== 0) {
                $line = sprintf('REDIS_PORT=%s', $ports['redis']) . "\n";
                $replaced = true;
                continue;
            }
        }
        unset($line);

        if (!$replaced) {
            return;
        }
        file_put_contents(
            $dotEnvFile,
            $lines
        );
    }
}
