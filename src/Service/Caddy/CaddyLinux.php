<?php

namespace App\Service\Caddy;

use App\Service\Caddy;

class CaddyLinux extends Caddy
{
    protected function doRestartCaddy(): void
    {
        // Todo: not like this :p
        exec('caddy stop');
        exec('caddy run --config ' . $this->config->config['caddyFile']);
    }
}
