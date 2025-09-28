<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('cancel_reservation')
                ->label('予約をキャンセル')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'cancelled',
                        'cancel_reason' => '管理画面からキャンセル',
                        'cancelled_at' => now(),
                    ]);

                    Notification::make()
                        ->title('予約がキャンセルされました')
                        ->success()
                        ->send();

                    $this->redirectRoute('filament.admin.resources.reservations.index');
                })
                ->visible(fn (): bool => !in_array($this->record->status, ['cancelled', 'completed', 'no_show'])),
            Actions\DeleteAction::make()
                ->label('削除'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Eager load relationships to prevent N+1 queries
        $this->record->load(['customer', 'menu', 'store', 'staff', 'optionMenus']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Handle the update
        $record->update($data);

        return $record;
    }
}