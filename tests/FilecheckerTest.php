<?php

/*
 * The MIT License
 *
 * Copyright 2024 zozlak.
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

namespace acdhOeaw\arche\fileChecker\tests;

use acdhOeaw\arche\fileChecker\FileChecker;

/**
 * Description of FilecheckerTest
 *
 * @author zozlak
 */
class FilecheckerTest extends \PHPUnit\Framework\TestCase {

    const CSV_SEP      = ';';
    const TMP_DIR      = 1;
    const REPORTS_DIR  = __DIR__ . '/reports';
    const DATA_DIR     = __DIR__ . '/data';
    const DEFAULT_OPTS = [
        'tmpDir'       => self::TMP_DIR,
        'reportDir'    => self::REPORTS_DIR,
        'signatureDir' => __DIR__ . '/../aux',
        'verapdfPath'  => __DIR__ . '/verapdf/verapdf',
        'overwrite'    => true,
    ];

    static public function setUpBeforeClass(): void {
        #return;

        exec('rm -fR "' . self::TMP_DIR . '" "' . self::REPORTS_DIR . '"');
        mkdir(self::TMP_DIR);
        mkdir(self::REPORTS_DIR);
        if (!file_exists(__DIR__ . '/data/emptyDir')) {
            mkdir(__DIR__ . '/data/emptyDir'); // can not be stored by git
        }

        $ch = new FileChecker(self::DEFAULT_OPTS);
        $ch->check(__DIR__ . '/data', PHP_INT_MAX, true);
        $ch->generateReports(true, true);
    }

    public function testCsv(): void {
        $filesCsv  = $this->readCsv(self::REPORTS_DIR . '/fileList.csv', false);
        $filesJson = json_decode(file_get_contents(self::REPORTS_DIR . '/fileList.json'), true);
        $this->assertEquals($filesJson, $filesCsv);

        $dirsCsv  = $this->readCsv(self::REPORTS_DIR . '/directoryList.csv', false);
        $dirsJson = json_decode(file_get_contents(self::REPORTS_DIR . '/directoryList.json'), true);
        $this->assertEquals($dirsJson, $dirsCsv);

        $errorsCsv  = $this->readCsv(self::REPORTS_DIR . '/error.csv', false);
        $errorsJson = json_decode(file_get_contents(self::REPORTS_DIR . '/error.json'), true);
        $this->assertEquals($errorsJson, $errorsCsv);
    }

    public function testDirectoryList(): void {
        $skipLastModified = function ($x) {
            unset($x['lastModified']);
            return $x;
        };
        $sortFn = fn($a, $b) => $a['path'] <=> $b['path'];

        $expected = $this->readCsv(__DIR__ . '/refDirectoryList.csv');
        usort($expected, $sortFn);
        $actual   = $this->readCsv(self::REPORTS_DIR . '/directoryList.csv');
        $actual   = $this->normalizeDirectory($actual, 'path');
        $actual   = array_map($skipLastModified, $actual);
        usort($actual, $sortFn);
        $this->assertEquals($expected, $actual);
    }

    public function testFileList(): void {
        $skipLastModified = function ($x) {
            unset($x['lastModified']);
            return $x;
        };

        $expected = $this->readCsv(__DIR__ . '/refFileList.csv');
        $this->sort($expected);
        $actual   = $this->readCsv(self::REPORTS_DIR . '/fileList.csv');
        $actual   = $this->normalizeDirectory($actual);
        $actual   = array_map($skipLastModified, $actual);
        $this->sort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function testErrorsInfo(): void {
        $this->errorTest('INFO');
    }

    public function testErrorsWarning(): void {
        $this->errorTest('WARNING');
    }

    public function testErrorsError(): void {
        $this->errorTest('ERROR');
    }

    private function errorTest(string $severity): void {
        $expected = $this->readCsv(__DIR__ . "/refError$severity.csv");

        $actual = $this->readCsv(self::REPORTS_DIR . '/error.csv');
        $actual = array_filter($actual, fn($x) => $x['severity'] === $severity);

        $this->normalizeErrorLists($expected, $actual);
        $this->assertEquals($expected, $actual);
    }

    private function readCsv(string $path, bool $shorten = true): array {
        $castBool = function ($x) {
            return match ($x) {'no' => false, 'yes' => true, default => $x
            };
        };

        $this->assertFileExists($path);
        $f      = fopen($path, 'rb');
        $header = fgetcsv($f, null, self::CSV_SEP);
        $data   = [];
        while ($line   = fgetcsv($f, null, self::CSV_SEP)) {
            if (is_array($line) && count($line) === count($header)) {
                if ($shorten) {
                    $line = array_map(fn($x) => explode("\n", $x)[0], $line);
                }
                $line   = array_map(fn($x) => is_numeric($x) ? (int) $x : $x, $line);
                $line   = array_map($castBool, $line);
                $data[] = array_combine($header, $line);
            }
        }
        fclose($f);
        return $data;
    }

    /**
     * Adds empty entries to $expected and $actual arrays so that corresponding
     * items always describe same test files.
     * This makes the assertEquals() results much easier to read in case of a mismatch.
     * 
     * @param array $expected
     * @param array $actual
     * @return void
     */
    private function normalizeErrorLists(array &$expected, array &$actual): void {
        $actual = $this->normalizeDirectory($actual);

        $countExpected = [];
        foreach ($expected as $i) {
            $path                 = $i['directory'] . '/' . $i['filename'];
            $countExpected[$path] = ($countExpected[$path] ?? 0) + 1;
        }
        $countActual = [];
        foreach ($actual as $i) {
            $path               = $i['directory'] . '/' . $i['filename'];
            $countActual[$path] = ($countActual[$path] ?? 0) + 1;
        }
        $allPaths = array_unique(array_merge(array_keys($countExpected), array_keys($countActual)));
        foreach ($allPaths as $k) {
            $item = [
                'directory' => dirname($k),
                'filename'  => basename($k),
                'errorType' => '',
            ];
            for ($i = 0; $i < ($countExpected[$k] ?? 0) - ($countActual[$k] ?? 0); $i++) {
                $actual[] = $item;
            }
            for ($i = 0; $i < ($countActual[$k] ?? 0) - ($countExpected[$k] ?? 0); $i++) {
                $expected[] = $item;
            }
        }

        $this->sort($actual, ['errorType']);
        $this->sort($expected, ['errorType']);
    }

    private function sort(array &$data, array $otherCols = []): void {
        $sortFn = function ($a, $b) use ($otherCols): int {
            $av = $a['directory'] . '/' . $a['filename'];
            $bv = $b['directory'] . '/' . $b['filename'];
            foreach ($otherCols as $i) {
                $av .= '#' . $a[$i];
                $bv .= '#' . $b[$i];
            }
            return $av <=> $bv;
        };
        usort($data, $sortFn);
    }

    private function normalizeDirectory(array $data,
                                        string $column = 'directory'): array {
        $n         = strlen(__DIR__ . '/data/');
        $normDirFn = function (array $x) use ($n, $column): array {
            $x[$column] = substr($x[$column], $n);
            return $x;
        };
        return array_map($normDirFn, $data);
    }
}
