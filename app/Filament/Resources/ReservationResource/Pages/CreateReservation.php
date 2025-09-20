<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 予約番号を自動生成
        if (!isset($data['reservation_number'])) {
            $data['reservation_number'] = 'R' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        // 電話予約の場合、sourceを自動設定
        if (request()->get('source') === 'phone') {
            $data['source'] = 'phone';
        }

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '予約を登録しました';
    }

    protected function afterCreate(): void
    {
        // 予約確認の通知
        if ($this->record->source === 'phone') {
            Notification::make()
                ->title('電話予約を登録しました')
                ->body("予約番号: {$this->record->reservation_number}")
                ->success()
                ->send();
        }
    }

    public function mount(): void
    {
        parent::mount();

        $defaultValues = [];

        // カルテから来た場合（customer_idとstore_idがURLパラメータにある）
        if (request()->has('customer_id')) {
            $defaultValues['customer_id'] = request()->get('customer_id');
        }

        if (request()->has('store_id')) {
            $defaultValues['store_id'] = request()->get('store_id');
        }

        // 電話予約の場合のデフォルト値設定
        if (request()->get('source') === 'phone') {
            $defaultValues['source'] = 'phone';
            $defaultValues['status'] = 'booked';
            $defaultValues['reservation_date'] = now()->addDay()->format('Y-m-d');
        }

        // デフォルト値が設定されている場合はフォームに反映
        if (!empty($defaultValues)) {
            $this->form->fill($defaultValues);
        }
    }
}