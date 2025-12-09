<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use App\Models\Store;
use App\Services\CustomerNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendLineReminders extends Command
{
    protected $signature = 'reminders:send {--force : 時刻チェックをスキップ}';
    protected $description = '各店舗の設定時刻に予約リマインダーを送信（LINE→メール→SMS優先順位）';

    private CustomerNotificationService $notificationService;

    public function __construct(CustomerNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $force = $this->option('force');

        // リマインダー有効な店舗を取得（LINE有効でなくてもOK）
        $stores = Store::where('line_send_reminder', true)->get();

        if ($stores->isEmpty()) {
            $this->info('リマインダー送信が有効な店舗がありません');
            return Command::SUCCESS;
        }

        foreach ($stores as $store) {
            $this->info("店舗: {$store->name} のリマインダー処理開始");

            // 店舗の設定時刻を確認
            $reminderTime = $store->line_reminder_time
                ? Carbon::parse($store->line_reminder_time)
                : Carbon::parse('10:00'); // デフォルト10:00
            $daysBefore = $store->line_reminder_days_before ?: 1;

            // 現在時刻が送信時刻の範囲内か確認（±5分）
            $now = now();
            if (!$force && !$now->between(
                $reminderTime->copy()->subMinutes(5),
                $reminderTime->copy()->addMinutes(5)
            )) {
                $this->info("  送信時刻外のためスキップ（設定時刻: {$reminderTime->format('H:i')}）");
                continue;
            }

            // 対象の予約を取得（LINE/メール/SMSいずれかで未送信のもの）
            $targetDate = now()->addDays($daysBefore)->format('Y-m-d');
            $reservations = Reservation::where('store_id', $store->id)
                ->whereDate('reservation_date', $targetDate)
                ->whereIn('status', ['booked', 'confirmed'])
                ->where(function ($query) {
                    // LINE未送信 かつ 一般リマインダー未送信
                    $query->whereNull('line_reminder_sent_at')
                          ->whereNull('reminder_sent_at');
                })
                ->with(['customer', 'menu'])
                ->get();

            if ($reservations->isEmpty()) {
                $this->info("  対象予約なし");
                continue;
            }

            $sent = 0;
            $failed = 0;

            foreach ($reservations as $reservation) {
                $customer = $reservation->customer;

                if (!$customer) {
                    $this->warn("  顧客情報なし: 予約ID {$reservation->id}");
                    continue;
                }

                // CustomerNotificationServiceを使用してリマインダー送信
                // 優先順位: LINE → メール → SMS
                try {
                    $result = $this->notificationService->sendReservationReminder($reservation);

                    $sentVia = null;
                    if ($result['line'] ?? false) {
                        $sentVia = 'LINE';
                        $reservation->update(['line_reminder_sent_at' => now()]);
                    } elseif ($result['email'] ?? false) {
                        $sentVia = 'メール';
                        $reservation->update(['reminder_sent_at' => now()]);
                    } elseif ($result['sms'] ?? false) {
                        $sentVia = 'SMS';
                        $reservation->update(['reminder_sent_at' => now()]);
                    }

                    if ($sentVia) {
                        $sent++;
                        $this->info("  ✅ 送信成功 ({$sentVia}): {$customer->full_name}");
                        Log::info('リマインダー送信成功', [
                            'reservation_id' => $reservation->id,
                            'customer_id' => $customer->id,
                            'sent_via' => $sentVia
                        ]);
                    } else {
                        $failed++;
                        $this->error("  ❌ 送信失敗: {$customer->full_name} (通知手段なし)");
                        Log::warning('リマインダー送信失敗: 通知手段なし', [
                            'reservation_id' => $reservation->id,
                            'customer_id' => $customer->id,
                            'has_line' => $customer->canReceiveLineNotifications(),
                            'has_email' => !empty($customer->email),
                            'has_phone' => !empty($customer->phone)
                        ]);
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("  ❌ エラー: {$customer->full_name} - {$e->getMessage()}");
                    Log::error('リマインダー送信例外', [
                        'reservation_id' => $reservation->id,
                        'error' => $e->getMessage()
                    ]);
                }

                usleep(200000); // 0.2秒待機（API制限対策）
            }

            $this->info("  完了: {$sent}件送信, {$failed}件失敗");
        }

        return Command::SUCCESS;
    }
}