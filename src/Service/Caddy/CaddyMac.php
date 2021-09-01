<?php

namespace App\Service\Caddy;

use App\Service\Caddy;
use Symfony\Component\Console\Output\OutputInterface;

class CaddyMac extends Caddy
{
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

    protected function doRestartCaddy(): void
    {
        exec($this->config->config['brewBin'] . ' services stop caddy');
        exec($this->config->config['brewBin'] . ' services start caddy');
    }
}
