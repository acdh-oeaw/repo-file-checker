<?php

use OEAW\Checks\Checking as CH;
require_once 'Checking.php';

if ($argc < 4 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] directory_name 0\n\n"
            . "0 => check files\n"
            . "1 => check files and viruses\n"
            . "0 => HTML output. 1 => json output \n";
    return;
}

$dir = $argv[1];
$option = (int)$argv[2];
$output = (int)$argv[3];

$ch = new CH();
echo $ch->startChecking($dir, $option, $output);
