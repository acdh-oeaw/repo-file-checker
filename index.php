<?php

use oeaw\checks\Checking as CH;
require_once 'Checking.php';

if ($argc < 2 || !file_exists($argv[1])) {
    echo "\nusage: $argv[0] directory_name\n\n";
    return;
}

$dir = $argv[1];

$ch = new CH();
echo $ch->startChecking($dir);
