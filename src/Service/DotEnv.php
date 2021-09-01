<?php

namespace App\Service;

use Symfony\Component\Dotenv\Dotenv as SymfonyDotenv;

class DotEnv
{
    private Config $config;

    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    public function readEnv(): array
    {
        $dotEnvFile = sprintf('%s/.env', $this->config->projectDir);
        if (!file_exists($dotEnvFile)) {
            throw new \RuntimeException(sprintf('Could not find your .env file at "%s"', $dotEnvFile));
        }

        return (new SymfonyDotenv())->parse(file_get_contents($dotEnvFile));
    }
}
