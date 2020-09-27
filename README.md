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

Just drop a `.lndo.yml` file in the root of your project and run `ftl start`

```yaml
name: project
config:
    webroot: public # optional
    php: '8.0'
```

This will configure Caddy to serve https://project.lndo.site, https://admin-project.lndo.site and forward requests to php-fpm. A MariaDB and Redis docker container will start as well.

The MariaDB container is configured as such:

| name | value |
| --- | --- |
username | `project`
password | `project`
database | `project`
hostname | `127.0.0.1`
port | random

To connect to the MariaDB server you will need to know the port. You can use `ftl status` to see it.

If your project contains a `.env` file, the `DB_PORT` value will get updated automatically. The same applies for the `REDIS_PORT` value.

### Commands

 command | description
---- | ----
`ftl start`      | Start your development environment. Automatically changes `DB_PORT` and `REDIS_PORT` in your `.env`
`ftl stop`       | Stop/pause your development environment
`ftl destroy`    | Destroy and delete your development environment ( removes database entirely)
`ftl status`     | Show status of your development environment. Shows your hostnames and ports of database and redis.
`ftl db`         | Reads your `.env` and opens your database ( in sequel pro if installed )
`ftl db-create`  | Create an extra database
`ftl db-export`  | Export database
`ftl db-import`  | Import an existing database

#### Weird shit

The Caddy SSL cert is only valid for a day. So you have to run `ftl start` daily or you'll get an outdated certificate error. 

## Still wanna use it?

Aight! You won't regret it!

## Install

### Docker

FTL uses Docker to create a MariaDB database and Redis per project. You can download Docker for Mac at https://hub.docker.com/editions/community/docker-ce-desktop-mac which will install `docker` and `docker-compose`.

### Caddy 

FTL uses `caddy` as web server. So let's install it. We also create an empty Caddyfile at the location Caddy expects it to exist.

```sh
brew install caddy
touch /usr/local/etc/Caddyfile
```

### drush

If you're gonna drush on your machine you'll need the `mysql-client` package. We also require it to create database exports with `ftl db-export`

```sh
brew install mysql-client
echo 'export PATH="/usr/local/opt/mysql-client/bin:$PATH"' >> ~/.zshrc
```

### php

Let's install some php on your machine

```sh
brew tap shivammathur/php

brew install shivammathur/php/php@8.0
brew install shivammathur/php/php@7.4
brew install shivammathur/php/php@7.3
brew install shivammathur/php/php@7.2
brew install shivammathur/php/php@7.1
brew install shivammathur/php/php@7.0
```

Then install some much-needed modules like xdebug and redis.

```sh
/usr/local/opt/php@8.0/bin/pecl install redis xdebug-3.0.3

/usr/local/opt/php@7.4/bin/pecl install redis xdebug-3.0.3

/usr/local/opt/php@7.3/bin/pecl install redis xdebug-3.0.3

/usr/local/opt/php@7.2/bin/pecl install redis xdebug-2.9.8

/usr/local/opt/php@7.1/bin/pecl install redis xdebug-2.9.8

/usr/local/opt/php@7.0/bin/pecl install redis xdebug-2.8.1
```

Let's make sure your default php version is 8.0

```sh
brew link --force php@8.0
```

### Configure php-packages

Now we need to configure the shit out of those things

```sh
sudo nano /usr/local/etc/php/8.0/php.ini
```

