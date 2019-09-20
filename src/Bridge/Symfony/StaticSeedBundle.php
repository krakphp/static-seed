<?php

namespace Krak\StaticSeed\Bridge\Symfony;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class StaticSeedBundle extends Bundle
{
    public function getContainerExtension() {
        return new class() extends Extension {
            public function getAlias() {
                return 'static_seed';
            }

            public function load(array $configs, ContainerBuilder $container) {
                $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/Resources/config'));
                $loader->load('services.php');
            }
        };
    }
}
