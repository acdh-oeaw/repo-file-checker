<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace acdhOeaw\arche\fileChecker;

/**
 * Description of FileInfo
 *
 * @author zozlak
 */
class FileInfo {

    static public function factory(string $dir, string $filename, bool $valid): self {
        $fi            = new FileInfo();
        $fi->name      = $dir . '/' . $filename;
        $fi->directory = $dir;
        $fi->filename  = $filename;
        $fi->type      = filetype($fi->name);
        $fi->size      = filesize($fi->name);
        $fi->lastmod   = date("Y-m-d H:i:s", filemtime($fi->name));
        $fi->valid     = $valid;
        $fi->extension = strtolower(substr($filename, strrpos($filename, '.') + 1));
        return $fi;
    }

    public string $name;
    public string $directory;
    public string $type;
    public int $size;
    public string $lastmod;
    public bool $valid;
    public string $filename;
    public string $extension;

    public function asFilesEntry(): FilesEntry {
        return new FilesEntry($this->directory, $this->filename, $this->extension, $this->size);
    }

    public function asDirectoriesEntry(): DirectoriesEntry {
        return new DirectoriesEntry($this->directory, $this->valid, $this->lastmod);
    }

    public function getError(string $type, string $message = ''): Error {
        return new Error($this->directory, $this->filename, $type, $message);
    }
}
