<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class Config
{
    public const LANDO_FILENAME = '.ftl.yml';

    // Todo: readonly in php 8.1
    public string $name;
    public string $webroot;
    public array $hosts;
    public string $phpVersion;
    public string $projectDir;
    public array $lando;
    public array $config;

    public function __construct(
        string $name,
        string $webroot,
        array $hosts,
        string $phpVersion,
        string $projectDir,
        array $lando,
        array $config
    ) {
        $this->name = $name;
        $this->webroot = $webroot;
        $this->hosts = $hosts;
        $this->phpVersion = $phpVersion;
        $this->projectDir = $projectDir;
        $this->lando = $lando;
        $this->config = $config;
    }

    public static function createConfig(array $ftlConfig): self
    {
        $directory = getcwd();
        if (!file_exists('.ftl.yml')) {
            $directory = exec('git rev-parse --show-toplevel 2> /dev/null') ?: '';
            if (!$directory) {
                throw new \RuntimeException(
                    'Can\'t find root directory of project. Did you run "git init"?'
                );
            }
        }

        $landoFile = sprintf('%s/%s', $directory, static::LANDO_FILENAME);

        if (!file_exists($landoFile)) {
            throw new \RuntimeException(
                'Can\'t find .ftl.yml file in root directory of project.'
            );
        }

        $lando = Yaml::parse(file_get_contents($landoFile));

        if (empty($lando)) {
            throw new \RuntimeException('Could not parse .ftl.yml content. Is it valid yaml?');
        }
        if (empty($lando['name'])) {
            throw new \RuntimeException('.ftl.yml does not contain the name of the project.');
        }

        $phpVersion = $lando['config']['php'] ?? $ftlConfig['defaultPhpVersion'];
        $webroot = $lando['config']['webroot'] ?? $ftlConfig['defaultWebroot'];
        if (isset($ftlConfig['hostname'])) {
            error_log('The "hostname" config option is deprecated. Please use "hostnames" instead.', E_USER_DEPRECATED);
            $ftlConfig['hostnames'] = array_merge(
                $ftlConfig['hostnames'] ?? [],
                [$ftlConfig['hostname']]
            );
        }

        $ftlConfig['hostnames'] = array_unique($ftlConfig['hostnames'] ?? []);
        if (empty($ftlConfig['hostnames'])) {
            throw new \RuntimeException('No "hostnames" defined in config.');
        }

        $hostnames = [];
        foreach ($ftlConfig['hostnames'] as $hostname) {
            // foobar.lndo.site
            $hostnames[] = sprintf('%s.%s', $lando['name'], $hostname);
            // admin-foobar.lndo.site
            $hostnames[] = sprintf('admin-%s.%s', $lando['name'], $hostname);
        }

        if (!empty($lando['proxy']['appserver_nginx'])) {
            $hostnames = array_merge($hostnames, $lando['proxy']['appserver_nginx']);
        }

        return new static(
            $lando['name'],
            $webroot,
            array_values(array_unique($hostnames)),
            $phpVersion,
            $directory,
            $lando,
            $ftlConfig,
        );
    }

    public function isLando(): bool
    {
        return $this->lando['is_lando'] ?? false;
    }

}
