<?php

declare(strict_types=1);

namespace App\Model;

class WebDir
{
    public function __construct(
        private readonly string $wwwDir,
    ) {
    }


    public function getPath(string $suffix = ''): string
    {
        return $this->wwwDir . ($suffix !== '' ? DIRECTORY_SEPARATOR . $suffix : '');
    }
}
