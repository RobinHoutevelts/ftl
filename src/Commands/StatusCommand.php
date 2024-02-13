<?php

namespace App\Commands;

use App\Service\Caddy;
use App\Service\Config;
use App\Service\Docker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    protected static $defaultName = 'status';
    private Config $config;
    private Caddy $caddy;
    private Docker $docker;

    public function __construct(Config $config, Caddy $caddy, Docker $docker, string $name = null)
    {
        parent::__construct($name);
        $this->setDescription('Show status of your development environment');

        $this->config = $config;
        $this->caddy = $caddy;
        $this->docker = $docker;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isInCaddy = $this->caddy->isSiteInCaddy();

        $output->writeln('Is added to Caddy: ' . ($isInCaddy ? 'true' : 'false'));
        $output->writeln('');

        $output->writeln('Hostnames: ');
        foreach ($this->config->hosts as $hostname) {
            $output->writeln(sprintf('- https://%s', $hostname));
        }

        $output->writeln('');
        $output->writeln('Docker services: ');
        $serviceStatus = $this->docker->getServiceStatus($this->config->name);
        $output->write($serviceStatus ?: 'No services running');

        $ports = [];
        try {
            $ports = $this->docker->getPortForwards();
        } catch (\RuntimeException $e) {
            // noop
        }
        $ports += ['redis' => 'na', 'database' => 'na'];

        $output->writeln('');
        $output->writeln('Status of port forwards: ');
        foreach ($ports as $service => $port) {
            $output->writeln(sprintf('%s => %s', $service, $port));
        }

        $output->writeln('');
        $this->caddy->checkRootCert($output);
        return 0;
    }
}
