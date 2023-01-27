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

namespace acdhOeaw\arche\fileChecker;

use ProgressBar\Manager as PB;
use acdhOeaw\arche\fileChecker\CheckFunctions;
use acdhOeaw\arche\fileChecker\JsonHandler;

class FileChecker {

    static public function die(string $msg): void {
        echo $msg;
        exit(1);
    }

    private string $tmpDir;
    private string $reportDir;
    private string $signatureDir;
    private string $tmplDir;
    private string $checkDir;
    private JsonHandler $jsonHandler;
    private CheckFunctions $chkFunc;
    private int $startDepth;
    private bool $noErrors;
    private OutputFormatter $checkOutput;

    /**
     * 
     * @var array<string, mixed>
     */
    private array $cfg;

    /**
     * 
     * @var array<string, string>
     */
    private array $hashes;

    /**
     * 
     * @param array<string, mixed> $config
     */
    public function __construct(array $config) {
        $this->chkFunc = new CheckFunctions($config);
        $this->cfg     = $config;
        $this->tmplDir = realpath(__DIR__ . '/../../../../template');

        $this->tmpDir       = $config['tmpDir'];
        $this->reportDir    = $config['reportDir'];
        $this->signatureDir = $config['signatureDir'];
        $this->checkDirExistsWritable($this->tmpDir, 'tmpDir');
        $this->checkDirExistsWritable($this->reportDir, 'reportDir');
        $this->checkDirExistsWritable($this->signatureDir, 'signatureDir');

        if (!$config['overwrite']) {
            $this->reportDir = $this->reportDir . '/' . date('Y_m_d_H_i_s');
            mkdir($this->reportDir);
        }
    }

    /**
     * Recursively checks a given directory writing results in the
     * {reportsDir}/fileInfo.jsonl JSONlines file.
     */
    public function check(string $dir, int $maxDepth = PHP_INT_MAX): bool {
        echo "\n### Checking $dir...\n";

        $this->checkDir    = realpath($dir);
        $this->hashes      = [];
        $this->startDepth  = $maxDepth;
        $this->noErrors    = true;
        $this->checkOutput = new OutputFormatter("$this->reportDir/fileInfo.jsonl", OutputFormatter::FORMAT_JSONLINES);
        $dirInfo           = FileInfo::fromPath($this->checkDir);
        $this->checkDir($dirInfo, $maxDepth);
        $dirInfo->save($this->checkOutput);
        $this->checkOutput->close();

        echo "\n### Finished checking $dir\n";
        return $this->noErrors;
    }

    private function checkDir(FileInfo $dirInfo, int $depthToGo): void {
        // don't validate the top-level directory        
        if ($depthToGo < $this->startDepth) {
            $dirInfo->assert($this->chkFunc->checkDirectoryNameValidity($dirInfo->filename), "Directory name contains invalid characters");
        }

        echo "\nGenerating files list...\n";
        $files = scandir($dirInfo->path);
        if ($files === false) {
            self::die("getFileList: Failed opening directory $dir for reading");
        }
        $files = array_diff($files, ['.', '..']);
        $dirInfo->assert(count($files) > 0, "Empty directory");

        $pbFL               = new PB(0, count($files));
        $filenameDuplicates = [];
        foreach ($files as $entry) {
            echo "$entry\n";
            $fileInfo      = FileInfo::fromPath("$dirInfo->path/$entry");
            $dirInfo->size += $fileInfo->size;

            if ($fileInfo->type === 'dir' && $depthToGo > 0) {
                $this->checkDir($fileInfo, $depthToGo - 1);
                echo "\nSubdirectory content checked... \n";
            } else {
                $this->checkFile($fileInfo);
                $dirInfo->filesCount++;
            }

            $entryStd                      = mb_strtolower($entry);
            $fileInfo->assert(!isset($filenameDuplicates[$entryStd]), "Duplicated filename within a directory", "Duplicated with " . ($filenameDuplicates[$entryStd] ?? ''));
            $filenameDuplicates[$entryStd] = $entry;

            $fileInfo->save($this->checkOutput);
            $this->noErrors = $this->noErrors && count($fileInfo->errors) === 0;

            $pbFL->advance();
            echo "\n";
        }
        $this->noErrors = $this->noErrors && count($dirInfo->errors) === 0;
    }

