<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Bridging\Repository\JknMobileRepositoryInterface;

final readonly class JknMobileController
{
    public function __construct(
        private JknMobileRepositoryInterface $repo,
        private string $tokenSecret
    ) {
    }

    public function getToken(array $headers = [], array $query = []): array
    {
        $username = trim((string) ($headers['X-Username'] ?? $headers['x-username'] ?? $query['username'] ?? ''));
        $password = (string) ($headers['X-Password'] ?? $headers['x-password'] ?? $query['password'] ?? '');
        if (!$this->repo->verifyCredentials($username, $password)) {
            return $this->bpjsJson(201, 'Username atau Password Tidak Sesuai');
        }
        $payload = base64_encode(json_encode(['u' => $username, 'exp' => time() + 3600], JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', $payload, $this->tokenSecret);
        return $this->bpjsJson(200, 'Ok', ['token' => $payload . '.' . $sig]);
    }

    public function ambilAntrean(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        if (trim((string) ($post['nomorkartu'] ?? '')) === '' || trim((string) ($post['kodepoli'] ?? '')) === '') {
            return $this->bpjsJson(201, 'Nomor Kartu dan Kode Poli wajib diisi');
        }
        return $this->bpjsJson(200, 'Ok', $this->repo->ambilAntrean($post));
    }

    public function statusAntrean(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        return $this->bpjsJson(200, 'Ok', $this->repo->statusAntrean(
            (string) ($post['kodepoli'] ?? ''),
            (string) ($post['kodedokter'] ?? ''),
            (string) ($post['tanggalperiksa'] ?? date('Y-m-d'))
        ));
    }

    public function sisaAntrean(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        $res = $this->repo->sisaAntrean((string) ($post['kodebooking'] ?? ''));
        return $res === null
            ? $this->bpjsJson(201, 'Antrean Tidak Ditemukan')
            : $this->bpjsJson(200, 'Ok', $res);
    }

    public function batalAntrean(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        $ok = $this->repo->batalAntrean((string) ($post['kodebooking'] ?? ''), (string) ($post['keterangan'] ?? ''));
        return $ok ? $this->bpjsJson(200, 'Ok') : $this->bpjsJson(201, 'Antrean Tidak Ditemukan');
    }

    public function checkIn(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        $res = $this->repo->checkIn((string) ($post['kodebooking'] ?? ''), (int) ($post['waktu'] ?? (int) round(microtime(true) * 1000)));
        return $res === null ? $this->bpjsJson(201, 'Kode Booking Tidak Ditemukan') : $this->bpjsJson(200, 'Ok', $res);
    }

    public function pasienBaru(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        if (trim((string) ($post['nik'] ?? '')) === '' || trim((string) ($post['nama'] ?? '')) === '') {
            return $this->bpjsJson(201, 'NIK dan Nama wajib diisi');
        }
        return $this->bpjsJson(200, 'Ok', $this->repo->pasienBaru($post));
    }

    public function ambilAntreanFarmasi(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        $res = $this->repo->ambilAntreanFarmasi((string) ($post['kodebooking'] ?? ''), (string) ($post['jenisresep'] ?? 'Non Racikan'));
        return $res === null ? $this->bpjsJson(201, 'Kode Booking Tidak Ditemukan') : $this->bpjsJson(200, 'Ok', $res);
    }

    public function statusAntreanFarmasi(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        $res = $this->repo->statusAntreanFarmasi((string) ($post['kodebooking'] ?? ''));
        return $res === null ? $this->bpjsJson(201, 'Kode Booking Tidak Ditemukan') : $this->bpjsJson(200, 'Ok', $res);
    }

    public function jadwalOperasiRs(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        return $this->bpjsJson(200, 'Ok', [
            'list' => $this->repo->jadwalOperasiRs(
                (string) ($post['tanggalawal'] ?? date('Y-m-d')),
                (string) ($post['tanggalakhir'] ?? date('Y-m-d'))
            ),
        ]);
    }

    public function jadwalOperasiPasien(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        return $this->bpjsJson(200, 'Ok', [
            'list' => $this->repo->jadwalOperasiPasien((string) ($post['nopeserta'] ?? '')),
        ]);
    }

    private function isValidToken(array $headers): bool
    {
        $token = trim((string) ($headers['X-Token'] ?? $headers['x-token'] ?? ''));
        if ($token === '' || !str_contains($token, '.')) {
            return false;
        }
        [$payload, $sig] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $payload, $this->tokenSecret);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        $decoded = json_decode((string) base64_decode($payload, true), true);
        return is_array($decoded) && (int) ($decoded['exp'] ?? 0) >= time();
    }

    private function bpjsJson(int $code, string $message, mixed $response = null): array
    {
        $body = ['metadata' => ['message' => $message, 'code' => $code]];
        if ($response !== null) {
            $body['response'] = $response;
        }
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
