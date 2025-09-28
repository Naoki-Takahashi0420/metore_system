<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function mount(int | string $record): void
    {
        // Eager load all necessary relationships
        $this->record = static::getResource()::getEloquentQuery()
            ->with(['customer', 'menu', 'store', 'staff', 'optionMenus'])
            ->findOrFail($record);

        // Authorization check
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $reservation = $this->record;

        // スーパーアドミンは全予約を閲覧可能
        if ($user->hasRole('super_admin')) {
            // Continue with mount
            static::authorizeResourceAccess();
            $this->fillForm();
            return;
        }

        // オーナーは管理可能店舗の予約のみ閲覧可能
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id')->toArray();
            if (!in_array($reservation->store_id, $manageableStoreIds)) {
                abort(403);
            }
            static::authorizeResourceAccess();
            $this->fillForm();
            return;
        }

        // 店長・スタッフは所属店舗の予約のみ閲覧可能
        if ($user->hasRole(['manager', 'staff'])) {
            if ($user->store_id !== $reservation->store_id) {
                abort(403);
            }
            static::authorizeResourceAccess();
            $this->fillForm();
            return;
        }

        abort(403);
    }
}