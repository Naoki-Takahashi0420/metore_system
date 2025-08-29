<?php

namespace App\Filament\Resources\CustomerAccessTokenResource\Pages;

use App\Filament\Resources\CustomerAccessTokenResource;
use App\Models\CustomerAccessToken;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CreateCustomerAccessToken extends CreateRecord
{
    protected static string $resource = CustomerAccessTokenResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // トークンが空の場合は自動生成
        if (empty($data['token'])) {
            $data['token'] = $this->generateUniqueToken();
        }
        
        // デフォルト値の設定
        $data['is_active'] = $data['is_active'] ?? true;
        $data['usage_count'] = 0;
        
        // デフォルトの有効期限（6ヶ月）
        if (empty($data['expires_at'])) {
            $data['expires_at'] = Carbon::now()->addMonths(6);
        }
        
        return $data;
    }
    
    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(32);
        } while (CustomerAccessToken::where('token', $token)->exists());

        return $token;
    }
}
