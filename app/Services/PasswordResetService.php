<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetService
{
    private EmailService $emailService;
    
    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }
    
    /**
     * パスワードリセットトークンを生成して送信
     *
     * @param string $email
     * @return bool
     */
    public function sendResetLink(string $email): bool
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            // セキュリティのため、ユーザーが存在しなくても成功を返す
            Log::info('Password reset requested for non-existent email', ['email' => $email]);
            return true;
        }
        
        // 既存のトークンを削除
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        
        // 新しいトークンを生成
        $token = Str::random(64);
        
        // トークンを保存
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);
        
        // リセットURLを生成
        $resetUrl = url("/admin/password-reset/{$token}?email=" . urlencode($email));
        
        // メール送信
        return $this->sendResetEmail($user, $resetUrl);
    }
    
    /**
     * パスワードリセットメールを送信
     *
     * @param User $user
     * @param string $resetUrl
     * @return bool
     */
    private function sendResetEmail(User $user, string $resetUrl): bool
    {
        $appName = config('app.name');
        $expiresIn = 60; // 60分
        
        $subject = "【{$appName}】パスワードリセットのご案内";
        
        $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #059669, #10b981); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #059669, #10b981); color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px; margin: 20px 0; }
        .code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$appName}</h1>
            <p style="margin: 0;">パスワードリセットのご案内</p>
        </div>
        <div class="content">
            <p>{$user->name} 様</p>
            
            <p>パスワードリセットのリクエストを受け付けました。<br>
            以下のボタンをクリックして、新しいパスワードを設定してください。</p>
            
            <div style="text-align: center;">
                <a href="{$resetUrl}" class="button">パスワードをリセット</a>
            </div>
            
            <p style="color: #6b7280; font-size: 14px;">
                ボタンが機能しない場合は、以下のURLをブラウザにコピー＆ペーストしてください：<br>
                <span class="code" style="word-break: break-all;">{$resetUrl}</span>
            </p>
            
            <div class="warning">
                <strong>ご注意：</strong><br>
                • このリンクの有効期限は<strong>{$expiresIn}分間</strong>です<br>
                • このメールに心当たりがない場合は、無視していただいて構いません<br>
                • パスワードは変更されません
            </div>
            
            <p style="color: #6b7280; font-size: 14px;">
                セキュリティのため、このリンクは1回のみ使用可能です。
            </p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$appName}. All rights reserved.</p>
            <p>このメールは自動送信されています。返信はできません。</p>
        </div>
    </div>
</body>
</html>
HTML;

        $textBody = <<<TEXT
【{$appName}】パスワードリセットのご案内

{$user->name} 様

パスワードリセットのリクエストを受け付けました。
以下のURLにアクセスして、新しいパスワードを設定してください。

{$resetUrl}

【ご注意】
• このリンクの有効期限は{$expiresIn}分間です
• このメールに心当たりがない場合は、無視していただいて構いません
• パスワードは変更されません

セキュリティのため、このリンクは1回のみ使用可能です。

---
このメールは自動送信されています。返信はできません。
© 2025 {$appName}. All rights reserved.
TEXT;
        
        return $this->emailService->sendEmail($user->email, $subject, $body, $textBody);
    }
    
    /**
     * トークンを検証
     *
     * @param string $token
     * @param string $email
     * @return bool
     */
    public function validateToken(string $token, string $email): bool
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();
        
        if (!$record) {
            return false;
        }
        
        // トークンの有効期限を確認（60分）
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return false;
        }
        
        // トークンを検証
        return Hash::check($token, $record->token);
    }
    
    /**
     * パスワードをリセット
     *
     * @param string $token
     * @param string $email
     * @param string $password
     * @return bool
     */
    public function resetPassword(string $token, string $email, string $password): bool
    {
        if (!$this->validateToken($token, $email)) {
            return false;
        }
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return false;
        }
        
        // パスワードを更新
        $user->password = Hash::make($password);
        $user->save();
        
        // トークンを削除
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        
        // 確認メールを送信
        $this->sendPasswordChangedNotification($user);
        
        return true;
    }
    
    /**
     * パスワード変更通知を送信
     *
     * @param User $user
     * @return bool
     */
    private function sendPasswordChangedNotification(User $user): bool
    {
        $appName = config('app.name');
        $subject = "【{$appName}】パスワード変更完了のお知らせ";
        
        $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #059669, #10b981); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 10px 10px; }
        .success { background: #d1fae5; border-left: 4px solid #10b981; padding: 10px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$appName}</h1>
            <p style="margin: 0;">パスワード変更完了</p>
        </div>
        <div class="content">
            <p>{$user->name} 様</p>
            
            <div class="success">
                <strong>パスワードが正常に変更されました</strong><br>
                新しいパスワードで管理画面にログインできます。
            </div>
            
            <p>変更日時: {$user->updated_at->format('Y年m月d日 H:i')}</p>
            
            <p style="color: #6b7280;">
                このパスワード変更に心当たりがない場合は、すぐにシステム管理者にご連絡ください。
            </p>
            
            <p>
                <a href="' . url('/admin/login') . '" style="color: #059669;">管理画面にログイン</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; 2025 {$appName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $textBody = <<<TEXT
【{$appName}】パスワード変更完了のお知らせ

{$user->name} 様

パスワードが正常に変更されました。
新しいパスワードで管理画面にログインできます。

変更日時: {$user->updated_at->format('Y年m月d日 H:i')}

このパスワード変更に心当たりがない場合は、すぐにシステム管理者にご連絡ください。

管理画面URL: ' . url('/admin/login') . '

---
© 2025 {$appName}. All rights reserved.
TEXT;
        
        return $this->emailService->sendEmail($user->email, $subject, $body, $textBody);
    }
}