    private function checkFile(FileInfo $fileInfo): void {
        $fileInfo->assert($fileInfo->type === 'file', "Wrong file type");
        $fileInfo->assert($this->chkFunc->checkFileNameValidity($fileInfo->filename), "Filename contains invalid characters");
        $fileInfo->assert($this->chkFunc->checkBom($fileInfo->path), 'File contains a Byte Order Mark');
        $fileInfo->appendErrors($this->chkFunc->checkMimeTypes($fileInfo->extension, $fileInfo->mime));
        $fileInfo->appendErrors($this->chkFunc->checkAcceptedByArche($fileInfo->mime));

        // duplicated files
        $hash                = $this->chkFunc->computeHash($fileInfo->path);
        $fileInfo->assert(!isset($this->hashes[$hash]), "Duplicated by hash", "Duplicated with " . ($this->hashes[$hash] ?? ''));
        $this->hashes[$hash] = $fileInfo->path;

        // zip checks
        $isZip = in_array($fileInfo->extension, ['zip', 'gzip', '7zip']) ||
            in_array($fileInfo->mime, ['application/zip', 'application/gzip', 'application/7zip']);
        if ($isZip) {
            $fileInfo->appendErrors($this->chkFunc->checkZipFile($fileInfo->path));
        }
        //check the PDF Files
        $isPdf = $fileInfo->extension == "pdf" || $fileInfo->mime == "application/pdf";
        if ($isPdf && $fileInfo->size > $this->cfg['pdfSize']) {
            $fileInfo->assert(false, "PDF too large");
        } elseif ($isPdf) {
            $fileInfo->appendErrors($this->chkFunc->checkPdfFile($fileInfo->path));
        }
        //check the RAR files
        $fileInfo->assert(!($fileInfo->extension === "rar" || $fileInfo->mime === "application/rar"), "RAR file - can't process");
        //check PW protected XLSX, DOCX
        $fileInfo->assert(!(in_array($fileInfo->extension, ["xlsx", "docx"]) && $fileInfo->mime === "application/CDFV2-encrypted"), "Encrypted DOCS/XLSX");
        //check the bagit files
        if (strpos(strtolower($fileInfo->directory), 'bagit') !== false) {
            $fileInfo->appendErrors($this->chkFunc->checkBagitFile($fileInfo->path));
        }
    }

    /**
     * Generates JSON, CSV and HTML reports based on the {reportsDir}/fileInfo.jsonl
     * file generated by the check() method.
     * 
     * @param bool $csv
     * @param bool $html
     * @return void
     */
    public function generateReports(bool $csv, bool $html): void {
        echo "\n### Generating reports...\n";

        // JSON and CSV
        $infh = fopen("$this->reportDir/fileInfo.jsonl", 'r');
        $of   = [];
        $of[] = [FileInfo::OUTPUT_ERROR, new OutputFormatter("$this->reportDir/error.json", OutputFormatter::FORMAT_JSON)];
        $of[] = [FileInfo::OUTPUT_FILELIST, new OutputFormatter("$this->reportDir/fileList.json", OutputFormatter::FORMAT_JSON)];
        $of[] = [FileInfo::OUTPUT_DIRLIST, new OutputFormatter("$this->reportDir/directoryList.json", OutputFormatter::FORMAT_JSON)];
        if ($csv) {
            $of[] = [FileInfo::OUTPUT_ERROR, new OutputFormatter("$this->reportDir/error.csv", OutputFormatter::FORMAT_CSV, FileInfo::getCsvHeader(FileInfo::OUTPUT_ERROR))];
            $of[] = [FileInfo::OUTPUT_FILELIST, new OutputFormatter("$this->reportDir/fileList.csv", OutputFormatter::FORMAT_CSV, FileInfo::getCsvHeader(FileInfo::OUTPUT_FILELIST))];
            $of[] = [FileInfo::OUTPUT_DIRLIST, new OutputFormatter("$this->reportDir/directoryList.csv", OutputFormatter::FORMAT_CSV, FileInfo::getCsvHeader(FileInfo::OUTPUT_DIRLIST))];
        }
        while ($l = fgets($infh)) {
            $fi = FileInfo::fromJson($l);
            foreach ($of as $i) {
                $fi->save($i[1], $i[0]);
            }
        }
        fclose($infh);
        foreach ($of as $i) {
            $i[1]->close();
        }

        // HTML
        if ($html) {
            $tmpl = file_get_contents(__DIR__ . '/../../../../template/index.html');
            $tmpl = explode('/*DATA*/', $tmpl);
            $fh   = fopen("$this->reportDir/index.html", 'w');
            fwrite($fh, $tmpl[0]);
            fwrite($fh, "baseDir = " . json_encode($this->checkDir) . ";\n");

            fwrite($fh, "var fileList = ");
            $this->copyFileContent("$this->reportDir/fileList.json", $fh);
            fwrite($fh, ";\n");

            fwrite($fh, "var directoryList = ");
            $this->copyFileContent("$this->reportDir/directoryList.json", $fh);
            fwrite($fh, ";\n");

            fwrite($fh, "var errorList = ");
            $this->copyFileContent("$this->reportDir/error.json", $fh);
            fwrite($fh, ";\n");

            $dict = [
                '{{title}}' => 'Report for ' . $this->checkDir . ' (' . date('Y-m-d H:i') . ')',
            ];
            fwrite($fh, str_replace(array_keys($dict), array_values($dict), $tmpl[1]));
            fclose($fh);
        }

        echo "\n### Finished reports generation\n";
    }

    private function checkDirExistsWritable(string $dir,
                                            string $type = "tempDir"): void {
        $real = realpath($dir);
        if (!is_dir($real) || !is_writable($real)) {
            self::die("\nERROR $type ($dir) does't exist or isn't writable.\n");
        }
    }

    private function copyFileContent($from, $to): void {
        $from = fopen($from, 'r') ?: self::die("Can't open $from for reading");
        while (!feof($from)) {
            fwrite($to, fread($from, 1048576));
        }
        fclose($from);
    }
}
