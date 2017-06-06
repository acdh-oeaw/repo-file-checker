<?php 

namespace oeaw\checks;

class Checking {
    
    private $errors = array();
    private $tmpDir;
    private $dirList = array();
        
    
    public function __construct(){
                
        $cfg = parse_ini_file('config.ini');        
        
        if($this->checkTmpDir($cfg['tmpDir'])){
            $this->tmpDir = $cfg['tmpDir'];
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
    public function startChecking(string $dir){
     
        $mimeTypes = $this->getMIME();        
        $this->dirList = $this->getFileList($dir, true);
        
        if(empty($this->dirList)){
            echo "\nERROR!!!! there are no files!!! \n\n";
            return;
        }

        $this->checkVirusAndFileExtension($dir);
        
        $this->checkFiles($mimeTypes);
        
        $this->generateFileListHtml();
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
            echo "\n!!! ERROR !!! tmpDir (".$str.") is not exists or not writable, please check the config.ini !!! ERROR !!!\n";            
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
        foreach($zipFiles as $f){
            $za->open($f);
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
        return $pwZips;
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
        sleep(1);
        //check the viruses
        include 'phpMussel\loader.php';
        $virusRes = $phpMussel['Scan']($str, true, false);

        //get the problematic files
        foreach($virusRes as $res){
            if ((strpos($res, 'No problems') === false)) { 
                $this->errors['VF'][] = $res; 
                echo "\n!!! ERROR !!! During the Viirus and File checking, please check the report !!! ERROR !!!\n";
            }
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

        if($this->dirList){		
            echo "\nChecking Filenames, extensions\n";
            sleep(1);

            foreach($this->dirList as $file){
                $duplicates[] = $file['filename'];
                
                if(isset($file['extension']) && !isset($mimeTypes[$file['extension']]) && $file['type'] != "dir"){                
                    $this->errors['MIME'][$file['filename']]['filename'] = $file['filename'];
                    $this->errors['MIME'][$file['filename']]['type'] = $file['type'];
                    $this->errors['MIME'][$file['filename']]['extension'] = $file['extension'];
                    echo "\n!!! ERROR !!! WRONG MIME or EXTENSION, please check the report !!! ERROR !!!\n";
                    //checking the array extensions list too
                }else if(isset($file['extension']) && is_array($mimeTypes[$file['extension']]) 
                        && !in_array($file['type'], $mimeTypes[$file['extension']]) && $file['type'] != "dir"){
                    $this->errors['MIME'][$file['filename']]['filename'] = $file['filename'];
                    $this->errors['MIME'][$file['filename']]['type'] = $file['type'];
                    $this->errors['MIME'][$file['filename']]['extension'] = $file['extension'];
                    echo "\n!!! ERROR !!! WRONG MIME or EXTENSION, please check the report !!! ERROR !!!\n";
                }else if((isset($file['extension'])) && ($file['extension'] == "zip" || $file['type'] == "application/zip")){
                    //check the zip files and add them to the zip pwd checking
                    $zipFiles[] = $file["name"];
                }

                //if the file name is not valid
                if($file['valid_file'] == false){
                    $this->errors['WRONGFILES'][] = $file['name'];
                    echo "\n!!! ERROR !!! NOT VALID FILES, please check the report !!! ERROR !!!\n";                        
                }
            }

            $duplicateFiles = array_count_values($duplicates);

            if(!empty($duplicateFiles)){            
                echo "\nChecking the duplicated files....\n";
                sleep(1);
                foreach($duplicateFiles as $k => $v){
                    if($v > 1){ 
                        echo "\n!!! ERROR !!! DUPLICATED FILES, please check the report !!! ERROR !!!\n";
                        sleep(1);
                        $this->errors['DUPLICATES'][] = $k;                     
                    }
                }
            }

            if(!empty($zipFiles)){
                echo "\nChecking the zip files....\n";
                sleep(1);
                $zips = array();
                $zips = $this->checkZipFiles($zipFiles);
                
                if(!empty($zips)){
                    $this->errors['ZipFileError'] = $zips;
                    echo "\n!!! ERROR !!! ZIP FILES WITH PASSWORD, please check the report !!! ERROR !!!\n";
                }
            }
            
            if(empty($this->errors)){
                echo "\n Everything is okay! \n";
                echo "\nFile List ok! Generating HTML with the File list\n";
                sleep(1);

                if($this->generateFileListHtml() === false){
                    $this->errors['GENFILE'][] = "\nERROR!!!! During the generateFileListHtml function \n\n";
                    echo "\n!!! ERROR !!! During the file list generating, please check the report !!! ERROR !!!\n";                    
                    
                }else {
                    echo "\n File list HTML report is ready! \n\n";
                    sleep(1);
                }
            }            

        }else {
            echo "\nERROR!!!! During the getFileList function \n\n";		
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
    private function generateFileListHtml(): bool {
        
        if(empty($this->dirList)){
            echo "ERROR!!!! generateFileListHtml function has no data \n\n".$f;
            sleep(1);
            return false;
        }
        
        $fileList = '<div class="card" id="filelist">
                        <div class="header">
                            <h4 class="title">File List</h4>                            
                        </div>
                    <div class="content table-responsive table-full-width" >';
        $fileList .= "<table class=\"table table-hover table-striped\">\n";        
        $fileList .= "<thead>\n";
        $fileList .= "<tr><th><b>Name</b></th><th><b>Type</b></th><th><b>Size</b></th><th><b>Last Modified</b></th><th><b>Filename</b></th></tr>\n";
        $fileList .= "</thead>\n";
        $fileList .= "<tbody>\n";
        foreach($this->dirList as $file) {
            $fileList .= "<tr>\n";
            $fileList .= "<td>{$file['name']}</td>\n";
            $fileList .= "<td>{$file['type']}</td>\n";
            $fileList .= "<td>{$file['size']}</td>\n";
            $fileList .= "<td>".date('r', $file['lastmod'])."</td>\n";
            $fileList .= "<td>{$file['filename']}</td>\n";		
            $fileList .= "</tr>\n";
        }
        $fileList .= "</tbody>\n";
        $fileList .= "</table>\n\n";
        $fileList .= "</div>\n\n";
        $fileList .= "</div>\n\n";
        
        if($this->errors){
            $fileList .= '<div class="card" id="errors">
                        <div class="header">
                            <h4 class="title">Errors</h4>                            
                        </div>
                    <div class="content table-responsive table-full-width" >';
            $fileList .= "<table class=\"table table-hover table-striped\">\n";
            $fileList .= "<thead>\n";
            $fileList .= "<tr><th><b>Error description</b></th><th><b>Filename/Error information</b></th></tr>\n";
            $fileList .= "</thead>\n";
            $fileList .= "<tbody>\n";
            
            if(!empty($this->errors['VF'])){
                foreach($this->errors['VF'] as $f){
                    $fileList .= "<tr>\n";
                    $fileList .= "<td>ERROR during the Virus and File Extension checking </td>\n";
                    $fileList .= "<td>{$f}</td>\n";
                    $fileList .= "</tr>\n";
                }
            }

            if(!empty($this->errors['tmpDIR'])){
                foreach($this->errors['tmpDIR'] as $f){
                    $fileList .= "<tr>\n";
                    $fileList .= "<td>ERROR TMP DIR writing error </td>\n";
                    $fileList .= "<td>{$f}</td>\n";
                    $fileList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['GENFILE'])){
                foreach($this->errors['GENFILE'] as $f){
                    $fileList .= "<tr>\n";
                    $fileList .= "<td>ERROR during the FILE LIST Generating</td>\n";
                    $fileList .= "<td>{$f}</td>\n";
                    $fileList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['ZipFileError'])){
                foreach($this->errors['ZipFileError'] as $f){
                    $fileList .= "<tr>\n";
                    $fileList .= "<td>ERROR Password protected zip file(s): </td>\n";
                    $fileList .= "<td>{$f}</td>\n";
                    $fileList .= "</tr>\n";
                }
            }

            //wrong MIME error MSG
            if(!empty($this->errors['MIME'])){
                foreach($this->errors['MIME'] as $value){
                    $fileList .= "<tr>\n";
                    $fileList .= "<td>ERROR WRONG MIME TYPES </td>\n";
                    $fileList .= "<td>FileName: {$value['filename']}<br> Extension: {$value['extension']} <br> MIME type: {$value['type']} </td>\n";
                    $fileList .= "</tr>\n";
                }
            }
            
            if(!empty($this->errors['DUPLICATES'])){
                foreach($this->errors['DUPLICATES'] as $f){
                    $fileList .= "<tr>\n";
                    $fileList .= "<td>ERROR!!! File Duplication:</td>\n";
                    $fileList .= "<td>{$f}</td>\n";
                    $fileList .= "</tr>\n";
                }
            }

            if(!empty($this->errors['WRONGFILES'])){
                foreach($this->errors['WRONGFILES'] as $f){
                    $fileList .= "<tr>\n";
                    $fileList .= "<td>ERROR!!!! Not valid file name(s):</td>\n";
                    $fileList .= "<td>{$f}</td>\n";
                    $fileList .= "</tr>\n";
                }
            }
            
            $fileList .= "</tbody>\n";
            $fileList .= "</table>\n\n";
            $fileList .= "</div>\n\n";
            $fileList .= "</div>\n\n";
        
        }
        
        $str=file_get_contents('template/template.html');
        $str=str_replace("{html_file_content}", $fileList,$str);
        //create the file list html
        file_put_contents(date('Y_m_d_H_i_s').'.html', $str.PHP_EOL , FILE_APPEND | LOCK_EX);

        return true;
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

        // open pointer to directory and read list of files
        $d = @dir($dir) or die("getFileList: Failed opening directory $dir for reading");

        while(false !== ($entry = $d->read())) {
            // skip hidden files
            if($entry[0] == ".") continue;

            if(is_dir("$dir$entry")) {
                $valid = true;
                $retval[] = array(
                    "name" => "$dir$entry/",
                    "type" => filetype("$dir$entry"),
                    "size" => 0,
                    "lastmod" => filemtime("$dir$entry"),
                    "valid_file" => $valid,
                    "filename" => $entry
                );

                if($recurse && is_readable("$dir$entry/")) {
                    if($depth === false) {
                            $retval = array_merge($retval, $this->getFileList("$dir$entry/", true));
                    } elseif($depth > 0) {
                            $retval = array_merge($retval, $this->getFileList("$dir$entry/", true, $depth-1));
                    }
                }
            } elseif(is_readable("$dir$entry")) {

                $valid = true;
                //check the file name
                if(preg_match('/[^A-Za-z0-9\_\-\.]/', $entry)){ $valid = false; }

                $extension = explode('.', $entry);
                $extension = end($extension);

                $retval[] = array(
                    "name" => "$dir$entry",
                    "type" => ($finfo) ? finfo_file($finfo, "$dir$entry") : mime_content_type("$dir$entry"),
                    "size" => filesize("$dir$entry"),
                    "lastmod" => filemtime("$dir$entry"),
                    "valid_file" => $valid,
                    "filename" => $entry,
                    "extension" => $extension
                );
            }
        }
        $d->close();
        return $retval;
    }
    
    
    private function getMIME(): array{
        return array(
            'hqx'	=>	array('application/mac-binhex40', 'application/mac-binhex', 'application/x-binhex40', 'application/x-mac-binhex40'),
            'cpt'	=>	'application/mac-compactpro',
            'csv'	=>	array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain'),
            'bin'	=>	array('application/macbinary', 'application/mac-binary', 'application/octet-stream', 'application/x-binary', 'application/x-macbinary'),
            'dms'	=>	'application/octet-stream',
            'lha'	=>	'application/octet-stream',
            'lzh'	=>	'application/octet-stream',
            'exe'	=>	array('application/octet-stream', 'application/x-msdownload'),
            'class'	=>	'application/octet-stream',
            'psd'	=>	array('application/x-photoshop', 'image/vnd.adobe.photoshop'),
            'so'	=>	'application/octet-stream',
            'sea'	=>	'application/octet-stream',
            'dll'	=>	'application/octet-stream',
            'oda'	=>	'application/oda',
            'pdf'	=>	array('application/pdf', 'application/force-download', 'application/x-download', 'binary/octet-stream'),
            'ai'	=>	array('application/pdf', 'application/postscript'),
            'eps'	=>	'application/postscript',
            'ps'	=>	'application/postscript',
            'smi'	=>	'application/smil',
            'smil'	=>	'application/smil',
            'mif'	=>	'application/vnd.mif',
            'xls'	=>	array('application/vnd.ms-excel', 'application/msexcel', 'application/x-msexcel', 'application/x-ms-excel', 'application/x-excel', 'application/x-dos_ms_excel', 'application/xls', 'application/x-xls', 'application/excel', 'application/download', 'application/vnd.ms-office', 'application/msword'),
            'ppt'	=>	array('application/powerpoint', 'application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/msword'),
            'pptx'	=> 	array('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/x-zip', 'application/zip'),
            'wbxml'	=>	'application/wbxml',
            'wmlc'	=>	'application/wmlc',
            'dcr'	=>	'application/x-director',
            'dir'	=>	'application/x-director',
            'dxr'	=>	'application/x-director',
            'dvi'	=>	'application/x-dvi',
            'gtar'	=>	'application/x-gtar',
            'gz'	=>	'application/x-gzip',
            'gzip'  =>	'application/x-gzip',
            'php'	=>	array('application/x-httpd-php', 'application/php', 'application/x-php', 'text/php', 'text/x-php', 'application/x-httpd-php-source'),
            'php4'	=>	'application/x-httpd-php',
            'php3'	=>	'application/x-httpd-php',
            'phtml'	=>	'application/x-httpd-php',
            'phps'	=>	'application/x-httpd-php-source',
            'js'	=>	array('application/x-javascript', 'text/plain'),
            'swf'	=>	'application/x-shockwave-flash',
            'sit'	=>	'application/x-stuffit',
            'tar'	=>	'application/x-tar',
            'tgz'	=>	array('application/x-tar', 'application/x-gzip-compressed'),
            'z'	=>	'application/x-compress',
            'xhtml'	=>	'application/xhtml+xml',
            'xht'	=>	'application/xhtml+xml',
            'zip'	=>	array('application/x-zip', 'application/zip', 'application/x-zip-compressed', 'application/s-compressed', 'multipart/x-zip'),
            'rar'	=>	array('application/x-rar', 'application/rar', 'application/x-rar-compressed'),
            'mid'	=>	'audio/midi',
            'midi'	=>	'audio/midi',
            'mpga'	=>	'audio/mpeg',
            'mp2'	=>	'audio/mpeg',
            'mp3'	=>	array('audio/mpeg', 'audio/mpg', 'audio/mpeg3', 'audio/mp3'),
            'aif'	=>	array('audio/x-aiff', 'audio/aiff'),
            'aiff'	=>	array('audio/x-aiff', 'audio/aiff'),
            'aifc'	=>	'audio/x-aiff',
            'ram'	=>	'audio/x-pn-realaudio',
            'rm'	=>	'audio/x-pn-realaudio',
            'rpm'	=>	'audio/x-pn-realaudio-plugin',
            'ra'	=>	'audio/x-realaudio',
            'rv'	=>	'video/vnd.rn-realvideo',
            'wav'	=>	array('audio/x-wav', 'audio/wave', 'audio/wav'),
            'bmp'	=>	array('image/bmp', 'image/x-bmp', 'image/x-bitmap', 'image/x-xbitmap', 'image/x-win-bitmap', 'image/x-windows-bmp', 'image/ms-bmp', 'image/x-ms-bmp', 'application/bmp', 'application/x-bmp', 'application/x-win-bitmap'),
            'gif'	=>	'image/gif',
            'jpeg'	=>	array('image/jpeg', 'image/pjpeg'),
            'jpg'	=>	array('image/jpeg', 'image/pjpeg'),
            'jpe'	=>	array('image/jpeg', 'image/pjpeg'),
            'jp2'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'j2k'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'jpf'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'jpg2'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'jpx'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'jpm'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'mj2'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'mjp2'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
            'png'	=>	array('image/png',  'image/x-png'),
            'tiff'	=>	'image/tiff',
            'tif'	=>	'image/tiff',
            'css'	=>	array('text/css', 'text/plain'),
            'html'	=>	array('text/html', 'text/plain'),
            'htm'	=>	array('text/html', 'text/plain'),
            'shtml'	=>	array('text/html', 'text/plain'),
            'txt'	=>	'text/plain',
            'text'	=>	'text/plain',
            'log'	=>	array('text/plain', 'text/x-log'),
            'rtx'	=>	'text/richtext',
            'rtf'	=>	array('application/rtf', 'text/rtf'),
            'xml'	=>	array('application/xml', 'text/xml', 'text/plain'),
            'xsl'	=>	array('application/xml', 'text/xsl', 'text/xml'),
            'mpeg'	=>	'video/mpeg',
            'mpg'	=>	'video/mpeg',
            'mpe'	=>	'video/mpeg',
            'qt'	=>	'video/quicktime',
            'mov'	=>	'video/quicktime',
            'avi'	=>	array('video/x-msvideo', 'video/msvideo', 'video/avi', 'application/x-troff-msvideo'),
            'movie'	=>	'video/x-sgi-movie',
            'doc'	=>	array('application/msword', 'application/vnd.ms-office'),
            'docx'	=>	array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/msword', 'application/x-zip'),
            'dot'	=>	array('application/msword', 'application/vnd.ms-office'),
            'dotx'	=>	array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/msword'),
            'xlsx'	=>	array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/vnd.ms-excel', 'application/msword', 'application/x-zip'),
            'word'	=>	array('application/msword', 'application/octet-stream'),
            'xl'	=>	'application/excel',
            'eml'	=>	'message/rfc822',
            'json'  =>	array('application/json', 'text/json'),
            'pem'   =>	array('application/x-x509-user-cert', 'application/x-pem-file', 'application/octet-stream'),
            'p10'   =>	array('application/x-pkcs10', 'application/pkcs10'),
            'p12'   =>	'application/x-pkcs12',
            'p7a'   =>	'application/x-pkcs7-signature',
            'p7c'   =>	array('application/pkcs7-mime', 'application/x-pkcs7-mime'),
            'p7m'   =>	array('application/pkcs7-mime', 'application/x-pkcs7-mime'),
            'p7r'   =>	'application/x-pkcs7-certreqresp',
            'p7s'   =>	'application/pkcs7-signature',
            'crt'   =>	array('application/x-x509-ca-cert', 'application/x-x509-user-cert', 'application/pkix-cert'),
            'crl'   =>	array('application/pkix-crl', 'application/pkcs-crl'),
            'der'   =>	'application/x-x509-ca-cert',
            'kdb'   =>	'application/octet-stream',
            'pgp'   =>	'application/pgp',
            'gpg'   =>	'application/gpg-keys',
            'sst'   =>	'application/octet-stream',
            'csr'   =>	'application/octet-stream',
            'rsa'   =>	'application/x-pkcs7',
            'cer'   =>	array('application/pkix-cert', 'application/x-x509-ca-cert'),
            '3g2'   =>	'video/3gpp2',
            '3gp'   =>	array('video/3gp', 'video/3gpp'),
            'mp4'   =>	'video/mp4',
            'm4a'   =>	'audio/x-m4a',
            'f4v'   =>	array('video/mp4', 'video/x-f4v'),
            'flv'	=>	'video/x-flv',
            'webm'	=>	'video/webm',
            'aac'   =>	'audio/x-acc',
            'm4u'   =>	'application/vnd.mpegurl',
            'm3u'   =>	'text/plain',
            'xspf'  =>	'application/xspf+xml',
            'vlc'   =>	'application/videolan',
            'wmv'   =>	array('video/x-ms-wmv', 'video/x-ms-asf'),
            'au'    =>	'audio/x-au',
            'ac3'   =>	'audio/ac3',
            'flac'  =>	'audio/x-flac',
            'ogg'   =>	array('audio/ogg', 'video/ogg', 'application/ogg'),
            'kmz'	=>	array('application/vnd.google-earth.kmz', 'application/zip', 'application/x-zip'),
            'kml'	=>	array('application/vnd.google-earth.kml+xml', 'application/xml', 'text/xml'),
            'ics'	=>	'text/calendar',
            'ical'	=>	'text/calendar',
            'zsh'	=>	'text/x-scriptzsh',
            '7zip'	=>	array('application/x-compressed', 'application/x-zip-compressed', 'application/zip', 'multipart/x-zip'),
            'cdr'	=>	array('application/cdr', 'application/coreldraw', 'application/x-cdr', 'application/x-coreldraw', 'image/cdr', 'image/x-cdr', 'zz-application/zz-winassoc-cdr'),
            'wma'	=>	array('audio/x-ms-wma', 'video/x-ms-asf'),
            'jar'	=>	array('application/java-archive', 'application/x-java-application', 'application/x-jar', 'application/x-compressed'),
            'svg'	=>	array('image/svg+xml', 'application/xml', 'text/xml'),
            'vcf'	=>	'text/x-vcard',
            'srt'	=>	array('text/srt', 'text/plain'),
            'vtt'	=>	array('text/vtt', 'text/plain'),
            'ico'	=>	array('image/x-icon', 'image/x-ico', 'image/vnd.microsoft.icon'),
            'odc'	=>	'application/vnd.oasis.opendocument.chart',
            'otc'	=>	'application/vnd.oasis.opendocument.chart-template',
            'odf'	=>	'application/vnd.oasis.opendocument.formula',
            'otf'	=>	'application/vnd.oasis.opendocument.formula-template',
            'odg'	=>	'application/vnd.oasis.opendocument.graphics',
            'otg'	=>	'application/vnd.oasis.opendocument.graphics-template',
            'odi'	=>	'application/vnd.oasis.opendocument.image',
            'oti'	=>	'application/vnd.oasis.opendocument.image-template',
            'odp'	=>	'application/vnd.oasis.opendocument.presentation',
            'otp'	=>	'application/vnd.oasis.opendocument.presentation-template',
            'ods'	=>	'application/vnd.oasis.opendocument.spreadsheet',
            'ots'	=>	'application/vnd.oasis.opendocument.spreadsheet-template',
            'odt'	=>	'application/vnd.oasis.opendocument.text',
            'odm'	=>	'application/vnd.oasis.opendocument.text-master',
            'ott'	=>	'application/vnd.oasis.opendocument.text-template',
            'oth'	=>	'application/vnd.oasis.opendocument.text-web',
            'ole'	=>	array('application/zip', 'application/ole')
        );
        
    }
    
   
    

}