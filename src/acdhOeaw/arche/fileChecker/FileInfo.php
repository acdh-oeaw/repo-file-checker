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

    const UNKNOWN_MIME    = 'unknown';
    const OUTPUT_ERROR    = 'error';
    const OUTPUT_FILELIST = 'fileList';
    const OUTPUT_DIRLIST  = 'dirList';

    static private $outputColumns = [
        self::OUTPUT_FILELIST => ['directory', 'filename', 'type', 'size', 'lastModified',
            'extension', 'mime', 'valid'],
        self::OUTPUT_DIRLIST  => ['path', 'lastModified', 'valid'],
        self::OUTPUT_ERROR    => ['directory', 'filename', 'severity', 'errorType',
            'errorMessage'],
    ];
    static private finfo | bool $finfo;

    static public function fromJson(string $json): self {
        $fi = new FileInfo();
        foreach (json_decode($json, true) as $k => $v) {
            $fi->$k = $v;
        }
        return $fi;
    }

    static public function fromPath(string $path): self {
        if (!isset(self::$finfo)) {
            try {
                self::$finfo = new finfo(FILEINFO_MIME_TYPE);
            } catch (Exception $ex) {
                echo "Failed to instantiate the finfo object. Falling back to mime_content_type() for getting file's MIME type.\n";
                self::$finfo = false;
            }
        }

        $fi               = new FileInfo();
        $fi->path         = $path;
        $fi->directory    = dirname($path);
        $fi->filename     = basename($path);
        $fi->type         = filetype($path);
        $fi->size         = 0;
        $fi->lastModified = date("Y-m-d H:i:s", filemtime($path));
        if ($fi->type === 'file') {
            $fi->size      = filesize($path);
            $fi->extension = strtolower(substr($path, strrpos($path, '.') + 1));
            $fi->mime      = (self::$finfo ? self::$finfo->file($path) : mime_content_type($path)) ?: self::UNKNOWN_MIME;

            // hacks for formats which can't be reliably recognized
            if ($fi->mime === 'text/plain') {
                $fi->mime = match ($fi->extension) {
                    'csv' => 'text/csv',
                    'tsv' => 'text/tsv',
                    default => 'text/plain'
                };
            } elseif ($fi->mime === 'application/octet-stream' && $fi->extension === 'docx') {
                self::recognizeDocx($fi);
            }
        } elseif ($fi->type === 'dir') {
            $fi->filesCount = 0;
        }
        return $fi;
    }

    static private function recognizeDocx(self $fileInfo): void {
        $archive = new ZipArchive();
        $archive->open($fileInfo->path);
        if ($archive->locateName('[Content_Types].xml') !== false) {
            for ($i = 0; $i < $archive->count(); $i++) {
                if (str_starts_with($archive->statIndex($i)['name'], 'word/')) {
                    $fileInfo->mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                    break;
                }
            }
        }
        $archive->close();
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

    /**
     * 
     * @var array<Error>
     */
    public array $errors = [];

    public function error(string $errorType, string $errorMessage = ''): void {
        $this->errors[] = new Error(Error::SEVERITY_ERROR, $errorType, $errorMessage);
    }

    public function warning(string $errorType, string $errorMessage = ''): void {
        $this->errors[] = new Error(Error::SEVERITY_WARNING, $errorType, $errorMessage);
    }

    public function info(string $errorType, string $errorMessage = ''): void {
        $this->errors[] = new Error(Error::SEVERITY_INFO, $errorType, $errorMessage);
    }

    public function save(OutputFormatter $handle, ?string $format = null): void {
        // just dump object as it is
        if ($format === null) {
            $handle->write($this);
            return;
        }

        // filter
        $skip = match ($format) {
            self::OUTPUT_DIRLIST => $this->type !== 'dir',
            self::OUTPUT_FILELIST => $this->type !== 'file',
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
            $data[$i] = match ($i) {
                'valid' => count($this->errors) === 0,
                default => $this->$i ?? '',
            };
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
