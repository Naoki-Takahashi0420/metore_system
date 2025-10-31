<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\Sale;
use App\Models\DailyClosing as DailyClosingModel;
use App\Models\Reservation;
use App\Models\CustomerTicket;
use Filament\Pages\Page;  // Filament\Resources\Pages\Page ではなく Filament\Pages\Page を使用
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class DailyClosing extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.resources.sale-resource.pages.daily-closing';

    protected static ?string $title = '日次精算';

    // ルート設定
    protected static string $routePath = 'sales/daily-closing';

    // サイドバーナビゲーション設定
    protected static bool $shouldRegisterNavigation = true;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = '日次精算';
    protected static ?string $navigationGroup = '売上・会計';
    protected static ?int $navigationSort = 2;

    /**
     * ナビゲーション表示の権限チェック
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && !$user->hasRole('staff');
    }

    public $closingDate;
    public $selectedStoreId; // 選択された店舗ID
    public $openingCash = 50000; // デフォルト釣銭準備金
    public $actualCash;
    public $notes;

    public $salesData = [];
    public $unposted = []; // 未計上予約のDTO配列
    public $rowState = []; // 各行のpayment_methodやoverride_source/amountのUI状態

    // 編集ドロワー用
    public $editingReservationId = null; // 現在編集中の予約ID
    public $editorOpen = false; // ドロワーの開閉状態
    public $editorData = []; // 編集中のデータ（予約情報、明細、支払方法等）
    
    public function mount(): void
    {
        $this->closingDate = today()->format('Y-m-d');

        $user = auth()->user();

        // 店舗の初期値を設定
        $accessibleStores = $this->getAccessibleStores();
        if ($accessibleStores->isEmpty()) {
            // アクセス可能な店舗がない場合はエラー
            abort(403, 'アクセス可能な店舗がありません');
        }

        // デフォルト店舗の選択
        if ($user->hasRole('super_admin')) {
            // super_adminは全店舗表示（null）
            $this->selectedStoreId = null;
        } else {
            // 一般ユーザーは自分の所属店舗、なければ最初の管理可能店舗
            $this->selectedStoreId = $user->store_id ?? $accessibleStores->first()->id;
        }

        $this->loadSalesData();
        $this->loadUnpostedReservations();
    }

    /**
     * ユーザーがアクセス可能な店舗リストを取得
     */
    public function getAccessibleStores()
    {
        $user = auth()->user();

        // スーパーアドミンは全店舗
        if ($user->hasRole('super_admin')) {
            return \App\Models\Store::all();
        }

        // 管理可能店舗を取得
        $manageableStores = $user->manageableStores;

        // 自分の所属店舗も追加（重複を除く）
        if ($user->store_id) {
            $ownStore = \App\Models\Store::find($user->store_id);
            if ($ownStore && !$manageableStores->contains('id', $user->store_id)) {
                $manageableStores->push($ownStore);
            }
        }

        return $manageableStores;
    }

    /**
     * 店舗変更時に再読み込み
     */
    public function updatedSelectedStoreId(): void
    {
        $this->loadSalesData();
        $this->loadUnpostedReservations();
    }

    /**
     * 日付変更時に再読み込み
     */
    public function updatedClosingDate(): void
    {
        $this->loadSalesData();
        $this->loadUnpostedReservations();
    }

    /**
     * 前の日に移動
     */
    public function previousDay(): void
    {
        $this->closingDate = \Carbon\Carbon::parse($this->closingDate)->subDay()->toDateString();
        $this->loadSalesData();
        $this->loadUnpostedReservations();
    }

    /**
     * 次の日に移動
     */
    public function nextDay(): void
    {
        $this->closingDate = \Carbon\Carbon::parse($this->closingDate)->addDay()->toDateString();
        $this->loadSalesData();
        $this->loadUnpostedReservations();
    }
    
    public function loadSalesData(): void
    {
        $sales = Sale::whereDate('sale_date', $this->closingDate)
            ->when($this->selectedStoreId, fn($q) => $q->where('store_id', $this->selectedStoreId))
            ->where('status', 'completed')
            ->get();

        // 支払方法別売上を動的に集計
        $salesByPaymentMethod = $sales->groupBy('payment_method')->map(function ($methodSales, $method) {
            return [
                'name' => $method ?: 'その他',
                'amount' => $methodSales->sum('total_amount'),
                'count' => $methodSales->count(),
            ];
        })->sortByDesc('amount');

        // payment_source別の件数
        $subscriptionCount = $sales->where('payment_source', 'subscription')->count();
        $ticketCount = $sales->where('payment_source', 'ticket')->count();
        $spotCount = $sales->where('payment_source', 'spot')->count();

        // サブスク/回数券で物販ありの件数と金額
        $subscriptionWithProducts = $sales->where('payment_source', 'subscription')->where('total_amount', '>', 0);
        $ticketWithProducts = $sales->where('payment_source', 'ticket')->where('total_amount', '>', 0);

        $this->salesData = [
            'sales_by_payment_method' => $salesByPaymentMethod, // 支払方法別売上（動的）
            'total_sales' => $sales->sum('total_amount'),
            'transaction_count' => $sales->count(),
            'customer_count' => $sales->unique('customer_id')->count(),
            // source別件数
            'subscription_count' => $subscriptionCount,
            'ticket_count' => $ticketCount,
            'spot_count' => $spotCount,
            // 物販ありの件数と金額（補助指標）
            'subscription_with_products_count' => $subscriptionWithProducts->count(),
            'subscription_with_products_amount' => $subscriptionWithProducts->sum('total_amount'),
            'ticket_with_products_count' => $ticketWithProducts->count(),
            'ticket_with_products_amount' => $ticketWithProducts->sum('total_amount'),
        ];

        // スタッフ別売上
        $this->salesData['sales_by_staff'] = $sales->groupBy('staff_id')->map(function ($staffSales) {
            return [
                'name' => $staffSales->first()->staff?->name ?? '不明',
                'amount' => $staffSales->sum('total_amount'),
                'count' => $staffSales->count(),
            ];
        });
        
        // メニュー別売上（売上明細から集計）
        $menuSales = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereDate('sales.sale_date', $this->closingDate)
            ->when($this->selectedStoreId, fn($q) => $q->where('sales.store_id', $this->selectedStoreId))
            ->where('sales.status', 'completed')
            ->select('sale_items.item_name', DB::raw('SUM(sale_items.amount) as total'), DB::raw('SUM(sale_items.quantity) as count'))
            ->groupBy('sale_items.item_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $this->salesData['top_menus'] = $menuSales;
    }

    /**
     * 未計上予約を読み込む
     */
    public function loadUnpostedReservations(): void
    {
        \Log::info('🔄 loadUnpostedReservations() 実行開始', [
            'closing_date' => $this->closingDate,
            'selected_store_id' => $this->selectedStoreId,
        ]);

        // 今日の完了済み予約を全て取得（売上の有無に関わらず）
        $reservations = Reservation::whereDate('reservation_date', $this->closingDate)
            ->when($this->selectedStoreId, fn($q) => $q->where('store_id', $this->selectedStoreId))
            ->where('status', 'completed')
            ->with(['customer', 'menu', 'store', 'medicalRecords', 'sale'])
            ->orderBy('start_time')
            ->get();

        // 店舗のデフォルト支払方法を取得（全予約が同じ店舗）
        $store = $reservations->first()?->store;
        $storePaymentMethods = $store && $store->payment_methods
            ? collect($store->payment_methods)->pluck('name')->toArray()
            : ['現金', 'クレジットカード', 'その他'];
        $defaultPaymentMethod = $storePaymentMethods[0] ?? '現金';

        $this->unposted = $reservations->map(function ($reservation) use ($defaultPaymentMethod, $storePaymentMethods) {
            // 自動判定: customer_ticket_id > customer_subscription_id > アクティブなサブスク契約 > spot
            $source = 'spot';
            if ($reservation->customer_ticket_id) {
                $source = 'ticket';
            } elseif ($reservation->customer_subscription_id) {
                $source = 'subscription';
            } else {
                // customer_subscription_idがNULLでも、アクティブなサブスク契約があれば判定
                // ステータスが'active'であれば有効とみなす（終了日を過ぎていても運用されているケースがあるため）
                $hasActiveSubscription = \App\Models\CustomerSubscription::where('customer_id', $reservation->customer_id)
                    ->where('store_id', $reservation->store_id)
                    ->where('status', 'active')
                    ->exists();

                if ($hasActiveSubscription) {
                    $source = 'subscription';
                }
            }

            // 売上を強制的に再取得（Eager Loadのキャッシュを回避）
            // 最新の売上を取得（同一予約に複数売上がある場合に備えて降順ソート）
            $freshSale = Sale::where('reservation_id', $reservation->id)
                ->orderByDesc('id')
                ->first();

            // 計上済みの場合は売上レコードから金額と支払方法を取得、未計上はカルテ/デフォルトから取得
            if ($freshSale) {
                // 計上済み：売上レコードの金額と支払方法を使用
                $amount = (int)($freshSale->total_amount ?? 0);
                $paymentMethod = $freshSale->payment_method ?? $defaultPaymentMethod;

                \Log::info('📊 計上済み予約データ', [
                    'reservation_id' => $reservation->id,
                    'customer' => $reservation->customer?->full_name,
                    'sale_id' => $freshSale->id,
                    'total_amount_from_sale' => $freshSale->total_amount,
                    'amount_int' => $amount,
                    'payment_method' => $paymentMethod,
                ]);
            } else {
                // 未計上：予約の金額を使用
                $amount = ($source === 'spot') ? (int)($reservation->total_amount ?? 0) : 0;

                // カルテから支払方法を取得（優先）
                $paymentMethod = null;
                $latestMedicalRecord = $reservation->medicalRecords->sortByDesc('created_at')->first();
                if ($latestMedicalRecord && $latestMedicalRecord->payment_method) {
                    $paymentMethod = $latestMedicalRecord->payment_method;
                }

                // カルテにない場合は、店舗のデフォルト支払方法
                if (!$paymentMethod) {
                    $paymentMethod = $defaultPaymentMethod;
                }
            }

            // 行の初期状態を設定
            $this->rowState[$reservation->id] = [
                'source' => $source,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
            ];

            $result = [
                'id' => $reservation->id,
                'time' => $reservation->start_time,
                'customer_name' => $reservation->customer?->full_name ?? '不明',
                'menu_name' => $reservation->menu?->name ?? '不明',
                'source' => $source,
                'amount' => $amount,
                'payment_methods' => $storePaymentMethods, // 店舗の支払方法リスト
                'is_posted' => $freshSale ? true : false, // 計上済みかどうか
                'sale_id' => $freshSale?->id, // 売上ID
            ];

            // 榊原 洋のデータをログ出力
            if ($reservation->id === 905 || str_contains($result['customer_name'], '榊原')) {
                \Log::info('👤 榊原 洋のデータ', [
                    'reservation_id' => $reservation->id,
                    'customer' => $result['customer_name'],
                    'is_posted' => $result['is_posted'],
                    'sale_id' => $result['sale_id'],
                    'amount' => $result['amount'],
                    'source' => $result['source'],
                ]);
            }

            return $result;
        })->toArray();
    }

    /**
     * 行の状態を更新（支払方法や金額の変更）
     */
    public function updateRowState($reservationId, $field, $value)
    {
        if (!isset($this->rowState[$reservationId])) {
            $this->rowState[$reservationId] = [];
        }

        $this->rowState[$reservationId][$field] = $value;

        \Log::info('Row state updated', [
            'reservation_id' => $reservationId,
            'field' => $field,
            'value' => $value,
            'current_state' => $this->rowState[$reservationId],
        ]);
    }

    /**
     * テーブルから直接計上
     */
    public function postSingleSale(int $reservationId): void
    {
        try {
            // 既に計上済みかチェック
            if (Sale::where('reservation_id', $reservationId)->exists()) {
                Notification::make()
                    ->warning()
                    ->title('既に計上済みです')
                    ->body('この予約は既に売上計上されています')
                    ->send();
                return;
            }

            $reservation = Reservation::findOrFail($reservationId);

            // 行の状態から支払方法と金額を取得
            $rowData = $this->rowState[$reservationId] ?? [];
            $paymentMethod = $rowData['payment_method'] ?? '現金';
            $source = $rowData['source'] ?? 'spot';

            // 売上計上
            $reservation->completeAndCreateSale($paymentMethod, $source);

            Notification::make()
                ->success()
                ->title('計上完了')
                ->body('売上を計上しました')
                ->send();

            // 配列を完全にリセットしてから再読み込み
            $this->unposted = [];
            $this->salesData = [];
            $this->rowState = [];

            $this->loadUnpostedReservations();
            $this->loadSalesData();

            // Livewireコンポーネントを明示的にリフレッシュ
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('計上失敗')
                ->body('エラー: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * 売上を取り消して未計上に戻す
     */
    public function cancelSale(int $reservationId): void
    {
        try {
            $reservation = Reservation::findOrFail($reservationId);
            $sale = $reservation->sale;

            if (!$sale) {
                Notification::make()
                    ->warning()
                    ->title('売上が見つかりません')
                    ->body('この予約には売上が紐づいていません')
                    ->send();
                return;
            }

            // SalePostingServiceを使用して売上取り消し
            $salePostingService = new \App\Services\SalePostingService();
            $salePostingService->void($sale);

            Notification::make()
                ->success()
                ->title('取消完了')
                ->body('売上を取り消しました。未計上に戻しました。')
                ->send();

            // 配列を完全にリセットしてから再読み込み
            $this->unposted = [];
            $this->salesData = [];
            $this->rowState = [];

            $this->loadUnpostedReservations();
            $this->loadSalesData();

            // Livewireコンポーネントを明示的にリフレッシュ
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('取消失敗')
                ->body('エラー: ' . $e->getMessage())
                ->send();
        }
    }

    /**
     * 編集ドロワーを開く
     */
    public function openEditor(int $reservationId): void
    {
        $reservation = Reservation::with(['customer', 'menu', 'medicalRecords', 'store'])->findOrFail($reservationId);

        // 自動判定: payment_source
        $source = 'spot';
        if ($reservation->customer_ticket_id) {
            $source = 'ticket';
        } elseif ($reservation->customer_subscription_id) {
            $source = 'subscription';
        }

        // 店舗の支払い方法設定を取得
        $storePaymentMethods = $reservation->store && $reservation->store->payment_methods
            ? collect($reservation->store->payment_methods)->pluck('name')->toArray()
            : ['現金', 'クレジットカード', 'その他'];

        // オプションメニューを取得
        // 1. まず予約のメインメニューに紐づく MenuOption を取得
        $optionMenus = [];

        if ($reservation->menu_id) {
            $menuOptions = \App\Models\MenuOption::where('menu_id', $reservation->menu_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'type' => 'menu_option',
                        'name' => $option->name,
                        'price' => (int)$option->price,
                        'duration_minutes' => $option->duration_minutes,
                    ];
                });

            $optionMenus = $menuOptions->toArray();
        }

        // 2. MenuOption がない場合は、オプション/アップセル専用メニューを取得
        if (empty($optionMenus)) {
            $optionMenus = \App\Models\Menu::where('store_id', $reservation->store_id)
                ->where('is_available', true)
                ->where(function ($query) {
                    // is_option=true または show_in_upsell=true のメニュー
                    $query->where('is_option', true)
                          ->orWhere('show_in_upsell', true);
                })
                ->where('is_subscription', false) // サブスクメニューを除外
                ->orderBy('sort_order')
                ->get()
                ->map(function ($menu) {
                    return [
                        'id' => $menu->id,
                        'type' => 'menu',
                        'name' => $menu->name,
                        'price' => (int)$menu->price,
                        'duration_minutes' => $menu->duration_minutes ?? 0,
                    ];
                })
                ->toArray();
        }

        // カルテから支払い方法を取得（優先）
        $paymentMethod = null;
        $latestMedicalRecord = $reservation->medicalRecords()->latest()->first();
        if ($latestMedicalRecord && $latestMedicalRecord->payment_method) {
            $paymentMethod = $latestMedicalRecord->payment_method;
        }

        // カルテにない場合は、店舗のデフォルト支払方法（リストの最初）
        if (!$paymentMethod) {
            $paymentMethod = ($source === 'spot')
                ? ($storePaymentMethods[0] ?? '現金')
                : 'その他';
        }

        // オプション/物販の読み込み：計上済みか未計上かで異なる
        $autoLoadedOptions = [];
        $autoLoadedProducts = [];

        // 計上済みの場合：売上のsale_itemsから読み込む
        $existingSale = \App\Models\Sale::where('reservation_id', $reservation->id)
            ->orderByDesc('id')
            ->first();

        if ($existingSale) {
            // 計上済み：sale_itemsから読み込み
            \Log::info('📦 計上済み売上のアイテムを読み込み', [
                'reservation_id' => $reservation->id,
                'sale_id' => $existingSale->id,
            ]);

            $saleItems = $existingSale->items;
            foreach ($saleItems as $item) {
                // typeとitem_typeの両方をチェック
                $itemType = $item->type ?? $item->item_type;

                if ($itemType === 'option' || $item->menu_option_id) {
                    // オプション
                    $autoLoadedOptions[] = [
                        'option_id' => $item->menu_option_id,
                        'option_type' => $item->menu_option_id ? 'menu_option' : null,
                        'name' => $item->item_name,
                        'price' => (int)($item->unit_price ?? 0),
                        'quantity' => (int)($item->quantity ?? 1),
                    ];
                } elseif ($itemType === 'product') {
                    // 物販
                    $autoLoadedProducts[] = [
                        'name' => $item->item_name,
                        'price' => (int)($item->unit_price ?? 0),
                        'quantity' => (int)($item->quantity ?? 1),
                    ];
                } elseif ($itemType === 'service' && !$item->menu_id) {
                    // serviceタイプだがmenu_idがない = 手動追加された物販/オプション
                    // 名前で判定（暫定）：将来的にはitem_typeを正しく設定すべき
                    $autoLoadedProducts[] = [
                        'name' => $item->item_name,
                        'price' => (int)($item->unit_price ?? 0),
                        'quantity' => (int)($item->quantity ?? 1),
                    ];
                }
                // それ以外（menu_idがあるservice）はメインサービスなので無視
            }

            \Log::info('✅ 読み込んだアイテム数', [
                'options' => count($autoLoadedOptions),
                'products' => count($autoLoadedProducts),
            ]);
        } else {
            // 未計上：予約のreservationOptionsから読み込み
            $reservationOptions = $reservation->getOptionMenusSafely();

            foreach ($reservationOptions as $reservationOption) {
                // MenuOption経由の場合
                if ($reservationOption->menuOption) {
                    $menuOption = $reservationOption->menuOption;
                    $autoLoadedOptions[] = [
                        'option_id' => $menuOption->id,
                        'option_type' => 'menu_option',
                        'name' => $menuOption->name ?? '',
                        'price' => (int)($reservationOption->price ?? $menuOption->price ?? 0),
                        'quantity' => (int)($reservationOption->quantity ?? 1),
                    ];
                }
            }
        }

        // エディタデータ初期化
        $initialSubtotal = $source === 'spot' ? ($reservation->total_amount ?? 0) : 0;
        $initialTaxAmount = floor($initialSubtotal * 0.1);

        // 計上済み売上がある場合は割引額を取得
        $initialDiscountAmount = $existingSale ? (int)($existingSale->discount_amount ?? 0) : 0;

        $this->editorData = [
            'reservation' => [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'time' => $reservation->start_time,
                'customer_name' => $reservation->customer?->full_name ?? '不明',
                'menu_name' => $reservation->menu?->name ?? '不明',
            ],
            'service_item' => [
                'name' => $reservation->menu?->name ?? 'サービス',
                'price' => (int)($source === 'spot' ? ($reservation->total_amount ?? 0) : 0),
                'quantity' => 1,
            ],
            'option_items' => $autoLoadedOptions, // 売上/予約から自動読込されたオプション
            'option_menus' => $optionMenus, // 選択可能なオプションメニュー
            'product_items' => $autoLoadedProducts, // 売上から自動読込された物販
            'payment_method' => $paymentMethod,
            'payment_methods_list' => $storePaymentMethods, // 店舗の支払い方法リスト
            'payment_source' => $source,
            'subtotal' => $initialSubtotal,
            'tax_amount' => $initialTaxAmount,
            'discount_amount' => $initialDiscountAmount, // 割引額
            'total' => $initialSubtotal + $initialTaxAmount,
        ];

        // オプション/物販がある場合は合計を再計算（税込み）
        if (!empty($autoLoadedOptions) || !empty($autoLoadedProducts)) {
            $this->updateCalculation();
        }

        $this->editingReservationId = $reservationId;
        $this->editorOpen = true;
    }

    /**
     * 編集ドロワーを閉じる
     */
    public function closeEditor(): void
    {
        $this->editorOpen = false;
        $this->editingReservationId = null;
        $this->editorData = [];
    }

    /**
     * オプション明細を追加
     */
    public function addOptionItem(): void
    {
        $this->editorData['option_items'][] = [
            'option_id' => null,
            'option_type' => null,
            'name' => '',
            'price' => 0,
            'quantity' => 1,
        ];
    }

    /**
     * オプションメニュー選択時に価格を自動設定
     */
    public function selectOptionMenu(int $index, string $value): void
    {
        if (empty($value)) {
            return;
        }

        // value形式: "type:id" (例: "menu_option:5" または "menu:10")
        list($type, $id) = explode(':', $value);

        if ($type === 'menu_option') {
            $option = \App\Models\MenuOption::find($id);
            if ($option) {
                $this->editorData['option_items'][$index]['option_id'] = $option->id;
                $this->editorData['option_items'][$index]['option_type'] = 'menu_option';
                $this->editorData['option_items'][$index]['name'] = $option->name;
                $this->editorData['option_items'][$index]['price'] = (int)$option->price;
                $this->updateCalculation();
            }
        } elseif ($type === 'menu') {
            $menu = \App\Models\Menu::find($id);
            if ($menu) {
                $this->editorData['option_items'][$index]['option_id'] = $menu->id;
                $this->editorData['option_items'][$index]['option_type'] = 'menu';
                $this->editorData['option_items'][$index]['name'] = $menu->name;
                $this->editorData['option_items'][$index]['price'] = (int)$menu->price;
                $this->updateCalculation();
            }
        }
    }

    /**
     * オプション明細を削除
     */
    public function removeOptionItem(int $index): void
    {
        unset($this->editorData['option_items'][$index]);
        $this->editorData['option_items'] = array_values($this->editorData['option_items']);
        $this->updateCalculation();
    }

    /**
     * 物販明細を追加
     */
    public function addProductItem(): void
    {
        $this->editorData['product_items'][] = [
            'name' => '',
            'price' => 0,
            'quantity' => 1,
        ];
    }

    /**
     * 物販明細を削除
     */
    public function removeProductItem(int $index): void
    {
        unset($this->editorData['product_items'][$index]);
        $this->editorData['product_items'] = array_values($this->editorData['product_items']);
        $this->updateCalculation();
    }

    /**
     * 合計を再計算（税込み）
     */
    public function updateCalculation(): void
    {
        $serviceTotal = ($this->editorData['service_item']['price'] ?? 0) * ($this->editorData['service_item']['quantity'] ?? 1);

        $optionTotal = 0;
        foreach ($this->editorData['option_items'] ?? [] as $item) {
            $optionTotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }

        $productTotal = 0;
        foreach ($this->editorData['product_items'] ?? [] as $item) {
            $productTotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }

        // 小計（税込）
        $subtotal = $serviceTotal + $optionTotal + $productTotal;

        // 割引額
        $discountAmount = (int)($this->editorData['discount_amount'] ?? 0);

        // 合計 = 小計 - 割引
        $total = $subtotal - $discountAmount;

        // マイナスにならないように
        if ($total < 0) {
            $total = 0;
        }

        $this->editorData['total'] = $total;
    }

    /**
     * 売上を保存（明細付き）
     *
     * 計上済みの場合は売上を更新、未計上の場合は新規作成
     */
    public function saveSaleWithItems(): void
    {
        try {
            $reservation = Reservation::findOrFail($this->editingReservationId);
            $method = $this->editorData['payment_method'];
            $totalAmount = $this->editorData['total'];

            // 合計>0の場合、支払方法をバリデーション（空の場合のみエラー）
            if ($totalAmount > 0 && empty($method)) {
                throw new \Exception('オプション/物販がある場合は、支払方法を選択してください');
            }

            // 既に計上済みかチェック
            $existingSale = Sale::where('reservation_id', $this->editingReservationId)->first();

            DB::beginTransaction();

            // スポット予約の場合、モーダルで変更された単価を予約に反映
            $paymentSource = $this->editorData['payment_source'];
            if ($paymentSource === 'spot') {
                $newServicePrice = $this->editorData['service_item']['price'] ?? 0;
                if ($newServicePrice != $reservation->total_amount) {
                    $reservation->update(['total_amount' => $newServicePrice]);
                }
            }

            // オプションデータの変換
            $options = [];
            foreach ($this->editorData['option_items'] ?? [] as $index => $item) {
                \Log::info('📝 オプション変換チェック', [
                    'index' => $index,
                    'name' => $item['name'] ?? 'なし',
                    'option_id' => $item['option_id'] ?? 'なし',
                    'price' => $item['price'] ?? 0,
                    'quantity' => $item['quantity'] ?? 0,
                ]);

                // option_idの有無に関わらず、nameがあれば保存
                if (!empty($item['name'])) {
                    $options[] = [
                        'menu_option_id' => $item['option_type'] === 'menu_option' ? $item['option_id'] : null,
                        'name' => $item['name'],
                        'price' => $item['price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 1,
                    ];
                }
            }

            \Log::info('✅ 変換後のオプション数', ['count' => count($options)]);

            // 物販データの変換
            $products = [];
            foreach ($this->editorData['product_items'] ?? [] as $index => $item) {
                \Log::info('📦 物販変換チェック', [
                    'index' => $index,
                    'name' => $item['name'] ?? 'なし',
                    'price' => $item['price'] ?? 0,
                    'quantity' => $item['quantity'] ?? 0,
                ]);

                if (!empty($item['name'])) {
                    $products[] = [
                        'name' => $item['name'],
                        'price' => $item['price'] ?? 0,
                        'quantity' => $item['quantity'] ?? 1,
                        'tax_rate' => 0.1,
                    ];
                }
            }

            \Log::info('✅ 変換後の物販数', ['count' => count($products)]);

            // 割引額を取得
            $discountAmount = (int)($this->editorData['discount_amount'] ?? 0);

            if ($existingSale) {
                // 既に計上済み：売上を更新
                $this->updateExistingSale($existingSale, $reservation, $method, $options, $products, $discountAmount);
                $message = "予約番号 {$reservation->reservation_number} の売上を更新しました";
            } else {
                // 未計上：新規作成
                $salePostingService = new \App\Services\SalePostingService();
                $sale = $salePostingService->post($reservation, $method, $options, $products, $discountAmount);

                // ポイント付与（スポットまたは合計>0の場合）
                if ($sale->payment_source === 'spot' || $totalAmount > 0) {
                    $sale->grantPoints();
                }

                $message = "予約番号 {$reservation->reservation_number} を計上しました";
            }

            // 予約ステータス更新
            $reservation->update([
                'status' => 'completed',
                'payment_status' => 'paid',
            ]);

            DB::commit();

            Notification::make()
                ->title('保存完了')
                ->body($message)
                ->success()
                ->send();

            // ドロワーを閉じてデータ再読み込み
            $this->closeEditor();

            // 配列を完全にリセットしてから再読み込み
            $this->unposted = [];
            $this->salesData = [];
            $this->rowState = [];

            $this->loadSalesData();
            $this->loadUnpostedReservations();

            // Livewireコンポーネントを明示的にリフレッシュ
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('エラー')
                ->body('保存処理中にエラーが発生しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * 既存の売上レコードを更新
     */
    protected function updateExistingSale(
        Sale $sale,
        Reservation $reservation,
        string $paymentMethod,
        array $options,
        array $products,
        int $discountAmount = 0
    ): void {
        // 既存の明細を削除
        $sale->items()->delete();

        // 金額を再計算
        $subtotal = 0;
        $taxAmount = 0;

        // スポットの場合はメニュー料金を加算
        if ($sale->payment_source === 'spot') {
            $menuPrice = $reservation->total_amount ?? 0;
            $subtotal += $menuPrice;
            $taxAmount += floor($menuPrice * 0.1);

            // メニュー明細を作成
            if ($reservation->menu) {
                $sale->items()->create([
                    'menu_id' => $reservation->menu_id,
                    'item_type' => 'service',
                    'item_name' => $reservation->menu->name,
                    'item_description' => $reservation->menu->description,
                    'unit_price' => $menuPrice,
                    'quantity' => 1,
                    'discount_amount' => 0,
                    'tax_rate' => 0.1,
                    'tax_amount' => floor($menuPrice * 0.1),
                    'amount' => $menuPrice,
                ]);
            }
        }

        // オプション明細を作成
        foreach ($options as $option) {
            $optionAmount = ($option['price'] ?? 0) * ($option['quantity'] ?? 1);
            $subtotal += $optionAmount;
            $taxAmount += floor($optionAmount * 0.1);

            $sale->items()->create([
                'menu_option_id' => $option['menu_option_id'] ?? null,
                'item_type' => 'option',
                'item_name' => $option['name'],
                'unit_price' => $option['price'],
                'quantity' => $option['quantity'],
                'amount' => $optionAmount,
                'tax_rate' => 0.1,
                'tax_amount' => floor($optionAmount * 0.1),
            ]);
        }

        // 物販明細を作成
        foreach ($products as $product) {
            $productAmount = ($product['price'] ?? 0) * ($product['quantity'] ?? 1);
            $subtotal += $productAmount;
            $taxAmount += floor($productAmount * ($product['tax_rate'] ?? 0.1));

            $sale->items()->create([
                'item_type' => 'product',
                'item_name' => $product['name'],
                'unit_price' => $product['price'],
                'quantity' => $product['quantity'],
                'amount' => $productAmount,
                'tax_rate' => $product['tax_rate'] ?? 0.1,
                'tax_amount' => floor($productAmount * ($product['tax_rate'] ?? 0.1)),
            ]);
        }

        // 内税計算のため税額は0
        $taxAmount = 0;

        // 合計 = 小計 - 割引
        $totalAmount = $subtotal - $discountAmount;
        if ($totalAmount < 0) {
            $totalAmount = 0;
        }

        // 売上レコードを更新
        $sale->update([
            'payment_method' => $paymentMethod,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
        ]);

        // 更新後のデータを確認
        $sale->refresh();

        \Log::info('🔄 売上更新完了', [
            'sale_id' => $sale->id,
            'reservation_id' => $reservation->id,
            'payment_method' => $paymentMethod,
            'menu_price' => $reservation->total_amount ?? 0,
            'options_count' => count($options),
            'products_count' => count($products),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'db_total_after_update' => $sale->total_amount,
            'db_payment_method_after_update' => $sale->payment_method,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * 個別の予約を計上（簡易版・後方互換）
     */
    public function postSale(int $reservationId): void
    {
        try {
            // 二重計上チェック
            if (Sale::where('reservation_id', $reservationId)->exists()) {
                Notification::make()
                    ->title('エラー')
                    ->body('この予約は既に計上済みです')
                    ->warning()
                    ->send();
                return;
            }

            $reservation = Reservation::findOrFail($reservationId);
            $state = $this->rowState[$reservationId] ?? null;

            if (!$state) {
                throw new \Exception('予約の状態が見つかりません');
            }

            $source = $state['source'];
            $method = $state['payment_method'];
            $amount = $state['amount'];

            // サブスク/回数券は強制的に0円
            if (in_array($source, ['subscription', 'ticket'])) {
                $amount = 0;
            }

            // スポットの場合は金額を更新
            if ($source === 'spot' && $amount != $reservation->total_amount) {
                $reservation->update(['total_amount' => $amount]);
            }

            DB::beginTransaction();

            // payment_sourceに応じて計上
            $sale = $reservation->completeAndCreateSale($method, $source);

            DB::commit();

            Notification::make()
                ->title('計上完了')
                ->body("予約番号 {$reservation->reservation_number} を計上しました")
                ->success()
                ->send();

            // 配列を完全にリセットしてから再読み込み
            $this->unposted = [];
            $this->salesData = [];
            $this->rowState = [];

            $this->loadSalesData();
            $this->loadUnpostedReservations();

            // Livewireコンポーネントを明示的にリフレッシュ
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('エラー')
                ->body('計上処理中にエラーが発生しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * 全ての未計上予約を一括計上
     */
    public function postAll(): void
    {
        $successCount = 0;
        $errorCount = 0;

        foreach ($this->unposted as $res) {
            try {
                $this->postSale($res['id']);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                \Log::error('一括計上エラー', [
                    'reservation_id' => $res['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Notification::make()
            ->title('一括計上完了')
            ->body("成功: {$successCount}件、エラー: {$errorCount}件")
            ->success()
            ->send();

        // 配列を完全にリセットしてから再読み込み
        $this->unposted = [];
        $this->salesData = [];
        $this->rowState = [];

        $this->loadSalesData();
        $this->loadUnpostedReservations();

        // Livewireコンポーネントを明示的にリフレッシュ
        $this->dispatch('$refresh');
    }

    /**
     * 売上を取り消す
     */
    public function voidSale(int $saleId): void
    {
        try {
            DB::beginTransaction();

            $sale = Sale::with(['customerTicket'])->findOrFail($saleId);

            // 回数券の場合は返却
            if ($sale->customer_ticket_id) {
                $ticket = CustomerTicket::find($sale->customer_ticket_id);
                if ($ticket) {
                    $ticket->refund($sale->reservation_id, 1);
                }
            }

            // 売上を削除
            $sale->delete();

            DB::commit();

            Notification::make()
                ->title('取消完了')
                ->body("売上番号 {$sale->sale_number} を取り消しました")
                ->success()
                ->send();

            // 配列を完全にリセットしてから再読み込み
            $this->unposted = [];
            $this->salesData = [];
            $this->rowState = [];

            $this->loadSalesData();
            $this->loadUnpostedReservations();

            // Livewireコンポーネントを明示的にリフレッシュ
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            DB::rollBack();

            Notification::make()
                ->title('エラー')
                ->body('取消処理中にエラーが発生しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('精算情報')
                ->schema([
                    Forms\Components\DatePicker::make('closingDate')
                        ->label('精算日')
                        ->native(false)
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->loadSalesData()),
                    Forms\Components\TextInput::make('openingCash')
                        ->label('釣銭準備金')
                        ->numeric()
                        ->prefix('¥')
                        ->reactive()
                        ->afterStateUpdated(fn () => $this->loadSalesData()),
                    Forms\Components\TextInput::make('actualCash')
                        ->label('実際の現金残高')
                        ->numeric()
                        ->prefix('¥')
                        ->helperText('レジ内の現金を数えて入力してください'),
                    Forms\Components\Textarea::make('notes')
                        ->label('備考')
                        ->rows(3),
                ]),
        ];
    }
    
    public function performClosing(): void
    {
        if (!$this->actualCash) {
            Notification::make()
                ->title('エラー')
                ->body('実際の現金残高を入力してください')
                ->danger()
                ->send();
            return;
        }
        
        $cashDifference = $this->actualCash - $this->salesData['expected_cash'];
        
        try {
            DB::beginTransaction();
            
            // 既存の精算レコードをチェック
            $existingClosing = DailyClosingModel::where('store_id', auth()->user()->store_id ?? 1)
                ->where('closing_date', $this->closingDate)
                ->first();
            
            if ($existingClosing) {
                Notification::make()
                    ->title('エラー')
                    ->body('この日の精算は既に完了しています')
                    ->danger()
                    ->send();
                return;
            }
            
            // 日次精算レコードを作成
            DailyClosingModel::create([
                'store_id' => auth()->user()->store_id ?? 1,
                'closing_date' => $this->closingDate,
                'opening_cash' => $this->openingCash,
                'cash_sales' => $this->salesData['cash_sales'],
                'card_sales' => $this->salesData['card_sales'],
                'digital_sales' => $this->salesData['digital_sales'],
                'total_sales' => $this->salesData['total_sales'],
                'expected_cash' => $this->salesData['expected_cash'],
                'actual_cash' => $this->actualCash,
                'cash_difference' => $cashDifference,
                'transaction_count' => $this->salesData['transaction_count'],
                'customer_count' => $this->salesData['customer_count'],
                'sales_by_staff' => $this->salesData['sales_by_staff']->toArray(),
                'sales_by_menu' => $this->salesData['top_menus']->toArray(),
                'status' => 'closed',
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'notes' => $this->notes,
            ]);
            
            DB::commit();
            
            Notification::make()
                ->title('日次精算完了')
                ->body('精算が正常に完了しました。差異: ¥' . number_format($cashDifference))
                ->success()
                ->send();
                
            $this->redirect(SaleResource::getUrl('index'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Notification::make()
                ->title('エラー')
                ->body('精算処理中にエラーが発生しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('戻る')
                ->url(SaleResource::getUrl('index'))
                ->color('gray'),
        ];
    }
}