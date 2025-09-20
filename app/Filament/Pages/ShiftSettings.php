<?php

namespace App\Filament\Pages;

use App\Models\Store;
use Filament\Pages\Page;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ShiftSettings extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'シフト設定';
    protected static ?string $title = 'シフト設定';
    protected static ?string $navigationGroup = 'スタッフ管理';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.shift-settings';
    
    public $stores;
    public $selectedStore;
    public $shiftTemplates = [];
    
    public static function canAccess(): bool
    {
        $user = Auth::user();
        // スタッフはアクセス不可
        return $user->hasRole(['super_admin', 'owner', 'manager']);
    }
    
    public function mount(): void
    {
        $user = Auth::user();
        
        // アクセス可能な店舗を取得
        if ($user->hasRole('super_admin')) {
            $this->stores = Store::where('is_active', true)->get();
        } elseif ($user->hasRole('owner')) {
            $this->stores = $user->manageableStores()->get();
        } else {
            // 店長・スタッフは所属店舗のみ
            $this->stores = $user->store ? collect([$user->store]) : collect();
        }
        
        $this->selectedStore = $this->stores->first()?->id;
        
        if ($this->selectedStore) {
            $this->loadShiftTemplates();
        }
    }
    
    public function changeStore(): void
    {
        $this->loadShiftTemplates();
    }
    
    public function loadShiftTemplates(): void
    {
        if (!$this->selectedStore) return;
        
        $store = Store::find($this->selectedStore);
        
        // 既存のテンプレートを読み込む（store.settingsから）
        $settings = $store->settings ?? [];
        $this->shiftTemplates = $settings['shift_templates'] ?? [
            // デフォルトテンプレート
            ['name' => '早番', 'start_time' => '09:00', 'end_time' => '14:00'],
            ['name' => '遅番', 'start_time' => '14:00', 'end_time' => '21:00'],
            ['name' => '通常', 'start_time' => '10:00', 'end_time' => '19:00'],
            ['name' => '短時間', 'start_time' => '10:00', 'end_time' => '15:00'],
        ];
    }
    
    public function addTemplate(): void
    {
        $this->shiftTemplates[] = [
            'name' => '',
            'start_time' => '09:00',
            'end_time' => '18:00'
        ];
    }
    
    public function removeTemplate($index): void
    {
        unset($this->shiftTemplates[$index]);
        $this->shiftTemplates = array_values($this->shiftTemplates);
    }
    
    public function save(): void
    {
        if (!$this->selectedStore) {
            Notification::make()
                ->title('エラー')
                ->body('店舗を選択してください')
                ->danger()
                ->send();
            return;
        }
        
        $user = Auth::user();
        $store = Store::find($this->selectedStore);
        
        // 権限チェック
        if ($user->hasRole(['manager', 'staff'])) {
            // 店長・スタッフは所属店舗のみ編集可能
            if ($user->store_id != $this->selectedStore) {
                Notification::make()
                    ->title('エラー')
                    ->body('権限がありません')
                    ->danger()
                    ->send();
                return;
            }
        } elseif ($user->hasRole('owner')) {
            // オーナーは管理可能店舗のみ編集可能
            if (!$user->manageableStores()->where('stores.id', $this->selectedStore)->exists()) {
                Notification::make()
                    ->title('エラー')
                    ->body('権限がありません')
                    ->danger()
                    ->send();
                return;
            }
        }
        
        // バリデーション
        foreach ($this->shiftTemplates as $index => $template) {
            if (empty($template['name'])) {
                Notification::make()
                    ->title('エラー')
                    ->body('テンプレート名を入力してください')
                    ->danger()
                    ->send();
                return;
            }
            
            if (empty($template['start_time']) || empty($template['end_time'])) {
                Notification::make()
                    ->title('エラー')
                    ->body('開始時間と終了時間を入力してください')
                    ->danger()
                    ->send();
                return;
            }
        }
        
        $settings = $store->settings ?? [];
        $settings['shift_templates'] = $this->shiftTemplates;
        
        $store->update(['settings' => $settings]);
        
        Notification::make()
            ->title('保存完了')
            ->body($store->name . 'のシフトテンプレートを保存しました')
            ->success()
            ->send();
    }
}