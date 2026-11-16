<?php

declare(strict_types=1);

namespace App\Presentation\View;

final class ViewRenderer
{
    private string $viewsPath;
    private string $layoutTemplate;

    public function __construct(
        ?string $viewsPath = null,
        string $layoutTemplate = 'templates/layout'
    ) {
        $this->viewsPath = rtrim($viewsPath ?? __DIR__ . '/templates', '/\\');
        $this->layoutTemplate = $layoutTemplate;
    }

    /**
     * Render view dan dibungkus oleh Master Layout Shell.
     *
     * @param string $view Nama template (relatif terhadap viewsPath)
     * @param array<string, mixed> $data Variabel yang dipassing ke template
     * @param bool $withLayout Apakah dibungkus layout master (default true)
     */
    public function render(string $view, array $data = [], bool $withLayout = true): string
    {
        $viewFile = $this->resolveViewFile($view);
        $content = $this->renderFile($viewFile, $data);

        if (!$withLayout) {
            return $content;
        }

        $layoutFile = $this->resolveViewFile($this->layoutTemplate);
        $layoutData = array_merge($data, ['content' => $content]);

        return $this->renderFile($layoutFile, $layoutData);
    }

    /**
     * Sanitasi output XSS string.
     */
    public function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $filePath, array $data): string
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Berkas template view tidak ditemukan: '{$filePath}'");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            include $filePath;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    private function resolveViewFile(string $view): string
    {
        if (str_contains($view, '..')) {
            throw new \InvalidArgumentException("Path traversal tidak diizinkan pada template: '{$view}'");
        }

        $path = $this->viewsPath . '/' . ltrim($view, '/\\');
        if (!str_ends_with($path, '.php')) {
            $path .= '.php';
        }

        if (!file_exists($path) && (str_starts_with($view, 'templates/layout') || $view === 'layout')) {
            return __DIR__ . '/templates/layout.php';
        }

        return $path;
    }
}
