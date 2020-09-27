<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class StatusCommand extends Command
{
    protected static $defaultName = 'status';
    protected array $config;
    protected string $directory;

    public function __construct(array $ftlConfig, string $name = null)
    {
        parent::__construct($name);
        $this->config = $ftlConfig;
        $this->config['caddyDir'] = str_replace('$HOME', $_SERVER['HOME'], $this->config['caddyDir']);
        $this->setDescription('Show status of your development environment');
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
        $isInCaddy = $this->isSiteInCaddy();

        $output->writeln('Is added to Caddy: ' . ($isInCaddy ? 'true' : 'false'));
        $output->writeln('');

        $output->writeln('Hostnames: ');
        foreach ($info['hosts'] as $hostname) {
            $output->writeln(sprintf('- https://%s', $hostname));
        }

        $output->writeln('');
        $output->writeln('Docker services: ');
        $serviceStatus = $this->getServiceStatus($info['name']);
        $output->write($serviceStatus?: 'No services running');

        $ports = [];
        try {
            $ports = $this->getPortForwards($info['name']);
        } catch (\RuntimeException $e) {
            // noop
        }
        $ports += ['redis' => 'na', 'mysql' => 'na'];

        $output->writeln('');
        $output->writeln('Status of port forwards: ');
        foreach ($ports as $service => $port) {
            $output->writeln(sprintf('%s => %s', $service, $port));
        }

        $output->writeln('');
        $this->checkRootCert($output);
        return 0;
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

    protected function isSiteInCaddy(): bool
    {
        $caddyFile = sprintf('%s/.caddyFile.dev', $this->directory);

        if (!file_exists($caddyFile)) {
            return false;
        }

        $importLine = sprintf('import %s', $caddyFile);

        // Read all existing lines from the global caddyFile
        $imports = file($this->config['caddyFile']);

        // If the project's caddyFile is already imported, do an early return
        return in_array($importLine, $imports, true);
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

    protected function getServiceStatus($name): string
    {
        $cmd = [$this->config['dockerComposeBin'], '-p', $name, '-f', '.docker-compose.yml.dev'];
        $process = new Process(array_merge($cmd, ['ps']), $this->directory);
        $process->run();
        return $process->getOutput();
    }

    protected function getPortForwards($name): array
    {
        $cmd = [$this->config['dockerComposeBin'], '-p', $name, '-f', '.docker-compose.yml.dev'];
        $ports = [];
        foreach (['redis' => 6379, 'mysql' => 3306] as $service => $port) {
            // docker-compose -p <projectName> -f .docker-compose.yml.dev port redis 6379
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
}
