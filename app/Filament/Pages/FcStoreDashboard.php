<?php

namespace App\Filament\Pages;

use App\Models\FcOrder;
use App\Models\FcInvoice;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class FcStoreDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'FC店舗ホーム';

    protected static ?string $title = 'FC店舗ダッシュボード';

    protected static ?string $navigationGroup = 'FC本部管理';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.fc-store-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // FC加盟店のユーザーのみ表示
        return $user->store?->isFcStore() ?? false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        // super_adminまたはFC加盟店のユーザーのみアクセス可能
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->store?->isFcStore() ?? false;
    }

    public function getHeading(): string|Htmlable
    {
        $storeName = auth()->user()->store?->name ?? 'FC店舗';
        return $storeName . ' ダッシュボード';
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $storeId = $user->store_id;

        // 発注データ（最新10件）
        $orders = FcOrder::where('fc_store_id', $storeId)
            ->with(['items', 'fcStore'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // 請求書データ（発行済み以降のみ表示、最新10件）
        $invoices = FcInvoice::where('fc_store_id', $storeId)
            ->whereIn('status', ['issued', 'sent', 'paid'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // 未払い請求書の合計
        $unpaidTotal = FcInvoice::where('fc_store_id', $storeId)
            ->whereIn('status', ['issued', 'sent'])
            ->sum('outstanding_amount');

        // 進行中の発注数
        $pendingOrders = FcOrder::where('fc_store_id', $storeId)
            ->whereIn('status', ['ordered', 'shipped'])
            ->count();

        return [
            'orders' => $orders,
            'invoices' => $invoices,
            'unpaidTotal' => $unpaidTotal,
            'pendingOrders' => $pendingOrders,
        ];
    }

    public static function getOrderStatusSteps(): array
    {
        return [
            'draft' => ['label' => '下書き', 'icon' => 'pencil'],
            'ordered' => ['label' => '発注済み', 'icon' => 'paper-airplane'],
            'shipped' => ['label' => '発送済み', 'icon' => 'truck'],
            'delivered' => ['label' => '納品完了', 'icon' => 'check-circle'],
        ];
    }

    public static function getInvoiceStatusSteps(): array
    {
        return [
            'draft' => ['label' => '作成中', 'icon' => 'pencil'],
            'issued' => ['label' => '発行済み', 'icon' => 'document-text'],
            'paid' => ['label' => '入金完了', 'icon' => 'check-circle'],
        ];
    }
}
