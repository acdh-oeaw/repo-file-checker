<?php


namespace OEAW\Checks;

use setasign\Fpdi;
use OEAW\Checks\Misc as MC;
require_once 'Misc.php';


class CheckFunctions {
    
    private $blackList = array();
    private $mimeTypes = array();
    private $misc;
    private $tmpDir;
    
    public function __construct(){
        $cfg = parse_ini_file('config.ini');
        
        $this->misc = new MC();
        
        //blacklist
        $bl = $cfg['blackList'];
        $bl = explode(",", trim($bl[0]));
        $this->blackList = array_map('trim',$bl); 
        
        $dirContent = $this->misc->scan_dir_by_date($cfg['signatureDir']);
        if(count($dirContent) > 0){
            $dirContent = $dirContent[0];
            if (strpos($dirContent, '.xml') === false) {
                die('\n Wrong file inside the signatureDir! \n');
            }
            $this->mimeTypes = $this->misc->getMimeFromPronom($cfg['signatureDir'].'/'.$dirContent);
            if(count($this->mimeTypes) < 1) {
                die('MIME type generation failed!');
            }
        }else{
            die("\nThere is no file inside the signature directory!\n");
        }
        
        if($this->checkTmpDir($cfg['tmpDir'])){
            $this->tmpDir = $cfg['tmpDir'];
        }else {
            die();
        }
    }
    
    
    
