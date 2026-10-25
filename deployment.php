<?php

require __DIR__ . '/bin/check-assets.php';

$credentials = require __DIR__ . '/.deployment-credentials.php';

return [
    'Assets' => [
        'remote' => 'ftps://prince.bukajuv.net/www',
        'local' => __DIR__,
        'user' => $credentials['user'],
        'password' => $credentials['password'],
        'test' => false,
        'include' => ['/www/assets'],
        'allowDelete' => false,
        'deploymentFile' => '.htdeployment-assets',
    ],
    'Production' => [
        'remote' => 'ftps://prince.bukajuv.net/www',
        'local' => __DIR__,
        'user' => $credentials['user'],
        'password' => $credentials['password'],
        'test' => false,
        'include' => [
            '/app',
            '/www',
            '/vendor',
        ],
        'ignore' => [
            '/www/.well-known',
            '/www/assets',
        ],
        'allowDelete' => true,
        'after' => ['https://ikofein.cz/deployment.php?after'],
    ],
    'tempDir' => __DIR__ . '/temp',
    'colors' => true,
    'log' => __DIR__ . '/log/deployment.log'
];
