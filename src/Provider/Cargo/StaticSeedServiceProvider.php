<?php

namespace Krak\StaticSeed\Provider\Cargo;

use Krak\Cargo;
use Krak\StaticSeed;

class StaticSeedServiceProvider implements Cargo\ServiceProvider
{
    public function register(Cargo\Container $c) {
        $c[StaticSeed\Export::class] = function($c) {
            return new StaticSeed\Export($c['Doctrine\DBAL\Connection']);
        };
        $c[StaticSeed\Import::class] = function($c) {
            return new StaticSeed\Import(
                $c['Doctrine\DBAL\Connection']
            );
        };
    }
}