     /**
     * 
     * Checks the bagit file, and if there is an error then add it to the errors variable
     * 
     * @param string $filename
     * @return bool
     */
    public function checkBagitFile(string $filename): array{
        
        $result = array();
        // use an existing bag
        $bag = new \BagIt($filename);
        $bag->validate();    
        if(is_array($bag->getBagErrors())){
            if(count($bag->getBagErrors() > 0)){
                $result = $bag->getBagErrors();
            }
        }
        return $result;
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
            return false;
        }
    }
    
    /**
     * 
     * Checks the Directory Name validation
     * 
     * @param string $dir
     * @return bool
     */
    public function checkDirectoryNameValidity(string $dir): bool {
 
        if(preg_match("#^(?:[a-zA-Z]:|\.\.?)?(?:[\\\/][a-zA-Z0-9_.\'\"-]*)+$#", $dir) !== 1){
            return false;
        }else{
            return true;
        }

    }
    
    /**
     * 
     * Checks the filename validation
     * 
     * @param string $filename
     * @return bool : true
     */
    public function checkFileNameValidity(string $filename): bool {
        if(preg_match('/[^A-Za-z0-9\_\(\)\-\.]/', $filename)){
            return false;
        }
        return true;
    }
    
    /**
     * 
     * Check the blacklisted elements
     * 
     * @param array $file
     */
    public function checkBlackListFile(string $extension): bool {
        
        foreach ($this->blackList as $bl){
            if(strtolower($bl) == strtolower($extension)){
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 
     * Check the MIME types and extensions based on the MISC.php extension list
     * 
     * 
     * @param string $extension
     * @param string $type
     * @return bool
     */
    public function checkMimeTypes(string $extension, string $type): bool{
        if(!in_array($extension, $this->mimeTypes)){ return false; }
        return true;
    }
    
    /**
     * 
     * Check the PDF version and try to open to be sure that is doesnt have any pwd
     * 
     * @param string $file
     * @return array
     */
    public function checkPdfFile(string $file): array{
        
        $return = array();
        
        try {
            // initiate FPDI
            $pdf = new Fpdi\Fpdi();
            // set the source file
            $pdf->setSourceFile($file);
            $tplId = $pdf->importPage(1);
        } catch (\ErrorException $ex) {
            $return = array("errorType" => "The PDF file is password protected", "filename" => $file, "dir" => $file, "errorMSG" => $ex->getMessage());
        } catch (\Exception $ex) {
            $return = array("errorType" => "The PDF file is password protected", "filename" => $file, "dir" => $file, "errorMSG" => $ex->getMessage());
        }
        return $return;    
    }
    
    /**
     * 
     * Remove the duplications from the array
     * 
     * @param array $data
     * @return type
     */
    public function makeUnique(array $data)
    {
        $serialized = array_map(function($a) {return $a;}, $data);
        $unique = array_unique($serialized);
        return $unique;
        return array_intersect_key($unique, $data);
    }
    
    /**
     * 
     *  Check the file and/or size duplications
     * 
     * @param array $data
     * @return array
     */
    public function checkFileDuplications(array $data): array{
        
        $result = array();
        foreach ($data as $current_key => $current_array) {
            foreach ($data as $search_key => $search_array) {
                if ( $search_array['filename'] == $current_array['filename'] ) {
                    if ($search_key != $current_key) {
                        if( $current_array['size'] == $search_array['size'] ){
                            $result["Duplicate_File_And_Size"][$current_array['filename']][] = $search_array['dir'];
                        }else {
                            $result["Duplicate_File"][$current_array['filename']][] = $search_array['dir'];
                        }
                    }
                }
            }
        }
        
       
        $return = array();
        if(isset($result['Duplicate_File_And_Size'])){
            foreach($result['Duplicate_File_And_Size'] as $k => $v){
                $return['Duplicate_File_And_Size'][$k] = $this->makeUnique($v);
                $return['Duplicate_File_And_Size'][$k] = array_values($return['Duplicate_File_And_Size'][$k]);
            }
        }
        if(isset($result['Duplicate_File'])){
            foreach($result['Duplicate_File'] as $k => $v){
                $return['Duplicate_File'][$k] = $this->makeUnique($v);
                $return['Duplicate_File'][$k] = array_values($return['Duplicate_File'][$k]);
            }
        }
       
        return $return;
    }
    
    
    function real_filesize($file_path)
    {
        $size = 0;
        if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
            $fs = new \COM("Scripting.FileSystemObject");        
            try {
                $getFile = $fs->GetFile($file_path);
                $size = $getFile->Size;
            } catch (\Exception $ex) {
                $size = 0;
            }
            if($size == 0){
                $size = filesize($file_path);
            }
        } else {
            $size = filesize($file_path);
        }
        return $size;

        
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
    public function checkZipFiles(array $zipFiles): array{

        $result = array();
            
        $za = new \ZipArchive();
        //open and extract the zip files
        $pbZip = new \ProgressBar\Manager(0, count($zipFiles));
        
        
        foreach($zipFiles as $f){
            $pbZip->advance();
            if ($za->open($f, \ZIPARCHIVE::CREATE) !== TRUE) {
                $result[] = array("errorType" => "Zip_Open_Error", "filename" => $f, "dir" => $f);
            }else {
                $za->extractTo($this->tmpDir);
                //the zip file has a password
                if($za->status == 26) {
                    //$pwZips[] = $f;
                    $result[] = array("errorType" => "Zip_Password_Error", "filename" => $f, "dir" => $f);
                }
                //get the files in the tmpDir and remove them
                $files = glob($this->tmpDir.'\*'); // get all file names
                foreach($files as $file){ // iterate files
                    if(is_file($file))
                    unlink($file); // delete file
                }
            }            
        }
        return $result;
    }
    
    /**
     * 
     * 
     * Generate the data to for the directory tree view
     * based on the filetypelist.json
     * 
     * @param array $flat
     * @return array
     */
    public function convertDirectoriesToTree(array $flat): array {
        
        $indexed = array();
        $arrKeys = array_keys($flat);
        for ($x = 0; $x <= count($flat) - 1; $x++) {
            $indexed[$x]['text'] = $arrKeys[$x];
            
            if(isset($flat[$arrKeys[$x]]['extension'])){
                
                if(count($flat[$arrKeys[$x]]['extension']) > 0){
                    $i = 0;
                    $children = array();
                    foreach($flat[$arrKeys[$x]]['extension'] as $k => $v){
                        
                        $children[$i]['text'] = $k;
                        if(isset($v["sumSize"])){
                            $children[$i]['children'][] = array("icon" => "jstree-file", "text" => "SumSize: ".$this->misc->formatSizeUnits($v["sumSize"]['sum']));
                        }
                        if(isset($v["fileCount"])){
                            $children[$i]['children'][] =array("icon" => "jstree-file", "text" => "fileCount: ".$v["fileCount"]['fileCount']." file(s)");
                        }
                        if(isset($v["minSize"])){
                            $children[$i]['children'][] = array("icon" => "jstree-file", "text" => "MinSize: ".$this->misc->formatSizeUnits($v["minSize"]['min']));
                        }
                        if(isset($v["maxSize"])){
                            $children[$i]['children'][] = array("icon" => "jstree-file", "text" => "MaxSize: ".$this->misc->formatSizeUnits($v["maxSize"]['max']));
                        }
                        $i++;
                    }
                    
                    $indexed[$x]['children'] = $children;
                }
                
                
            }
            if(isset($flat[$arrKeys[$x]]['dirSumSize'])){
                $indexed[$x]['children'][] = array("icon" => "jstree-file", "text" => "dirSumSize: ".$this->misc->formatSizeUnits($flat[$arrKeys[$x]]['dirSumSize']['sumSize']));
            }
            if(isset($flat[$arrKeys[$x]]['dirSumFiles'])){
                $indexed[$x]['children'][] = array("icon" => "jstree-file", "text" => "dirSumFiles: ".$this->misc->formatSizeUnits($flat[$arrKeys[$x]]['dirSumFiles']['sumFileCount']));
            }
        }
        return $indexed;
    }
    
    /**
     * 
     * Generate the data to for the extension tree view
     * based on the filetypelist.json
     * 
     * @param array $flat
     * @return array
     */
    public function convertExtensionsToTree(array $flat): array {
        
        $indexed = array();
        $arrKeys = array_keys($flat);
        for ($x = 0; $x <= count($flat) - 1; $x++) {
            $indexed[$x]['text'] = $arrKeys[$x];
            $i = 0;
            $children = array();
            foreach($flat[$arrKeys[$x]] as $k => $v){
                //$children[$i] = ['text'] = $k;
                
                if($k == "sumSize"){
                    $children[$i] = array("icon" => "jstree-file", "text" => "SumSize: ".$this->misc->formatSizeUnits($v));
                }
                if( $k == "fileCount"){
                    $children[$i] = array("icon" => "jstree-file", "text" => "fileCount: ".$v." file(s)");
                }
                if( $k == "min"){
                    $children[$i] = array("icon" => "jstree-file", "text" => "MinSize: ".$this->misc->formatSizeUnits($v));
                }
                if($k == "max"){
                    $children[$i] = array("icon" => "jstree-file", "text" => "MaxSize: ".$this->misc->formatSizeUnits($v));
                }
                $i++;
            }

            $indexed[$x]['children'] = $children;
            
        }
        return $indexed;
    }

    
    
    
    
    
    
}