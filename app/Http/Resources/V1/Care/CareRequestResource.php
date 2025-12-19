<?php

namespace App\Http\Resources\V1\Care;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CareRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_user_id' => $this->patient_user_id,
            'nurse_user_id' => $this->nurse_user_id,
            'care_type' => $this->care_type,
            'description' => $this->description,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'address' => $this->address,
            'city' => $this->city,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => $this->status,
            // present only when selected in query
            'distance_km' => isset($this->distance_km) ? round((float) $this->distance_km, 2) : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
