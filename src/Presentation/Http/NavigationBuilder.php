<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;

final class NavigationBuilder
{
    /**
     * @return list<array{title: string, icon: string, route: string, category: string, module_key: string, modules: list<string>}>
     */
    private function catalog(): array
    {
        return [
            [
                'title' => 'Dashboard',
                'icon' => 'fa-gauge',
                'route' => '/',
                'category' => 'Utama',
                'module_key' => 'dashboard',
                'modules' => [],
            ],
            [
                'title' => 'Front Office',
                'icon' => 'fa-headset',
                'route' => '/frontoffice',
                'category' => 'Layanan',
                'module_key' => 'frontoffice',
                'modules' => ['frontoffice', 'pendaftaran', 'rawat_jalan'],
            ],
            [
                'title' => 'Pasien',
                'icon' => 'fa-address-card',
                'route' => '/pasien',
                'category' => 'Layanan',
                'module_key' => 'pasien',
                'modules' => ['pasien', 'pendaftaran'],
            ],
            [
                'title' => 'Pendaftaran',
                'icon' => 'fa-user-plus',
                'route' => '/pendaftaran',
                'category' => 'Layanan',
                'module_key' => 'pendaftaran',
                'modules' => ['pendaftaran'],
            ],
            [
                'title' => 'Dokter Ralan',
                'icon' => 'fa-stethoscope',
                'route' => '/dokter-ralan',
                'category' => 'Layanan',
                'module_key' => 'dokter_ralan',
                'modules' => ['dokter_ralan', 'rawat_jalan'],
            ],
            [
                'title' => 'IGD & Triase',
                'icon' => 'fa-truck-medical',
                'route' => '/igd',
                'category' => 'Layanan',
                'module_key' => 'igd',
                'modules' => ['igd', 'dokter_igd', 'pendaftaran'],
            ],
            [
                'title' => 'Surat Medis',
                'icon' => 'fa-file-medical',
                'route' => '/surat',
                'category' => 'Layanan',
                'module_key' => 'surat',
                'modules' => ['surat', 'dokter_ralan', 'rawat_jalan'],
            ],
            [
                'title' => 'Farmasi',
                'icon' => 'fa-pills',
                'route' => '/farmasi',
                'category' => 'Farmasi',
                'module_key' => 'farmasi',
                'modules' => ['farmasi', 'apotek_ralan'],
            ],
            [
                'title' => 'Kasir',
                'icon' => 'fa-cash-register',
                'route' => '/kasir',
                'category' => 'Keuangan & Penunjang',
                'module_key' => 'kasir',
                'modules' => ['kasir', 'kasir_rawat_jalan'],
            ],
            [
                'title' => 'Laboratorium',
                'icon' => 'fa-flask',
                'route' => '/laboratorium',
                'category' => 'Keuangan & Penunjang',
                'module_key' => 'laboratorium',
                'modules' => ['laboratorium'],
            ],
            [
                'title' => 'Radiologi',
                'icon' => 'fa-x-ray',
                'route' => '/radiologi',
                'category' => 'Keuangan & Penunjang',
                'module_key' => 'radiologi',
                'modules' => ['radiologi'],
            ],
            [
                'title' => 'Laporan',
                'icon' => 'fa-chart-bar',
                'route' => '/laporan',
                'category' => 'Keuangan & Penunjang',
                'module_key' => 'laporan',
                'modules' => ['laporan'],
            ],
            [
                'title' => 'Bridging BPJS',
                'icon' => 'fa-network-wired',
                'route' => '/bridging',
                'category' => 'Bridging & CRM',
                'module_key' => 'bridging',
                'modules' => ['bridging_bpjs', 'vclaim', 'satu_sehat', 'vedika'],
            ],
            [
                'title' => 'WhatsApp CRM',
                'icon' => 'fa-brands fa-whatsapp',
                'route' => '/wagateway',
                'category' => 'Bridging & CRM',
                'module_key' => 'wagateway',
                'modules' => ['wagateway', 'crm'],
            ],
            [
                'title' => 'Home Care',
                'icon' => 'fa-house-medical',
                'route' => '/homecare',
                'category' => 'Layanan',
                'module_key' => 'homecare',
                'modules' => ['home_care', 'homecare'],
            ],
            [
                'title' => 'Audit ACID',
                'icon' => 'fa-shield-halved',
                'route' => '/audit',
                'category' => 'Manajemen & Sistem',
                'module_key' => 'audit',
                'modules' => ['audit', 'admin'],
            ],
            [
                'title' => 'Pengguna',
                'icon' => 'fa-users',
                'route' => '/users',
                'category' => 'Manajemen & Sistem',
                'module_key' => 'users',
                'modules' => ['users'],
            ],
            [
                'title' => 'Master Data',
                'icon' => 'fa-database',
                'route' => '/master',
                'category' => 'Manajemen & Sistem',
                'module_key' => 'master',
                'modules' => ['master', 'admin'],
            ],
            [
                'title' => 'Kepegawaian',
                'icon' => 'fa-id-badge',
                'route' => '/kepegawaian',
                'category' => 'Manajemen & Sistem',
                'module_key' => 'kepegawaian',
                'modules' => ['kepegawaian', 'admin'],
            ],
            [
                'title' => 'Pengaturan',
                'icon' => 'fa-gear',
                'route' => '/settings',
                'category' => 'Manajemen & Sistem',
                'module_key' => 'settings',
                'modules' => ['settings'],
            ],
        ];
    }

    /**
     * @return list<array{title: string, icon: string, route: string, category: string, module_key: string}>
     */
    public function buildForUser(?UserAccount $user): array
    {
        $items = [];
        foreach ($this->catalog() as $entry) {
            if ($entry['modules'] === []) {
                $items[] = [
                    'title' => $entry['title'],
                    'icon' => $entry['icon'],
                    'route' => $entry['route'],
                    'category' => $entry['category'],
                    'module_key' => $entry['module_key'],
                ];
                continue;
            }

            if ($user === null) {
                continue;
            }

            foreach ($entry['modules'] as $mod) {
                if ($user->canAccessModule($mod)) {
                    $items[] = [
                        'title' => $entry['title'],
                        'icon' => $entry['icon'],
                        'route' => $entry['route'],
                        'category' => $entry['category'],
                        'module_key' => $entry['module_key'],
                    ];
                    break;
                }
            }
        }

        return $items;
    }

    /**
     * @return array<string, list<array{title: string, icon: string, route: string, module_key: string}>>
     */
    public function buildGroupedForUser(?UserAccount $user): array
    {
        $grouped = [];
        foreach ($this->buildForUser($user) as $item) {
            if ($item['category'] === 'Utama') {
                continue;
            }
            $grouped[$item['category']][] = [
                'title' => $item['title'],
                'icon' => $item['icon'],
                'route' => $item['route'],
                'module_key' => $item['module_key'],
            ];
        }
        return $grouped;
    }
}
