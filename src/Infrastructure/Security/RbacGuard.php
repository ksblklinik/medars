<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Domain\Security\Exception\UnauthorizedException;

final class RbacGuard
{
    public function assertCanAccessModule(?UserAccount $user, string $module): void
    {
        if ($user === null) {
            throw new UnauthorizedException('Autentikasi diperlukan untuk mengakses modul.');
        }

        if (!$user->isActive()) {
            throw new UnauthorizedException('Akun pengguna telah dinonaktifkan.');
        }

        if (!$user->canAccessModule($module)) {
            throw new ForbiddenException("Anda tidak memiliki izin untuk mengakses modul: {$module}");
        }
    }

    public function assertCanAccessPoli(?UserAccount $user, string $kdPoli): void
    {
        if ($user === null) {
            throw new UnauthorizedException('Autentikasi diperlukan untuk mengakses poliklinik.');
        }

        if (!$user->isActive()) {
            throw new UnauthorizedException('Akun pengguna telah dinonaktifkan.');
        }

        if (!$user->canAccessPoli($kdPoli)) {
            throw new ForbiddenException("Anda tidak memiliki izin melayani poliklinik: {$kdPoli}");
        }
    }
}
