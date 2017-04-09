<?php
namespace PHlite;

use PDO;

require_once 'DB.php';
require_once 'Base64.php';

class User {
    //Flags
    public const GET_BY_ID       = 0;
    public const GET_BY_USERNAME = 1;

    public const LOGIN_ERROR = [
        'NO_USERNAME'        => 1,
        'NO_PASSWORD'        => 2,
        'NO_SUCH_USER'       => 3,
        'INCORRECT_PASSWORD' => 4,
        'USER_IN_COOLDOWN'   => 5,
        'FREQUENCY_EXCEEDED' => 6,
    ];

    protected $id           = NULL;
    protected $username     = NULL;
    protected $password     = NULL;
    protected $email        = NULL;
    protected $registerTime = NULL;
    protected $failureCount = NULL;
    protected $failureTime  = NULL;
    protected $requestToken = NULL;
    protected $sessions     = [];

    public function __construct($uid, int $mode = self::GET_BY_ID) {
        switch($mode) {
            case self::GET_BY_ID:
                $sql = 'SELECT * FROM users WHERE id = :i';
                $q = DB::get()->prepare($sql);
                $q->bindValue(':i', $uid, PDO::PARAM_INT);
                break;
            case self::GET_BY_USERNAME:
                $sql = 'SELECT * FROM users WHERE username = :u';
                $q = DB::get()->prepare($sql);
                $q->bindValue(':u', $uid, PDO::PARAM_STR);
                break;
            default:
                //TODO: throw
        }
        $q->execute();
        $q->bindColumn('id',           $this->id,           PDO::PARAM_INT);
        $q->bindColumn('username',     $this->username,     PDO::PARAM_STR);
        $q->bindColumn('password',     $this->password,     PDO::PARAM_STR);
        $q->bindColumn('email',        $this->email,        PDO::PARAM_STR);
        $q->bindColumn('registerTime', $this->registerTime, PDO::PARAM_INT);
        $q->bindColumn('failureCount', $this->failureCount, PDO::PARAM_INT);
        $q->bindColumn('failureTime',  $this->failureTime);
        $q->bindColumn('requestToken', $this->requestToken, PDO::PARAM_STR);
        $q->fetch(PDO::FETCH_BOUND);
        //TODO: throw better exception
        if($this->id === NULL)
            throw new \Exception('User not found');
        //Load sessions from DB
        $q = DB::get()->prepare('SELECT * FROM users_sessions WHERE userID = :i');
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $s)
            $this->sessions[$s['key']] = $s['IP'];
    }

    public function __toString() : string {
        return $this->username;
    }

    public function getID() : int {
        return $this->id;
    }
    public function getUsername() : string {
        return $this->username;
    }
    public function getEmail() : string {
        return $this->email;
    }
    public function getRegisterTime() : int {
        return $this->registerTime;
    }

    public function setUsername(string $u) : void {
        //TODO: validate
        $sql = 'UPDATE users SET username = :u WHERE id = :i';
        $q = DB::get()->prepare($sql);
        $q->bindValue(':u', $u, PDO::PARAM_STR);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
        $this->username = $u;
    }

    public function setEmail(string $e) : void {
        //TODO: validate
        $sql = 'UPDATE users SET email = :e WHERE id = :i';
        $q = DB::get()->prepare($sql);
        $q->bindValue(':e', $e, PDO::PARAM_STR);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
        $this->email = $e;
    }

    /*************
     * Passwords *
     *************/
    public function setPassword(string $p) : void {
        //TODO: validate
        $p = self::hashPassword($p);
        $sql = 'UPDATE users SET password = :p WHERE id = :i';
        $q = DB::get()->prepare($sql);
        $q->bindValue(':p', $p, PDO::PARAM_STR);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
        $this->password = $p;
    }

    public function checkPassword(string $p) : bool {
        return password_verify($p, $this->password);
    }

    protected static function hashPassword(string $p) : string {
        $options = [
            'cost' => Config::get('user', 'password_hash_cost'),
        ];
        return password_hash($p, PASSWORD_BCRYPT, $options);
    }

    /***************
     * Login logic *
     ***************/
    public static function login(string $username, string $password) : array {
        if($username == '') {
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['NO_USERNAME'],
            ];
        }
        if($password == '') {
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['NO_PASSWORD'],
            ];
        }
        try {
            $u = new self($username, self::GET_BY_USERNAME);
        }
        //TODO: more specific exception (see constructor)
        catch(\Exception $e) {
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['NO_SUCH_USER'],
            ];
        }
        //TODO: brute force protection logic
        if(!$u->checkPassword($password)) {
            //TODO: log failure
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['INCORRECT_PASSWORD'],
            ];
        }
        $s = $u->startSession();
        return [
            'success' => true,
            'user'    => $u,
            'session' => $s,
        ];
    }

    protected function loginFailure(float $t) : void {
        $this->failureTime = $t;
        $this->failureCount += 1;
        $q = DB::get()->prepare('UPDATE users SET failureTime = :t, failureCount = :c WHERE id = :i');
        $q->bindValue(':t', $t);
        $q->bindValue(':c', $this->failureCount, PDO::PARAM_INT);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
    }

    /******************
     * Sessions logic *
     ******************/
    public function startSession() : string {
        $this->freeSession();
        $key  = self::generateSessionKey();
        $hash = self::hashSessionKey($key);
        $ip   = $_SERVER['REMOTE_ADDR'];
        $q = DB::get()->prepare('INSERT INTO users_sessions(userID, key, IP, active) VALUES(:u, :k, :i, :a)');
        $q->bindValue(':u', $this->id, PDO::PARAM_INT);
        $q->bindValue(':k', $hash,     PDO::PARAM_STR);
        $q->bindValue(':i', $ip,       PDO::PARAM_STR);
        $q->bindValue(':a', time(),    PDO::PARAM_INT);
        $q->execute();
        $this->sessions[$hash] = $ip;
        $this->sendCookies($key);
        return $key;
    }

    public function checkSession(string $k) : bool {
        foreach($this->sessions as $hash => $ip) {
            if(!password_verify($k, $hash))
                continue;
            if($ip = $_SERVER['REMOTE_ADDR'])
                return true;
        }
        return false;
    }

    public function endSession(string $k) : void {
        foreach($this->sessions as $hash => $ip) {
            if(!password_verify($k, $hash))
                continue;
            $q = DB::get()->prepare('DELETE FROM users_sessions WHERE key = :k AND IP = :ip');
            $q->bindValue(':k',  $hash, PDO::PARAM_STR);
            $q->bindValue(':ip', $ip,   PDO::PARAM_STR);
            $q->execute();
            unset($this->sessions[$hash]);
            //TODO: clear cookies
            $this->clearCookies();
            return;
        }
    }

    //Remove oldest session if max_sessions reached
    protected function freeSession() : void {
        //Return early if max_sessions not reached
        if(count($this->sessions) < Config::get('user', 'max_sessions'))
            return;
        //Pull UID and key of oldest session
        $q = DB::get()->prepare('SELECT * FROM users_sessions WHERE userID = :i ORDER BY active ASC LIMIT 1');
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('id',  $id,  PDO::PARAM_INT);
        $q->bindColumn('key', $key, PDO::PARAM_STR);
        $q->fetch(PDO::FETCH_BOUND);
        //Remove oldest session from the DB
        $q = DB::get()->prepare('DELETE FROM users_sessions WHERE id = :i');
        $q->bindValue(':i', $id, PDO::PARAM_INT);
        $q->execute();
        //Clean now-deleted session data from the active User object
        unset($this->sessions[$key]);
    }

    protected function sendCookies(string $key, int $duration = 0) : void {
        if($duration > 0)
            $duration += time();
        //TODO: load config for cookie params
        setcookie(
            'userID',
            $this->id,
            $duration,
            NULL,       //path
            NULL,       //domain
            false,      //secure (https only)
            true        //HttpOnly - hidden from js
        );
        setcookie(
            'sessionKey',
            $key,
            $duration,
            NULL,       //path
            NULL,       //domain
            false,      //secure (https only)
            true        //HttpOnly - hidden from js
        );
        $_COOKIE['userID']     = $this->id;
        $_COOKIE['sessionKey'] = $key;
    }

    protected function clearCookies() : void {
        setcookie('userID',     NULL, -1);
        setcookie('sessionKey', NULL, -1);
        unset($_COOKIE['userID']);
        unset($_COOKIE['sessionKey']);
    }

    protected static function generateSessionKey() : string {
        $k = random_bytes(Config::get('user', 'session_key_bytes'));
        return Base64::encode($k);
    }

    protected static function hashSessionKey(string $k) : string {
        $opt = [
            'cost' => Config::get('user', 'session_key_hash_cost'),
        ];
        return password_hash($k, PASSWORD_BCRYPT, $opt);
    }

    /*******************
     * CSRF Protection *
     *******************/
    protected function setRequestToken(string $t) : void {
        $opt = [
            'cost' => Config::get('user', 'request_token_hash_cost'),
        ];
        $t = password_hash($t, PASSWORD_BCRYPT, $opt);
        $sql = 'UPDATE users SET requestToken = :t WHERE id = :i';
        $q = DB::get()->prepare($sql);
        $q->bindValue(':t', $t,        PDO::PARAM_STR);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $this->requestToken = $t;
    }

    public function generateRequestToken() : string {
        $t = random_bytes(Config::get('user', 'request_token_bytes'));
        $this->setRequestToken($t);
        return Base64::encode($t);
    }

    public function checkRequestToken(string $t) : bool {
        return password_verify($t, $this->requestToken);
    }

    /*
     */
    public static function add(string $username, string $password, string $email) : self {
        //TODO: verify
        $password = self::hashPassword($password);
        $sql = 'INSERT INTO users(username, password, email, registerTime) VALUES(:u, :p, :e, :t)';
        $query = DB::get()->prepare($sql);
        $query->bindValue(':u', $username, PDO::PARAM_STR);
        $query->bindValue(':p', $password, PDO::PARAM_STR);
        $query->bindValue(':e', $email,    PDO::PARAM_STR);
        $query->bindValue(':t', time(),    PDO::PARAM_INT);
        $query->execute();
        return new self($username, self::GET_BY_USERNAME);
    }

    public static function setupDB() : void {
        DB::execFile('sql/users.sql');
        DB::execFile('sql/users_verify.sql');
        DB::execFile('sql/users_sessions.sql');
    }
}

?>
