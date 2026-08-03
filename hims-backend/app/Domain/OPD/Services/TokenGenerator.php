<?php

namespace App\Domain\OPD\Services;

use App\Domain\Patients\Models\SequenceCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TokenGenerator
{
    /**
     * Allocate the next sequential OPD token for a hospital/branch/date/department/doctor
     * combination using a locked sequence counter row, guaranteeing no two callers can
     * ever receive the same token number even under concurrent check-ins.
     *
     * @return array{token_number: int, token_prefix: string, token_code: string}
     */
    public function nextToken(
        int $hospitalId,
        int $branchId,
        string $queueDate,
        ?int $departmentId,
        ?int $doctorUserId,
        ?string $tokenPrefix = null,
    ): array {
        return DB::transaction(function () use ($hospitalId, $branchId, $queueDate, $departmentId, $doctorUserId, $tokenPrefix): array {
            $counterName = sprintf(
                'opd_token:%s:%s:%s',
                $queueDate,
                $departmentId ?? '0',
                $doctorUserId ?? '0',
            );

            SequenceCounter::firstOrCreate(
                [
                    'hospital_id' => $hospitalId,
                    'branch_id' => $branchId,
                    'name' => $counterName,
                ],
                ['current_value' => 0],
            );

            $counter = SequenceCounter::query()
                ->where('hospital_id', $hospitalId)
                ->where('branch_id', $branchId)
                ->where('name', $counterName)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->current_value++;
            $counter->save();

            $prefix = Str::upper($tokenPrefix ?: 'OPD');

            return [
                'token_number' => $counter->current_value,
                'token_prefix' => $prefix,
                'token_code' => sprintf('%s-%03d', $prefix, $counter->current_value),
            ];
        });
    }
}
