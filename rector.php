<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/libs',
        __DIR__ . '/www',
    ])
    ->withPhpSets()
    //->withImportNames()
    ->withSkip([
        ClassPropertyAssignToConstructorPromotionRector::class,
    ])
    ->withTypeCoverageLevel(0);
