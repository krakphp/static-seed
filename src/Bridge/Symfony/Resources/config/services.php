<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Krak\StaticSeed\Bridge\Symfony\Command\StaticSeedImportCommand;

return static function(ContainerConfigurator $configurator) {
    $container = $configurator->services()->defaults()
            ->private()->autowire()->autoconfigure();
    $configurator->parameters()
        ->set('krak.static_seed.import_dir', '%kernel.project_dir%/config/static-seeds');

    $container->set(StaticSeedImportCommand::class)
        ->arg('$seedDir', '%krak.static_seed.import_dir%');
};

