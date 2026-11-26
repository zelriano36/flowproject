<?php
class Role
{
    public function __construct(private int $id, private string $name) {}

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }

    public static function findByName(string $name): ?Role
    {
        $st = Database::get()->prepare('SELECT * FROM role WHERE name = ?');
        $st->execute([$name]);
        $r = $st->fetch();
        return $r ? new Role($r['id'], $r['name']) : null;
    }
}