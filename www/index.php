<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

/** @noinspection PhpUnhandledExceptionInspection */
App\Booting::boot()
    ->createContainer()
    ->getByType(Nette\Application\Application::class)
    ->run();
