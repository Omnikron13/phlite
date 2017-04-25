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

    /************
     * Database *
     ************/
    public static function setupDB() : void {
        Group::setupDB();
        DB::execFile('sql/locks.sql');
        DB::execFile('sql/locks_group_keys.sql');
    }
}

?>
