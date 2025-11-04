<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('create_medical_record')
                ->label(function () {
                    // カルテが既に存在する場合は「カルテ編集」、存在しない場合は「カルテ作成」
                    $existingRecord = \App\Models\MedicalRecord::where('reservation_id', $this->record->id)->first();
                    return $existingRecord ? 'カルテ編集' : 'カルテ作成';
                })
                ->icon('heroicon-o-document-text')
                ->color(function () {
                    // カルテが既に存在する場合は「info」、存在しない場合は「success」
                    $existingRecord = \App\Models\MedicalRecord::where('reservation_id', $this->record->id)->first();
                    return $existingRecord ? 'info' : 'success';
                })
                ->url(function () {
                    // カルテが既に存在するかチェック
                    $existingRecord = \App\Models\MedicalRecord::where('reservation_id', $this->record->id)->first();

                    if ($existingRecord) {
                        // 既存のカルテを編集
                        return route('filament.admin.resources.medical-records.edit', [
                            'record' => $existingRecord->id
                        ]);
                    } else {
                        // 新しいカルテを作成
                        return route('filament.admin.resources.medical-records.create', [
                            'customer_id' => $this->record->customer_id,
                            'reservation_id' => $this->record->id
                        ]);
                    }
                })
                ->visible(fn (): bool => $this->record->status === 'completed'),
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

    // Override the form to use a simpler version for debugging
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('reservation_number')
                            ->label('予約番号')
                            ->disabled(),
                        Forms\Components\Select::make('store_id')
                            ->label('店舗')
                            ->options(\App\Models\Store::pluck('name', 'id'))
                            ->required(),
                        Forms\Components\Select::make('customer_id')
                            ->label('顧客')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                $user = auth()->user();
                                $dbDriver = \DB::connection()->getDriverName();
                                $search = trim($search);

                                // ベースクエリ
                                $query = \App\Models\Customer::query();

                                // 検索条件
                                if ($dbDriver === 'mysql') {
                                    $query->where(function ($q) use ($search) {
                                        $q->where('last_name', 'like', "%{$search}%")
                                          ->orWhere('first_name', 'like', "%{$search}%")
                                          ->orWhere('last_name_kana', 'like', "%{$search}%")
                                          ->orWhere('first_name_kana', 'like', "%{$search}%")
                                          ->orWhere('phone', 'like', "%{$search}%")
                                          ->orWhere('email', 'like', "%{$search}%")
                                          ->orWhereRaw('CONCAT(last_name, first_name) LIKE ?', ["%{$search}%"])
                                          ->orWhereRaw('CONCAT(last_name, " ", first_name) LIKE ?', ["%{$search}%"]);
                                    });
                                } else {
                                    $query->where(function ($q) use ($search) {
                                        $q->where('last_name', 'like', "%{$search}%")
                                          ->orWhere('first_name', 'like', "%{$search}%")
                                          ->orWhere('last_name_kana', 'like', "%{$search}%")
                                          ->orWhere('first_name_kana', 'like', "%{$search}%")
                                          ->orWhere('phone', 'like', "%{$search}%")
                                          ->orWhere('email', 'like', "%{$search}%")
                                          ->orWhereRaw('(last_name || first_name) LIKE ?', ["%{$search}%"])
                                          ->orWhereRaw('(last_name || " " || first_name) LIKE ?', ["%{$search}%"]);
                                    });
                                }

                                return $query->limit(50)->get()->mapWithKeys(function ($customer) {
                                    $label = ($customer->last_name ?? '') . ' ' . ($customer->first_name ?? '') . ' (' . ($customer->phone ?? '') . ')';
                                    return [$customer->id => $label];
                                });
                            })
                            ->getOptionLabelUsing(function ($value) {
                                $customer = \App\Models\Customer::find($value);
                                if (!$customer) return $value;
                                return ($customer->last_name ?? '') . ' ' . ($customer->first_name ?? '') . ' (' . ($customer->phone ?? '') . ')';
                            })
                            ->required(),
                        Forms\Components\Select::make('menu_id')
                            ->label('メニュー')
                            ->options(\App\Models\Menu::where('is_available', true)->pluck('name', 'id'))
                            ->required(),
                        Forms\Components\Select::make('staff_id')
                            ->label('担当スタッフ')
                            ->options(\App\Models\User::where('is_active_staff', true)->pluck('name', 'id'))
                            ->placeholder('未定'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('予約詳細')
                    ->schema([
                        Forms\Components\DatePicker::make('reservation_date')
                            ->label('予約日')
                            ->required(),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('開始時刻')
                            ->required(),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('終了時刻')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('ステータス')
                            ->options([
                                'booked' => '予約済み',
                                'completed' => '完了',
                                'no_show' => '来店なし',
                                'cancelled' => 'キャンセル',
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('備考')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('備考')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        try {
            // Eager load relationships to prevent N+1 queries and ensure all data is available
            $this->record->load(['customer', 'menu', 'store', 'staff', 'reservationOptions.menuOption']);
        } catch (\Exception $e) {
            \Log::error('予約編集ページでリレーション読み込みエラー: ' . $e->getMessage());
            // エラーが発生してもページを表示できるよう、基本リレーションのみ読み込み
            $this->record->load(['customer', 'menu', 'store']);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 終了時刻を強制的にメニューの所要時間で再計算（手動変更を防ぐ）
        if (isset($data['menu_id']) && isset($data['start_time'])) {
            $menu = \App\Models\Menu::find($data['menu_id']);
            if ($menu && $menu->duration_minutes) {
                $startTime = \Carbon\Carbon::parse($data['start_time']);
                $data['end_time'] = $startTime->addMinutes($menu->duration_minutes)->format('H:i:s');
            }
        }

        // 顧客のアクティブなサブスク契約を自動設定（共通サービスを使用）
        if (isset($data['customer_id']) && isset($data['store_id']) && !isset($data['customer_subscription_id'])) {
            $binder = app(\App\Services\ReservationSubscriptionBinder::class);
            $data = $binder->bind($data, 'update');
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Handle the update
        $record->update($data);

        return $record;
    }
}