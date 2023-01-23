<?php

namespace acdhOeaw\arche\fileChecker;

class Misc {

    static public function die(string $msg): void {
        echo $msg;
        exit(1);
    }

    /**
     * Checks if a given directory exists and is writable and ends the execution
     * with an error message if it doesn't.
     * 
     * @param string $dir
     * @param string $type
     * @return void
     */
    static public function checkTmpDir(string $dir, string $type = "tempDir"): void {
        $real = realpath($dir);
        if (!is_dir($real) || !is_writable($real)) {
            self::die("\nERROR $type ($dir) does't exist or isn't writable.\n");
        }
    }

    /**
     * 
     * Create nice format from file sizes
     * 
     * @param int $bytes
     * @return string
     */
    static public function formatSizeUnits(int $bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    /**
     * 
     * Clean the string
     * 
     * @param string $string
     * @return string
     */
    static public function clean(string $string): string {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        return preg_replace('[^A-Za-z0-9\-]', '', $string); // Removes special chars.
    }

    /**
     * 
     * @return array<string>
     */
    static public function extensionWhiteList(): array {
        return [
            "PDF", "ODT", "DOCX", "DOC", "RTF", "SXW",
            "TXT", "XML", "SGML", "HTML", "DTD", "XSD",
            "TIFF", "DNG", "PNG", "JPEG", "GIF", "BMP",
            "PSD", "CPT", "JPEG2000", "SVG", "CGM", "DXF", "DWG",
            "PostScript", "AI", "DWF", "CSV", "TSV", "ODS",
            "XLSX", "SXC", "XLS", "SIARD", "SQL", "JSON", "MDB",
            "FMP", "DBF", "BAK", "ODB", "MKV", "MJ2", "MP4",
            "MXF", "MPEG", "AVI", "MOV", "ASF/WMV", "OGG",
            "FLV", "FLAC", "WAV", "BWF", "RF64", "MBWF", "AAC",
            "MP4", "MP3", "AIFF", "WMA", "X3D", "COLLADA", "OBJ",
            "PLY", "VRML", "U3D", "STL", "XHTML", "MHTML", "WARC", "MAFF"
        ];
    }

    /**
     * Process the pronom xml for the MIMEtypes
     * 
     * @param string $file
     * @return array<string>
     */
    static public function getMimeFromPronom(string $file): array {
        $xml = simplexml_load_file($file);
        if ($xml === false) {
            self::die("Pronom Droid Signaturefile is not available!");
        }
        $extArray = [];
        foreach ($xml->FileFormatCollection->FileFormat as $i) {
            foreach ($i->Extension as $ext) {
                $extArray[] = mb_strtolower((string) $ext);
            }
        }
        return $extArray;
    }
}
