<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Bridging\UseCase\FetchIcareHistoryUseCase;

final readonly class IcareController
{
    public function __construct(private FetchIcareHistoryUseCase $useCase)
    {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiRiwayat(array $query = []): array
    {
        $noRawat = trim((string) ($query['no_rawat'] ?? ''));
        $mode = trim((string) ($query['mode'] ?? 'fkrtl'));
        $res = $this->useCase->execute($noRawat, $mode);

        return [
            'status' => $res['status_code'],
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($res, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function viewer(array $query = []): array
    {
        $noRawat = trim((string) ($query['no_rawat'] ?? ''));
        $mode = trim((string) ($query['mode'] ?? 'fkrtl'));
        $res = $this->useCase->execute($noRawat, $mode);

        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $contentHtml = $res['ok']
            ? '<div class="alert alert-success"><strong>' . $e($res['message']) . '</strong></div>'
              . '<table class="table table-sm table-bordered small">'
              . '<tr><th width="35%">No. Rawat</th><td>' . $e($res['no_rawat']) . '</td></tr>'
              . '<tr><th>Nama Pasien</th><td>' . $e($res['nm_pasien']) . '</td></tr>'
              . '<tr><th>No. Kartu BPJS / NIK</th><td><code>' . $e($res['no_peserta']) . '</code></td></tr>'
              . '<tr><th>Dokter DPJP</th><td>' . $e($res['nm_dokter']) . ' (' . $e($res['kd_dokter']) . ')</td></tr>'
              . '</table>'
              . '<a href="' . $e($res['url']) . '" target="_blank" rel="noopener" class="btn btn-sm text-white" style="background-color:#00897b;">Buka Portal i-Care JKN BPJS</a>'
            : '<div class="alert alert-warning"><strong>Gagal memuat i-Care:</strong> ' . $e($res['message']) . '</div>';

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>i-Care JKN BPJS</title>'
            . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
            . '<body class="bg-light p-4"><div class="container" style="max-width:640px;"><div class="card shadow-sm border-0">'
            . '<div class="card-header text-white fw-semibold" style="background-color:#00897b;">Riwayat Pelayanan i-Care JKN BPJS</div>'
            . '<div class="card-body">' . $contentHtml . '</div></div></div></body></html>';

        return [
            'status' => $res['status_code'],
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }
}
