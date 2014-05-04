#!/usr/bin/env php
<?php

if ($argc < 2) {
	echo "Usage: $argv[0] <sfv_file>\n";

	exit(1);
}

$sfvFile = $argv[1];

if (!is_readable($sfvFile)) {
	echo "Cannot read \"$sfvFile\".\n";

	exit(1);
}

//Initialize counters.
$pass = $fail = $miss = 0;

//Parse SFV lines.
foreach (new SplFileObject($sfvFile) as $line) {
    //Skip empty lines.
    if (!isset(ltrim($line)[0])) continue;

    //Skip comments.
    if ($line[0] === ';') continue;

    $tokens = explode(' ', $line);
    $crc = chop(array_pop($tokens));
    $filename = implode(' ', $tokens);

    //File is located relative to SFV file.
    $file = dirname($sfvFile) . DIRECTORY_SEPARATOR . $filename;

    echo "Checking \"$filename\"... ";

    if (!is_readable($file)) {
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