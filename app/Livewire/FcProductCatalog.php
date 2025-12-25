<?php

namespace App\Livewire;

use App\Models\FcProduct;
use App\Models\FcProductCategory;
use App\Models\FcOrder;
use App\Models\FcOrderItem;
use App\Services\FcNotificationService;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\WithPagination;

class FcProductCatalog extends Component
{
    use WithPagination;

    public $selectedCategory = null;
    public $searchQuery = '';
    public $cart = [];
    public $showCart = false;

    protected $fcNotificationService;

    public function boot(FcNotificationService $fcNotificationService)
    {
        $this->fcNotificationService = $fcNotificationService;
    }

    public function mount()
    {
        // カートをセッションから復元
        $this->cart = session('fc_cart', []);
    }

    public function updated($property)
    {
        // cartプロパティが更新されたらセッションに保存
        if (str_starts_with($property, 'cart.')) {
            session(['fc_cart' => $this->cart]);
        }
    }

    public function addToCart($productId, $quantity = 1)
    {
        $product = FcProduct::find($productId);

        if (!$product || !$product->is_active) {
            Notification::make()
                ->danger()
                ->title('この商品は現在購入できません。')
                ->send();
            return;
        }

        if ($quantity < $product->min_order_quantity) {
            Notification::make()
                ->danger()
                ->title("最小発注数は{$product->min_order_quantity}{$product->unit}です。")
                ->send();
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] += $quantity;
        } else {
            $this->cart[$productId] = [
                'product_id' => $productId,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit_price' => $product->unit_price,
                'tax_rate' => $product->tax_rate,
                'unit' => $product->unit,
                'quantity' => $quantity,
                'image_path' => $product->image_path,
            ];
        }

        session(['fc_cart' => $this->cart]);

