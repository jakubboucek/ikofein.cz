<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ObjectExplicitBoolCompareRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/www',
    ])
    ->withPhpSets(php85: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withSkip([
        RestoreDefaultNullToNullableTypePropertyRector::class => [__DIR__ . '/app/Entity'],
        LocallyCalledStaticMethodToNonStaticRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        ObjectExplicitBoolCompareRector::class,
    ])
    ->withIndent(indentChar: ' ', indentSize: 4);
