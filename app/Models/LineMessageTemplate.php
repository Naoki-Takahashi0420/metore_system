<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineMessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'message',
        'variables',
        'store_id',
        'is_active',
        'category',
        'description',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * リレーション：店舗
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 変数を置き換えてメッセージを生成
     */
    public function generateMessage(array $variables = []): string
    {
        $message = $this->message;
        
        foreach ($variables as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        
        return $message;
    }

    /**
     * カテゴリ別テンプレート取得
     */
    public static function getByCategory(string $category, ?int $storeId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::where('category', $category)
                      ->where('is_active', true);
                      
        if ($storeId) {
            $query->where(function($q) use ($storeId) {
                $q->where('store_id', $storeId)
                  ->orWhereNull('store_id');
            });
        } else {
            $query->whereNull('store_id');
        }
        
        return $query->orderBy('name')->get();
    }

    /**
     * キーでテンプレート取得（店舗別優先）
     */
    public static function getByKey(string $key, ?int $storeId = null): ?self
    {
        if ($storeId) {
            // 店舗別設定を優先
            $template = static::where('key', $key)
                             ->where('store_id', $storeId)
                             ->where('is_active', true)
                             ->first();
            
            if ($template) {
                return $template;
            }
        }
        
        // 全体設定をフォールバック
        return static::where('key', $key)
                    ->whereNull('store_id')
                    ->where('is_active', true)
                    ->first();
    }

    /**
     * カテゴリのラベル
     */
    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'general' => '一般',
            'reminder' => 'リマインダー',
            'campaign' => 'キャンペーン',
            'auto_reply' => '自動返信',
            'welcome' => 'ウェルカム',
            default => 'その他',
        };
    }
}
