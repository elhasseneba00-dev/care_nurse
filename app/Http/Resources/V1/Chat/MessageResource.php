<?php

namespace App\Http\Resources\V1\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'care_request_id' => $this->care_request_id,
            'sender_user_id' => $this->sender_user_id,
            'message' => $this->message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
