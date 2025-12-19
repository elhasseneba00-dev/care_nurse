<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareRequest extends Model
{
    protected $fillable = [
        'patient_user_id',
        'nurse_user_id',
        'care_type',
        'description',
        'scheduled_at',
        'address',
        'city',
        'lat',
        'lng',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function nurse(): BelongsTo{
        return $this->belongsTo(User::class, 'nurse_user_id');
    }
}
