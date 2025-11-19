<?php

namespace App\Services;

use App\Models\FcOrder;
use App\Models\FcInvoice;
use App\Models\Store;
use App\Models\User;
use App\Models\Announcement;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class FcNotificationService
{
    /**
     * 発注承認リクエスト通知（FC店舗→本部）
     */
    public function notifyOrderSubmitted(FcOrder $order): void
    {
        $headquarters = $order->headquartersStore;
        $fcStore = $order->fcStore;

        // 本部のsuper_admin取得
        $superAdmins = User::role('super_admin')->get();

        // メール通知
        foreach ($superAdmins as $admin) {
            $this->sendEmail(
                $admin->email,
                "【発注申請】{$fcStore->name}より発注がありました",
                $this->buildOrderSubmittedMessage($order, $fcStore)
            );
        }

        // お知らせ作成（本部向け）
        $this->createAnnouncement(
            "【新規発注】{$fcStore->name}より発注がありました",
            "発注番号: {$order->order_number}\n合計金額: ¥" . number_format($order->total_amount) . "\n\n管理画面から発送処理を行ってください。",
            'important',
            [$headquarters->id]
        );

        Log::info("FC発注通知送信", [
            'order_number' => $order->order_number,
            'fc_store' => $fcStore->name,
            'headquarters' => $headquarters->name,
            'recipients' => $superAdmins->pluck('email')->toArray(),
        ]);
    }

    /**
     * 発注承認通知（本部→FC店舗）
     */
    public function notifyOrderApproved(FcOrder $order): void
    {
        $fcStore = $order->fcStore;

        // FC店舗の管理者取得
        $storeManagers = $fcStore->managers;

        foreach ($storeManagers as $manager) {
            $this->sendEmail(
                $manager->email,
                "【発注承認】発注番号 {$order->order_number} が承認されました",
                $this->buildOrderApprovedMessage($order)
            );
        }

        Log::info("FC発注承認通知送信", [
            'order_number' => $order->order_number,
            'fc_store' => $fcStore->name,
            'recipients' => $storeManagers->pluck('email')->toArray(),
        ]);
    }

    /**
     * 発送通知（本部→FC店舗）
     */
    public function notifyOrderShipped(FcOrder $order): void
    {
        $fcStore = $order->fcStore;
        $storeManagers = $fcStore->managers;

        // メール通知
        foreach ($storeManagers as $manager) {
            $this->sendEmail(
                $manager->email,
                "【発送完了】発注番号 {$order->order_number} を発送しました",
                $this->buildOrderShippedMessage($order)
            );
        }

        // お知らせ作成（FC店舗向け）
        $trackingInfo = $order->shipping_tracking_number ? "追跡番号: {$order->shipping_tracking_number}" : '';
        $this->createAnnouncement(
            "【発送完了】発注番号 {$order->order_number}",
            "ご注文の商品を発送いたしました。\n{$trackingInfo}\n\n到着まで今しばらくお待ちください。",
            'normal',
            [$fcStore->id]
        );

        Log::info("FC発送通知送信", [
            'order_number' => $order->order_number,
            'tracking_number' => $order->shipping_tracking_number,
            'fc_store' => $fcStore->name,
        ]);
    }

    /**
     * 請求書発行通知（本部→FC店舗）
     */
    public function notifyInvoiceIssued(FcInvoice $invoice): void
    {
        $fcStore = $invoice->fcStore;
        $storeManagers = $fcStore->managers;

        // メール通知
        foreach ($storeManagers as $manager) {
            $this->sendEmail(
                $manager->email,
                "【請求書発行】請求書番号 {$invoice->invoice_number}",
                $this->buildInvoiceIssuedMessage($invoice)
            );
        }

        // お知らせ作成（FC店舗向け）
        $this->createAnnouncement(
            "【請求書発行】請求書番号 {$invoice->invoice_number}",
            "請求金額: ¥" . number_format($invoice->total_amount) . "\n支払期限: {$invoice->due_date->format('Y/m/d')}\n\nお支払期限までにお振込みをお願いいたします。",
            'important',
            [$fcStore->id]
        );

        Log::info("FC請求書発行通知送信", [
            'invoice_number' => $invoice->invoice_number,
            'fc_store' => $fcStore->name,
            'total_amount' => $invoice->total_amount,
        ]);
    }

    /**
     * 請求書送付通知（本部→FC店舗）
     */
    public function notifyInvoiceSent(FcInvoice $invoice): void
    {
        $fcStore = $invoice->fcStore;
        $storeManagers = $fcStore->managers;

        // メール通知
        foreach ($storeManagers as $manager) {
            $this->sendEmail(
                $manager->email,
                "【請求書送付】請求書番号 {$invoice->invoice_number}",
                $this->buildInvoiceIssuedMessage($invoice) // 同じメッセージを使用
            );
        }

        // お知らせ作成（FC店舗向け）
        $this->createAnnouncement(
            "【請求書送付】請求書番号 {$invoice->invoice_number}",
            "請求書を送付いたしました。\n請求金額: ¥" . number_format($invoice->total_amount) . "\n支払期限: {$invoice->due_date->format('Y/m/d')}\n\nご確認ください。",
            'important',
            [$fcStore->id]
        );

        Log::info("FC請求書送付通知送信", [
            'invoice_number' => $invoice->invoice_number,
            'fc_store' => $fcStore->name,
            'total_amount' => $invoice->total_amount,
        ]);
    }

    /**
     * 支払期限リマインダー（本部→FC店舗）
     */
    public function notifyPaymentReminder(FcInvoice $invoice): void
    {
        $fcStore = $invoice->fcStore;
        $storeManagers = $fcStore->managers;

        foreach ($storeManagers as $manager) {
            $this->sendEmail(
                $manager->email,
                "【支払期限リマインダー】請求書番号 {$invoice->invoice_number}",
                $this->buildPaymentReminderMessage($invoice)
            );
        }

        Log::info("FC支払期限リマインダー送信", [
            'invoice_number' => $invoice->invoice_number,
            'due_date' => $invoice->due_date->format('Y/m/d'),
            'outstanding_amount' => $invoice->outstanding_amount,
        ]);
    }

    /**
     * 支払期限超過アラート（本部に通知）
     */
    public function notifyPaymentOverdue(FcInvoice $invoice): void
    {
        $superAdmins = User::role('super_admin')->get();

        foreach ($superAdmins as $admin) {
            $this->sendEmail(
                $admin->email,
                "【支払期限超過】{$invoice->fcStore->name} - 請求書番号 {$invoice->invoice_number}",
                $this->buildPaymentOverdueMessage($invoice)
            );
        }

        Log::warning("FC支払期限超過アラート", [
            'invoice_number' => $invoice->invoice_number,
            'fc_store' => $invoice->fcStore->name,
            'overdue_days' => now()->diffInDays($invoice->due_date),
        ]);
    }

    /**
     * 入金確認通知（本部→FC店舗）
     */
    public function notifyPaymentReceived(FcInvoice $invoice, float $amount): void
    {
        $fcStore = $invoice->fcStore;
        $storeManagers = $fcStore->managers;

        // メール通知
        foreach ($storeManagers as $manager) {
            $this->sendEmail(
                $manager->email,
                "【入金確認】請求書番号 {$invoice->invoice_number}",
                $this->buildPaymentReceivedMessage($invoice, $amount)
            );
        }

        // お知らせ作成（FC店舗向け）
        $status = $invoice->status === 'paid' ? '✓ 入金完了しました' : '一部入金を確認しました';
        $statusDetail = $invoice->status === 'paid'
            ? "請求書番号 {$invoice->invoice_number} の入金が完了しました。"
            : "請求書番号 {$invoice->invoice_number} の一部入金を確認しました。";

        $this->createAnnouncement(
            "【入金確認】{$statusDetail}",
            "今回の入金額: ¥" . number_format($amount) . "\n" .
            "残高: ¥" . number_format($invoice->outstanding_amount) . "\n\n" .
            "ご入金ありがとうございます。",
            'normal',
            [$fcStore->id]
        );

        Log::info("FC入金確認通知送信", [
            'invoice_number' => $invoice->invoice_number,
            'amount' => $amount,
            'remaining' => $invoice->outstanding_amount,
        ]);
    }

    /**
     * メール送信（AWS SES使用）
     */
    protected function sendEmail(string $to, string $subject, string $message): bool
    {
        try {
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return true;
        } catch (\Exception $e) {
            Log::error("FCメール送信失敗", [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ========== メッセージテンプレート ==========

    protected function buildOrderSubmittedMessage(FcOrder $order, Store $fcStore): string
    {
        $itemsList = $order->items->map(function ($item) {
            return "  - {$item->product_name} x {$item->quantity} = ¥" . number_format($item->total);
        })->join("\n");

        return <<<MESSAGE
FC加盟店からの発注申請です。

【発注情報】
発注番号: {$order->order_number}
発注元: {$fcStore->name}
発注日時: {$order->ordered_at->format('Y/m/d H:i')}

【発注内容】
{$itemsList}

【金額】
小計（税抜）: ¥{number_format($order->subtotal)}
消費税: ¥{number_format($order->tax_amount)}
合計（税込）: ¥{number_format($order->total_amount)}

【備考】
{$order->notes}

管理画面から承認処理を行ってください。
MESSAGE;
    }

    protected function buildOrderApprovedMessage(FcOrder $order): string
    {
        return <<<MESSAGE
発注が承認されました。

【発注情報】
発注番号: {$order->order_number}
承認日時: {$order->approved_at->format('Y/m/d H:i')}
合計金額: ¥{number_format($order->total_amount)}

発送準備が完了次第、追跡番号をお知らせいたします。
MESSAGE;
    }

    protected function buildOrderShippedMessage(FcOrder $order): string
    {
        $trackingInfo = $order->shipping_tracking_number
            ? "追跡番号: {$order->shipping_tracking_number}"
            : "追跡番号: なし";

        return <<<MESSAGE
ご注文の商品を発送いたしました。

【発送情報】
発注番号: {$order->order_number}
発送日時: {$order->shipped_at->format('Y/m/d H:i')}
{$trackingInfo}

到着まで今しばらくお待ちください。
MESSAGE;
    }

    protected function buildInvoiceIssuedMessage(FcInvoice $invoice): string
    {
        return <<<MESSAGE
請求書を発行いたしました。

【請求書情報】
請求書番号: {$invoice->invoice_number}
発行日: {$invoice->issue_date->format('Y/m/d')}
支払期限: {$invoice->due_date->format('Y/m/d')}

【請求金額】
小計（税抜）: ¥{number_format($invoice->subtotal)}
消費税: ¥{number_format($invoice->tax_amount)}
合計（税込）: ¥{number_format($invoice->total_amount)}

【請求対象期間】
{$invoice->billing_period_start->format('Y/m/d')} - {$invoice->billing_period_end->format('Y/m/d')}

お支払期限までにお振込みをお願いいたします。
MESSAGE;
    }

    protected function buildPaymentReminderMessage(FcInvoice $invoice): string
    {
        $daysUntilDue = now()->diffInDays($invoice->due_date, false);

        return <<<MESSAGE
お支払期限が近づいております。

【請求書情報】
請求書番号: {$invoice->invoice_number}
支払期限: {$invoice->due_date->format('Y/m/d')}（あと{$daysUntilDue}日）

【未払い金額】
¥{number_format($invoice->outstanding_amount)}

お支払期限までにお振込みをお願いいたします。
MESSAGE;
    }

    protected function buildPaymentOverdueMessage(FcInvoice $invoice): string
    {
        $overdueDays = now()->diffInDays($invoice->due_date);

        return <<<MESSAGE
支払期限が超過した請求書があります。

【請求書情報】
請求書番号: {$invoice->invoice_number}
請求先: {$invoice->fcStore->name}
支払期限: {$invoice->due_date->format('Y/m/d')}（{$overdueDays}日超過）

【未払い金額】
¥{number_format($invoice->outstanding_amount)}

早急に確認・対応をお願いします。
MESSAGE;
    }

    protected function buildPaymentReceivedMessage(FcInvoice $invoice, float $amount): string
    {
        $status = $invoice->status === 'paid' ? '入金完了' : '一部入金';

        return <<<MESSAGE
入金を確認いたしました。

【請求書情報】
請求書番号: {$invoice->invoice_number}
ステータス: {$status}

【今回の入金】
¥{number_format($amount)}

【残高】
¥{number_format($invoice->outstanding_amount)}

ありがとうございます。
MESSAGE;
    }

    /**
     * お知らせ作成
     *
     * @param string $title タイトル
     * @param string $content 内容
     * @param string $priority 優先度 (normal, important, urgent)
     * @param array $storeIds 対象店舗ID配列
     */
    protected function createAnnouncement(
        string $title,
        string $content,
        string $priority = 'normal',
        array $storeIds = []
    ): ?Announcement {
        try {
            // システムユーザーIDを取得（super_adminの最初のユーザー、または最初のユーザー）
            $systemUser = User::role('super_admin')->first() ?? User::first();
            if (!$systemUser) {
                Log::error("お知らせ作成失敗: システムユーザーが見つかりません");
                return null;
            }

            $announcement = Announcement::create([
                'title' => $title,
                'content' => $content,
                'priority' => $priority,
                'target_type' => empty($storeIds) ? 'all' : 'specific_stores',
                'published_at' => now(),
                'expires_at' => now()->addDays(30), // 30日後に期限切れ
                'is_active' => true,
                'created_by' => $systemUser->id,
            ]);

            // 対象店舗を関連付け
            if (!empty($storeIds)) {
                $announcement->stores()->sync($storeIds);
            }

            Log::info("お知らせ作成", [
                'announcement_id' => $announcement->id,
                'title' => $title,
                'target_stores' => $storeIds,
            ]);

            return $announcement;
        } catch (\Exception $e) {
            Log::error("お知らせ作成失敗", [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
