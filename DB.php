<?php
namespace PHlite;

require_once "Config.php";

use PDO;
use PDOStatement;

class DB {
    protected static $pdo = NULL;

    public static function get() : PDO {
        self::connect();
        return self::$pdo;
    }

    public static function prepare(string $sql) : PDOStatement {
        return self::get()->prepare($sql);
    }

    public static function exec(string $sql) : int {
        return self::get()->exec($sql);
    }

    public static function execFile(string $f) : int {
        $sql = file_get_contents($f, FILE_USE_INCLUDE_PATH);
        return self::exec($sql);
    }

    protected static function connect() : void {
        if(self::$pdo !== NULL)
            return;
        self::$pdo = new PDO('sqlite:'.Config::get('database', 'path'));
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
    }
}

?>
