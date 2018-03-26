<?php


namespace OEAW\Checks;


class JsonHandler {
    
     protected static $_messages = array(
        JSON_ERROR_NONE => 'No error has occurred',
        JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
        JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
        JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
        JSON_ERROR_SYNTAX => 'Syntax error',
        JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    );

    public static function encode($value, $options = 0) {
        $result = json_encode($value, $options);

        if($result)  {
            return $result;
        }

        throw new RuntimeException(static::$_messages[json_last_error()]);
    }

    public static function decode($json, $assoc = false) {
        $result = json_decode($json, $assoc);

        if($result) {
            return $result;
        }

        throw new RuntimeException(static::$_messages[json_last_error()]);
    }
    
    
    public function createJsonObjFromFile(string $filepath): array  {
        $data = array();
        if(file_exists($filepath)){
            $file = file_get_contents($filepath);
            $data = $this->decode($file);
            if(count($data) > 0){
                return $data;
            }
        }
        return $data();
    }
    
     /**
     * 
     * Close the Json
     * 
     * @param string $reportDir
     * @param string $type
     * @return bool
     */
    public function closeJsonFiles(string $reportDir, string $type): bool{
        
        if(file_exists($reportDir.'/'.$type.'.json') === false){
             return false;
        }else {
            //open just the last line and do not load all file to the memory
            $line = "";
            $fp = fopen($reportDir.'/'.$type.'.json', 'r+');
            //-2 because of the new line
            $pos = -2; $line = ''; $c = '';
            do {
                $line = $c . $line;
                fseek($fp, $pos--, SEEK_END);
                $c = fgetc($fp);
            } while ($c != YOUR_EOL);
            $stat = fstat($fp);
            //remove the , sign from the end of the file
            ftruncate($fp, $stat['size']-2);
            fclose($fp);
            
            //add the  ] to the end of the file
            $fh = fopen($reportDir.'/'.$type.'.json', 'a');
            fwrite($fh, '] }');
            fclose($fh); 
            return true;
        }
    }
    
    
    /**
     * 
     * @param array $data
     * @param string $type "error", "fileList", "fileType"
     * @param string $dir 
     * @param string $jsonOutput json, ndjson
     */
    public function writeDataToJsonFile(array $data, string $type, string $dir, string $output = "json"){
        
        $jsonData = $this->generateJsonFileList($data, $output);
        
        if(file_exists($dir.'/'.$type.'.json') === false){
            
            $json = fopen($dir.'/'.$type.'.json', "a");
            if($output == "json"){
                fwrite($json, '{ "data": [ ');    
            }
            fclose($json);
        }
        $json = fopen($dir.'/'.$type.'.json', "a");
        $pieces = str_split($jsonData, 1024 * 4);
        foreach ($pieces as $piece) {
            fwrite($json, $piece, strlen($piece));
        }
        if($output == "ndjson"){
            fwrite($json, "\n");
        }else {
            fwrite($json, ",\n");
        }
            
        fclose($json);
    }
    
    
    
    /**
     * 
     *  Generate json data from the file list
     * 
     * @return string - json encoded array
     */
    public function generateJsonFileList(array $data, string $output = "json"): string{
        $result = "";
        $result = json_encode($data, JSON_UNESCAPED_SLASHES);
        $result = str_replace("\\/", "/", $result);
        $result = str_replace("\\", "/", $result);
        $result = str_replace("//", "/", $result);
        
        if($output == "ndjson"){
            $index_directive = "{\"index\":{}}" ;
            $result = $index_directive."\n".$result;
        }
        
        return stripslashes($result);
    }

   
}