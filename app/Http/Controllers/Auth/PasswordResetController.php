<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    private PasswordResetService $passwordResetService;
    
    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }
    
    /**
     * パスワードリセット申請画面
     */
    public function showRequestForm()
    {
        return view('auth.password-reset-request');
    }
    
    /**
     * パスワードリセットリンク送信
     */
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);
        
        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $this->passwordResetService->sendResetLink($request->email);
        
        return back()->with('status', 'パスワードリセットリンクをメールで送信しました。');
    }
    
    /**
     * パスワードリセット画面
     */
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }
    
    /**
     * パスワードリセット実行
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);
        
        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $result = $this->passwordResetService->resetPassword(
            $request->token,
            $request->email,
            $request->password
        );
        
        if (!$result) {
            return back()->withErrors(['email' => 'リセットリンクが無効または期限切れです。']);
        }
        
        return redirect('/admin/login')
            ->with('status', 'パスワードが正常に変更されました。新しいパスワードでログインしてください。');
    }
}