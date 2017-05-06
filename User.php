<?php
namespace PHlite;

use PDO;

require_once 'DB.php';
require_once 'Base64.php';
require_once 'Session.php';
require_once 'RequestToken.php';
require_once 'PhliteException.php';

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

    public function __construct($uid, int $mode = self::GET_BY_ID) {
        switch($mode) {
            case self::GET_BY_ID:
                $sql = 'SELECT * FROM users WHERE id = :i';
                $q = DB::prepare($sql);
                $q->bindValue(':i', $uid, PDO::PARAM_INT);
                break;
            case self::GET_BY_USERNAME:
                $sql = 'SELECT * FROM users WHERE username = :u';
                $q = DB::prepare($sql);
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
        $q->fetch(PDO::FETCH_BOUND);
        //TODO: throw better exception
        if($this->id === NULL)
            throw new PhliteException('User not found');
    }

    public function __toString() : string {
        return $this->username;
    }

    public function getID() : int {
        return $this->id;
    }
    public function getEmail() : string {
        return $this->email;
    }
    public function getRegisterTime() : int {
        return $this->registerTime;
    }

    public function setEmail(string $e) : void {
        //TODO: validate
        $sql = 'UPDATE users SET email = :e WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':e', $e, PDO::PARAM_STR);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
        $this->email = $e;
    }

    /*************
     * Usernames *
     *************/
    public function getUsername() : string {
        return $this->username;
    }

    public function setUsername(string $u) : void {
        //TODO: validate
        $sql = 'UPDATE users SET username = :u WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u, PDO::PARAM_STR);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
        $this->username = $u;
    }

    public static function validUsername(string $u) : bool {
        $r = Config::get('user', 'username_regex');
        return preg_match($r, $u);
    }

    public static function availableUsername(string $u) : bool {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :u';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u, PDO::PARAM_STR);
        $q->execute();
        return $q->fetchColumn() == 0;
    }

    /*************
     * Passwords *
     *************/
    public function setPassword(string $p) : void {
        //TODO: validate
        $p = self::hashPassword($p);
        $sql = 'UPDATE users SET password = :p WHERE id = :i';
        $q = DB::prepare($sql);
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
        catch(PhliteException $e) {
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['NO_SUCH_USER'],
            ];
        }

        //Check for unnaturally frequent login attempts
        $ft = $u->getLastLoginFailTime();
        if($_SERVER['REQUEST_TIME_FLOAT'] < $ft + Config::get('user', 'login_frequency_limit')) {
            $u->logLogin(false);
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['FREQUENCY_EXCEEDED'],
            ];
        }

        //Check if user is in brute-force cooldown
        $fc = $u->getLoginFailCount($ft - Config::get('user', 'login_failure_period'));
        if($fc >= Config::get('user', 'login_failure_limit')) {
            if($_SERVER['REQUEST_TIME_FLOAT'] < $ft + Config::get('user', 'login_failure_cooldown')) {
                $u->logLogin(false);
                return [
                    'success' => false,
                    'code'    => self::LOGIN_ERROR['USER_IN_COOLDOWN'],
                ];
            }
        }

        if(!$u->checkPassword($password)) {
            $u->logLogin(false);
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['INCORRECT_PASSWORD'],
            ];
        }
        $s = Session::start($u);
        $u->logLogin(true);
        return [
            'success' => true,
            'user'    => $u,
            'session' => $s,
        ];
    }

    public static function logout() : void {
        $s = Session::getCurrent();
        if($s === NULL)
            return;
        $s->end();
    }

    protected function logLogin(bool $success) : void {
        $sql = 'INSERT INTO users_logins(userID, success, time, IP) VALUES(:u, :s, :t, :i)';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $this->id, PDO::PARAM_INT);
        $q->bindValue(':s', $success,  PDO::PARAM_BOOL);
        $q->bindValue(':t', $_SERVER['REQUEST_TIME_FLOAT']);
        $q->bindValue(':i', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
        $q->execute();
    }

    protected function getLastLoginFailTime() : ?float {
        $sql = 'SELECT time FROM users_logins_fail_view WHERE userID = :u LIMIT 1';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $this->id, PDO::PARAM_INT);
        $q->execute();
        return $q->fetchColumn() ?: NULL;
    }

    protected function getLoginFailCount(float $t) : int {
        $sql = 'SELECT COUNT(*) FROM users_logins_fail_view WHERE userID = :u AND time > :t';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $this->id, PDO::PARAM_INT);
        $q->bindValue(':t', $t);
        $q->execute();
        return $q->fetchColumn();
    }

    /*******************
     * CSRF Protection *
     *******************/
    public function generateRequestToken() : string {
        return RequestToken::generate($this);
    }

    public function checkRequestToken(string $t) : bool {
        return RequestToken::check($t, $this);
    }

    /*******************
     * User management *
     *******************/
    public static function add(string $username, string $password, string $email) : self {
        //TODO: verify
        $password = self::hashPassword($password);
        $sql = 'INSERT INTO users(username, password, email, registerTime) VALUES(:u, :p, :e, :t)';
        $query = DB::prepare($sql);
        $query->bindValue(':u', $username, PDO::PARAM_STR);
        $query->bindValue(':p', $password, PDO::PARAM_STR);
        $query->bindValue(':e', $email,    PDO::PARAM_STR);
        $query->bindValue(':t', time(),    PDO::PARAM_INT);
        $query->execute();
        return new self($username, self::GET_BY_USERNAME);
    }

    public static function getCurrent() : ?self {
        $s = Session::getCurrent();
        if($s === NULL)
            return NULL;
        return $s->getUser();
    }

    public static function getAll() : array {
        $sql = 'SELECT id FROM users';
        $q = DB::prepare($sql);
        $q->execute();
        $u = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        $u = array_map(
            function(int $i) {
                return new User($i);
            },
            $u
        );
        return $u;
    }

    /************
     * Database *
     ************/
    public static function setupDB() : void {
        DB::execFile('sql/users.sql');
        DB::execFile('sql/users_verify.sql');
        DB::execFile('sql/users_sessions.sql');
        DB::execFile('sql/users_logins.sql');
        DB::execFile('sql/users_request_tokens.sql');
    }
}

?>
