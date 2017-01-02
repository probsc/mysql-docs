<?php 

$autoloader = require __DIR__ . '/../src/composer_autoloader.php';

if (!$autoloader()) {
    die();
}

return new \NanairoWs\MysqlDocs;