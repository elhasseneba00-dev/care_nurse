<?php

namespace App\Http\Requests\V1\Care;

use Illuminate\Foundation\Http\FormRequest;

class StoreCareRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // If you want patient to pick a specific nurse at creation time
            //'nurse_user_id' => ['nullable', 'integer', 'exists:users,id'],

            // Open request: no nurse_user_id here
            'care_type' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            // ISO 8601 expected. ex: "2025-12-20T10:00:00+00:00"
            'scheduled_at' => ['nullable', 'date'],
            'address' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:80'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
