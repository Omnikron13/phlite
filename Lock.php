<?php
namespace Phlite;

use PDO;

require_once __DIR__.'/DB.php';
require_once __DIR__.'/Group.php';
require_once __DIR__.'/User.php';
require_once __DIR__.'/PhliteException.php';

class Lock {
    protected $id         = NULL;
    protected $name       = NULL;
    protected $desription = NULL;

    public function __construct(int $id) {
        $sql = 'SELECT * FROM locks WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('id',          $this->id,          PDO::PARAM_INT);
        $q->bindColumn('name',        $this->name,        PDO::PARAM_STR);
        $q->bindColumn('description', $this->description, PDO::PARAM_STR);
        $q->fetch(PDO::FETCH_BOUND);
        if($this->id === NULL)
            throw new LockException(LockException::CODE['LOCK_NOT_FOUND']);
    }

    public function getID() : int {
        return $this->id;
    }

    /********
     * Name *
     ********/
    public function getName() : string {
        return $this->name;
    }

    public function setName(string $n) : void {
        if(!self::validName($n))
            throw new LockException(LockException::CODE['NAME_INVALID']);
        if(!self::availableName($n))
            throw new LockException(LockException::CODE['NAME_UNAVAILABLE']);
        $sql = 'UPDATE locks SET name = :n WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->bindValue(':n', $n,        PDO::PARAM_STR);
        $q->execute();
        $this->name = $n;
    }

    public static function validName(string $n) : bool {
        $r = Config::get('lock', 'name_regex');
        return preg_match($r, $n);
    }

    public static function availableName(string $n) : bool {
        $sql = 'SELECT COUNT(*) FROM locks WHERE name = :n';
        $q = DB::prepare($sql);
        $q->bindValue(':n', $n, PDO::PARAM_STR);
        $q->execute();
        return $q->fetchColumn() == 0;
    }

    /***************
     * Description *
     ***************/
    public function getDescription() : string {
        return $this->description;
    }

    public function setDescription(string $d) : void {
        if(!self::validDescription($d))
            throw new LockException(LockException::CODE['DESCRIPTION_INVALID']);
        $sql = 'UPDATE locks SET description = :d WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->bindValue(':d', $d,        PDO::PARAM_STR);
        $q->execute();
        $this->description = $d;
    }

    public static function validDescription(string $d) : bool {
        $r = Config::get('lock', 'description_regex');
        return preg_match($r, $d);
    }

    /*******************
     * Lock management *
     *******************/
    public function remove() : void {
        $sql = 'DELETE FROM locks WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
    }

    public static function add(string $n, ?string $d = NULL) : self {
        if(!self::validName($n))
            throw new LockException(LockException::CODE['NAME_INVALID']);
        if(!self::availableName($n))
            throw new LockException(LockException::CODE['NAME_UNAVAILABLE']);
        if($d !== NULL && !self::validDescription($d))
            throw new LockException(LockException::CODE['DESCRIPTION_INVALID']);
        $sql = 'INSERT INTO locks(name, description) VALUES(:n, :d)';
        $q = DB::prepare($sql);
        $q->bindValue(':n', $n, PDO::PARAM_STR);
        $q->bindValue(':d', $d, PDO::PARAM_STR);
        $q->execute();
        return new self(DB::get()->lastInsertId());
    }

    public static function getAll() : array {
        $sql = 'SELECT id FROM locks';
        $q = DB::prepare($sql);
        $q->execute();
        $l = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        $l = array_map(
            function(int $i) {
                return new Lock($i);
            },
            $l
        );
        return $l;
    }

    public static function getByID(int $i) : ?self {
        try{
            $l = new self($i);
        }
        catch(LockException $e) {
            return NULL;
        }
        return $l;
    }

    /******************
     * Key management *
     ******************/
    public function grantGroupKey(Group $g) : void {
        if($this->checkGroupKey($g))
            return;
        $sql = 'INSERT INTO locks_group_keys(lockID, groupID) VALUES(:l, :g)';
        $q = DB::prepare($sql);
        $q->bindValue(':l', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':g', $g->getID(), PDO::PARAM_INT);
        $q->execute();
    }

