#!/usr/bin/php
<?php

/*
 * The MIT License
 *
 * Copyright 2019 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

use acdhOeaw\arche\fileChecker\FileChecker;
use zozlak\argparse\ArgumentParser;
use acdhOeaw\arche\lib\ingest\Redmine;

$composerDir = realpath(__DIR__);
while ($composerDir !== false && !file_exists("$composerDir/vendor")) {
    $composerDir = realpath("$composerDir/..");
}
require_once "$composerDir/vendor/autoload.php";

$parser = new ArgumentParser();
$parser->addArgument('--tmpDir', default: sys_get_temp_dir(), help: "Temporary directory. If not specified, the system-wide temp dir is used.");
$parser->addArgument('--signatureDir', default: __DIR__ . '/aux', help: "Directory containing the DROID_SignatureFile XML file (default: %(default)s)");
$parser->addArgument('--pdfSize', type: ArgumentParser::TYPE_INT, default: 80000000, help: "Maximum PDF file size in bytes (default: %(default)s)");
$parser->addArgument('--csv', action: ArgumentParser::ACTION_STORE_TRUE, help: "If present, CSV reports are generated on top of the standard JSON linse output.");
$parser->addArgument('--html', action: ArgumentParser::ACTION_STORE_TRUE, help: "If present, HTML reports are generated on top of the standard JSON lines output.");
$parser->addArgument('--overwrite', action: ArgumentParser::ACTION_STORE_TRUE, help: "If present, the report is generated directly in the reportDir without creation of a timestamp-based directory.");
$parser->addArgument('--match', help: "If provided, only files and directories matching a given regular expression are being checked. If the directory check is skipped, the directory content is still being checked.");
$parser->addArgument('--redmineIssueId', help: "If provided (along with --redmineUser and --redminePswd), the corresponding redmine id will be updated with the check results. Both repo-filechecker issue id or its parent issue id can be provided.");
$parser->addArgument('--redmineUser');
$parser->addArgument('--redminePswd');
$parser->addArgument('--redmineToken');
$parser->addArgument('--redmineApiUrl', default: "https://redmine.acdh.oeaw.ac.at");
$parser->addArgument('--redmineDone', type: ArgumentParser::TYPE_INT, default: 10, help: "Percent done value set on the redmine issue in case of unsuccessful check.");
$parser->addArgument('--redmineMessage', default: '', help: "Message posted as a note in the redmine issue. If not provided, a default value is used.");
$parser->addArgument('--redmineAppend', action: ArgumentParser::ACTION_STORE_TRUE, help: "Should --redmineMessage be appended to the default message?");
$parser->addArgument('--skipWarnings', action: ArgumentParser::ACTION_STORE_TRUE, help: "If present, only errors cause a non-zero script return value.");
$parser->addArgument('directoryToCheck');
$parser->addArgument('reportDir');
$args   = $parser->parseArgs();

$ch  = new FileChecker((array) $args);
$ret = $ch->check($args->directoryToCheck);
$ch->generateReports($args->csv, $args->html);

if (!empty($args->redmineId)) {
    try {
        $redmine   = new Redmine($args->redmineApiUrl, (string) ($args->redmineToken ?? $args->redmineUser), (string) $args->redminePswd);
        $redmineId = $redmine->updateIssue(
            $args->mainIssueId, 'Run repo-file-checker', $ret,
            issueStatus: $ret ? 'Resolved' : 'In Progress',
            done: $ret ? $args->redmineDone : 100,
            message: $args->redmineMessage,
            append: $args->redmineAppend
        );
        echo "Redmine issue $redmineId updated successfully\n";
    } catch (Throwable $e) {
        echo $e->getMessage() . "\n";
    }
}

exit($ret ? 0 : 2);
