<?php
namespace PHlite;

use PDO;

require_once 'DB.php';
require_once 'User.php';
require_once 'GroupException.php';

class Group {
    protected $id          = NULL;
    protected $name        = NULL;
    protected $description = NULL;

    public function __construct(int $id) {
        $sql = 'SELECT * FROM groups WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('id',          $this->id,          PDO::PARAM_INT);
        $q->bindColumn('name',        $this->name,        PDO::PARAM_STR);
        $q->bindColumn('description', $this->description, PDO::PARAM_STR);
        $q->fetch(PDO::FETCH_BOUND);
        if($this->id === NULL)
            throw new GroupException(GroupException::CODE['GROUP_NOT_FOUND']);
    }

    public function __toString() : string {
        return $this->name;
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
        //TODO: validate
        $sql = 'UPDATE groups SET name = :n WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->bindValue(':n', $n,        PDO::PARAM_STR);
        $q->execute();
        $this->name = $n;
    }

    public static function validName(string $n) : bool {
        $r = Config::get('group', 'name_regex');
        return preg_match($r, $n);
    }

    /***************
     * Description *
     ***************/
    public function getDescription() : string {
        return $this->description;
    }

    public function setDescription(string $d) : void {
        //TODO: validate
        $sql = 'UPDATE groups SET description = :d WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->bindValue(':d', $d,        PDO::PARAM_STR);
        $q->execute();
        $this->description = $d;
    }

    /*********************
     * Member management *
     *********************/
    public function addMember(User $u) : void {
        //TODO: note constraint violation with return code?
        if($this->containsMember($u))
            return;
        $sql = 'INSERT INTO groups_members(groupID, userID) VALUES(:g, :u)';
        $q = DB::prepare($sql);
        $q->bindValue(':g', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
    }

    public function getMembers() : array {
        $sql = 'SELECT userID FROM groups_members WHERE groupID = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $m = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        $m = array_map(
            function(int $i) {
                return new User($i);
            },
            $m
        );
        return $m;
    }

    public function containsMember(User $u) : bool {
        $sql = 'SELECT COUNT(*) FROM groups_members WHERE groupID = :g AND userID = :u';
        $q = DB::prepare($sql);
        $q->bindValue(':g', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
        return $q->fetchColumn() > 0;
    }

    public function removeMember(User $u) : void {
        $sql = 'DELETE FROM groups_members WHERE groupID = :g AND userID = :u';
        $q = DB::prepare($sql);
        $q->bindValue(':g', $this->id,   PDO::PARAM_INT);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
    }

    /********************
     * Group management *
     ********************/
    public function remove() : void {
        $sql = 'DELETE FROM groups WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
    }

    public static function add(string $n, ?string $d = NULL) : self {
        //TODO: validation
        $sql = 'INSERT INTO groups(name, description) VALUES(:n, :d)';
        $q = DB::prepare($sql);
        $q->bindValue(':n', $n, PDO::PARAM_STR);
        $q->bindValue(':d', $d, PDO::PARAM_STR);
        $q->execute();
        return new self(DB::get()->lastInsertId());
    }

    public static function getAll() : array {
        $sql = 'SELECT id FROM groups';
        $q = DB::prepare($sql);
        $q->execute();
        $g = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        $g = array_map(
            function(int $i) {
                return new Group($i);
            },
            $g
        );
        return $g;
    }

    public static function getUserGroups(User $u) : array {
        $sql = 'SELECT groupID FROM groups_members WHERE userID = :u';
        $q = DB::prepare($sql);
        $q->bindValue(':u', $u->getID(), PDO::PARAM_INT);
        $q->execute();
        $g = $q->fetchAll(PDO::FETCH_COLUMN, 0);
        $g = array_map(
            function(int $i) {
                return new Group($i);
            },
            $g
        );
        return $g;
    }

    /************
     * Database *
     ************/
    public static function setupDB() : void {
        User::setupDB();
        DB::execFile('sql/groups.sql');
        DB::execFile('sql/groups_members.sql');
    }
}

?>
