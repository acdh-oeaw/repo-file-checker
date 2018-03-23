<?php


namespace OEAW\Checks;

use OEAW\Checks\Misc as MC;
require_once 'Misc.php';


class GenerateHTMLOutput {
    
    private $misc;
    
    public function __construct(){
        $this->misc = new MC();
    }
    
    /**
     * 
     * Generate HTML file from the error list
     * 
     * @return string
     */
    public function generateErrorListHtml(array $data, string $directory): bool {
        
       if(!count($data) > 0){
            return false;
        }
        
        $errorList = "";
        
        $errorList = '<div class="card" id="errors">
                    <div class="header">
                        <h4 class="title">Errors</h4>                            
                    </div>
                <div class="content table-responsive table-full-width" >';
        $errorList .= "<table class=\"table table-hover table-striped\" id=\"errorsDT\">\n";
        $errorList .= "<thead>\n";
        $errorList .= "<tr><th><b>Error description</b></th><th><b>Filename/Error information</b></th></tr>\n";
        $errorList .= "</thead>\n";
        $errorList .= "<tbody>\n";
        foreach($data as $k){
            $errorList .= "<tr>\n";
            
            $errorList .= "<td>";
            if(isset($k->errorType)){
                $errorList .= "{$k->errorType}";
            }else{
                $errorList .= "error_";
            }
            
            $errorList .= "</td>\n";
        
            $errorList .= "<td>";
            if(isset($k->dir)){
                $errorList .= "<b>Directory:</b> {$k->dir} \n";
            }
            if(isset($k->filename)){
                $errorList .= "<b>File:</b> {$k->filename} \n";
            }
            if(isset($k->errorMSG)){
                if(is_array($k->errorMSG)){
                    $errorList .= "<b>errorMSG:</b>\n";
                    foreach ($k->errorMSG as $v){
                        $errorList .= serialize($v)."\n";
                    }
                }else{
                    $errorList .= "<b>errorMSG:</b> {$k->errorMSG} \n";
                }
            }
            $errorList .= "</td>\n";    
            $errorList .= "</tr>\n";
        }
        $errorList .= "</tbody>\n";
        $errorList .= "</table>\n\n";
        $errorList .= "</div>\n\n";
        $errorList .= "</div>\n\n";        
        
        $this->writeDataToHtmlFile($errorList, $directory, "errorList");
        
        return true;
    }
    
    /**
     * 
     * Generate HTML file from the file list
     * 
     * @param array $data
     * @return boolean
     */
    public function generateFileListHtml(array $data, string $directory): bool {
        
        if(!count($data) > 0){
            return false;
        }
        
        $dirFileSizes = array();        
        
        $fileList = "";
        $fileList = '<div class="card" id="filelist">
                        <div class="header">
                            <h4 class="title">File List</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >';
        $fileList .= "<table class=\"table table-hover table-striped\" id=\"filesDT\" >"
                . "<h2>Files</h2>\n";
        $fileList .= "<thead>\n";
        $fileList .= "<tr><th><b>Directory</b></th><th><b>Filename</b></th><th><b>Type</b></th><th><b>Size</b></th><th><b>Last Modified</b></th></tr>\n";
        $fileList .= "</thead>\n";
        $fileList .= "<tbody>\n";
        
        foreach($data as $f){
            if(isset($dirFileSizes[$f->directory])){
                $dirFileSizes[$f->directory] += $f->size;
            }else {
                $dirFileSizes[$f->directory] = $f->size;
            }
            $fileList .= "<tr>\n";
            $fileList .= "<td>{$f->directory}</td>\n";
            $fileList .= "<td>{$f->filename}</td>\n";
            $fileList .= "<td>{$f->type}</td>\n";
            $fileList .= "<td>".$this->misc->formatSizeUnits($f->size)."</td>\n";
            $fileList .= "<td>".date('r', $f->lastmod)."</td>\n";
            $fileList .= "</tr>\n";
        }   
        $fileList .= "</tbody>\n";
        $fileList .= "</table>\n\n";
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        
        $this->writeDataToHtmlFile($fileList, $directory, "fileList");
        
        return true;
    }
    
    
     public function generateDirListHtml(array $data, string $directory): bool {
        
        if(!count($data) > 0){
            return false;
        }
        
        $fileList = "";
        $fileList = '<div class="card" id="filelist">
                        <div class="header">
                            <h4 class="title">Directory List</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >';
        $fileList .= "<table class=\"table table-hover table-striped\" id=\"filesDT\" >"
                . "<h2>Files</h2>\n";
        $fileList .= "<thead>\n";
        $fileList .= "<tr><th><b>Directory</b></th><th><b>Valid</b></th><th><b>Last Modified</b></th></tr>\n";
        $fileList .= "</thead>\n";
        $fileList .= "<tbody>\n";
        
        foreach($data as $f){
            $fileList .= "<tr>\n";
            $fileList .= "<td>{$f->name}</td>\n";
            $valid = "no";
            if(isset($f->valid) && $f->valid === true){
                $valid = "yes";
            }
            $fileList .= "<td>{$valid}</td>\n";
            $fileList .= "<td>".date('r', $f->lastmodified)."</td>\n";
            $fileList .= "</tr>\n";
        }   
        $fileList .= "</tbody>\n";
        $fileList .= "</table>\n\n";
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        
        
        $this->writeDataToHtmlFile($fileList, $directory, "directoryList");
        
        return true;
    }
    
    /**
     * 
     * Write string into HTML file
     * 
     * @param string $string
     * @param string $directory
     */
    public function writeDataToHtmlFile(string $string, string $directory, string $filename){
        
        if(empty($string) || empty($directory) || empty($filename)){
            die("writeDataToHtmlFile -> missing data");
        }
        copy('template/style.css', $directory.'/style.css');
        copy('template/jquery.js', $directory.'/jquery.js');
        copy('template/jquery.dataTables.css', $directory.'/jquery.dataTables.css');
        copy('template/jquery.dataTables.js', $directory.'/jquery.dataTables.js');

        $template=file_get_contents('template/template.html');
        $tpl=str_replace("{html_file_content}", $string,$template);
        file_put_contents($directory.'/'.$filename.'.html', $tpl.PHP_EOL , FILE_APPEND | LOCK_EX);
    }
    
    
   
    
    /**
     * Creates the FileTypeList HTML
     * 
     * @return string
     */
    private function generateFileTypeList(): string {
        if(empty($this->dirList)){
            echo "ERROR genereateFileTypeList function has no data \n\n".$f;            
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
        
        //sort alphabetically the extension array elements
        ksort($extensionList, SORT_STRING);
        
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
    
}

