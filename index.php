<?php

use OEAW\Checks\Checking as CH;
use zozlak\argparse\ArgumentParser;
$composerDir = realpath(__DIR__);
while ($composerDir !== false && !file_exists("$composerDir/vendor")) {
    $composerDir = realpath("$composerDir/..");
}
require_once "$composerDir/vendor/autoload.php";
require_once "$composerDir/vendor/scholarslab/bagit/lib/bagit.php";

function die2(string $msg) {
    echo $msg;
    exit(1);
}

$parser = new ArgumentParser();
$parser->addArgument('--signatureDir', default: __DIR__ . '/signatures', help: "Directory containing the DROID_SignatureFile XML file (default: %(default)s)");
$parser->addArgument('--tmpDir', required: true);
$parser->addArgument('--reportDir', required: true);
$parser->addArgument('--blackList', nargs: ArgumentParser::NARGS_REQ, default: ['app', 'apk', 'cfg'], help: "Extenstions of files to be skipped (default: [%(default)s])");
$parser->addArgument('--pdfSize', type: ArgumentParser::TYPE_INT, default: 80000000, help: "Maximum PDF file size in bytes (default: %(default)s)");
$parser->addArgument('--zipSize', type: ArgumentParser::TYPE_INT, default: 100000000, help: "Maximum ZIP file size in bytes (default: %(default)s)");
$parser->addArgument('directoryToCheck');
$parser->addArgument('outputMode', choices: [0, 1, 2, 3], type: ArgumentParser::TYPE_INT, help:"0 - check files  (json output) and create file type report (json output); 1 - check files (json output and html output) and create file type report (json output); 2 - check files (NDJSON output); 3 - like 2. but with a Type List as a treeview");
$args   = $parser->parseArgs();

$ch = new CH((array) $args);
$ret = $ch->startChecking($args->directoryToCheck, $args->outputMode);
exit($ret ? 0 : 2);
