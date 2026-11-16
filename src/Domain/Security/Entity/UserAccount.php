<?php

declare(strict_types=1);

namespace App\Domain\Security\Entity;

final class UserAccount
{
    /**
     * @param list<string> $access
     * @param list<string> $cap
     */
    public function __construct(
        private readonly ?int $id,
        private readonly string $username,
        private readonly string $passwordHash,
        private readonly string $email,
        private readonly string $fullname,
        private readonly string $role,
        private readonly array $access,
        private readonly array $cap,
        private readonly string $avatar = '',
        private readonly string $status = '1',
    ) {
    }

    public static function fromLegacy(
        ?int $id,
        string $username,
        string $passwordHash,
        string $email,
        string $fullname,
        string $role,
        string|array $accessRaw,
        string|array $capRaw,
        string $avatar = '',
        string $status = '1',
    ): self {
        $access = is_array($accessRaw)
            ? array_values(array_filter(array_map('trim', $accessRaw)))
            : array_values(array_filter(array_map('trim', explode(',', $accessRaw))));

        $cap = is_array($capRaw)
            ? array_values(array_filter(array_map('trim', $capRaw)))
            : array_values(array_filter(array_map('trim', explode(',', $capRaw))));

        return new self(
            id: $id,
            username: $username,
            passwordHash: $passwordHash,
            email: $email,
            fullname: $fullname,
            role: $role,
            access: $access,
            cap: $cap,
            avatar: $avatar,
            status: $status,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFullname(): string
    {
        return $this->fullname;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return list<string>
     */
    public function getAccess(): array
    {
        return $this->access;
    }

    /**
     * @return list<string>
     */
    public function getCap(): array
    {
        return $this->cap;
    }

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === '1';
    }

    public function isAdmin(): bool
    {
        return strtolower($this->role) === 'admin';
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function canAccessModule(string $module): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (in_array('all', $this->access, true)) {
            return true;
        }

        return in_array($module, $this->access, true);
    }

    public function canAccessPoli(string $kdPoli): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (in_array('all', $this->cap, true)) {
            return true;
        }

        return in_array($kdPoli, $this->cap, true);
    }
}
