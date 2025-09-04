<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedTimePeriod extends Model
{
    protected $fillable = [
        'store_id',
        'blocked_date',
        'start_time',
        'end_time',
        'is_all_day',
        'reason',
        'is_recurring',
        'recurrence_pattern'
    ];

    protected $casts = [
        'blocked_date' => 'date',
        'is_all_day' => 'boolean',
        'is_recurring' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}