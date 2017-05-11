<?php
namespace PHlite;

use PDO;

require_once 'DB.php';
require_once 'Group.php';
require_once 'User.php';

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
        //TODO: throw on failure
    }

    public function getID() : int {
        return $this->id;
    }
    public function getName() : string {
        return $this->name;
    }
    public function getDescription() : string {
        return $this->description;
    }

    /********
     * Name *
     ********/
    public function setName(string $n) : void {
        //TODO: validate
        $sql = 'UPDATE locks SET name = :n WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->bindValue(':n', $n,        PDO::PARAM_STR);
        $q->execute();
        $this->name = $n;
    }

    public function setDescription(string $d) : void {
        //TODO: validate
        $sql = 'UPDATE locks SET description = :d WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->bindValue(':d', $d,        PDO::PARAM_STR);
        $q->execute();
        $this->description = $d;
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

    public function grantUserKey(User $u) : void {
        if($this->checkUserKey($u))
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

?>
