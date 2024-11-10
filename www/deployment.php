<?php

declare(strict_types=1);

ini_set('display_errors', 'off');
header('Content-Type: text/plain;charset=utf-8');

$root = dirname(__DIR__);

if (!isset($_GET['after'])) {
    http_response_code(400);
    echo 'Invalid request';
    exit();
}

echo "PURGE TEMP:\n";

$tempDir = $root . '/temp';
$temporaryTempDir = $tempDir . '_' . time();

echo "    - Renaming '{$tempDir}' to temporary name: '{$temporaryTempDir}'... ";
if (!rename($tempDir, $temporaryTempDir)) {
    exit("ERROR: " . (error_get_last()['message'] ?? 'Unknown error'));
}
echo "OK\n";

echo "    - Creating new tempDir at: '{$tempDir}'... ";
if (!mkdir($tempDir) || !is_dir($tempDir)) {
    exit("ERROR: " . (error_get_last()['message'] ?? 'Unknown error'));
}
echo "OK\n";

echo "    - Purging content of old tempDir at: '{$temporaryTempDir}': \n";
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($temporaryTempDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($files as $fileinfo) {
    if ($fileinfo->isDir()) {
        echo "        - Deleting subdir: '{$fileinfo->getRealPath()}'... ";
        if (!rmdir($fileinfo->getRealPath())) {
            exit("ERROR: " . (error_get_last()['message'] ?? 'Unknown error'));
        }
        echo "OK\n";
    } elseif (!unlink($fileinfo->getRealPath())) {
        exit(
            "ERROR: Unable to delete file: {$fileinfo->getRealPath()}, error: "
            . (error_get_last()['message'] ?? 'Unknown error')
        );
    }
}

echo "      OK\n";

echo "    - Deleting temporary dir at: '{$temporaryTempDir}'... ";
if (!rmdir($temporaryTempDir)) {
    exit("ERROR: " . (error_get_last()['message'] ?? 'Unknown error'));
}
echo "OK\n";

echo "DONE\n\n";

echo "DELETING ERROR ALERT MARKER:\n";
$markerFile = $root . '/log/email-sent';
echo "    - Deleting Error Alert Marker file at: '{$markerFile}'... ";
if (!is_file($markerFile)) {
    echo "OK (marker not exists)\n";
} elseif (unlink($markerFile)) {
    echo "OK\n";
} else {
    exit("ERROR: " . (error_get_last()['message'] ?? 'Unknown error'));
}
echo "DONE\n\n";

