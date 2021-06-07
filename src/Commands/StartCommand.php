<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class StartCommand extends Command
{
    protected static $defaultName = 'start';
    protected array $config;
    protected string $directory;

    public function __construct(array $ftlConfig, string $name = null)
    {
        parent::__construct($name);
        $this->config = $ftlConfig;
        $this->config['caddyDir'] = str_replace('$HOME', $_SERVER['HOME'], $this->config['caddyDir']);
        $this->setDescription('Start your development environment');
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
        if (!file_exists($this->config['caddyFile'])) {
            throw new \RuntimeException(sprintf(
                'Could not find caddyFile at "%s". Does it exist?',
                $this->config['caddyFile'],
            ));
        }

        $this->directory = $directory;

        $info = $this->parseLandoFile(sprintf('%s/.lando.yml', $this->directory));

        $output->writeln('Adding sites to Caddy');
        $this->addSiteToCaddy($info);
        $output->writeln('Restarting caddy');
        $this->restartCaddy();

        $output->writeln('Starting docker services ( redis and mysql )');
        $ports = $this->startDockerServices($info);
        if ($ports) {
            $output->writeln('Updating .env');
            $this->replaceDotEnv($ports);
        }

        $output->writeln('');
        $this->checkRootCert($output);

        $output->writeln('Ready to go make some moolah:');
        foreach ($info['hosts'] as $hostname) {
            $output->writeln(sprintf('- https://%s', $hostname));
        }
        return 0;
    }

    protected function restartCaddy(): void
    {
        $autosaveFile = sprintf('%s/autosave.json', $this->config['caddyDir']);
        if (file_exists($autosaveFile)) {
            unlink($autosaveFile);
        }

        exec(sprintf('rm -rf "%s/certificates/local"', $this->config['caddyDir']));
        exec($this->config['brewBin'] . ' services stop caddy');
        exec($this->config['brewBin'] . ' services start caddy');
    }

    protected function parseLandoFile(string $landoFile)
    {
        if (!file_exists($landoFile)) {
            throw new \RuntimeException(
                'Can\'t find .lando.yml file in root directory of project.'
            );
        }

        $lando = Yaml::parse(file_get_contents($landoFile));

        if (empty($lando)) {
            throw new \RuntimeException('Could not parse .lando.yml content. Is it valid yaml?');
        }
        if (empty($lando['name'])) {
            throw new \RuntimeException('.lando.yml does not contain the name of the project.');
        }

        $phpVersion = $lando['config']['php'] ?? $this->config['defaultPhpVersion'];
        $webroot = $lando['config']['webroot'] ?? $this->config['defaultWebroot'];
        $hostnames = [
            // foobar.dev.kzen.pro
            sprintf('%s.%s', $lando['name'], $this->config['hostname']),
            // admin-foobar.dev.kzen.pro
            sprintf('admin-%s.%s', $lando['name'], $this->config['hostname'])
        ];
        if (!empty($lando['proxy']['appserver_nginx'])) {
            $hostnames = array_merge($hostnames, $lando['proxy']['appserver_nginx']);
        }

        return [
            'name' => $lando['name'],
            'webroot' => $webroot,
            'hosts' => array_values(array_unique($hostnames)),
            'phpVersion' => $phpVersion,
        ];
    }

    protected function createCaddyContents(array $info, string $directory): string
    {
        if (!isset($this->config['fpmProxies'][$info['phpVersion']])) {
            throw new \RuntimeException(sprintf(
                'Could not find an fpmProxy for php version "%s". Is it defined in your config?',
                $info['phpVersion']
            ));
        }

        $hosts = implode(', ', $info['hosts']);
        $public = sprintf('%s/%s', $directory, $info['webroot']);
        if (!file_exists($public)) {
            throw new \RuntimeException(sprintf(
                'Could not find webroot directory "%s". Does it exist?',
                $public
            ));
        }
        $upstream = $this->config['fpmProxies'][$info['phpVersion']];

        $caddy = <<<TXT
$hosts {
    tls internal
    root * $public
    php_fastcgi /* $upstream {
        env SERVER_PORT 443
        env LANDO 1
    }
    file_server
}
TXT;
        return $caddy;
    }

    protected function addSiteToCaddy(array $info): void
    {
        $caddyFile = sprintf('%s/.caddyFile.dev', $this->directory);

        // Write .caddyFile.dev to project
        file_put_contents(
            $caddyFile,
            $this->createCaddyContents(
                $info,
                $this->directory
            )
        );

        $this->importCaddyFile($caddyFile);
    }

    protected function importCaddyFile(string $caddyFile): void
    {
        $importLine = sprintf('import %s', $caddyFile);

        $exists = false;
        // Read all existing lines from the global caddyFile
        $imports = array_filter(array_map(
            static function ($line) use(&$exists, $importLine) {
                $line = trim($line);
                $exists = $exists || $line === $importLine;
                return $line;
            },
            file($this->config['caddyFile'], FILE_SKIP_EMPTY_LINES)
        ));

        // If the project's caddyFile is already imported, do an early return
        if ($exists) {
            return;
        }

        // Add the project's caddyFile to the global caddyFile
        $imports[] = $importLine;
        file_put_contents(
            $this->config['caddyFile'],
            implode("\n", $imports)
        );
    }

    protected function checkRootCert(OutputInterface $output): void
    {
        $certFile = sprintf('%s/pki/authorities/local/root.crt', $this->config['caddyDir']);
        if (!file_exists($certFile)) {
            $output->writeln('No Caddy root certificate found.');
            return;
        }

        $cmdOutput = null;
        $exitCode = null;
        exec('security find-certificate -a | grep -q "Caddy Local Authority"', $cmdOutput, $exitCode);

        if ($exitCode === 0) {
            return;
        }

        $addCmd = sprintf(
            'sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain "%s"',
            $certFile
        );

        // Todo: can Symfony apps call sudo and have the user enter the password?

        $output->writeln('Trust your self-signed ssl certificate so you can use HTTPS');
        $output->writeln('Run the following command:');
        $output->writeln($addCmd);
    }

    protected function createMysqlAndRedisDockerCompose(array $info)
    {
        $mysqlcnf = sprintf('%s/../config/mysql/mysql.cnf', __DIR__);

        $name = $info['name'];
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

    protected function startDockerServices(array $info): array
    {
        $compose = $this->createMysqlAndRedisDockerCompose($info);
        file_put_contents(
            sprintf('%s/.docker-compose.yml.dev', $this->directory),
            $compose
        );

        $cmd = [$this->config['dockerBin'], 'compose', '-p', $info['name'], '-f', '.docker-compose.yml.dev'];

        // docker-compose -p <projectName> -f .docker-compose.yml up -d
        $process = new Process(array_merge($cmd, ['up', '-d']), $this->directory);
        if ($process->run() !== 0) {
            throw new \RuntimeException(sprintf('Error while booting docker-compose services.: %s', $process->getOutput()));
        }

        $ports = [];
        foreach (['redis' => 6379, 'mysql' => 3306] as $service => $port) {
            // docker-compose -p <projectName> -f .docker-compose.yml port redis 6379
            $process = new Process(array_merge($cmd, ['port', $service, $port]), $this->directory);
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

    protected function replaceDotEnv(array $ports)
    {
        $dotEnvFile = sprintf('%s/.env', $this->directory);
        if (!file_exists($dotEnvFile)) {
            return;
        }

        $replaced = false;
        $lines = file($dotEnvFile);
        foreach ($lines as &$line) {
            if (strpos($line, 'DB_PORT=') === 0) {
                $line = sprintf('DB_PORT=%s', $ports['mysql']) . "\n";
                $replaced = true;
                continue;
            }
            if (strpos($line, 'REDIS_PORT=') === 0) {
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