Remove the two added extensions ( we'll add them back later )

And replace it with some configured shit.

This will make sure you have an xdebug that will connect to your host.

```diff
- zend_extension="xdebug.so"
- extension="redis.so"
+ [xdebug]
+ zend_extension="xdebug.so"
+ xdebug.mode=develop,debug
+ xdebug.start_with_request=yes
+ xdebug.client_host=localhost
+ xdebug.client_port=9000
+ xdebug.log_level=0
+ 
+ [redis]
+ extension="redis.so"
```

<details><summary>For xdebug 2.x (php 7.0/7.1/7.2)</summary>

```diff
- zend_extension="xdebug.so"
- extension="redis.so"
+ [xdebug]
+ zend_extension="xdebug.so"
+ xdebug.remote_enable=1
+ xdebug.remote_autostart=1
+ xdebug.remote_host=localhost
+ xdebug.remote_port=9000
+ 
+ [redis]
+ extension="redis.so"
```

</details>

Also increase your max memory from 128M to 1G by looking for `memory_limit` and replacing it.

```diff
- memory_limit = 128M
+ memory_limit = 1G
```

*Perform the same steps also for php 7.0, 7.1, 7.2, 7.3 and 7.4 ( they each have their own php.ini file)*

### Configure php-fpm

We need to define a port we'll listen on.

```sh
sudo nano /usr/local/etc/php/8.0/php-fpm.d/www.conf
````

Replace the default listing port from `9000` to `9180`

```diff
- ;listen = 127.0.0.1:9000
+ listen = 127.0.0.1:9180
```

Do the same for php 7.0, 7.1, 7.2, 7.3, 7.4 but use ports `9170`, `9171`, `9172`, `9173`, `9174`

### Restart the services

**Don't** use `sudo` to run these services.
Your php-processes will run as `root` and you'll get random permission errors when developing.

```sh
brew services restart php
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
.lando.yml
.caddyFile.dev
.docker-compose.yml.dev
```

### Configure FTL

You can configure FTL in the `src/services.yml` file.

## ProTip

### Alias `php` binary

Alias `php` to a script that loads correct versions based on your `.lando.yml` file.

I have the following line in my `~/.zshrc` file

```sh
export PATH=$HOME/bin:$PATH
```

It makes sure all executables are first searched for in my `~/bin` directory. There I have a `~/bin/php` file that calls the correct `php` version based on the `.lando.yml` file in my project git root.

```sh
#!/usr/bin/env bash
DEFAULT_PHP_VERSION="7.4"

ROOTDIR=$(pwd)

if git rev-parse --git-dir > /dev/null 2>&1; then
  ROOTDIR=$(git rev-parse --show-toplevel)
fi

PHP_VERSION="$DEFAULT_PHP_VERSION"
if [[ -f ".lando.yml" ]]; then
    PHP_VERSION=$(cat ".lando.yml" | grep "php:" | cut -d ":" -f2)
elif [[ -f "$ROOTDIR/.lando.yml" ]]; then
    PHP_VERSION=$(cat "$ROOTDIR/.lando.yml" | grep "php:" | cut -d ":" -f2)
fi

PHP_VERSION=$(echo "$PHP_VERSION" | sed 's/[^0-9]*//g')
PHP_VERSION="$(echo "$PHP_VERSION" | cut -c1,1).$(echo "$PHP_VERSION" | cut -c2,2)"

if [[ ! -f "/usr/local/opt/php@$PHP_VERSION/bin/php" ]]; then
   PHP_VERSION=${DEFAULT_PHP_VERSION}
fi

PHP_BIN="/usr/local/opt/php@$PHP_VERSION/bin/php"

${PHP_BIN} "$@"
exit $?
```

Make sure to make it executable

```sh
chmod +x ~/bin/php
```

### Alias `ftl` binary

I do the same for the ftl binary. Sometimes I got an error some shared libaries aren't loaded when using php. This is because we have multiple php versions installed. 

```
dyld: Library not loaded: /usr/local/opt/icu4c/lib/libicuio.67.dylib
  Referenced from: /usr/local/opt/php@7.4/bin/php
  Reason: image not found
```

I need to make sure icu4c version 67 is loaded. If your error says a different version, use `brew list --versions icu4c` to see what version you can hardcode.

Hardcode the version in `~/bin/ftl`. Make sure you also change the path to ftl. I have mine at `~/Projects/ftl`

```bash
#!/usr/bin/env bash

brew switch icu4c 67.1  > /dev/null 2>&1

$HOME/Projects/ftl/ftl "$@"
exit $?
```

Make sure to make it executable

```sh
chmod +x ~/bin/ftl
```
