<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Modules\Exception\ModuleDependencyException;
use App\Modules\Service\DependencyResolver;
use PHPUnit\Framework\TestCase;

final class DependencyResolverTest extends TestCase
{
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DependencyResolver();
    }

    public function test_resolves_modules_in_correct_order(): void
    {
        // rawat-jalan butuh pendaftaran
        // apotek butuh farmasi dan rawat-jalan
        $modules = [
            'apotek' => ['farmasi', 'rawat-jalan'],
            'rawat-jalan' => ['pendaftaran'],
            'pendaftaran' => [],
            'farmasi' => [],
        ];

        $order = $this->resolver->resolveOrder($modules);

        // pendaftaran dan farmasi harus lebih dulu dari rawat-jalan dan apotek
        $pendaftaranIdx = array_search('pendaftaran', $order, true);
        $rawatJalanIdx = array_search('rawat-jalan', $order, true);
        $farmasiIdx = array_search('farmasi', $order, true);
        $apotekIdx = array_search('apotek', $order, true);

        $this->assertLessThan($rawatJalanIdx, $pendaftaranIdx);
        $this->assertLessThan($apotekIdx, $rawatJalanIdx);
        $this->assertLessThan($apotekIdx, $farmasiIdx);
    }

    public function test_detects_and_prevents_circular_dependencies(): void
    {
        // A butuh B, B butuh C, C butuh A
        $modules = [
            'modul-a' => ['modul-b'],
            'modul-b' => ['modul-c'],
            'modul-c' => ['modul-a'],
        ];

        $this->expectException(ModuleDependencyException::class);
        $this->expectExceptionMessage('Terdeteksi ketergantungan melingkar (circular dependency)');

        $this->resolver->resolveOrder($modules);
    }
}
