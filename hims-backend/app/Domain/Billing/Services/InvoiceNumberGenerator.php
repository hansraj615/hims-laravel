<?php

namespace App\Domain\Billing\Services;

use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\SequenceCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceNumberGenerator
{
    public function nextForHospital(Hospital $hospital): string
    {
        return DB::transaction(function () use ($hospital): string {
            SequenceCounter::firstOrCreate(
                [
                    'hospital_id' => $hospital->id,
                    'branch_id' => 0,
                    'name' => 'invoice_number',
                ],
                ['current_value' => 0],
            );

            $counter = SequenceCounter::query()
                ->where('hospital_id', $hospital->id)
                ->where('branch_id', 0)
                ->where('name', 'invoice_number')
                ->lockForUpdate()
                ->firstOrFail();

            $counter->current_value++;
            $counter->save();

            $prefix = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', $hospital->code), 12, ''));

            return sprintf('%s-INV-%06d', $prefix, $counter->current_value);
        });
    }
}
