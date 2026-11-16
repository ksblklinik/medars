<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;

final class NavigationBuilder
{
    /**
     * @return list<array{title: string, icon: string, route: string, modules: list<string>}>
     */
    private function catalog(): array
    {
        return [
            [
                'title' => 'Dashboard',
                'icon' => 'fa-gauge',
                'route' => '/',
                'modules' => [],
            ],
            [
                'title' => 'Pendaftaran',
                'icon' => 'fa-user-plus',
                'route' => '/pendaftaran',
                'modules' => ['pendaftaran'],
            ],
            [
                'title' => 'Rawat Jalan',
                'icon' => 'fa-stethoscope',
                'route' => '/dokter-ralan',
                'modules' => ['dokter_ralan', 'rawat_jalan'],
            ],
            [
                'title' => 'Farmasi',
                'icon' => 'fa-pills',
                'route' => '/farmasi',
                'modules' => ['farmasi', 'apotek_ralan'],
            ],
            [
                'title' => 'Kasir',
                'icon' => 'fa-cash-register',
                'route' => '/kasir',
                'modules' => ['kasir', 'kasir_rawat_jalan'],
            ],
            [
                'title' => 'Laboratorium',
                'icon' => 'fa-flask',
                'route' => '/laboratorium',
                'modules' => ['laboratorium'],
            ],
            [
                'title' => 'Radiologi',
                'icon' => 'fa-x-ray',
                'route' => '/radiologi',
                'modules' => ['radiologi'],
            ],
            [
                'title' => 'Laporan',
                'icon' => 'fa-chart-bar',
                'route' => '/laporan',
                'modules' => ['laporan'],
            ],
            [
                'title' => 'Pengguna',
                'icon' => 'fa-users',
                'route' => '/users',
                'modules' => ['users'],
            ],
            [
                'title' => 'Pengaturan',
                'icon' => 'fa-gear',
                'route' => '/settings',
                'modules' => ['settings'],
            ],
        ];
    }

    /**
     * @return list<array{title: string, icon: string, route: string}>
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
                    ];
                    break;
                }
            }
        }

        return $items;
    }
}
