<?php

namespace acdhOeaw\arche\fileChecker;

use finfo;
use acdhOeaw\arche\fileChecker\Misc as MC;
use acdhOeaw\arche\fileChecker\CheckFunctions;
use acdhOeaw\arche\fileChecker\JsonHandler;
use acdhOeaw\arche\fileChecker\HtmlOutput as HTML;

class FileChecker {

    const UNKNOWN_MIME = 'unknown';

    private string $tmpDir;
    private string $reportDir;
    private string $signatureDir;
    private string $generatedReportDirectory;
    private string $tmplDir;
    private JsonHandler $jsonHandler;
    private CheckFunctions $chkFunc;
    private HTML $html;
    private int $startDepth;
    private finfo $finfo;

    /**
     * 
     * @var array<string, mixed>
     */
    private array $cfg;

    /**
     * 
     * @var array<string, mixed>
     */
    private array $fileTypeArray;

    /**
     * 
     * @param array<string, mixed> $config
     */
    public function __construct(array $config) {
        $this->html    = new HTML();
        $this->chkFunc = new CheckFunctions($config);
        $this->cfg     = $config;
        $this->tmplDir = realpath(__DIR__ . '/../../../../template');

        $this->tmpDir       = $config['tmpDir'];
        $this->reportDir    = $config['reportDir'];
        $this->signatureDir = $config['signatureDir'];
        MC::checkTmpDir($this->tmpDir, 'tmpDir');
        MC::checkTmpDir($this->reportDir, 'reportDir');
        MC::checkTmpDir($this->signatureDir, 'signatureDir');

        $this->generatedReportDirectory = $this->reportDir . '/' . date('Y_m_d_H_i_s');
        $success                        = true;
        $success                        &= mkdir($this->generatedReportDirectory);
        $success                        &= mkdir($this->generatedReportDirectory . "/js");
        $success                        &= mkdir($this->generatedReportDirectory . "/css");
        if (!$success) {
            MC::die("Report directories creation ($this->generatedReportDirectory and subdirs) failed");
        }

        try {
            $this->finfo = new finfo(FILEINFO_MIME_TYPE);
        } catch (\Exception $ex) {
            echo "Failed to instantiate the finfo object. Falling back to mime_content_type() for getting file's MIME type.\n";
        }
    }

    /**
     * Get the dir what the script should check
     * 
     */
    public function check(string $dir, int $output = 0,
                          int $maxDepth = PHP_INT_MAX): bool {
        $this->fileTypeArray = [
            'extensions' => [],
            'summary'    => [],
        ];
        $format              = $output === 2 ? JsonHandler::FORMAT_NDJSON : JsonHandler::FORMAT_JSON;
        $this->jsonHandler   = new JsonHandler($this->generatedReportDirectory, $format);

        $this->jsonHandler->noError = true;
        $maxDepth = 2;
        $this->startDepth           = $maxDepth;
        $this->checkDir(realpath($dir), $maxDepth);
        $this->postprocess($output);

        return $this->jsonHandler->noError;
    }

    /**
     * 
     * @param string $dir
     * @param int $depthToGo
     * @return array<FileInfo>
     */
    private function checkDir(string $dir, int $depthToGo): array {
        $retval  = [];
        $dirInfo = FileInfo::factory($dir, '', $this->chkFunc->checkDirectoryNameValidity($dir));

        // don't validate the top-level directory        
        if ($depthToGo < $this->startDepth) {
            if (!$dirInfo->valid) {
                $this->jsonHandler->writeDataToJsonFile($dirInfo->getError("Directory name contains invalid characters"));
            }
            $retval[] = $dirInfo;
            $this->jsonHandler->writeDataToJsonFile($dirInfo->asDirectoriesEntry());
        }

        echo "\nGenerating files list...\n";
        $files = scandir($dir);
        if ($files === false) {
            MC::die("getFileList: Failed opening directory $dir for reading");
        }
        $files = array_filter($files, fn($x) => $x !== '.' && $x != '..');
        if (count($files) === 0) {
            $this->jsonHandler->writeDataToJsonFile($dirInfo->getError("Directory is empty"));
            return [];
        }

        $pbFL = new \ProgressBar\Manager(0, count($files));
        foreach ($files as $entry) {
            echo "$entry\n";
            $path = "$dir/$entry";
            if (is_dir($path) && $depthToGo > 0) {
                $retval = array_merge($retval, $this->checkDir($path, $depthToGo - 1));
                echo "\nSubdirectory content checked... \n";
            } elseif (is_link($path)) {
                $this->jsonHandler->writeDataToJsonFile(new Error($dir, $entry, "Symbolic links aren't allowed"));
            } elseif (is_file($path)) {
                $retval[] = $this->checkFile($path);
            } else {
                $this->jsonHandler->writeDataToJsonFile(new Error($dir, $entry, "Unprocessable file type " . filetype($path)));
            }
            $pbFL->advance();
            echo "\n";
        }

        return $retval;
    }

