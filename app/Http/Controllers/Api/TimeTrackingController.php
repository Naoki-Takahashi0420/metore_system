<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TimeTrackingController extends Controller
{
    public function clockIn(Request $request, $shiftId)
    {
        $shift = Shift::find($shiftId);
        $user = auth()->user();
        
        if (!$shift) {
            return response()->json(['error' => 'シフトが見つかりません'], 404);
        }
        
        // 権限チェック: 本人またはスーパーアドミン
        if ($user->id !== $shift->user_id && $user->role !== 'superadmin') {
            return response()->json(['error' => '権限がありません'], 403);
        }
        
        if ($shift->actual_start_time) {
            return response()->json(['error' => 'すでに出勤済みです'], 400);
        }
        
        $shift->update([
            'actual_start_time' => now()->format('H:i:s'),
            'status' => 'working'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => $shift->user->name . 'さんが出勤しました (' . now()->format('H:i') . ')',
            'actual_start_time' => $shift->actual_start_time
        ]);
    }
    
    public function startBreak(Request $request, $shiftId)
    {
        $shift = Shift::find($shiftId);
        $user = auth()->user();
        
        if (!$shift) {
            return response()->json(['error' => 'シフトが見つかりません'], 404);
        }
        
        // 権限チェック: 本人またはスーパーアドミン
        if ($user->id !== $shift->user_id && $user->role !== 'superadmin') {
            return response()->json(['error' => '権限がありません'], 403);
        }
        
        if (!$shift->actual_start_time) {
            return response()->json(['error' => '出勤してから休憩を開始してください'], 400);
        }
        
        if ($shift->actual_break_start) {
            return response()->json(['error' => 'すでに休憩中です'], 400);
        }
        
        $shift->update([
            'actual_break_start' => now()->format('H:i:s')
        ]);
        
        return response()->json([
            'success' => true,
            'message' => $shift->user->name . 'さんが休憩に入りました (' . now()->format('H:i') . ')',
            'actual_break_start' => $shift->actual_break_start
        ]);
    }
    
    public function endBreak(Request $request, $shiftId)
    {
        $shift = Shift::find($shiftId);
        $user = auth()->user();
        
        if (!$shift) {
            return response()->json(['error' => 'シフトが見つかりません'], 404);
        }
        
        // 権限チェック: 本人またはスーパーアドミン
        if ($user->id !== $shift->user_id && $user->role !== 'superadmin') {
            return response()->json(['error' => '権限がありません'], 403);
        }
        
        if (!$shift->actual_break_start) {
            return response()->json(['error' => '休憩を開始してから終了してください'], 400);
        }
        
        if ($shift->actual_break_end) {
            return response()->json(['error' => 'すでに休憩終了済みです'], 400);
        }
        
        $shift->update([
            'actual_break_end' => now()->format('H:i:s')
        ]);
        
        return response()->json([
            'success' => true,
            'message' => $shift->user->name . 'さんが休憩から戻りました (' . now()->format('H:i') . ')',
            'actual_break_end' => $shift->actual_break_end
        ]);
    }
    
    public function clockOut(Request $request, $shiftId)
    {
        $shift = Shift::find($shiftId);
        $user = auth()->user();
        
        if (!$shift) {
            return response()->json(['error' => 'シフトが見つかりません'], 404);
        }
        
        // 権限チェック: 本人またはスーパーアドミン
        if ($user->id !== $shift->user_id && $user->role !== 'superadmin') {
            return response()->json(['error' => '権限がありません'], 403);
        }
        
        if (!$shift->actual_start_time) {
            return response()->json(['error' => '出勤してから退勤してください'], 400);
        }
        
        if ($shift->actual_end_time) {
            return response()->json(['error' => 'すでに退勤済みです'], 400);
        }
        
        $shift->update([
            'actual_end_time' => now()->format('H:i:s'),
            'status' => 'completed'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => $shift->user->name . 'さんが退勤しました (' . now()->format('H:i') . ')',
            'actual_end_time' => $shift->actual_end_time,
            'actual_working_hours' => $shift->actual_working_hours
        ]);
    }
}