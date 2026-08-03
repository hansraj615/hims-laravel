<?php

namespace App\Domain\IPD\Services;

use App\Domain\Billing\Models\Service;
use App\Domain\IPD\Models\Admission;
use App\Domain\IPD\Models\IpdChargeLine;

class IpdChargePoster
{
    /**
     * Ensure one auto bed-day charge exists for each calendar day from admit through today.
     *
     * @return list<IpdChargeLine>
     */
    public function ensureBedDayCharges(Admission $admission, ?int $actorId = null): array
    {
        $service = Service::query()
            ->forHospital($admission->hospital_id)
            ->where('code', 'IPDDAY')
            ->where('status', 'active')
            ->first();

        $unitRate = $service ? (float) $service->base_rate : 2500.0;
        $description = $service?->name ?? 'IPD Bed Day Charge';

        $start = $admission->admitted_at->copy()->startOfDay();
        $end = now()->copy()->startOfDay();
        $created = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $query = IpdChargeLine::query()
                ->where('admission_id', $admission->id)
                ->where('source', 'auto_bed_day')
                ->whereDate('charge_date', $day->toDateString());

            if ($service) {
                $query->where('service_id', $service->id);
            } else {
                $query->whereNull('service_id');
            }

            $existing = $query->first();
            if ($existing) {
                continue;
            }

            $created[] = IpdChargeLine::create([
                'hospital_id' => $admission->hospital_id,
                'admission_id' => $admission->id,
                'service_id' => $service?->id,
                'charge_date' => $day->toDateString(),
                'description' => $description,
                'source' => 'auto_bed_day',
                'quantity' => 1,
                'unit_rate' => $unitRate,
                'amount' => $unitRate,
                'status' => 'open',
                'created_by' => $actorId,
            ]);
        }

        return $created;
    }
}
