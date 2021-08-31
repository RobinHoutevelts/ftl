<?php

namespace App\Commands;

use App\Service\Caddy;
use App\Service\Docker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopCommand extends Command
{
    protected static $defaultName = 'stop';
    private Caddy $caddy;
    private Docker $docker;

    public function __construct(Caddy $caddy, Docker $docker, string $name = null)
    {
        parent::__construct($name);
        $this->setDescription('Stop/pause your development environment');
        $this->caddy = $caddy;
        $this->docker = $docker;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->caddy->removeSiteFromCaddy();
        $this->caddy->restartCaddy();

        $this->docker->stopDockerServices();
        return 0;
    }
}
