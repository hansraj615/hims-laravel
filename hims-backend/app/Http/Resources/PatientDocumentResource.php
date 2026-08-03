<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'document_type' => $this->document_type,
            'title' => $this->title,
            'file_path' => $this->file_path,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'metadata' => $this->metadata,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at,
        ];
    }
}
