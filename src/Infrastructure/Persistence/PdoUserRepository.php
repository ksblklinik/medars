<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Domain\Security\Repository\UserRepositoryInterface;
use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findByUsername(string $username): ?UserAccount
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mlite_users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findById(int $id): ?UserAccount
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mlite_users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM mlite_users ORDER BY id ASC");
        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(UserAccount $user): void
    {
        $accessString = implode(',', $user->getAccess());
        $capString = implode(',', $user->getCap());

        if ($user->getId() === null) {
            $sql = "
                INSERT INTO mlite_users (username, password, email, fullname, role, cap, access, avatar, status)
                VALUES (:username, :password, :email, :fullname, :role, :cap, :access, :avatar, :status)
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':username' => $user->getUsername(),
                ':password' => $user->getPasswordHash(),
                ':email' => $user->getEmail(),
                ':fullname' => $user->getFullname(),
                ':role' => $user->getRole(),
                ':cap' => $capString,
                ':access' => $accessString,
                ':avatar' => $user->getAvatar(),
                ':status' => $user->getStatus(),
            ]);
        } else {
            $sql = "
                UPDATE mlite_users
                SET username = :username,
                    password = :password,
                    email = :email,
                    fullname = :fullname,
                    role = :role,
                    cap = :cap,
                    access = :access,
                    avatar = :avatar,
                    status = :status
                WHERE id = :id
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $user->getId(),
                ':username' => $user->getUsername(),
                ':password' => $user->getPasswordHash(),
                ':email' => $user->getEmail(),
                ':fullname' => $user->getFullname(),
                ':role' => $user->getRole(),
                ':cap' => $capString,
                ':access' => $accessString,
                ':avatar' => $user->getAvatar(),
                ':status' => $user->getStatus(),
            ]);
        }
    }

    public function delete(int $id, int $currentUserId): void
    {
        if ($id === 1) {
            throw new ForbiddenException('Superadmin utama (ID: 1) dilindungi dan tidak dapat dihapus.');
        }

        if ($id === $currentUserId) {
            throw new ForbiddenException('Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
        }

        $stmt = $this->pdo->prepare("DELETE FROM mlite_users WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function recordLoginFailure(string $username, string $ipAddress): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO mlite_login_attempts (username, ip_address, attempt_time)
            VALUES (:username, :ip_address, :attempt_time)
        ");
        $stmt->execute([
            ':username' => $username,
            ':ip_address' => $ipAddress,
            ':attempt_time' => time(),
        ]);
    }

    public function recordLoginSuccess(int $userId, string $ipAddress): void
    {
        // Bersihkan attempts untuk IP ini
        $stmt = $this->pdo->prepare("DELETE FROM mlite_login_attempts WHERE ip_address = :ip_address");
        $stmt->execute([':ip_address' => $ipAddress]);
    }

    public function isRateLimited(string $username, string $ipAddress, int $maxAttempts = 5, int $decaySeconds = 300): bool
    {
        $cutoff = time() - $decaySeconds;

        // Bersihkan attempts lama yang telah kadaluarsa
        $cleanup = $this->pdo->prepare("DELETE FROM mlite_login_attempts WHERE attempt_time < :cutoff");
        $cleanup->execute([':cutoff' => $cutoff]);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM mlite_login_attempts
            WHERE (username = :username OR ip_address = :ip_address)
              AND attempt_time >= :cutoff
        ");
        $stmt->execute([
            ':username' => $username,
            ':ip_address' => $ipAddress,
            ':cutoff' => $cutoff,
        ]);

        $count = (int) $stmt->fetchColumn();
        return $count >= $maxAttempts;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): UserAccount
    {
        return UserAccount::fromLegacy(
            id: (int) $row['id'],
            username: (string) $row['username'],
            passwordHash: (string) $row['password'],
            email: (string) ($row['email'] ?? ''),
            fullname: (string) ($row['fullname'] ?? ''),
            role: (string) ($row['role'] ?? 'staff'),
            accessRaw: (string) ($row['access'] ?? ''),
            capRaw: (string) ($row['cap'] ?? ''),
            avatar: (string) ($row['avatar'] ?? ''),
            status: (string) ($row['status'] ?? '1')
        );
    }
}
