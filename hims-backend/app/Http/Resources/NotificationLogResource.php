<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_code' => $this->template_code,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'patient_id' => $this->patient_id,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'attempts' => $this->attempts,
            'last_attempted_at' => $this->last_attempted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
