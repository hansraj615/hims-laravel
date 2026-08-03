<?php

namespace App\Support\Tenancy;

use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Hospitals\Models\UserHospitalBranchAssignment;

readonly class TenantContext
{
    public function __construct(
        public Hospital $hospital,
        public ?Branch $branch,
        public UserHospitalBranchAssignment $assignment,
    ) {
    }

    public function hospitalId(): int
    {
        return $this->hospital->id;
    }

    public function branchId(): ?int
    {
        return $this->branch?->id;
    }
}
