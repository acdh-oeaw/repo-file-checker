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

use Exception;
use RuntimeException;
use ZipArchive;
use finfo;

/**
 * Description of FileInfo
 *
 * @author zozlak
 */
class FileInfo {

    const DROID_TYPEDIR   = 'Folder';
    const DROID_TYPEFILE  = 'File';
    const DROID_TYPELINK  = 'Symlink';
    const UNKNOWN_MIME    = 'unknown';
    const OUTPUT_ERROR    = 'error';
    const OUTPUT_FILELIST = 'fileList';
    const OUTPUT_DIRLIST  = 'dirList';
    const SPECIAL_BAGIT   = 'bagit';
    const SPECIAL_XSD     = 'xsd';

    static private $outputColumns = [
        self::OUTPUT_FILELIST => ['directory', 'filename', 'type', 'size', 'lastModified',
            'extension', 'mime', 'specialType', 'valid'],
        self::OUTPUT_DIRLIST  => ['path', 'lastModified', 'valid'],
        self::OUTPUT_ERROR    => ['directory', 'filename', 'severity', 'errorType',
            'errorMessage'],
    ];
    static private finfo $fileInfo;

    static public function fromJson(string $json): self {
        $fi = new FileInfo();
        foreach (json_decode($json, true) as $k => $v) {
            $fi->$k = $v;
        }
        return $fi;
    }

    static public function fromDroid(array $data) {
        $fi                   = new FileInfo();
        $fi->path             = $data['FILE_PATH'];
        $fi->directory        = dirname($fi->path);
        $fi->filename         = basename($fi->path);
        $fi->type             = is_link($fi->path) ? self::DROID_TYPELINK : $data['TYPE'];
        $fi->size             = (int) $data['SIZE'];
        $fi->lastModified     = $data['LAST_MODIFIED'];
        $fi->extension        = $data['EXT'];
        $fi->mime             = preg_replace('/,.*/', '', $data['MIME_TYPE']);
        $fi->filesCount       = 0;
        $fi->droidId          = (int) $data['ID'];
        $fi->droidParentId    = (int) $data['PARENT_ID'];
        $fi->droidExtMismatch = $data['EXTENSION_MISMATCH'] === 'true';
        $fi->droidFormatCount = (int) $data['FORMAT_COUNT'];
        $fi->valid            = true;

        switch ($data['PUID']) {
            case 'x-fmt/280':
                $fi->specialType = self::SPECIAL_XSD;
                break;
        }


        return $fi;
    }

    static public function getCsvHeader(string $format): string {
        if (!isset(self::$outputColumns[$format])) {
            FileChecker::die("Unknown output format $format");
        }
        return implode(OutputFormatter::CSV_SEPARATOR, self::$outputColumns[$format]) . "\n";
    }

    public string $path;
    public string $directory;
    public string $type;
    public string $lastModified;
    public string $filename;
    public string $extension;
    public string $mime;
    public int $size;
    public int $filesCount;
    public bool $valid;
    public int $droidId;
    public int $droidParentId;
    public bool $droidExtMismatch;
    public int $droidFormatCount;
    public ?string $specialType = null;

    /**
     * 
     * @var array<Error>
     */
    public array $errors = [];

    public function error(string $errorType, string $errorMessage = ''): void {
        $this->errors[] = new Error(Error::SEVERITY_ERROR, $errorType, $errorMessage);
        $this->valid    = false;
    }

    public function warning(string $errorType, string $errorMessage = ''): void {
        $this->errors[] = new Error(Error::SEVERITY_WARNING, $errorType, $errorMessage);
        $this->valid    = false;
    }

    public function info(string $errorType, string $errorMessage = ''): void {
        $this->errors[] = new Error(Error::SEVERITY_INFO, $errorType, $errorMessage);
    }

    public function isValid(bool $skipWarnings): bool {
        if ($skipWarnings) {
            $callback = fn(Error $x) => $x->severity === Error::SEVERITY_ERROR;
        } else {
            $callback = fn(Error $x) => $x->severity !== Error::SEVERITY_INFO;
        }
        return array_sum(array_map($callback, $this->errors)) === 0;
    }

    public function isDir(): bool {
        return $this->type === self::DROID_TYPEDIR;
    }

    public function save(OutputFormatter $handle, ?string $format = null): void {
        // just dump object as it is
        if ($format === null) {
            $handle->write($this);
            return;
        }

        // filter
        $skip = match ($format) {
            self::OUTPUT_DIRLIST => $this->type !== self::DROID_TYPEDIR,
            self::OUTPUT_FILELIST => $this->type !== self::DROID_TYPEFILE,
            self::OUTPUT_ERROR => count($this->errors) === 0,
            default => false,
        };
        if ($skip) {
            return;
        }

        // extract columns
        $cols = self::$outputColumns[$format] ?? throw new RuntimeException("Unknown output format $format");
        $data = [];
        foreach ($cols as $i) {
            $data[$i] = $this->$i ?? '';
        }
        if ($format !== self::OUTPUT_ERROR) {
            $handle->write($data);
            return;
        }

        // OUTPUT_ERROR - as many output lines as errors
        foreach ($this->errors as $i) {
            $row = $data;
            foreach ((array) $i as $k => $v) {
                if (isset($row[$k])) {
                    $row[$k] = $v;
                }
            }
            $handle->write($row);
        }
    }
}
