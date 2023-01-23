<?php

namespace acdhOeaw\arche\fileChecker;

use RuntimeException;
use acdhOeaw\arche\fileChecker\Misc as MC;

class CheckFunctions {

    /**
     * 
     * @var array<string>
     */
    private array $blackList = [];

    /**
     * 
     * @var array<string>
     */
    private array $mimeTypes = [];
    private string $tmpDir;

    /**
     * 
     * @param array<string, mixed> $cfg
     */
    public function __construct(array $cfg) {
        //blacklist
        $bl              = $cfg['blackList'];
        $this->blackList = array_map(fn($x) => mb_strtolower(trim($x)), $bl);

        $files = scandir($cfg['signatureDir']);
        $files = array_filter($files, fn($x) => str_ends_with(mb_strtolower($x), '.xml'));
        if (count($files) === 0) {
            MC::die("\nThere is no file inside the signature directory!\n");
        }
        sort($files);
        $this->mimeTypes = MC::getMimeFromPronom($cfg['signatureDir'] . '/' . end($files));
        if (count($this->mimeTypes) === 0) {
            MC::die('MIME type generation failed!');
        }

        $this->tmpDir = $cfg['tmpDir'];
        MC::checkTmpDir($this->tmpDir, 'tmpDir');
    }

    /**
     * 
     * Checks the bagit file, and if there is an error then add it to the errors variable
     * 
     * @param string $filename
     * @return array<string, mixed>
     */
    public function checkBagitFile(string $filename): array {
        $result = [];
        // use an existing bag
        $bag    = new \BagIt($filename);
        $bag->validate();
        if (is_array($bag->getBagErrors())) {
            if (count($bag->getBagErrors()) > 0) {
                $result[$filename] = $bag->getBagErrors();
            }
        }
        return $result;
    }

    /**
     * 
     * Checks the Directory Name validation
     * 
     * @param string $dir
     * @return bool
     */
    public function checkDirectoryNameValidity(string $dir): bool {
        $dir = basename($dir);
        return preg_match("#^(?:[a-zA-Z]:|\.\.?)?(?:[\\\/][a-zA-Z0-9_.\'\"-]*)+$#", $dir) === 1;
    }

    /**
     * 
     * Checks the filename validation
     * 
     * @param string $filename
     * @return bool : true
     */
    public function checkFileNameValidity(string $filename): bool {
        return preg_match('/[^A-Za-z0-9\_\(\)\-\.]/', $filename) === 0;
    }

    /**
     * 
     * Check the blacklisted elements
     * 
     * @param string $extension
     */
    public function checkBlackListFile(string $extension): bool {
        return in_array(mb_strtolower($extension), $this->blackList);
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
    public function checkMimeTypes(string $extension, string $type): bool {
        return in_array(mb_strtolower($extension), $this->mimeTypes);
    }

    /**
     * 
     * Check the PDF version and try to open to be sure that is doesnt have any pwd
     * 
     * @param string $file
     * @return Error|null
     */
    public function checkPdfFile(string $file): ?Error {

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $parser->parseFile($file);
        } catch (\Exception $ex) {
            return new Error(dirname($file), basename($file), "PDF error", $ex->getMessage());
        }
        return null;
    }

    /**
     * 
     * Check the file and/or size duplications
     * 
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function checkFileDuplications(array $data): array {
        $result = [];
        foreach ($data as $current_key => $current_array) {
            foreach ($data as $search_key => $search_array) {
                if ($search_array['filename'] == $current_array['filename']) {
                    if ($search_key != $current_key) {
                        if ($current_array['size'] == $search_array['size']) {
                            $result["Duplicate_File_And_Size"][$current_array['filename']][] = $search_array['dir'];
                        } else {
                            $result["Duplicate_File"][$current_array['filename']][] = $search_array['dir'];
                        }
                    }
                }
            }
        }

        $return = [];
        if (isset($result['Duplicate_File_And_Size'])) {
            foreach ($result['Duplicate_File_And_Size'] as $k => $v) {
                $return['Duplicate_File_And_Size'][$k] = array_unique($v);
                $return['Duplicate_File_And_Size'][$k] = array_values($return['Duplicate_File_And_Size'][$k]);
            }
        }
        if (isset($result['Duplicate_File'])) {
            foreach ($result['Duplicate_File'] as $k => $v) {
                $return['Duplicate_File'][$k] = array_unique($v);
                $return['Duplicate_File'][$k] = array_values($return['Duplicate_File'][$k]);
            }
        }

        return $return;
    }

    /**
     * 
     * Checks the zip file, we extract them to know if it is pwd protected or not
     * 
     * If we have a pw protected one then we will put it to the $pwZips array
     * 
     */
    public function checkZipFile(string $zipFile): ?Error {
        $za = new \ZipArchive();
        if ($za->open($zipFile) !== TRUE) {
            return new Error(dirname($zipFile), basename($zipFile), "Zip_Open_Error");
        } 
        $filesCount = $za->count();
        for($i = 0; $i < $filesCount; $i++) {
            $res = $za->getStream($za->statIndex($i)['name']);
            if ($res === false) {
                return new Error(dirname($zipFile), basename($zipFile), "Zip_Error", $za->getStatusString());
            }
        }
        return null;
    }

