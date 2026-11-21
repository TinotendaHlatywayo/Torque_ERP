<?php

declare(strict_types=1);

const MANIFEST_FORMAT = 'torqueerp-backup-manifest/v1';

function validateManifest(array $manifest): bool
{
    if (($manifest['format'] ?? null) !== MANIFEST_FORMAT) {
        return false;
    }

    foreach (['created_at_utc', 'source_db', 'mysql_version', 'mysqldump_version'] as $key) {
        if (! is_string($manifest[$key] ?? null) || $manifest[$key] === '') {
            return false;
        }
    }

    if (! is_array($manifest['counts'] ?? null) || $manifest['counts'] === []) {
        return false;
    }

    foreach ($manifest['counts'] as $table => $count) {
        if (! is_string($table) || ! is_int($count) || $count < 0) {
            return false;
        }
    }

    if (! is_array($manifest['files'] ?? null) || $manifest['files'] === []) {
        return false;
    }

    foreach ($manifest['files'] as $file) {
        if (! is_array($file)) {
            return false;
        }
        if (! is_string($file['name'] ?? null) || $file['name'] === '') {
            return false;
        }
        if (! is_string($file['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $file['sha256']) !== 1) {
            return false;
        }
        if (! is_int($file['bytes'] ?? null) || $file['bytes'] < 0) {
            return false;
        }
    }

    return true;
}

function parseTsv(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        fwrite(STDERR, "Cannot read $path\n");
        exit(4);
    }

    $counts = [];
    foreach ($lines as $line) {
        $parts = explode("\t", $line);
        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            fwrite(STDERR, "Invalid TSV row in $path\n");
            exit(4);
        }
        $counts[$parts[0]] = (int) $parts[1];
    }

    return $counts;
}

function parseFilesTsv(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        fwrite(STDERR, "Cannot read $path\n");
        exit(4);
    }

    $files = [];
    foreach ($lines as $line) {
        $parts = explode("\t", $line);
        if (count($parts) !== 3 || preg_match('/^[a-f0-9]{64}$/', $parts[1]) !== 1 || ! ctype_digit($parts[2])) {
            fwrite(STDERR, "Invalid files TSV row in $path\n");
            exit(4);
        }
        $files[] = ['name' => $parts[0], 'sha256' => $parts[1], 'bytes' => (int) $parts[2]];
    }

    return $files;
}

function loadManifest(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "Cannot read manifest $path\n");
        exit(4);
    }

    $decoded = json_decode($raw, true);
    if (! is_array($decoded) || ! validateManifest($decoded)) {
        fwrite(STDERR, "Invalid backup manifest: $path\n");
        exit(4);
    }

    return $decoded;
}

$command = $argv[1] ?? '';

if ($command === 'write') {
    $outputPath = $argv[2] ?? '';
    $sourceDb = $argv[3] ?? '';
    $createdAt = $argv[4] ?? '';
    $mysqlVersion = $argv[5] ?? '';
    $mysqldumpVersion = $argv[6] ?? '';
    $encryption = $argv[7] ?? '';
    $countsPath = $argv[8] ?? '';
    $filesPath = $argv[9] ?? '';
    if ($outputPath === '' || $sourceDb === '' || $createdAt === '' || $mysqlVersion === '' || $mysqldumpVersion === '' || $encryption === '' || $countsPath === '' || $filesPath === '') {
        fwrite(STDERR, "Usage: manifest.php write <out> <source_db> <created_at> <mysql_version> <mysqldump_version> <encryption> <counts.tsv> <files.tsv>\n");
        exit(64);
    }

    $manifest = [
        'format' => MANIFEST_FORMAT,
        'created_at_utc' => $createdAt,
        'source_db' => $sourceDb,
        'mysql_version' => $mysqlVersion,
        'mysqldump_version' => $mysqldumpVersion,
        'encryption' => ['at_rest' => $encryption, 'offsite_transit' => 'not configured for local drill'],
        'offsite' => ['target' => 'not configured', 'responsibility' => 'infrastructure owner'],
        'counts' => parseTsv($countsPath),
        'files' => parseFilesTsv($filesPath),
    ];
    if (! validateManifest($manifest)) {
        fwrite(STDERR, "Refusing to write an invalid manifest\n");
        exit(4);
    }
    file_put_contents($outputPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(0);
}

if ($command === 'validate') {
    loadManifest($argv[2] ?? '');
    echo "PASS manifest\n";
    exit(0);
}

$manifest = loadManifest($argv[2] ?? '');

if ($command === 'counts') {
    foreach ($manifest['counts'] as $table => $count) {
        echo $table."\t".$count.PHP_EOL;
    }
    exit(0);
}

if ($command === 'files') {
    foreach ($manifest['files'] as $file) {
        echo $file['name']."\t".$file['sha256']."\t".$file['bytes'].PHP_EOL;
    }
    exit(0);
}

if ($command === 'field') {
    $field = $argv[3] ?? '';
    $value = match ($field) {
        'format' => $manifest['format'],
        'created_at_utc' => $manifest['created_at_utc'],
        'source_db' => $manifest['source_db'],
        'mysql_version' => $manifest['mysql_version'],
        'mysqldump_version' => $manifest['mysqldump_version'],
        'encryption' => $manifest['encryption']['at_rest'],
        default => null,
    };
    if ($value === null) {
        fwrite(STDERR, "Unknown manifest field: $field\n");
        exit(64);
    }
    echo $value.PHP_EOL;
    exit(0);
}

fwrite(STDERR, "Usage: manifest.php <write|validate|counts|files|field> ...\n");
exit(64);
