<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Security;

use App\Domain\Security\Entity\UserAccount;
use PHPUnit\Framework\TestCase;

class UserAccountTest extends TestCase
{
    public function testUserAccountInitializationAndProperties(): void
    {
        $hash = password_hash('Rahasia123!', PASSWORD_BCRYPT);
        $user = new UserAccount(
            id: 1,
            username: 'admin',
            passwordHash: $hash,
            email: 'admin@medars.local',
            fullname: 'Administrator Utama',
            role: 'admin',
            access: ['all'],
            cap: ['all'],
            avatar: 'admin.png',
            status: '1'
        );

        $this->assertSame(1, $user->getId());
        $this->assertSame('admin', $user->getUsername());
        $this->assertSame('admin@medars.local', $user->getEmail());
        $this->assertSame('Administrator Utama', $user->getFullname());
        $this->assertSame('admin', $user->getRole());
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isActive());
        $this->assertSame('admin.png', $user->getAvatar());
    }

    public function testVerifyPassword(): void
    {
        $hash = password_hash('KsmMedars2026', PASSWORD_BCRYPT);
        $user = new UserAccount(
            id: 2,
            username: 'dokter1',
            passwordHash: $hash,
            email: 'dr.budi@medars.local',
            fullname: 'dr. Budi Santoso, Sp.A',
            role: 'dokter',
            access: ['dokter_ralan', 'rekam_medis'],
            cap: ['U001'],
            status: '1'
        );

        $this->assertTrue($user->verifyPassword('KsmMedars2026'));
        $this->assertFalse($user->verifyPassword('WrongPassword'));
        $this->assertFalse($user->isAdmin());
    }

    public function testModuleAccessForAdminAndStaff(): void
    {
        $admin = new UserAccount(
            id: 1,
            username: 'superadmin',
            passwordHash: 'hash',
            email: 'super@medars.local',
            fullname: 'Super Admin',
            role: 'admin',
            access: ['users'], // Walaupun access hanya ada users, role admin berhak akses semua
            cap: ['all'],
            status: '1'
        );

        $this->assertTrue($admin->canAccessModule('kasir'));
        $this->assertTrue($admin->canAccessModule('settings'));
        $this->assertTrue($admin->canAccessModule('farmasi'));

        $staff = new UserAccount(
            id: 3,
            username: 'kasir1',
            passwordHash: 'hash',
            email: 'kasir@medars.local',
            fullname: 'Siti Kasir',
            role: 'kasir',
            access: ['kasir_rawat_jalan', 'kasir_rawat_inap'],
            cap: [],
            status: '1'
        );

        $this->assertTrue($staff->canAccessModule('kasir_rawat_jalan'));
        $this->assertTrue($staff->canAccessModule('kasir_rawat_inap'));
        $this->assertFalse($staff->canAccessModule('settings'));
        $this->assertFalse($staff->canAccessModule('dokter_ralan'));
    }

    public function testPoliklinikCapForDoctors(): void
    {
        $doctor = new UserAccount(
            id: 4,
            username: 'dr_anak',
            passwordHash: 'hash',
            email: 'dr.anak@medars.local',
            fullname: 'dr. Anak',
            role: 'dokter',
            access: ['dokter_ralan'],
            cap: ['POL01', 'POL02'],
            status: '1'
        );

        $this->assertTrue($doctor->canAccessPoli('POL01'));
        $this->assertTrue($doctor->canAccessPoli('POL02'));
        $this->assertFalse($doctor->canAccessPoli('POL03'));

        $admin = new UserAccount(
            id: 1,
            username: 'admin',
            passwordHash: 'hash',
            email: 'admin@medars.local',
            fullname: 'Admin',
            role: 'admin',
            access: ['all'],
            cap: ['all'],
            status: '1'
        );

        $this->assertTrue($admin->canAccessPoli('POL01'));
        $this->assertTrue($admin->canAccessPoli('POL99'));
    }

    public function testDeactivatedUser(): void
    {
        $deactivated = new UserAccount(
            id: 5,
            username: 'nonaktif',
            passwordHash: 'hash',
            email: 'nonaktif@medars.local',
            fullname: 'Mantan Karyawan',
            role: 'staff',
            access: ['all'],
            cap: ['all'],
            status: '0'
        );

        $this->assertFalse($deactivated->isActive());
    }

    public function testAccessAndCapParsedFromCsvOrArray(): void
    {
        // Support format serialisasi ksmlite lama (koma atau serialized array)
        $userFromComma = UserAccount::fromLegacy(
            id: 10,
            username: 'perawat1',
            passwordHash: 'hash',
            email: 'nurse@medars.local',
            fullname: 'Ns. Ratna',
            role: 'perawat',
            accessRaw: 'rawat_jalan, pendaftaran, igd',
            capRaw: 'POL01, POL02',
            avatar: '',
            status: '1'
        );

        $this->assertTrue($userFromComma->canAccessModule('rawat_jalan'));
        $this->assertTrue($userFromComma->canAccessModule('pendaftaran'));
        $this->assertTrue($userFromComma->canAccessModule('igd'));
        $this->assertFalse($userFromComma->canAccessModule('farmasi'));
        $this->assertTrue($userFromComma->canAccessPoli('POL01'));
        $this->assertFalse($userFromComma->canAccessPoli('POL05'));
    }
}
