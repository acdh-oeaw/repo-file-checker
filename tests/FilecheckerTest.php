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
        return;
        exec('rm -fR "' . self::TMP_DIR . '" "' . self::REPORTS_DIR . '"');
        mkdir(self::TMP_DIR);
        mkdir(self::REPORTS_DIR);

        $ch = new FileChecker(self::DEFAULT_OPTS);
        $ch->check(__DIR__ . '/data');
        $ch->generateReports(true, true);
    }

    private function readErrorLog(string $pathRegex = '',
                                  string $typeRegex = '', string $msgRegex = '',
                                  bool $simplify = true): array {
        $results = json_decode(file_get_contents(__DIR__ . '/reports/error.json'));
        $results = array_filter($results, fn($x) => preg_match("`$pathRegex`", $x->directory . '/' . $x->filename) && preg_match("`$typeRegex`", $x->errorType) && preg_match("`$msgRegex`", $x->errorMessage));
        if ($simplify) {
            $n       = strlen(self::DATA_DIR);
            $results = array_map(fn($x) => substr($x->directory, $n + 1) . '/' . $x->filename, $results);
            sort($results);
        }
        return array_values($results);
    }

    public function testWrongContent(): void {
        $actual   = $this->readErrorLog('', "File content doesn't match extension", '', true);
        $expected = array_diff(scandir(self::DATA_DIR . '/wrongContent'), ['.', '..']);
        $expected = array_values(array_map(fn($x) => 'wrongContent/' . $x, $expected));
        sort($expected);

        $this->assertEquals([], array_diff($actual, $expected), 'unexpected');
        $this->assertEquals([], array_diff($expected, $actual), 'missing');

        $this->assertTrue(true);
    }
}
