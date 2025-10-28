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

        // 終了時刻を強制的にメニューの所要時間で再計算（手動変更を防ぐ）
        if (isset($data['menu_id']) && isset($data['start_time'])) {
            $menu = \App\Models\Menu::find($data['menu_id']);
            if ($menu && $menu->duration_minutes) {
                $startTime = \Carbon\Carbon::parse($data['start_time']);
                $data['end_time'] = $startTime->addMinutes($menu->duration_minutes)->format('H:i:s');
            }
        }

        // 顧客のアクティブなサブスク契約を自動設定
        // ステータスが'active'であれば有効とみなす（終了日を過ぎていても運用されているケースがあるため）
        if (isset($data['customer_id']) && isset($data['store_id']) && !isset($data['customer_subscription_id'])) {
            $activeSubscription = \App\Models\CustomerSubscription::where('customer_id', $data['customer_id'])
                ->where('store_id', $data['store_id'])
                ->where('status', 'active')
                ->first();

            if ($activeSubscription) {
                $data['customer_subscription_id'] = $activeSubscription->id;
            }
        }

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '予約を登録しました';
    }

    protected function afterCreate(): void
    {
        // 回数券での支払いの場合、自動的に回数券を消費
        if ($this->record->payment_method === 'ticket' && $this->record->customer_ticket_id) {
            $ticket = \App\Models\CustomerTicket::find($this->record->customer_ticket_id);

            if ($ticket && $ticket->canUse()) {
                $result = $ticket->use($this->record->id);

                if ($result) {
                    // 支払い済みに設定
                    $this->record->update([
                        'paid_with_ticket' => true,
                        'payment_status' => 'paid',
                    ]);

                    Notification::make()
                        ->title('回数券を使用しました')
                        ->body("{$ticket->plan_name} - 残り{$ticket->fresh()->remaining_count}回")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('回数券の使用に失敗しました')
                        ->body('回数券が期限切れまたは使い切りです')
                        ->warning()
                        ->send();
                }
            }
        }

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