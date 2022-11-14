<?php

namespace App\Service;

use App\Service\Caddy\CaddyLinux;
use App\Service\Caddy\CaddyMac;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Caddy
{
    protected Config $config;
    protected string $caddyFile;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
        $this->caddyFile = sprintf('%s/.caddyFile.dev', $config->projectDir);
    }

    public static function create(Config $config, ?string $os = null)
    {
        $os = $os ?? PHP_OS_FAMILY;
        if ($os === 'Darwin') {
            return new CaddyMac($config);
        }
        if ($os === 'Linux') {
            return new CaddyLinux($config);
        }

        throw new \UnexpectedValueException('No Caddy implementation for Os "' . $os . '"');
    }

    public function restartCaddy(): void
    {
        $autosaveFile = sprintf('%s/autosave.json', $this->config->config['caddyDir']);
        if (file_exists($autosaveFile)) {
            unlink($autosaveFile);
        }

        $this->doRestartCaddy();
    }

    public function addSiteToCaddy(): void
    {
        // Write .caddyFile.dev to project
        file_put_contents(
            $this->caddyFile,
            $this->createCaddyContents()
        );

        $this->importCaddyFile();
    }

    public function createCaddyContents(): string
    {
        if (!isset($this->config->config['fpmProxies'][$this->config->phpVersion])) {
            throw new \RuntimeException(
                sprintf(
                    'Could not find an fpmProxy for php version "%s". Is it defined in your config?',
                    $this->config->phpVersion
                )
            );
        }

        $hosts = implode(', ', $this->config->hosts);
        $public = sprintf('%s/%s', $this->config->projectDir, $this->config->webroot);
        if (!file_exists($public)) {
            throw new \RuntimeException(
                sprintf(
                    'Could not find webroot directory "%s". Does it exist?',
                    $public
                )
            );
        }
        $upstream = $this->config->config['fpmProxies'][$this->config->phpVersion];

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

    public function importCaddyFile(): void
    {
        $importLine = $this->getImportLine();

        [$imports, $exists] = $this->parseGlobalCaddyFile($importLine);

        // If the project's caddyFile is already imported, do an early return
        if ($exists) {
            return;
        }

        // Add the project's caddyFile to the global caddyFile
        $imports[] = $importLine;
        file_put_contents(
            $this->config->config['caddyFile'],
            implode("\n", $imports)
        );
    }

    public function removeSiteFromCaddy(): void
    {
        $importLine = $this->getImportLine();

        [$imports, $exists] = $this->parseGlobalCaddyFile($importLine);

        // If the project's caddyFile is not imported, do an early return
        if (!$exists) {
            return;
        }

        // Remove the project's caddyFile from the global caddyFile
        $imports = array_diff($imports, [$importLine]);
        file_put_contents(
            $this->config->config['caddyFile'],
            implode("\n", $imports)
        );
    }

    public function checkRootCert(OutputInterface $output): void
    {
    }

    public function isSiteInCaddy(): bool
    {
        if (!file_exists($this->caddyFile)) {
            return false;
        }

        $importLine = $this->getImportLine();

        [$imports, $exists] = $this->parseGlobalCaddyFile($importLine);

        return $exists;
    }

    abstract protected function doRestartCaddy(): void;

    private function parseGlobalCaddyFile(string $importLine): array
    {
        $exists = false;
        // Read all existing lines from the global caddyFile
        $imports = array_filter(
            array_map(
                static function ($line) use (&$exists, $importLine) {
                    $line = trim($line);
                    $exists = $exists || $line === $importLine;
                    return $line;
                },
                file($this->config->config['caddyFile'], FILE_SKIP_EMPTY_LINES)
            )
        );

        // If the project's caddyFile is already imported, do an early return
        return [$imports, $exists];
    }

    private function getImportLine(): string
    {
        return sprintf('import %s', $this->caddyFile);
    }
}
