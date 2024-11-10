<?php

declare(strict_types=1);

namespace App\Model;

class WebDir
{
    private readonly string $wwwDir;


    public function __construct(string $wwwDir)
    {
        $this->wwwDir = $wwwDir;
    }


    public function getPath(string $suffix = ''): string
    {
        return $this->wwwDir . ($suffix !== '' ? DIRECTORY_SEPARATOR . $suffix : '');
    }
}
