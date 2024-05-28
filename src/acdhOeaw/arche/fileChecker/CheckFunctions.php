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

    const VERAPDF_PATH   = __DIR__ . '/../../../../aux/verapdf/bin/verapdf';
    const DROID_PATH     = __DIR__ . '/../../../../aux/droid/droid.sh';
    const SIGNATURES_DIR = __DIR__ . '/../../../../aux/droid/user/signature_files/';
    const BAGIT_REGEX    = '/^BagIt-Version: [0-9]+[.][0-9]+/';

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

        foreach ($xml->FileFormatCollection->FileFormat as $i) {
            $mime = mb_strtolower($i->attributes()->MIMEType[0] ?? '');
            if (!empty($mime)) {
                $mime = array_map(fn($x) => trim($x), explode(',', $mime));
                foreach ($i->Extension as $ext) {
                    $ext            = mb_strtolower((string) $ext);
                    $extArray[$ext] = array_merge($extArray[$ext] ?? [], $mime);
                }
            }
        }
        $extArray = array_map(fn($x) => array_unique($x), $extArray);
        return $extArray;
    }

    /**
     * 
     * @var array<string, array<string>>
     */
    private array $extToMime = [];

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
    private array $bom          = [];
    private string $tmpDir;
    private string $gdalCalcPath = '/usr/bin/gdal_calc.py';

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
        if (!file_exists(self::DROID_PATH) || !file_exists(self::VERAPDF_PATH) || count(scandir(self::SIGNATURES_DIR)) < 3) {
            exec(__DIR__ . '/../../../../aux/install_deps.sh 2>&1', $output, $ret);
            if ($ret !== 0) {
                FileChecker::die("External tools installation failed with:\n" . implode("\n", $output) . "\n");
            }
        }

        // read PRONOM
        $files = scandir(self::SIGNATURES_DIR);
        $files = array_filter($files, fn($x) => str_ends_with(mb_strtolower($x), '.xml'));
        if (count($files) === 0) {
            FileChecker::die("Can't read signatures file - the signature directory is empty\n");
        }
        sort($files);
        $this->extToMime = self::getMimeFromPronom(self::SIGNATURES_DIR . '/' . end($files));
        if (count($this->extToMime) === 0) {
            FileChecker::die("Reading signatures file failed");
        }
        // hacks
        $this->extToMime['tsv'][]     = 'text/tsv';
        $this->extToMime['geojson'][] = 'application/json';
        $this->extToMime['avif']      = ['image/avif'];
        $this->extToMime['webp']      = ['image/webp'];

        // read file formats from arche-assets
        // in case of extension/mime conflicts be conservative and assign "accepted" instead of "preferred"
        foreach (\acdhOeaw\ArcheFileFormats::getAll() as $i) {
            foreach ($i->extensions as $j) {
                $this->archeFormatsByExtension[$j] = min($i->ARCHE_conformance, $this->archeFormatsByExtension[$j] ?? 'preferred');
            }
            foreach ($i->MIME_type as $j) {
                $this->archeFormatsByMime[$j] = min($i->ARCHE_conformance, $this->archeFormatsByMime[$j] ?? 'preferred');
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
    public function checkEmptyDir(FileInfo $fi): void {
        if (count(scandir($fi->path)) <= 2) {
            $fi->error("Empty directory");
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
    public function checkRasterImage(FileInfo $fi): void {
        static $imgMime = ['image/tiff', 'image/png', 'image/jpeg', 'image/gif'];
        if (!empty($this->gdalCalcPath) && !in_array($fi->mime, $imgMime)) {
            return;
        }
        $tmpfile = escapeshellarg("$this->tmpDir/tmp.tif");
        $cmd     = sprintf(
            "%s --overwrite --quiet -A %s --calc A --outfile %s --co COMPRESS=LZW 2>&1",
            escapeshellcmd($this->gdalCalcPath),
            escapeshellarg($fi->path),
            $tmpfile
        );
        $output  = [];
        $retCode = null;
        exec($cmd, $output, $retCode);
        if ($retCode !== 0) {
            $msg = implode("\n", array_filter($output, fn($x) => str_starts_with($x, 'ERROR')));
            $fi->error("Corrupted image", $msg);
        }
        if (file_exists($tmpfile)) {
            unlink($tmpfile);
        }
    }

    /**
     * Checks the bagit file, and if there is an error then add it to the errors variable
     * 
     * @param string $filename
     * @return array<Error>
     */
    #[CheckFile]
    #[CheckDir]
    public function checkBagitFile(FileInfo $fi): void {
        $isBagIt = false;
        if ($fi->type === 'dir') {
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
        if (isset($archive)) {
            $root = $this->tmpDir . '/' . $archive->getFilename();
            $archive->extractTo($this->tmpDir);
        }
        try {
            $bag   = Bag::load($root);
            $valid = $bag->isValid();

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

    #[CheckDir]
    public function checkValidDirname(FileInfo $fi): void {
        if (preg_match('/^[A-Za-z0-9][-_A-Za-z0-9]*[A-Za-z0-9]$/', $fi->filename) !== 1) {
            $fi->error("Invalid filename");
        }
    }

    #[CheckFile]
    public function checkValidFilename(FileInfo $fi): void {
        if (preg_match('/^[A-Za-z0-9][-_A-Za-z0-9]*[A-Za-z0-9][.][A-Za-z0-9]+$/', $fi->filename) !== 1) {
            $fi->error("Invalid filename");
        }
    }

    /**
     * Check the extension is valid for a given MIME type according to the
     * definitions read from the DROID signatures file.
     * 
     * Look at the class constructor to check sam hacks against DROID/finfo
     * MIME type recognition mismatches
     */
    #[CheckFile]
    public function checkMimeMeetsExtension(FileInfo $fi): void {
        $mime      = mb_strtolower($fi->mime);
        $validMime = $this->extToMime[$fi->extension] ?? [];
        if (!in_array($mime, $validMime)) {
            $fi->error("File content doesn't match extension", "Extension: $fi->extension, MIME type: $fi->mime, MIME types allowed for this extension: " . implode(', ', $validMime));
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
    public function checkXml(FileInfo $fi): void {
        if (!in_array($fi->mime, ['text/xml', 'application/xml']) && !($fi->mime === 'text/plain' && $fi->extension === 'xml')) {
            return;
        }
        $prev = libxml_use_internal_errors(true);
        $xml  = new DOMDocument();

        // parse with DTD validation turned on
        $res = $xml->load($fi->path, LIBXML_DTDLOAD | LIBXML_DTDVALID | LIBXML_BIGLINES | LIBXML_COMPACT);
        if ($res === false) {
            $fi->error("XML validation", "Failed to parse the XML file with: " . print_r(libxml_get_last_error(), true));
            return;
        }

        // basic checks
        if ($fi->mime === "text/plain") {
            $fi->error("XML validation", "Missing XML declaration");
        } elseif (empty($xml->encoding)) {
            $fi->warning("XML validation", "Encoding not defined");
        }

        $valid = [];
        if ($xml->doctype !== null) {
            $valid[] = $xml->validate();
        }
        foreach ($xml->childNodes as $child) {
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
                                $fi->error('XML validation', "Failed to read schema from $href", true);
                                continue;
                                ;
                            }
                        }
                        $schema = @file_get_contents($href);
                        if ($schema === false) {
                            $fi->error('XML validation', "Failed to read schema from $href", true);
                            continue;
                        }
                        $res = $xml->$fn($schema);
                        if ($res) {
                            $fi->info('XML validation', "Schema successfully validated against $href");
                        } else {
                            $fi->error('XML validation', "Schema validation against $href failed with: " . print_r(libxml_get_last_error(), true));
                        }
                        $valid[] = $res;
                    }
                }
            }
        }
        if (count($valid) === 0) {
            $fi->warning('XML validation', "Schema not defined");
        }

        libxml_use_internal_errors($prev);
    }

    /**
     * Checks if odt/ods/docx/xlsx files are password protected
     */
    #[CheckFile]
    public function checkPswdProtected(FileInfo $fi): void {
        static $mime = [
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.text'
        ];
        if ($fi->mime === 'application/CDFV2-encrypted') {
            $fi->error("Password protected file");
        } elseif (in_array($fi->extension, ['odt', 'ods']) || in_array($fi->mime, $mime)) {
            $archive   = new ZipArchive();
            $archive->open($fi->path);
            $xml       = new SimpleXMLElement(fread($archive->getStream('META-INF/manifest.xml'), 10485760));
            $xml->registerXPathNamespace('manifest', 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0');
            $encrypted = $xml->xpath('//manifest:encryption-data');
            if (is_array($encrypted) && count($encrypted) > 0) {
                $fi->error("Password protected file");
            }
            $archive->close();
        }
    }

    /**
     * 
     * Check the PDF version and try to open to be sure that is doesnt have any pwd
     * 
     * @param string $file
     * @return Error|null
     */
    #[CheckFile]
    public function checkPdfFile(FileInfo $fi): void {
        if (!empty($this->veraPdfPath)) {
            return;
        }
        if ($fi->extension !== 'pdf' && $fi->mime !== 'application/pdf') {
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
                $fi->info("PDF", "Compliant with the " . $result->profileName);
            } else {
                foreach ($result->details?->ruleSummaries ?? [] as $i) {
                    $fi->error("PDF", "PDF/A rule $i->clause violated: $i->description");
                }
            }
        } elseif (isset($result[0]->taskException)) {
            $fi->error("PDF", $result[0]->taskException->exception);
        }
    }

    /**
     * 
     * Checks the zip file, we extract them to know if it is pwd protected or not
     * 
     * If we have a pw protected one then we will put it to the $pwZips array
     * 
     */
    #[CheckFile]
    public function checkZipFile(FileInfo $fi): void {
        if ($fi->extension !== 'zip' && $fi->mime !== "application/zip") {
            return;
        }
        $za = new ZipArchive();
        if ($za->open($fi->path) !== true) {
            $fi->error("Zip", $za->getStatusString());
            return;
        }
        $filesCount = $za->count();
        for ($i = 0; $i < $filesCount; $i++) {
            $res = $za->getStream($za->statIndex($i)['name']);
            if ($res === false) {
                $fi->error("Zip error", $za->getStatusString());
            }
        }
    }

    #[CheckFile]
    public function checkAcceptedByArche(FileInfo $fi): void {
        $ext   = $this->archeFormatsByExtension[$fi->extension] ?? null;
        $mime  = $this->archeFormatsByMime[$fi->mime] ?? null;
        $valid = min($ext, $mime);
        $ext   = !empty($ext) ? $ext : 'not accepted';
        $mime  = !empty($mime) ? $mime : 'not accepted';
        if ($valid === null || $valid === '') {
            $fi->error("File format not accepted", "MIME $fi->mime: $mime, extension $fi->extension: $ext");
        } elseif ($valid === 'accepted') {
            $fi->warning("File format not preferred", "MIME $fi->mime: $mime, extension $fi->extension: $ext");
        }
    }

    #[CheckFile]
    public function checkBom(FileInfo $fi): void {
        $fh      = fopen($fi->path, 'r');
        $content = fread($fh, 4);
        fclose($fh);
        if (in_array($content, $this->bom[4]) || in_array(substr($content, 0, 3), $this->bom[3]) || in_array(substr($content, 0, 2), $this->bom[2])) {
            $fi->error('File contains Byte Order Mark');
        }
    }
}
