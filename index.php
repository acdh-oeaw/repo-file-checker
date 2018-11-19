<?php

use OEAW\Checks\Checking as CH;
require_once 'Checking.php';

if ($argc < 3 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] directory_name 0 \n\n"
            . "First arg: 0 => check files  (json output) and create file type report (json output)\n \n"
            . "1 => check files (json output and html output) and create file type report (json output) \n"
            . "2 => check files (NDJSON output) \n"
            . "3 => the same like the 2. just here the Type List is in a treeview \n";
    return;
}

$dir = $argv[1];
$output = (int)$argv[2];

$ch = new CH();
echo $ch->startChecking($dir, $output);
