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

// This indirection is designed to facilitate testing.
set_exception_handler(function(Exception $e) use ($argv) {
    echo "{$e->getMessage()}\n";

    if ($argv[0] !== 'test') exit(1);
});

if ($argc < 2)
    throw new RuntimeException("Usage: \"$argv[0]\" <sfv_file_or_directory>");

$sfvFile = $argv[1];

if (!is_readable($sfvFile))
    throw new IOException("Cannot read \"$sfvFile\".");

// Find SFV file in directory.
if (is_dir($sfvFile)) {
    $files = scandir($sfvFile);

    $sfvFiles = [];
    foreach ($files as $filename)
        if (fnmatch('*.sfv', $filename))
            $sfvFiles[] = $sfvFile . DIRECTORY_SEPARATOR . $filename;

    if (!count($sfvFiles))
        throw new FileNotFoundException("No file matching *.sfv in \"$sfvFile\".");

    $sfvFile = $sfvFiles[0];
}

// Initialize counters.
$pass = $fail = $miss = 0;

// Parse SFV lines.
foreach (new SplFileObject($sfvFile) as $line) {
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
    $file = dirname($sfvFile) . DIRECTORY_SEPARATOR . $filename;

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
