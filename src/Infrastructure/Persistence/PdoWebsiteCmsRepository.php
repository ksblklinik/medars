<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\WebsiteCmsRepositoryInterface;
use PDO;

final class PdoWebsiteCmsRepository implements WebsiteCmsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listPages(bool $publishedOnly = false): array
    {
        $sql = 'SELECT * FROM mlite_website_pages';
        if ($publishedOnly) {
            $sql .= ' WHERE is_published = 1';
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mlite_website_pages WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => trim($slug)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function savePage(array $data): array
    {
        $slug = strtolower(trim((string) ($data['slug'] ?? 'beranda')));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?: 'beranda';
        $title = trim((string) ($data['title'] ?? ''));
        $content = (string) ($data['content_html'] ?? '');
        $published = (int) ($data['is_published'] ?? 1);
        $now = date('Y-m-d H:i:s');

        $existing = $this->findBySlug($slug);
        if ($existing !== null) {
            $upd = $this->pdo->prepare(
                'UPDATE mlite_website_pages SET title = :t, content_html = :c, is_published = :p, updated_at = :u WHERE slug = :s'
            );
            $upd->execute([':t' => $title, ':c' => $content, ':p' => $published, ':u' => $now, ':s' => $slug]);
        } else {
            $ins = $this->pdo->prepare(
                'INSERT INTO mlite_website_pages (slug, title, content_html, is_published, updated_at) VALUES (:s, :t, :c, :p, :u)'
            );
            $ins->execute([':s' => $slug, ':t' => $title, ':c' => $content, ':p' => $published, ':u' => $now]);
        }

        return $this->findBySlug($slug) ?? [];
    }
}
