<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Rme\Repository\DokterTindakanRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveResumeMedisUseCase
{
    public function __construct(
        private DokterTindakanRepositoryInterface $tindakanRepo,
        private TransactionManagerInterface $tx,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data): void
    {
        if (trim((string) ($data['no_rawat'] ?? '')) === '' || trim((string) ($data['keluhan_utama'] ?? '')) === '') {
            throw new \InvalidArgumentException('No. Rawat dan Keluhan Utama pada Resume Medis wajib diisi.');
        }

        $this->tx->transaction(function () use ($data): void {
            $this->tindakanRepo->saveResume($data);
        });
    }
}
