<?php

declare(strict_types=1);

namespace App;

use JakubBoucek\ComposerVendorChecker\Checker;
use Nette\Configurator;
use Nette\InvalidArgumentException;
use Nette\NotSupportedException;
use RuntimeException;
use Tracy\Debugger;


class Bootstrap
{
    /**
     * @return Configurator
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     * @throws RuntimeException
     */
    public static function boot(): Configurator
    {
        $configurator = new Configurator;

        $configurator->setDebugMode((int)getenv() === 1 ? true : null);
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


    /**
     * @throws RuntimeException
     */
    protected static function checkVendorConsistency(): void
    {
        if (Debugger::$productionMode === false && class_exists(Checker::class)) {
            (new Checker(__DIR__ . '/..'))->setStrictReqs(false)->validate();
        }

    }
}
