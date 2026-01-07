<?php

namespace App\Livewire\Marketing;

use Livewire\Component;
use App\Services\NewCustomerTrackingService;
use App\Services\MarketingAIAnalysisService;
use Illuminate\Support\Facades\Response;

class NewCustomerTrackingTable extends Component
{
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $storeId = null;
    public string $activeTab = 'charts'; // charts, tracking, source, handler, subscription, monthly

    public array $trackingData = [];
    public array $sourceData = [];
    public array $handlerData = [];
    public array $subscriptionData = [];
    public array $subscriptionHandlerDetails = [];
    public array $ticketHandlerDetails = [];
    public array $nextReservationHandlerDetails = [];
    public array $monthlyData = [];

    // チャート用データ
    public array $funnelData = [];
    public array $resultPieData = [];
    public array $sourceRankingData = [];
    public array $handlerRankingData = [];
    public array $monthlyTrendData = [];
    public array $heatmapData = [];

    // AI分析用
    public bool $isAnalyzing = false;
    public ?string $aiAnalysisResult = null;
    public ?string $aiAnalysisError = null;
    public bool $aiAvailable = false;

    public int $visitNumber = 1; // 1回目、2回目、3回目

    protected $listeners = ['filtersUpdated' => 'handleFiltersUpdated'];

    public function mount(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $store_id = null
    ): void {
        // デフォルトは今月（パフォーマンス考慮）
        $this->startDate = $startDate ?? now()->startOfMonth()->format('Y-m-d');
        $this->endDate = $endDate ?? now()->format('Y-m-d');
        $this->storeId = $store_id;

        // AI分析が利用可能かチェック
        $aiService = new MarketingAIAnalysisService();
        $this->aiAvailable = $aiService->isAvailable();

        $this->loadData();
    }

