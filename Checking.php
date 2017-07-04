<?php 

namespace OEAW\Checks;


use OEAW\Checks\Misc as MC;
require_once 'Misc.php';


require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/scholarslab/bagit/lib/bagit.php';

class Checking {
    
    private $errors = array();
    private $tmpDir;
    private $reportDir;
    private $dirList = array();
    private $dir;
    private $bagitFiles = array();
    private $misc;
    
    public function __construct(){
                
        $cfg = parse_ini_file('config.ini');        
        
        if($this->checkTmpDir($cfg['tmpDir'])){
            $this->tmpDir = $cfg['tmpDir'];
        }else {
            die();
        }
        
         if($this->checkReportDir($cfg['reportDir'])){
            $this->reportDir = $cfg['reportDir'];
        }else {
            die();
        }
        
        $this->misc = new MC();
    }
    
    /**
     * Get the dir what the script should check
     * 
     * @param string $dir
     * @return type
     */
    public function startChecking(string $dir){
             
        
        $mimeTypes = $this->misc->getMIME();
        $this->dirList = $this->getFileList($dir, true);
        $this->dir = $dir;
        
        if(empty($this->dirList)){
            echo "\nERROR there are no files!!! \n\n";
            return;
        }
        
        $this->checkVirusAndFileExtension($dir);
        $this->checkFiles($mimeTypes);
        
        //create the file list html
        $fn = date('Y_m_d_H_i_s');
        mkdir($this->reportDir.'/'.$fn);
        copy('template/style.css', $this->reportDir.'/'.$fn.'/style.css');
        
        $template=file_get_contents('template/template.html');
        
        if(!empty($fList = $this->generateFileListHtml())){
            $tpl=str_replace("{html_file_content}", $fList,$template);
        }else {
            $tpl=str_replace("{html_file_content}", "File list is empty",$template);
        }
        file_put_contents($this->reportDir.'/'.$fn.'/fileList.html', $tpl.PHP_EOL , FILE_APPEND | LOCK_EX);
        
        if(!empty($fTypeList = $this->generateFileTypeList())){
            $tplFL=str_replace("{html_file_content}", $fTypeList,$template);
        }else{
            $tplFL=str_replace("{html_file_content}", "File Type list is empty",$template);
        }
        file_put_contents($this->reportDir.'/'.$fn.'/fileTypeList.html', $tplFL.PHP_EOL , FILE_APPEND | LOCK_EX);
        
        if($this->errors){
            $errorList = $this->generateErrorReport();
            if(!empty($errorList)){
                $tplE=str_replace("{html_file_content}", $errorList,$template);
            }
        }else{
            $tplE=str_replace("{html_file_content}", "Error list is empty",$template);
        }
        file_put_contents($this->reportDir.'/'.$fn.'/errorList.html', $tplE.PHP_EOL , FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 
     * Checks the bagit file, and if there is an error then add it to the errors variable
     * 
     * @param string $filename
     * @return bool
     */
    private function checkBagitFile(string $filename): bool{
        
        // use an existing bag
        $bag = new \BagIt($filename);
        $bag->validate();    
        if(count($bag->getBagErrors() > 0)){
            $this->errors['bagITError'][$filename] = $bag->getBagErrors();
        }
        return true;
    }
    
    /**
     * Creates the FileTypeList HTML
     * 
     * @return string
     */
    private function generateFileTypeList(): string {
        if(empty($this->dirList)){
            echo "ERROR genereateFileTypeList function has no data \n\n".$f;
            sleep(1);
            return false;
        }
                
        $extensionList = array();
        $directoryList = array();
        foreach($this->dirList as $d){
            if(isset($d["extension"])){
                
                $extensionList[$d["extension"]][] = $d;
            }else{
                $directoryList[] = $d;
            }
        }
        
        $fileList = '<div class="card" id="fileTypeList">
                        <div class="header">
                            <h4 class="title">File Type List</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >';
        
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
                        
            $fileList .= "<table class=\"table table-hover table-striped\">\n";
            $fileList .= "<thead>\n";
            $fileList .= "<tr><th><b>Extension</b></th><th><b>Count</b></th><th><b>SumSize</b></th><th><b>AvgSize</b></th><th><b>MinSize</b></th><th><b>MaxSize</b></th></tr>\n";
            $fileList .= "</thead>\n";
            $fileList .= "<tbody>\n";
            $fileList .= "<tr>\n";
            $fileList .= "<td width='10%'>{$k}</td>\n";
            $fileList .= "<td width='18%'>{$fileCount}</td>\n";
            $fileList .= "<td width='18%'>".$this->misc->formatSizeUnits($fileSumSize)."</td>\n";
            $fileList .= "<td width='18%'>".$this->misc->formatSizeUnits($avgSize)."</td>\n";
            $fileList .= "<td width='18%'>".$this->misc->formatSizeUnits($min)."</td>\n";
            $fileList .= "<td width='18%'>".$this->misc->formatSizeUnits($max)."</td>\n";
            $fileList .= "</tr>\n";
            $fileList .= "</tbody>\n";
            $fileList .= "</table>\n\n";        
        }
        
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
                
        return $fileList;
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
            $this->errors['tmpDIR'][] = "tmpDir (".$str.") is not exists or not writable, please check the config.ini";
            echo "\n ERROR tmpDir (".$str.") is not exists or not writable, please check the config.ini \n";
            return false;
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
            $this->errors['reportDIR'][] = "reportDIR (".$str.") is not exists or not writable, please check the config.ini";
            echo "\nERROR reportDIR (".$str.") is not exists or not writable, please check the config.ini \n";
            return false;
        }        
    }

