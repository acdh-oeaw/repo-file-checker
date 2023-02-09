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

use Closure;
use Exception;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ProgressBar\Manager as PB;
use acdhOeaw\arche\fileChecker\CheckFunctions;
use acdhOeaw\arche\fileChecker\attributes\CheckFile;
use acdhOeaw\arche\fileChecker\attributes\CheckDir;

class FileChecker {

    const HASH_FALLBACK = 'sha1';
    const HASH_DEFAULT  = 'xxh128';

    static public function die(string $msg): void {
        echo $msg;
        exit(1);
    }

    private string $tmpDir;
    private string $reportDir;
    private string $signatureDir;
    private string $tmplDir;
    private string $checkDir;
    private string $matchRegex;
    private CheckFunctions $chkFunc;
    private int $startDepth;
    private bool $noErrors;
    private OutputFormatter $checkOutput;
    private string $hashAlgo;
    private PB $progressBar;

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
     * @var array<Closure>
     */
    private array $checksFile = [];

    /**
     * 
     * @var array<Closure>
     */
    private array $checksDir = [];

    /**
     * 
     * @param array<string, mixed> $config
     */
    public function __construct(array $config) {
        $this->chkFunc    = new CheckFunctions($config);
        $this->cfg        = $config;
        $this->tmplDir    = realpath(__DIR__ . '/../../../../template');
        $this->matchRegex = isset($config['match']) ? "`" . $config['match'] . "`" : '';

        $this->tmpDir       = $config['tmpDir'];
        $this->reportDir    = realpath($config['reportDir']);
        $this->signatureDir = $config['signatureDir'];
        $this->checkDirExistsWritable($this->tmpDir, 'tmpDir');
        $this->checkDirExistsWritable($this->reportDir, 'reportDir');
        $this->checkDirExistsWritable($this->signatureDir, 'signatureDir');

        if (!$config['overwrite']) {
            $this->reportDir = $this->reportDir . '/' . date('Y_m_d_H_i_s');
            mkdir($this->reportDir);
        }

        $this->hashAlgo = $config['hashAlgo'] ?? self::HASH_DEFAULT;
        if (!in_array($this->hashAlgo, hash_algos())) {
            echo "Hashing algorithm $hash->algo unavailable, falling back to " . self::HASH_FALLBACK . "\n\n";
            $this->hashAlgo = self::HASH_FALLBACK;
        }

        // initialize checks by introspecting the CheckFunctions class
        $rc = new ReflectionClass(CheckFunctions::class);
        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attr = array_map(fn($x) => $x->getName(), $method->getAttributes());
            if (in_array(CheckFile::class, $attr)) {
                $this->checksFile[] = $method->getClosure($this->chkFunc);
            }
            if (in_array(CheckDir::class, $attr)) {
                $this->checksDir[] = $method->getClosure($this->chkFunc);
            }
        }
    }

    /**
     * Recursively checks a given directory writing results in the
     * {reportsDir}/fileInfo.jsonl JSONlines file.
     */
    public function check(string $dir, int $maxDepth = PHP_INT_MAX): bool {
        $this->checkDir    = realpath($dir);
        $this->hashes      = [];
        $this->startDepth  = $maxDepth;
        $this->noErrors    = true;
        $this->checkOutput = new OutputFormatter("$this->reportDir/fileInfo.jsonl", OutputFormatter::FORMAT_JSONLINES);

        echo "\nComputing the number of files and directories to analyze\n";
        $iter = new RecursiveDirectoryIterator($this->checkDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iter = new RecursiveIteratorIterator($iter, RecursiveIteratorIterator::SELF_FIRST);
        if (empty($this->matchRegex)) {
            $count = iterator_count($iter);
        } else {
            $count = 0;
            foreach ($iter as $i) {
                $count += (int) preg_match($this->matchRegex, basename($i));
            }
        }
        unset($iter);
        $this->progressBar = new PB(0, $count);

        echo "\n### Checking $this->checkDir...\n";
        $dirInfo = FileInfo::fromPath($this->checkDir);
        $this->checkDir($dirInfo, $maxDepth);
        $dirInfo->save($this->checkOutput);
        $this->checkOutput->close();

        echo "\n### Finished checking $this->checkDir - " . ($this->noErrors ? 'no errors found' : 'errors found') . "\n";
        return $this->noErrors;
    }

    private function checkDir(FileInfo $dirInfo, int $depthToGo): void {
        // don't validate the top-level directory        
        if ($depthToGo < $this->startDepth && (empty($this->matchRegex) || preg_match($this->matchRegex, $dirInfo->filename))) {
            foreach ($this->checksDir as $check) {
                try {
                    $check($dirInfo);
                } catch (Exception $e) {
                    $fileInfo->error(get_class($e), $e->getMessage());
                }
            }
        }

        $files = scandir($dirInfo->path);
        if ($files === false) {
            self::die("getFileList: Failed opening directory $dir for reading");
        }
        $files = array_diff($files, ['.', '..']);

        $filenameDuplicates = [];
        foreach ($files as $entry) {
            $path  = "$dirInfo->path/$entry";
            $match = empty($this->matchRegex) || preg_match($this->matchRegex, $entry) === 1;
            if (!$match) {
                if (is_dir($path)) {
                    $this->checkDir(FileInfo::fromPath($path), $depthToGo - 1);
                }
                continue;
            }

            echo "$entry\n";
            $this->progressBar->advance();
            echo "\n";
            $fileInfo      = FileInfo::fromPath($path);
            $dirInfo->size += $fileInfo->size;

            $entryStd = mb_strtolower($entry);
            if (isset($filenameDuplicates[$entryStd])) {
                $fileInfo->error("Duplicated file", "Duplicates with $filenameDuplicates[$entryStd] on case-insensitive filesystems");
            }
            $filenameDuplicates[$entryStd] = $entry;

            if ($fileInfo->type === 'dir' && $depthToGo > 0) {
                echo "[entering $fileInfo->path]\n";
                $this->checkDir($fileInfo, $depthToGo - 1);
                echo "[going back to $dirInfo->path]\n";
            } else {
                foreach ($this->checksFile as $check) {
                    try {
                        $check($fileInfo);
                    } catch (Exception $e) {
                        $fileInfo->error(get_class($e), $e->getMessage());
                    }
                }

                $hash = $this->computeHash($fileInfo->path);
                if (isset($this->hashes[$hash])) {
                    $fileInfo->error("Duplicated file", "Same hash as " . $this->hashes[$hash]);
                }
                $this->hashes[$hash] = $fileInfo->path;

                $dirInfo->filesCount++;
            }

            $fileInfo->save($this->checkOutput);
            $this->noErrors = $this->noErrors && $fileInfo->valid;
        }
        $this->noErrors = $this->noErrors && $dirInfo->valid;
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
        echo "\n### Writing reports to $this->reportDir\n";

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
            $tmpl = file_get_contents(__DIR__ . '/../../../../aux/index.html');
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

    private function computeHash(string $path): string {
        $hash  = hash_init($this->hashAlgo);
        $input = fopen($path, 'r');
        while (!feof($input)) {
            $buffer = (string) fread($input, 1048576);
            hash_update($hash, $buffer);
        }
        fclose($input);
        return hash_final($hash, false);
    }
}
