<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Registration;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use PHPUnit\Framework\TestCase;

final class ValueObjectTest extends TestCase
{
    public function test_no_rkm_medis_preserves_leading_zeros_as_string(): void
    {
        $rm = new NoRkmMedis('000123');
        $this->assertSame('000123', $rm->value());
        $this->assertSame('000123', (string) $rm);

        $fromSeq = NoRkmMedis::fromSequence(45);
        $this->assertSame('000045', $fromSeq->value());
    }

    public function test_no_rkm_medis_rejects_empty_or_invalid_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NoRkmMedis('');
    }

    public function test_no_rawat_validates_and_generates_next_sequence(): void
    {
        $date = new \DateTimeImmutable('2026-11-16');
        $first = NoRawat::generate($date, 1);

        $this->assertSame('2026/11/16/000001', $first->value());
        $this->assertSame('2026/11/16/000002', $first->next()->value());
        $this->assertSame(1, $first->sequenceNumber());
    }

    public function test_no_rawat_rejects_invalid_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NoRawat('2026-11-16-000001'); // Format salah (memakai dash)
    }

    public function test_no_reg_formats_and_increments_three_digit_queue_string(): void
    {
        $reg = NoReg::fromSequence(1);
        $this->assertSame('001', $reg->value());
        $this->assertSame('002', $reg->next()->value());

        $regLarge = NoReg::fromSequence(125);
        $this->assertSame('125', $regLarge->value());
    }

    public function test_no_reg_rejects_zero_or_non_numeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NoReg('000');
    }

    public function test_no_reg_rejects_non_numeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NoReg('ABC');
    }
}
