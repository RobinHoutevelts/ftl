<?php

namespace App\Service;

use Symfony\Component\Console\Output\OutputInterface;

class Caddy
{
    private Config $config;
    private string $caddyFile;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
        $this->caddyFile = sprintf('%s/.caddyFile.dev', $config->projectDir);
    }

    public function restartCaddy(): void
    {
        $autosaveFile = sprintf('%s/autosave.json', $this->config->config['caddyDir']);
        if (file_exists($autosaveFile)) {
            unlink($autosaveFile);
        }

        exec(sprintf('rm -rf "%s/certificates/local"', $this->config->config['caddyDir']));
        exec($this->config->config['brewBin'] . ' services stop caddy');
        exec($this->config->config['brewBin'] . ' services start caddy');
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
        $importLine = sprintf('import %s', $this->caddyFile);

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

    public function checkRootCert(OutputInterface $output): void
    {
        $certFile = sprintf('%s/pki/authorities/local/root.crt', $this->config->config['caddyDir']);
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

}
