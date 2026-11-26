<?php
class ProjectRole
{
    public function __construct(private int $id, private string $name) {}

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function canManageTasks(): bool { return $this->name === 'manager'; }

    public static function findByName(string $name): ?ProjectRole
    {
        $st = Database::get()->prepare('SELECT * FROM project_role WHERE name = ?');
        $st->execute([$name]);
        $r = $st->fetch();
        return $r ? new ProjectRole($r['id'], $r['name']) : null;
    }
}