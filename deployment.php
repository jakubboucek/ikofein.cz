<?php

$credentials = require __DIR__ . '/.deployment-credentials.php';

return [
    'Production' => [
        'remote' => 'ftps://prince.bukajuv.net/www',
        'local' => __DIR__,
        'user' => $credentials['user'],
        'password' => $credentials['password'],
        'test' => false,
        'include' => [
            '/app',
            '/libs',
            '/www',
            '/vendor',
        ],
        'ignore' => [
            '/www/.well-known',
        ],
        'allowDelete' => true,
        'after' => ['https://ikofein.cz/deployment.php?after'],
    ],
    'tempDir' => __DIR__ . '/temp',
    'colors' => true,
    'log' => __DIR__ . '/log/deployment.log'
];
