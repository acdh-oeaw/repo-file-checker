<?php

use OEAW\Checks\Checking as CH;
require_once 'Checking.php';

if ($argc < 3 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] directory_name 0 \n\n"
            . "First arg: 0 => check files (json output) \n"
            . "1 => check files and create file type report (json output) \n"
            . "2 => check files (html and json output) \n"
            . "3 => check files and create file type report (html and json output) \n"
            . "4 => check files (NDJSON output) \n";
    return;
}

$dir = $argv[1];
$output = (int)$argv[2];

$ch = new CH();
echo $ch->startChecking($dir, $output);
