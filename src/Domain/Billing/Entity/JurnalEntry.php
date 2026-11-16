<?php

declare(strict_types=1);

namespace App\Domain\Billing\Entity;

final readonly class JurnalEntry
{
    public const COA_KAS_FISIK = '1101.01';
    public const COA_PIUTANG_BPJS = '1103.01';
    public const COA_PIUTANG_REKANAN = '1103.02';
    public const COA_PENDAPATAN_LAYANAN = '4101.01';
    public const COA_PENDAPATAN_OBAT = '4101.02';

    /**
     * @param list<JurnalDetail> $details
     */
    public function __construct(
        public string $noJurnal,
        public string $tglJurnal,
        public string $jamJurnal,
        public string $noBukti,
        public string $keterangan,
        public array $details,
    ) {
        if ($this->details === []) {
            throw new \DomainException('Entri jurnal harus memiliki minimal 2 baris detail (debet & kredit).');
        }

        $debet = $this->totalDebet();
        $kredit = $this->totalKredit();

        if ($debet <= 0 || abs($debet - $kredit) > 0.001) {
            throw new \DomainException(
                "Jurnal {$this->noJurnal} tidak seimbang (Debet: {$debet}, Kredit: {$kredit})."
            );
        }
    }

    public function totalDebet(): float
    {
        $sum = 0.0;
        foreach ($this->details as $d) {
            $sum += $d->debet;
        }
        return round($sum, 2);
    }

    public function totalKredit(): float
    {
        $sum = 0.0;
        foreach ($this->details as $d) {
            $sum += $d->kredit;
        }
        return round($sum, 2);
    }

    public function createReversal(string $newNoJurnal, string $tglJurnal, string $jamJurnal, string $reason): self
    {
        $reversedDetails = [];
        foreach ($this->details as $d) {
            $reversedDetails[] = new JurnalDetail(
                kdRek: $d->kdRek,
                debet: $d->kredit,
                kredit: $d->debet
            );
        }

        return new self(
            noJurnal: $newNoJurnal,
            tglJurnal: $tglJurnal,
            jamJurnal: $jamJurnal,
            noBukti: $this->noBukti,
            keterangan: "[REVERSAL {$this->noJurnal}] {$reason}",
            details: $reversedDetails
        );
    }
}
