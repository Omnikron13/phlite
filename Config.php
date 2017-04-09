<?php
namespace PHlite;

class Config {
    protected const DEFAULT_FILE = 'phlite.cfg';

    protected static $data = NULL;

    public static function load(string $file = self::DEFAULT_FILE) : void {
        $s = file_get_contents($file, FILE_USE_INCLUDE_PATH);
        self::$data = parse_ini_string($s, true, INI_SCANNER_TYPED);
    }

    public static function get(string $section, string $param) {
        if(self::$data === NULL)
            self::load();
        return self::$data[$section][$param];
    }

    protected static function merge(array $a) : void {
        foreach($a as $section => $params) {
            foreach($params as $k => $v) {
                self::$data[$section][$k] = $v;
            }
        }
    }
}

?>
