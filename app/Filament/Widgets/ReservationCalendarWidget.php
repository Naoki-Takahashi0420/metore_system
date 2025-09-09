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
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;

class ReservationCalendarWidget extends FullCalendarWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = '予約カレンダー';
    
    public ?int $selectedStoreId = null;
    
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
    
    #[On('store-changed')]
    public function updateStoreId($storeId, $date = null): void
    {
        $this->selectedStoreId = $storeId;
        // FullCalendarのイベントを再取得
        $this->refreshRecords();
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
                        ->content(fn ($record) => $record->reservation_date ? 
                            \Carbon\Carbon::parse($record->reservation_date)->format('Y年m月d日') : 'N/A'),
                    Forms\Components\Placeholder::make('time')
                        ->label('時間')
                        ->content(fn ($record) => 
                            \Carbon\Carbon::parse($record->start_time)->format('H:i') . ' - ' . 
                            \Carbon\Carbon::parse($record->end_time)->format('H:i')),
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
                                ->first();
                            return $existingRecord ? 'カルテ編集' : 'カルテ記入';
                        })
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->form([
                            Forms\Components\Hidden::make('reservation_id'),
                            Forms\Components\Hidden::make('customer_id'),
                            
                            Forms\Components\Tabs::make('Tabs')
                                ->tabs([
                                    // 基本情報タブ
                                    Forms\Components\Tabs\Tab::make('基本情報')
                                        ->schema([
                                            Forms\Components\Grid::make(2)
                                                ->schema([
                                                    Forms\Components\TextInput::make('handled_by')
                                                        ->label('対応者')
                                                        ->default(Auth::user()->name)
                                                        ->required(),
                                                    
                                                    Forms\Components\DatePicker::make('treatment_date')
                                                        ->label('施術日')
                                                        ->default(function (array $arguments) {
                                                            $reservation = Reservation::find($arguments['event']['id'] ?? null);
                                                            return $reservation?->reservation_date ?? now();
                                                        })
                                                        ->required(),
                                                ]),
                                        ]),
                                    
                                    // 顧客管理情報タブ
                                    Forms\Components\Tabs\Tab::make('顧客管理情報')
                                        ->schema([
                                            Forms\Components\Grid::make(2)
                                                ->schema([
                                                    Forms\Components\Select::make('payment_method')
                                                        ->label('支払い方法')
                                                        ->options([
                                                            'cash' => '現金',
                                                            'credit' => 'クレジットカード',
                                                            'paypay' => 'PayPay',
                                                            'bank_transfer' => '銀行振込',
                                                            'subscription' => 'サブスク',
                                                        ]),
                                                    
                                                    Forms\Components\Select::make('reservation_source')
                                                        ->label('来店経路')
                                                        ->options([
                                                            'hp' => 'ホームページ',
                                                            'phone' => '電話',
                                                            'line' => 'LINE',
                                                            'instagram' => 'Instagram',
                                                            'referral' => '紹介',
                                                            'walk_in' => '飛び込み',
                                                        ]),
                                                    
                                                    Forms\Components\Textarea::make('visit_purpose')
                                                        ->label('来店目的')
                                                        ->rows(2),
                                                    
                                                    Forms\Components\Textarea::make('workplace_address')
                                                        ->label('職場・住所')
                                                        ->rows(2),
                                                ]),
                                            
                                            Forms\Components\Grid::make(3)
                                                ->schema([
                                                    Forms\Components\Toggle::make('genetic_possibility')
                                                        ->label('遺伝の可能性'),
                                                    
                                                    Forms\Components\Toggle::make('has_astigmatism')
                                                        ->label('乱視'),
                                                    
                                                    Forms\Components\Textarea::make('eye_diseases')
                                                        ->label('目の病気')
                                                        ->placeholder('レーシック、白内障など')
                                                        ->rows(2)
                                                        ->columnSpan(3),
                                                ]),
                                            
                                            Forms\Components\Textarea::make('device_usage')
                                                ->label('スマホ・PC使用頻度')
                                                ->placeholder('1日何時間程度、仕事で使用など')
                                                ->rows(2),
                                        ]),
                                    
                                    // 視力記録タブ（簡易版）
                                    Forms\Components\Tabs\Tab::make('視力記録')
                                        ->schema([
                                            Forms\Components\Grid::make(2)
                                                ->schema([
                                                    Forms\Components\TextInput::make('intensity')
                                                        ->label('強度')
                                                        ->numeric()
                                                        ->minValue(1)
                                                        ->maxValue(50)
                                                        ->placeholder('1-50')
                                                        ->helperText('1（弱）〜 50（強）'),
                                                    
                                                    Forms\Components\TextInput::make('duration')
                                                        ->label('時間（分）')
                                                        ->numeric()
                                                        ->default(60)
                                                        ->suffix('分'),
                                                ]),
                                            
                                            // 施術前視力（裸眼）
                                            Forms\Components\Section::make('施術前視力 - 裸眼')
                                                ->schema([
                                                    Forms\Components\Grid::make(2)
                                                        ->schema([
                                                            Forms\Components\TextInput::make('before_naked_left')
                                                                ->label('左眼')
                                                                ->placeholder('0.5'),
                                                            
                                                            Forms\Components\TextInput::make('before_naked_right')
                                                                ->label('右眼')
                                                                ->placeholder('0.5'),
                                                        ]),
                                                ])
                                                ->collapsible(),
                                            
                                            // 施術前視力（矯正）
                                            Forms\Components\Section::make('施術前視力 - 矯正')
                                                ->schema([
                                                    Forms\Components\Grid::make(2)
                                                        ->schema([
                                                            Forms\Components\TextInput::make('before_corrected_left')
                                                                ->label('左眼')
                                                                ->placeholder('1.0'),
                                                            
                                                            Forms\Components\TextInput::make('before_corrected_right')
                                                                ->label('右眼')
                                                                ->placeholder('1.0'),
                                                        ]),
                                                ])
                                                ->collapsible()
                                                ->collapsed(),
                                            
                                            // 施術後視力（裸眼）
                                            Forms\Components\Section::make('施術後視力 - 裸眼')
                                                ->schema([
                                                    Forms\Components\Grid::make(2)
                                                        ->schema([
                                                            Forms\Components\TextInput::make('after_naked_left')
                                                                ->label('左眼')
                                                                ->placeholder('0.8'),
                                                            
                                                            Forms\Components\TextInput::make('after_naked_right')
                                                                ->label('右眼')
                                                                ->placeholder('0.8'),
                                                        ]),
                                                ])
                                                ->collapsible(),
                                            
                                            // 施術後視力（矯正）
                                            Forms\Components\Section::make('施術後視力 - 矯正')
                                                ->schema([
                                                    Forms\Components\Grid::make(2)
                                                        ->schema([
                                                            Forms\Components\TextInput::make('after_corrected_left')
                                                                ->label('左眼')
                                                                ->placeholder('1.2'),
                                                            
                                                            Forms\Components\TextInput::make('after_corrected_right')
                                                                ->label('右眼')
                                                                ->placeholder('1.2'),
                                                        ]),
                                                ])
                                                ->collapsible()
                                                ->collapsed(),
                                        ]),
                                ]),
                        ])
                        ->fillForm(function (array $arguments) {
                            $reservation = Reservation::with(['customer'])->find($arguments['event']['id'] ?? null);
                            if (!$reservation) return [];
                            
                            $existingRecord = MedicalRecord::where('reservation_id', $reservation->id)
                                ->first();
                            
                            if ($existingRecord) {
                                // 既存のカルテデータを整形
                                $data = $existingRecord->toArray();
                                
                                // vision_recordsがある場合は最新の記録から値を取得
                                if (!empty($data['vision_records']) && is_array($data['vision_records'])) {
                                    $latestRecord = end($data['vision_records']);
                                    $data = array_merge($data, $latestRecord);
                                }
                                
                                return $data;
                            }
                            
                            return [
                                'reservation_id' => $reservation->id,
                                'customer_id' => $reservation->customer_id,
                                'treatment_date' => $reservation->reservation_date,
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
                                ->first();
                            
                            // vision_recordsを構築
                            $visionRecord = [];
                            if (isset($data['intensity']) || isset($data['duration'])) {
                                $visionRecord = [
                                    'session' => 1,
                                    'date' => $data['treatment_date'] ?? now(),
                                    'intensity' => $data['intensity'] ?? null,
                                    'duration' => $data['duration'] ?? null,
                                    'before_naked_left' => $data['before_naked_left'] ?? null,
                                    'before_naked_right' => $data['before_naked_right'] ?? null,
                                    'before_corrected_left' => $data['before_corrected_left'] ?? null,
                                    'before_corrected_right' => $data['before_corrected_right'] ?? null,
                                    'after_naked_left' => $data['after_naked_left'] ?? null,
                                    'after_naked_right' => $data['after_naked_right'] ?? null,
                                    'after_corrected_left' => $data['after_corrected_left'] ?? null,
                                    'after_corrected_right' => $data['after_corrected_right'] ?? null,
                                ];
                            }
                            
                            // 視力関連のフィールドを削除してvision_recordsに移動
                            $medicalData = array_filter($data, function($key) {
                                return !in_array($key, [
                                    'intensity', 'duration',
                                    'before_naked_left', 'before_naked_right',
                                    'before_corrected_left', 'before_corrected_right',
                                    'after_naked_left', 'after_naked_right',
                                    'after_corrected_left', 'after_corrected_right'
                                ]);
                            }, ARRAY_FILTER_USE_KEY);
                            
                            if (!empty($visionRecord)) {
                                $medicalData['vision_records'] = [$visionRecord];
                            }
                            
                            if ($existingRecord) {
                                // 既存のvision_recordsがある場合はマージ
                                if (!empty($visionRecord) && !empty($existingRecord->vision_records)) {
                                    $existingRecords = is_string($existingRecord->vision_records) 
                                        ? json_decode($existingRecord->vision_records, true) 
                                        : $existingRecord->vision_records;
                                    $medicalData['vision_records'] = array_merge($existingRecords, [$visionRecord]);
                                }
                                
                                $existingRecord->update($medicalData);
                                Notification::make()
                                    ->title('カルテを更新しました')
                                    ->success()
                                    ->send();
                            } else {
                                $medicalData['staff_id'] = auth()->id();
                                $medicalData['created_by'] = auth()->id();
                                MedicalRecord::create($medicalData);
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