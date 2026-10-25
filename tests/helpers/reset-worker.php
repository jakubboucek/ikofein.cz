<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$container = App\Bootstrap::boot()->createContainer();
$reset = $container->getByType(App\Model\PasswordReset::class);
$token = trim(stream_get_contents(STDIN));
try {
    $reset->complete($token, 'Concurrent-reset-password!', $argv[1]);
    echo 'accepted';
} catch (App\Model\InvalidPasswordResetException) {
    echo 'rejected';
}
