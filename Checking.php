<?php

namespace OEAW\Checks;

use OEAW\Checks\Misc as MC;
use OEAW\Checks\CheckFunctions as CheckFunctions;
use OEAW\Checks\JsonHandler as JH;
use OEAW\Checks\GenerateHTMLOutput as HTML;

require_once 'Misc.php';
require_once 'CheckFunctions.php';
require_once 'JsonHandler.php';
require_once 'GenerateHTMLOutput.php';

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/scholarslab/bagit/lib/bagit.php';

class Checking {

    private $tmpDir;
    private $reportDir;
    private $signatureDir;
    private $dirList = array();
    private $dir;
    private $misc;
    private $jsonHandler;
    private $generatedReportDirectory;
    private $chkFunc;
    private $html;
    private $cfg;
    private $fileTypeArray = array();
    private $mainDir;

    public function __construct() {
        $this->misc = new MC();
        $this->jsonHandler = new JH();
        $this->html = new HTML();
        $this->cfg = parse_ini_file('config.ini');

        $this->setSignatureDir();
        $this->setTmpDir();

        if ($this->checkReportDir($this->cfg['reportDir'])) {
            $this->reportDir = $this->cfg['reportDir'];
            //create the file list html
            $this->createReportDirFiles();
        } else {
            die('Report Dir does not exists! Please check your settings in the config.ini file');
        }

        $this->chkFunc = new CheckFunctions();
    }
    
    private function setTmpDir(): void {
        if ($this->checkTmpDir($this->cfg['tmpDir'])) {
            $this->tmpDir = $this->cfg['tmpDir'];
        } else {
            die('Temp Dir does not exists! Please check your settings in the config.ini file');
        }
    }

    private function setSignatureDir(): void {
        if ($this->checkTmpDir($this->cfg['signatureDir'])) {
            $this->signatureDir = $this->cfg['signatureDir'];
        } else {
            die('Signature Dir does not exists! Please check your settings in the config.ini file');
        }
    }
    
    /**
     * Create report dir files
     * @return void
     */
    private function createReportDirFiles(): void {
        $fn = date('Y_m_d_H_i_s');
        mkdir($this->reportDir . '/' . $fn);
        mkdir($this->reportDir . '/' . $fn . '/js');
        mkdir($this->reportDir . '/' . $fn . '/css');
        $this->generatedReportDirectory = $this->reportDir . '/' . $fn;
    }

    /**
     * Get the dir what the script should check
     * 
     * @param string $dir
     * @return type
     */
    public function startChecking(string $dir, int $output = 0) {
        define('YOUR_EOL', "\n");

        $this->dir = $dir;
        $this->getJsonFileList($dir, true, false, $output);

        if (count($this->fileTypeArray) > 0) {
            $this->jsonHandler->writeDataToJsonFile($this->fileTypeArray, "fileTypeList", $this->generatedReportDirectory);
            if ($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'fileTypeList') === false) {
                die("Error! Json file cant close: " . $this->generatedReportDirectory . '/' . 'fileTypeList.json');
            }
        }

        if ($output == 0 || $output == 1 || $output == 3) {

            if ($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'fileList') === false) {
                die("Error! Json file cant close: " . $this->generatedReportDirectory . '/' . 'fileList.json');
            }

