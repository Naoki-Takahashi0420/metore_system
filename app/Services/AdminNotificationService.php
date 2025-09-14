<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Customer;
use App\Models\User;
use App\Models\Store;
use App\Services\Sms\SmsService;
use App\Services\EmailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminNotificationService
{
    private $smsService;
    private $emailService;
    
    public function __construct(SmsService $smsService, EmailService $emailService)
    {
        $this->smsService = $smsService;
        $this->emailService = $emailService;
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
            'admin_count' => $admins->count(),
            'admins_notified' => $admins->pluck('email')->toArray()
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
        $adminIds = collect();

        // 店舗オーナーと管理者を取得
        try {
            if ($store->managers()->exists()) {
                $storeManagerIds = $store->managers()->pluck('users.id');
                $adminIds = $adminIds->merge($storeManagerIds);
            }
        } catch (\Exception $e) {
            \Log::warning('店舗管理者の取得に失敗: ' . $e->getMessage());
            // 代替として、store_idが一致するユーザーを取得
            $storeUserIds = User::where('store_id', $store->id)->pluck('id');
            $adminIds = $adminIds->merge($storeUserIds);
        }

        // スーパー管理者も含める（全店舗の重要イベントを受信）
        try {
            $superAdminIds = User::role('super_admin')->pluck('id');
            $adminIds = $adminIds->merge($superAdminIds);
        } catch (\Exception $e) {
            // ロールシステムが利用できない場合は、全管理者を取得
            $allAdminIds = User::where('is_admin', true)->pluck('id');
            $adminIds = $adminIds->merge($allAdminIds);
        }

        // 本番環境でも高橋直希には必ず通知を送る（オーナー用）
        $owner = User::where('email', 'dasuna2305@gmail.com')->first();
        if ($owner) {
            $adminIds->push($owner->id);
        }

        // 重複を除去してからユーザーオブジェクトを取得
        $uniqueAdminIds = $adminIds->unique()->filter();

        return User::whereIn('id', $uniqueAdminIds)->get();
    }
    
    /**
     * 通知送信（SMS/メール）
     */
    private function sendNotification(User $admin, string $message, string $type): void
    {
        // 無効なユーザーには通知を送信しない
        if (!$admin->is_active) {
            return;
        }

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
        // SMSは現在使用していない
        return false;
    }
    
    /**
     * メール送信すべきかの判定
     */
    private function shouldSendEmail(User $admin, string $type): bool
    {
        $preferences = $admin->notification_preferences ?? [];
        
        // 予約通知が無効の場合は送信しない
        return $preferences['email_enabled'] ?? true;
    }
    
    /**
     * メール通知送信
     */
    private function sendEmailNotification(User $admin, string $message, string $type): void
    {
        $subject = $this->getEmailSubject($type);
        
        // HTMLメール用のフォーマット
        $htmlMessage = nl2br(htmlspecialchars($message));
        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #059669; padding: 20px; text-align: center; color: white; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
        .message { white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>目のトレーニング 管理者通知</h2>
        </div>
        <div class="content">
            <div class="message">{$htmlMessage}</div>
        </div>
    </div>
</body>
</html>
HTML;
        
        // EmailServiceを使用してメール送信
        $this->emailService->sendEmail($admin->email, $subject, $htmlBody, $message);
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