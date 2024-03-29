# FTL: Faster than Lando Mac local development environment

If you also fucking hate the slow-as-fuck Docker-for-Mac experience you'll like this one:

FTL is a combination of a Caddy webserver and php-fpm installed with `brew` with a very simple docker-compose wrapper to run Redis and MariaDB.

Of course there are a few downsides:

- No php patch versions
- You have to manually install php on your mac
- You have to manually upgrade/maintain php on your mac

The upside is:

- Fast as fuck. It's just php running natively on your mac.

----

## Usage

Just drop a `.ftl.yml` file in the root of your project and run `ftl start`

```yaml
name: project
config:
    webroot: public # optional
    php: '8.0'
```

This will configure Caddy to serve https://project.lndo.site, https://admin-project.lndo.site and forward requests to php-fpm. A MariaDB and Redis docker container will start as well.

The MariaDB container is configured as such:

| name     | value       |
|----------|-------------|
| username | `project`   |
| password | `project`   |
| database | `project`   |
| hostname | `127.0.0.1` |
| port     | random      |

To connect to the MariaDB server you will need to know the port. You can use `ftl status` to see it.

If your project contains a `.env` file, the `DB_PORT` value will get updated automatically. The same applies for the `REDIS_PORT` value.

### Commands

| command         | description                                                                                             |
|-----------------|---------------------------------------------------------------------------------------------------------|
| `ftl start`     | Start your development environment.<br/>Automatically changes `DB_PORT` and `REDIS_PORT` in your `.env` |
| `ftl stop`      | Stop/pause your development environment                                                                 |
| `ftl destroy`   | Destroy and delete your development environment ( destroys database )                                   |
| `ftl status`    | Show status of your development environment.<br/>Shows your hostnames and ports of database and redis.  |
| `ftl db`        | Reads your `.env` and opens your database ( in sequel pro if installed )                                |
| `ftl db-create` | Create an extra database                                                                                |
| `ftl db-export` | Export database                                                                                         |
| `ftl db-import` | Import an existing database                                                                             |

## Still wanna use it?

Aight! You won't regret it!

## Install

### Docker

FTL uses Docker to create a MariaDB database and Redis per project. You can download Docker for Mac at https://hub.docker.com/editions/community/docker-ce-desktop-mac which will install `docker`.

### Caddy 

FTL uses `caddy` as web server. So let's install it. We also create an empty Caddyfile at the location Caddy expects it to exist.

```sh
brew install caddy
touch /opt/homebrew/etc/Caddyfile
caddy trust
```

### drush

If you're gonna drush on your machine you'll need the `mysql-client` package. We also require it to create database exports with `ftl db-export`

```sh
brew install mysql-client
echo 'export PATH="/opt/homebrew/opt/mysql-client/bin:$PATH"' >> ~/.zshrc
```

### php

Let's install some php on your machine

```sh
brew tap shivammathur/php

brew install shivammathur/php/php@8.3
brew install shivammathur/php/php@8.2
brew install shivammathur/php/php@8.1
brew install shivammathur/php/php@8.0
brew install shivammathur/php/php@7.4
brew install shivammathur/php/php@7.3
brew install shivammathur/php/php@7.2
brew install shivammathur/php/php@7.1
brew install shivammathur/php/php@7.0
```

Then install some much-needed modules like xdebug and redis.

```sh
/opt/homebrew/opt/php@8.3/bin/pecl install redis xdebug-3.3.1

/opt/homebrew/opt/php@8.2/bin/pecl install redis xdebug-3.3.1

/opt/homebrew/opt/php@8.1/bin/pecl install redis xdebug-3.3.1

/opt/homebrew/opt/php@8.0/bin/pecl install redis xdebug-3.1.2

/opt/homebrew/opt/php@7.4/bin/pecl install redis xdebug-3.1.2

/opt/homebrew/opt/php@7.3/bin/pecl install redis xdebug-3.1.2

/opt/homebrew/opt/php@7.2/bin/pecl install redis xdebug-2.9.8

/opt/homebrew/opt/php@7.1/bin/pecl install redis xdebug-2.9.8

/opt/homebrew/opt/php@7.0/bin/pecl install redis xdebug-2.8.1
```

### Configure php-packages

Now we need to configure those things

```sh
sudo nano /opt/homebrew/etc/php/8.3/php.ini
```

