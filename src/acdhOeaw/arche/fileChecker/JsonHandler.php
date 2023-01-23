<?php

namespace acdhOeaw\arche\fileChecker;

use RuntimeException;

class JsonHandler {

    const FORMAT_JSON   = 'json';
    const FORMAT_NDJSON = 'ndjson';

    /**
     * 
     * @var array<int, string>
     */
    private static $messages = [
        JSON_ERROR_NONE           => 'No error has occurred',
        JSON_ERROR_DEPTH          => 'The maximum stack depth has been exceeded',
        JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
        JSON_ERROR_CTRL_CHAR      => 'Control character error, possibly incorrectly encoded',
        JSON_ERROR_SYNTAX         => 'Syntax error',
        JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    ];

    public static function encode(mixed $value, int $options = 0): string {
        return json_encode($value, $options) ?: throw new RuntimeException(self::$messages[json_last_error()]);
    }

    /**
     * 
     * @param string $json
     * @param bool $assoc
     * @return array<mixed>|object
     */
    public static function decode(string $json, bool $assoc = false): array | object {
        return json_decode($json, $assoc) ?: throw new RuntimeException(self::$messages[json_last_error()]);
    }

    public bool $noError = true;
    private string $outputDir;
    private string $format;

    public function __construct(string $outputDir,
                                string $format = self::FORMAT_JSON) {
        $this->outputDir = $outputDir;
        $this->format    = $format;
    }

    /**
     * 
     * Closes the Json by removing the content after the last object
     * (e.g. a "loose" coma) and adding an ending suffix depending on $this->format.
     * 
     */
    public function closeJsonFile(string $type): void {
        $file = $this->outputDir . '/' . $type . '.json';
        if (file_exists($file)) {
            $json     = fopen($file, 'a+');
            fseek($json, -100, SEEK_END);
            $ending   = fread($json, 100);
            $cutpoint = strrpos($ending, '}');
            if ($cutpoint) {
                ftruncate($json, ftell($json) - 99 + $cutpoint);
            }
            fwrite($json, $this->format == self::FORMAT_JSON ? "\n]}\n" : '');
            fclose($json);
        }
    }

    /**
     * 
     * @param DirectoriesEntry|FilesEntry|FileInfo|Error|array<mixed> $data
     * @param string $filename name of the JSON file to write to (excluding the
     *  .json extension). If not provided, a default value based on $data class is used.
     */
    public function writeDataToJsonFile(DirectoriesEntry | FilesEntry | FileInfo | Error | array $data,
                                        string $filename = ''): void {
        $this->noError = $this->noError && !($data instanceof Error);
        if (empty($filename)) {
            $filename = match (get_class($data)) {
                DirectoriesEntry::class => 'directoryList',
                FilesEntry::class => 'fileList',
                FileInfo::class => 'fileType',
                Error::class => 'error',
                default => throw new RuntimeException('Can\'t determine default output for $data of class ' . get_class($data))
            };
        }
        $outputPath = "$this->outputDir/$filename.json";
        $exists     = file_exists($outputPath);

        $json = fopen($outputPath, "a");
        if (!$exists && $this->format === self::FORMAT_JSON) {
            fwrite($json, '{ "data": [ ' . "\n");
        }
        fwrite($json, $this->generateJsonFileList($data));
        fwrite($json, $this->format === self::FORMAT_NDJSON ? "\n" : ",\n");
        fclose($json);
    }

    /**
     * 
     * Generate json data from the file list
     *
     */
    public function generateJsonFileList(mixed $data): string {
        $result = "";
        $result .= json_encode($data, JSON_UNESCAPED_SLASHES);
        $result = str_replace("\\/", "/", $result);
        $result = str_replace("\\", "/", $result);
        $result = str_replace("//", "/", $result);

        if ($this->format === self::FORMAT_NDJSON) {
            $index_directive = '{"index":{}}';
            $result          = $index_directive . "\n" . $result;
        }

        return stripslashes($result);
    }
}
