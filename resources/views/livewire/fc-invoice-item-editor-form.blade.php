@if($invoice)
    @livewire('fc-invoice-item-editor', ['invoice' => $invoice, 'readonly' => $readonly ?? false])
@else
    <div class="text-center py-8 text-gray-500">
        請求書を保存してから明細を編集できます。
    </div>
@endif