<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Bridging\Repository\JknMobileRepositoryInterface;

final readonly class JknMobileFktpController
{
    public function __construct(
        private JknMobileRepositoryInterface $repo,
        private string $tokenSecret
    ) {
    }

    public function auth(array $headers = [], array $query = []): array
    {
        $username = trim((string) ($headers['X-Username'] ?? $headers['x-username'] ?? $query['username'] ?? ''));
        $password = (string) ($headers['X-Password'] ?? $headers['x-password'] ?? $query['password'] ?? '');
        if (!$this->repo->verifyCredentials($username, $password)) {
            return $this->bpjsJson(201, 'Username atau Password Tidak Sesuai');
        }
        $payload = base64_encode(json_encode(['u' => $username, 'scope' => 'fktp', 'exp' => time() + 3600], JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', $payload, $this->tokenSecret);
        return $this->bpjsJson(200, 'Ok', ['token' => $payload . '.' . $sig]);
    }

    public function antrean(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        if (trim((string) ($post['nomorkartu'] ?? '')) === '' || trim((string) ($post['kodepoli'] ?? '')) === '') {
            return $this->bpjsJson(201, 'Nomor Kartu dan Kode Poli wajib diisi');
        }
        return $this->bpjsJson(200, 'Ok', $this->repo->ambilAntrean($post));
    }

    public function statusAntrean(array $headers, array $input): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        return $this->bpjsJson(200, 'Ok', $this->repo->statusAntrean(
            (string) ($input['kodepoli'] ?? ''),
            (string) ($input['kodedokter'] ?? ''),
            (string) ($input['tanggalperiksa'] ?? date('Y-m-d'))
        ));
    }

    public function sisaAntrean(array $headers, array $input): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        $res = $this->repo->sisaAntreanFktp(
            (string) ($input['nomorkartu'] ?? ''),
            (string) ($input['kodepoli'] ?? ''),
            (string) ($input['tanggalperiksa'] ?? date('Y-m-d'))
        );
        return $res === null ? $this->bpjsJson(201, 'Antrean Peserta Tidak Ditemukan') : $this->bpjsJson(200, 'Ok', $res);
    }

    public function batalAntrean(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        $ok = $this->repo->batalAntreanFktp(
            (string) ($post['nomorkartu'] ?? ''),
            (string) ($post['kodepoli'] ?? ''),
            (string) ($post['tanggalperiksa'] ?? date('Y-m-d')),
            (string) ($post['keterangan'] ?? 'Dibatalkan oleh peserta')
        );
        return $ok ? $this->bpjsJson(200, 'Ok') : $this->bpjsJson(201, 'Antrean Peserta Tidak Ditemukan');
    }

    public function peserta(array $headers, array $post): array
    {
        if (!$this->isValidToken($headers)) {
            return $this->bpjsJson(201, 'Token Expired / Tidak Valid');
        }
        if (trim((string) ($post['nik'] ?? '')) === '' || trim((string) ($post['nama'] ?? '')) === '') {
            return $this->bpjsJson(201, 'NIK dan Nama wajib diisi');
        }
        return $this->bpjsJson(200, 'Ok', $this->repo->pasienBaru($post));
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
