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

        // メニュー情報から必要なデータを設定
        if (isset($data['menu_id'])) {
            $menu = \App\Models\Menu::find($data['menu_id']);
            if ($menu) {
                // 料金情報を設定
                $data['monthly_price'] = $menu->subscription_monthly_price ?? 0;
                $data['monthly_limit'] = $menu->max_monthly_usage ?? 0;

                // プラン情報を設定
                if (!isset($data['plan_name'])) {
                    $data['plan_name'] = $menu->name;
                }
                if (!isset($data['plan_type'])) {
                    $data['plan_type'] = 'MENU_' . $menu->id;
                }

                // 契約期間を設定
                $data['contract_months'] = $menu->subscription_contract_months ?? 12;

                // 契約終了日が設定されていない場合は計算
                if (!isset($data['end_date']) && isset($data['service_start_date'])) {
                    $endDate = \Carbon\Carbon::parse($data['service_start_date'])
                        ->addMonths($data['contract_months'])
                        ->subDay();
                    $data['end_date'] = $endDate->format('Y-m-d');
                }

                // 次回請求日が設定されていない場合は計算
                if (!isset($data['next_billing_date']) && isset($data['billing_start_date'])) {
                    $nextBilling = \Carbon\Carbon::parse($data['billing_start_date'])->addMonth();
                    $data['next_billing_date'] = $nextBilling->format('Y-m-d');
                }
            }
        }

        // デフォルト値を設定
        $data['current_month_visits'] = 0;
        $data['reset_day'] = 1;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // 作成後、顧客編集画面に戻る
        if ($this->record->customer_id) {
            return route('filament.admin.resources.customers.edit', ['record' => $this->record->customer_id]);
        }

        // デフォルトは一覧画面
        return $this->getResource()::getUrl('index');
    }
}
