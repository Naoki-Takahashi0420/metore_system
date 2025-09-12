<?php

namespace App\Filament\Pages;

use App\Models\Store;
use App\Models\Shift;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class AttendanceReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = '勤怠レポート';
    protected static ?string $title = '月次勤怠レポート';
    protected static ?string $navigationGroup = 'スタッフ管理';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.attendance-report';
    
    public $stores;
    public $selectedStore;
    public $selectedYear;
    public $selectedMonth;
    
    // レポートデータ
    public $reportData = [];
    public $staffSummary = [];
    public $dailySummary = [];
    public $patternAnalysis = [];
    public $timeSlotAnalysis = [];
    
    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user->hasRole(['super_admin', 'owner', 'manager']);
    }
    
    public function mount(): void
    {
        $user = Auth::user();
        
        // アクセス可能な店舗を取得
        if ($user->hasRole('super_admin')) {
            $this->stores = Store::where('is_active', true)->get();
        } elseif ($user->hasRole('owner')) {
            $this->stores = $user->manageableStores()->get();
        } else {
            $this->stores = $user->store ? collect([$user->store]) : collect();
        }
        
        $this->selectedStore = $this->stores->first()?->id;
        $this->selectedYear = now()->year;
        $this->selectedMonth = now()->month;
        
        if ($this->selectedStore) {
            $this->generateReport();
        }
    }
    
    public function changeStore(): void
    {
        $this->generateReport();
    }
    
    public function changeMonth(): void
    {
        $this->generateReport();
    }
    
    public function generateReport(): void
    {
        if (!$this->selectedStore) return;
        
        $startDate = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        // 今日までに限定（未来のシフトは含めない）
        $today = now()->endOfDay();
        if ($endDate > $today) {
            $endDate = $today;
        }
        
        // 店舗情報
        $store = Store::find($this->selectedStore);
        $this->reportData['store'] = $store;
        $this->reportData['period'] = $startDate->format('Y年n月');
        $this->reportData['generated_at'] = now()->format('Y年m月d日 H:i');
        
        // シフトデータを取得（今日までのデータのみ）
        $shifts = Shift::with('user')
            ->where('store_id', $this->selectedStore)
            ->whereBetween('shift_date', [$startDate, $endDate])
            ->where('shift_date', '<=', now()->format('Y-m-d'))
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get();
        
        // スタッフ別集計
        $this->calculateStaffSummary($shifts);
        
        // 日別集計
        $this->calculateDailySummary($shifts, $startDate, $endDate);
        
        // シフトパターン分析
        $this->analyzeShiftPatterns($shifts);
        
        // 時間帯別分析
        $this->analyzeTimeSlots($shifts);
    }
    
    private function calculateStaffSummary($shifts): void
    {
        $this->staffSummary = [];
        
        $staffShifts = $shifts->groupBy('user_id');
        
        foreach ($staffShifts as $userId => $userShifts) {
            $user = $userShifts->first()->user;
            if (!$user) continue;
            
            $totalWorkMinutes = 0;
            $totalBreakMinutes = 0;
            $patterns = [];
            
            foreach ($userShifts as $shift) {
                // 勤務時間計算（日付を考慮）
                $shiftDate = $shift->shift_date instanceof Carbon ? $shift->shift_date : Carbon::parse($shift->shift_date);
                
                // 開始・終了時刻を日付と組み合わせて正しいCarbonインスタンスを作成
                $startTimeStr = $shiftDate->format('Y-m-d') . ' ' . $shift->start_time . ':00';
                $endTimeStr = $shiftDate->format('Y-m-d') . ' ' . $shift->end_time . ':00';
                
                $start = Carbon::parse($startTimeStr);
                $end = Carbon::parse($endTimeStr);
                
                // 終了時間が開始時間より前の場合は翌日とみなす
                if ($end <= $start) {
                    $end->addDay();
                }
                
                $workMinutes = $start->diffInMinutes($end);
                $totalWorkMinutes += $workMinutes;
                
                // 休憩時間計算
                if ($shift->break_start && $shift->break_end) {
                    $breakStartStr = $shiftDate->format('Y-m-d') . ' ' . $shift->break_start;
                    $breakEndStr = $shiftDate->format('Y-m-d') . ' ' . $shift->break_end;
                    
                    $breakStart = Carbon::parse($breakStartStr);
                    $breakEnd = Carbon::parse($breakEndStr);
                    
                    // 休憩終了が開始より前の場合は翌日とみなす
                    if ($breakEnd <= $breakStart) {
                        $breakEnd->addDay();
                    }
                    
                    $totalBreakMinutes += $breakStart->diffInMinutes($breakEnd);
                }
                
                // 追加休憩時間
                if ($shift->additional_breaks) {
                    $additionalBreaks = is_array($shift->additional_breaks) 
                        ? $shift->additional_breaks 
                        : json_decode($shift->additional_breaks, true);
                    
                    if ($additionalBreaks) {
                        foreach ($additionalBreaks as $break) {
                            // 時刻形式を統一（秒がない場合は追加）
                            $breakTimeStart = strpos($break['start'], ':') !== false ? $break['start'] : $break['start'] . ':00';
                            $breakTimeEnd = strpos($break['end'], ':') !== false ? $break['end'] : $break['end'] . ':00';
                            
                            if (substr_count($breakTimeStart, ':') == 1) {
                                $breakTimeStart .= ':00';
                            }
                            if (substr_count($breakTimeEnd, ':') == 1) {
                                $breakTimeEnd .= ':00';
                            }
                            
                            $breakStartStr = $shiftDate->format('Y-m-d') . ' ' . $breakTimeStart;
                            $breakEndStr = $shiftDate->format('Y-m-d') . ' ' . $breakTimeEnd;
                            
                            $breakStart = Carbon::parse($breakStartStr);
                            $breakEnd = Carbon::parse($breakEndStr);
                            
                            // 休憩終了が開始より前の場合は翌日とみなす
                            if ($breakEnd <= $breakStart) {
                                $breakEnd->addDay();
                            }
                            
                            $totalBreakMinutes += $breakStart->diffInMinutes($breakEnd);
                        }
                    }
                }
                
                // パターン集計
                $pattern = $this->determineShiftPattern($shift->start_time, $shift->end_time);
                $patterns[$pattern] = ($patterns[$pattern] ?? 0) + 1;
            }
            
            $this->staffSummary[] = [
                'name' => $user->name,
                'days' => $userShifts->count(),
                'total_hours' => round($totalWorkMinutes / 60, 1),
                'break_hours' => round($totalBreakMinutes / 60, 1),
                'actual_hours' => round(($totalWorkMinutes - $totalBreakMinutes) / 60, 1),
                'patterns' => $patterns
            ];
        }
    }
    
    private function calculateDailySummary($shifts, $startDate, $endDate): void
    {
        $this->dailySummary = [];
        
        $current = $startDate->copy();
        $today = now()->endOfDay();
        
        // 今日までのデータのみ処理
        while ($current <= $endDate && $current <= $today) {
            $dayShifts = $shifts->filter(function ($shift) use ($current) {
                return Carbon::parse($shift->shift_date)->isSameDay($current);
            });
            
            $staffList = [];
            $totalMinutes = 0;
            
            foreach ($dayShifts as $shift) {
                $shiftDate = $shift->shift_date instanceof Carbon ? $shift->shift_date : Carbon::parse($shift->shift_date);
                
                $startTimeStr = $shiftDate->format('Y-m-d') . ' ' . $shift->start_time . ':00';
                $endTimeStr = $shiftDate->format('Y-m-d') . ' ' . $shift->end_time . ':00';
                
                $start = Carbon::parse($startTimeStr);
                $end = Carbon::parse($endTimeStr);
                
                // 終了時間が開始時間より前の場合は翌日とみなす
                if ($end <= $start) {
                    $end->addDay();
                }
                
                $minutes = $start->diffInMinutes($end);
                
                $staffList[] = [
                    'name' => $shift->user ? $shift->user->name : '未割当',
                    'time' => $start->format('H:i') . '-' . $end->format('H:i'),
                    'hours' => round($minutes / 60, 1)
                ];
                
                $totalMinutes += $minutes;
            }
            
            $this->dailySummary[] = [
                'date' => $current->format('n/j'),
                'day' => $current->isoFormat('(ddd)'),
                'staff' => $staffList,
                'total_hours' => round($totalMinutes / 60, 1)
            ];
            
            $current->addDay();
        }
    }
    
    private function analyzeShiftPatterns($shifts): void
    {
        $patterns = [];
        
        foreach ($shifts as $shift) {
            $pattern = $this->determineShiftPattern($shift->start_time, $shift->end_time);
            $patterns[$pattern] = ($patterns[$pattern] ?? 0) + 1;
        }
        
        $this->patternAnalysis = $patterns;
    }
    
    private function analyzeTimeSlots($shifts): void
    {
        $slots = [];
        
        // 時間帯別（1時間ごと）の集計
        for ($hour = 0; $hour < 24; $hour++) {
            $slots[$hour] = 0;
        }
        
        foreach ($shifts as $shift) {
            $start = Carbon::parse($shift->start_time);
            $end = Carbon::parse($shift->end_time);
            
            $current = $start->copy();
            while ($current < $end) {
                $hour = $current->hour;
                $slots[$hour]++;
                $current->addHour();
            }
        }
        
        $this->timeSlotAnalysis = $slots;
    }
    
    private function determineShiftPattern($startTime, $endTime): string
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        
        if ($start->hour < 12 && $end->hour <= 15) {
            return '早番';
        } elseif ($start->hour >= 14 && $end->hour >= 20) {
            return '遅番';
        } elseif ($end->diffInHours($start) <= 5) {
            return '短時間';
        } else {
            return '通常';
        }
    }
    
    public function exportPdf()
    {
        // データが空の場合は先に生成
        if (empty($this->reportData)) {
            $this->generateReport();
        }
        
        try {
            $pdf = Pdf::loadView('reports.attendance-pdf', [
                'reportData' => $this->reportData,
                'staffSummary' => $this->staffSummary,
                'dailySummary' => $this->dailySummary,
                'patternAnalysis' => $this->patternAnalysis
            ]);
            
            // 日本語フォント対応
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);
            
            $filename = "attendance_report_{$this->selectedYear}_{$this->selectedMonth}.pdf";
            
            return response()->streamDownload(function() use ($pdf) {
                echo $pdf->output();
            }, $filename);
            
        } catch (\Exception $e) {
            // PDFが生成できない場合はHTMLとして出力
            $html = view('reports.attendance-pdf', [
                'reportData' => $this->reportData,
                'staffSummary' => $this->staffSummary,
                'dailySummary' => $this->dailySummary,
                'patternAnalysis' => $this->patternAnalysis
            ])->render();
            
            $filename = "attendance_report_{$this->selectedYear}_{$this->selectedMonth}.html";
            
            return Response::make($html, 200)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        }
    }
    
    public function exportCsv()
    {
        // データが空の場合は先に生成
        if (empty($this->reportData)) {
            $this->generateReport();
        }
        
        $filename = "勤怠レポート_{$this->reportData['store']->name}_{$this->selectedYear}年{$this->selectedMonth}月.csv";
        
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        
        $callback = function() {
            $file = fopen('php://output', 'w');
            
            // BOM付きUTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // ヘッダー情報
            fputcsv($file, ['月次勤怠レポート']);
            fputcsv($file, ['店舗名', $this->reportData['store']->name]);
            fputcsv($file, ['対象期間', $this->reportData['period']]);
            fputcsv($file, []);
            
            // スタッフ別集計
            fputcsv($file, ['スタッフ別集計']);
            fputcsv($file, ['氏名', '勤務日数', '総勤務時間', '休憩時間', '実働時間']);
            foreach ($this->staffSummary as $staff) {
                fputcsv($file, [
                    $staff['name'],
                    $staff['days'] . '日',
                    $staff['total_hours'] . '時間',
                    $staff['break_hours'] . '時間',
                    $staff['actual_hours'] . '時間'
                ]);
            }
            
            // 合計行
            fputcsv($file, [
                '合計',
                collect($this->staffSummary)->sum('days') . '日',
                collect($this->staffSummary)->sum('total_hours') . '時間',
                collect($this->staffSummary)->sum('break_hours') . '時間',
                collect($this->staffSummary)->sum('actual_hours') . '時間'
            ]);
            
            fputcsv($file, []);
            
            // 日別詳細
            fputcsv($file, ['日別詳細']);
            fputcsv($file, ['日付', 'スタッフ', '合計時間']);
            foreach ($this->dailySummary as $day) {
                $staffNames = '';
                if (count($day['staff']) > 0) {
                    $staffNames = implode(', ', array_map(function($s) {
                        return $s['name'] . '(' . $s['time'] . ')';
                    }, $day['staff']));
                }
                fputcsv($file, [
                    $day['date'] . ' ' . $day['day'],
                    $staffNames ?: '-',
                    $day['total_hours'] . '時間'
                ]);
            }
            
            fclose($file);
        };
        
        return Response::stream($callback, 200, $headers);
    }
}