        Notification::make()
            ->success()
            ->title('カートに追加しました')
            ->send();
    }

    public function removeFromCart($productId)
    {
        unset($this->cart[$productId]);
        session(['fc_cart' => $this->cart]);
        session()->flash('success', 'カートから削除しました。');
    }

    public function updateQuantity($productId, $quantity)
    {
        if ($quantity <= 0) {
            $this->removeFromCart($productId);
            return;
        }

        $product = FcProduct::find($productId);

        if ($quantity < $product->min_order_quantity) {
            session()->flash('error', "最小発注数は{$product->min_order_quantity}{$product->unit}です。");
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] = $quantity;
            session(['fc_cart' => $this->cart]);
        }
    }

    public function clearCart()
    {
        $this->cart = [];
        session()->forget('fc_cart');
        session()->flash('success', 'カートをクリアしました。');
    }

    public function submitOrder()
    {
        \Log::info('submitOrder called');
        \Log::info('Cart contents:', $this->cart);

        if (empty($this->cart)) {
            \Log::warning('Cart is empty');
            Notification::make()
                ->danger()
                ->title('カートが空です')
                ->send();
            return;
        }

        $user = auth()->user();

        if (!$user || !$user->store || !$user->store->isFcStore()) {
            Notification::make()
                ->danger()
                ->title('FC加盟店ユーザーのみ発注できます')
                ->send();
            return;
        }

        try {
            \DB::beginTransaction();

            // 注文作成
            $order = FcOrder::create([
                'fc_store_id' => $user->store_id,
                'headquarters_store_id' => $user->store->headquarters_store_id,
                'order_number' => FcOrder::generateOrderNumber(),
                'status' => 'draft',
                'subtotal' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
            ]);

            // 注文明細作成
            foreach ($this->cart as $item) {
                $product = FcProduct::find($item['product_id']);

                if (!$product || !$product->is_active) {
                    throw new \Exception("商品「{$item['name']}」は現在購入できません。");
                }

                $subtotal = $product->unit_price * $item['quantity'];
                $taxAmount = $subtotal * ($product->tax_rate / 100);
                $total = $subtotal + $taxAmount;

                FcOrderItem::create([
                    'fc_order_id' => $order->id,
                    'fc_product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->unit_price,
                    'tax_rate' => $product->tax_rate,
                    'tax_amount' => $taxAmount,
                    'unit' => $product->unit,
                    'subtotal' => $subtotal,
                    'total' => $total,
                ]);
            }

            // 合計金額を再計算
            $order->recalculateTotals();

            // ステータスを「発注済み」に更新
            $order->update([
                'status' => 'ordered',
                'ordered_at' => now(),
            ]);

            // 通知送信
            try {
                $this->fcNotificationService->notifyOrderSubmitted($order);
            } catch (\Exception $e) {
                \Log::error("FC発注通知エラー: " . $e->getMessage());
            }

            \DB::commit();

            // カートをクリア
            $this->clearCart();

            Notification::make()
                ->success()
                ->title('発注が完了しました')
                ->body("注文番号: {$order->order_number}")
                ->send();

            $this->redirect(route('filament.admin.pages.fc-store-dashboard'));

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("FC発注エラー: " . $e->getMessage());

            Notification::make()
                ->danger()
                ->title('発注に失敗しました')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function toggleCart()
    {
        $this->showCart = !$this->showCart;
    }

    public function render()
    {
        $categories = FcProductCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $query = FcProduct::where('fc_products.is_active', true)
            ->leftJoin('fc_product_categories', 'fc_products.category_id', '=', 'fc_product_categories.id')
            ->select('fc_products.*');

        if ($this->selectedCategory) {
            $query->where('fc_products.category_id', $this->selectedCategory);
        }

        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->where('fc_products.name', 'like', "%{$this->searchQuery}%")
                  ->orWhere('fc_products.sku', 'like', "%{$this->searchQuery}%")
                  ->orWhere('fc_products.description', 'like', "%{$this->searchQuery}%");
            });
        }

        // カテゴリごとにグループ化して取得
        if (!$this->selectedCategory && !$this->searchQuery) {
            // カテゴリごとのグループ表示（検索なし、カテゴリフィルタなし）
            $groupedProducts = [];
            foreach ($categories as $category) {
                $categoryProducts = FcProduct::where('is_active', true)
                    ->where('category_id', $category->id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();
                
                if ($categoryProducts->count() > 0) {
                    $groupedProducts[] = [
                        'category' => $category,
                        'products' => $categoryProducts
                    ];
                }
            }
            $products = collect(); // 空のコレクション（ページネーションなし）
        } else {
            // 通常の表示（検索あり、またはカテゴリフィルタあり）
            $products = $query->orderBy('fc_product_categories.sort_order')
                              ->orderBy('fc_products.sort_order')
                              ->orderBy('fc_products.name')
                              ->paginate(12);
            $groupedProducts = null;
        }
        
        // デバッグ用：最初の3商品の並び順を確認
        \Log::info('FC商品カタログの並び順確認:', [
            'products' => $products->take(3)->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sort_order' => $p->sort_order,
                    'category_id' => $p->category_id,
                    'unit_price' => $p->unit_price
                ];
            })->toArray()
        ]);

        // カート合計計算
        $cartTotal = 0;
        $cartTaxTotal = 0;
        $cartSubtotal = 0;

        foreach ($this->cart as $item) {
            $subtotal = $item['unit_price'] * $item['quantity'];
            $tax = $subtotal * ($item['tax_rate'] / 100);

            $cartSubtotal += $subtotal;
            $cartTaxTotal += $tax;
        }
        $cartTotal = $cartSubtotal + $cartTaxTotal;

        return view('livewire.fc-product-catalog', [
            'categories' => $categories,
            'products' => $products,
            'groupedProducts' => $groupedProducts ?? null,
            'cartItemCount' => count($this->cart),
            'cartSubtotal' => $cartSubtotal,
            'cartTaxTotal' => $cartTaxTotal,
            'cartTotal' => $cartTotal,
        ]);
    }
}
