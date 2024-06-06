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

    private string $tmpDir;
    private string $reportDir;
    private string $tmplDir;
    private string $checkDir;
    private string $matchRegex;
    private CheckFunctions $chkFunc;
    private int $startDepth;
    private bool $noErrors;
    private bool $skipWarnings;
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
        $config['tmpDir']   .= '/filechecker' . rand();
        $this->chkFunc      = new CheckFunctions($config);
        $this->cfg          = $config;
        $this->tmplDir      = realpath(__DIR__ . '/../../../../template');
        $this->matchRegex   = isset($config['match']) ? "`" . $config['match'] . "`" : '';
        $this->skipWarnings = (bool) ($config['skipWarnings'] ?? false);

        $this->tmpDir    = $config['tmpDir'];
        $this->reportDir = $config['reportDir'];
        $this->checkDirExistsWritable($this->tmpDir);
        $this->checkDirExistsWritable($this->reportDir);

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
                $this->checksFile[$method->name] = $method->getClosure($this->chkFunc);
            }
            if (in_array(CheckDir::class, $attr)) {
                $this->checksDir[$method->name] = $method->getClosure($this->chkFunc);
            }
        }
        ksort($this->checksFile);
        ksort($this->checksDir);
    }

    public function __destruct() {
        system("rm -fR " . escapeshellarg($this->tmpDir));
    }

    /**
     * Recursively checks a given directory writing results in the
     * {reportsDir}/fileInfo.jsonl JSONlines file.
     */
    public function check(string $dir, int $maxDepth = PHP_INT_MAX,
                          bool $sortDroidOutput = false): bool {
        $this->checkDir    = realpath($dir);
        $this->startDepth  = $maxDepth;
        $this->noErrors    = true;
        $this->checkOutput = new OutputFormatter("$this->reportDir/fileInfo.jsonl", OutputFormatter::FORMAT_JSONLINES);

        if ($this->checkDir === '') {
            echo "\nERROR: Directory '$dir' does not exist\n";
            return false;
        }

        $droidOutput       = $this->runDroid($sortDroidOutput);
        echo "\n### Running DROID...\n\n";
        $this->progressBar = new PB(0, $this->getCountFromDroidOutput($droidOutput));

        echo "\n### Processing. DROID output...\n\n";
        $this->checkFromDroidOutput($droidOutput);

        echo "### Finished checking $this->checkDir - " . ($this->noErrors ? 'no errors found' : 'errors found') . "\n";

        $this->checkOutput->close();
        return $this->noErrors;
    }

    private function checkFromDroidOutput(string $droidOutput): void {
        $fh       = fopen($droidOutput, 'rb');
        $header   = fgetcsv($fh);
        $dirs     = [];
        $hashes   = [];
        $stdNames = [];
        while (!feof($fh)) {
            $line = fgetcsv($fh);
            if (is_array($line) && count($line) === count($header)) {
                $fileInfo = FileInfo::fromDroid(array_combine($header, $line));

                if ($fileInfo->type === FileInfo::DROID_TYPEDIR) {
                    $dirs[$fileInfo->droidId] = $fileInfo;
                } else {
                    $this->runChecks($fileInfo, $this->checksFile);

                    if (!is_link($fileInfo->path)) {
                        $hash = $this->computeHash($fileInfo->path);
                        if (isset($hashes[$hash])) {
                            $fileInfo->error("Duplicated file", "Same hash as " . substr($hashes[$hash], strlen($this->checkDir) + 1));
                        } else {
                            $hashes[$hash] = $fileInfo->path;
                        }
                    }

                    $stdName = strtolower($fileInfo->path);
                    if (isset($stdNames[$stdName])) {
                        $fileInfo->error("Duplicated file", "File names duplication with " . $stdNames[$stdName] . " on a case-insensititve filesystems");
                    } else {
                        $stdNames[$stdName] = $fileInfo->filename;
                    }

                    $fileInfo->assignHasCategory();
                    $fileInfo->save($this->checkOutput);
                    $this->progressBar->advance();
                }

                if (!empty($fileInfo->droidParentId)) {
                    $dirs[$fileInfo->droidParentId]->filesCount++;
                }

                $this->noErrors = $this->noErrors && $fileInfo->isValid($this->skipWarnings);
            }
        }
        fclose($fh);

        foreach ($dirs as $dirInfo) {
            if ($dirInfo->path !== $this->checkDir) {
                $this->runChecks($dirInfo, $this->checksDir);
                $this->noErrors = $this->noErrors && $dirInfo->isValid($this->skipWarnings);
                $dirInfo->save($this->checkOutput);
                $this->progressBar->advance();
            }
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
            foreach ($of as $x => $i) {
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

    private function checkDirExistsWritable(string $dir): void {
        exec('mkdir -p ' . escapeshellarg($dir));
        if (!is_writable($dir)) {
            $real = realpath($dir);
            throw new FileCheckerException("$dir ($real) does't exist or isn't writable");
        }
    }

    private function copyFileContent($from, $to): void {
        $from = fopen($from, 'r') ?: throw new FileCheckerException("Can't open $from for reading");
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

    private function getCountFromDroidOutput(string $path): int {
        $f = fopen($path, 'rb');
        $n = 0;
        while (!feof($f)) {
            $n += substr_count(fread($f, 1047552), "\n");
        }
        fclose($f);
        return $n;
    }

    /**
     * 
     * @param bool $sortOutput should output should be sorted by full path?
     *   DROID output comes in the order of records on the filesystem. In some scenarios,
     *   e.g. for running tests, a stable order can be needed. It can be enforced using
     *   this parameter. It should not be used when not needed as sorting takes time
     *   and for large number of files also a lot of memory.
     * @return string
     * @throws \RuntimeException
     */
    private function runDroid(bool $sortOutput = false): string {
        $droidOutput = $this->tmpDir . '/droid.csv';
        $output      = $ret         = null;
        $cmd         = sprintf(
            "%s -R %s -At none > %s 2>&1",
            escapeshellarg(CheckFunctions::DROID_PATH),
            escapeshellarg($this->checkDir),
            escapeshellarg($droidOutput)
        );
        exec($cmd, $output, $ret);
        if ($ret !== 0) {
            throw new \RuntimeException("Running DOID failed with:\n" . file_get_contents($droidOutput));
        }

        if ($sortOutput) {
            $fh     = fopen($droidOutput, 'rb');
            $header = fgetcsv($fh);
            $data   = [];
            while ($l      = fgetcsv($fh)) {
                if (count($l) > 2) {
                    $data[$l[3]] = $l;
                }
            }
            fclose($fh);
            ksort($data);
            $fh = fopen($droidOutput, 'wb');
            fputcsv($fh, $header);
            foreach ($data as $l) {
                fputcsv($fh, $l);
            }
            fclose($fh);
        }

        return $droidOutput;
    }

    private function runChecks(FileInfo $fileInfo, array $checks): void {
        try {
            foreach ($checks as $check) {
                try {
                    $check($fileInfo);
                } catch (LastCheckException $e) {
                    throw $e;
                } catch (Exception $e) {
                    $fileInfo->error(get_class($e), $e->getMessage());
                }
            }
        } catch (LastCheckException) {
            
        }
    }
}
