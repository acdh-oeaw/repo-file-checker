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

/**
 * Description of OutputFormatter
 *
 * @author zozlak
 */
class OutputFormatter {

    const FORMAT_JSON      = 'json';
    const FORMAT_JSONLINES = 'jsonlines';
    const FORMAT_CSV       = 'csv';
    const CSV_SEPARATOR    = ';';

    /**
     * 
     * @var resource
     */
    private $fh;
    private string $format;
    private bool $first = true;

    public function __construct(string $filename, string $format,
                                ?string $header = null) {
        $this->fh     = fopen($filename, 'w') ?: FileChecker::die("Failed to open $filename for writing");
        $this->format = $format;

        if ($format === self::FORMAT_JSON) {
            fwrite($this->fh, "[\n");
        } elseif ($format === self::FORMAT_CSV) {
            fwrite($this->fh, $header);
        }
    }

    public function write(mixed $data): void {
        $data        = match ($this->format) {
            self::FORMAT_JSONLINES => json_encode($data, JSON_UNESCAPED_SLASHES) . "\n",
            self::FORMAT_JSON => ($this->first ? '' : ",\n") . json_encode($data, JSON_UNESCAPED_SLASHES),
            self::FORMAT_CSV => implode(self::CSV_SEPARATOR, array_map(fn($x) => is_bool($x) ? ($x ? 'yes' : 'no') : $x, $data)) . "\n",
        };
        fwrite($this->fh, $data);
        $this->first = false;
    }

    public function __destruct() {
        if (isset($this->fh)) {
            $this->close();
        }
    }

    public function close(): void {
        if ($this->format === self::FORMAT_JSON) {
            fwrite($this->fh, "\n]\n");
        }
        fclose($this->fh);
        unset($this->fh);
    }
}