    /**
     * 
     * check the zip files, we extract them to know if it is pwd protected or not
     * 
     * If we have a pw protected one then we will put it to the $pwZips array
     * 
     * @param array $zipFiles
     * @return array
     */
    private function checkZipFiles(array $zipFiles): array{

        $pwZips = array();        
        $za = new \ZipArchive();
        //open and extract the zip files
        $pbZip = new \ProgressBar\Manager(0, count($zipFiles));
        
        foreach($zipFiles as $f){
            $pbZip->advance();
            if ($za->open($f, \ZIPARCHIVE::CREATE) !== TRUE) {
                $this->errors['ZipFileError'][] = $f;
            }else {
                $za->extractTo($this->tmpDir);
                //the zip file has a password
                if($za->status == 26) {
                    $pwZips[] = $f;
                }
                //get the files in the tmpDir and remove them
                $files = glob($this->tmpDir.'\*'); // get all file names
                foreach($files as $file){ // iterate files
                    if(is_file($file))
                    unlink($file); // delete file
                }
            }
            
        }
        return $pwZips;
    }
    
    /**
     * 
     * Check the pdf files, if some of them contains Password then it will be added to the return array
     * 
     * @param array $pdfFiles
     * @return array
     */
    private function checkPdfFiles(array $pdfFiles): array{
                
        $pdf = new \FPDI();
        $filename = "";
        $return = array();

        foreach($pdfFiles as $file){            
            try {
                $filename = $file;
		$pdf->setSourceFile($file);
            }catch(\Exception $e) {
		$return[] = $filename;
            }
        }
        
        return $return;
        
    }
   
    /**
     * 
     * Here we using the phpMussel plugin to check the files in the directory
     * 
     * Virus, extension, file content checking
     * 
     * @param string $str
     * @return bool
     */
    private function checkVirusAndFileExtension(string $str): bool{

        echo "\n######## - Virus and File Extension checking Starting - ########\n";
        //check the viruses
        include 'phpMussel\loader.php';
        
        $virusRes = $phpMussel['Scan']($str, true, false);

        //get the problematic files
        foreach($virusRes as $res){            
            if ((strpos($res, 'No problems') === false)) { 
                $this->errors['VF'][] = $res;                 
            }
        }
        
        if(isset($this->errors['VF']) && (count($this->errors['VF']) > 0)){
            echo "\nERROR During the Virus and File checking, please check the report\n";
        }
        
        echo "\n######## - Virus and File Extension checking Ended - ########\n";
        sleep(1);
        return true;
    }


