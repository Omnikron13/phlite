<?php
namespace PHlite;

use PDO;

require_once 'DB.php';
require_once 'Base64.php';
require_once 'User.php';

class RequestToken {
    protected $id     = NULL;
    protected $userID = NULL;
    protected $token  = NULL;
    protected $time   = NULL;

    public function __construct(int $id) {
        $sql = 'SELECT * FROM users_request_tokens WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('id',     $this->id,     PDO::PARAM_INT);
        $q->bindColumn('userID', $this->userID, PDO::PARAM_INT);
        $q->bindColumn('token',  $this->token,  PDO::PARAM_STR);
        $q->bindColumn('time',   $this->time,   PDO::PARAM_INT);
        $q->fetch(PDO::FETCH_BOUND);
        //TODO: throw on fail
    }

    public function remove() : void {
        $sql = 'DELETE FROM users_request_tokens WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
    }

    public static function check(string $t, User $u) : bool {
        $t = explode(':', $t);
        $rt = new self($t[0]);
        if($rt->id != $u->getID())
            return false;
        //TODO: check for time expiry
        if(!password_verify($t[1], $rt->token))
            return false;
        //TODO: remove db entry now that the token has been used?
        return true;
    }

    public static function generate(User $u) : string {
        self::free($u);
        $t = random_bytes(Config::get('request_token', 'bytes'));
        $t = Base64::encode($t);
        $hash = self::hash($t);
        $sql = 'INSERT INTO users_request_tokens(userID, token, time) VALUES(:u, :to, :ti)';
        $q = DB::prepare($sql);
        $q->bindValue(':u',  $u->getID(),              PDO::PARAM_INT);
        $q->bindValue(':to', $hash,                    PDO::PARAM_STR);
        $q->bindValue(':ti', $_SERVER['REQUEST_TIME'], PDO::PARAM_INT);
        $q->execute();
        $i = DB::get()->lastInsertId();
        return $i.':'.$t;
    }

    protected static function free(User $u) : void {
        $sql = 'SELECT COUNT(*) FROM users_request_tokens WHERE userID = :u';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
        $c = $q->fetchColumn();
        if($c < Config::get('request_token', 'max'))
            return;
        $sql = 'SELECT id FROM users_request_tokens WHERE userID = :u ORDER BY time ASC LIMIT 1';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
        $i = $q->fetchColumn();
        $sql = 'DELETE FROM users_request_tokens WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $i, PDO::PARAM_INT);
        $q->execute();
    }

    protected static function hash(string $t) : string {
        $opt = [
            'cost' => Config::get('request_token', 'hash_cost'),
        ];
        return password_hash($t, PASSWORD_BCRYPT, $opt);
    }
}

?>
