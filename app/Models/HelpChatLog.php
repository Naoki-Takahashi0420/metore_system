<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpChatLog extends Model
{
    protected $fillable = [
        'user_id',
        'page_name',
        'question',
        'answer',
        'is_resolved',
        'feedback',
        'context',
        'usage',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'context' => 'array',
        'usage' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
