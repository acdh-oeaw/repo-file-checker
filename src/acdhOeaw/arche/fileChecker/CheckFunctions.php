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

use DOMDocument;
use PharData;
use SimpleXMLElement;
use UnexpectedValueException;
use ZipArchive;
use whikloj\BagItTools\Bag;
use acdhOeaw\arche\fileChecker\attributes\CheckFile;
use acdhOeaw\arche\fileChecker\attributes\CheckDir;

class CheckFunctions {

    const VERAPDF_PATH    = __DIR__ . '/../../../../aux/verapdf/verapdf';
    const DROID_PATH      = __DIR__ . '/../../../../aux/droid/droid.sh';
    const SIGNATURES_DIR  = __DIR__ . '/../../../../aux/droid/user/signature_files/';
    const BAGIT_REGEX     = '/^BagIt-Version: [0-9]+[.][0-9]+/';
    const FILES_TO_REMOVE = ['Thumbs.db', '.DS_Store'];

    /**
     * 
     * @var array<string, string>
     */
    private array $archeFormatsByExtension = [];

    /**
     * 
     * @var array<string, string>
     */
    private array $archeFormatsByMime = [];

    /**
     * 
     * @var array<int, array<string>>
     */
    private array $bom           = [];
    private string $tmpDir;
    private string $gdalCalcPath = '/usr/bin/gdal_calc.py';
    private string $gdalinfoPath = '/usr/bin/gdalinfo';

