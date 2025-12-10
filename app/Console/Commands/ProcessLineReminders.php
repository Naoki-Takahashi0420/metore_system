<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;
use App\Models\Customer;
// use App\Models\LineReminderRule; // 未実装のため一時無効化
use App\Models\CustomerLabel;
use App\Services\LineMessageService;
use Carbon\Carbon;

class ProcessLineReminders extends Command
{
    protected $signature = 'line:process-reminders';
    protected $description = 'LINE自動リマインダーを処理';

    protected $lineService;

    public function __construct(LineMessageService $lineService)
    {
        parent::__construct();
        $this->lineService = $lineService;
    }

    public function handle()
    {
        $this->info('LINEリマインダー処理を開始します...');
        
        // 1. 予約24時間前リマインダー
        $this->process24HourReminders();
        
        // 2. 予約3時間前リマインダー  
        $this->process3HourReminders();
        
        // 3. ラベルベースの自動リマインダー
        $this->processLabelBasedReminders();
        
        // 4. ノーショーフォローアップ
        $this->processNoShowFollowups();
        
        $this->info('LINEリマインダー処理が完了しました');
    }
    
    /**
     * 24時間前リマインダー
     */
    protected function process24HourReminders()
    {
        $targetTime = Carbon::now()->addHours(24);
        
        $reservations = Reservation::with(['customer', 'store'])
            ->whereBetween('reservation_date', [
                $targetTime->copy()->startOfHour(),
                $targetTime->copy()->endOfHour()
            ])
            ->whereIn('status', ['booked', 'confirmed'])
            ->whereNull('reminder_sent_24h')
            ->get();
            
        foreach ($reservations as $reservation) {
            if ($this->lineService->sendReminder($reservation, '24h')) {
                $reservation->update(['reminder_sent_24h' => now()]);
                $this->info("24時間前リマインダー送信: 予約ID {$reservation->id}");
            }
        }
    }
    
    /**
     * 3時間前リマインダー
     */
    protected function process3HourReminders()
    {
        $targetTime = Carbon::now()->addHours(3);
        
        $reservations = Reservation::with(['customer', 'store'])
            ->whereBetween('reservation_date', [
                $targetTime->copy()->startOfHour(),
                $targetTime->copy()->endOfHour()
            ])
            ->whereIn('status', ['booked', 'confirmed'])
            ->whereNull('reminder_sent_3h')
            ->get();
            
        foreach ($reservations as $reservation) {
            if ($this->lineService->sendReminder($reservation, '3h')) {
                $reservation->update(['reminder_sent_3h' => now()]);
                $this->info("3時間前リマインダー送信: 予約ID {$reservation->id}");
            }
        }
    }
    
    /**
     * ラベルベースの自動リマインダー
     * ※ LineReminderRuleモデルが未実装のため一時無効化
     */
    protected function processLabelBasedReminders()
    {
        // LineReminderRuleモデルが未実装のためスキップ
        $this->info('ラベルベースリマインダー: 機能未実装のためスキップ');
        return;

        /*
        $rules = LineReminderRule::where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            $customers = $rule->getTargetCustomers();

            foreach ($customers as $customer) {
                if ($this->lineService->sendRuleBasedMessage($customer, $rule)) {
                    $this->info("ルールベースメッセージ送信: {$rule->name} → 顧客ID {$customer->id}");
                }
            }
        }
        */
    }
    
    /**
     * ノーショーフォローアップ
     */
    protected function processNoShowFollowups()
    {
        // 3日前にノーショーした顧客
        $threeDaysAgo = Carbon::now()->subDays(3);
        
        $reservations = Reservation::with(['customer', 'store'])
            ->whereDate('reservation_date', $threeDaysAgo->toDateString())
            ->where('status', 'no_show')
            ->whereNull('no_show_follow_sent')
            ->get();
            
        foreach ($reservations as $reservation) {
            if ($this->lineService->sendNoShowFollowup($reservation)) {
                $reservation->update(['no_show_follow_sent' => now()]);
                
                // ノーショーラベルを付与
                CustomerLabel::assignAutoLabel($reservation->customer, 'no_show_once');
                
                $this->info("ノーショーフォロー送信: 予約ID {$reservation->id}");
            }
        }
    }
}