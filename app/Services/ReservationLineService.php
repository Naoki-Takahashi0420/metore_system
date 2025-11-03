<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationLine;
use App\Models\ReservationLineAssignment;
use App\Models\Customer;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationLineService
{
    /**
     * 予約に最適なラインを自動割り当て
     */
    public function assignLineToReservation(Reservation $reservation): ?ReservationLineAssignment
    {
        $customer = $reservation->customer;
        $store = $reservation->store;
        $isNewCustomer = $this->isNewCustomer($customer);
        
        // 予約の日時情報
        $startDateTime = Carbon::parse($reservation->reservation_date . ' ' . $reservation->reservation_time);
        $duration = $reservation->menu->duration_minutes ?? 60;
        $endDateTime = $startDateTime->copy()->addMinutes($duration);
        
        // 利用可能なラインを取得
        $availableLines = $this->getAvailableLines(
            $store,
            $isNewCustomer,
            $startDateTime,
            $endDateTime,
            $reservation->staff_id
        );
        
        if ($availableLines->isEmpty()) {
            throw new \Exception('利用可能な予約枠がありません');
        }
        
        // 最適なラインを選択
        $selectedLine = $this->selectOptimalLine($availableLines, $isNewCustomer);
        
        // ライン割り当てを作成
        return $this->createLineAssignment($reservation, $selectedLine, $startDateTime, $endDateTime);
    }
    
    /**
     * 新規顧客かどうかを判定
     */
    private function isNewCustomer(Customer $customer): bool
    {
        // 過去の完了済み予約があるかチェック
        return !Reservation::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->exists();
    }
    
    /**
     * 利用可能なラインを取得
     */
    private function getAvailableLines(
        Store $store,
        bool $isNewCustomer,
        Carbon $startDateTime,
        Carbon $endDateTime,
        ?int $staffId = null
    ) {
        $query = ReservationLine::where('store_id', $store->id)
            ->where('is_active', true);
        
        // 新規顧客の場合はメインラインのみ
        if ($isNewCustomer) {
            $query->where('line_type', 'main')
                  ->where('allow_new_customers', true);
        } else {
            // 既存顧客は全てのラインを利用可能
            $query->where('allow_existing_customers', true);
        }
        
        // スタッフ指定がある場合
        if ($staffId && $store->use_staff_assignment) {
            $query->whereHas('staffAssignments', function ($q) use ($staffId, $startDateTime) {
                $q->where('staff_id', $staffId)
                  ->where('date', $startDateTime->toDateString())
                  // time()関数で時刻フォーマットを統一
                  ->whereRaw('time(start_time) <= time(?)', [$startDateTime->toTimeString()])
                  ->whereRaw('time(end_time) >= time(?)', [$startDateTime->toTimeString()]);
            });
        }
        
        $lines = $query->get();
        
        // 各ラインの空き容量をチェック
        return $lines->filter(function ($line) use ($startDateTime, $endDateTime) {
            $availableCapacity = $line->getAvailableCapacity(
                $startDateTime->toDateString(),
                $startDateTime->toTimeString(),
                $endDateTime->toTimeString()
            );
            return $availableCapacity > 0;
        });
    }
    
    /**
     * 最適なラインを選択
     */
    private function selectOptimalLine($availableLines, bool $isNewCustomer)
    {
        // 優先度でソート
        $sorted = $availableLines->sortByDesc('priority');
        
        if ($isNewCustomer) {
            // 新規顧客はメインラインを優先
            $mainLines = $sorted->where('line_type', 'main');
            if ($mainLines->isNotEmpty()) {
                return $mainLines->first();
            }
        } else {
            // 既存顧客はサブラインを優先的に使用
            $subLines = $sorted->where('line_type', 'sub');
            if ($subLines->isNotEmpty()) {
                return $subLines->first();
            }
        }
        
        // デフォルトは優先度が最も高いライン
        return $sorted->first();
    }
    
    /**
     * ライン割り当てを作成
     */
    private function createLineAssignment(
        Reservation $reservation,
        ReservationLine $line,
        Carbon $startDateTime,
        Carbon $endDateTime
    ): ReservationLineAssignment {
        return ReservationLineAssignment::create([
            'reservation_id' => $reservation->id,
            'line_id' => $line->id,
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'assignment_type' => 'auto',
            'assignment_reason' => $this->getAssignmentReason($reservation, $line),
        ]);
    }
    
    /**
     * 割り当て理由を生成
     */
    private function getAssignmentReason(Reservation $reservation, ReservationLine $line): string
    {
        $customer = $reservation->customer;
        $isNew = $this->isNewCustomer($customer);
        
        if ($isNew) {
            return "新規顧客のため{$line->line_name}に割り当て";
        } else {
            if ($line->line_type === 'sub') {
                return "既存顧客のため予備ライン（{$line->line_name}）に割り当て";
            }
            return "既存顧客・{$line->line_name}に割り当て";
        }
    }
    
    /**
     * 店舗のライン初期設定を作成
     */
    public function initializeStoreLines(Store $store)
    {
        DB::transaction(function () use ($store) {
            // メインライン作成
            for ($i = 1; $i <= $store->main_lines_count; $i++) {
                ReservationLine::create([
                    'store_id' => $store->id,
                    'line_name' => "本ライン{$i}",
                    'line_type' => 'main',
                    'line_number' => $i,
                    'capacity' => 1,
                    'is_active' => true,
                    'allow_new_customers' => true,
                    'allow_existing_customers' => true,
                    'requires_staff' => $store->use_staff_assignment,
                    'allows_simultaneous' => false,
                    'priority' => 100 - $i,
                ]);
            }
            
            // サブライン作成
            for ($i = 1; $i <= $store->sub_lines_count; $i++) {
                ReservationLine::create([
                    'store_id' => $store->id,
                    'line_name' => "予備ライン{$i}",
                    'line_type' => 'sub',
                    'line_number' => $i,
                    'capacity' => 2, // 同時施術可能
                    'is_active' => true,
                    'allow_new_customers' => false,
                    'allow_existing_customers' => true,
                    'requires_staff' => false,
                    'allows_simultaneous' => true,
                    'priority' => 50 - $i,
                ]);
            }
        });
    }
    
    /**
     * タイムラインデータを取得
     */
    public function getTimelineData(Store $store, Carbon $date)
    {
        $lines = ReservationLine::where('store_id', $store->id)
            ->where('is_active', true)
            ->with(['assignments' => function ($query) use ($date) {
                $query->whereDate('start_datetime', $date)
                    ->with(['reservation.customer', 'reservation.menu']);
            }])
            ->orderBy('line_type')
            ->orderBy('line_number')
            ->get();
        
        $timelineData = [];
        
        foreach ($lines as $line) {
            $lineData = [
                'id' => $line->id,
                'name' => $line->line_name,
                'type' => $line->line_type,
                'capacity' => $line->capacity,
                'reservations' => []
            ];
            
            foreach ($line->assignments as $assignment) {
                $reservation = $assignment->reservation;
                $lineData['reservations'][] = [
                    'id' => $reservation->id,
                    'customer_name' => $reservation->customer->full_name,
                    'menu_name' => $reservation->menu->name,
                    'start_time' => $assignment->start_datetime->format('H:i'),
                    'end_time' => $assignment->end_datetime->format('H:i'),
                    'status' => $reservation->status,
                    'is_new_customer' => $this->isNewCustomer($reservation->customer),
                ];
            }
            
            $timelineData[] = $lineData;
        }
        
        return $timelineData;
    }
}