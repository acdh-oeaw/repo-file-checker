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
    const ERR_REMOVED     = 'Removed';
    const ERR_SYSTEM_FILE = 'SystemFile';
    const FILE_LIST_SKIP  = [
        self::ERR_REMOVED,
        self::ERR_SYSTEM_FILE,
    ];

    /**
     * 
     * @var array<string, array<string>>
     */
    static private array $outputColumns = [
        self::OUTPUT_FILELIST => ['directory', 'filename', 'type', 'size', 'lastModified',
            'extension', 'mime', 'specialType', 'valid', 'hasCategory'],
        self::OUTPUT_DIRLIST  => ['path', 'lastModified', 'valid'],
        self::OUTPUT_ERROR    => ['directory', 'filename', 'severity', 'errorType',
            'errorMessage'],
    ];

    /**
     * 
     * @var array<string, array<string>>
     */
    static private array $hasCategoryByExt = [];

    /**
     * 
     * @var array<string, array<string>>
     */
    static private array $hasCategoryByMime = [];

    /**
     * 
     * @var array<string, array<string>>
     */
    static private array $hasCategoryByPuid = [];

    static public function fromJson(string $json): self {
        $fi = new FileInfo();
        foreach (json_decode($json, true) as $k => $v) {
            $fi->$k = $v;
        }
        return $fi;
    }

    static private function loadHasCategory(): void {
        if (count(self::$hasCategoryByExt) === 0) {
            $dir  = \Composer\InstalledVersions::getInstallPath('acdh-oeaw/arche-assets');
            $data = json_decode(file_get_contents($dir . '/AcdhArcheAssets/formats.json'));
            $data = array_filter($data, fn($x) => !empty($x->ARCHE_category));
            /** @var \stdClass $fd */
            foreach ($data as $fd) {
                foreach ($fd->extensions as $i) {
                    self::$hasCategoryByExt[$i][] = $fd->ARCHE_category;
                }
                foreach ($fd->MIME_type as $i) {
                    self::$hasCategoryByMime[$i][] = $fd->ARCHE_category;
                }
                if (!empty($fd->PRONOM_ID)) {
                    foreach (explode(',', $fd->PRONOM_ID) as $i) {
                        self::$hasCategoryByPuid[trim($i)][] = $fd->ARCHE_category;
                    }
                }
            }
            self::$hasCategoryByExt  = array_map(fn($x) => array_unique($x), self::$hasCategoryByExt);
            self::$hasCategoryByMime = array_map(fn($x) => array_unique($x), self::$hasCategoryByMime);
            self::$hasCategoryByPuid = array_map(fn($x) => array_unique($x), self::$hasCategoryByPuid);
        }
    }

    /**
     * 
     * @param array<string> $line
     * @param array<string> $header
     */
    static public function fromDroid(array $line, array $header): self {
        $countPos          = array_search('FORMAT_COUNT', $header);
        $formatHeader      = array_slice($header, $countPos + 1);
        $data              = array_combine(array_slice($header, 0, $countPos + 1), array_slice($line, 0, $countPos + 1));
        $fi                = new FileInfo();
        $fi->path          = $data['FILE_PATH'];
        $fi->directory     = dirname($fi->path);
        $fi->filename      = basename($fi->path);
        $fi->type          = is_link($fi->path) ? self::DROID_TYPELINK : $data['TYPE'];
        $fi->size          = (int) $data['SIZE'];
        $fi->lastModified  = $data['LAST_MODIFIED'];
        $fi->extension     = $data['EXT'];
        $fi->filesCount    = 0;
        $fi->droidId       = (int) $data['ID'];
        $fi->droidParentId = (int) $data['PARENT_ID'];
        $fi->mime          = '';
        for ($i = 0; $i < $data['FORMAT_COUNT']; $i++) {
            $formatData         = array_slice($line, $countPos + 1 + $i * count($formatHeader), count($formatHeader));
            $df                 = DroidFormat::fromDroid(array_combine($formatHeader, $formatData));
            $fi->droidFormats[] = $df;
            if (!empty($df->mime)) {
                $fi->mime = preg_replace('/,.*/', '', $df->mime);
            }
        }
        $fi->droidExtMismatch = $data['EXTENSION_MISMATCH'] === 'true';
        $fi->valid            = true;

        foreach ($fi->droidFormats as $i) {
            switch ($i->puid) {
                case 'x-fmt/280':
                    $fi->specialType = self::SPECIAL_XSD;
                    break;
            }
        }

        return $fi;
    }

    static public function getCsvHeader(string $format): string {
        if (!isset(self::$outputColumns[$format])) {
            throw new \RuntimeException("Unknown output format $format");
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
    public string $hasCategory;
    public int $droidId;
    public int $droidParentId;

    /**
     * 
     * @var array<DroidFormat>
     */
    public array $droidFormats  = [];
    public bool $droidExtMismatch;
    public string $droidPuid;
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

    public function assignHasCategory(): void {
        self::loadHasCategory();

        $matches = null;
        if (isset(self::$hasCategoryByExt[$this->extension ?? -1])) {
            $matches = self::$hasCategoryByExt[$this->extension];
        }
        if (isset(self::$hasCategoryByMime[$this->mime ?? -1])) {
            $tmp     = self::$hasCategoryByMime[$this->mime];
            $matches = $matches === null ? $tmp : array_intersect($matches, $tmp);
        }
        if (isset(self::$hasCategoryByPuid[$this->droidPuid ?? -1])) {
            $tmp     = self::$hasCategoryByPuid[$this->droidPuid];
            $matches = $matches === null ? $tmp : array_intersect($matches, $tmp);
        }
        $matches ??= [];
        if (count($matches) === 1) {
            $this->hasCategory = reset($matches);
        }
    }

    public function save(OutputFormatter $handle, ?string $format = null): void {
        // just dump object as it is
        if ($format === null) {
            $handle->write($this);
            return;
        } elseif ($format === FileInfo::OUTPUT_FILELIST && count(array_filter($this->errors, fn($x) => in_array($x['errorType'], self::FILE_LIST_SKIP))) > 0) {
            // don't include removed files in the file list output 
            // (https://github.com/acdh-oeaw/arche-metadata-crawler/issues/14)
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
