#!/opt/homebrew/opt/php@8.1/bin/php
<?php
require __DIR__ . '/vendor/autoload.php';

use App\Commands\CompletionCommand;
use Symfony\Component\Console\Application;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(__DIR__));
$loader->load('src/services.yml');
if (file_exists($customConfig = __DIR__ . '/config.yml')) {
    $loader->load($customConfig);
}

$container->addCompilerPass(new AddConsoleCommandPass());
$container->addCompilerPass(
    new class implements CompilerPassInterface {
        public function process(ContainerBuilder $container): void
        {
            if ($container->hasParameter('config')) {
                $config = $container->getParameter('config');
                if (isset($config['caddyDir'])) {
                    $config['caddyDir'] = str_replace(
                        '$HOME',
                        $_SERVER['HOME'],
                        $config['caddyDir']
                    );
                    $container->setParameter('config', $config);
                }
            }
        }
    }
);
$container->compile(false);

$application = new Application('FTL', '0.0.1');
$application->setCommandLoader($container->get('console.command_loader'));
$application->add(
    (new CompletionCommand())
);
$application->run();
