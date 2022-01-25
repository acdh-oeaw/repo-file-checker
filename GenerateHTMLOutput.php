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
    public function generateErrorListHtml(string $directory): bool {
        
        $errorList = "";
        
        $errorList = '<div class="card" id="errors">
                    <div class="header">
                        <h4 class="title">Errors</h4>                            
                    </div>
                <div class="content table-responsive table-full-width" >';
        $errorList .= "<table class=\"table table-hover table-striped\" id=\"errorsDT\">\n";
        $errorList .= "<thead>\n";
        $errorList .= "<tr><th><b>ErrorType</b></th><th><b>Directory</b></th><th><b>File Name</b></th></tr>\n";
        $errorList .= "</thead>\n";
        
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
    public function generateFileListHtml(string $directory): bool {
        
        $fileList = "";
        $fileList = '<div class="card" id="filelist">
                        <div class="header">
                            <h4 class="title">File List</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >';
        $fileList .= "<table class=\"table table-hover table-striped\" id=\"filesDT\" >"
                . "<h2>Files</h2>\n";
        $fileList .= "<thead>\n";
        $fileList .= "<tr>"
                . "<th><b>Filename</b></th>
                <th><b>Directory</b></th>
                <th><b>Extension</b></th>
                <th><b>Type</b></th>
                <th><b>Size</b></th>
                <th><b>Last Modif.</b></th>
                <th><b>Valid</b></th>
                </tr>\n";
        $fileList .= "</thead>\n";
        $fileList .= "</table>\n\n";
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        
        $this->writeDataToHtmlFile($fileList, $directory, "fileList");
        
        return true;
    }
    
     public function generateDirListHtml(string $directory): bool {
        
        $fileList = "";
        $fileList = '<div class="card" id="filelist">
                        <div class="header">
                            <h4 class="title">Directory List</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >';
        $fileList .= "<table class=\"table table-hover table-striped\" id=\"dirsDT\" >"
                . "<h2>Directories</h2>\n";
        $fileList .= "<thead>\n";
        $fileList .= "<tr><th><b>Directory</b></th><th><b>Valid</b></th></tr>\n";
        $fileList .= "</thead>\n";
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
            die2("writeDataToHtmlFile -> missing data");
        }
        copy('template/css/style.css', $directory.'/css/style.css');
        copy('template/js/jquery.js', $directory.'/js/jquery.js');
        copy('template/js/jstree.min.js', $directory.'/js/jstree.min.js');        
        copy('template/css/jquery.dataTables.css', $directory.'/css/jquery.dataTables.css');
        copy('template/js/jquery.dataTables.js', $directory.'/js/jquery.dataTables.js');
        copy('template/js/helper.js', $directory.'/js/helper.js');
        copy('template/css/jstreecss.css', $directory.'/css/jstreecss.css');
        copy('template/css/throbber.gif', $directory.'/css/throbber.gif');
        copy('template/css/40px.png', $directory.'/css/40px.png');
        copy('template/css/32px.png', $directory.'/css/32px.png');
        copy('template/.htaccess', $directory.'/.htaccess');

        $template=file_get_contents('template/template.html');
        $tpl=str_replace("{html_file_content}", $string,$template);
        file_put_contents($directory.'/'.$filename.'.html', $tpl.PHP_EOL , FILE_APPEND | LOCK_EX);
    }
    
    
    public function generateFileTypeListHtml(string $directory): bool {
                
        $extensionList = array();
        
        $file = fopen($directory.'/extensions.json', 'r');
        if(!$file) {
            return false;
        }            
        
        $content = stream_get_contents($file);
        $obj = json_decode($content, true);
        
        foreach($obj as $o ) {
            $ext = "";
            $ext = $o['text'];
            foreach($o['children'] as $d) {
                $text = explode(':', $d['text']);
                $extensionList[$ext][$text[0]] = $text[1];
            }
        }
        ksort($extensionList, SORT_STRING);
       
        $fileList = '<div class="card" id="">
                        <div class="header">
                            <h4 class="title">File Types By Extensions</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >';
        $fileList .= "<table class=\"table table-hover table-striped\" id=\"fileTypeDT\">\n";
            $fileList .= "<thead>\n";
            $fileList .= "<tr><th><b>Extension</b></th><th><b>Count</b></th><th><b>SumSize</b></th><th><b>MinSize</b></th><th><b>MaxSize</b></th></tr>\n";
            $fileList .= "</thead>\n";
            $fileList .= "<tbody>\n";
        foreach($extensionList as $k => $v){
            $fileList .= "<tr>\n";
            $fileList .= "<td width='10%'>{$k}</td>\n";
            $fileList .= "<td width='18%'>{$v['fileCount']}</td>\n";
            $fileList .= "<td width='18%'>{$v['SumSize']}</td>\n";
            $fileList .= "<td width='18%'>{$v['MinSize'] }</td>\n";
            $fileList .= "<td width='18%'>{$v['MaxSize'] }</td>\n";
            $fileList .= "</tr>\n";
        }
        $fileList .= "</tbody>\n";
        $fileList .= "</table>\n\n";        
        
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        $this->writeDataToHtmlFile($fileList, $directory, "fileTypeList");  
        return $fileList;
    }
    
    
    /**
     * Creates the FileTypeList HTML
     * 
     * @return string
     */
    public function generateFileTypeJstreeHtml(string $directory): string {
        
        $fileList = "";
        
        $fileList = '<div class="card" id="fileTypeList">
                        <div class="header">
                            <h4 class="title">File Types By Directory</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >
                    <div class="container-fluid">';
        $fileList .= ' <input type="text" value="" style="box-shadow:inset 0 0 4px #eee; width:200px; margin:0; padding:6px 12px; border-radius:4px; border:1px solid silver; font-size:1.1em;" id="directories_q" placeholder="Search" />
                        <div id="data" class="demo"></div>';
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        
        $fileList .= '<div class="card" id="fileTypeList">
                        <div class="header">
                            <h4 class="title">File Types By Extensions</h4>
                        </div>
                    <div class="content table-responsive table-full-width" >
                    <div class="container-fluid">';
        
        $fileList .= ' <input type="text" value="" style="box-shadow:inset 0 0 4px #eee; width:200px; margin:0; padding:6px 12px; border-radius:4px; border:1px solid silver; font-size:1.1em;" id="extensions_q" placeholder="Search" />
                        <div id="ext_data" class="demo"></div>';
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        
        $this->writeDataToHtmlFile($fileList, $directory, "fileTypeList");
        
        return true;
        
    }
    
}

