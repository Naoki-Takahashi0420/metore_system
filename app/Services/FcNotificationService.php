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
     * 納品完了通知（本部→FC店舗）
     */
    public function notifyOrderDelivered(FcOrder $order, ?FcInvoice $invoice = null): void
    {
        $fcStore = $order->fcStore;
        $storeManagers = $fcStore->managers;

        // メール通知
        foreach ($storeManagers as $manager) {
            $this->sendEmail(
                $manager->email,
                "【納品完了】発注番号 {$order->order_number} の納品が完了しました",
                $this->buildOrderDeliveredMessage($order, $invoice)
            );
        }

        // お知らせ作成（FC店舗向け）
        $invoiceInfo = $invoice 
            ? "請求書番号: {$invoice->invoice_number}\n請求金額: ¥" . number_format($invoice->total_amount)
            : "請求書は別途発行いたします";
            
        $this->createAnnouncement(
            "【納品完了】発注番号 {$order->order_number}",
            "ご注文の商品の納品が完了いたしました。\n\n{$invoiceInfo}\n\nご確認をお願いいたします。",
            'normal',
            [$fcStore->id]
        );

        Log::info("FC納品完了通知送信", [
            'order_number' => $order->order_number,
            'fc_store' => $fcStore->name,
            'invoice_number' => $invoice?->invoice_number,
        ]);
    }

    /**
     * 月初請求書一括生成通知（本部→FC店舗）
     */
    public function notifyMonthlyInvoiceGenerated(FcInvoice $invoice): void
    {
        $fcStore = $invoice->fcStore;
        $storeManagers = $fcStore->managers;

        // メール通知
        foreach ($storeManagers as $manager) {
            $this->sendEmail(
                $manager->email,
                "【月次請求書発行】{$invoice->billing_period_start->format('Y年m月')}分の請求書を発行しました",
                $this->buildMonthlyInvoiceMessage($invoice)
            );
        }

        // お知らせ作成（FC店舗向け - 発注通知として分類）
        $this->createMonthlyInvoiceAnnouncement($invoice);

        Log::info("FC月次請求書発行通知送信", [
            'invoice_number' => $invoice->invoice_number,
            'fc_store' => $fcStore->name,
            'billing_period' => $invoice->billing_period_start->format('Y-m'),
            'total_amount' => $invoice->total_amount,
        ]);
    }

    /**
     * 納品完了通知（エイリアス、recordDelivery用）
     */
    public function notifyDeliveryCompleted(FcOrder $order, FcInvoice $invoice): void
    {
        $this->notifyOrderDelivered($order, $invoice);
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
            $unitPrice = number_format($item->unit_price);
            $itemTotal = number_format($item->total);
            return "  ◆ {$item->product_name}\n    　数量: {$item->quantity}個　単価: ¥{$unitPrice}　小計: ¥{$itemTotal}";
        })->join("\n");

        $itemCount = $order->items->count();
        $totalQuantity = $order->items->sum('quantity');

        return <<<MESSAGE
🏪 FC加盟店より新規発注申請がございました

【📋 発注概要】
発注番号: {$order->order_number}
発注店舗: {$fcStore->name}
発注日時: {$order->ordered_at->format('Y年m月d日 H:i')}
商品種類: {$itemCount}種類　総数量: {$totalQuantity}個

【📦 発注明細】
{$itemsList}

【💰 金額内訳】
小計（税抜）: ¥{number_format($order->subtotal)}
消費税（10%）: ¥{number_format($order->tax_amount)}
━━━━━━━━━━━━━━━━━━
合計（税込）: ¥{number_format($order->total_amount)}

【📝 連絡事項】
{$order->notes}

