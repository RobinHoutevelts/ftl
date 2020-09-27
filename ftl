#!/usr/local/opt/php@7.4/bin/php
<?php
require __DIR__.'/vendor/autoload.php';

use App\Commands\CompletionCommand;
use Symfony\Component\Console\Application;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(__DIR__));
$loader->load('src/services.yml');

$container->addCompilerPass(new AddConsoleCommandPass());
$container->compile(false);

$application = new Application('FTL', '0.0.1');
$application->setCommandLoader($container->get('console.command_loader'));
$application->add(
    (new CompletionCommand())
);
$application->run();
