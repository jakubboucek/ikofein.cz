<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;
use Redbitcz\DebugMode\Detector;

class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator;

        $configurator->setDebugMode(Detector::detect());
        $configurator->enableTracy(__DIR__ . '/../log', 'pan@jakubboucek.cz');

        $configurator->setTimeZone('Europe/Prague');
        $configurator->setTempDirectory(__DIR__ . '/../temp');

        $configurator->createRobotLoader()
            ->addDirectory(__DIR__)
            ->addDirectory(__DIR__ . '/../libs')
            ->register();

        $configurator->addConfig(__DIR__ . '/Config/config.neon');
        $configurator->addConfig(__DIR__ . '/../local/Config/config.neon');

        return $configurator;
    }
}
