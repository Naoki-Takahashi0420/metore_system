<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\HasShiftPermissions;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, HasShiftPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'store_id',
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'specialties',
        'hourly_rate',
        'is_active',
        'last_login_at',
        'phone',
        'notification_preferences',
        'theme_color',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'permissions' => 'array',
        'specialties' => 'array',
        'hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'notification_preferences' => 'array',
    ];

    /**
     * Filamentパネルへのアクセス可否
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // 無効なユーザーはログイン不可
        return $this->is_active;
    }

    /**
     * 利用可能な色のリスト
     */
    protected static $availableColors = [
        '#ef4444', // 赤
        '#f97316', // オレンジ
        '#eab308', // 黄
        '#84cc16', // ライム
        '#22c55e', // 緑
        '#14b8a6', // ティール
        '#06b6d4', // シアン
        '#3b82f6', // 青
        '#6366f1', // インディゴ
        '#8b5cf6', // バイオレット
        '#a855f7', // パープル
        '#d946ef', // フクシア
        '#ec4899', // ピンク
        '#f43f5e', // ローズ
    ];

    /**
     * モデルの初期化処理
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            // theme_colorが設定されていない場合、自動的に割り当て
            if (empty($user->theme_color)) {
                $user->theme_color = static::getNextAvailableColor();
            }
        });
    }

    /**
     * 次に利用可能な色を取得
     */
    protected static function getNextAvailableColor(): string
    {
        // 既存のユーザーが使用している色を取得
        $usedColors = static::whereNotNull('theme_color')
            ->pluck('theme_color')
            ->toArray();

        // まだ使われていない色を探す
        $availableColors = array_diff(static::$availableColors, $usedColors);

        if (!empty($availableColors)) {
            // 使われていない色から最初のものを返す
            return array_values($availableColors)[0];
        }

        // すべての色が使用されている場合、ランダムに選択
        return static::$availableColors[array_rand(static::$availableColors)];
    }

    /**
     * リレーション: 店舗
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * リレーション: 予約（担当）
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'staff_id');
    }

    /**
     * リレーション: シフト
     */
    public function shiftSchedules()
    {
        return $this->hasMany(ShiftSchedule::class, 'staff_id');
    }

    /**
     * リレーション: カルテ
     */
    public function medicalRecords()
    {
        return $this->hasMany(MedicalRecord::class, 'staff_id');
    }

    /**
     * リレーション: 管理可能店舗
     */
    public function manageableStores()
    {
        return $this->belongsToMany(Store::class, 'store_managers')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * リレーション: 所有店舗（オーナーのみ）
     */
    public function ownedStores()
    {
        return $this->belongsToMany(Store::class, 'store_managers')
                    ->wherePivot('role', 'owner')
                    ->withTimestamps();
    }

    /**
     * 特定の店舗を管理できるかチェック
     */
    public function canManageStore($storeId): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->manageableStores()->where('stores.id', $storeId)->exists();
    }
}