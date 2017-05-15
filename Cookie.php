<?php
namespace Phlite;

require_once 'Config.php';

class Cookie {
    public static function send(string $k, ?string $v, ?int $d = NULL) : void {
        if($d === NULL)
            $d = Config::get('cookie', 'default_ttl');
        if($d > 0)
            $d += $_SERVER['REQUEST_TIME'];
        setcookie(
            $k, //key
            $v, //value
            $d, //duration
            Config::get('cookie', 'path'),
            Config::get('cookie', 'domain'),
            Config::get('cookie', 'secure'),
            Config::get('cookie', 'http_only')
        );
        $_COOKIE[$k] = $v;
    }

    public static function clear(string $k) : void {
        self::send($k, NULL, -1);
    }
}

?>