    /**
     *      
     * @param array $mimeTypes
     * @return bool
     */
    private function checkFiles(array $mimeTypes): bool{

        echo "\n######## - Files checking Starting - ########\n";
        sleep(1);
        
        $zipFiles = array();
        $pdfFiles = array();
        
        
        if($this->dirList){
            
            $progressBar = new \ProgressBar\Manager(0, count($this->dirList));
            echo "\nChecking Filenames, extensions\n";
            $mimeTypes = array_change_key_case($mimeTypes,CASE_LOWER);
            
            foreach($this->dirList as $file){
                $duplicates[] = $file['filename'];
                $duplicatesData[$file['filename']][] = $file['name'];
                $progressBar->advance();
                
                if(isset($file['extension'])){
                    if(!isset($mimeTypes[$file['extension']]) && $file['type'] != "dir"){
                        $this->errors['MIME'][$file['filename']]['filename'] = $file['filename'];
                        $this->errors['MIME'][$file['filename']]['type'] = $file['type'];
                        $this->errors['MIME'][$file['filename']]['extension'] = $file['extension'];                        
                        //checking the array extensions list too
                    }else if(is_array($mimeTypes[$file['extension']])
                            && !in_array($file['type'], $mimeTypes[$file['extension']]) && $file['type'] != "dir"){
                        $this->errors['MIME'][$file['filename']]['filename'] = $file['filename'];
                        $this->errors['MIME'][$file['filename']]['type'] = $file['type'];
                        $this->errors['MIME'][$file['filename']]['extension'] = $file['extension'];
                    }
                    
                    if(
                        $file['extension'] == "zip" || $file['type'] == "application/zip" || 
                        $file['extension'] == "gzip" || $file['type'] == "application/gzip" ||
                        $file['extension'] == "7zip" || $file['type'] == "application/7zip"
                    ){
                        //check the zip files and add them to the zip pwd checking
                        $zipFiles[] = $file["name"];
                    }
                    
                    if($file['extension'] == "pdf" || $file['type'] == "application/pdf"){
                        //check the zip files and add them to the zip pwd checking
                        $pdfFiles[] = $file["name"];
                    }
                    
                    if($file['extension'] == "rar" || $file['type'] == "application/rar"){
                        echo "\n You have RAR Files! Please check them! \n";
                    }
                    
                    if(($file['extension'] == "xlsx" || $file['extension'] == "docx")&& $file['type'] == "application/CDFV2-encrypted"){
                        $this->errors['xlsxPW'][] = $file["name"];
                    }
                }

                //if the file name is not valid
                if($file['valid_file'] == false){
                    $this->errors['WRONGFILES'][] = $file['name'];                 
                }
            }
            
            if(count($this->bagitFiles) > 0){
                foreach($this->bagitFiles as $v){
                    if($this->checkBagitFile($v) === false){
                        $this->erros['bagitError'][] = $v;
                    }
                }                    
            }

            $duplicateFiles = array_count_values($duplicates);
            
            if(count($duplicateFiles) > 0){               
                echo "\nChecking the duplicated file(s)....\n";
                sleep(1);
                foreach($duplicateFiles as $k => $v){
                    if($v > 1){
                        sleep(1);
                        $this->errors['DUPLICATES'][] = $duplicatesData[$k];
                    }
                }                
            }

            if(count($zipFiles) > 0){
                echo "\nChecking the zip file(s)....\n";
                sleep(1);
                $zips = array();
                $zips = $this->checkZipFiles($zipFiles);
                
                if(!empty($zips)){
                    $this->errors['ZipFileError'] = $zips;
                    echo "\nERROR ZIP FILES WITH PASSWORD, please check the report \n";
                }
            }
            
            if(count($pdfFiles) > 0){
                $pdfResult = $this->checkPdfFiles($pdfFiles);
                if(count($pdfResult) > 0){
                    $this->errors['PDFError'] = $pdfResult;
                }
            }
            
            if(empty($this->errors)){
                echo "\n Everything is okay! \n";
                echo "\nFile List ok! Generating HTML with the File list\n";
                sleep(1);

                if($this->generateFileListHtml() === false){
                    $this->errors['GENFILE'][] = "\nERROR During the generateFileListHtml function \n\n";
                    echo "\nERROR During the file list generating, please check the report \n";                    
                    
                }else {
                    echo "\n File list HTML report is ready! \n\n";
                    sleep(1);
                }
            }            

        }else {
            echo "\nERROR During the getFileList function \n\n";		
            echo "\n######## - FileNames checking Ended - ########\n";
            sleep(1);
            return false;
        }

        echo "\n######## - FileNames checking Ended - ########\n";
        sleep(1);
        return true;
    }




