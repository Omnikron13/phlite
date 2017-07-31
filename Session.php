<?php
namespace Phlite;

use PDO;

require_once 'Config.php';
require_once 'DB.php';
require_once 'User.php';
require_once 'Base64.php';
require_once 'Cookie.php';
require_once 'PhliteException.php';

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
        if($this->id === NULL)
            throw new SessionException(SessionException::CODE['SESSION_NOT_FOUND']);
    }

    public function getID() : int {
        return $this->id;
    }

    public function getUser() : User {
        return new User($this->userID);
    }

    public function check(string $k) : bool {
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
        Cookie::clear('session');
    }

    public static function getCurrent() : ?self {
        if(!isset($_COOKIE['session']))
            return NULL;
        $idkey = explode(':', $_COOKIE['session']);
        $s = self::getByID($idkey[0]);
        if($s === NULL)
            return NULL;
        if(!$s->check($idkey[1]))
            return NULL;
        return $s;
    }

    public static function getByID(int $i) : ?self {
        try{
            $s = new self($i);
        }
        catch(SessionException $e) {
            return NULL;
        }
        return $s;
    }

    public static function start(User $u) : string {
        self::free($u);
        $key = self::generateKey();
        $sql = 'INSERT INTO users_sessions(userID, key, IP, active) VALUES(:u, :k, :i, :a)';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u->getID(),              PDO::PARAM_INT);
        $q->bindValue(':k', self::hashKey($key),      PDO::PARAM_STR);
        $q->bindValue(':i', $_SERVER['REMOTE_ADDR'],  PDO::PARAM_STR);
        $q->bindValue(':a', $_SERVER['REQUEST_TIME'], PDO::PARAM_INT);
        $q->execute();
        $id = DB::get()->lastInsertId();
        Cookie::send('session', "$id:$key");
        return "$id:$key";
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
}

class SessionException extends PhliteException {
    public const CODE_PREFIX = 400;
    public const CODE = [
        'SESSION_NOT_FOUND' => self::CODE_PREFIX + 1,
    ];
    protected const MESSAGE = [
        self::CODE['SESSION_NOT_FOUND'] => 'Session not found',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>
