<?php
namespace Phlite;

require_once __DIR__.'/User.php';
require_once __DIR__.'/Group.php';
require_once __DIR__.'/Lock.php';

function setupDB() : void {
    Lock::setupDB();
}

?>
