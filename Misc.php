<?php


namespace OEAW\Checks;


class Misc {
    
    
    
     /**
     * 
     * Create nice format from file sizes
     * 
     * @param type $bytes
     * @return string
     */
    public function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ' byte';
        }
        else
        {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
    
    /**
     * 
     * Clean the string
     * 
     * @param type $string
     * @return string
     */
    function clean($string): string {
        $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
        return preg_replace('[^A-Za-z0-9\-]', '', $string); // Removes special chars.
    }
    
    
    public function extensionWhiteList(){
        return array(
            "PDF","ODT","DOCX","DOC","RTF","SXW",
            "TXT","XML","SGML","HTML","DTD","XSD",
            "TIFF","DNG","PNG","JPEG","GIF","BMP",
            "PSD","CPT","JPEG2000","SVG","CGM","DXF","DWG",
            "PostScript","AI","DWF","CSV","TSV","ODS",
            "XLSX","SXC","XLS","SIARD","SQL","JSON","MDB",
            "FMP","DBF","BAK","ODB","MKV","MJ2","MP4",
            "MXF","MPEG","AVI","MOV","ASF/WMV","OGG",
            "FLV","FLAC","WAV","BWF","RF64","MBWF","AAC",
            "MP4","MP3","AIFF","WMA","X3D","COLLADA","OBJ",
            "PLY","VRML","U3D","STL","XHTML","MHTML","WARC","MAFF");
    }
    

    /**
     * Scan the directory and sort the files by date
     * 
     * @param type $dir
     * @return type
     */
    public function scan_dir_by_date($dir) {
        $ignored = array('.', '..', '.svn', '.htaccess');

        $files = array();    
        foreach (scandir($dir) as $file) {
            if (in_array($file, $ignored)) continue;
            $files[$file] = filemtime($dir . '/' . $file);
        }

        arsort($files);
        $files = array_keys($files);

        return $files;
    }


    /**
     * Process the pronom xml for the MIMEtypes
     * 
     * @param type $file
     * @return array
     */
    public function getMimeFromPronom($file): array {
        $reader = new \XMLReader();
        //check the file
        
        if(!$reader->open( $file )){
            die("Pronom Droid Signaturefile is not available!");
        }
        
        $doc = new \DOMDocument;
        $extArray = array();
        
        while( $reader->read() ) {
            if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name == 'FileFormat') {
                $node = simplexml_import_dom($doc->importNode($reader->expand(), true));
                
                if(isset($node->Extension) && count($node->Extension) > 1){
                    foreach($node->Extension as $nodes){
                        array_push($extArray, strtolower((string)$nodes));   
                    }
                }else if(isset($node->Extension)){
                    array_push($extArray, strtolower((string)$node->Extension));   
                }
            }
        }
        $extArray = array_unique($extArray);
        return $extArray;
    }
    
}

?>