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
    
    private $errors = array();
    private $tmpDir;
    private $reportDir;
    private $dirList = array();
    private $errorList = array();
    private $fileList = array();
    private $dir;
    private $misc;
    private $jsonHandler;
    private $generatedReportDirectory;
    private $chkFunc;
    private $html;


    public function __construct(){
        $this->misc = new MC();
        $this->jsonHandler = new JH();
        $this->html = new HTML();
        
        $cfg = parse_ini_file('config.ini');        
        
        $this->chkFunc = new CheckFunctions();
        
        if($this->checkTmpDir($cfg['tmpDir'])){
            $this->tmpDir = $cfg['tmpDir'];
        }else {
            die();
        }
        
        if($this->checkReportDir($cfg['reportDir'])){
            $this->reportDir = $cfg['reportDir'];
            //create the file list html
            $fn = date('Y_m_d_H_i_s');
            mkdir($this->reportDir.'/'.$fn);
            $this->generatedReportDirectory = $this->reportDir.'/'.$fn;
        }else {
            die();
        }
    }
    
    /**
     * Get the dir what the script should check
     * 
     * @param string $dir
     * @return type
     */

    public function startChecking(string $dir, int $output = 0){
        
        
        $this->dir = $dir;
        $this->getJsonFileList($dir, true, false, $output);
        
        define('YOUR_EOL', "\n");
        
        if($output == 0 || $output == 1 || $output == 2 || $output == 3 ){

            if($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'fileList') === false){
                die("Error! Json file cant close: ".$this->generatedReportDirectory.'/'.'fileList.json');
            }

            if($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'files') === false){
                die("Error! Json file cant close: ".$this->generatedReportDirectory.'/'.'files.json');
            }
            
            if($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'directoryList') === false){
                die("Error! Json file cant close: ".$this->generatedReportDirectory.'/'.'directoryList.json');
            }
            
            if($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'error') === false){
                die("Error! Json file cant close: ".$this->generatedReportDirectory.'/'.'error.json');
            }
            
            $duplicates = array();
            $buffer = "";
            $handle = fopen($this->generatedReportDirectory.'/'.'files.json', "r");
            if ($handle) {
                while (!feof($handle)) {
                    $buffer .= stream_get_line($handle, 4096);
                }
                fclose($handle);
            }
            
            if(!empty($buffer)){
                $arr = array();
                $arr = json_decode($buffer, true);
                $duplicates = $this->chkFunc->checkFileDuplications($arr['data']);
            }
          
            if(count($duplicates) > 0){
                
                if( isset($duplicates["Duplicate_File_And_Size"]) && count($duplicates["Duplicate_File_And_Size"]) > 0){
                    foreach( $duplicates["Duplicate_File_And_Size"] as $k => $v){
                        $arr = array();
                        $arr[$k] = $v;
                        $this->jsonHandler->writeDataToJsonFile( $arr, "duplicates_size", $this->generatedReportDirectory, "json");
                    }
                    
                    if($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'duplicates_size') === false){
                        die("Error! Json file cant close: ".$this->generatedReportDirectory.'/'.'duplicates_size.json');
                    }
                }
                
                if( isset($duplicates["Duplicate_File"]) && count($duplicates["Duplicate_File"]) > 0){
                    foreach( $duplicates["Duplicate_File"] as $k => $v){
                        $arr = array();
                        $arr[$k] = $v;
                        $this->jsonHandler->writeDataToJsonFile( $arr, "duplicates", $this->generatedReportDirectory, "json");
                    }
                    
                    if($this->jsonHandler->closeJsonFiles($this->generatedReportDirectory, 'duplicates') === false){
                        die("Error! Json file cant close: ".$this->generatedReportDirectory.'/'.'duplicates.json');
                    }
                }
            }
            
        }
        
        
        if ($output == 1 || $output == 3 ){
            //create filetype json
        }
        
        
        if ($output == 2 || $output == 3){
            //create basic html
            $this->html->generateFileListHtml($this->generatedReportDirectory);
            
            $this->html->generateErrorListHtml($this->generatedReportDirectory);
            
            $this->html->generateDirListHtml($this->generatedReportDirectory);
            
            
            if ( $output == 3 ){
                //create html with filetype
                
            }
        }
       
    }
    
    
    
    /**
     * 
     * This function creates a json data from the file types
     * 
     * @return string
     */
    private function generateJsonFileTypeList(): string {
        
                
        $extensionList = array();
        $directoryList = array();
        $result = array();


        
        foreach($this->dirList as $d){
            if(isset($d["extension"])){                
                $extensionList[$d["extension"]][] = $d;
            }else{
                $directoryList[] = $d;
            }
        }
        
        //sort alphabetically the extension array elements
        ksort($extensionList, SORT_STRING);
        
        $i = 0;
        foreach($extensionList as $k => $v){
            $fileSumSize = 0;
            $fileCount = 0;
            $min = 0;
            $max = 0;
            $size = array_column($v, 'size');
            $min = min($size);
            $max = max($size);
            
            foreach($v as $val){
                $fileSumSize += $val["size"];
                $fileCount += 1;
            }
            
            $avgSize = $fileSumSize / $fileCount;

            $result[$i]['Extension'] = $k;
            $result[$i]['Count'] = $fileCount;
            $result[$i]['SumSize'] = $this->misc->formatSizeUnits($fileSumSize);
            $result[$i]['AvgSize'] = $this->misc->formatSizeUnits($avgSize);
            $result[$i]['MinSize'] = $this->misc->formatSizeUnits($min);
            $result[$i]['MaxSize'] = $this->misc->formatSizeUnits($max);
            
            $i++;
        }       
        return json_encode($result);
    }
    

    /**
     * 
     * Check the temp directory and the permissions
     * 
     * @param string $str
     * @return bool
     */
    private function checkTmpDir(string $str): bool{
        
        if(is_dir($str) && is_writable($str)){
            return true;
        }else {
            die("\n ERROR tmpDir (".$str.") is not exists or not writable, please check the config.ini \n");
        }        
    }
    
    
    /**
     * 
     * Check the temp directory and the permissions
     * 
     * @param string $str
     * @return bool
     */
    private function checkReportDir(string $str): bool{
        
        if(is_dir($str) && is_writable($str)){
            return true;
        }else {
            die("\nERROR reportDIR (".$str.") is not exists or not writable, please check the config.ini \n");
        }        
    }
 
    private function getJsonFileList(string $dir, bool $recurse=false, bool $depth=false, string $output)
    {
        if(function_exists('mime_content_type')){
            $finfo = false;
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
        }

        $retval = array();
        $jsonOutput = "json";
        if($output == 4){
            $jsonOutput = "ndjson";    
        }
        // add trailing slash if missing
        if(substr($dir, -1) != "/") $dir .= "/";

        echo "\n File list generating...\n";
        // open pointer to directory and read list of files
        $d = @dir($dir) or die("getFileList: Failed opening directory $dir for reading");
        
        $files = scandir($dir);
        // Count number of files and store them to variable..
        $numOfFiles = count($files)-2;
        $pbFL = new \ProgressBar\Manager(0, $numOfFiles);
        $childrenDir = false;
        
        while(false !== ($entry = $d->read())) {
            
            // skip hidden files
            if($entry[0] == ".") continue;
            
            echo $entry."\n";
            
            //DIRECTORY
            if(is_dir("$dir$entry")) {
                
                echo "\nSubDirectory found, checking the contents... \n";
                
                if($recurse && is_readable("$dir$entry/")) { $childrenDir = true;}
                                
                //check the file name validity
                $valid = $this->chkFunc->checkDirectoryNameValidity("$dir$entry");
                if($valid === false){
                    $this->jsonHandler->writeDataToJsonFile( array("errorType" => "Directory_Not_Valid" ,"dir" => "$dir$entry", "filename" => ""), "error", $this->generatedReportDirectory, $jsonOutput);
                }
                
                $retval[] = array(
                    "name" => "$dir$entry/",
                    "directory" => "$dir",
                    "type" => filetype("$dir$entry"),
                    "size" => 0,
                    "lastmod" => date("Y-m-d H:i:s" , filemtime("$dir$entry")),
                    "valid_file" => $valid,
                    "filename" => $entry                    
                );
                
                //create the directory json file
                if(filetype("$dir$entry") == "dir"){
                    $dirData = array();
                    $dirData = array("name" => "$dir$entry", "valid" => $valid,  "lastmodified" => date("Y-m-d H:i:s" , filemtime("$dir$entry")));
                    $this->jsonHandler->writeDataToJsonFile($dirData, "directoryList", $this->generatedReportDirectory, $jsonOutput);
                }  
                
                
                if($recurse && is_readable("$dir$entry/")) {
                    if($depth === false) {
                            $retval = array_merge($retval, $this->getJsonFileList("$dir$entry/", true, false, $output));
                            echo "\nSubDirectory content checked... \n";
                    } elseif($depth > 0) {
                            $retval = array_merge($retval, $this->getJsonFileList("$dir$entry/", true, $depth-1, $output));
                            echo "\nSubDirectory content checked... \n";
                    }
                }
                
                
            //FILE    
            } elseif(is_readable("$dir$entry")) {
                
                $extension = explode('.', $entry);
                $extension = end($extension);
                if(empty($finfo)){
                    if("$dir$entry" != null){
                        if(mime_content_type("$dir$entry") == null){
                            $fileType = "unknown";

                        }else {
                            $fileType = mime_content_type("$dir$entry");
                        }
                    }else {
                        $fileType = "unknown, file error";
                    }
                }else {
                    $fileType = finfo_file($finfo, "$dir$entry");
                }
                //blacklist files
                if($this->chkFunc->checkBlackListFile($extension) == true){
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "File_Blacklisted", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }
                
                //check the file name validity
                $valid = $this->chkFunc->checkFileNameValidity($entry);
                if($valid === false){
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "File_Not_Valid", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }
                
                //check the mime extensions
                $mime = $this->chkFunc->checkMimeTypes($extension, $fileType);
                if($mime === false){
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "Mime_Type_Error", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }
                
                //check the ZIP files
                if(
                    $extension == "zip" || $fileType == "application/zip" || 
                    $extension == "gzip" || $fileType == "application/gzip" ||
                    $extension == "7zip" || $fileType == "application/7zip"
                ){
                    $zipResult = $this->chkFunc->checkZipFiles(array("$dir$entry"));
                    if(count($zipResult) > 0 && isset($zipResult[0])){
                        $this->jsonHandler->writeDataToJsonFile($zipResult[0], "error", $this->generatedReportDirectory, $jsonOutput);
                    }
                }
                
                //check the PDF Files
                 if($extension == "pdf" || $fileType == "application/pdf"){
                    //check the zip files and add them to the zip pwd checking
                    $pdfResult = $this->chkFunc->checkPdfFile("$dir$entry");
                    if(count($pdfResult) > 0 ){
                        $this->jsonHandler->writeDataToJsonFile($pdfResult, "error", $this->generatedReportDirectory, $jsonOutput);
                    }
                }
                //check the RAR files
                if($extension == "rar" || $fileType == "application/rar"){
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "RAR_File_Check_it_manually", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }
                
                //check PW protected XLSX, DOCX
                if(($extension == "xlsx" || $extension == "docx") && $fileType == "application/CDFV2-encrypted"){
                    $this->jsonHandler->writeDataToJsonFile(array("errorType" => "XLSX_DOCX_With_Password", "dir" => $dir, "filename" => $entry), "error", $this->generatedReportDirectory, $jsonOutput);
                }
                
                //check the bagit files
                if (strpos(strtolower($dir), 'bagit') !== false) {
                    $bagItResult = array();
                    $bagItResult = $this->chkFunc->checkBagitFile("$dir$entry");
                    if(count($bagItResult) > 0){
                        $this->jsonHandler->writeDataToJsonFile(array("errorType" =>"Bagit_Error", "dir" => $dir, "filename" => $entry, "errorMSG" => $bagItResult), "error", $this->generatedReportDirectory, $jsonOutput);
                    }
                }
                
                $retval[] = array(
                    "name" => "$dir$entry",
                    "directory" => "$dir",
                    "type" => $fileType,
                    "size" => filesize("$dir$entry"),
                    "lastmod" => date("Y-m-d H:i:s" , filemtime("$dir$entry")),
                    "valid_file" => $valid,
                    "filename" => $entry,
                    "extension" => strtolower($extension)
                );
                
                $fileInfo = array();
                $fileInfo = array(
                    "name" => "$dir$entry",
                    "directory" => "$dir",
                    "type" => $fileType,
                    "size" => filesize("$dir$entry"),
                    "lastmod" =>date("Y-m-d H:i:s" , filemtime("$dir$entry")),
                    "valid_file" => $valid,
                    "filename" => $entry,
                    "extension" => strtolower($extension)
                );
                
                $filesList = array();
                $filesList = array("filename" => $entry, "size" => filesize("$dir$entry"), "dir" => $dir );
                $this->jsonHandler->writeDataToJsonFile($fileInfo, "fileList", $this->generatedReportDirectory, $jsonOutput);
                $this->jsonHandler->writeDataToJsonFile($filesList, "files", $this->generatedReportDirectory, $jsonOutput);
               
            }
            $pbFL->advance();
            echo "\n";
            
        }
               
        $d->close();
        return $retval;
    }
    
}