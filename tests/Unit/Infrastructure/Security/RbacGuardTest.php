<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Domain\Security\Exception\UnauthorizedException;
use App\Infrastructure\Security\RbacGuard;
use PHPUnit\Framework\TestCase;

class RbacGuardTest extends TestCase
{
    private RbacGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new RbacGuard();
    }

    public function testUnauthenticatedUserThrowsUnauthorizedException(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Autentikasi diperlukan untuk mengakses modul.');
        $this->guard->assertCanAccessModule(null, 'registration');
    }

    public function testDeactivatedUserThrowsUnauthorizedException(): void
    {
        $user = new UserAccount(
            id: 2,
            username: 'suspend',
            passwordHash: 'hash',
            email: 'suspend@medars.local',
            fullname: 'Suspended User',
            role: 'staff',
            access: ['all'],
            cap: ['all'],
            status: '0'
        );

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Akun pengguna telah dinonaktifkan.');
        $this->guard->assertCanAccessModule($user, 'registration');
    }

    public function testForbiddenModuleAccessThrowsForbiddenException(): void
    {
        $user = new UserAccount(
            id: 3,
            username: 'loket1',
            passwordHash: 'hash',
            email: 'loket@medars.local',
            fullname: 'Petugas Loket',
            role: 'staff',
            access: ['pendaftaran'],
            cap: [],
            status: '1'
        );

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Anda tidak memiliki izin untuk mengakses modul: kasir');
        $this->guard->assertCanAccessModule($user, 'kasir');
    }

    public function testAuthorizedModuleAccessPassesSilently(): void
    {
        $user = new UserAccount(
            id: 3,
            username: 'loket1',
            passwordHash: 'hash',
            email: 'loket@medars.local',
            fullname: 'Petugas Loket',
            role: 'staff',
            access: ['pendaftaran', 'pasien'],
            cap: [],
            status: '1'
        );

        $this->guard->assertCanAccessModule($user, 'pendaftaran');
        $this->guard->assertCanAccessModule($user, 'pasien');
        $this->assertTrue(true);
    }

    public function testPoliCapAccessValidation(): void
    {
        $doctor = new UserAccount(
            id: 4,
            username: 'dr_gigi',
            passwordHash: 'hash',
            email: 'dr.gigi@medars.local',
            fullname: 'drg. Maya',
            role: 'dokter',
            access: ['dokter_ralan'],
            cap: ['GIG01'],
            status: '1'
        );

        $this->guard->assertCanAccessPoli($doctor, 'GIG01');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Anda tidak memiliki izin melayani poliklinik: UMU01');
        $this->guard->assertCanAccessPoli($doctor, 'UMU01');
    }
}