    /**
     * 
     * Generate HTML file from the file list
     * 
     * @param array $data
     * @return boolean
     */
    private function generateFileListHtml(): string {
        
        if(empty($this->dirList)){
            echo "ERROR generateFileListHtml function has no data \n\n".$f;
            sleep(1);
            return false;
        }
        $dirArr = array();
        $fileArr = array();
        
        //create file and dir array for the lists
        foreach($this->dirList as $file) {            
            if($file['type'] == "dir"){                
                //get the folder depth
                $dirDepth = array();
                $dir = str_replace($this->dir."/", "", $file["name"]);
                if(!empty($dir)){
                    $dirDepth = explode("/", $dir);
                    $file["dirDepth"] = count(array_filter($dirDepth));
                }
                $dirArr[] = $file;                
            }else {
                $fileArr[] = $file;
            }
        }
        $dirArr[] = array("directory" => $this->dir."/", "name" => $this->dir."/", "filename" => "root_dir");
        
        $dirFileSizes = array();        
        $fileList = "";
        $fileList = '<div class="card" id="filelist">
                        <div class="header">
                            <h4 class="title">File List</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >';
        $fileList .= "<table class=\"table table-hover table-striped\">"
                . "<h2>Files</h2>\n";
        $fileList .= "<thead>\n";
        $fileList .= "<tr><th><b>Directory</b></th><th><b>Filename</b></th><th><b>Type</b></th><th><b>Size</b></th><th><b>Last Modified</b></th></tr>\n";
        $fileList .= "</thead>\n";
        $fileList .= "<tbody>\n";
        foreach($fileArr as $f){
            if(isset($dirFileSizes[$f['directory']])){
                $dirFileSizes[$f['directory']] += $f["size"];
            }else {
                $dirFileSizes[$f['directory']] = $f["size"];
            }
            $fileList .= "<tr>\n";
            $fileList .= "<td>{$f['directory']}</td>\n";
            $fileList .= "<td>{$f['filename']}</td>\n";
            $fileList .= "<td>{$f['type']}</td>\n";
            $fileList .= "<td>".$this->misc->formatSizeUnits($f['size'])."</td>\n";
            $fileList .= "<td>".date('r', $f['lastmod'])."</td>\n";
            $fileList .= "</tr>\n";
        }   
        $fileList .= "</tbody>\n";
        $fileList .= "</table>\n\n";
        
        $fileList .= "<table class=\"table table-hover table-striped\">"
                . "<h2>Directories</h2>\n";
        $fileList .= "<thead>\n";
        $fileList .= "<tr><th><b>Directory</b></th><th><b>SubDir</b></th><th><b>Directory Depth</b></th><th><b>Dir. Sum File Size</b></th><th><b>Last Modified</b></th></tr>\n";
        $fileList .= "</thead>\n";
        $fileList .= "<tbody>\n";
        
        
        foreach($dirArr as $d){
            $dirFS = 0;
            if(!isset($d["dirDepth"])){ $d["dirDepth"] = 0; }
            if(!isset($d["lastmod"])){ $lm = ""; }else {$lm = date('r', $d['lastmod']);}
            
            if(!empty($dirFileSizes[$d['name']])){
                $dirFS = $dirFileSizes[$d['name']];
            }
            
            $fileList .= "<tr>\n";
            $fileList .= "<td>{$d['directory']}</td>\n";
            $fileList .= "<td>{$d['filename']}</td>\n";
            $fileList .= "<td>{$d['dirDepth']}</td>\n";
            $fileList .= "<td>".$this->misc->formatSizeUnits($dirFS)."</td>\n";
            $fileList .= "<td>{$lm}</td>\n";
            $fileList .= "</tr>\n";
        }   
        
        $fileList .= "</tbody>\n";
        $fileList .= "</table>\n\n";
        
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        
        return $fileList;
    }
    
   
    
