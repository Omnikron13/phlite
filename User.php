<?php
namespace Phlite;

use PDO;

require_once 'DB.php';
require_once 'Base64.php';
require_once 'Session.php';
require_once 'RequestToken.php';
require_once 'PhliteException.php';

class User {
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

    public function __construct(int $id) {
        $sql = 'SELECT * FROM users WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('id',           $this->id,           PDO::PARAM_INT);
        $q->bindColumn('username',     $this->username,     PDO::PARAM_STR);
        $q->bindColumn('password',     $this->password,     PDO::PARAM_STR);
        $q->bindColumn('email',        $this->email,        PDO::PARAM_STR);
        $q->bindColumn('registerTime', $this->registerTime, PDO::PARAM_INT);
        $q->fetch(PDO::FETCH_BOUND);
        if($this->id === NULL)
            throw new UserException(UserException::CODE['USER_NOT_FOUND']);
    }

    public function __toString() : string {
        return $this->username;
    }

    public function getID() : int {
        return $this->id;
    }
    public function getRegisterTime() : int {
        return $this->registerTime;
    }

    /*************
     * Usernames *
     *************/
    public function getUsername() : string {
        return $this->username;
    }

    public function setUsername(string $u) : void {
        if(!self::validUsername($u))
            throw new UserException(UserException::CODE['USERNAME_INVALID']);
        if(!self::availableUsername($u))
            throw new UserException(UserException::CODE['USERNAME_UNAVAILABLE']);
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

    /**********
     * Emails *
     **********/
    public function getEmail() : string {
        return $this->email;
    }

    public function setEmail(string $e) : void {
        if(!self::validEmail($e))
            throw new UserException(UserException::CODE['EMAIL_INVALID']);
        if(!self::availableEmail($e))
            throw new UserException(UserException::CODE['EMAIL_UNAVAILABLE']);
        $sql = 'UPDATE users SET email = :e WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':e', $e, PDO::PARAM_STR);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
        $this->email = $e;
    }

    public static function validEmail(string $e) : bool {
        $r = Config::get('user', 'email_regex');
        return preg_match($r, $e);
    }

    public static function availableEmail(string $e) : bool {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :e';
        $q = DB::prepare($sql);
        $q->bindValue(':e', $e, PDO::PARAM_STR);
        $q->execute();
        return $q->fetchColumn() == 0;
    }

    /*************
     * Passwords *
     *************/
    public function setPassword(string $p) : void {
        if(!self::validPassword($p))
            throw new UserException(UserException::CODE['PASSWORD_INVALID']);
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

    public static function validPassword(string $p) : bool {
        $r = Config::get('user', 'password_regex');
        return preg_match($r, $p);
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
        $u = self::getByUsername($username);
        if($u === NULL) {
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
        //TODO: return more useful data (e.g. unhashed session id:key)
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
    public function remove() : void {
        $sql = 'DELETE FROM users WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
    }

    public static function add(string $username, string $password, string $email) : self {
        if(!self::validUsername($username))
            throw new UserException(UserException::CODE['USERNAME_INVALID']);
        if(!self::availableUsername($username))
            throw new UserException(UserException::CODE['USERNAME_UNAVAILABLE']);
        if(!self::validEmail($email))
            throw new UserException(UserException::CODE['EMAIL_INVALID']);
        if(!self::availableEmail($email))
            throw new UserException(UserException::CODE['EMAIL_UNAVAILABLE']);
        if(!self::validPassword($password))
            throw new UserException(UserException::CODE['PASSWORD_INVALID']);
        $password = self::hashPassword($password);
        $sql = 'INSERT INTO users(username, password, email, registerTime) VALUES(:u, :p, :e, :t)';
        $query = DB::prepare($sql);
        $query->bindValue(':u', $username, PDO::PARAM_STR);
        $query->bindValue(':p', $password, PDO::PARAM_STR);
        $query->bindValue(':e', $email,    PDO::PARAM_STR);
        $query->bindValue(':t', time(),    PDO::PARAM_INT);
        $query->execute();
        return self::getByUsername($username);
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

    public static function getByID(int $i) : ?self {
        try{
            $u = new self($i);
        }
        catch(UserException $e) {
            return NULL;
        }
        return $u;
    }

    public static function getByUsername(string $u) : ?self {
        $sql = 'SELECT id FROM users WHERE username = :u';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u, PDO::PARAM_STR);
        $q->execute();
        $i = $q->fetchColumn();
        if($i === false)
            return NULL;
        return new self($i);
    }

    public static function getByEmail(string $e) : ?self {
        $sql = 'SELECT id FROM users WHERE email = :e';
        $q = DB::prepare($sql);
        $q->bindValue(':e', $e, PDO::PARAM_STR);
        $q->execute();
        $i = $q->fetchColumn();
        if($i === false)
            return NULL;
        return new self($i);
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

class UserException extends PhliteException {
    public const CODE_PREFIX = 100;
    public const CODE = [
        'USER_NOT_FOUND'       => self::CODE_PREFIX + 1,
        'USERNAME_INVALID'     => self::CODE_PREFIX + 2,
        'USERNAME_UNAVAILABLE' => self::CODE_PREFIX + 3,
        'EMAIL_INVALID'        => self::CODE_PREFIX + 4,
        'EMAIL_UNAVAILABLE'    => self::CODE_PREFIX + 5,
        'PASSWORD_INVALID'     => self::CODE_PREFIX + 6,
    ];
    protected const MESSAGE = [
        self::CODE['USER_NOT_FOUND']       => 'User not found',
        self::CODE['USERNAME_INVALID']     => 'Invalid username',
        self::CODE['USERNAME_UNAVAILABLE'] => 'Unavailable username',
        self::CODE['EMAIL_INVALID']        => 'Invalid email address',
        self::CODE['EMAIL_UNAVAILABLE']    => 'Unavailable email address',
        self::CODE['PASSWORD_INVALID']     => 'Invalid password',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>
