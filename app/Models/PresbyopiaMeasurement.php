<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresbyopiaMeasurement extends Model
{
    protected $fillable = [
        'medical_record_id',
        'status',
        'a_95_left',
        'a_95_right',
        'b_50_left',
        'b_50_right',
        'c_25_left',
        'c_25_right',
        'd_12_left',
        'd_12_right',
        'e_6_left',
        'e_6_right',
    ];

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }
}
