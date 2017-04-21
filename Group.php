<?php
namespace PHlite;

use PDO;

require_once 'DB.php';
require_once 'User.php';

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
        $sql = 'UPDATE groups SET name = :n WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->bindValue(':n', $n,        PDO::PARAM_STR);
        $q->execute();
        $this->name = $n;
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

    public static function setupDB() : void {
        DB::execFile('sql/groups.sql');
        DB::execFile('sql/groups_members.sql');
    }
}

?>
