<?php
namespace PHlite;

use PDO;

require_once 'Config.php';
require_once 'DB.php';
require_once 'User.php';
require_once 'Base64.php';
require_once 'Cookie.php';

class Session {
    protected $id     = NULL;
    protected $userID = NULL;
    protected $key    = NULL;
    protected $IP     = NULL;
    protected $active = NULL;

    public function __construct(int $id) {
        $sql = 'SELECT * FROM users_sessions WHERE id = :i';
        $q = DB::get()->prepare($sql);
        $q->bindValue(':i', $id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('id',     $this->id,     PDO::PARAM_INT);
        $q->bindColumn('userID', $this->userID, PDO::PARAM_INT);
        $q->bindColumn('key',    $this->key,    PDO::PARAM_STR);
        $q->bindColumn('IP',     $this->IP,     PDO::PARAM_STR);
        $q->bindColumn('active', $this->active, PDO::PARAM_INT);
        $q->fetch(PDO::FETCH_BOUND);
        //TODO: throw on failure
    }

    public function getID() : int {
        return $this->id;
    }

    public function getUser() : User {
        return new User($this->userID);
    }

    public function check(?string $k = NULL) : bool {
        if($k === NULL)
            $k = $_COOKIE['sessionKey'];
        if($_SERVER['REMOTE_ADDR'] != $this->IP)
            return false;
        if(!password_verify($k, $this->key))
            return false;
        $this->touch();
        return true;
    }

    public function touch() : void {
        $sql = 'UPDATE users_sessions SET active = :t WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':t', $_SERVER['REQUEST_TIME'], PDO::PARAM_INT);
        $q->bindValue(':i', $this->id,                PDO::PARAM_INT);
        $q->execute();
        $this->active = $_SERVER['REQUEST_TIME'];
    }

    public function end() : void {
        $sql = 'DELETE FROM users_sessions WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        self::clearCookie('sessionID');
        self::clearCookie('sessionKey');
    }

    public static function getCurrent() : ?self {
        if(!isset($_COOKIE['sessionID']))
            return NULL;
        $s = new self($_COOKIE['sessionID']);
        if(!$s->check())
            return NULL;
        return $s;
    }

    public static function start(User $u) : self {
        self::free($u);
        $key = self::generateKey();
        $sql = 'INSERT INTO users_sessions(userID, key, IP, active) VALUES(:u, :k, :i, :a)';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u->getID(),              PDO::PARAM_INT);
        $q->bindValue(':k', self::hashKey($key),      PDO::PARAM_STR);
        $q->bindValue(':i', $_SERVER['REMOTE_ADDR'],  PDO::PARAM_STR);
        $q->bindValue(':a', $_SERVER['REQUEST_TIME'], PDO::PARAM_INT);
        $q->execute();
        $s = new self(DB::get()->lastInsertId());
        self::sendCookie('sessionID',  $s->getID());
        self::sendCookie('sessionKey', $key);
        return $s;
    }

    protected static function free(User $u) : void {
        //Return early if max not reached
        $sql = 'SELECT COUNT(*) FROM users_sessions WHERE userID = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $u->getID(), PDO::PARAM_INT);
        $q->execute();
        if($q->fetchColumn() >= Config::get('session', 'max'))
            return;
        //Pull ID of oldest session
        $sql = 'SELECT id FROM users_sessions WHERE userID = :i ORDER BY active ASC LIMIT 1';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $u->getID(), PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('id', $i, PDO::PARAM_INT);
        $q->fetch(PDO::FETCH_BOUND);
        //Remove oldest session from the DB
        $sql = 'DELETE FROM users_sessions WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
    }

    protected static function generateKey() : string {
        $k = random_bytes(Config::get('session', 'key_bytes'));
        return Base64::encode($k);
    }

    protected static function hashKey(string $k) : string {
        $opt = [
            'cost' => Config::get('session', 'key_hash_cost'),
        ];
        return password_hash($k, PASSWORD_BCRYPT, $opt);
    }

    protected static function sendCookie(string $k, ?string $v, int $d = 0) : void {
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

    protected static function clearCookie(string $k) : void {
        self::sendCookie($k, NULL, -1);
    }
}

?>
