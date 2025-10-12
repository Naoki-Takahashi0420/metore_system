<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\BlockedTimePeriod;
use App\Models\Reservation;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('change_line')
                ->label('ライン変更')
                ->icon('heroicon-o-arrows-right-left')
                ->color('info')
                ->visible(fn () => in_array($this->record->status, ['booked', 'completed']))
                ->form([
                    Forms\Components\Select::make('line_type')
                        ->label('移動先のライン')
                        ->options([
                            'main' => 'メイン',
                            'sub' => 'サブ',
                        ])
                        ->default(fn () => $this->record->line_type)
                        ->required()
                        ->helperText('移動先のラインを選択してください'),
                ])
                ->action(function (array $data) {
                    $reservation = $this->record;
                    $newLine = $data['line_type'];

                    // 同じラインに移動しようとしている場合はエラー
                    if ($reservation->line_type === $newLine) {
                        Notification::make()
                            ->danger()
                            ->title('エラー')
                            ->body('既に選択されたラインに配置されています。')
                            ->send();
                        return;
                    }

                    // 移動先のラインで予約ブロックとの重複チェック
                    $startTime = Carbon::parse($reservation->start_time);
                    $endTime = Carbon::parse($reservation->end_time);
                    $reservationDate = Carbon::parse($reservation->reservation_date);

                    $hasBlockConflict = BlockedTimePeriod::where('store_id', $reservation->store_id)
                        ->where('line_type', $newLine)
                        ->whereDate('blocked_date', $reservationDate)
                        ->where(function ($query) use ($startTime, $endTime) {
                            $endTimeStr = strlen($endTime->format('H:i')) === 5 ? $endTime->format('H:i') . ':00' : $endTime->format('H:i:s');
                            $startTimeStr = strlen($startTime->format('H:i')) === 5 ? $startTime->format('H:i') . ':00' : $startTime->format('H:i:s');
                            $query->whereRaw('time(start_time) < time(?)', [$endTimeStr])
                                  ->whereRaw('time(end_time) > time(?)', [$startTimeStr]);
                        })
                        ->exists();

                    if ($hasBlockConflict) {
                        Notification::make()
                            ->danger()
                            ->title('予約ブロックと重複')
                            ->body('移動先のラインには予約ブロックがあるため移動できません。')
                            ->send();
                        return;
                    }

                    // 移動先のラインで既存予約との重複チェック
                    $hasReservationConflict = Reservation::where('store_id', $reservation->store_id)
                        ->where('line_type', $newLine)
                        ->where('id', '!=', $reservation->id)
                        ->whereDate('reservation_date', $reservationDate)
                        ->whereNotIn('status', ['cancelled', 'canceled'])
                        ->where(function ($query) use ($startTime, $endTime) {
                            $query->where(function ($q) use ($startTime, $endTime) {
                                // 既存予約の開始時刻が新しい予約時間内
                                $q->whereBetween('start_time', [$startTime, $endTime])
                                  ->orWhereBetween('end_time', [$startTime, $endTime])
                                  // または新しい予約が既存予約時間内
                                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                                      $q2->where('start_time', '<=', $startTime)
                                         ->where('end_time', '>=', $endTime);
                                  });
                            });
                        })
                        ->exists();

                    if ($hasReservationConflict) {
                        Notification::make()
                            ->danger()
                            ->title('予約が重複')
                            ->body('移動先のラインには既に予約があるため移動できません。')
                            ->send();
                        return;
                    }

                    // ライン変更を実行
                    $reservation->update(['line_type' => $newLine]);

                    $lineName = $newLine === 'main' ? 'メイン' : 'サブ';
                    Notification::make()
                        ->success()
                        ->title('ライン変更完了')
                        ->body("ラインを「{$lineName}」に変更しました。")
                        ->send();

                    // ページをリロード
                    redirect()->to(static::getResource()::getUrl('view', ['record' => $reservation->id]));
                }),
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