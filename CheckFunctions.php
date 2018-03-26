<?php


namespace OEAW\Checks;

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
        
        //mime types
        $this->mimeTypes = $this->misc->getMIME();
        $this->mimeTypes = array_change_key_case($this->mimeTypes,CASE_LOWER);
         
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
        if(count($bag->getBagErrors() > 0)){
            $result = $bag->getBagErrors();
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
        if(!isset($this->mimeTypes[strtolower($extension)])){
            return false;
        }else if(is_array($this->mimeTypes[strtolower($extension)]) && 
                !in_array($type, $this->mimeTypes[strtolower($extension)]) && 
                $type != "dir"){
            return false;
        }
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
        $parser = new \Smalot\PdfParser\Parser();
            
        try {
            if((strpos(fgets(fopen($file, 'r')), "%PDF-1.4") !== false) ){
                $return = array("errorType" => "PDF_Version_Above_1.4", "filename" => $file, "dir" => $file);
            }else if (strpos(fgets(fopen($file, 'r')), "%PDF") !== false) {
                $parser->parseFile($file);
            }

        }catch(\Exception $e) {

            if (strpos($e->getMessage(), 'Secured pdf file are currently not supported') !== false) {
                $return = array("errorType" => "Password protected PDF file", "filename" => $file, "dir" => $file);
            }else {
                $errMsg = $e->getMessage();
                $return = array("errorType" => "PDF_Check_Error", "filename" => $file, "dir" => $file, "errorMSG" => $errMsg);
            }
        }
        
        return $return;    
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
        //$pbZip = new \ProgressBar\Manager(0, count($zipFiles));
        
        
        foreach($zipFiles as $f){
           // $pbZip->advance();
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
    
    
    
    
    
}