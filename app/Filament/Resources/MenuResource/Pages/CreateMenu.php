<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\MenuCategory;

class CreateMenu extends CreateRecord
{
    protected static string $resource = MenuResource::class;

    public function mount(): void
    {
        parent::mount();
        
        // 管理者でない場合、自店舗のカテゴリをチェック
        if (!auth()->user()->hasRole('super_admin') && auth()->user()->store_id) {
            $hasCategories = MenuCategory::where('store_id', auth()->user()->store_id)
                ->where('is_active', true)
                ->exists();
                
            if (!$hasCategories) {
                Notification::make()
                    ->title('カテゴリーが必要です')
                    ->body('メニューを作成する前に、まずカテゴリーを作成してください。')
                    ->warning()
                    ->persistent()
                    ->send();
                    
                // カテゴリ作成ページへリダイレクト
                redirect()->route('filament.admin.resources.menu-categories.create');
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // store_idが設定されていない場合は、カテゴリから取得
        if (empty($data['store_id']) && !empty($data['category_id'])) {
            $category = MenuCategory::find($data['category_id']);
            if ($category) {
                $data['store_id'] = $category->store_id;
            }
        }
        
        return $data;
    }
}