    /**
     * 
     * 
     * Generate the data to for the directory tree view
     * based on the filetypelist.json
     * 
     * @param array<string, array<string, mixed>> $flat
     * @return array<array<string, mixed>>
     */
    public function convertDirectoriesToTree(array $flat): array {
        $indexed = [];
        $arrKeys = array_keys($flat);
        for ($x = 0; $x <= count($flat) - 1; $x++) {
            $indexed[$x]['text'] = $arrKeys[$x];

            if (isset($flat[$arrKeys[$x]]['extension'])) {

                if (count($flat[$arrKeys[$x]]['extension']) > 0) {
                    $i        = 0;
                    $children = [];
                    foreach ($flat[$arrKeys[$x]]['extension'] as $k => $v) {

                        $children[$i]['text'] = $k;
                        if (isset($v["sumSize"])) {
                            $children[$i]['children'][] = array("icon" => "jstree-file",
                                "text" => "SumSize: " . MC::formatSizeUnits($v["sumSize"]['sum']));
                        }
                        if (isset($v["fileCount"])) {
                            $children[$i]['children'][] = array("icon" => "jstree-file",
                                "text" => "fileCount: " . $v["fileCount"]['fileCount'] . " file(s)");
                        }
                        if (isset($v["minSize"])) {
                            $children[$i]['children'][] = array("icon" => "jstree-file",
                                "text" => "MinSize: " . MC::formatSizeUnits($v["minSize"]['min']));
                        }
                        if (isset($v["maxSize"])) {
                            $children[$i]['children'][] = array("icon" => "jstree-file",
                                "text" => "MaxSize: " . MC::formatSizeUnits($v["maxSize"]['max']));
                        }
                        $i++;
                    }

                    $indexed[$x]['children'] = $children;
                }
            }
            if (isset($flat[$arrKeys[$x]]['dirSumSize'])) {
                $indexed[$x]['children'][] = array("icon" => "jstree-file", "text" => "dirSumSize: " . MC::formatSizeUnits($flat[$arrKeys[$x]]['dirSumSize']['sumSize']));
            }
            if (isset($flat[$arrKeys[$x]]['dirSumFiles'])) {
                $indexed[$x]['children'][] = array("icon" => "jstree-file", "text" => "dirSumFiles: " . MC::formatSizeUnits($flat[$arrKeys[$x]]['dirSumFiles']['sumFileCount']));
            }
        }
        return $indexed;
    }

    /**
     * 
     * Generate the data to for the extension tree view
     * based on the filetypelist.json
     * 
     * @param array<string, array<string, int>> $flat
     * @return array<array<string, mixed>>
     */
    public function convertExtensionsToTree(array $flat): array {

        $indexed = [];
        foreach ($flat as $flatKey => $flatValue) {
            $entry = ['text' => $flatKey, 'children' => []];
            foreach ($flatValue as $k => $v) {
                $entry['children'][] = match ($k) {
                    "sumSize" => ["icon" => "jstree-file", "text" => "SumSize: " . MC::formatSizeUnits($v)],
                    "fileCount" => ["icon" => "jstree-file", "text" => "fileCount: " . $v . " file(s)"],
                    "min" => ["icon" => "jstree-file", "text" => "MinSize: " . MC::formatSizeUnits($v)],
                    "max" => ["icon" => "jstree-file", "text" => "MaxSize: " . MC::formatSizeUnits($v)],
                    default => throw new RuntimeException("Unknown key $k"),
                };
            }
            $indexed[] = $entry;
        }
        return $indexed;
    }
}