    public function checkGroupKey(Group $g) : bool {
        $sql = 'SELECT COUNT(*) FROM locks_group_keys WHERE lockID = :l AND groupID = :g';
        $q = DB::prepare($sql);
        $q->bindValue(':l', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':g', $g->getID(), PDO::PARAM_INT);
        $q->execute();
        return $q->fetchColumn() > 0;
    }

    public function revokeGroupKey(Group $g) : void {
        $sql = 'DELETE FROM locks_group_keys WHERE lockID = :l AND groupID = :g';
        $q = DB::prepare($sql);
        $q->bindValue(':l', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':g', $g->getID(), PDO::PARAM_INT);
        $q->execute();
    }

    public function getGroups() : array {
        $sql = 'SELECT groupID FROM locks_group_keys WHERE lockID = :l';
        $q = DB::prepare($sql);
        $q->bindValue(':l', $this->id, PDO::PARAM_INT);
        $q->execute();
        return $q->fetchAll(PDO::FETCH_FUNC, ['Phlite\Group', 'getByID']);
    }

    public function grantUserKey(User $u) : void {
        if($this->checkUserKey($u, false))
            return;
        $sql = 'INSERT INTO locks_user_keys(lockID, userID) VALUES(:l, :u)';
        $q = DB::prepare($sql);
        $q->bindValue(':l', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
    }

    public function checkUserKey(User $u, bool $checkGroups = true) : bool {
        $sql = 'SELECT COUNT(*) FROM locks_user_keys WHERE lockID = :l AND userID = :u';
        $q = DB::prepare($sql);
        $q->bindValue(':l', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
        if($q->fetchColumn() > 0)
            return true;
        if(!$checkGroups)
            return false;
        foreach(Group::getUserGroups($u) as $g) {
            if($this->checkGroupKey($g))
                return true;
        }
        return false;
    }

    public function revokeUserKey(User $u) : void {
        $sql = 'DELETE FROM locks_user_keys WHERE lockID = :l AND userID = :u';
        $q = DB::prepare($sql);
        $q->bindValue(':l', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
    }

    //TODO: document
    public function getUsers(bool $includeGroups = false) : array {
        $sql = 'SELECT userID FROM locks_user_keys WHERE lockID = :l';
        $q = DB::prepare($sql);
        $q->bindValue(':l', $this->id, PDO::PARAM_INT);
        $q->execute();
        $users = $q->fetchAll(PDO::FETCH_FUNC, ['Phlite\User', 'getByID']);
        if(!$includeGroups)
            return $users;
        foreach($this->getGroups() as $g) {
            $users = array_merge($users, $g->getMembers());
        }
        return array_unique($users);
    }

    /************
     * Database *
     ************/
    public static function setupDB() : void {
        Group::setupDB();
        DB::execFile('sql/locks.sql');
        DB::execFile('sql/locks_group_keys.sql');
        DB::execFile('sql/locks_user_keys.sql');
    }
}

class LockException extends PhliteException {
    public const CODE_PREFIX = 300;
    public const CODE = [
        'LOCK_NOT_FOUND'      => self::CODE_PREFIX + 1,
        'NAME_INVALID'        => self::CODE_PREFIX + 2,
        'NAME_UNAVAILABLE'    => self::CODE_PREFIX + 3,
        'DESCRIPTION_INVALID' => self::CODE_PREFIX + 4,
    ];
    protected const MESSAGE = [
        self::CODE['LOCK_NOT_FOUND']      => 'Lock not found',
        self::CODE['NAME_INVALID']        => 'Invalid lock name',
        self::CODE['NAME_UNAVAILABLE']    => 'Unavailable lock name',
        self::CODE['DESCRIPTION_INVALID'] => 'Invalid lock description',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>
