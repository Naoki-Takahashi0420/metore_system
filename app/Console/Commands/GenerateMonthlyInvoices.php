<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FcOrder;
use App\Models\FcInvoice;
use App\Models\FcInvoiceItem;
use App\Models\Store;
use App\Services\FcNotificationService;
use Carbon\Carbon;

class GenerateMonthlyInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fc:generate-monthly-invoices 
                           {--month= : 対象月 (YYYY-MM形式、省略時は前月)}
                           {--dry-run : 実行せずに結果をプレビューのみ}
                           {--force : 既存の請求書がある場合も再生成}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '月初に前月の納品完了分から請求書を一括生成';

    protected FcNotificationService $notificationService;

    public function __construct(FcNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $monthOption = $this->option('month');
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        // 対象月を決定
        if ($monthOption) {
            try {
                $targetMonth = Carbon::createFromFormat('Y-m', $monthOption)->startOfMonth();
            } catch (\Exception $e) {
                $this->error("無効な月形式です。YYYY-MM形式で入力してください。");
                return self::FAILURE;
            }
        } else {
            $targetMonth = Carbon::now()->subMonth()->startOfMonth();
        }

        $this->info("📋 {$targetMonth->format('Y年m月')}の請求書生成を開始します");

        if ($isDryRun) {
            $this->info("🔍 [DRY RUN] 実際の生成は行いません");
        }

        // 対象の納品完了注文を取得
        $orders = FcOrder::getOrdersForMonthlyInvoicing($targetMonth);
        
        if ($orders->isEmpty()) {
            $this->info("✅ 対象月に納品完了した注文がありません");
            return self::SUCCESS;
        }

        $this->info("📦 対象注文: {$orders->count()}件");

        // 店舗ごとにグループ化
        $ordersByStore = $orders->groupBy('fc_store_id');

        $generatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($ordersByStore as $storeId => $storeOrders) {
            $store = Store::find($storeId);
            if (!$store) {
                $this->error("店舗ID {$storeId} が見つかりません");
                continue;
            }

            $this->info("🏪 {$store->name} ({$storeOrders->count()}件)");

            // 既存請求書チェック
            if (!$isForce) {
                $existingInvoice = FcInvoice::where('fc_store_id', $storeId)
                    ->where('billing_period_start', '>=', $targetMonth->startOfMonth())
                    ->where('billing_period_end', '<=', $targetMonth->endOfMonth())
                    ->first();

                if ($existingInvoice) {
                    $this->warn("   ⚠️  既に請求書 {$existingInvoice->invoice_number} が存在するためスキップ");
                    $skippedCount++;
                    continue;
                }
            }

            if (!$isDryRun) {
                try {
                    $invoice = $this->generateInvoiceForStore($store, $storeOrders, $targetMonth);
                    $this->info("   ✅ 請求書 {$invoice->invoice_number} を生成 (¥" . number_format($invoice->total_amount) . ")");
                    $generatedCount++;

                    // 通知送信
                    try {
                        $this->notificationService->notifyMonthlyInvoiceGenerated($invoice);
                        $this->info("   📧 通知を送信しました");
                    } catch (\Exception $e) {
                        $this->warn("   ⚠️  通知送信エラー: " . $e->getMessage());
                    }

                } catch (\Exception $e) {
                    $this->error("   ❌ 請求書生成エラー: " . $e->getMessage());
                    $errorCount++;
                }
            } else {
                // DRY RUN: 計算のみ
                $totalAmount = $storeOrders->sum('total_amount');
                $this->info("   📊 生成予定請求額: ¥" . number_format($totalAmount));
                $generatedCount++;
            }
        }

        // サマリー表示
        $this->info("\n📊 実行結果サマリー");
        $this->info("  生成: {$generatedCount}件");
        if ($skippedCount > 0) {
            $this->info("  スキップ: {$skippedCount}件");
        }
        if ($errorCount > 0) {
            $this->info("  エラー: {$errorCount}件");
        }

        if ($isDryRun) {
            $this->info("\n実際に生成するには --dry-run オプションを外して実行してください");
        }

        return self::SUCCESS;
    }

    protected function generateInvoiceForStore(Store $store, $orders, Carbon $targetMonth): FcInvoice
    {
        // 本部店舗を取得（最初の注文から）
        $firstOrder = $orders->first();
        $headquartersStore = $firstOrder->headquartersStore;

        // 請求書作成
        $invoice = FcInvoice::create([
            'invoice_number' => FcInvoice::generateInvoiceNumber(),
            'fc_store_id' => $store->id,
            'headquarters_store_id' => $headquartersStore->id,
            'status' => FcInvoice::STATUS_ISSUED, // 即発行
            'billing_period_start' => $targetMonth->startOfMonth(),
            'billing_period_end' => $targetMonth->copy()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 0, // 後で計算
            'tax_amount' => 0, // 後で計算
            'total_amount' => 0, // 後で計算
            'paid_amount' => 0,
            'outstanding_amount' => 0, // 後で計算
            'notes' => "{$targetMonth->format('Y年m月')}分 納品商品代金",
        ]);

        $sortOrder = 0;
        $totalSubtotal = 0;
        $totalTaxAmount = 0;

        // 注文ごとに明細を作成（発送済み数量のみを請求対象）
        foreach ($orders as $order) {
            foreach ($order->items as $orderItem) {
                // 発送済み数量がない場合はスキップ
                $shippedQty = $orderItem->shipped_quantity ?? $orderItem->quantity; // 互換性のため、未設定時は全量発送扱い
                if ($shippedQty <= 0) {
                    continue;
                }

                // 発送済み数量に基づいて金額を計算
                $unitPrice = floatval($orderItem->unit_price);
                $taxRate = floatval($orderItem->tax_rate ?? 10.00);
                $subtotal = $unitPrice * $shippedQty;
                $taxAmount = $subtotal * ($taxRate / 100);
                $totalAmount = $subtotal + $taxAmount;

                $notes = "納品日: {$order->delivered_at->format('Y/m/d')} | 発注番号: {$order->order_number}";
                
                // 部分発送の場合は備考に記載
                if ($shippedQty < $orderItem->quantity) {
                    $notes .= " | 部分発送 ({$shippedQty}/{$orderItem->quantity})";
                }

                $invoiceItem = FcInvoiceItem::create([
                    'fc_invoice_id' => $invoice->id,
                    'type' => FcInvoiceItem::TYPE_PRODUCT,
                    'fc_product_id' => $orderItem->fc_product_id,
                    'description' => $orderItem->product->name ?? $orderItem->product_name,
                    'quantity' => $shippedQty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0,
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                    'notes' => $notes,
                    'sort_order' => $sortOrder++,
                ]);

                $totalSubtotal += $subtotal;
                $totalTaxAmount += $taxAmount;
            }
        }

        // カスタム項目の自動追加（ロイヤリティ、システム使用料など）
        $customItems = $this->getCustomItemsForStore($store);
        foreach ($customItems as $customItem) {
            $subtotal = floatval($customItem['unit_price']) * intval($customItem['quantity']);
            $taxAmount = $subtotal * 0.10; // 10%消費税
            $totalAmount = $subtotal + $taxAmount;

            FcInvoiceItem::create([
                'fc_invoice_id' => $invoice->id,
                'type' => $customItem['type'],
                'fc_product_id' => null,
                'description' => $customItem['description'],
                'quantity' => $customItem['quantity'],
                'unit_price' => $customItem['unit_price'],
                'discount_amount' => 0,
                'subtotal' => $subtotal,
                'tax_rate' => 10.00,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'notes' => $customItem['notes'] ?? null,
                'sort_order' => $sortOrder++,
            ]);

            $totalSubtotal += $subtotal;
            $totalTaxAmount += $taxAmount;
        }

        // 請求書合計を更新
        $totalAmount = $totalSubtotal + $totalTaxAmount;
        $invoice->update([
            'subtotal' => $totalSubtotal,
            'tax_amount' => $totalTaxAmount,
            'total_amount' => $totalAmount,
            'outstanding_amount' => $totalAmount,
        ]);

        return $invoice;
    }

    /**
     * 店舗のカスタム項目テンプレートを取得
     * 
     * @param Store $store
     * @return array
     */
    protected function getCustomItemsForStore(Store $store): array
    {
        $customItems = [];

        // FC加盟店の基本料金設定
        // 実際の運用では、storesテーブルにfc_settingsカラムを追加して
        // 店舗ごとの設定を保持するのが理想的
        
        // ロイヤリティ（月額固定）
        $customItems[] = [
            'type' => FcInvoiceItem::TYPE_ROYALTY,
            'description' => 'ロイヤリティ（月額）',
            'quantity' => 1,
            'unit_price' => 50000, // ¥50,000
            'notes' => '月額固定費用',
        ];

        // システム使用料
        $customItems[] = [
            'type' => FcInvoiceItem::TYPE_SYSTEM_FEE,
            'description' => 'システム使用料',
            'quantity' => 1,
            'unit_price' => 10000, // ¥10,000
            'notes' => '予約管理システム利用料',
        ];

        // 店舗ごとのカスタム設定があれば追加
        // 将来的には以下のような実装が望ましい：
        // if ($store->fc_settings) {
        //     $settings = json_decode($store->fc_settings, true);
        //     if (isset($settings['monthly_items'])) {
        //         foreach ($settings['monthly_items'] as $item) {
        //             $customItems[] = $item;
        //         }
        //     }
        // }

        // 環境変数や設定ファイルから読み込む場合の例：
        // $configItems = config('fc.monthly_invoice_items.' . $store->id, []);
        // $customItems = array_merge($customItems, $configItems);

        return $customItems;
    }
}