    /**
     * 
     * @param array<string, mixed> $cfg
     */
    public function __construct(array $cfg) {
        $this->tmpDir = $cfg['tmpDir'];
        if (isset($cfg['gdalCalcPath'])) {
            $this->gdalCalcPath = $cfg['gdalCalcPath'];
        }
        if (!file_exists($this->gdalCalcPath)) {
            $this->gdalCalcPath = '';
            echo "WARNING: gdal_calc.py not found, images won't be checked for corruption\n";
        }
        if (isset($cfg['gdalinfoPath'])) {
            $this->gdalinfoPath = $cfg['gdalinfoPath'];
        }
        if (!file_exists($this->gdalinfoPath)) {
            $this->gdalinfoPath = '';
            echo "WARNING: gdalinfo not found, images won't be checked for compression and projection\n";
        }
        if (!file_exists(self::DROID_PATH) || !file_exists(self::VERAPDF_PATH) || count(scandir(self::SIGNATURES_DIR)) < 3) {
            exec(__DIR__ . '/../../../../aux/install_deps.sh 2>&1', $output, $ret);
            if ($ret !== 0) {
                throw new FileCheckerException("External tools installation failed with:\n" . implode("\n", $output) . "\n");
            }
        }

        // read file formats from arche-assets
        // in case of extension/mime conflicts be conservative and assign "accepted" instead of "preferred"
        foreach (\acdhOeaw\ArcheFileFormats::getAll() as $i) {
            foreach ($i->extensions as $j) {
                $this->archeFormatsByExtension[$j] = min($i->ARCHE_conformance, $this->archeFormatsByExtension[$j] ?? 'preferred');
            }
            foreach ($i->MIME_type as $j) {
                $this->archeFormatsByMime[$j] = max($i->ARCHE_conformance, $this->archeFormatsByMime[$j] ?? '');
            }
        }

        // https://en.wikipedia.org/wiki/Byte_order_mark#Byte_order_marks_by_encoding
        // constants but impossible to set as such because of non-character values
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

    #[CheckDir]
    public function check01ValidDirname(FileInfo $fi): void {
        if (preg_match('/^(?:[A-Za-z0-9][-_A-Za-z0-9]*[A-Za-z0-9]|[A-Za-z0-9]{1,2})$/', $fi->filename) !== 1) {
            $fi->error("Invalid filename");
        }
    }

    #[CheckDir]
    public function check02EmptyDir(FileInfo $fi): void {
        if ($fi->filesCount === 0) {
            $fi->error("Empty directory");
        }
    }

    #[CheckFile]
    public function check00RemoveFiles(FileInfo $fi): void {
        if (in_array($fi->filename, self::FILES_TO_REMOVE)) {
            unlink($fi->path);
            $fi->info('SystemFile', 'File removed');
            throw new LastCheckException();
        }
    }

    #[CheckFile]
    public function check01ValidFilename(FileInfo $fi): void {
        if (preg_match('/^(?:[A-Za-z0-9][-_A-Za-z0-9]*[A-Za-z0-9]|[A-Za-z0-9]{0,2})[.][A-Za-z0-9]+$/', $fi->filename) !== 1) {
            $fi->error("Invalid filename");
        }
    }

    #[CheckFile]
    public function check02Type(FileInfo $fi): void {
        if ($fi->type !== FileInfo::DROID_TYPEDIR && $fi->type !== FileInfo::DROID_TYPEFILE) {
            $fi->error('Wrong file type', "File type: $fi->type");
            throw new LastCheckException();
        }
    }

    #[CheckFile]
    public function check02ZipArchive(FileInfo $fi): void {
        if ($fi->mime !== 'application/zip') {
            return;
        }
        $za  = new ZipArchive();
        $res = $za->open($fi->path);
        if ($res !== true) {
            $error = $this->getZipError($za, $res);
            $fi->error("Zip", $error);
            throw new LastCheckException();
        }
        $filesCount = $za->count();
        for ($i = 0; $i < $filesCount; $i++) {
            $res = $za->getStream($za->getNameIndex($i)); // ZipFile::getStreamName() introuduced only in PHP 8.2
            if ($res === false) {
                $error = $za->getStatusString();
                if ($error === 'No password provided') {
                    $fi->error("Password protected file");
                } else {
                    $fi->error("Zip", $za->getStatusString());
                }
                throw new LastCheckException();
            }
        }
    }

    /**
     * Checks if odt/ods/docx/xlsx files are password protected
     */
    #[CheckFile]
    public function check03PswdProtected(FileInfo $fi): void {
        if (in_array($fi->extension, ['odt', 'ods']) && $fi->mime === 'application/zip') {
            $za  = new ZipArchive();
            $za->open($fi->path);
            $res = $za->getStream('META-INF/manifest.xml');
            if ($res === false) {
                $fi->error($fi->extension, "Error reading META-INF/manifest.xml: " . $za->getStatusString());
                $za->close();
                throw new LastCheckException();
            }
            $xml       = new SimpleXMLElement(fread($res, 10485760));
            $xml->registerXPathNamespace('manifest', 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0');
            $encrypted = $xml->xpath('//manifest:encryption-data');
            if (is_array($encrypted) && count($encrypted) > 0) {
                $fi->error("Password protected file");
                $za->close();
                throw new LastCheckException();
            }
            $za->close();
        }
        if (in_array($fi->extension, ['docx', 'xlsx']) && empty($fi->mime)) {
            $za  = new ZipArchive();
            $res = $za->open($fi->path);
            if ($res !== true) {
                $error = $this->getZipError($za, $res);
                if ($error === 'Not a zip archive') {
                    $fi->error('Password protected file', "Or not a $fi->extension file at all");
                } else {
                    $fi->error($fi->extension, $error);
                }
                throw new LastCheckException();
            }
            $za->close();
        }
    }

    /**
     * Check the extension is valid for a given MIME type according to the DROID.
     */
    #[CheckFile]
    public function check04MimeMeetsExtension(FileInfo $fi): void {
        if (count($fi->droidFormats) === 0) {
            if ($fi->extension === 'xml') {
                $this->check10Xml($fi, true);
            } else {
                $fi->error('Unknown MIME', 'Content type not recognized by the DROID');
            }
            throw new LastCheckException();
        } elseif (count($fi->droidFormats) > 1) {
            $fi->error('Unknown MIME', 'Multiple content types recognized by the DROID');
            throw new LastCheckException();
        }

        static $except = ['tgz|application/gzip'];
        if ($fi->droidExtMismatch && !in_array($fi->extension . '|' . $fi->mime, $except)) {
            $fi->error('MIME/extension mismatch', 'Extension: ' . $fi->extension . ', MIME type: ' . $fi->mime);
            throw new LastCheckException();
        }
    }

    /**
     * Checks XML files:
     * 
     * - loads the file with DTD validation turned on
     * - checks if the file contains XML declaration
     * - checks if the root element defines a schema location for its own namespace
     * - if the schema location is provided, validates against the schema
     */
    #[CheckFile]
    public function check10Xml(FileInfo $fi, bool $force = false): void {
        static $xmlMime = ['text/xml', 'application/xml', 'application/tei+xml'];
        static $xmlSkip = [FileInfo::SPECIAL_XSD];
        if (!$force && (!in_array($fi->mime, $xmlMime) || in_array($fi->specialType, $xmlSkip))) {
            return;
        }
        $prev = libxml_use_internal_errors(true);
        $xml  = new DOMDocument();

        // parse with DTD validation turned on
        $res = $xml->load($fi->path, LIBXML_DTDLOAD | LIBXML_DTDVALID | LIBXML_BIGLINES | LIBXML_COMPACT);
        if ($res === false) {
            $fi->error("Unknown MIME", "Failed to parse the XML file with: " . print_r(libxml_get_last_error(), true));
            return;
        }

        // basic checks
        if (!in_array($fi->mime, $xmlMime)) {
            $fi->error("XML", "Missing XML declaration");
        } elseif (empty($xml->encoding)) {
            $fi->warning("XML", "Encoding not defined");
        }

        $valid = [];
        if ($xml->doctype !== null) {
            $valid[] = $xml->validate();
        }
        foreach ($xml->childNodes as $child) {
            // https://www.w3.org/TR/xml-model/
            if ($child instanceof \DOMProcessingInstruction && $child->nodeName === 'xml-model') {
                preg_match('`href="([^"]+)"`', $child->nodeValue, $href);
                $href = $href[1] ?? '';
                preg_match('`type="([^"]+)"`', $child->nodeValue, $type);
                $type = $type[1] ?? '';
                preg_match('`schematypens="([^"]+)"`', $child->nodeValue, $ns);
                $ns   = $ns[1] ?? '';
                if (!empty($href)) {
                    $fn = false;
                    if (str_ends_with(strtolower($href), '.rng') || $type === 'application/relax-ng-compact-syntax' || $ns === 'http://relaxng.org/ns/structure/1.0') {
                        $fn = 'relaxNGValidateSource';
                    }
                    if (str_ends_with(strtolower($href), '.xsd') || $ns === 'http://www.w3.org/2001/XMLSchema') {
                        $fn = 'schemaValidateSource';
                    }
                    if ($fn) {
                        if (!str_starts_with(strtolower($href), 'http')) {
                            $href = $fi->directory . '/' . $href;
                            if (!file_exists($href)) {
                                $fi->error('XML', "Failed to read schema from $href");
                                continue;
                                ;
                            }
                        }
                        $schema = @file_get_contents($href);
                        if ($schema === false) {
                            $fi->error('XML', "Failed to read schema from $href");
                            continue;
                        }
                        $res = $xml->$fn($schema);
                        if ($res) {
                            $fi->info('XML', "Schema successfully validated against $href");
                        } else {
                            $fi->error('XML', "Schema validation against $href failed with: " . print_r(libxml_get_last_error(), true));
                        }
                        $valid[] = $res;
                    }
                }
            }
        }
        if (count($valid) === 0) {
            $fi->warning('XML', "Schema not defined");
        }

        libxml_use_internal_errors($prev);
    }

    /**
     * 
     * Check the PDF version and try to open to be sure that is doesnt have any pwd
     */
    #[CheckFile]
    public function check11PdfFile(FileInfo $fi): void {
        if ($fi->mime !== 'application/pdf') {
            return;
        }
        $cmd     = sprintf(
            "%s --format json %s 2>/dev/null",
            escapeshellcmd(self::VERAPDF_PATH),
            escapeshellarg($fi->path)
        );
        $output  = [];
        $retCode = null;
        exec($cmd, $output, $retCode);
        $result  = json_decode(implode("\n", $output)) ?: null;
        $result  = $result?->report?->jobs;
        if (isset($result[0]->validationResult)) {
            $result = $result[0]->validationResult;
            if ($result->compliant ?? false) {
                //$fi->info("PDF", "Compliant with the " . $result->profileName);
            } else {
                foreach ($result->details?->ruleSummaries ?? [] as $i) {
                    $fi->error("PDF", "PDF/A rule $i->clause violated: $i->description");
                }
            }
        } elseif (isset($result[0]->taskException)) {
            $error = $result[0]->taskException->exception;
            if ($error === 'The PDF stream appears to be encrypted.') {
                $fi->error('Password protected file');
                throw new LastCheckException();
            } else {
                $fi->error("PDF", $error);
            }
        }
    }

    #[CheckFile]
    public function check12Bom(FileInfo $fi): void {
        if (!str_starts_with($fi->mime, 'text/')) {
            return;
        }
        $fh      = fopen($fi->path, 'r');
        $content = fread($fh, 4);
        fclose($fh);
        if (in_array($content, $this->bom[4]) || in_array(substr($content, 0, 3), $this->bom[3]) || in_array(substr($content, 0, 2), $this->bom[2])) {
            $fi->error('File contains Byte Order Mark');
        }
    }

    /**
     * Verifies if an image has no errors.
     * 
     * Unfortunatelly there's no good PHP library for that - gd doesn't support TIFF
     * and Imagick just reads whatever it can without throwing any error (even on
     * extremally corrupted images).
     * 
     * The best tool turns out to be any GDAL script. They can handle not-geo-referenced
     * images and complain on any errors. Of course this requires the runtime
     * environment to provide gdal-bin :(
     * 
     * @param FileInfo $fi
     * @return void
     */
    #[CheckFile]
    public function check13RasterImage(FileInfo $fi): void {
        static $imgMime = ['image/tiff', 'image/png', 'image/jpeg', 'image/gif',
            'image/webp'];
        if (!in_array($fi->mime, $imgMime)) {
            return;
        }
        $tmpfile = escapeshellarg("$this->tmpDir/tmp.tif");

        if (!empty($this->gdalCalcPath)) {
            $cmd     = sprintf(
                "%s --overwrite --quiet -A %s --calc A --outfile %s --co COMPRESS=LZW 2>&1",
                escapeshellcmd($this->gdalCalcPath),
                escapeshellarg($fi->path),
                $tmpfile
            );
            $output  = [];
            $retCode = null;
            exec($cmd, $output, $retCode);
            if (file_exists($tmpfile)) {
                unlink($tmpfile);
            }
            if ($retCode !== 0) {
                // not reliable due to different reporting on different gdal and python versions
                //$msg = implode("\n", array_filter($output, fn($x) => str_starts_with($x, 'ERROR')));
                $fi->error("Corrupted image", '');
                // do not perform furhter checks if an image is corruptued
                return;
            }
        }

        if (!empty($this->gdalinfoPath)) {
            $cmd    = $this->gdalinfoPath . ' ' . escapeshellarg($fi->path);
            $output = [];
            exec($cmd, $output, $retCode);
            if ($retCode === 0) {
                $output = implode("\n", $output);
                if ($fi->mime === 'image/tiff' && !preg_match('/COMPRESSION=/', $output)) {
                    $fi->error('Uncompressed TIFF', '');
                } elseif ($fi->mime === 'image/tiff' && !preg_match('/COMPRESSION=LZW/', $output)) {
                    $fi->warning('Compression other than LZW', '');
                }

                $geoTransform  = (int) preg_match('/Origin =/', $output);
                $geoProjection = (int) preg_match('/Coordinate System/', $output);
                if ($geoTransform + $geoProjection === 1) {
                    $fi->error("Geo data", $geoTransform === 1 ? 'Image has geo transformation data but lacks projection data' : 'Image has projection data but lacks transformation data');
                }
            }
        }
        
        $exif = @exif_read_data($fi->path, 'ifd0');
        if (is_array($exif) && ($exif['Orientation'] ?? 1) !== 1) {
            $fi->warning('Rotated image', 'The EXIF metadata indicate the image is rotated');
        }
    }
    
    /**
     * Checks the bagit file, and if there is an error then add it to the errors variable
     */
    #[CheckFile]
    #[CheckDir]
    public function check80BagitFile(FileInfo $fi): void {
        $isBagIt = false;
        $root    = '';
        if ($fi->type === FileInfo::DROID_TYPEDIR) {
            if (file_exists($fi->path . '/bagit.txt')) {
                $isBagIt = preg_match(self::BAGIT_REGEX, file_get_contents($fi->path . '/bagit.txt', false, null, 0, 1000)) === 1;
                if ($isBagIt) {
                    $root = $fi->path;
                }
            }
        } elseif (in_array($fi->extension, ['zip', 'tgz', 'gz', 'bz2', 'tar'])) {
            try {
                $archive = new PharData($fi->path);
                foreach ($archive->getChildren() as $file) {
                    if ($file->getFilename() === 'bagit.txt') {
                        $isBagIt = preg_match(self::BAGIT_REGEX, $file->openFile()->fread(1000));
                        break;
                    }
                }
            } catch (UnexpectedValueException) {
                
            }
        }
        if (!$isBagIt) {
            return;
        }
        $fi->info("BagIt", "BagIt bag recognized");
        $fi->specialType = FileInfo::SPECIAL_BAGIT;
        if (isset($archive)) {
            $root = $this->tmpDir . '/' . $archive->getFilename();
            $archive->extractTo($this->tmpDir);
        }
        try {
            $bag       = Bag::load($root);
            $fi->valid = $bag->isValid();

            foreach ($bag->getErrors() as $i) {
                $fi->error("BagIt", $i['file'] . ': ' . $i['message']);
            }
            foreach ($bag->getWarnings() as $i) {
                $fi->warning("BagIt", $i['file'] . ': ' . $i['message']);
            }
        } finally {
            if (str_starts_with($root, $this->tmpDir)) {
                system("rm -fR '$root'");
            }
        }
    }

    #[CheckFile]
    public function check99AcceptedByArche(FileInfo $fi): void {
        if (!empty($fi->specialType)) {
            // BagIt, TEI-XML, etc. can not be properly checked based on the arche-assets
            return;
        }
        $ext   = $this->archeFormatsByExtension[$fi->extension] ?? null;
        $mime  = $this->archeFormatsByMime[$fi->mime] ?? null;
        $valid = min($ext, $mime);
        $ext   = !empty($ext) ? $ext : 'not accepted';
        $mime  = !empty($mime) ? $mime : 'not accepted';
        if ($valid === null || $valid === '') {
            $fi->error("Format not accepted", "MIME $fi->mime: $mime, extension $fi->extension: $ext");
        } elseif ($valid === 'accepted') {
            $fi->warning("Format not preferred", "MIME $fi->mime: $mime, extension $fi->extension: $ext");
        }
    }

    private function getZipError(ZipArchive $za, bool | int $res): string {
        return match ($res) {
            ZipArchive::ER_EXISTS => 'File already exists',
            ZipArchive::ER_INCONS => 'Archive inconsistent',
            ZipArchive::ER_INVAL => 'Invalid argument',
            ZipArchive::ER_MEMORY => 'Malloc failure',
            ZipArchive::ER_NOENT => 'No such file',
            ZipArchive::ER_NOZIP => 'Not a zip archive',
            ZipArchive::ER_OPEN => 'Can not open file',
            ZipArchive::ER_READ => 'Read error',
            ZipArchive::ER_SEEK => 'Seek error',
            false => $za->getStatusString(),
            default => 'Other error',
        };
    }
}
