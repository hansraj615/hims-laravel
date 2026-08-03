<?php

namespace App\Domain\Appointments\Services;

use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\SequenceCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentNumberGenerator
{
    public function nextForHospital(Hospital $hospital): string
    {
        return DB::transaction(function () use ($hospital): string {
            SequenceCounter::firstOrCreate(
                [
                    'hospital_id' => $hospital->id,
                    'branch_id' => 0,
                    'name' => 'appointment_number',
                ],
                ['current_value' => 0],
            );

            $counter = SequenceCounter::query()
                ->where('hospital_id', $hospital->id)
                ->where('branch_id', 0)
                ->where('name', 'appointment_number')
                ->lockForUpdate()
                ->firstOrFail();

            $counter->current_value++;
            $counter->save();

            $prefix = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', $hospital->code), 12, ''));

            return sprintf('%s-APT-%06d', $prefix, $counter->current_value);
        });
    }
}