            if ($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'files') === false) {
                die("Error! Json file cant close: " . $this->generatedReportDirectory . '/' . 'files.json');
            }

            if ($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'directoryList') === false) {
                die("Error! Json file cant close: " . $this->generatedReportDirectory . '/' . 'directoryList.json');
            }

            $buffer = "";
            (file_exists($this->generatedReportDirectory . '/' . 'files.json')) ? $handle = fopen($this->generatedReportDirectory . '/' . 'files.json', "r") : $handle = "";
            if ($handle) {
                while (!feof($handle)) {
                    $buffer .= stream_get_line($handle, 4096);
                }
                fclose($handle);
            }

            $duplicates = array();

            if (!empty($buffer)) {
                $arr = array();
                $arr = json_decode($buffer, true);
                if (is_array($arr['data'])) {
                    $duplicates = $this->chkFunc->checkFileDuplications($arr['data']);
                }
            }

            if (count($duplicates) > 0) {

                if (isset($duplicates["Duplicate_File_And_Size"]) && count($duplicates["Duplicate_File_And_Size"]) > 0) {
                    foreach ($duplicates["Duplicate_File_And_Size"] as $k => $v) {
                        $arr = array();
                        $arr[$k] = $v;

                        $this->jsonHandler->writeDataToJsonFile($arr, "duplicates_size", $this->generatedReportDirectory, "json");
                        if (is_array($v)) {
                            $dirs = implode(",", $v);
                        } else {
                            $dirs = $v;
                        }
                        $this->jsonHandler->writeDataToJsonFile(
                                array("errorType" => "DUPLICATION! There is a file with same name and size!", "dir" => "$dirs", "filename" => "$k"),
                                "error",
                                $this->generatedReportDirectory,
                                "json"
                        );
                    }

                    if ($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'duplicates_size') === false) {
                        die("Error! Json file cant close: " . $this->generatedReportDirectory . '/' . 'duplicates_size.json');
                    }
                }

                if (isset($duplicates["Duplicate_File"]) && count($duplicates["Duplicate_File"]) > 0) {
                    foreach ($duplicates["Duplicate_File"] as $k => $v) {
                        $arr = array();
                        $arr[$k] = $v;
                        $this->jsonHandler->writeDataToJsonFile($arr, "duplicates", $this->generatedReportDirectory, "json");
                        if (is_array($v)) {
                            $dirs = implode(",", $v);
                        } else {
                            $dirs = $v;
                        }
                        $this->jsonHandler->writeDataToJsonFile(
                                array("errorType" => "DUPLICATION! There is a file with same name!", "dir" => "$dirs", "filename" => "$k"),
                                "error",
                                $this->generatedReportDirectory,
                                "json"
                        );
                    }

                    if ($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'duplicates') === false) {
                        die("Error! Json file cant close: " . $this->generatedReportDirectory . '/' . 'duplicates.json');
                    }
                }
            }

            if ($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'error') === false) {
                die("Error! Json file cant close: " . $this->generatedReportDirectory . '/' . 'error.json');
            }


            if ($output == 1) {
                //create basic html
                $this->html->generateFileListHtml($this->generatedReportDirectory);
                $this->html->generateErrorListHtml($this->generatedReportDirectory);
                $this->html->generateDirListHtml($this->generatedReportDirectory);

                if (file_exists($this->generatedReportDirectory . '/' . 'fileTypeList.json')) {
                    $file = fopen($this->generatedReportDirectory . '/' . 'fileTypeList.json', "r");

                    $content = stream_get_contents($file);
                    $obj = json_decode($content, true);
                    if (isset($obj['data'][0]['directories']) && count((array) $obj['data'][0]['directories']) > 0) {
                        $result = $this->chkFunc->convertDirectoriesToTree($obj['data'][0]['directories']);
                        if (count((array) $result) > 0) {
                            $dirDataFile = fopen($this->generatedReportDirectory . '/directories.json', 'w');
                            fwrite($dirDataFile, json_encode($result));
                            fclose($dirDataFile);
                        }
                    }
                    if (isset($obj['data'][0]['extensions']) && count((array) $obj['data'][0]['extensions']) > 0) {
                        $resultExt = $this->chkFunc->convertExtensionsToTree($obj['data'][0]['extensions']);
                        if (count((array) $resultExt) > 0) {
                            $dirDataFile = fopen($this->generatedReportDirectory . '/extensions.json', 'w');
                            fwrite($dirDataFile, json_encode($resultExt));
                            fclose($dirDataFile);
                        }
                    }
                    $this->html->generateFileTypeListHtml($this->generatedReportDirectory);
                }
                if (!file_exists($this->generatedReportDirectory . '/fileTypeList.json')) {
                    copy('template/fileTypeList.json', $this->generatedReportDirectory . '/fileTypeList.json');
                }
                if (!file_exists($this->generatedReportDirectory . '/error.json')) {
                    copy('template/error.json', $this->generatedReportDirectory . '/error.json');
                }
                if (!file_exists($this->generatedReportDirectory . '/directories.json')) {
                    copy('template/directories.json', $this->generatedReportDirectory . '/directories.json');
                }
                if (!file_exists($this->generatedReportDirectory . '/directoryList.json')) {
                    copy('template/directoryList.json', $this->generatedReportDirectory . '/directoryList.json');
                }
                if (!file_exists($this->generatedReportDirectory . '/extensions.json')) {
                    copy('template/extensions.json', $this->generatedReportDirectory . '/extensions.json');
                }
                if (!file_exists($this->generatedReportDirectory . '/files.json')) {
                    copy('template/files.json', $this->generatedReportDirectory . '/files.json');
                }
                if (!file_exists($this->generatedReportDirectory . '/fileList.json')) {
                    copy('template/fileList.json', $this->generatedReportDirectory . '/fileList.json');
                }
            }

            if ($output == 3) {
                //create basic html
                $this->html->generateFileListHtml($this->generatedReportDirectory);
                $this->html->generateErrorListHtml($this->generatedReportDirectory);
                $this->html->generateDirListHtml($this->generatedReportDirectory);

                if (file_exists($this->generatedReportDirectory . '/' . 'fileTypeList.json')) {
                    $file = fopen($this->generatedReportDirectory . '/fileTypeList.json', 'r');
                    $content = stream_get_contents($file);
                    $obj = json_decode($content, true);
                    $result = $this->chkFunc->convertDirectoriesToTree($obj['data'][0]['directories']);
                    if (count($result) > 0) {
                        $dirDataFile = fopen($this->generatedReportDirectory . '/directories.json', 'w');
                        fwrite($dirDataFile, json_encode($result));
                        fclose($dirDataFile);
                    }
                    $resultExt = $this->chkFunc->convertExtensionsToTree($obj['data'][0]['extensions']);
                    if (count($resultExt) > 0) {
                        $dirDataFile = fopen($this->generatedReportDirectory . '/extensions.json', 'w');
                        fwrite($dirDataFile, json_encode($resultExt));
                        fclose($dirDataFile);
                    }
                    $this->html->generateFileTypeJstreeHtml($this->generatedReportDirectory);
                }
            }
        }
    }

    /**
     * 
     * Check the temp directory and the permissions
     * 
     * @param string $str
     * @return bool
     */
    private function checkTmpDir(string $str, string $type = "tempDir"): bool {
        if (is_dir(realpath($str)) && is_writable($str)) {
            return true;
        } else {
            die("\n ERROR " . $type . " (" . $str . ") is not exists or not writable, please check the config.ini \n");
        }
    }

    /**
     * 
     * Check the temp directory and the permissions
     * 
     * @param string $str
     * @return bool
     */
    private function checkReportDir(string $str): bool {

        if (is_dir($str) && is_writable($str)) {
            return true;
        } else {
            die("\nERROR reportDIR (" . $str . ") is not exists or not writable, please check the config.ini \n");
        }
    }

    private function setFinfo() {
        
    }
    
    private function getJsonFileList(string $dir, bool $recurse = false, bool $depth = false, string $output) {
        if (function_exists('mime_content_type')) {
            $finfo = false;
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
        }
        error_log($finfo);

        $retval = array();
        $jsonOutput = "json";
        if ($output == 2) {
            $jsonOutput = "ndjson";
        }
        // add trailing slash if missing
        if (substr($dir, -1) != "/")
            $dir .= "/";

        echo "\n File list generating...\n";
        // open pointer to directory and read list of files
        $d = @dir($dir) or die("getFileList: Failed opening directory $dir for reading");

        $files = scandir($dir);
        // Count number of files and store them to variable..
        $numOfFiles = count($files) - 2;
        $pbFL = new \ProgressBar\Manager(0, $numOfFiles);
        $childrenDir = false;

        $fileTypeList = array();

        if ($numOfFiles == 0) {
            $this->jsonHandler->writeDataToJsonFile(array("errorType" => "Directory is empty", "dir" => "$dir", "filename" => ""), "error", $this->generatedReportDirectory, $jsonOutput);
        }

        while (false !== ($entry = $d->read())) {

            // skip hidden files
            if ($entry[0] == ".")
                continue;

            echo $entry . "\n";

            //DIRECTORY
            if (is_dir("$dir$entry")) {

                echo "\nSubDirectory found, checking the contents... \n";

                if ($recurse && is_readable("$dir$entry/")) {
                    $childrenDir = true;
                }

                //check the file name validity
                $valid = $this->chkFunc->checkDirectoryNameValidity("$dir$entry", $this->dir);
                if ($valid === false && !empty("$dir$entry")) {
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "Directory name contains invalid characters", "dir" => "$dir$entry", "filename" => ""), "error", $this->generatedReportDirectory, $jsonOutput);
                }

                $retval[] = array(
                    "name" => "$dir$entry/",
                    "directory" => "$dir",
                    "type" => filetype("$dir$entry"),
                    "size" => 0,
                    "lastmod" => date("Y-m-d H:i:s", filemtime("$dir$entry")),
                    "valid_file" => $valid,
                    "filename" => $entry
                );

                //create the directory json file
                if (filetype("$dir$entry") == "dir") {
                    $dirData = array();
                    $dirData = array("name" => "$dir$entry", "valid" => $valid, "lastmodified" => date("Y-m-d H:i:s", filemtime("$dir$entry")));
                    if (count($dirData) > 0) {
                        $this->jsonHandler->writeDataToJsonFile($dirData, "directoryList", $this->generatedReportDirectory, $jsonOutput);
                    }
                }


                if ($recurse && is_readable("$dir$entry/")) {
                    if ($depth === false) {
                        $retval = array_merge($retval, $this->getJsonFileList("$dir$entry/", true, false, $output));
                        echo "\nSubDirectory content checked... \n";
                    } elseif ($depth > 0) {
                        $retval = array_merge($retval, $this->getJsonFileList("$dir$entry/", true, $depth - 1, $output));
                        echo "\nSubDirectory content checked... \n";
                    }
                }


                //FILE    
            } elseif (is_readable("$dir$entry")) {

                $extension = explode('.', $entry);
                $extension = end($extension);
                if (empty($finfo)) {
                    if ("$dir$entry" != null) {
                        if (mime_content_type("$dir$entry") == null) {
                            $fileType = "unknown";
                        } else {
                            $fileType = mime_content_type("$dir$entry");
                        }
                    } else {
                        $fileType = "unknown, file error";
                    }
                } else {
                    $fileType = finfo_file($finfo, "$dir$entry");
                }
                //blacklist files
                if ($this->chkFunc->checkBlackListFile($extension) == true && !empty($dir) && !empty($entry)) {
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "This file extension is blacklisted", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }

                //check the file name validity
                $valid = $this->chkFunc->checkFileNameValidity($entry);
                if ($valid === false && !empty($dir) && !empty($entry)) {
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "File name contains invalid characters", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }

                //check the mime extensions
                $mime = $this->chkFunc->checkMimeTypes($extension, $fileType);
                if ($mime === false && !empty($dir) && !empty($entry)) {
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "MIME type does not match format extension", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }

                //check the ZIP files
                if (
                        $extension == "zip" || $fileType == "application/zip" ||
                        $extension == "gzip" || $fileType == "application/gzip" ||
                        $extension == "7zip" || $fileType == "application/7zip"
                ) {
                    if ($this->chkFunc->real_filesize("$dir$entry") > $this->cfg['zipSize']) {
                        $this->jsonHandler->writeDataToJsonFile(array("errorType" => "The ZIP was skipped, because it is too large", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                    } else {
                        $zipResult = $this->chkFunc->checkZipFiles(array("$dir$entry"));
                        if (count($zipResult) > 0 && isset($zipResult[0])) {
                            $this->jsonHandler->writeDataToJsonFile($zipResult[0], "error", $this->generatedReportDirectory, $jsonOutput);
                        }
                    }
                }

                //check the PDF Files
                if ($extension == "pdf" || $fileType == "application/pdf") {
                    //check the zip files and add them to the zip pwd checking
                    if ($this->chkFunc->real_filesize("$dir$entry") > $this->cfg['pdfSize']) {
                        $this->jsonHandler->writeDataToJsonFile(array("errorType" => "The PDF was skipped, because it is too large", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                    } else {
                        $pdfResult = $this->chkFunc->checkPdfFile("$dir$entry");
                        if (count($pdfResult) > 0) {
                            $this->jsonHandler->writeDataToJsonFile($pdfResult, "error", $this->generatedReportDirectory, $jsonOutput);
                        }
                    }
                }
                //check the RAR files
                if ($extension == "rar" || $fileType == "application/rar") {
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "This is a RAR file! Please check it manually!", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }

                //check PW protected XLSX, DOCX
                if (($extension == "xlsx" || $extension == "docx") && $fileType == "application/CDFV2-encrypted") {
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "This document (XLSX,DOCX) is password protected", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }

                //check the bagit files
                if (strpos(strtolower($dir), 'bagit') !== false) {
                    $bagItResult = array();
                    $bagItResult = $this->chkFunc->checkBagitFile("$dir$entry");
                    if (count($bagItResult) > 0) {
                        foreach ($bagItResult as $filename => $val) {
                            $dir = "";
                            if ((strpos($filename, '/') !== false) || (strpos($filename, '\\') !== false)) {
                                $dir = $filename;
                                $filename = substr($filename, strrpos($filename, '/') + 1);
                                $dir = str_replace($filename, '', $dir);
                            }
                            if (is_array($val) && count($val) > 0) {
                                foreach ($val as $v) {
                                    $error = "";
                                    if (isset($v[0]) && isset($v[1])) {
                                        $error = $v[0] . ". error: " . $v[1];
                                    } else {
                                        $error = $v[0];
                                    }
                                    (empty($error)) ? $error = "BagIt file is not valid or can not be analysed" : "";
                                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => $error, "dir" => $dir, "filename" => $filename, "errorMSG" => $error), "error", $this->generatedReportDirectory, $jsonOutput);
                                }
                            }
                        }
                    }
                }

                $retval[] = array(
                    "name" => "$dir$entry",
                    "directory" => "$dir",
                    "type" => $fileType,
                    "size" => $this->chkFunc->real_filesize("$dir$entry"),
                    "lastmod" => date("Y-m-d H:i:s", filemtime("$dir$entry")),
                    "valid_file" => $valid,
                    "filename" => $entry,
                    "extension" => strtolower($extension)
                );

                $fileInfo = array();
                $fileInfo = array(
                    "name" => "$dir$entry",
                    "directory" => "$dir",
                    "type" => $fileType,
                    "size" => $this->chkFunc->real_filesize("$dir$entry"),
                    "lastmod" => date("Y-m-d H:i:s", filemtime("$dir$entry")),
                    "valid_file" => $valid,
                    "filename" => $entry,
                    "extension" => strtolower($extension)
                );

                $filesList = array();
                $filesList = array("filename" => $entry, "size" => $this->chkFunc->real_filesize("$dir$entry"), "dir" => $dir, "extension" => strtolower($extension));

                $ext = strtolower($extension);
                $fsize = $this->chkFunc->real_filesize("$dir$entry");

                $cleanDir = $this->misc->clean($dir);
                $cleanFile = $this->misc->clean("$dir$entry");

                //check that the file is damaged or not
                if ($fsize < 0) {
                    $this->fileTypeArray['info']['damagedFiles'] = array("filename" => "$dir$entry", "dir" => $dir);
                } else {
                    //directories
                    if (!isset($this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['sumSize']['sum'])) {
                        $this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['sumSize']['sum'] = 0;
                    }
                    $this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['sumSize']['sum'] += $fsize;

                    if (!isset($this->fileTypeArray['directories'][$cleanDir]['dirSumSize']['sumSize'])) {
                        $this->fileTypeArray['directories'][$cleanDir]['dirSumSize']['sumSize'] = 0;
                    }
                    $this->fileTypeArray['directories'][$cleanDir]['dirSumSize']['sumSize'] += $fsize;

                    if (!isset($this->fileTypeArray['directories'][$cleanDir]['dirSumFiles']['sumFileCount'])) {
                        $this->fileTypeArray['directories'][$cleanDir]['dirSumFiles']['sumFileCount'] = 0;
                    }
                    $this->fileTypeArray['directories'][$cleanDir]['dirSumFiles']['sumFileCount'] += 1;

                    if (!isset($this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['fileCount']['fileCount'])) {
                        $this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['fileCount']['fileCount'] = 0;
                    }
                    $this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['fileCount']['fileCount'] += 1;

                    if (!isset($this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['minSize']['min'])) {
                        $this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['minSize']['min'] = $fsize;
                    } else {
                        if ($this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['minSize']['min'] > $fsize) {
                            $this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['minSize']['min'] = $fsize;
                        }
                    }

                    if (!isset($this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['maxSize']['max'])) {
                        $this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['maxSize']['max'] = $fsize;
                    } else {
                        if ($this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['maxSize']['max'] < $fsize) {
                            $this->fileTypeArray['directories'][$cleanDir]['extension'][$ext]['maxSize']['max'] = $fsize;
                        }
                    }

                    // extensions
                    if (!isset($this->fileTypeArray['extensions'][$ext]['sumSize'])) {
                        $this->fileTypeArray['extensions'][$ext]['sumSize'] = 0;
                    }
                    $this->fileTypeArray['extensions'][$ext]['sumSize'] += $fsize;

                    if (!isset($this->fileTypeArray['extensions'][$ext]['fileCount'])) {
                        $this->fileTypeArray['extensions'][$ext]['fileCount'] = 0;
                    }
                    $this->fileTypeArray['extensions'][$ext]['fileCount'] += 1;

                    if (!isset($this->fileTypeArray['extensions'][$ext]['min'])) {
                        $this->fileTypeArray['extensions'][$ext]['min'] = $fsize;
                    } else {
                        if ($this->fileTypeArray['extensions'][$ext]['min'] > $fsize) {
                            $this->fileTypeArray['extensions'][$ext]['min'] = $fsize;
                        }
                    }

                    if (!isset($this->fileTypeArray['extensions'][$ext]['max'])) {
                        $this->fileTypeArray['extensions'][$ext]['max'] = $fsize;
                    } else {
                        if ($this->fileTypeArray['extensions'][$ext]['max'] < $fsize) {
                            $this->fileTypeArray['extensions'][$ext]['max'] = $fsize;
                        }
                    }


                    //summary
                    if (!isset($this->fileTypeArray['summary']['overallFileCount'])) {
                        $this->fileTypeArray['summary']['overallFileCount'] = 0;
                    }
                    $this->fileTypeArray['summary']['overallFileCount'] += 1;

                    if (!isset($this->fileTypeArray['summary']['overallFileSize'])) {
                        $this->fileTypeArray['summary']['overallFileSize'] = 0;
                    }
                    $this->fileTypeArray['summary']['overallFileSize'] += $fsize;
                }
                if (count($fileInfo) > 0) {
                    $this->jsonHandler->writeDataToJsonFile($fileInfo, "fileList", $this->generatedReportDirectory, $jsonOutput);
                }
                if (count($filesList) > 0) {
                    $this->jsonHandler->writeDataToJsonFile($filesList, "files", $this->generatedReportDirectory, $jsonOutput);
                }
            }
            $pbFL->advance();
            echo "\n";
        }

        $d->close();
        return $retval;
    }

}
