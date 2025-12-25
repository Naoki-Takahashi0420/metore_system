<?php

namespace App\Filament\Pages;

use App\Models\FcOrder;
use App\Models\FcInvoice;
use App\Models\Store;
use App\Services\FcNotificationService;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class FcStoreDashboard extends Page
{
    protected $listeners = ['refreshDashboard' => '$refresh'];
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

        // super_adminまたはFC加盟店のユーザーに表示
        if ($user->hasRole('super_admin')) {
            return true;
        }

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
        $user = auth()->user();
        if ($user->hasRole('super_admin')) {
            return 'FC全店舗ダッシュボード';
        }
        $storeName = $user->store?->name ?? 'FC店舗';
        return $storeName . ' ダッシュボード';
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        if ($isSuperAdmin) {
            // super_adminは全FC店舗のデータを取得
            return $this->getAllStoresData();
        }

        // 通常のFC店舗ユーザー
        return $this->getSingleStoreData($user->store_id);
    }

    /**
     * 全FC店舗のデータを取得（super_admin用）
     */
    protected function getAllStoresData(): array
    {
        // 全FC店舗を取得
        $fcStores = Store::where('fc_type', 'fc_store')
            ->orderBy('name')
            ->get();

        $storesData = [];
        $totalUnpaid = 0;
        $totalPendingOrders = 0;

        foreach ($fcStores as $store) {
            $storeData = $this->getSingleStoreData($store->id);
            $storeData['store'] = $store;

            $storesData[] = $storeData;
            $totalUnpaid += $storeData['unpaidTotal'];
            $totalPendingOrders += $storeData['pendingOrders'];
        }

        return [
            'isSuperAdmin' => true,
            'storesData' => $storesData,
            'unpaidTotal' => $totalUnpaid,
            'pendingOrders' => $totalPendingOrders,
            // 互換性のため（空のコレクション）
            'orders' => collect([]),
            'invoices' => collect([]),
        ];
    }

    /**
     * 単一店舗のデータを取得
     */
    protected function getSingleStoreData(?int $storeId): array
    {
        if (!$storeId) {
            return [
                'isSuperAdmin' => false,
                'orders' => collect([]),
                'invoices' => collect([]),
                'unpaidTotal' => 0,
                'pendingOrders' => 0,
            ];
        }

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
            'isSuperAdmin' => false,
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

    /**
     * 発注ステータスを変更
     */
    public function updateOrderStatus(int $orderId, string $status): void
    {
        // super_adminのみ実行可能
        if (!auth()->user()->hasRole('super_admin')) {
            Notification::make()
                ->danger()
                ->title('権限がありません')
                ->send();
            return;
        }

        $order = FcOrder::find($orderId);
        if (!$order) {
            Notification::make()
                ->danger()
                ->title('発注が見つかりません')
                ->send();
            return;
        }

        $statusLabels = [
            'shipped' => '発送済み',
            'delivered' => '納品完了',
        ];

        $order->update([
            'status' => $status,
            $status . '_at' => now(),
        ]);

        // FC店舗へ通知を送信
        try {
            $notificationService = app(FcNotificationService::class);
            if ($status === 'shipped') {
                $notificationService->notifyOrderShipped($order);
            } elseif ($status === 'delivered') {
                $notificationService->notifyOrderDelivered($order);
            }
        } catch (\Exception $e) {
            \Log::error("FC発注ステータス通知エラー: " . $e->getMessage());
        }

        Notification::make()
            ->success()
            ->title($order->order_number . ' を「' . ($statusLabels[$status] ?? $status) . '」に更新しました')
            ->send();
    }

    /**
     * 月次請求書を生成（全店舗）
     */
    public function generateMonthlyInvoices(): void
    {
        // super_adminのみ実行可能
        if (!auth()->user()->hasRole('super_admin')) {
            Notification::make()
                ->danger()
                ->title('権限がありません')
                ->send();
            return;
        }

        $result = FcInvoice::generateMonthlyInvoicesForAllStores();

        $createdCount = count($result['created']);
        $skippedCount = count($result['skipped']);

        if ($createdCount > 0) {
            $storeNames = collect($result['created'])->pluck('store_name')->join('、');
            Notification::make()
                ->success()
                ->title("月次請求書を{$createdCount}件生成しました")
                ->body("店舗: {$storeNames}")
                ->send();
        } else {
            Notification::make()
                ->warning()
                ->title('生成対象がありませんでした')
                ->body('未請求の納品済み発注がありません')
                ->send();
        }
    }

    /**
     * 特定店舗の月次請求書を生成
     */
    public function generateInvoiceForStore(int $storeId): void
    {
        // super_adminのみ実行可能
        if (!auth()->user()->hasRole('super_admin')) {
            Notification::make()
                ->danger()
                ->title('権限がありません')
                ->send();
            return;
        }

        $store = Store::find($storeId);
        if (!$store) {
            Notification::make()
                ->danger()
                ->title('店舗が見つかりません')
                ->send();
            return;
        }

        $invoice = FcInvoice::createMonthlyInvoice($store);

        if ($invoice) {
            Notification::make()
                ->success()
                ->title("{$store->name}の請求書を生成しました")
                ->body("請求書番号: {$invoice->invoice_number}")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('edit')
                        ->label('編集してロイヤリティを追加')
                        ->url(route('filament.admin.resources.fc-invoices.edit', $invoice))
                ])
                ->send();
        } else {
            Notification::make()
                ->warning()
                ->title('生成対象がありません')
                ->body("{$store->name}に未請求の納品済み発注がありません")
                ->send();
        }
    }

    /**
     * 請求書ステータスを変更
     */
    public function updateInvoiceStatus(int $invoiceId, string $status): void
    {
        // super_adminのみ実行可能
        if (!auth()->user()->hasRole('super_admin')) {
            Notification::make()
                ->danger()
                ->title('権限がありません')
                ->send();
            return;
        }

        $invoice = FcInvoice::find($invoiceId);
        if (!$invoice) {
            Notification::make()
                ->danger()
                ->title('請求書が見つかりません')
                ->send();
            return;
        }

        $statusLabels = [
            'issued' => '発行済み',
            'sent' => '送付済み',
            'paid' => '入金完了',
        ];

        $updateData = ['status' => $status];

        if ($status === 'paid') {
            $updateData['paid_at'] = now();
            $updateData['paid_amount'] = $invoice->total_amount;
            $updateData['outstanding_amount'] = 0;
        }

        $invoice->update($updateData);

        Notification::make()
            ->success()
            ->title($invoice->invoice_number . ' を「' . ($statusLabels[$status] ?? $status) . '」に更新しました')
            ->send();
    }
}
