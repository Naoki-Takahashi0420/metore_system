<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptions extends Command
{
    protected $signature = 'subscriptions:process';
    protected $description = 'サブスクリプションの自動更新と期限切れ処理';

    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->subscriptionService = $subscriptionService;
    }

    public function handle()
    {
        $this->info('サブスクリプション処理を開始します...');
        
        // 自動更新処理
        $renewed = $this->subscriptionService->processAutoRenewals();
        $this->info("自動更新: {$renewed}件");
        
        // 期限切れ処理
        $expired = $this->subscriptionService->deactivateExpired();
        $this->info("期限切れ: {$expired}件");
        
        $this->info('サブスクリプション処理が完了しました。');
        
        return Command::SUCCESS;
    }
}