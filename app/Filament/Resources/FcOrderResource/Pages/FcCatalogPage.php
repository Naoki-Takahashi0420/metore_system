<?php

namespace App\Filament\Resources\FcOrderResource\Pages;

use App\Filament\Resources\FcOrderResource;
use Filament\Resources\Pages\Page;

class FcCatalogPage extends Page
{
    protected static string $resource = FcOrderResource::class;

    protected static string $view = 'filament.resources.fc-order-resource.pages.fc-catalog-page';

    protected static ?string $title = '商品カタログ';

    protected static ?string $navigationLabel = '商品カタログ';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = -1; // 一番上に表示

    protected static ?string $navigationGroup = 'FC本部管理';

    public static function getNavigationLabel(): string
    {
        return '商品カタログ';
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // super_adminはナビゲーションに表示
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // FC加盟店のみナビゲーションに表示
        return $user->store?->isFcStore() ?? false;
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // FC加盟店ならアクセス可能
        if ($user->store && $user->store->isFcStore()) {
            return true;
        }

        // super_adminもアクセス可能（テスト用）
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return false;
    }
}
