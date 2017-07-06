<?php

use OEAW\Checks\Checking as CH;
require_once 'Checking.php';

if ($argc < 3 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] directory_name 0\n\n"
            . "0 => check files\n"
            . "1 => check files and viruses\n";
    return;
}

$dir = $argv[1];
$option = (int)$argv[2];

$ch = new CH();
echo $ch->startChecking($dir, $option);
