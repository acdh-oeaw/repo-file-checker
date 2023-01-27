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

use whikloj\BagItTools\Bag;

class CheckFunctions {

    const HASH_FALLBACK = 'sha1';
    const HASH_DEFAULT  = 'xxh128';

    /**
     * Process the pronom xml for the MIMEtypes
     * 
     * @param string $file
     * @return array<string>
     */
    static private function getMimeFromPronom(string $file): array {
        $xml = simplexml_load_file($file);
        if ($xml === false) {
            FileChecker::die("Failed to read signatures file $file");
        }
        $extArray = [];

        // - jeden format może mieć wiele Extension
        // - różne formaty mogą zgłaszać takie samo Extension

        foreach ($xml->FileFormatCollection->FileFormat as $i) {
            $mime = mb_strtolower($i->attributes()->MIMEType[0] ?? '');
            if (!empty($mime)) {
                foreach ($i->Extension as $ext) {
                    $ext = mb_strtolower((string) $ext);
                    if (!isset($extArray[$ext])) {
                        $extArray[$ext] = [];
                    }
                    $extArray[$ext][] = $mime;
                }
            }
        }
        $extArray = array_map(fn($x) => array_unique($x), $extArray);
        return $extArray;
    }

    /**
     * 
     * @var array<string>
     */
    private array $blackList = [];

    /**
     * 
     * @var array<string, array<string>>
     */
    private array $mimeTypes = [];
    private string $hashAlgo;

    /**
     * 
     * @var array<int, array<string>>
     */
    private array $bom = [];

    /**
     * 
     * @param array<string, mixed> $cfg
     */
    public function __construct(array $cfg) {
        //blacklist
        $bl              = $cfg['blackList'];
        $this->blackList = array_map(fn($x) => mb_strtolower(trim($x)), $bl);

        $files = scandir($cfg['signatureDir']);
        $files = array_filter($files, fn($x) => str_ends_with(mb_strtolower($x), '.xml'));
        if (count($files) === 0) {
            FileChecker::die("Can't read signatures file - the signature directory is empty\n");
        }
        sort($files);
        $this->mimeTypes = self::getMimeFromPronom($cfg['signatureDir'] . '/' . end($files));
        if (count($this->mimeTypes) === 0) {
            FileChecker::die("Reading signatures file failed");
        }

        $this->hashAlgo = $cfg['hashAlgo'] ?? self::HASH_DEFAULT;
        if (!in_array($this->hashAlgo, hash_algos())) {
            echo "Hashing algorithm $hash->algo unavailable, falling back to " . self::HASH_FALLBACK . "\n\n";
            $this->hashAlgo = self::HASH_FALLBACK;
        }

        // https://en.wikipedia.org/wiki/Byte_order_mark#Byte_order_marks_by_encoding
        $this->bom = [
            2 => [
                chr(254) . chr(255), // UTF-16 BE
                chr(255) . chr(254), // UTF-16 BE
            ],
            3 => [
                chr(239) . chr(187) . chr(191), // UTF-8
                chr(43) . chr(47) . chr(118), // UTF-7
                chr(247) . chr(100) . chr(76), // UTF-1
                chr(14) . chr(254) . chr(255), // SCSU
                chr(251) . chr(238) . chr(40), // BOCU-1
            ],
            4 => [
                chr(0) . chr(0) . chr(254) . chr(255), // UTF-32 BE
                chr(254) . chr(255) . chr(0) . chr(0), // UTF-32 LE
                chr(221) . chr(115) . chr(102) . chr(115), // UTF-EBCDIC
                chr(132) . chr(49) . chr(149) . chr(51), // GB18030
            ],
        ];
    }

