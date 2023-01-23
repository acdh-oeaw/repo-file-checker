<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace acdhOeaw\arche\fileChecker;

/**
 * Description of DirectoriesEntry
 *
 * @author zozlak
 */
class DirectoriesEntry {

    public function __construct(public string $name, public bool $valid,
                                public string $modified) {
        
    }
}
