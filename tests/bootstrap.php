<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$container = App\Bootstrap::boot()->createContainer();
Tester\Environment::setup();
$directory = __DIR__ . '/../temp/tester';
if (!is_dir($directory)) {
    mkdir($directory, 0777, true);
}
Tester\Environment::lock('database', $directory);

return $container;
