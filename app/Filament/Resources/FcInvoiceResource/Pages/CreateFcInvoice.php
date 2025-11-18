<?php

namespace App\Filament\Resources\FcInvoiceResource\Pages;

use App\Filament\Resources\FcInvoiceResource;
use App\Models\FcInvoice;
use Filament\Resources\Pages\CreateRecord;

class CreateFcInvoice extends CreateRecord
{
    protected static string $resource = FcInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['invoice_number'] = FcInvoice::generateInvoiceNumber();
        $data['status'] = 'draft';

        // 税額と合計を計算
        $subtotal = floatval($data['subtotal'] ?? 0);
        $taxAmount = $subtotal * 0.1; // 10% tax
        $totalAmount = $subtotal + $taxAmount;

        $data['tax_amount'] = $taxAmount;
        $data['total_amount'] = $totalAmount;
        $data['paid_amount'] = 0;
        $data['outstanding_amount'] = $totalAmount;

        return $data;
    }
}
