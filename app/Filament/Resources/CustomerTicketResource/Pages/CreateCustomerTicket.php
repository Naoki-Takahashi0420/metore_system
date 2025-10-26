<?php

namespace App\Filament\Resources\CustomerTicketResource\Pages;

use App\Filament\Resources\CustomerTicketResource;
use App\Models\TicketPlan;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerTicket extends CreateRecord
{
    protected static string $resource = CustomerTicketResource::class;

    protected static bool $shouldCheckUnsavedChanges = true;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // プランIDから必要な情報を取得して設定
        if (!empty($data['ticket_plan_id'])) {
            $plan = TicketPlan::find($data['ticket_plan_id']);
            if ($plan) {
                $data['plan_name'] = $plan->name;
                $data['total_count'] = $plan->ticket_count;
                $data['purchase_price'] = $plan->price;

                // 有効期限を計算
                $purchaseDate = $data['purchased_at'] ?? now();
                $data['expires_at'] = $this->calculateExpiryDate($plan, $purchaseDate);
            }
        }

        // デフォルト値を設定
        $data['used_count'] = $data['used_count'] ?? 0;
        $data['status'] = 'active';

        return $data;
    }

    protected function calculateExpiryDate(TicketPlan $plan, $purchaseDate): ?\Carbon\Carbon
    {
        if (!$plan->validity_months && !$plan->validity_days) {
            return null; // 無期限
        }

        $expiryDate = \Carbon\Carbon::parse($purchaseDate);

        if ($plan->validity_months) {
            $expiryDate->addMonths($plan->validity_months);
        }

        if ($plan->validity_days) {
            $expiryDate->addDays($plan->validity_days);
        }

        return $expiryDate->endOfDay();
    }

    protected function afterCreate(): void
    {
        \Filament\Notifications\Notification::make()
            ->title('回数券を発行しました')
            ->body("{$this->record->customer->full_name}様に{$this->record->plan_name}を発行しました。")
            ->success()
            ->send();
    }
}