【⚡ 次のステップ】
管理画面にログインして「承認」処理を行い、発送準備を開始してください。
MESSAGE;
    }

    protected function buildOrderApprovedMessage(FcOrder $order): string
    {
        $itemCount = $order->items->count();
        $totalQuantity = $order->items->sum('quantity');
        
        return <<<MESSAGE
✅ ご発注が承認されました

【📋 発注概要】
発注番号: {$order->order_number}
承認日時: {$order->approved_at->format('Y年m月d日 H:i')}
商品種類: {$itemCount}種類　総数量: {$totalQuantity}個
合計金額: ¥{number_format($order->total_amount)}

【📦 次のステップ】
発送準備を開始いたします。
発送完了次第、追跡番号と共にご連絡いたします。

引き続きよろしくお願いいたします。
MESSAGE;
    }

    protected function buildOrderShippedMessage(FcOrder $order): string
    {
        $trackingInfo = $order->shipping_tracking_number
            ? "🔍 追跡番号: {$order->shipping_tracking_number}"
            : "📦 追跡番号: 設定なし";
            
        $itemCount = $order->items->count();
        $totalQuantity = $order->items->sum('quantity');

        return <<<MESSAGE
🚚 ご注文商品を発送いたしました

【📋 発注情報】
発注番号: {$order->order_number}
商品種類: {$itemCount}種類　総数量: {$totalQuantity}個
合計金額: ¥{number_format($order->total_amount)}

【🚛 発送詳細】
発送日時: {$order->shipped_at->format('Y年m月d日 H:i')}
{$trackingInfo}

【📅 お届け予定】
通常1-2営業日でお届け予定です。
到着まで今しばらくお待ちください。

商品到着後は内容をご確認いただき、
何かございましたらお気軽にお問い合わせください。
MESSAGE;
    }

    protected function buildInvoiceIssuedMessage(FcInvoice $invoice): string
    {
        $daysUntilDue = now()->diffInDays($invoice->due_date, false);
        
        return <<<MESSAGE
📄 請求書を発行いたしました

【📋 請求書情報】
請求書番号: {$invoice->invoice_number}
発行日: {$invoice->issue_date->format('Y年m月d日')}
支払期限: {$invoice->due_date->format('Y年m月d日')}（{$daysUntilDue}日後）

【💰 請求金額】
小計（税抜）: ¥{number_format($invoice->subtotal)}
消費税（10%）: ¥{number_format($invoice->tax_amount)}
━━━━━━━━━━━━━━━━━━
合計（税込）: ¥{number_format($invoice->total_amount)}

【📅 請求対象期間】
{$invoice->billing_period_start->format('Y年m月d日')} ～ {$invoice->billing_period_end->format('Y年m月d日')}

【🏦 お支払いについて】
お支払期限までに指定口座へのお振込みをお願いいたします。
ご不明な点がございましたらお気軽にお問い合わせください。
MESSAGE;
    }

    protected function buildPaymentReminderMessage(FcInvoice $invoice): string
    {
        $daysUntilDue = now()->diffInDays($invoice->due_date, false);
        $urgencyIcon = $daysUntilDue <= 3 ? '🔥' : '⏰';
        
        return <<<MESSAGE
{$urgencyIcon} お支払期限のリマインダー

【📋 請求書情報】
請求書番号: {$invoice->invoice_number}
支払期限: {$invoice->due_date->format('Y年m月d日')}（あと{$daysUntilDue}日）

【💰 未払い金額】
¥{number_format($invoice->outstanding_amount)}

【⚡ お願い】
お支払期限が近づいております。
期限内のお振込みをお願いいたします。

既にお振込み済みの場合は、確認にお時間をいただく場合がございます。
ご不明な点がございましたらお気軽にお問い合わせください。
MESSAGE;
    }

    protected function buildPaymentOverdueMessage(FcInvoice $invoice): string
    {
        $overdueDays = now()->diffInDays($invoice->due_date);

        return <<<MESSAGE
🚨 【緊急】支払期限超過のお知らせ

【⚠️ 請求書情報】
請求書番号: {$invoice->invoice_number}
請求先FC店舗: {$invoice->fcStore->name}
支払期限: {$invoice->due_date->format('Y年m月d日')}（{$overdueDays}日超過）

【💰 未払い金額】
¥{number_format($invoice->outstanding_amount)}

【🔥 対応が必要】
支払期限を{$overdueDays}日超過しています。
加盟店への確認と早急な対応をお願いします。

・入金確認の見落としがないかチェック
・加盟店への督促連絡
・支払い計画の確認

本部管理者は速やかに対応してください。
MESSAGE;
    }

    protected function buildOrderDeliveredMessage(FcOrder $order, ?FcInvoice $invoice = null): string
    {
        $itemsList = $order->items->map(function ($item) {
            return "  - {$item->product_name} x {$item->quantity} = ¥" . number_format($item->total);
        })->join("\n");

        $invoiceSection = $invoice
            ? "\n【請求書情報】\n請求書番号: {$invoice->invoice_number}\n請求金額: ¥" . number_format($invoice->total_amount) . "\n支払期限: {$invoice->due_date->format('Y/m/d')}"
            : "\n【請求書】\n請求書は別途発行いたします。";

        return <<<MESSAGE
発注いただいた商品の納品が完了いたしました。

【発注情報】
発注番号: {$order->order_number}
発注元: {$order->fcStore->name}
発注日時: {$order->ordered_at->format('Y/m/d H:i')}
納品日時: {$order->delivered_at->format('Y/m/d H:i')}

【納品内容】
{$itemsList}

【合計金額】
¥{number_format($order->total_amount)}{$invoiceSection}

商品の確認をお願いいたします。
何かご不明な点がございましたら、お気軽にお問い合わせください。
MESSAGE;
    }

    protected function buildPaymentReceivedMessage(FcInvoice $invoice, float $amount): string
    {
        $status = $invoice->status === 'paid' ? '✅ 入金完了' : '🔄 一部入金';
        $completionMessage = $invoice->status === 'paid'
            ? "\n🎉 請求書の入金が完了いたしました。\nありがとうございました。"
            : "\n📝 残金のお支払いをお待ちしております。";

        return <<<MESSAGE
💰 入金を確認いたしました

【📋 請求書情報】
請求書番号: {$invoice->invoice_number}
ステータス: {$status}

【💵 今回の入金】
¥{number_format($amount)}

【📊 残高状況】
¥{number_format($invoice->outstanding_amount)}{$completionMessage}

今後ともよろしくお願いいたします。
MESSAGE;
    }

    protected function buildMonthlyInvoiceMessage(FcInvoice $invoice): string
    {
        $daysUntilDue = now()->diffInDays($invoice->due_date, false);
        $itemsCount = $invoice->items->count();
        
        return <<<MESSAGE
📅 月次請求書を発行いたしました

【📋 請求書情報】
請求書番号: {$invoice->invoice_number}
対象期間: {$invoice->billing_period_start->format('Y年m月d日')} ～ {$invoice->billing_period_end->format('Y年m月d日')}
発行日: {$invoice->issue_date->format('Y年m月d日')}
支払期限: {$invoice->due_date->format('Y年m月d日')}（{$daysUntilDue}日後）

【📦 請求内容】
商品・サービス: {$itemsCount}件
前月納品分の商品代金をまとめて請求いたします。

【💰 請求金額】
小計（税抜）: ¥{number_format($invoice->subtotal)}
消費税（10%）: ¥{number_format($invoice->tax_amount)}
━━━━━━━━━━━━━━━━━━
合計（税込）: ¥{number_format($invoice->total_amount)}

【🏦 お支払いについて】
お支払期限までに指定口座へのお振込みをお願いいたします。
詳細は管理画面よりPDF請求書をダウンロードしてご確認ください。

今後ともよろしくお願いいたします。
MESSAGE;
    }

    /**
     * 月次請求書用のお知らせ作成（発注通知として分類）
     */
    protected function createMonthlyInvoiceAnnouncement(FcInvoice $invoice): ?Announcement
    {
        try {
            $systemUser = User::role('super_admin')->first() ?? User::first();
            if (!$systemUser) {
                Log::error("月次請求書お知らせ作成失敗: システムユーザーが見つかりません");
                return null;
            }

            $announcement = Announcement::create([
                'type' => Announcement::TYPE_ORDER_NOTIFICATION, // 発注通知として分類
                'title' => "【月次請求書】{$invoice->billing_period_start->format('Y年m月')}分の請求書発行",
                'content' => "請求書番号: {$invoice->invoice_number}\n" .
                           "請求期間: {$invoice->billing_period_start->format('Y年m月d日')} ～ {$invoice->billing_period_end->format('Y年m月d日')}\n" .
                           "請求金額: ¥" . number_format($invoice->total_amount) . "\n" .
                           "支払期限: {$invoice->due_date->format('Y年m月d日')}\n\n" .
                           "前月にご注文いただいた商品の請求書を発行いたしました。\n" .
                           "管理画面よりPDFをダウンロードしてご確認ください。",
                'priority' => 'important',
                'target_type' => 'specific_stores',
                'published_at' => now(),
                'expires_at' => $invoice->due_date->addDays(7), // 支払期限の1週間後まで表示
                'is_active' => true,
                'created_by' => $systemUser->id,
            ]);

            // FC店舗を関連付け
            $announcement->stores()->sync([$invoice->fc_store_id]);

            Log::info("月次請求書お知らせ作成", [
                'announcement_id' => $announcement->id,
                'invoice_number' => $invoice->invoice_number,
                'fc_store_id' => $invoice->fc_store_id,
            ]);

            return $announcement;
        } catch (\Exception $e) {
            Log::error("月次請求書お知らせ作成失敗", [
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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
