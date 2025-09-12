<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Customer;
use App\Models\User;
use App\Models\Store;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminNotificationService
{
    private $smsService;
    
    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    
    /**
     * 新規予約時のアドミン通知
     */
    public function notifyNewReservation(Reservation $reservation): void
    {
        $store = $reservation->store;
        $customer = $reservation->customer;
        
        // 店舗管理者に通知
        $admins = $this->getStoreAdmins($store);
        
        $message = $this->buildNewReservationMessage($reservation, $customer);
        
        foreach ($admins as $admin) {
            $this->sendNotification($admin, $message, 'new_reservation');
        }
        
        \Log::info('Admin notification sent for new reservation', [
            'reservation_id' => $reservation->id,
            'store_id' => $store->id,
            'admin_count' => count($admins),
            'admins_notified' => collect($admins)->pluck('email')->toArray()
        ]);
    }
    
    /**
     * 予約キャンセル時のアドミン通知
     */
    public function notifyReservationCancelled(Reservation $reservation): void
    {
        $store = $reservation->store;
        $customer = $reservation->customer;
        
        $admins = $this->getStoreAdmins($store);
        
        $message = $this->buildCancellationMessage($reservation, $customer);
        
        foreach ($admins as $admin) {
            $this->sendNotification($admin, $message, 'cancellation');
        }
        
        Log::info('Admin notification sent for reservation cancellation', [
            'reservation_id' => $reservation->id,
            'store_id' => $store->id
        ]);
    }
    
    /**
     * 予約変更時のアドミン通知
     */
    public function notifyReservationChanged(Reservation $oldReservation, Reservation $newReservation): void
    {
        $store = $newReservation->store;
        $customer = $newReservation->customer;
        
        $admins = $this->getStoreAdmins($store);
        
        $message = $this->buildChangeMessage($oldReservation, $newReservation, $customer);
        
        foreach ($admins as $admin) {
            $this->sendNotification($admin, $message, 'change');
        }
        
        Log::info('Admin notification sent for reservation change', [
            'old_reservation_id' => $oldReservation->id,
            'new_reservation_id' => $newReservation->id,
            'store_id' => $store->id
        ]);
    }
    
    /**
     * 当日予約アラート
     */
    public function notifyTodayReservations(Store $store): void
    {
        $todayReservations = Reservation::where('store_id', $store->id)
            ->whereDate('reservation_date', today())
            ->where('status', 'confirmed')
            ->with('customer', 'menu')
            ->get();
        
        if ($todayReservations->isEmpty()) {
            return;
        }
        
        $admins = $this->getStoreAdmins($store);
        $message = $this->buildTodayReservationsMessage($todayReservations, $store);
        
        foreach ($admins as $admin) {
            $this->sendNotification($admin, $message, 'today_summary');
        }
    }
    
    /**
     * 店舗管理者の取得
     */
    private function getStoreAdmins(Store $store): \Illuminate\Support\Collection
    {
        $admins = collect();
        
        // 店舗オーナーと管理者を取得
        if ($store->managers()->exists()) {
            $storeManagers = $store->managers()->get();
            $admins = $admins->merge($storeManagers);
        }
        
        // スーパー管理者も含める（全店舗の重要イベントを受信）
        $superAdmins = User::role('super_admin')->get();
        $admins = $admins->merge($superAdmins);
        
        // テスト用：開発環境では指定メールアドレスにも送信
        if (app()->environment(['local', 'development'])) {
            $testUser = new User();
            $testUser->email = 'dasuna2305@gmail.com';
            $testUser->name = 'Test Admin';
            $testUser->notification_preferences = [
                'email_enabled' => true,
                'sms_enabled' => false
            ];
            $admins->push($testUser);
        }
        
        return $admins->unique('email')->values();
    }
    
    /**
     * 通知送信（SMS/メール）
     */
    private function sendNotification(User $admin, string $message, string $type): void
    {
        // SMS通知（電話番号がある場合）
        if ($admin->phone && $this->shouldSendSms($admin, $type)) {
            $this->smsService->sendSms($admin->phone, $message);
        }
        
        // メール通知（メールアドレスがある場合）
        if ($admin->email && $this->shouldSendEmail($admin, $type)) {
            $this->sendEmailNotification($admin, $message, $type);
        }
    }
    
    /**
     * SMS送信すべきかの判定
     */
    private function shouldSendSms(User $admin, string $type): bool
    {
        $preferences = $admin->notification_preferences ?? [];
        
        // 緊急通知は常に送信
        if (in_array($type, ['cancellation', 'change'])) {
            return true;
        }
        
        return $preferences['sms_enabled'] ?? false;
    }
    
    /**
     * メール送信すべきかの判定
     */
    private function shouldSendEmail(User $admin, string $type): bool
    {
        $preferences = $admin->notification_preferences ?? [];
        
        return $preferences['email_enabled'] ?? true; // デフォルトでメール有効
    }
    
    /**
     * メール通知送信
     */
    private function sendEmailNotification(User $admin, string $message, string $type): void
    {
        $subject = $this->getEmailSubject($type);
        
        Mail::raw($message, function ($mail) use ($admin, $subject) {
            $mail->to($admin->email)
                 ->subject($subject);
        });
    }
    
    /**
     * メール件名の取得
     */
    private function getEmailSubject(string $type): string
    {
        return match($type) {
            'new_reservation' => '新規予約が入りました',
            'cancellation' => '予約がキャンセルされました',
            'change' => '予約が変更されました',
            'today_summary' => '本日の予約一覧',
            default => '予約管理通知',
        };
    }
    
    /**
     * 新規予約メッセージ作成
     */
    private function buildNewReservationMessage(Reservation $reservation, Customer $customer): string
    {
        $dateStr = Carbon::parse($reservation->reservation_date)->format('m月d日');
        $timeStr = Carbon::parse($reservation->start_time)->format('H:i');
        
        return "【新規予約】\n" .
               "顧客: {$customer->last_name} {$customer->first_name}様\n" .
               "日時: {$dateStr} {$timeStr}\n" .
               "メニュー: {$reservation->menu->name}\n" .
               "予約ID: {$reservation->id}";
    }
    
    /**
     * キャンセルメッセージ作成
     */
    private function buildCancellationMessage(Reservation $reservation, Customer $customer): string
    {
        $dateStr = Carbon::parse($reservation->reservation_date)->format('m月d日');
        $timeStr = Carbon::parse($reservation->start_time)->format('H:i');
        
        return "【予約キャンセル】\n" .
               "顧客: {$customer->last_name} {$customer->first_name}様\n" .
               "日時: {$dateStr} {$timeStr}\n" .
               "メニュー: {$reservation->menu->name}\n" .
               "予約ID: {$reservation->id}";
    }
    
    /**
     * 変更メッセージ作成
     */
    private function buildChangeMessage(Reservation $oldReservation, Reservation $newReservation, Customer $customer): string
    {
        $oldDateStr = Carbon::parse($oldReservation->reservation_date)->format('m月d日');
        $oldTimeStr = Carbon::parse($oldReservation->start_time)->format('H:i');
        $newDateStr = Carbon::parse($newReservation->reservation_date)->format('m月d日');
        $newTimeStr = Carbon::parse($newReservation->start_time)->format('H:i');
        
        return "【予約変更】\n" .
               "顧客: {$customer->last_name} {$customer->first_name}様\n" .
               "変更前: {$oldDateStr} {$oldTimeStr}\n" .
               "変更後: {$newDateStr} {$newTimeStr}\n" .
               "新予約ID: {$newReservation->id}";
    }
    
    /**
     * 本日予約一覧メッセージ作成
     */
    private function buildTodayReservationsMessage($reservations, Store $store): string
    {
        $message = "【{$store->name} 本日の予約】\n";
        $message .= "予約件数: " . $reservations->count() . "件\n\n";
        
        foreach ($reservations as $reservation) {
            $timeStr = Carbon::parse($reservation->start_time)->format('H:i');
            $customer = $reservation->customer;
            $message .= "• {$timeStr} {$customer->last_name}様 ({$reservation->menu->name})\n";
        }
        
        return $message;
    }
}