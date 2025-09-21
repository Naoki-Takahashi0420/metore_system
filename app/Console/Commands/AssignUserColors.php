<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class AssignUserColors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:assign-colors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign theme colors to existing users who do not have one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('既存ユーザーへの色割り当てを開始...');

        // theme_colorがnullまたは空のユーザーを取得
        $usersWithoutColor = User::whereNull('theme_color')
            ->orWhere('theme_color', '')
            ->get();

        if ($usersWithoutColor->isEmpty()) {
            $this->info('色が未設定のユーザーはいません。');
            return;
        }

        $updatedCount = 0;

        foreach ($usersWithoutColor as $user) {
            // Userモデルの静的メソッドを使用して色を自動割り当て
            $user->theme_color = User::getNextAvailableColor();
            $user->save();

            $this->line("ユーザー「{$user->name}」に色「{$user->theme_color}」を割り当てました。");
            $updatedCount++;
        }

        $this->info("完了: {$updatedCount}人のユーザーに色を割り当てました。");
    }
}