    private function generateErrorReport(): string{
        
        if(empty($this->dirList)){
            echo "ERROR generateErrorReport function has no data \n\n".$f;
            sleep(1);
            return false;
        }
        
        $errorList = "";
        
        
        if($this->errors){
            $errorList = '<div class="card" id="errors">
                        <div class="header">
                            <h4 class="title">Errors</h4>                            
                        </div>
                    <div class="content table-responsive table-full-width" >';
            $errorList .= "<table class=\"table table-hover table-striped\">\n";
            $errorList .= "<thead>\n";
            $errorList .= "<tr><th><b>Error description</b></th><th><b>Filename/Error information</b></th></tr>\n";
            $errorList .= "</thead>\n";
            $errorList .= "<tbody>\n";
            
            if(!empty($this->errors['VF']) && count($this->errors['VF']) > 0){
                foreach($this->errors['VF'] as $f){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR during the Virus and File Extension checking </td>\n";
                    
                    if (strpos($f, 'blacklist') !== false) {
                        $errorList .= "<td style='color:white; background-color:#FFB74D'>";
                    } elseif(strpos($f, 'chameleon') !== false){
                        $errorList .= "<td style='color:white; background-color:#2c3e50'>";
                    } else {
                        $errorList .= "<td>";
                    }
                    
                    $errorList .= "{$f}</td>\n";
                    $errorList .= "</tr>\n";
                }
            }

            if(!empty($this->errors['tmpDIR']) && count($this->errors['tmpDIR']) > 0){
                foreach($this->errors['tmpDIR'] as $f){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR TMP DIR writing error </td>\n";
                    $errorList .= "<td>{$f}</td>\n";
                    $errorList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['GENFILE']) && count($this->errors['GENFILE']) > 0){
                foreach($this->errors['GENFILE'] as $f){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR during the FILE LIST Generating</td>\n";
                    $errorList .= "<td>{$f}</td>\n";
                    $errorList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['ZipFileError']) && count($this->errors['ZipFileError']) > 0){
                foreach($this->errors['ZipFileError'] as $f){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR Password protected zip file(s) or wrong zip file(s): </td>\n";
                    $errorList .= "<td>{$f}</td>\n";
                    $errorList .= "</tr>\n";
                }
            }
            
            //wrong MIME error MSG
            if(!empty($this->errors['MIME']) && count($this->errors['MIME'])  > 0){
                foreach($this->errors['MIME'] as $value){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR WRONG MIME TYPES </td>\n";
                    $errorList .= "<td>FileName: {$value['filename']}<br> Extension: {$value['extension']} <br> MIME type: {$value['type']} </td>\n";
                    $errorList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['DUPLICATES']) && count($this->errors['DUPLICATES']) > 0){
                foreach($this->errors['DUPLICATES'] as $v){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR File Duplication:</td>\n";
                    $errorList .= "<td>";
                    $errorList .= "<ul>";
                    foreach($v as $val){
                        $errorList .= "<li>".$val."</li>";
                    } 
                    $errorList .= "</ul>";
                    $errorList .= "</td>";
                    $errorList .= "</tr>\n";
                }
            }

            if(!empty($this->errors['WRONGFILES']) && count($this->errors['WRONGFILES']) > 0){
                foreach($this->errors['WRONGFILES'] as $f){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR Not valid file name(s):</td>\n";
                    $errorList .= "<td>{$f}</td>\n";
                    $errorList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['xlsxPW']) && count($this->errors['xlsxPW']) > 0){
                foreach($this->errors['xlsxPW'] as $f){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR Password proected XLSX/DOCX file(s):</td>\n";
                    $errorList .= "<td>{$f}</td>\n";
                    $errorList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['PDFError']) && count($this->errors['PDFError']) > 0){
                foreach($this->errors['PDFError'] as $f){
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR Password proected PDF file(s):</td>\n";
                    $errorList .= "<td>{$f}</td>\n";
                    $errorList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['bagITError']) && count($this->errors['bagITError']) > 0){
                
                foreach($this->errors['bagITError'] as $k => $v){                    
                    $errorList .= "<tr>\n";
                    $errorList .= "<td>ERROR BagIT file validation Error: </td>\n";
                    $errorList .= "<td>BagIT filename: {$k} <br><br>";
                    $errorList .= "Errors: <br>";
                        foreach($v as $val){
                            if(isset($val[0])){
                                $errorList .= "Filename:{$val[0]} <br> ";
                            }
                            if(isset($val[1])){
                                $errorList .= "Error description:{$val[1]} <br> ";
                            }                            
                        }
                    $errorList .= "</td>\n";    
                    $errorList .= "</tr>\n";
                }
            }
            
            $errorList .= "</tbody>\n";
            $errorList .= "</table>\n\n";
            $errorList .= "</div>\n\n";
            $errorList .= "</div>\n\n";        
        }
        
        return $errorList;
    }
    
