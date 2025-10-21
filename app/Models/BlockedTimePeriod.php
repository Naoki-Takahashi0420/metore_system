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
        'recurrence_pattern',
        'line_type',
        'line_number',
        'staff_id',
        'created_by'
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

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}