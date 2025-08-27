<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Store;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class TodayReservationTimelineWidget extends Widget
{
    protected static string $view = 'filament.widgets.today-reservation-timeline-widget';
    
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';

    #[Url]
    public string $selectedDate = '';

    public function mount(): void
    {
        if (empty($this->selectedDate)) {
            $this->selectedDate = Carbon::today()->format('Y-m-d');
        }
    }

    public function getData(): array
    {
        $selectedDate = Carbon::parse($this->selectedDate);
        
        // ログインユーザーの店舗制限
        $query = Reservation::with(['customer', 'menu', 'store']);
        
        // スタッフロールの場合は自分の店舗のみ表示
        $user = auth()->user();
        if ($user && !$user->hasRole('super_admin')) {
            if ($user->store_id) {
                $query->where('store_id', $user->store_id);
            }
        }
        
        // 選択された日の予約を取得（新規/既存顧客の判定も含む）
        $reservations = $query->whereDate('reservation_date', $selectedDate)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->orderBy('start_time')
            ->get()
            ->map(function ($reservation) {
                // 新規顧客かどうかの判定（この予約が初回予約かどうか）
                $isNewCustomer = Reservation::where('customer_id', $reservation->customer_id)
                    ->where('id', '<=', $reservation->id)
                    ->whereNotIn('status', ['cancelled', 'canceled'])
                    ->count() === 1;
                
                $reservation->is_new_customer = $isNewCustomer;
                return $reservation;
            });

        // 店舗ごとの営業時間を取得（ロール制限）
        $storesQuery = Store::where('is_active', true);
        if ($user && !$user->hasRole('super_admin')) {
            if ($user->store_id) {
                $storesQuery->where('id', $user->store_id);
            }
        }
        $stores = $storesQuery->get();
        
        // タイムスロットを生成（9:00-18:00を30分刻み）
        $timeSlots = $this->generateTimeSlots();
        
        // ガントチャート用の予約情報を前処理
        $reservationsWithSlotInfo = $reservations->map(function($reservation) {
            $slotInfo = $this->getReservationTimeSlotInfo($reservation);
            $reservation->slot_info = $slotInfo;
            return $reservation;
        });
        
        $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][$selectedDate->dayOfWeek];
        
        return [
            'reservations' => $reservationsWithSlotInfo,
            'stores' => $stores,
            'timeSlots' => $timeSlots,
            'selectedDate' => $selectedDate,
            'todayDate' => $selectedDate->format('Y年n月j日') . '（' . $dayOfWeek . '）',
            'isToday' => $selectedDate->isToday(),
            'canNavigateBack' => $selectedDate->gt(Carbon::today()->subDays(30)), // 30日前まで
            'canNavigateForward' => $selectedDate->lt(Carbon::today()->addDays(60)), // 60日先まで
        ];
    }

    public function goToPreviousDay()
    {
        $currentDate = Carbon::parse($this->selectedDate);
        if ($currentDate->gt(Carbon::today()->subDays(30))) {
            $this->selectedDate = $currentDate->subDay()->format('Y-m-d');
        }
    }

    public function goToNextDay()
    {
        $currentDate = Carbon::parse($this->selectedDate);
        if ($currentDate->lt(Carbon::today()->addDays(60))) {
            $this->selectedDate = $currentDate->addDay()->format('Y-m-d');
        }
    }

    public function goToToday()
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
    }

    public function updatedSelectedDate()
    {
        // 日付が更新された時に自動でリフレッシュ
    }

    private function generateTimeSlots(): Collection
    {
        $slots = collect();
        $start = Carbon::createFromTime(9, 0);
        $end = Carbon::createFromTime(18, 0);
        
        while ($start <= $end) {
            // 統一したフォーマット（HH:MM）で時間を保存
            $slots->push($start->format('H:i'));
            $start->addMinutes(30);
        }
        
        return $slots;
    }
    
    /**
     * 予約の時間スロットでの開始位置と期間を計算
     */
    public function getReservationTimeSlotInfo($reservation): array
    {
        $timeSlots = $this->generateTimeSlots();
        
        try {
            $startTime = is_string($reservation->start_time) 
                ? Carbon::createFromFormat('H:i:s', $reservation->start_time)->format('H:i')
                : $reservation->start_time;
            $endTime = is_string($reservation->end_time)
                ? Carbon::createFromFormat('H:i:s', $reservation->end_time)->format('H:i')
                : $reservation->end_time;
        } catch (\Exception $e) {
            $startTime = $reservation->start_time;
            $endTime = $reservation->end_time;
        }
        
        // 開始時刻のスロットインデックスを取得
        $startSlotIndex = $timeSlots->search($startTime);
        if ($startSlotIndex === false) {
            // 完全一致しない場合は最も近いスロットを探す
            $startSlotIndex = $timeSlots->search(function($slot) use ($startTime) {
                return $slot >= $startTime;
            });
            if ($startSlotIndex === false) $startSlotIndex = 0;
        }
        
        // 終了時刻のスロットインデックスを取得
        $endSlotIndex = $timeSlots->search(function($slot) use ($endTime) {
            return $slot >= $endTime;
        });
        if ($endSlotIndex === false) $endSlotIndex = count($timeSlots);
        
        $duration = max(1, $endSlotIndex - $startSlotIndex);
        
        return [
            'startSlotIndex' => $startSlotIndex,
            'duration' => $duration,
            'startTime' => $startTime,
            'endTime' => $endTime
        ];
    }

    public function getReservationAtTime(string $time, ?int $storeId = null): ?Reservation
    {
        $reservations = $this->getData()['reservations'];
        $checkTime = Carbon::createFromFormat('H:i', $time);
        
        return $reservations->first(function ($reservation) use ($checkTime, $storeId) {
            if ($storeId && $reservation->store_id !== $storeId) {
                return false;
            }
            
            // 時刻を安全にパース
            try {
                // start_timeとend_timeが時刻形式かチェック
                if (strlen($reservation->start_time) === 5) {
                    // H:i形式の場合
                    $start = Carbon::createFromFormat('H:i', $reservation->start_time);
                    $end = Carbon::createFromFormat('H:i', $reservation->end_time);
                } elseif (strlen($reservation->start_time) === 8) {
                    // H:i:s形式の場合
                    $start = Carbon::createFromFormat('H:i:s', $reservation->start_time);
                    $end = Carbon::createFromFormat('H:i:s', $reservation->end_time);
                } else {
                    // その他の形式の場合はCarbonに任せる
                    $start = Carbon::parse($reservation->start_time);
                    $end = Carbon::parse($reservation->end_time);
                }
            } catch (\Exception $e) {
                return false;
            }
            
            // 時間のみ比較（日付は無視）
            return $checkTime->between($start, $end->copy()->subMinute(), false);
        });
    }
}