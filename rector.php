<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ObjectExplicitBoolCompareRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/www',
    ])
    ->withPhpSets(php85: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withSkip([
        LocallyCalledStaticMethodToNonStaticRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        ObjectExplicitBoolCompareRector::class,
    ])
    ->withIndent(indentChar: ' ', indentSize: 4);