    /**
     * 
     * Check the files in the specified directory and make an array from the content
     * 
     * @param string $dir
     * @param bool $recurse
     * @param bool $depth
     * @return array
     */
    private function getFileList(string $dir, bool $recurse=false, bool $depth=false): array
    {
        if(function_exists('mime_content_type')){
          $finfo = false;
        } else {
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
        }

        $retval = array();

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
            
            if(is_dir("$dir$entry")) {
                echo "\nSubDirectory found, checking the contents... \n";
                $valid = true;
                
                if($recurse && is_readable("$dir$entry/")) { $childrenDir = true;}
                
                $retval[] = array(
                    "name" => "$dir$entry/",
                    "directory" => "$dir",
                    "type" => filetype("$dir$entry"),
                    "size" => 0,
                    "lastmod" => filemtime("$dir$entry"),
                    "valid_file" => $valid,
                    "filename" => $entry                    
                );
                
                if($recurse && is_readable("$dir$entry/")) {
                    if($depth === false) {
                            $retval = array_merge($retval, $this->getFileList("$dir$entry/", true));
                            echo "\nSubDirectory content checked... \n";
                    } elseif($depth > 0) {
                            $retval = array_merge($retval, $this->getFileList("$dir$entry/", true, $depth-1));
                            echo "\nSubDirectory content checked... \n";
                    }
                }
            } elseif(is_readable("$dir$entry")) {
                $valid = true;
                //check the file name
                if(preg_match('/[^A-Za-z0-9\_\-\.]/', $entry)){ $valid = false; }

                $extension = explode('.', $entry);
                $extension = end($extension);

                if (strpos(strtolower($dir), 'bagit') !== false) {
                    $this->bagitFiles[] = "$dir$entry";
                }
                
                $retval[] = array(
                    "name" => "$dir$entry",
                    "directory" => "$dir",
                    "type" => ($finfo) ? finfo_file($finfo, "$dir$entry") : mime_content_type("$dir$entry"),
                    "size" => filesize("$dir$entry"),
                    "lastmod" => filemtime("$dir$entry"),
                    "valid_file" => $valid,
                    "filename" => $entry,
                    "extension" => strtolower($extension)
                );
            }
            
            $pbFL->advance();
            echo "\n";
            
        }
        
        $d->close();
        return $retval;
    }
    
    
    
    
   
    

}