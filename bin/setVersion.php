<?php
if ($argc !== 3) {
    fwrite(STDERR, "usage: setVersion.php <config-file> <version>\n");
    exit(1);
}
[$file, $version] = [$argv[1], $argv[2]];

$src = file_get_contents($file);
if ($src === false) {
    fwrite(STDERR, "cannot read: $file\n");
    exit(1);
}

$config = require $file;
$exists = is_array($config) && array_key_exists('versionString', $config);
$q = var_export($version, true);

if ($exists) {
    $out = preg_replace(
        '/([\'"])versionString\1(\s*=>\s*)[^,)\]]+/',
        '$1versionString$1$2' . str_replace('$', '\\$', $q),
        $src, 1, $count
    );
} else {
    $out = preg_replace(
        '/(return\s*(?:\[|array\s*\()|\$config\s*=\s*(?:\[|array\s*\())/',
        '$0' . "\n    'versionString' => " . str_replace('$', '\\$', $q) . ',',
        $src, 1, $count
    );
}

if ($out === null || $count !== 1) {
    fwrite(STDERR, "pattern did not match once (count=$count) — file unchanged\n");
    exit(1);
}

if (file_put_contents($file, $out, LOCK_EX) === false) {
    fwrite(STDERR, "write failed: $file\n");
    exit(1);
}
echo "versionString = $version\n";