<?php

namespace App\Domain\IPD\Services;

use App\Domain\IPD\Models\Admission;
use App\Domain\IPD\Models\AdmissionClearance;

class AdmissionClearanceBootstrapper
{
    public function seedPending(Admission $admission): void
    {
        foreach (AdmissionClearance::TYPES as $type) {
            AdmissionClearance::firstOrCreate(
                [
                    'admission_id' => $admission->id,
                    'clearance_type' => $type,
                ],
                [
                    'hospital_id' => $admission->hospital_id,
                    'status' => 'pending',
                ],
            );
        }
    }

    public function allCleared(Admission $admission): bool
    {
        $this->seedPending($admission);

        return AdmissionClearance::query()
            ->where('admission_id', $admission->id)
            ->get()
            ->every(fn (AdmissionClearance $clearance) => $clearance->isCleared());
    }
}
