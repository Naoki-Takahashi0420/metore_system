<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'content',
        'priority',
        'target_type',
        'published_at',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * お知らせを作成したユーザー
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 対象店舗（多対多）
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'announcement_store');
    }

    /**
     * 既読レコード
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * 特定のユーザーが既読済みかチェック
     */
    public function isReadBy($userId): bool
    {
        return $this->reads()->where('user_id', $userId)->exists();
    }

    /**
     * スコープ: 公開中のお知らせのみ
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('published_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * スコープ: ログインユーザーに関係するお知らせ
     */
    public function scopeForUser($query, $user)
    {
        if (!$user) {
            return $query->where('target_type', 'all');
        }

        // super_adminは全てのお知らせを閲覧可能
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // 店舗IDがない一般ユーザーは「全店舗」対象のみ
        if (!$user->store_id) {
            return $query->where('target_type', 'all');
        }

        // 店舗ユーザーは「全店舗」または「自分の店舗」対象のお知らせ
        return $query->where(function ($q) use ($user) {
            $q->where('target_type', 'all')
              ->orWhereHas('stores', function ($storeQuery) use ($user) {
                  $storeQuery->where('stores.id', $user->store_id);
              });
        });
    }
}
