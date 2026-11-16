<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Setting;

use App\Domain\Setting\Service\SettingService;
use App\Domain\Setting\Repository\SettingRepositoryInterface;
use PHPUnit\Framework\TestCase;

class SettingServiceTest extends TestCase
{
    private SettingRepositoryInterface $repo;
    private SettingService $service;
    private array $storage = [];

    protected function setUp(): void
    {
        $this->storage = [
            'pendaftaran.nama_instansi' => 'Klinik Pratama Rawat Inap Sehat Sejahtera',
            'pendaftaran.max_kuota' => '40',
            'aplikasi.tema' => 'teal',
        ];

        $this->repo = new class($this->storage) implements SettingRepositoryInterface {
            public function __construct(public array &$storage) {}

            public function get(string $module, string $field): ?string
            {
                return $this->storage["{$module}.{$field}"] ?? null;
            }

            public function getAll(string $module): array
            {
                $prefix = "{$module}.";
                $result = [];
                foreach ($this->storage as $key => $val) {
                    if (str_starts_with($key, $prefix)) {
                        $field = substr($key, strlen($prefix));
                        $result[$field] = $val;
                    }
                }
                return $result;
            }

            public function set(string $module, string $field, string $value): void
            {
                $this->storage["{$module}.{$field}"] = $value;
            }
        };

        $this->service = new SettingService($this->repo);
    }

    public function testGetSettingWithDefault(): void
    {
        $this->assertSame('Klinik Pratama Rawat Inap Sehat Sejahtera', $this->service->get('pendaftaran', 'nama_instansi'));
        $this->assertSame(40, $this->service->getInt('pendaftaran', 'max_kuota'));
        $this->assertSame('default_val', $this->service->get('pendaftaran', 'unknown_field', 'default_val'));
    }

    public function testSetSettingUpdatesValueAndCache(): void
    {
        $this->service->set('aplikasi', 'tema', 'dark-emerald');
        $this->assertSame('dark-emerald', $this->service->get('aplikasi', 'tema'));
        $this->assertSame('dark-emerald', $this->storage['aplikasi.tema']);
    }

    public function testGetAllSettingsForModule(): void
    {
        $all = $this->service->getAll('pendaftaran');
        $this->assertArrayHasKey('nama_instansi', $all);
        $this->assertArrayHasKey('max_kuota', $all);
        $this->assertArrayNotHasKey('tema', $all);
    }
}
