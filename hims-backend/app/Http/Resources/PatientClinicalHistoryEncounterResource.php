<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\EMR\Models\Encounter */
class PatientClinicalHistoryEncounterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $vitals = is_array($this->vitals) ? $this->vitals : [];
        $diagnoses = is_array($this->diagnoses) ? $this->diagnoses : [];
        $complaints = $this->normalizeComplaints($this->chief_complaints);
        $carePlan = is_array($this->care_plan) ? $this->care_plan : [];
        $carePlanNotes = $carePlan['notes']
            ?? (array_is_list($carePlan) ? implode('; ', array_map('strval', $carePlan)) : null);

        $prescriptionItems = collect($this->prescriptions ?? [])
            ->flatMap(fn ($prescription) => $prescription->items ?? [])
            ->map(fn ($item) => [
                'medicine_name' => $item->medicine_name,
                'strength' => $item->strength,
                'route' => $item->route,
                'frequency' => $item->frequency,
                'duration' => $item->duration,
                'instructions' => $item->instructions,
            ])
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'date' => ($this->started_at ?? $this->created_at)?->toISOString(),
            'encounter_number' => $this->encounter_number,
            'status' => $this->status,
            'encounter_type' => $this->encounter_type,
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor === null ? null : [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'department' => $this->whenLoaded('department', fn () => $this->department === null ? null : [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'code' => $this->department->code,
            ]),
            'vitals_summary' => [
                'temperature_c' => $vitals['temperature_c'] ?? null,
                'pulse_bpm' => $vitals['pulse_bpm'] ?? null,
                'bp_systolic' => $vitals['bp_systolic'] ?? null,
                'bp_diastolic' => $vitals['bp_diastolic'] ?? null,
                'spo2_percent' => $vitals['spo2_percent'] ?? null,
            ],
            'chief_complaints' => $complaints,
            'diagnoses' => collect($diagnoses)->map(fn ($diagnosis) => [
                'display' => is_array($diagnosis) ? ($diagnosis['display'] ?? null) : (string) $diagnosis,
                'code' => is_array($diagnosis) ? ($diagnosis['code'] ?? null) : null,
                'system' => is_array($diagnosis) ? ($diagnosis['system'] ?? null) : null,
            ])->values()->all(),
            'prescription_items' => $prescriptionItems,
            'care_plan_notes' => $carePlanNotes,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeComplaints(mixed $complaints): array
    {
        if (! is_array($complaints)) {
            return [];
        }

        return collect($complaints)
            ->map(function ($item) {
                if (is_string($item)) {
                    return $item;
                }

                if (is_array($item)) {
                    return $item['text'] ?? $item['display'] ?? null;
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
