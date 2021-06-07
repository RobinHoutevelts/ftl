<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class StopCommand extends Command
{
    protected static $defaultName = 'stop';
    protected array $config;
    protected string $directory;

    public function __construct(array $ftlConfig, string $name = null)
    {
        parent::__construct($name);
        $this->config = $ftlConfig;
        $this->config['caddyDir'] = str_replace('$HOME', $_SERVER['HOME'], $this->config['caddyDir']);
        $this->setDescription('Stop/pause your development environment');
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
        $this->removeSiteFromCaddy($info);
        $this->restartCaddy();

        $this->stopDockerServices($info);
        return 0;
    }

    protected function restartCaddy(): void
    {
        $autosaveFile = sprintf('%s/autosave.json', $this->config['caddyDir']);
        if (file_exists($autosaveFile)) {
            unlink($autosaveFile);
        }

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

    protected function removeSiteFromCaddy(array $info): void
    {
        $caddyFile = sprintf('%s/.caddyFile.dev', $this->directory);
        $importLine = sprintf('import %s', $caddyFile);

        // Read all existing lines from the global caddyFile
        $imports = file($this->config['caddyFile']);

        // If the project's caddyFile isn't imported, do an early return
        if (!in_array($importLine, $imports, true)) {
            return;
        }

        // Remove the project's caddyFile from the global caddyFile
        $imports = array_diff($imports, [$importLine]);
        file_put_contents(
            $this->config['caddyFile'],
            implode("\n", $imports)
        );
    }

    protected function stopDockerServices(array $info): void
    {
        $cmd = [$this->config['dockerBin'], 'compose', '-p', $info['name'], '-f', '.docker-compose.yml.dev'];

        // docker-compose -p <projectName> -f .docker-compose.yml stop
        $process = new Process(array_merge($cmd, ['stop']), $this->directory);
        $process->run();
    }
}
