<?php
namespace Phlite;

class Config {
    protected const DEFAULT_FILE = 'defaults.cfg';

    protected static $data = NULL;

    public static function load(?string $file = NULL) : void {
        if(self::$data === NULL) {
            $s = file_get_contents(self::DEFAULT_FILE, FILE_USE_INCLUDE_PATH);
            self::$data = parse_ini_string($s, true, INI_SCANNER_TYPED);
        }
        if($file === NULL)
            return;
        $s = file_get_contents($file, FILE_USE_INCLUDE_PATH);
        $a = parse_ini_string($s, true, INI_SCANNER_TYPED);
        self::merge($a);
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
