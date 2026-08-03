<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'status' => $this->status,
            'roles' => $this->getRoleNames()->values()->all(),
            'assignments' => $this->whenLoaded('hospitalAssignments', fn () => $this->hospitalAssignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'hospital_id' => $assignment->hospital_id,
                'branch_id' => $assignment->branch_id,
                'department_id' => $assignment->department_id,
                'assignment_type' => $assignment->assignment_type,
                'is_default' => $assignment->is_default,
                'status' => $assignment->status,
            ])->values()->all()),
        ];
    }
}