    private function checkFile(string $path): FileInfo {
        $dir   = dirname($path);
        $entry = basename($path);
        if (isset($this->finfo)) {
            $fileType = $this->finfo->file($path) ?: self::UNKNOWN_MIME;
        } else {
            $fileType = mime_content_type($path) ?: self::UNKNOWN_MIME;
        }
        $fileInfo = FileInfo::factory($dir, $entry, $this->chkFunc->checkFileNameValidity($entry));

        //blacklist files
        if ($this->chkFunc->checkBlackListFile($fileInfo->extension)) {
            $this->jsonHandler->writeDataToJsonFile($fileInfo->getError("This file extension is blacklisted"));
        }
        //check the file name validity
        if ($fileInfo->valid === false) {
            $this->jsonHandler->writeDataToJsonFile($fileInfo->getError("File name contains invalid characters"));
        }
        //check the mime extensions
        if ($this->chkFunc->checkMimeTypes($fileInfo->extension, $fileType)) {
            $this->jsonHandler->writeDataToJsonFile($fileInfo->getError('MIME type does not match format extension'));
        }

        //check the ZIP files
        if (
            $fileInfo->extension == "zip" || $fileType == "application/zip" ||
            $fileInfo->extension == "gzip" || $fileType == "application/gzip" ||
            $fileInfo->extension == "7zip" || $fileType == "application/7zip"
        ) {
            if ($fileInfo->size > $this->cfg['zipSize']) {
                $this->jsonHandler->writeDataToJsonFile($fileInfo->getError("The ZIP was skipped, because it is too large"));
            } else {
                $zipError = $this->chkFunc->checkZipFile($path);
                if ($zipError !== null) {
                    $this->jsonHandler->writeDataToJsonFile($zipError);
                }
            }
        }

        //check the PDF Files
        if ($fileInfo->extension == "pdf" || $fileType == "application/pdf") {
            //check the zip files and add them to the zip pwd checking
            if ($fileInfo->size > $this->cfg['pdfSize']) {
                $this->jsonHandler->writeDataToJsonFile($fileInfo->getError("The PDF was skipped, because it is too large"));
            } else {
                $pdfError = $this->chkFunc->checkPdfFile($path);
                if ($pdfError !== null) {
                    $this->jsonHandler->writeDataToJsonFile($pdfError);
                }
            }
        }
        //check the RAR files
        if ($fileInfo->extension == "rar" || $fileType == "application/rar") {
            $this->jsonHandler->writeDataToJsonFile($fileInfo->getError("This is a RAR file! Please check it manually!"));
        }

        //check PW protected XLSX, DOCX
        if (($fileInfo->extension == "xlsx" || $fileInfo->extension == "docx") && $fileType == "application/CDFV2-encrypted") {
            $this->jsonHandler->writeDataToJsonFile($fileInfo->getError("This document (XLSX,DOCX) is password protected"));
        }

        //check the bagit files
        if (strpos(strtolower($dir), 'bagitaaa') !== false) {
            $bagItResult = [];
            $bagItResult = $this->chkFunc->checkBagitFile($path);
            if (count($bagItResult) > 0) {
                foreach ($bagItResult as $filename => $val) {
                    $dir = "";
                    if ((strpos($filename, '/') !== false) || (strpos($filename, '\\') !== false)) {
                        $dir      = $filename;
                        $filename = substr($filename, strrpos($filename, '/') + 1);
                        $dir      = str_replace($filename, '', $dir);
                    }
                    if (is_array($val) && count($val) > 0) {
                        foreach ($val as $v) {
                            $error = "";
                            if (isset($v[0]) && isset($v[1])) {
                                $error = $v[0] . ". error: " . $v[1];
                            } else {
                                $error = $v[0];
                            }
                            $error = empty($error) ? "BagIt file is not valid or can not be analysed" : $error;
                            $this->jsonHandler->writeDataToJsonFile($fileInfo->getError('BagIt error', $error));
                        }
                    }
                }
            }
        }
        $cleanDir = MC::clean($dir);

        //check that the file is damaged or not
        if ($fileInfo->size <= 0) {
            $this->fileTypeArray['info']['damagedFiles'] = [
                "filename" => "$dir$entry",
                "dir"      => $dir
            ];
        } else {
            $ext                                                 = $fileInfo->extension;
            //directories
            $arrRef                                              = &$this->fileTypeArray['directories'][$cleanDir];
            $arrRef['extension'][$ext]['sumSize']['sum']         = ($arrRef['extension'][$ext]['sumSize']['sum'] ?? 0) + $fileInfo->size;
            $arrRef['dirSumSize']['sumSize']                     = ($arrRef['dirSumSize']['sumSize'] ?? 0) + $fileInfo->size;
            $arrRef['dirSumFiles']['sumFileCount']               = ($arrRef['dirSumFiles']['sumFileCount'] ?? 0) + 1;
            $arrRef['extension'][$ext]['fileCount']['fileCount'] = ($arrRef['extension'][$ext]['fileCount']['fileCount'] ?? 0) + 1;
            $arrRef['extension'][$ext]['minSize']['min']         = min($arrRef['extension'][$ext]['minSize']['min'] ?? PHP_INT_MAX, $fileInfo->size);
            $arrRef['extension'][$ext]['maxSize']['max']         = max($arrRef['extension'][$ext]['maxSize']['max'] ?? 0, $fileInfo->size);

            // extensions
            $arrRef              = &$this->fileTypeArray['extensions'][$ext];
            $arrRef['fileCount'] = ($arrRef['fileCount'] ?? 0) + 1;
            $arrRef['min']       = min($arrRef['min'] ?? PHP_INT_MAX, $fileInfo->size);
            $arrRef['max']       = max($arrRef['max'] ?? 0, $fileInfo->size);

            //summary
            $arrRef                     = &$this->fileTypeArray['summary'];
            $arrRef['overallFileCount'] = ($arrRef['overallFileCount'] ?? 0) + 1;
            $arrRef['overallFileSize']  = ($arrRef['overallFileSize'] ?? 0) + $fileInfo->size;
        }
        $this->jsonHandler->writeDataToJsonFile($fileInfo);
        $this->jsonHandler->writeDataToJsonFile($fileInfo->asFilesEntry());

        return $fileInfo;
    }

