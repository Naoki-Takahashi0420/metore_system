<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffShiftController extends Controller
{
    /**
     * スタッフ用シフト確認画面（スマホ対応）
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $month = $request->get('month', now()->format('Y-m'));
        $date = Carbon::parse($month . '-01');
        
        // 自分のシフトを取得
        $shifts = Shift::where('user_id', $user->id)
            ->whereMonth('shift_date', $date->month)
            ->whereYear('shift_date', $date->year)
            ->orderBy('shift_date')
            ->get();
        
        // 全スタッフのシフト（店舗別）
        $storeShifts = [];
        if ($request->get('view') === 'all') {
            $stores = Store::where('is_active', true)->get();
            foreach ($stores as $store) {
                $storeShifts[$store->id] = [
                    'store' => $store,
                    'shifts' => Shift::with('user')
                        ->where('store_id', $store->id)
                        ->whereMonth('shift_date', $date->month)
                        ->whereYear('shift_date', $date->year)
                        ->orderBy('shift_date')
                        ->orderBy('start_time')
                        ->get()
                        ->groupBy(function($shift) {
                            return $shift->shift_date->format('Y-m-d');
                        })
                ];
            }
        }
        
        // カレンダーデータ生成
        $calendarData = $this->generateCalendarData($date, $shifts);
        
        // 月間サマリー
        $summary = [
            'total_shifts' => $shifts->count(),
            'total_hours' => $this->calculateTotalHours($shifts),
            'next_shift' => $this->getNextShift($user->id),
        ];
        
        return view('staff.shifts.index', compact(
            'shifts',
            'calendarData',
            'summary',
            'date',
            'storeShifts'
        ));
    }
    
    /**
     * シフト詳細表示
     */
    public function show($id)
    {
        $shift = Shift::with(['store', 'user'])->findOrFail($id);
        
        // 権限チェック（自分のシフトまたは管理者）
        if ($shift->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }
        
        // 同日の他のスタッフのシフト
        $otherShifts = Shift::with('user')
            ->where('store_id', $shift->store_id)
            ->where('shift_date', $shift->shift_date)
            ->where('id', '!=', $shift->id)
            ->orderBy('start_time')
            ->get();
        
        return view('staff.shifts.show', compact('shift', 'otherShifts'));
    }
    
    /**
     * カレンダーデータ生成
     */
    private function generateCalendarData($date, $shifts)
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $startDate = $startOfMonth->copy()->startOfWeek();
        $endDate = $endOfMonth->copy()->endOfWeek();
        
        $calendarData = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayShifts = $shifts->filter(function($shift) use ($dateKey) {
                return $shift->shift_date->format('Y-m-d') === $dateKey;
            });
            
            $calendarData[] = [
                'date' => $currentDate->copy(),
                'is_current_month' => $currentDate->month === $date->month,
                'is_today' => $currentDate->isToday(),
                'shifts' => $dayShifts,
                'has_shift' => $dayShifts->isNotEmpty(),
            ];
            
            $currentDate->addDay();
        }
        
        return collect($calendarData)->chunk(7);
    }
    
    /**
     * 総勤務時間計算
     */
    private function calculateTotalHours($shifts)
    {
        $totalMinutes = 0;
        
        foreach ($shifts as $shift) {
            $start = Carbon::parse($shift->start_time);
            $end = Carbon::parse($shift->end_time);
            $minutes = $end->diffInMinutes($start);
            
            // 休憩時間を引く
            if ($shift->break_start && $shift->break_end) {
                $breakStart = Carbon::parse($shift->break_start);
                $breakEnd = Carbon::parse($shift->break_end);
                $minutes -= $breakEnd->diffInMinutes($breakStart);
            }
            
            $totalMinutes += $minutes;
        }
        
        return round($totalMinutes / 60, 1);
    }
    
    /**
     * 次回のシフト取得
     */
    private function getNextShift($userId)
    {
        return Shift::with('store')
            ->where('user_id', $userId)
            ->where('shift_date', '>=', today())
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->first();
    }
}