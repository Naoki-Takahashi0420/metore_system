<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LineSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'name', 
        'description',
        'type',
        'options',
        'category',
        'sort_order',
        'is_system',
    ];

    protected $casts = [
        'value' => 'array',
        'options' => 'array',
        'is_system' => 'boolean',
    ];

    /**
     * 設定値を取得（デフォルト値付き）
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        return $setting->getValue();
    }

    /**
     * 設定値をセット
     */
    public static function set(string $key, $value): void
    {
        $setting = static::where('key', $key)->first();
        
        if ($setting) {
            $setting->update(['value' => $value]);
        }
    }

    /**
     * 型に応じた値を取得
     */
    public function getValue()
    {
        if (is_null($this->value)) {
            return null;
        }

        return match($this->type) {
            'boolean' => (bool) ($this->value['enabled'] ?? false),
            'select' => $this->value['selected'] ?? null,
            'textarea', 'text' => $this->value['text'] ?? '',
            default => $this->value,
        };
    }

    /**
     * カテゴリ別設定取得
     */
    public static function getByCategory(string $category): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('category', $category)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();
    }
}
