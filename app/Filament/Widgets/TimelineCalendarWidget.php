<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Filament\Forms\Components\Select;
use Livewire\Attributes\Reactive;

class TimelineCalendarWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.timeline-calendar-widget';

    protected static ?string $heading = '予約タイムライン';

    // 60秒ごとに自動更新
    protected static ?string $pollingInterval = '60s';

    public ?int $selectedStoreId = null;
    public string $currentDate;
    public array $timeSlots = [];
    public array $reservations = [];
    public array $stores = [];

    // 営業時間（動的に設定）
    private int $businessHoursStart = 9;
    private int $businessHoursEnd = 21;
    
    public function mount(): void
    {
        // 初期設定
        $this->currentDate = now()->format('Y-m-d');
        
        $user = auth()->user();
        
        if ($user->hasRole('super_admin')) {
            $this->stores = Store::orderBy('name')->get()->toArray();
            $firstStore = Store::first();
            $this->selectedStoreId = $firstStore?->id;
        } else {
            $this->selectedStoreId = $user->store_id;
            if ($this->selectedStoreId) {
                $this->stores = [Store::find($this->selectedStoreId)->toArray()];
            }
        }
        
        $this->generateTimeSlots();
        $this->loadReservations();
    }
    
    public function updatedSelectedStoreId()
    {
        $this->generateTimeSlots(); // 営業時間が変わる可能性があるので再生成
        $this->loadReservations();
    }

    public function updatedCurrentDate()
    {
        $this->generateTimeSlots(); // 曜日が変わると営業時間が変わる可能性があるので再生成
        $this->loadReservations();
    }

    public function refreshData()
    {
        $this->loadReservations();
        $this->dispatch('refreshed');
    }

    public function changeDate($direction)
    {
        $currentDate = Carbon::parse($this->currentDate);
        
        if ($direction === 'prev') {
            $this->currentDate = $currentDate->subDay()->format('Y-m-d');
        } else {
            $this->currentDate = $currentDate->addDay()->format('Y-m-d');
        }
        
        $this->loadReservations();
    }
    
    public function goToToday()
    {
        $this->currentDate = now()->format('Y-m-d');
        $this->loadReservations();
    }
    
    private function generateTimeSlots()
    {
        $this->timeSlots = [];

        // 選択された店舗の予約間隔を取得
        $slotInterval = 30; // デフォルト
        $startHour = 9;
        $endHour = 21;

        if ($this->selectedStoreId) {
            $store = Store::find($this->selectedStoreId);
            $slotInterval = $store->reservation_slot_duration ?? 30;

            // 店舗の営業時間を取得
            $businessHours = $this->getBusinessHoursForDate($store, $this->currentDate);
            if ($businessHours) {
                $startHour = (int) substr($businessHours['open_time'], 0, 2);
                $endHour = (int) substr($businessHours['close_time'], 0, 2);
            }
        }

        // 営業時間を保存（位置計算用）
        $this->businessHoursStart = $startHour;
        $this->businessHoursEnd = $endHour;

        $start = Carbon::createFromTime($startHour, 0);
        $end = Carbon::createFromTime($endHour, 0);

        while ($start <= $end) {
            $this->timeSlots[] = $start->format('H:i');
            $start->addMinutes($slotInterval);
        }
    }

    /**
     * 指定日の営業時間を取得
     */
    private function getBusinessHoursForDate($store, $date)
    {
        if (!$store->business_hours || !is_array($store->business_hours)) {
            return null;
        }

        $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);

        foreach ($store->business_hours as $hours) {
            if (isset($hours['day']) && $hours['day'] === $dayOfWeek) {
                if (!empty($hours['is_closed'])) {
                    return null; // 定休日
                }
                return $hours;
            }
        }

        return null;
    }
    
    private function loadReservations()
    {
        $query = Reservation::with(['customer', 'store', 'menu'])
            ->whereDate('reservation_date', $this->currentDate);
        
        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }
        
        $reservations = $query->orderBy('start_time')->get();
        
        $this->reservations = $reservations->map(function (Reservation $reservation) {
            $customerName = $reservation->customer ? 
                $reservation->customer->last_name . ' ' . $reservation->customer->first_name : 
                '顧客情報なし';
            
            $startTime = Carbon::parse($reservation->start_time);
            $endTime = Carbon::parse($reservation->end_time);
            $duration = $startTime->diffInMinutes($endTime);
            
            // 24時間以内の予約かチェック
            $isNewReservation = Carbon::parse($reservation->created_at)->diffInHours(now()) <= 24;
            
            // ステータスに応じて色とアイコンを設定
            [$color, $statusIcon, $statusText] = match($reservation->status) {
                'booked' => $isNewReservation ? ['#1d4ed8', '📅', '予約済み'] : ['#3b82f6', '📅', '予約済み'],
                'visited' => $isNewReservation ? ['#059669', '✅', '来店済み'] : ['#10b981', '✅', '来店済み'],
                'cancelled' => ['#ef4444', '❌', 'キャンセル'],
                default => $isNewReservation ? ['#1d4ed8', '📅', '予約済み'] : ['#3b82f6', '📅', '予約済み'],
            };
            
            return [
                'id' => $reservation->id,
                'customer_name' => $customerName,
                'phone' => $reservation->customer?->phone ?? '',
                'menu_name' => $reservation->menu?->name ?? 'メニュー未設定',
                'store_name' => $reservation->store?->name ?? '店舗未設定',
                'start_time' => $startTime->format('H:i'),
                'end_time' => $endTime->format('H:i'),
                'duration' => $duration,
                'total_amount' => $reservation->total_amount,
                'status' => $reservation->status,
                'status_text' => $statusText,
                'status_icon' => $statusIcon,
                'color' => $color,
                'is_new' => $isNewReservation,
                'notes' => $reservation->notes,
                'reservation_number' => $reservation->reservation_number,
                
                // タイムライン表示用の計算
                'start_position' => $this->calculateTimePosition($startTime->format('H:i')),
                'width' => $this->calculateWidth($duration),
            ];
        })->toArray();
    }
    
    private function calculateTimePosition($time)
    {
        // 営業開始時間を基準点(0%)として、時間位置を計算
        $timeParts = explode(':', $time);
        $hour = intval($timeParts[0]);
        $minute = intval($timeParts[1]);

        $totalMinutesFromStart = (($hour - $this->businessHoursStart) * 60) + $minute;
        $totalMinutesInDay = ($this->businessHoursEnd - $this->businessHoursStart) * 60;

        return ($totalMinutesFromStart / $totalMinutesInDay) * 100;
    }

    private function calculateWidth($durationMinutes)
    {
        $totalMinutesInDay = ($this->businessHoursEnd - $this->businessHoursStart) * 60;
        return ($durationMinutes / $totalMinutesInDay) * 100;
    }
    
    public function getStoreOptions()
    {
        return Store::pluck('name', 'id')->toArray();
    }
}