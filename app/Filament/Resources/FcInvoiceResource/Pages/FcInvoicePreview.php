<?php

namespace App\Filament\Resources\FcInvoiceResource\Pages;

use App\Filament\Resources\FcInvoiceResource;
use App\Models\FcOrder;
use App\Models\FcOrderItem;
use App\Models\FcInvoice;
use App\Models\FcInvoiceItem;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class FcInvoicePreview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = FcInvoiceResource::class;

    protected static string $view = 'filament.resources.fc-invoice-resource.pages.fc-invoice-preview';

    protected static ?string $title = '請求書プレビュー（当月分）';
    
    protected static ?string $navigationLabel = '請求書プレビュー';
    
    protected static ?string $navigationIcon = 'heroicon-o-eye';

    public $selectedStoreId = null;
    public $previewData = [];
    public $month;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->loadPreviewData();
    }

    public function loadPreviewData(): void
    {
        $user = auth()->user();
        
        // 対象月の開始日と終了日
        $startDate = Carbon::parse($this->month)->startOfMonth();
        $endDate = Carbon::parse($this->month)->endOfMonth();

        // 権限チェック
        if ($user->hasRole('super_admin') || ($user->store && $user->store->isHeadquarters())) {
            // 本部: 全FC店舗の請求予定を表示
            $stores = Store::where('fc_type', 'fc_store')->get();
        } else if ($user->store && $user->store->isFcStore()) {
            // FC店舗: 自店舗のみ
            $stores = collect([$user->store]);
            $this->selectedStoreId = $user->store->id;
        } else {
            $stores = collect([]);
        }

        $this->previewData = [];

        foreach ($stores as $store) {
            // 該当月の納品済み発注を取得
            $orders = FcOrder::where('fc_store_id', $store->id)
                ->where('status', 'delivered')
                ->whereBetween('delivered_at', [$startDate, $endDate])
                ->with(['items.product'])
                ->get();

            if ($orders->isEmpty()) {
                continue;
            }

            $items = [];
            $subtotal = 0;
            $taxAmount = 0;

            // 発送済み商品の集計
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $shippedQty = $item->shipped_quantity ?? $item->quantity;
                    if ($shippedQty <= 0) continue;

                    $itemSubtotal = floatval($item->unit_price) * $shippedQty;
                    $itemTax = $itemSubtotal * 0.10;

                    $items[] = [
                        'description' => $item->product->name ?? $item->product_name,
                        'quantity' => $shippedQty,
                        'unit_price' => $item->unit_price,
                        'subtotal' => $itemSubtotal,
                        'tax_amount' => $itemTax,
                        'total' => $itemSubtotal + $itemTax,
                        'order_number' => $order->order_number,
                        'delivered_at' => $order->delivered_at->format('Y/m/d'),
                    ];

                    $subtotal += $itemSubtotal;
                    $taxAmount += $itemTax;
                }
            }

            // カスタム項目（ロイヤリティ等）
            $customItems = $this->getCustomItemsForStore($store);
            foreach ($customItems as $customItem) {
                $itemSubtotal = floatval($customItem['unit_price']) * intval($customItem['quantity']);
                $itemTax = $itemSubtotal * 0.10;

                $items[] = [
                    'description' => $customItem['description'],
                    'quantity' => $customItem['quantity'],
                    'unit_price' => $customItem['unit_price'],
                    'subtotal' => $itemSubtotal,
                    'tax_amount' => $itemTax,
                    'total' => $itemSubtotal + $itemTax,
                    'order_number' => null,
                    'delivered_at' => null,
                    'is_custom' => true,
                ];

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;
            }

            // 既に発行済みの請求書があるかチェック
            $existingInvoice = FcInvoice::where('fc_store_id', $store->id)
                ->whereBetween('billing_period_start', [$startDate, $endDate])
                ->first();

            $this->previewData[] = [
                'store' => $store,
                'items' => $items,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount,
                'has_existing_invoice' => $existingInvoice !== null,
                'existing_invoice' => $existingInvoice,
            ];
        }
    }

    protected function getCustomItemsForStore(Store $store): array
    {
        return [
            [
                'type' => FcInvoiceItem::TYPE_ROYALTY,
                'description' => 'ロイヤリティ（月額）',
                'quantity' => 1,
                'unit_price' => 50000,
            ],
            [
                'type' => FcInvoiceItem::TYPE_SYSTEM_FEE,
                'description' => 'システム使用料',
                'quantity' => 1,
                'unit_price' => 10000,
            ],
        ];
    }

    public function generateInvoice(int $storeId): void
    {
        $user = auth()->user();
        
        // 権限チェック
        if (!$user->hasRole('super_admin') && 
            !($user->store && $user->store->isHeadquarters())) {
            Notification::make()
                ->title('権限エラー')
                ->body('請求書の発行権限がありません')
                ->danger()
                ->send();
            return;
        }

        $storeData = collect($this->previewData)->firstWhere('store.id', $storeId);
        
        if (!$storeData) {
            Notification::make()
                ->title('エラー')
                ->body('店舗データが見つかりません')
                ->danger()
                ->send();
            return;
        }

        if ($storeData['has_existing_invoice']) {
            Notification::make()
                ->title('エラー')
                ->body('既に請求書が発行されています')
                ->warning()
                ->send();
            return;
        }

        try {
            // 請求書作成
            $invoice = FcInvoice::create([
                'invoice_number' => FcInvoice::generateInvoiceNumber(),
                'fc_store_id' => $storeId,
                'headquarters_store_id' => $user->store->id ?? Store::where('fc_type', 'headquarters')->first()->id,
                'status' => FcInvoice::STATUS_ISSUED,
                'billing_period_start' => Carbon::parse($this->month)->startOfMonth(),
                'billing_period_end' => Carbon::parse($this->month)->endOfMonth(),
                'issue_date' => now(),
                'due_date' => now()->addDays(30),
                'subtotal' => $storeData['subtotal'],
                'tax_amount' => $storeData['tax_amount'],
                'total_amount' => $storeData['total'],
                'paid_amount' => 0,
                'outstanding_amount' => $storeData['total'],
                'notes' => Carbon::parse($this->month)->format('Y年m月') . '分 請求書',
            ]);

            // 明細作成
            $sortOrder = 0;
            foreach ($storeData['items'] as $item) {
                FcInvoiceItem::create([
                    'fc_invoice_id' => $invoice->id,
                    'type' => $item['is_custom'] ?? false ? FcInvoiceItem::TYPE_CUSTOM : FcInvoiceItem::TYPE_PRODUCT,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'tax_rate' => 10.00,
                    'tax_amount' => $item['tax_amount'],
                    'total_amount' => $item['total'],
                    'notes' => $item['order_number'] ? "発注番号: {$item['order_number']} | 納品日: {$item['delivered_at']}" : null,
                    'sort_order' => $sortOrder++,
                ]);
            }

            Notification::make()
                ->title('請求書を発行しました')
                ->body("請求書番号: {$invoice->invoice_number}")
                ->success()
                ->send();

            // データ再読み込み
            $this->loadPreviewData();

        } catch (\Exception $e) {
            Notification::make()
                ->title('エラー')
                ->body('請求書の発行に失敗しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function updateMonth(): void
    {
        $this->loadPreviewData();
    }

    protected function getTableQuery(): Builder
    {
        // ダミークエリ（実際のデータは$previewDataから表示）
        return FcOrder::query()->where('id', -1);
    }

    protected function getTableColumns(): array
    {
        return [];
    }
}