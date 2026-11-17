<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Rme\UseCase\VerifySuratUseCase;
use App\Presentation\View\ViewRenderer;

final readonly class VerifikasiController
{
    public function __construct(
        private VerifySuratUseCase $useCase,
        private ViewRenderer $renderer,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(array $query = []): array
    {
        $hash = trim((string) ($query['hash'] ?? ''));
        $type = trim((string) ($query['type'] ?? 'sakit'));
        $noSurat = trim((string) ($query['no_surat'] ?? ''));

        $result = null;
        if ($hash !== '') {
            $result = $this->useCase->verifyByHash($hash);
        } elseif ($noSurat !== '') {
            $result = $this->useCase->verifyDirect($type, $noSurat);
        }

        $body = $this->renderer->render('verifikasi/index', [
            'judul' => 'Verifikasi Keaslian Dokumen Medis',
            'hash' => $hash,
            'type' => $type,
            'no_surat' => $noSurat,
            'result' => $result,
        ], false);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiVerify(array $query = []): array
    {
        $hash = trim((string) ($query['hash'] ?? ''));
        $type = trim((string) ($query['type'] ?? 'sakit'));
        $noSurat = trim((string) ($query['no_surat'] ?? ''));

        $res = $hash !== ''
            ? $this->useCase->verifyByHash($hash)
            : $this->useCase->verifyDirect($type, $noSurat);

        return [
            'status' => $res['valid'] ? 200 : 404,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($res, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
