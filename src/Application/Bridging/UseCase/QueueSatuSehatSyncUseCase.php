<?php

declare(strict_types=1);

namespace App\Application\Bridging\UseCase;

use App\Domain\Bridging\Repository\BridgingQueueRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;

final readonly class QueueSatuSehatSyncUseCase
{
    public function __construct(
        private BridgingQueueRepositoryInterface $queueRepo,
    ) {}

    /**
     * Builds FHIR R4 transaction Bundle (Encounter + Condition + ServiceRequest) and enqueues asynchronously.
     *
     * @return array<string, mixed>
     */
    public function execute(
        NoRawat $noRawat,
        string $patientIhsId,
        string $practitionerIhsId,
        string $icd10Code,
        string $icd10Display,
        string $encounterStatus = 'finished',
    ): array {
        $encounterUuid = 'urn:uuid:' . bin2hex(random_bytes(8));
        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'entry' => [
                [
                    'fullUrl' => $encounterUuid,
                    'resource' => [
                        'resourceType' => 'Encounter',
                        'status' => $encounterStatus,
                        'subject' => ['reference' => 'Patient/' . $patientIhsId],
                        'participant' => [[
                            'individual' => ['reference' => 'Practitioner/' . $practitionerIhsId],
                        ]],
                    ],
                    'request' => ['method' => 'POST', 'url' => 'Encounter'],
                ],
                [
                    'resource' => [
                        'resourceType' => 'Condition',
                        'clinicalStatus' => [
                            'coding' => [[
                                'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                                'code' => 'active',
                            ]],
                        ],
                        'code' => [
                            'coding' => [[
                                'system' => 'http://hl7.org/fhir/sid/icd-10',
                                'code' => $icd10Code,
                                'display' => $icd10Display,
                            ]],
                        ],
                        'subject' => ['reference' => 'Patient/' . $patientIhsId],
                        'encounter' => ['reference' => $encounterUuid],
                    ],
                    'request' => ['method' => 'POST', 'url' => 'Condition'],
                ],
            ],
        ];

        return $this->queueRepo->enqueue(
            provider: 'SATUSEHAT',
            resourceType: 'Bundle',
            noRawat: $noRawat->value(),
            endpoint: '/',
            payloadJson: (string) json_encode($bundle, JSON_UNESCAPED_UNICODE),
        );
    }
}
