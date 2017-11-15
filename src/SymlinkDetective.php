<?php

use SymlinkDetective\SymlinkDetective as BaseDetective;

// Bootstrap classes
if (!class_exists(BaseDetective::class)) {
    require_once __DIR__ . '/SymlinkDetective/SymlinkDetective.php';
}

class SymlinkDetective extends BaseDetective
{

}