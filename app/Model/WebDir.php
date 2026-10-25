<?php

declare(strict_types=1);

namespace App\Model;

readonly class WebDir
{
    public function __construct(
        private string $wwwDir,
    ) {
    }


    public function getPath(string $suffix = ''): string
    {
        return $this->wwwDir . ($suffix !== '' ? DIRECTORY_SEPARATOR . $suffix : '');
    }
}
