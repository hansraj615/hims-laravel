<?php

namespace App\Domain\EMR\Services;

use App\Domain\EMR\Models\Encounter;
use App\Domain\EMR\Models\Prescription;

class FhirPayloadBuilder
{
    /**
     * Build a minimal FHIR R4 Bundle containing an Encounter resource and, when a
     * prescription with items is supplied, one MedicationRequest resource per item.
     *
     * @return array<string, mixed>
     */
    public function buildEncounterBundle(Encounter $encounter, ?Prescription $prescription = null): array
    {
        $entries = [
            [
                'fullUrl' => 'urn:uuid:encounter-'.$encounter->id,
                'resource' => $this->buildEncounterResource($encounter),
            ],
        ];

        if ($prescription !== null) {
            foreach ($prescription->items as $item) {
                $entries[] = [
                    'fullUrl' => 'urn:uuid:medicationrequest-'.$item->id,
                    'resource' => $this->buildMedicationRequestResource($encounter, $prescription, $item),
                ];
            }
        }

        return [
            'resourceType' => 'Bundle',
            'type' => 'collection',
            'timestamp' => now()->toISOString(),
            'entry' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEncounterResource(Encounter $encounter): array
    {
        $diagnoses = collect($encounter->diagnoses ?? [])
            ->map(fn (array $diagnosis) => [
                'condition' => [
                    'text' => $diagnosis['display'] ?? null,
                ],
                'use' => [
                    'text' => $diagnosis['type'] ?? 'encounter-diagnosis',
                ],
            ])
            ->values()
            ->all();

        return array_filter([
            'resourceType' => 'Encounter',
            'id' => (string) $encounter->id,
            'status' => $encounter->status === 'completed' ? 'finished' : 'in-progress',
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'AMB',
                'display' => 'ambulatory',
            ],
            'subject' => ['reference' => 'Patient/'.$encounter->patient_id],
            'participant' => $encounter->doctor_user_id !== null ? [[
                'individual' => ['reference' => 'Practitioner/'.$encounter->doctor_user_id],
            ]] : [],
            'reasonCode' => collect($encounter->chief_complaints ?? [])->isEmpty() ? [] : [[
                'text' => is_array($encounter->chief_complaints) ? implode('; ', array_map('strval', $encounter->chief_complaints)) : (string) $encounter->chief_complaints,
            ]],
            'diagnosis' => $diagnoses,
            'period' => [
                'start' => $encounter->started_at?->toISOString(),
                'end' => $encounter->completed_at?->toISOString(),
            ],
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMedicationRequestResource(Encounter $encounter, Prescription $prescription, mixed $item): array
    {
        return array_filter([
            'resourceType' => 'MedicationRequest',
            'id' => (string) $item->id,
            'status' => 'active',
            'intent' => 'order',
            'medicationCodeableConcept' => [
                'text' => trim($item->medicine_name.($item->strength ? ' '.$item->strength : '')),
            ],
            'subject' => ['reference' => 'Patient/'.$encounter->patient_id],
            'requester' => $prescription->prescribed_by !== null ? [
                'reference' => 'Practitioner/'.$prescription->prescribed_by,
            ] : null,
            'dosageInstruction' => [array_filter([
                'text' => $item->instructions,
                'route' => $item->route ? ['text' => $item->route] : null,
                'timing' => $item->frequency ? ['code' => ['text' => $item->frequency]] : null,
            ], fn ($value) => $value !== null)],
        ], fn ($value) => $value !== null);
    }
}
