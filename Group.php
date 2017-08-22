<?php
namespace Phlite;

use PDO;

require_once __DIR__.'/DB.php';
require_once __DIR__.'/User.php';
require_once __DIR__.'/PhliteException.php';

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
        if(!self::validName($n))
            throw new GroupException(GroupException::CODE['NAME_INVALID']);
        if(!self::availableName($n))
            throw new GroupException(GroupException::CODE['NAME_UNAVAILABLE']);
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

    public static function availableName(string $n) : bool {
        $sql = 'SELECT COUNT(*) FROM groups WHERE name = :n';
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
            throw new GroupException(GroupException::CODE['DESCRIPTION_INVALID']);
        $sql = 'UPDATE groups SET description = :d WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->bindValue(':d', $d,        PDO::PARAM_STR);
        $q->execute();
        $this->description = $d;
    }

    public static function validDescription(string $d) : bool {
        $r = Config::get('group', 'description_regex');
        return preg_match($r, $d);
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
        return $q->fetchAll(PDO::FETCH_FUNC, ['Phlite\User', 'getByID']);
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
        if(!self::validName($n))
            throw new GroupException(GroupException::CODE['NAME_INVALID']);
        if(!self::availableName($n))
            throw new GroupException(GroupException::CODE['NAME_UNAVAILABLE']);
        if($d !== NULL && !self::validDescription($d))
            throw new GroupException(GroupException::CODE['DESCRIPTION_INVALID']);
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

    public static function getByID(int $i) : ?self {
        try{
            $g = new self($i);
        }
        catch(GroupException $e) {
            return NULL;
        }
        return $g;
    }

    //TODO: consider moving this to User->getGroups()
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

class GroupException extends PhliteException {
    public const CODE_PREFIX = 200;
    public const CODE = [
        'GROUP_NOT_FOUND'     => self::CODE_PREFIX + 1,
        'NAME_INVALID'        => self::CODE_PREFIX + 2,
        'NAME_UNAVAILABLE'    => self::CODE_PREFIX + 3,
        'DESCRIPTION_INVALID' => self::CODE_PREFIX + 4,
    ];
    protected const MESSAGE = [
        self::CODE['GROUP_NOT_FOUND']     => 'Group not found',
        self::CODE['NAME_INVALID']        => 'Invalid group name',
        self::CODE['NAME_UNAVAILABLE']    => 'Unavailable group name',
        self::CODE['DESCRIPTION_INVALID'] => 'Invalid group description',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>
