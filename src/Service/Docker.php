<?php

namespace App\Service;

use Symfony\Component\Process\Process;

class Docker
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function createMysqlAndRedisDockerCompose(): string
    {
        $mysqlcnf = sprintf('%s/../config/mysql/mysql.cnf', __DIR__);

        $name = $this->config->name;
        $compose = <<<YAML
version: '3'
services:
  redis:
    image: redis:5-alpine
    ports:
      - '6379'

  mysql:
    image: 'bitnami/mariadb:10.4'
    environment:
      ALLOW_EMPTY_PASSWORD: 'yes'
      MARIADB_DATABASE: '$name'
      MARIADB_PASSWORD: '$name'
      MARIADB_USER: '$name'
    volumes:
      - >-
        $mysqlcnf:/opt/bitnami/mariadb/conf/my_custom.cnf
    healthcheck:
      test: mysql -uroot --silent --execute "SHOW DATABASES;"
      interval: 2s
      timeout: 10s
      retries: 25
    ports:
      - '3306'
YAML;

        return $compose;
    }

    public function startDockerServices(): void
    {
        $compose = $this->createMysqlAndRedisDockerCompose();
        file_put_contents(
            sprintf('%s/.docker-compose.yml.dev', $this->config->projectDir),
            $compose
        );

        $process = $this->process(['up', '-d']);
        if ($process->run() !== 0) {
            throw new \RuntimeException(
                sprintf('Error while booting docker-compose services.: %s', $process->getOutput())
            );
        }
    }

    public function stopDockerServices(): void
    {
        $this->process(['stop'])->run();
    }

    public function getServiceStatus(): string
    {
        $process = $this->process(['ps']);
        $process->run();
        return $process->getOutput();
    }

    public function getPortForwards(): array
    {
        $ports = [];
        foreach (['redis' => 6379, 'mysql' => 3306] as $service => $port) {
            // docker-compose -p <projectName> -f .docker-compose.yml.dev port redis 6379
            $process = $this->process(['port', $service, $port]);
            if ($process->run() !== 0) {
                throw new \RuntimeException(sprintf('Error while fetching %s port.', $service));
            }
            $matches = [];
            preg_match('/:(\d+)$/', trim($process->getOutput()), $matches);
            $forwardedPort = $matches[1] ?? null;
            if (!$forwardedPort) {
                throw new \RuntimeException(sprintf('Error while parsing %s port.', $service));
            }
            $ports[$service] = $forwardedPort;
        }

        return $ports;
    }

    private function process(array $command): Process
    {
        $dockerCommand = [
            // docker-compose -p <projectName> -f .docker-compose.yml
            $this->config->config['dockerBin'], 'compose', '-p', $this->config->name, '-f', '.docker-compose.yml.dev'
        ];

        // docker-compose -p <projectName> -f .docker-compose.yml <command>
        return new Process(array_merge($dockerCommand, $command), $this->config->projectDir);
    }
}
