<?php

namespace App\Commands;

use App\Service\Caddy;
use App\Service\Config;
use App\Service\Docker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DestroyCommand extends Command
{
    protected static $defaultName = 'destroy';
    private Config $config;
    private Caddy $caddy;
    private Docker $docker;

    public function __construct(Config $config, Caddy $caddy, Docker $docker, string $name = null)
    {
        parent::__construct($name);
        $this->setDescription('Destroy and delete your development environment');

        $this->config = $config;
        $this->caddy = $caddy;
        $this->docker = $docker;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->caddy->removeSiteFromCaddy();
        $this->caddy->restartCaddy();

        $this->docker->destroyDockerServices();
        return 0;
    }
}
