<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\View;

use App\Presentation\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class ViewRendererTest extends TestCase
{
    private string $tempViewsDir;
    private ViewRenderer $renderer;

    protected function setUp(): void
    {
        $this->tempViewsDir = sys_get_temp_dir() . '/medars_views_' . uniqid();
        mkdir($this->tempViewsDir . '/templates', 0777, true);

        // Buat view parsial sederhana
        file_put_contents(
            $this->tempViewsDir . '/test_view.php',
            '<h1>Konten: <?= $this->e($judul) ?></h1><div id="formSlot">Form</div>'
        );

        $this->renderer = new ViewRenderer($this->tempViewsDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempViewsDir)) {
            $files = glob($this->tempViewsDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($this->tempViewsDir . '/templates')) {
                @rmdir($this->tempViewsDir . '/templates');
            }
            @rmdir($this->tempViewsDir);
        }
    }

    public function test_renders_view_wrapped_in_master_layout(): void
    {
        $html = $this->renderer->render('test_view', [
            'judul' => 'Pendaftaran Pasien',
            'klinik_nama' => 'Klinik Pratama Rawat Inap',
            'navigations' => [
                ['title' => 'Pendaftaran', 'icon' => 'fa-user-plus', 'route' => '/pendaftaran'],
                ['title' => 'Poli Umum', 'icon' => 'fa-stethoscope', 'route' => '/rawat-jalan'],
            ],
        ]);

        // Verifikasi paritas elemen layout eksisting
        $this->assertStringContainsString('Pendaftaran Pasien', $html);
        $this->assertStringContainsString('Klinik Pratama Rawat Inap', $html);
        $this->assertStringContainsString('Poli Umum', $html);

        // Verifikasi revolusi teknis: Keberadaan Dynamic Viewport Slot & Single-Host Modal
        $this->assertStringContainsString('id="moduleViewportSlot"', $html, 'Wajib memiliki Dynamic Viewport Slot.');
        $this->assertStringContainsString('id="appModalHost"', $html, 'Wajib memiliki Single-Host Modal (#appModalHost).');
    }

    public function test_escapes_xss_output_properly(): void
    {
        $html = $this->renderer->render('test_view', [
            'judul' => '<script>alert("XSS")</script>',
            'klinik_nama' => 'Klinik Test',
            'navigations' => [],
        ]);

        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $html);
    }
}
