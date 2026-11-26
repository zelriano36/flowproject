<?php
class User
{
    public function __construct(
        private int $id,
        private string $name,
        private string $email,
        private int $roleId,
        private ?string $createdAt = null,
        private int $avatarColor = 0
    ) {}

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getRoleId(): int { return $this->roleId; }
    public function getAvatarColor(): int { return $this->avatarColor; }

    public function isAdmin(): bool
    {
        $role = Role::findByName('admin');
        return $role !== null && $role->getId() === $this->roleId;
    }

    private static function fromRow(array $r): User
    {
        return new User($r['id'], $r['name'], $r['email'], $r['role_id'], $r['created_at'], (int)($r['avatar_color'] ?? 0));
    }

    public static function register(string $name, string $email, string $password): ?User
    {
        $pdo = Database::get();
        if (self::emailTaken($email, 0)) return null;

        $role = Role::findByName('user');
        if ($role === null) {
            throw new RuntimeException("Rôle 'user' introuvable : importez les données initiales.");
        }
        $color = random_int(0, 7);
        $st = $pdo->prepare('INSERT INTO user (name, email, password, role_id, avatar_color) VALUES (?, ?, ?, ?, ?)');
        $st->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role->getId(), $color]);
        return self::findById((int)$pdo->lastInsertId());
    }

    public static function login(string $email, string $password): ?User
    {
        $st = Database::get()->prepare('SELECT * FROM user WHERE email = ?');
        $st->execute([$email]);
        $r = $st->fetch();
        return ($r && password_verify($password, $r['password'])) ? self::fromRow($r) : null;
    }

    public static function findById(int $id): ?User
    {
        $st = Database::get()->prepare('SELECT * FROM user WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ? self::fromRow($r) : null;
    }

    public static function findByEmail(string $email): ?User
    {
        $st = Database::get()->prepare('SELECT * FROM user WHERE email = ?');
        $st->execute([$email]);
        $r = $st->fetch();
        return $r ? self::fromRow($r) : null;
    }

    public static function emailTaken(string $email, int $exceptId): bool
    {
        $st = Database::get()->prepare('SELECT 1 FROM user WHERE email = ? AND id <> ?');
        $st->execute([$email, $exceptId]);
        return (bool)$st->fetch();
    }

    public function updateProfile(string $name, string $email): bool
    {
        $st = Database::get()->prepare('UPDATE user SET name = ?, email = ? WHERE id = ?');
        return $st->execute([$name, $email, $this->id]);
    }

    public function setAvatarColor(int $color): bool
    {
        if ($color < 0 || $color > 7) return false;
        $st = Database::get()->prepare('UPDATE user SET avatar_color = ? WHERE id = ?');
        return $st->execute([$color, $this->id]);
    }

    public function checkPassword(string $password): bool
    {
        $st = Database::get()->prepare('SELECT password FROM user WHERE id = ?');
        $st->execute([$this->id]);
        $hash = $st->fetchColumn();
        return $hash && password_verify($password, $hash);
    }

    public function changePassword(string $new): bool
    {
        $st = Database::get()->prepare('UPDATE user SET password = ? WHERE id = ?');
        return $st->execute([password_hash($new, PASSWORD_DEFAULT), $this->id]);
    }

    // Supprime le compte. Les projets créés sont transférés à un autre membre
    // (gestionnaire en priorité) ou supprimés s'il n'y a personne d'autre.
    public function delete(): bool
    {
        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            $manager = ProjectRole::findByName('manager');
            $st = $pdo->prepare('SELECT id FROM project WHERE owner_id = ?');
            $st->execute([$this->id]);

            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                $q = $pdo->prepare(
                    "SELECT pm.user_id FROM project_member pm
                     JOIN project_role pr ON pr.id = pm.project_role_id
                     WHERE pm.project_id = ? AND pm.user_id <> ?
                     ORDER BY (pr.name = 'manager') DESC, pm.id LIMIT 1"
                );
                $q->execute([$pid, $this->id]);
                $newOwner = $q->fetchColumn();

                if ($newOwner) {
                    $pdo->prepare('UPDATE project SET owner_id = ? WHERE id = ?')->execute([$newOwner, $pid]);
                    $pdo->prepare('UPDATE project_member SET project_role_id = ? WHERE user_id = ? AND project_id = ?')
                        ->execute([$manager->getId(), $newOwner, $pid]);
                } else {
                    $pdo->prepare('DELETE FROM project WHERE id = ?')->execute([$pid]);
                }
            }

            $pdo->prepare('DELETE FROM user WHERE id = ?')->execute([$this->id]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}