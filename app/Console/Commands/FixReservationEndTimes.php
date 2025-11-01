<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use Carbon\Carbon;

class FixReservationEndTimes extends Command
{
    protected $signature = 'reservations:fix-end-times {--dry-run : Dry run without actually updating}';

    protected $description = 'Fix reservation end_time to match menu duration + options duration';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN モード - 実際の更新は行いません');
        } else {
            $this->warn('⚠️  本番モード - データベースを更新します');
            if (!$this->confirm('続行しますか？')) {
                $this->info('キャンセルしました');
                return 0;
            }
        }

        $this->info('予約データの不整合をチェック中...');

        // 有効な予約のみを対象
        $reservations = Reservation::with(['menu', 'reservationOptions'])
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->whereNotNull('end_time')
            ->get();

        $totalChecked = 0;
        $totalFixed = 0;
        $errors = [];

        foreach ($reservations as $reservation) {
            $totalChecked++;

            // メニューの所要時間
            $menuDuration = $reservation->menu->duration_minutes ?? 60;

            // オプションの合計時間
            $optionsDuration = $reservation->reservationOptions->sum('duration_minutes');

            // 合計時間
            $totalDuration = $menuDuration + $optionsDuration;

            // 現在のend_timeから実際の所要時間を計算
            $date = Carbon::parse($reservation->reservation_date)->format('Y-m-d');
            $startTime = Carbon::parse($date . ' ' . $reservation->start_time);
            $endTime = Carbon::parse($date . ' ' . $reservation->end_time);
            $actualDuration = (int) $startTime->diffInMinutes($endTime);

            // 不整合をチェック
            if ($actualDuration != $totalDuration) {
                $newEndTime = $startTime->copy()->addMinutes($totalDuration);

                $this->line(sprintf(
                    '予約ID %d: %s → %s (実際: %d分 → 正しい: %d分)',
                    $reservation->id,
                    $endTime->format('H:i'),
                    $newEndTime->format('H:i'),
                    $actualDuration,
                    $totalDuration
                ));

                if (!$isDryRun) {
                    try {
                        $reservation->update([
                            'end_time' => $newEndTime->format('H:i:s')
                        ]);
                        $totalFixed++;
                        $this->info("  ✅ 修正しました");
                    } catch (\Exception $e) {
                        $errors[] = [
                            'id' => $reservation->id,
                            'error' => $e->getMessage()
                        ];
                        $this->error("  ❌ エラー: " . $e->getMessage());
                    }
                } else {
                    $totalFixed++; // Dry runでもカウント
                }
            }
        }

        $this->newLine();
        $this->info('=== 結果 ===');
        $this->info("チェック件数: {$totalChecked}");
        $this->info("修正対象件数: {$totalFixed}");

        if ($isDryRun) {
            $this->warn('DRY RUN モードのため、実際の更新は行われていません');
            $this->info('本番実行する場合: php artisan reservations:fix-end-times');
        } else {
            $this->info("✅ 修正完了");
        }

        if (count($errors) > 0) {
            $this->error("エラー件数: " . count($errors));
            foreach ($errors as $error) {
                $this->error("  予約ID {$error['id']}: {$error['error']}");
            }
        }

        return 0;
    }
}
