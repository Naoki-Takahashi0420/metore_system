<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Store;
use App\Models\LineMessageTemplate;
use App\Services\SimpleLineService;

class SendStoreCampaign extends Command
{
    protected $signature = 'line:send-campaign {store_id} {template_id?}';
    protected $description = '店舗別にプロモーションメッセージを一斉送信';
    
    public function handle()
    {
        $storeId = $this->argument('store_id');
        $templateId = $this->argument('template_id');
        
        $store = Store::find($storeId);
        if (!$store) {
            $this->error('店舗が見つかりません');
            return Command::FAILURE;
        }
        
        if (!$store->line_enabled || !$store->line_send_promotion) {
            $this->error('この店舗はLINEプロモーション送信が無効です');
            return Command::FAILURE;
        }
        
        // メッセージ内容を取得
        $message = '';
        if ($templateId) {
            $template = LineMessageTemplate::find($templateId);
            if (!$template) {
                $this->error('テンプレートが見つかりません');
                return Command::FAILURE;
            }
            $message = $template->content;
        } else {
            // 対話式でメッセージを入力
            $message = $this->ask('送信するメッセージを入力してください');
        }
        
        if (empty($message)) {
            $this->error('メッセージが空です');
            return Command::FAILURE;
        }
        
        $this->info("店舗: {$store->name}");
        $this->info("メッセージ内容:");
        $this->line($message);
        
        if (!$this->confirm('このメッセージを送信しますか？')) {
            $this->info('キャンセルしました');
            return Command::SUCCESS;
        }
        
        $lineService = new SimpleLineService($store);
        $result = $lineService->sendPromotion($store, $message);
        
        $this->info("送信結果:");
        $this->info("  成功: {$result['success']}件");
        $this->info("  失敗: {$result['failed']}件");
        $this->info("  合計: {$result['total']}件");
        
        return Command::SUCCESS;
    }
}