    private function postprocess(int $output): void {
        if (count($this->fileTypeArray) > 0) {
            $this->jsonHandler->writeDataToJsonFile($this->fileTypeArray, "fileTypeList");
            $this->jsonHandler->closeJsonFile('fileTypeList');
        }

        if ($output == 0 || $output == 1 || $output == 3) {
            $this->jsonHandler->closeJsonFile('fileList');
            $this->jsonHandler->closeJsonFile('files');
            $this->jsonHandler->closeJsonFile('directoryList');

            $filesJson  = $this->generatedReportDirectory . '/files.json';
            $duplicates = [];
            if (file_exists($filesJson)) {
                $arr = json_decode(file_get_contents($filesJson), true);
                if (is_array($arr) && isset($arr['data'])) {
                    $duplicates = $this->chkFunc->checkFileDuplications($arr['data']);
                }
            }
            if (count($duplicates) > 0) {
                foreach ($duplicates["Duplicate_File_And_Size"] ?? [] as $k => $v) {
                    $arr     = [];
                    $arr[$k] = $v;

                    $this->jsonHandler->writeDataToJsonFile($arr, "duplicates_size");
                    if (is_array($v)) {
                        $dirs = implode(",", $v);
                    } else {
                        $dirs = $v;
                    }
                    $this->jsonHandler->writeDataToJsonFile(new Error($dirs, $k, "DUPLICATION! There is a file with same name and size!"));
                }

                $this->jsonHandler->closeJsonFile('duplicates_size');

                foreach ($duplicates["Duplicate_File"] ?? [] as $k => $v) {
                    $arr     = [];
                    $arr[$k] = $v;
                    $this->jsonHandler->writeDataToJsonFile($arr, "duplicates");
                    if (is_array($v)) {
                        $dirs = implode(",", $v);
                    } else {
                        $dirs = $v;
                    }
                    $this->jsonHandler->writeDataToJsonFile(new Error($dirs, $k, "DUPLICATION! There is a file with same name!"));
                }

                $this->jsonHandler->closeJsonFile('duplicates');
            }

            $this->jsonHandler->closeJsonFile('error');

            if ($output == 1) {
                //create basic html
                $this->html->generateFileListHtml($this->generatedReportDirectory);
                $this->html->generateErrorListHtml($this->generatedReportDirectory);
                $this->html->generateDirListHtml($this->generatedReportDirectory);

                if (file_exists($this->generatedReportDirectory . '/' . 'fileTypeList.json')) {
                    $obj = json_decode(file_get_contents($this->generatedReportDirectory . '/' . 'fileTypeList.json'), true);
                    if (isset($obj['data'][0]['directories']) && count((array) $obj['data'][0]['directories']) > 0) {
                        $result = $this->chkFunc->convertDirectoriesToTree($obj['data'][0]['directories']);
                        if (count((array) $result) > 0) {
                            file_put_contents($this->generatedReportDirectory . '/directories.json', json_encode($result));
                        }
                    }
                    if (isset($obj['data'][0]['extensions']) && count((array) $obj['data'][0]['extensions']) > 0) {
                        $resultExt = $this->chkFunc->convertExtensionsToTree($obj['data'][0]['extensions']);
                        if (count((array) $resultExt) > 0) {
                            file_put_contents($this->generatedReportDirectory . '/extensions.json', json_encode($resultExt));
                        }
                    }
                    $this->html->generateFileTypeListHtml($this->generatedReportDirectory);
                }
                $jsonTypes = [
                    'fileTypeList', 'error', 'directories', 'directoryList',
                    'extensions', 'files', 'fileList'
                ];
                foreach ($jsonTypes as $jsonType) {
                    $filePath = "$this->generatedReportDirectory/$jsonType.json";
                    if (!file_exists($filePath)) {
                        copy("$this->tmplDir/$jsonType.json", $filePath);
                    }
                }
            }

            if ($output == 3) {
                //create basic html
                $this->html->generateFileListHtml($this->generatedReportDirectory);
                $this->html->generateErrorListHtml($this->generatedReportDirectory);
                $this->html->generateDirListHtml($this->generatedReportDirectory);

                if (file_exists($this->generatedReportDirectory . '/' . 'fileTypeList.json')) {
                    $obj    = json_decode(file_get_contents($this->generatedReportDirectory . '/fileTypeList.json'), true);
                    $result = $this->chkFunc->convertDirectoriesToTree($obj['data'][0]['directories']);
                    if (count($result) > 0) {
                        file_put_contents($this->generatedReportDirectory . '/directories.json', json_encode($result));
                    }
                    $resultExt = $this->chkFunc->convertExtensionsToTree($obj['data'][0]['extensions']);
                    if (count($resultExt) > 0) {
                        file_put_contents($this->generatedReportDirectory . '/extensions.json', json_encode($resultExt));
                    }
                    $this->html->generateFileTypeJstreeHtml($this->generatedReportDirectory);
                }
            }
        }
    }
}
