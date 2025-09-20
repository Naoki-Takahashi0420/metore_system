<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use App\Models\Customer;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerSubscription extends CreateRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // URLパラメータからcustomer_idを取得
        if (request()->has('customer_id')) {
            $data['customer_id'] = request('customer_id');

            // 顧客の店舗を自動設定
            $customer = Customer::find($data['customer_id']);
            if ($customer && $customer->store_id) {
                $data['store_id'] = $customer->store_id;
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // 作成後、顧客編集画面に戻る
        if ($this->record->customer_id) {
            redirect()->to(route('filament.admin.resources.customers.edit', $this->record->customer_id))
                ->send();
        }
    }
}
