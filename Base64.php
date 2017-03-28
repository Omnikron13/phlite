<?php
namespace PHlite;

class Base64 {
    public static function encode(string $s) : string {
        $s = base64_encode($s);
        $s = strtr($s, '+/', '-_');
        $s = rtrim($s, '=');
        return $s;
    }

    public static function decode(string $s) : string {
        $x = strlen($s);
        $s = str_pad($s, $x+($x%4), '=');
        $s = strtr($s, '-_', '+/');
        $s = base64_decode($s);
        return $s;
    }
}

?>
