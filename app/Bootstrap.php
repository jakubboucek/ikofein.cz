<?php

declare(strict_types=1);

namespace App;

use JakubBoucek\ComposerConsistency\ComposerConsistency;
use Nette\Configurator;
use Nette\InvalidArgumentException;
use Nette\NotSupportedException;
use Redbitcz\DebugMode\Detector;
use RuntimeException;
use Tracy\Debugger;


class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator;

        $configurator->setDebugMode(Detector::detect());
        $configurator->enableDebugger(__DIR__ . '/../log', 'pan@jakubboucek.cz');

        self::checkVendorConsistency();

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


    protected static function checkVendorConsistency(): void
    {
        if (Debugger::$productionMode === false && class_exists(ComposerConsistency::class)) {
            ComposerConsistency::rootDir(__DIR__ . '/..')->validate();
        }
    }
}
