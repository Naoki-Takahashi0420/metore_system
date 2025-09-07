<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Store;
use App\Models\Menu;
use App\Models\MedicalRecord;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Notifications\Notification;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\Actions;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Filament\Forms\Components\Select;

class ReservationCalendarWidget extends FullCalendarWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = '予約カレンダー';
    
    public ?int $selectedStoreId = null;
    
    protected $listeners = ['storeChanged' => 'updateStoreId'];
    
    public function mount(): void
    {
        // 親ページから店舗IDを受け取る、または初期値を設定
        if (!$this->selectedStoreId) {
            $user = auth()->user();
            
            if ($user->hasRole('super_admin')) {
                // スーパーアドミンの場合、最初の店舗を選択
                $firstStore = Store::first();
                $this->selectedStoreId = $firstStore?->id;
            } else {
                // 店舗管理者の場合、自分の店舗IDを設定
                $this->selectedStoreId = $user->store_id;
            }
        }
    }
    
    public function updateStoreId($storeId): void
    {
        $this->selectedStoreId = $storeId;
        // カレンダーを再描画
        $this->refreshEvents();
    }
    
    
    public function fetchEvents(array $info): array
    {
        $start = Carbon::parse($info['start']);
        $end = Carbon::parse($info['end']);
        
        $query = Reservation::with(['customer', 'store', 'menu'])
            ->whereBetween('reservation_date', [$start, $end]);
        
        // 店舗フィルタリング
        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }
        
        $reservations = $query->get();
        
        return $reservations->map(function (Reservation $reservation) {
            // start_timeフィールドを使用
            $startTime = Carbon::parse($reservation->start_time)->format('H:i');
            $endTime = Carbon::parse($reservation->end_time)->format('H:i');
            $customerName = $reservation->customer ? 
                $reservation->customer->last_name . ' ' . $reservation->customer->first_name : 
                '顧客情報なし';
            $storeName = $reservation->store ? $reservation->store->name : '店舗未設定';
            $menuName = $reservation->menu ? $reservation->menu->name : 'メニュー未設定';
            
            // 24時間以内の予約かチェック
            $isNewReservation = Carbon::parse($reservation->created_at)->diffInHours(now()) <= 24;
            $newBadge = $isNewReservation ? '🔴 ' : '';  // 赤い丸で新規を表現
            
            // ステータスに応じて色を設定
            $backgroundColor = match($reservation->status) {
                'booked' => '#3b82f6', // 予約済み: 青
                'completed' => '#9ca3af', // 完了: グレー
                'cancelled' => '#fca5a5', // キャンセル: 薄い赤
                default => '#3b82f6', // デフォルト: 青
            };
            
            // 日付と時間を正しく結合
            $reservationDate = Carbon::parse($reservation->reservation_date)->format('Y-m-d');
            $startTimeStr = is_string($reservation->start_time) ? 
                Carbon::parse($reservation->start_time)->format('H:i:s') : 
                $reservation->start_time->format('H:i:s');
            $endTimeStr = is_string($reservation->end_time) ? 
                Carbon::parse($reservation->end_time)->format('H:i:s') : 
                $reservation->end_time->format('H:i:s');
            
            $startDateTime = $reservationDate . ' ' . $startTimeStr;
            $endDateTime = $reservationDate . ' ' . $endTimeStr;
            
            // より多くの情報を表示
            $phone = $reservation->customer->phone ?? '';
            $reservationNumber = $reservation->reservation_number ?? '';
            $statusText = match($reservation->status) {
                'booked' => '予約済み',
                'completed' => '完了',
                'cancelled' => 'キャンセル',
                default => $reservation->status,
            };
            
            // シンプルな表示形式に変更
            return [
                'id' => $reservation->id,
                'title' => sprintf(
                    "%s%s様\n%s",
                    $newBadge,
                    $customerName,
                    $menuName
                ),
                'start' => $startDateTime,
                'end' => $endDateTime,
                'backgroundColor' => $backgroundColor,
                'borderColor' => $backgroundColor,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'customer' => $customerName,
                    'phone' => $phone,
                    'menu' => $menuName,
                    'store' => $storeName,
                    'status' => $reservation->status,
                    'statusText' => $statusText,
                    'notes' => $reservation->notes,
                    'reservationNumber' => $reservationNumber,
                    'isNew' => $isNewReservation,
                    'totalAmount' => $reservation->total_amount,
                    'guestCount' => $reservation->guest_count,
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                ],
            ];
        })->toArray();
    }
    
    
    public $showModal = false;
    public $selectedReservation = null;

    public function openReservationDetail($reservationId)
    {
        $reservation = Reservation::with(['customer', 'store', 'menu'])->find($reservationId);
        
        if ($reservation) {
            $this->selectedReservation = $reservation;
            $this->showModal = true;
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedReservation = null;
    }

    // 親クラスのrenderメソッドを使用

    protected function modalActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('予約詳細')
                ->modalHeading(function (array $arguments) {
                    $reservation = Reservation::with(['customer'])->find($arguments['event']['id'] ?? null);
                    return $reservation ? '予約詳細 - ' . $reservation->customer->last_name . ' ' . $reservation->customer->first_name . '様' : '予約詳細';
                })
                ->modalWidth('md')
                ->form([
                    Forms\Components\Placeholder::make('reservation_number')
                        ->label('予約番号')
                        ->content(fn ($record) => $record->reservation_number ?? 'N/A'),
                    Forms\Components\Placeholder::make('reservation_date')
                        ->label('予約日')
                        ->content(fn ($record) => $record->reservation_date?->format('Y年m月d日') ?? 'N/A'),
                    Forms\Components\Placeholder::make('time')
                        ->label('時間')
                        ->content(fn ($record) => $record->start_time . ' - ' . $record->end_time),
                    Forms\Components\Placeholder::make('customer_name')
                        ->label('顧客名')
                        ->content(fn ($record) => $record->customer ? 
                            $record->customer->last_name . ' ' . $record->customer->first_name : 'N/A'),
                    Forms\Components\Placeholder::make('customer_phone')
                        ->label('電話番号')
                        ->content(fn ($record) => $record->customer?->phone ?? 'N/A'),
                    Forms\Components\Placeholder::make('store_name')
                        ->label('店舗')
                        ->content(fn ($record) => $record->store?->name ?? 'N/A'),
                    Forms\Components\Placeholder::make('menu_name')
                        ->label('メニュー')
                        ->content(fn ($record) => $record->menu?->name ?? 'N/A'),
                    Forms\Components\Placeholder::make('total_amount')
                        ->label('料金')
                        ->content(fn ($record) => '¥' . number_format($record->total_amount)),
                    Forms\Components\Placeholder::make('status')
                        ->label('ステータス')
                        ->content(fn ($record) => match($record->status) {
                            'confirmed' => '確定',
                            'pending' => '保留',
                            'cancelled' => 'キャンセル',
                            'completed' => '完了',
                            default => $record->status
                        }),
                ])
                ->modalActions([
                    // カルテ記入/編集ボタン
                    \Filament\Actions\Action::make('medical_record')
                        ->label(function (array $arguments) {
                            $reservation = Reservation::find($arguments['event']['id'] ?? null);
                            
                            if (!$reservation) {
                                return 'カルテ記入';
                            }
                            
                            $existingRecord = MedicalRecord::where('reservation_id', $reservation->id)
                                ->where('record_date', $reservation->reservation_date)
                                ->first();
                            return $existingRecord ? 'カルテ編集' : 'カルテ記入';
                        })
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->form([
                            Forms\Components\Hidden::make('reservation_id'),
                            Forms\Components\Hidden::make('customer_id'),
                            Forms\Components\DatePicker::make('record_date')
                                ->label('記録日')
                                ->required()
                                ->default(function (array $arguments) {
                                    $reservation = Reservation::find($arguments['event']['id'] ?? null);
                                    return $reservation?->reservation_date;
                                }),
                            Forms\Components\Textarea::make('chief_complaint')
                                ->label('主訴')
                                ->rows(2)
                                ->placeholder('患者様の主な訴えを記入してください'),
                            Forms\Components\Textarea::make('symptoms')
                                ->label('症状')
                                ->rows(3)
                                ->placeholder('詳細な症状を記入してください'),
                            Forms\Components\Textarea::make('diagnosis')
                                ->label('診断')
                                ->rows(2)
                                ->placeholder('診断結果を記入してください'),
                            Forms\Components\Textarea::make('treatment')
                                ->label('施術内容')
                                ->rows(3)
                                ->placeholder('実施した施術内容を記入してください'),
                            Forms\Components\Textarea::make('prescription')
                                ->label('処方・指導')
                                ->rows(2)
                                ->placeholder('処方や生活指導を記入してください'),
                            Forms\Components\DatePicker::make('next_visit_date')
                                ->label('次回来院予定日'),
                            Forms\Components\Textarea::make('notes')
                                ->label('備考')
                                ->rows(2)
                                ->placeholder('その他特記事項があれば記入してください'),
                        ])
                        ->fillForm(function (array $arguments) {
                            $reservation = Reservation::with(['customer'])->find($arguments['event']['id'] ?? null);
                            if (!$reservation) return [];
                            
                            $existingRecord = MedicalRecord::where('reservation_id', $reservation->id)
                                ->where('record_date', $reservation->reservation_date)
                                ->first();
                            
                            if ($existingRecord) {
                                return $existingRecord->toArray();
                            }
                            
                            return [
                                'reservation_id' => $reservation->id,
                                'customer_id' => $reservation->customer_id,
                                'record_date' => $reservation->reservation_date,
                            ];
                        })
                        ->action(function (array $data, array $arguments) {
                            $reservation = Reservation::find($arguments['event']['id'] ?? null);
                            
                            if (!$reservation) {
                                Notification::make()
                                    ->title('予約が見つかりません')
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            $existingRecord = MedicalRecord::where('reservation_id', $reservation->id)
                                ->where('record_date', $reservation->reservation_date)
                                ->first();
                            
                            if ($existingRecord) {
                                $existingRecord->update($data);
                                Notification::make()
                                    ->title('カルテを更新しました')
                                    ->success()
                                    ->send();
                            } else {
                                $data['staff_id'] = auth()->id();
                                $data['created_by'] = auth()->id();
                                MedicalRecord::create($data);
                                Notification::make()
                                    ->title('カルテを記入しました')
                                    ->success()
                                    ->send();
                            }
                            
                            // 予約ステータスを来店済みに更新
                            if ($reservation && $reservation->status !== 'visited') {
                                $reservation->update(['status' => 'visited']);
                            }
                        })
                        ->modalHeading(function (array $arguments) {
                            $reservation = Reservation::with(['customer'])->find($arguments['event']['id'] ?? null);
                            
                            if (!$reservation || !$reservation->customer) {
                                return 'カルテ記入';
                            }
                            
                            $existingRecord = MedicalRecord::where('reservation_id', $reservation->id)
                                ->where('record_date', $reservation->reservation_date)
                                ->first();
                            
                            $customerName = $reservation->customer->last_name . ' ' . $reservation->customer->first_name . '様';
                            
                            return ($existingRecord ? 'カルテ編集' : 'カルテ記入') . ' - ' . $customerName;
                        })
                        ->modalWidth('xl'),
                ])
                ->fillForm(function (array $arguments) {
                    $reservation = Reservation::with(['customer', 'store', 'menu'])
                        ->find($arguments['event']['id'] ?? null);
                    return $reservation ? $reservation->toArray() : [];
                })
        ];
    }
    
    public function getEventRecord(array $data): ?Model
    {
        return Reservation::find($data['id']);
    }
    
    public function getModel(): string
    {
        return Reservation::class;
    }
    
    protected function getOptions(): array
    {
        return [
            'locale' => 'ja',
            'firstDay' => 1, // 月曜始まり
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'initialView' => 'timeGridWeek',
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '21:00:00',
            'slotDuration' => '00:30:00',
            'slotLabelInterval' => '01:00',
            'weekNumbers' => false,
            'weekNumberCalculation' => 'ISO',
            'height' => 700,
            'contentHeight' => 'auto',
            'aspectRatio' => 1.8,
            'nowIndicator' => true,
            'selectable' => true,
            'selectMirror' => true,
            'businessHours' => [
                'daysOfWeek' => [1, 2, 3, 4, 5, 6],
                'startTime' => '09:00',
                'endTime' => '20:00',
            ],
            'displayEventTime' => true,
            'displayEventEnd' => true,
            'eventTimeFormat' => [
                'hour' => '2-digit',
                'minute' => '2-digit',
                'meridiem' => false,
            ],
            // 大量予約対応の設定
            'dayMaxEvents' => 6, // 1日最大6件まで表示
            'eventMaxStack' => 4, // 同時間帯最大4件まで積み重ね
            'moreLinkText' => '他 +{0} 件',
            'eventOverlap' => true, // 重複表示を許可
            'slotEventOverlap' => true,
            'eventOrder' => 'start,title', // 開始時間順でソート
            
            // 月表示での設定
            'dayMaxEventRows' => 4, // 月表示で1日最大4行
            'moreLinkClick' => 'popover', // 「+more」クリック時にポップオーバー表示
            
            // イベントの文字サイズ調整
            'eventDisplay' => 'block',
            'eventTextColor' => '#ffffff',
            
            'buttonText' => [
                'today' => '今日',
                'month' => '月',
                'week' => '週',
                'day' => '日',
                'list' => 'リスト',
            ],
            'allDayText' => '終日',
            
            // イベントクリック処理を有効化
            'eventClick' => true,
            
            'editable' => false,
            
            // ツールチップ設定（追加情報表示）
            'eventDidMount' => 'function(info) {
                var startTime = info.event.extendedProps.startTime || "";
                var endTime = info.event.extendedProps.endTime || "";
                info.el.setAttribute("title", 
                    startTime + " - " + endTime + "\\n" +
                    info.event.extendedProps.customer + "様\\n" +
                    info.event.extendedProps.menu + "\\n" +
                    "📞 " + (info.event.extendedProps.phone || "電話番号なし") + "\\n" +
                    "💰 " + (info.event.extendedProps.totalAmount || "未設定") + "円\\n" +
                    "👥 " + (info.event.extendedProps.guestCount || 1) + "名"
                );
                info.el.style.cursor = "pointer";
            }',
        ];
    }
}