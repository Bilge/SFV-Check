#!/usr/bin/env php
<?php

if (!class_exists(IOException::class)) {
    class IOException extends RuntimeException {}
}
if (!class_exists(FileNotFoundException::class)) {
    class FileNotFoundException extends RuntimeException {}
}
if (!class_exists(MalformedFileException::class)) {
    class MalformedFileException extends RuntimeException {}
}

$script = array_shift($argv);

// This indirection is designed to facilitate testing.
set_exception_handler(function(Exception $e) use ($script) {
    echo "{$e->getMessage()}\n";

    if ($script !== 'test') exit(1);
});

if ($argc < 2)
    throw new RuntimeException("Usage: \"$script\" <sfv_file_or_directory>...");

sfv_check:

$target = array_shift($argv);

echo "\nProcessing \"" . basename($target) . "\"...\n";

if (!is_readable($target))
    throw new IOException("Cannot read \"$target\".");

// Find SFV file in directory.
if (is_dir($target)) {
    $files = scandir($target);

    $sfvFiles = [];
    foreach ($files as $filename)
        if (fnmatch('*.sfv', $filename))
            $sfvFiles[] = $target . DIRECTORY_SEPARATOR . $filename;

    if (!count($sfvFiles))
        throw new FileNotFoundException("No file matching *.sfv in \"$target\".");

    $target = $sfvFiles[0];
}

// Initialize counters.
$pass = $fail = $miss = 0;

// Parse SFV lines.
foreach (new SplFileObject($target) as $line) {
    // Skip empty lines.
    if (!isset(ltrim($line)[0])) continue;

    // Skip comments.
    if ($line[0] === ';') continue;

    $tokens = explode(' ', $line);

    if (count($tokens) < 2)
        throw new MalformedFileException("Malformed file at line: \"$line\"");

    $crc = chop(array_pop($tokens));
    $filename = implode(' ', $tokens);

    // File is located relative to SFV file.
    $file = dirname($target) . DIRECTORY_SEPARATOR . $filename;

    echo "Checking \"$filename\"... ";

    if (!is_readable($file) || !is_file($file)) {
        ++$miss;
        echo "MISSING\n";

        continue;
    }

    if (($hash = hash_file('crc32b', $file)) === strtolower($crc)) {
        ++$pass;
        echo "OK\n";
    } else {
        ++$fail;
        echo "FAILED (our hash $hash does not match $crc)\n";
    }
}

echo "\nSummary: $pass passed, $fail failed, $miss missing.\n";

// Process next file.
if (count($argv)) goto sfv_check;
