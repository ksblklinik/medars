<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Service;

final readonly class HfisLeanCalculator
{
    public function resolveDoctorStartTime(string $shift, string $kdPoli, string $nmPoli): string
    {
        if (strcasecmp(trim($shift), 'Pagi') === 0) {
            return '08:00:00';
        }

        $isDental = stripos($kdPoli, 'GIG') !== false || stripos($nmPoli, 'gigi') !== false;
        return $isDental ? '15:00:00' : '16:00:00';
    }

    /**
     * @return array{
     *   doctor_start_time: string,
     *   estimated_service_time: string,
     *   suggested_arrival_time: string
     * }
     */
    public function calculateEstimate(
        string $shift,
        string $kdPoli,
        string $nmPoli,
        int $noReg,
        int $durationMinutes,
        string $date,
    ): array {
        $startTime = $this->resolveDoctorStartTime($shift, $kdPoli, $nmPoli);
        $startDt = new \DateTimeImmutable($date . ' ' . $startTime);

        $offsetMinutes = max(0, $noReg) * max(1, $durationMinutes);
        $estimatedDt = $startDt->modify('+' . $offsetMinutes . ' minutes');
        $suggestedDt = $estimatedDt->modify('-30 minutes');

        if ($suggestedDt < $startDt) {
            $suggestedDt = $startDt;
        }

        return [
            'doctor_start_time' => $startTime,
            'estimated_service_time' => $estimatedDt->format('H:i:s'),
            'suggested_arrival_time' => $suggestedDt->format('H:i:s'),
        ];
    }

    public function calculateSignedArrivalDeltaMinutes(string $actualArrivalDateTime, string $estimatedDateTime): int
    {
        $actualTs = strtotime($actualArrivalDateTime);
        $estTs = strtotime($estimatedDateTime);
        if ($actualTs === false || $estTs === false) {
            return 0;
        }

        return (int) round(($actualTs - $estTs) / 60);
    }
}
