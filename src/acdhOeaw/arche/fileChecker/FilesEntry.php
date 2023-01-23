<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace acdhOeaw\arche\fileChecker;

/**
 * Description of FilesEntry
 *
 * @author zozlak
 */
class FilesEntry {

    public function __construct(public string $dir, public string $filename,
                                public string $extension, public int $size) {
        
    }
}