Remove the two added extensions ( we'll add them back later )

```diff
- zend_extension="xdebug.so"
- extension="redis.so"
```

And replace it with some configured shit.

This will make sure you have an xdebug that will connect to your host.

```
[xdebug]
zend_extension="xdebug.so"
xdebug.mode=develop,debug
xdebug.start_with_request=yes
xdebug.client_host=localhost
xdebug.client_port=9003
xdebug.log_level=0

[redis]
extension="redis.so"
```

<details><summary>For xdebug 2.x (php 7.0/7.1/7.2)</summary>

```diff
[xdebug]
zend_extension="xdebug.so"
xdebug.remote_enable=1
xdebug.remote_autostart=1
xdebug.remote_host=localhost
xdebug.remote_port=9000

[redis]
extension="redis.so"
```

</details>

Also increase your max memory from 128M to 1G by looking for `memory_limit` and replacing it.

```diff
- memory_limit = 128M
+ memory_limit = 1G
```

*Perform the same steps also for php 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.1 and 8.2 ( they each have their own php.ini file)*

### Configure php-fpm

We need to define a port we'll listen on.

```sh
sudo nano /opt/homebrew/etc/php/8.3/php-fpm.d/www.conf
````

Replace the default listing port from `9000` to `9183`

```diff
- ;listen = 127.0.0.1:9000
+ listen = 127.0.0.1:9183
```

Do the same for php 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.2 but use ports `9170`, `9171`, `9172`, `9173`, `9174`, `9180`, `9181`, `9182`

### Restart the services

**Don't** use `sudo` to run these services.
Your php-processes will run as `root` and you'll get random permission errors when developing.

```sh
brew services restart php
brew services restart php@8.2
brew services restart php@8.1
brew services restart php@8.0
brew services restart php@7.4
brew services restart php@7.3
brew services restart php@7.2
brew services restart php@7.1
brew services restart php@7.0
```

## Install FTL

When you finally have `docker`, `caddy` and `php` installed we can let them work together by installing and configuring `FTL`.

Clone this repository somewhere on your mac. We aren't going to delete it so pick a good spot. After cloning go to the project and run `composer install`

```bash
git clone https://github.com/RobinHoutevelts/ftl.git
cd ftl
composer install
```

FTL creates a `.caddyFile.dev` and a `.docker-compose.yml.dev` in the root of your projects. In a later version I plan to move it elsewhere but for now you'll need to add it to your global gitignore file.

First create a global gitignore file

```bash
touch ~/.gitignore_global
git config --global core.excludesfile ~/.gitignore_global
```

Then add the following to the gitignore file:

```
.ftl.yml
.caddyFile.dev
.docker-compose.yml.dev
```

### Configure FTL

You can configure FTL by copying the `src/services.yml` file to `config.yml` in the root directory.

```bash
cp src/services.yml ./config.yml
```

## ProTip

### Alias `php` binary

Alias `php` to a script that loads correct versions based on your `.ftl.yml` file.

I have the following lines in my `~/.zshrc` file

```sh
export PATH=$HOME/bin:$PATH
export PATH=$HOME/.composer/vendor/bin:$PATH
```

It makes sure all executables are first searched for in my `~/bin` directory. There I have a `~/bin/php` file that calls the correct `php` version based on the `.ftl.yml` file in my project git root.

```sh
#!/usr/bin/env bash
DEFAULT_PHP_VERSION="8.1"

ROOTDIR=$(pwd)

if git rev-parse --git-dir > /dev/null 2>&1; then
  ROOTDIR=$(git rev-parse --show-toplevel)
fi

PHP_VERSION="$DEFAULT_PHP_VERSION"
if [[ -f ".ftl.yml" ]]; then
    PHP_VERSION=$(cat ".ftl.yml" | grep "php:" | cut -d ":" -f2)
elif [[ -f "$ROOTDIR/.ftl.yml" ]]; then
    PHP_VERSION=$(cat "$ROOTDIR/.ftl.yml" | grep "php:" | cut -d ":" -f2)
fi

PHP_VERSION=$(echo "$PHP_VERSION" | sed 's/[^0-9]*//g')
PHP_VERSION="$(echo "$PHP_VERSION" | cut -c1,1).$(echo "$PHP_VERSION" | cut -c2,2)"

if [[ ! -f "/opt/homebrew/opt/php@$PHP_VERSION/bin/php" ]]; then
   PHP_VERSION=${DEFAULT_PHP_VERSION}
fi

PHP_BIN="/opt/homebrew/opt/php@$PHP_VERSION/bin/php"

${PHP_BIN} "$@"
exit $?
```

Make sure to make it executable

```sh
chmod +x ~/bin/php
```

### Xdebug

Xdebug is preconfigured. Configure PHPStorm to use it.
