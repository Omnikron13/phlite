<?php
namespace PHlite;

require_once 'Config.php';

class Cookie {
    public static function send(string $k, ?string $v, int $d = 0) : void {
        if($d > 0)
            $d += $_SERVER['REQUEST_TIME'];
        setcookie(
            $k,
            $v,
            $d,     //duration
            NULL,   //path
            NULL,   //domain
            false,  //secure (https only)
            true    //HttpOnly - hidden from js
        );
        $_COOKIE[$k] = $v;
    }

    public static function clear(string $k) : void {
        self::send($k, NULL, -1);
    }
}

?>
