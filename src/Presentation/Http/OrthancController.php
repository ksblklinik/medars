<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\OrthancRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class OrthancController
{
    public function __construct(
        private OrthancRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {
    }

    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('orthanc/index', [
            'judul' => 'Orthanc PACS & DICOM Viewer',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/orthanc',
            'csrf_token' => $csrfToken,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    public function apiStudies(array $query = []): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listStudies(trim((string) ($query['q'] ?? '')))]);
    }

    public function apiSaveStudy(array $post): array
    {
        $noRawat = trim((string) ($post['no_rawat'] ?? ''));
        $studyUid = trim((string) ($post['study_uid'] ?? ''));
        if ($noRawat === '' || $studyUid === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Rawat dan Study Instance UID wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveStudy($post)]);
    }

    public function apiUpdateHasilBaca(?UserAccount $actor, array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        $hasil = trim((string) ($post['hasil_baca'] ?? ''));
        $dokter = trim((string) ($post['dokter_radiologi'] ?? ($actor?->getFullname() ?: ($actor?->getUsername() ?? ''))));
        if ($id <= 0 || $hasil === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'ID study dan hasil baca radiologi wajib diisi.']);
        }
        if (!$this->repo->updateHasilBaca($id, $hasil, $dokter)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Study DICOM tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Interpretasi citra radiologi disimpan.']);
    }

    public function apiDeleteStudy(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0 || !$this->repo->deleteStudy($id)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Study DICOM tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Study dihapus.']);
    }

    private function json(int $status, array $payload): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
