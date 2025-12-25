<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\FcInvoice;
use App\Models\FcInvoiceItem;
use App\Models\FcProduct;
use App\Models\FcInvoiceItemTemplate;

class FcInvoiceItemEditor extends Component
{
    public FcInvoice $invoice;
    public array $items = [];
    public bool $readonly = false;

    public function mount(FcInvoice $invoice, bool $readonly = false)
    {
        $this->invoice = $invoice;
        $this->readonly = $readonly;
        $this->loadItems();
    }

    protected function loadItems()
    {
        $this->items = $this->invoice->items->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => $item->type,
                'fc_product_id' => $item->fc_product_id,
                'description' => $item->description,
                'quantity' => floatval($item->quantity),
                'unit_price' => floatval($item->unit_price),
                'discount_amount' => floatval($item->discount_amount),
                'subtotal' => floatval($item->subtotal),
                'tax_rate' => floatval($item->tax_rate),
                'tax_amount' => floatval($item->tax_amount),
                'total_amount' => floatval($item->total_amount),
                'notes' => $item->notes,
                'sort_order' => $item->sort_order,
            ];
        })->toArray();

        // 空の行を追加（編集用）
        if (!$this->readonly) {
            $this->addEmptyRow();
        }
    }

    protected function addEmptyRow()
    {
        $this->items[] = [
            'id' => null,
            'type' => 'custom',
            'fc_product_id' => null,
            'description' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'discount_amount' => 0,
            'subtotal' => 0,
            'tax_rate' => 10.00,
            'tax_amount' => 0,
            'total_amount' => 0,
            'notes' => '',
            'sort_order' => count($this->items),
        ];
    }

    public function addRow()
    {
        if ($this->readonly) return;

        $this->addEmptyRow();
    }

    public function removeRow($index)
    {
        if ($this->readonly) return;
        
        $item = $this->items[$index];
        
        // DBから削除
        if ($item['id']) {
            FcInvoiceItem::find($item['id'])?->delete();
        }

        // 配列から削除
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        // 請求書合計を更新
        $this->recalculateInvoiceTotals();
    }

    public function updateItem($index, $field)
    {
        if ($this->readonly) return;

        $item = &$this->items[$index];
        
        // 自動計算
        $this->calculateItemAmounts($index);

        // 空でない行をDBに保存
        if (!empty($item['description']) && $item['unit_price'] > 0) {
            $this->saveItem($index);
        }

        // 請求書合計を更新
        $this->recalculateInvoiceTotals();
    }

    protected function calculateItemAmounts($index)
    {
        $item = &$this->items[$index];
        
        $quantity = floatval($item['quantity'] ?? 1);
        $unitPrice = floatval($item['unit_price'] ?? 0);
        $discountAmount = floatval($item['discount_amount'] ?? 0);
        $taxRate = floatval($item['tax_rate'] ?? 10);

        // 小計 = 数量 × 単価 - 値引き額
        $subtotal = ($quantity * $unitPrice) - $discountAmount;
        
        // 税額 = 小計 × 税率 / 100
        $taxAmount = $subtotal * ($taxRate / 100);
        
        // 合計 = 小計 + 税額
        $totalAmount = $subtotal + $taxAmount;

        $item['subtotal'] = round($subtotal, 2);
        $item['tax_amount'] = round($taxAmount, 2);
        $item['total_amount'] = round($totalAmount, 2);
    }

    protected function saveItem($index)
    {
        $item = $this->items[$index];
        
        $data = [
            'fc_invoice_id' => $this->invoice->id,
            'type' => $item['type'],
            'fc_product_id' => $item['fc_product_id'],
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount_amount' => $item['discount_amount'],
            'subtotal' => $item['subtotal'],
            'tax_rate' => $item['tax_rate'],
            'tax_amount' => $item['tax_amount'],
            'total_amount' => $item['total_amount'],
            'notes' => $item['notes'],
            'sort_order' => $index,
        ];

        if ($item['id']) {
            // 更新
            $invoiceItem = FcInvoiceItem::find($item['id']);
            $invoiceItem->update($data);
        } else {
            // 新規作成
            $invoiceItem = FcInvoiceItem::create($data);
            $this->items[$index]['id'] = $invoiceItem->id;
        }
    }

    protected function recalculateInvoiceTotals()
    {
        $subtotal = 0;
        $taxAmount = 0;
        $totalAmount = 0;

        foreach ($this->items as $item) {
            if ($item['id'] && !empty($item['description'])) {
                $subtotal += floatval($item['subtotal']);
                $taxAmount += floatval($item['tax_amount']);
                $totalAmount += floatval($item['total_amount']);
            }
        }

        // 請求書の合計を更新
        $paidAmount = floatval($this->invoice->paid_amount);
        $outstandingAmount = $totalAmount - $paidAmount;

        $this->invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'outstanding_amount' => $outstandingAmount,
        ]);

        $this->invoice->refresh();
    }

    public function selectProduct($index, $productId)
    {
        if ($this->readonly || !$productId) return;

        $product = FcProduct::find($productId);
        if (!$product) return;

        $this->items[$index]['fc_product_id'] = $product->id;
        $this->items[$index]['type'] = 'product';
        $this->items[$index]['description'] = $product->name;
        $this->items[$index]['unit_price'] = floatval($product->price);
        
        $this->updateItem($index, 'unit_price');
    }

    public function setItemType($index, $type)
    {
        if ($this->readonly) return;

        $this->items[$index]['type'] = $type;
        
        // 商品以外の場合は商品IDをクリア
        if ($type !== 'product') {
            $this->items[$index]['fc_product_id'] = null;
        }

        // タイプに応じてデフォルトの説明を設定
        if (empty($this->items[$index]['description'])) {
            $this->items[$index]['description'] = match($type) {
                'royalty' => 'ロイヤリティ',
                'system_fee' => 'システム使用料',
                default => ''
            };
        }

        $this->updateItem($index, 'type');
    }

    public function getAvailableProducts()
    {
        return FcProduct::where('is_active', true)->orderBy('name')->get();
    }

    public function getAvailableTemplates()
    {
        return FcInvoiceItemTemplate::active()->orderBy('sort_order')->get();
    }

    public function addFromTemplate($templateId)
    {
        if ($this->readonly) return;

        $template = FcInvoiceItemTemplate::find($templateId);
        if (!$template) return;

        // 空の行を探すか、新しい行を追加
        $emptyIndex = null;
        foreach ($this->items as $index => $item) {
            if (!$item['id'] && empty($item['description'])) {
                $emptyIndex = $index;
                break;
            }
        }

        if ($emptyIndex === null) {
            $this->addEmptyRow();
            $emptyIndex = count($this->items) - 1;
        }

        // テンプレートの値を設定
        $this->items[$emptyIndex]['type'] = $template->type;
        $this->items[$emptyIndex]['description'] = $template->description;
        $this->items[$emptyIndex]['quantity'] = floatval($template->quantity);
        $this->items[$emptyIndex]['unit_price'] = floatval($template->unit_price);
        $this->items[$emptyIndex]['tax_rate'] = floatval($template->tax_rate);

        // 計算して保存
        $this->calculateItemAmounts($emptyIndex);
        $this->saveItem($emptyIndex);
        $this->recalculateInvoiceTotals();

        // 新しい空行を追加
        $this->addEmptyRow();
    }

    public function render()
    {
        return view('livewire.fc-invoice-item-editor', [
            'products' => $this->getAvailableProducts(),
            'templates' => $this->getAvailableTemplates(),
        ]);
    }
}