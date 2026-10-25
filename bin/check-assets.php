<?php

declare(strict_types=1);

$assetsDirectory = __DIR__ . '/../www/assets';
$manifestFile = $assetsDirectory . '/.vite/manifest.json';
if (!is_file($manifestFile) || is_file($assetsDirectory . '/.vite/nette.json')) {
    throw new RuntimeException('Stop the Vite dev server and run npm run build before deployment.');
}

$manifest = json_decode(file_get_contents($manifestFile), true, flags: JSON_THROW_ON_ERROR);
foreach (['front.js', 'admin.js'] as $entry) {
    if (empty($manifest[$entry]['isEntry'])) {
        throw new RuntimeException("Missing Vite entry: $entry");
    }
}

foreach ($manifest as $chunk) {
    foreach ([$chunk['file'], ...($chunk['css'] ?? []), ...($chunk['assets'] ?? [])] as $file) {
        if (str_contains($file, '..') || !is_file($assetsDirectory . '/' . $file)) {
            throw new RuntimeException("Missing or invalid Vite asset: $file");
        }
    }
    foreach ([...($chunk['imports'] ?? []), ...($chunk['dynamicImports'] ?? [])] as $import) {
        if (!isset($manifest[$import])) {
            throw new RuntimeException("Missing Vite manifest dependency: $import");
        }
    }
}
