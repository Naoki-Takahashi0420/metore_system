<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CustomerLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'label_key',
        'label_name',
        'assigned_at',
        'auto_assigned',
        'expires_at',
        'metadata',
        'reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_assigned' => 'boolean',
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 顧客に自動ラベル付与
     */
    public static function assignAutoLabel(Customer $customer, string $labelKey, array $metadata = []): self
    {
        $labelDefinitions = self::getLabelDefinitions();
        $definition = $labelDefinitions[$labelKey] ?? null;
        
        if (!$definition) {
            throw new \InvalidArgumentException("Unknown label key: {$labelKey}");
        }

        // 既存ラベルがあれば更新、なければ作成
        return static::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'label_key' => $labelKey
            ],
            [
                'label_name' => $definition['name'],
                'assigned_at' => now(),
                'auto_assigned' => true,
                'expires_at' => $definition['expires_after'] ? now()->add($definition['expires_after']) : null,
                'metadata' => $metadata,
                'reason' => $definition['auto_reason'] ?? '自動判定'
            ]
        );
    }

    /**
     * 顧客の行動パターンを分析してラベル自動付与
     */
    public static function analyzeAndAssignLabels(Customer $customer): array
    {
        $assignedLabels = [];
        $reservations = $customer->reservations()->orderBy('created_at', 'desc')->get();
        $totalReservations = $reservations->count();
        
        // 初回顧客判定
        if ($totalReservations === 1) {
            $assignedLabels[] = self::assignAutoLabel($customer, 'first_timer');
        }
        
        // ノーショー分析
        $noShowCount = $reservations->where('status', 'no_show')->count();
        if ($noShowCount === 1) {
            $assignedLabels[] = self::assignAutoLabel($customer, 'no_show_once');
        } elseif ($noShowCount >= 2) {
            $assignedLabels[] = self::assignAutoLabel($customer, 'no_show_repeat');
        }
        
        // 来店頻度分析
        if ($totalReservations >= 2) {
            $lastReservation = $reservations->where('status', 'completed')->first();
            if ($lastReservation && $lastReservation->created_at->diffInMonths() >= 3) {
                $assignedLabels[] = self::assignAutoLabel($customer, 'long_absence');
            }
            
            // 月2回以上なら常連
            $monthlyAverage = $totalReservations / max(1, $customer->created_at->diffInMonths(now()));
            if ($monthlyAverage >= 2) {
                $assignedLabels[] = self::assignAutoLabel($customer, 'regular');
            } elseif ($monthlyAverage < 1) {
                $assignedLabels[] = self::assignAutoLabel($customer, 'irregular');
            }
        }
        
        return $assignedLabels;
    }

    /**
     * ラベル定義を取得
     */
    public static function getLabelDefinitions(): array
    {
        return [
            'first_timer' => [
                'name' => '初回顧客',
                'description' => '初回予約の顧客',
                'expires_after' => '30 days',
                'auto_reason' => '初回予約を検出'
            ],
            'regular' => [
                'name' => '常連顧客',
                'description' => '月2回以上利用',
                'expires_after' => null,
                'auto_reason' => '高頻度利用を検出'
            ],
            'irregular' => [
                'name' => '不定期顧客',
                'description' => '月1回以下の利用',
                'expires_after' => null,
                'auto_reason' => '低頻度利用を検出'
            ],
            'no_show_once' => [
                'name' => '1回ノーショー',
                'description' => '1度無断キャンセルした顧客',
                'expires_after' => '60 days',
                'auto_reason' => 'ノーショー履歴を検出'
            ],
            'no_show_repeat' => [
                'name' => '複数回ノーショー',
                'description' => '複数回無断キャンセルした顧客',
                'expires_after' => null,
                'auto_reason' => '複数回ノーショーを検出'
            ],
            'long_absence' => [
                'name' => '長期未来店',
                'description' => '3ヶ月以上来店していない顧客',
                'expires_after' => '30 days',
                'auto_reason' => '長期間の非来店を検出'
            ],
            'vip' => [
                'name' => 'VIP顧客',
                'description' => '特別優遇顧客',
                'expires_after' => null,
                'auto_reason' => '手動設定'
            ],
            'price_sensitive' => [
                'name' => '価格重視顧客',
                'description' => 'キャンペーン時のみ利用',
                'expires_after' => null,
                'auto_reason' => 'キャンペーン利用パターンを検出'
            ],
        ];
    }

    /**
     * 有効なラベルのみ取得
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * ラベルが有効かチェック
     */
    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}