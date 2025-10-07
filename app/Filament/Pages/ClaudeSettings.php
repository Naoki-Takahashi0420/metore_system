<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClaudeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Claude設定';
    protected static ?string $title = 'Claude AIヘルプ設定';
    protected static ?string $navigationGroup = '設定';
    protected static ?int $navigationSort = 100;
    protected static string $view = 'filament.pages.claude-settings';

    public ?string $api_key = '';
    public bool $enabled = true;
    public int $daily_limit_per_user = 20;
    public int $monthly_limit_total = 1000;

    public function mount(): void
    {
        // super_admin以外はアクセス不可
        abort_unless(auth()->user()->hasRole('super_admin'), 403);

        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        // データベースから設定を読み込み
        $settings = DB::table('settings')
            ->where('key', 'like', 'claude.%')
            ->pluck('value', 'key');

        $this->api_key = $settings['claude.api_key'] ?? config('claude.api_key');
        $this->enabled = ($settings['claude.enabled'] ?? config('claude.enabled')) === '1' ||
                        ($settings['claude.enabled'] ?? config('claude.enabled')) === true;
        $this->daily_limit_per_user = (int)($settings['claude.daily_limit_per_user'] ?? config('claude.rate_limit.daily_per_user'));
        $this->monthly_limit_total = (int)($settings['claude.monthly_limit_total'] ?? config('claude.rate_limit.monthly_total'));
    }

    public function save(): void
    {
        // バリデーション
        if ($this->enabled && empty($this->api_key)) {
            Notification::make()
                ->title('APIキーが必要です')
                ->body('ヘルプチャット機能を有効にするには、Claude APIキーを入力してください。')
                ->danger()
                ->send();
            return;
        }

        try {
            // settingsテーブルが存在しない場合は作成
            if (!DB::getSchemaBuilder()->hasTable('settings')) {
                DB::statement("
                    CREATE TABLE IF NOT EXISTS settings (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `key` VARCHAR(255) NOT NULL UNIQUE,
                        `value` TEXT,
                        created_at TIMESTAMP NULL DEFAULT NULL,
                        updated_at TIMESTAMP NULL DEFAULT NULL
                    )
                ");
            }

            // 設定を保存
            $this->upsertSetting('claude.api_key', $this->api_key);
            $this->upsertSetting('claude.enabled', $this->enabled ? '1' : '0');
            $this->upsertSetting('claude.daily_limit_per_user', (string)$this->daily_limit_per_user);
            $this->upsertSetting('claude.monthly_limit_total', (string)$this->monthly_limit_total);

            // キャッシュをクリア
            Cache::forget('claude_settings');

            Notification::make()
                ->title('設定を保存しました')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('エラーが発生しました')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function upsertSetting(string $key, string $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => $value,
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())')
            ]
        );
    }

    public function testConnection(): void
    {
        if (empty($this->api_key)) {
            Notification::make()
                ->title('APIキーを入力してください')
                ->warning()
                ->send();
            return;
        }

        try {
            $service = app(\App\Services\ClaudeHelpService::class);
            $response = $service->testConnection($this->api_key);

            if ($response['success']) {
                Notification::make()
                    ->title('接続成功')
                    ->body('Claude APIに正常に接続できました。')
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('接続失敗')
                    ->body($response['error'] ?? '不明なエラー')
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('接続テスト失敗')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Claude API設定')
                ->description('Claude AIヘルプチャット機能の設定を行います。')
                ->schema([
                    Toggle::make('enabled')
                        ->label('ヘルプチャット機能を有効化')
                        ->helperText('管理画面にAIヘルプチャットボタンを表示します。'),

                    TextInput::make('api_key')
                        ->label('Claude APIキー')
                        ->password()
                        ->revealable()
                        ->helperText('Anthropic Consoleで取得したAPIキーを入力してください。')
                        ->required($this->enabled)
                        ->placeholder('sk-ant-api03-...'),
                ]),

            Section::make('レート制限')
                ->description('API使用量の制限を設定します。')
                ->schema([
                    TextInput::make('daily_limit_per_user')
                        ->label('1日あたりのユーザー制限')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->helperText('1ユーザーが1日に利用できる回数'),

                    TextInput::make('monthly_limit_total')
                        ->label('月間合計制限')
                        ->numeric()
                        ->minValue(100)
                        ->maxValue(10000)
                        ->helperText('全ユーザーの月間合計利用回数'),
                ]),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }
}
