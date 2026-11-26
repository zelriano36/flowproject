<?php
class Project
{
    public function __construct(
        private int $id,
        private string $name,
        private ?string $description,
        private int $ownerId,
        private string $status,
        private ?string $createdAt = null
    ) {}

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getOwnerId(): int { return $this->ownerId; }
    public function getStatus(): string { return $this->status; }

    private static function fromRow(array $r): Project
    {
        return new Project($r['id'], $r['name'], $r['description'], $r['owner_id'], $r['status'], $r['created_at']);
    }

    // Crée le projet et ajoute le créateur comme manager (transaction)
    public static function create(string $name, string $description, int $ownerId): Project
    {
        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('INSERT INTO project (name, description, owner_id) VALUES (?, ?, ?)');
            $st->execute([$name, $description, $ownerId]);
            $id = (int)$pdo->lastInsertId();

            $manager = ProjectRole::findByName('manager');
            $st = $pdo->prepare('INSERT INTO project_member (user_id, project_id, project_role_id) VALUES (?, ?, ?)');
            $st->execute([$ownerId, $id, $manager->getId()]);

            $pdo->commit();
            return self::findById($id);
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function findById(int $id): ?Project
    {
        $st = Database::get()->prepare('SELECT * FROM project WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ? self::fromRow($r) : null;
    }

    public static function findForUser(int $userId): array
    {
        $sql = "SELECT p.*, pr.name AS my_role,
                       (SELECT COUNT(*) FROM task t WHERE t.project_id = p.id) AS total,
                       (SELECT COUNT(*) FROM task t WHERE t.project_id = p.id AND t.status = 'terminee') AS done
                FROM project p
                JOIN project_member pm ON pm.project_id = p.id
                JOIN project_role pr ON pr.id = pm.project_role_id
                WHERE pm.user_id = ?
                ORDER BY p.created_at DESC";
        $st = Database::get()->prepare($sql);
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public function update(string $name, string $description, string $status): bool
    {
        $st = Database::get()->prepare('UPDATE project SET name = ?, description = ?, status = ? WHERE id = ?');
        return $st->execute([$name, $description, $status, $this->id]);
    }

    public function delete(): bool
    {
        $st = Database::get()->prepare('DELETE FROM project WHERE id = ?');
        return $st->execute([$this->id]);
    }

    public function isMember(int $userId): bool
    {
        return ProjectMember::find($userId, $this->id) !== null;
    }

    public function isManager(int $userId): bool
    {
        $m = ProjectMember::find($userId, $this->id);
        return $m !== null && $m['role'] === 'manager';
    }

    public function addMember(int $userId, string $roleName = 'membre'): bool
    {
        return ProjectMember::add($userId, $this->id, $roleName);
    }
    public function isOwner(int $userId): bool
    {
        return $userId === $this->ownerId;
    }
    public function removeMember(int $userId): bool
    {
        // Le créateur ne peut jamais être retiré
        if ($userId === $this->ownerId) {
            return false;
        }
        return ProjectMember::remove($userId, $this->id);
    }

    public function getMembers(): array { return ProjectMember::listByProject($this->id); }
    public function getTasks(): array { return Task::listByProject($this->id); }
}