<?php
namespace PHlite;

use PDO;

require_once 'DB.php';
require_once 'Base64.php';
require_once 'Session.php';

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
        $q->bindColumn('failureCount', $this->failureCount, PDO::PARAM_INT);
        $q->bindColumn('failureTime',  $this->failureTime);
        $q->bindColumn('requestToken', $this->requestToken, PDO::PARAM_STR);
        $q->fetch(PDO::FETCH_BOUND);
        //TODO: throw better exception
        if($this->id === NULL)
            throw new \Exception('User not found');
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
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u, PDO::PARAM_STR);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
        $this->username = $u;
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
        catch(\Exception $e) {
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['NO_SUCH_USER'],
            ];
        }
        $t = $_SERVER['REQUEST_TIME_FLOAT'];
        if($u->failureCount >= Config::get('user', 'login_failure_limit')) {
            if($t < $u->failureTime + Config::get('user', 'login_failure_cooldown')) {
                $u->loginFailure();
                return [
                    'success' => false,
                    'code'    => self::LOGIN_ERROR['USER_IN_COOLDOWN'],
                ];
            }
            $this->setFailureCount(0);
        }
        if($t < $u->failureTime + Config::get('user', 'login_frequency_limit')) {
            $u->loginFailure();
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['FREQUENCY_EXCEEDED'],
            ];
        }
        if(!$u->checkPassword($password)) {
            $u->loginFailure();
            return [
                'success' => false,
                'code'    => self::LOGIN_ERROR['INCORRECT_PASSWORD'],
            ];
        }
        $s = Session::start($u);
        $u->setFailureCount(0);
        return [
            'success' => true,
            'user'    => $u,
            'session' => $s,
        ];
    }

    protected function loginFailure() : void {
        $this->failureTime = $_SERVER['REQUEST_TIME_FLOAT'];
        $this->failureCount += 1;
        $q = DB::prepare('UPDATE users SET failureTime = :t, failureCount = :c WHERE id = :i');
        $q->bindValue(':t', $_SERVER['REQUEST_TIME_FLOAT']);
        $q->bindValue(':c', $this->failureCount, PDO::PARAM_INT);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        //TODO: log IP of attempt?
    }

    protected function setFailureCount(int $c) : void {
        $q = DB::prepare('UPDATE users SET failureCount = :c WHERE id = :i');
        $q->bindValue(':c', $c,        PDO::PARAM_INT);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $this->failureCount = $c;
    }

    /******************
     * Sessions logic *
     ******************/
    public function startSession() : Session {
        return Session::start($this);
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
        $q = DB::prepare($sql);
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
        $query = DB::prepare($sql);
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