    public function handleFiltersUpdated(array $filters): void
    {
        $this->startDate = $filters['startDateA'] ?? $this->startDate;
        $this->endDate = $filters['endDateA'] ?? $this->endDate;
        $this->storeId = $filters['store_id'] ?? $this->storeId;

        $this->loadData();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setVisitNumber(int $number): void
    {
        $this->visitNumber = $number;
        $this->loadData();
    }

    public function loadData(): void
    {
        $service = new NewCustomerTrackingService();

        // 新規結果（個別顧客追跡）
        $rawTracking = $service->getNewCustomerTracking(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );

        // サービスからのデータをそのまま使用
        $this->trackingData = $rawTracking->toArray();

        // 媒体別集計
        $rawSource = $service->getSourceResultSummary(
            $this->startDate,
            $this->endDate,
            $this->storeId,
            $this->visitNumber
        );
        $this->sourceData = collect($rawSource['data'] ?? [])->map(function ($row) {
            return [
                'source' => $row['source'],
                'キャンセル' => $row['キャンセル'] ?? 0,
                'サブスク' => $row['サブスク'] ?? 0,
                '回数券' => $row['回数券'] ?? 0,
                '次回予約' => $row['次回予約'] ?? 0,
                '飛び' => $row['飛び'] ?? 0,
                '予約なし' => $row['予約なし'] ?? 0,
                'total' => $row['total'] ?? 0,
                'rate' => $row['conversion_rate'] ?? 0,
            ];
        })->toArray();

        // 対応者別集計
        $rawHandler = $service->getHandlerResultSummary(
            $this->startDate,
            $this->endDate,
            $this->storeId,
            $this->visitNumber
        );
        $this->handlerData = collect($rawHandler['data'] ?? [])->map(function ($row) {
            return [
                'handler' => $row['handler'],
                'キャンセル' => $row['キャンセル'] ?? 0,
                'サブスク' => $row['サブスク'] ?? 0,
                '回数券' => $row['回数券'] ?? 0,
                '次回予約' => $row['次回予約'] ?? 0,
                '飛び' => $row['飛び'] ?? 0,
                '予約なし' => $row['予約なし'] ?? 0,
                'total' => $row['total'] ?? 0,
                'rate' => $row['conversion_rate'] ?? 0,
            ];
        })->toArray();

        // サブスク内訳
        $rawSubscription = $service->getSubscriptionBreakdown(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );
        $this->subscriptionData = $this->transformSubscriptionData($rawSubscription);

        // サブスク契約の対応者別詳細（インセンティブ用）
        $this->subscriptionHandlerDetails = $service->getSubscriptionHandlerDetails(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );

        // 回数券購入の対応者別詳細（インセンティブ用）
        $this->ticketHandlerDetails = $service->getTicketHandlerDetails(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );

        // 次回予約獲得の対応者別詳細（インセンティブ用）
        $this->nextReservationHandlerDetails = $service->getNextReservationHandlerDetails(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );

        // 月別集計
        $rawMonthly = $service->getMonthlyHandlerStats(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );
        $this->monthlyData = $this->transformMonthlyData($rawMonthly);

        // チャート用データ
        $this->funnelData = $service->getFunnelData(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );

        $this->resultPieData = $service->getResultPieData(
            $this->startDate,
            $this->endDate,
            $this->storeId,
            $this->visitNumber
        );

        $this->sourceRankingData = $service->getConversionRankingData(
            $this->startDate,
            $this->endDate,
            $this->storeId,
            'source',
            $this->visitNumber
        );

        $this->handlerRankingData = $service->getConversionRankingData(
            $this->startDate,
            $this->endDate,
            $this->storeId,
            'handler',
            $this->visitNumber
        );

        $this->monthlyTrendData = $service->getMonthlyTrendData(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );

        $this->heatmapData = $service->getHeatmapData(
            $this->startDate,
            $this->endDate,
            $this->storeId,
            $this->visitNumber
        );
    }

    private function transformSubscriptionData(array $rawData): array
    {
        $result = [];

        // 媒体別データ
        foreach ($rawData['data'] ?? [] as $row) {
            $plans = [];
            foreach ($row as $key => $value) {
                if (!in_array($key, ['source', 'total'])) {
                    $plans[$key] = $value;
                }
            }
            $result[] = [
                'group_type' => 'source',
                'name' => $row['source'],
                'plans' => $plans,
                'total' => $row['total'] ?? 0,
            ];
        }

        return $result;
    }

    private function transformMonthlyData(array $rawData): array
    {
        $result = [];

        $months = $rawData['months'] ?? collect();
        $handlers = $rawData['handlers'] ?? collect();
        $counts = $rawData['new_customer_counts'] ?? [];

        foreach ($months as $month) {
            foreach ($handlers as $handler) {
                $result[] = [
                    'month' => $month,
                    'handler' => $handler,
                    'count' => $counts[$month][$handler] ?? 0,
                ];
            }
        }

        return $result;
    }

    public function exportCsv()
    {
        $service = new NewCustomerTrackingService();
        $csv = $service->exportToCsv(
            $this->startDate,
            $this->endDate,
            $this->storeId
        );

        $filename = '新規顧客追跡_' . date('Y-m-d_His') . '.csv';

        return Response::streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function runAiAnalysis(): void
    {
        $this->isAnalyzing = true;
        $this->aiAnalysisResult = null;
        $this->aiAnalysisError = null;

        try {
            $aiService = new MarketingAIAnalysisService();

            // APIキーの確認を実行時に行う
            if (!$aiService->isAvailable()) {
                $this->aiAnalysisError = 'AI分析機能は現在利用できません。管理画面の「Claude設定」でAPIキーを設定してください。';
                $this->isAnalyzing = false;
                return;
            }

            $result = $aiService->analyzeMarketingData(
                $this->startDate,
                $this->endDate,
                $this->storeId
            );

            if ($result['success']) {
                $this->aiAnalysisResult = $result['analysis'];
            } else {
                $this->aiAnalysisError = $result['error'];
            }
        } catch (\Exception $e) {
            $this->aiAnalysisError = '分析中にエラーが発生しました: ' . $e->getMessage();
        } finally {
            $this->isAnalyzing = false;
        }
    }

    public function render()
    {
        return view('livewire.marketing.new-customer-tracking-table');
    }
}