    /**
     * 
     * Checks the bagit file, and if there is an error then add it to the errors variable
     * 
     * @param string $filename
     * @return array<Error>
     */
    public function checkBagitFile(string $filename, bool $verbose = true): array {
        $bag   = Bag::load($filename);
        $valid = $bag->isValid();

        $issues = $bag->getErrors();
        if ($verbose) {
            $issues = array_merge($issues, $bag->getWarnings());
        }

        $dir      = dirname($filename);
        $filename = basename($filename);
        $issues   = array_map(
            fn($x) => new Error(Error::SEVERITY_ERROR, 'BagIt_Error', $x['file'] . ': ' . $x['message']),
                                $issues
        );

        return $issues;
    }

    /**
     * 
     * Checks the Directory Name validation
     * 
     * @param string $dir
     * @return bool
     */
    public function checkDirectoryNameValidity(string $dirname): bool {
        return preg_match('/[^-A-Za-z0-9_().]/', $dirname) === 0;
    }

    /**
     * 
     * Checks the filename validation
     * 
     * @param string $filename
     * @return bool : true
     */
    public function checkFileNameValidity(string $filename): bool {
        return preg_match('/[^-A-Za-z0-9_().]/', $filename) === 0;
    }

    /**
     * 
     * Check the blacklisted elements
     * 
     * @param string $extension
     */
    public function checkBlackListFile(string $extension): bool {
        return in_array(mb_strtolower($extension), $this->blackList);
    }

    /**
     * 
     * Check the extension is valid for a given MIME type according to the
     * definitions read from the DROID signatures file.
     * 
     */
    public function checkMimeTypes(string $extension, string $mime): ?Error {
        $validMime = $this->mimeTypes[mb_strtolower($extension)] ?? [];
        return in_array(mb_strtolower($mime), $validMime) ? null : new Error(Error::SEVERITY_ERROR, "Extension doesn't match MIME type", "Extension: $extension, MIME type: $mime, allowed MIME types: " . implode(', ', $validMime));
    }

    /**
     * 
     * Check the PDF version and try to open to be sure that is doesnt have any pwd
     * 
     * @param string $file
     * @return Error|null
     */
    public function checkPdfFile(string $file): ?Error {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $parser->parseFile($file);
        } catch (\Exception $ex) {
            return new Error(Error::SEVERITY_ERROR, "PDF error", $ex->getMessage());
        }
        return null;
    }

    /**
     * 
     * Checks the zip file, we extract them to know if it is pwd protected or not
     * 
     * If we have a pw protected one then we will put it to the $pwZips array
     * 
     */
    public function checkZipFile(string $zipFile): ?Error {
        $za = new \ZipArchive();
        if ($za->open($zipFile) !== TRUE) {
            return new Error(Error::SEVERITY_ERROR, "Zip_Open_Error");
        }
        $filesCount = $za->count();
        for ($i = 0; $i < $filesCount; $i++) {
            $res = $za->getStream($za->statIndex($i)['name']);
            if ($res === false) {
                return new Error(Error::SEVERITY_ERROR, "Zip_Error", $za->getStatusString());
            }
        }
        return null;
    }

    public function checkAcceptedByArche(string $mime): ?Error {
        $def = \acdhOeaw\ArcheFileFormats::getByMime($mime);
        if ($def === null || empty($def->Long_term_format)) {
            return new Error(Error::SEVERITY_ERROR, 'File format not accepted by ARCHE');
        }
        if ($def->Long_term_format === 'yes') {
            return null;
        }
        return match ($def->Long_term_format) {
            'unsure' => new Error('WARNING', 'Not sure if the file format is accepted by ARCHE'),
            'restricted' => new Error('WARNING', 'Restricted file format'),
            default => new Error(Error::SEVERITY_ERROR, 'File format not accepted by ARCHE'),
        };
    }

    public function checkBom(string $path): bool {
        $fh      = fopen($path, 'r');
        $content = fread($fh, 4);
        fclose($fh);
        return !(in_array($content, $this->bom[4]) || in_array(substr($content, 0, 3), $this->bom[3]) || in_array(substr($content, 0, 2), $this->bom[2]));
    }

    public function computeHash(string $path): string {
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
