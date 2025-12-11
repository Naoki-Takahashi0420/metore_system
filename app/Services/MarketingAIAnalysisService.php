<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\MedicalRecord;
use App\Models\Reservation;
use App\Models\CustomerSubscription;
use Carbon\Carbon;

class MarketingAIAnalysisService
{
    private ?string $apiKey = null;
    private string $model = 'claude-sonnet-4-5-20250929';
    private int $maxTokens = 2048;

    public function __construct()
    {
        $this->loadSettings();
    }

    /**
     * 設定を読み込み
     */
    private function loadSettings(): void
    {
        $settings = Cache::remember('claude_settings', 300, function () {
            if (!DB::getSchemaBuilder()->hasTable('settings')) {
                return [];
            }
            return DB::table('settings')
                ->where('key', 'like', 'claude.%')
                ->pluck('value', 'key')
                ->toArray();
        });

        $this->apiKey = $settings['claude.api_key'] ?? config('claude.api_key');
        $this->model = config('claude.model', 'claude-sonnet-4-5-20250929');
        $this->maxTokens = 2048;
    }

    /**
     * マーケティングデータを分析
     */
    public function analyzeMarketingData(
        string $startDate,
        string $endDate,
        ?int $storeId = null
    ): array {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error' => 'Claude APIキーが設定されていません。',
                'analysis' => null
            ];
        }

        // 分析用データを収集
        $analysisData = $this->collectAnalysisData($startDate, $endDate, $storeId);

        // プロンプトを構築
        $prompt = $this->buildAnalysisPrompt($analysisData, $startDate, $endDate);

        try {
            $response = Http::timeout(60)->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('Marketing AI Analysis Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'API通信エラーが発生しました。',
                    'analysis' => null
                ];
            }

            $data = $response->json();

            Log::info('Marketing AI Analysis Usage', [
                'user_id' => auth()->id(),
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            ]);

            return [
                'success' => true,
                'error' => null,
                'analysis' => $data['content'][0]['text'] ?? '',
                'usage' => $data['usage'] ?? []
            ];

        } catch (\Exception $e) {
            Log::error('Marketing AI Analysis Exception', [
                'message' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return [
                'success' => false,
                'error' => '分析中にエラーが発生しました: ' . $e->getMessage(),
                'analysis' => null
            ];
        }
    }

    /**
     * 分析用データを収集
     */
    private function collectAnalysisData(string $startDate, string $endDate, ?int $storeId): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // 基本クエリビルダー
        $reservationQuery = Reservation::whereBetween('reservation_date', [$start, $end]);
        $medicalRecordQuery = MedicalRecord::whereBetween('treatment_date', [$start, $end]);

        if ($storeId) {
            $reservationQuery->where('store_id', $storeId);
            $medicalRecordQuery->where('store_id', $storeId);
        }

        // 1. 曜日別データ
        $dayOfWeekData = $this->getDayOfWeekAnalysis($start, $end, $storeId);

        // 2. 時間帯別データ
        $timeSlotData = $this->getTimeSlotAnalysis($start, $end, $storeId);

        // 3. 媒体別データ
        $sourceData = $this->getSourceAnalysis($start, $end, $storeId);

        // 4. 対応者別データ
        $handlerData = $this->getHandlerAnalysis($start, $end, $storeId);

        // 5. 月別トレンド
        $monthlyTrend = $this->getMonthlyTrend($start, $end, $storeId);

        // 6. リピート率分析
        $repeatData = $this->getRepeatAnalysis($start, $end, $storeId);

        // 7. キャンセル分析
        $cancelData = $this->getCancelAnalysis($start, $end, $storeId);

        // 8. サブスク契約分析
        $subscriptionData = $this->getSubscriptionAnalysis($start, $end, $storeId);

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'days' => $start->diffInDays($end) + 1
            ],
            'day_of_week' => $dayOfWeekData,
            'time_slot' => $timeSlotData,
            'source' => $sourceData,
            'handler' => $handlerData,
            'monthly_trend' => $monthlyTrend,
            'repeat' => $repeatData,
            'cancel' => $cancelData,
            'subscription' => $subscriptionData,
        ];
    }

    /**
     * 曜日別分析
     */
    private function getDayOfWeekAnalysis(Carbon $start, Carbon $end, ?int $storeId): array
    {
        $dayNames = ['日', '月', '火', '水', '木', '金', '土'];
        $data = [];

        $query = Reservation::selectRaw('
            strftime("%w", reservation_date) as day_of_week,
            COUNT(*) as total,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled
        ')
        ->whereBetween('reservation_date', [$start, $end])
        ->groupBy('day_of_week');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $results = $query->get();

        foreach ($results as $row) {
            $dayIndex = (int)$row->day_of_week;
            $data[$dayNames[$dayIndex]] = [
                'total' => $row->total,
                'completed' => $row->completed,
                'cancelled' => $row->cancelled,
                'cancel_rate' => $row->total > 0 ? round($row->cancelled / $row->total * 100, 1) : 0
            ];
        }

        return $data;
    }

    /**
     * 時間帯別分析
     */
    private function getTimeSlotAnalysis(Carbon $start, Carbon $end, ?int $storeId): array
    {
        $data = [];

        $query = Reservation::selectRaw('
            CAST(strftime("%H", start_time) AS INTEGER) as hour,
            COUNT(*) as total,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed
        ')
        ->whereBetween('reservation_date', [$start, $end])
        ->whereNotNull('start_time')
        ->groupBy('hour')
        ->orderBy('hour');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $results = $query->get();

        foreach ($results as $row) {
            $hour = (int)$row->hour;
            $timeSlot = sprintf('%02d:00-%02d:00', $hour, $hour + 1);
            $data[$timeSlot] = [
                'total' => $row->total,
                'completed' => $row->completed
            ];
        }

        return $data;
    }

    /**
     * 媒体別分析
     */
    private function getSourceAnalysis(Carbon $start, Carbon $end, ?int $storeId): array
    {
        $query = MedicalRecord::selectRaw('
            reservation_source as source,
            COUNT(*) as total
        ')
        ->whereBetween('treatment_date', [$start, $end])
        ->whereNotNull('reservation_source')
        ->where('reservation_source', '!=', '')
        ->groupBy('reservation_source')
        ->orderByDesc('total');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $results = $query->get();

        $data = [];
        foreach ($results as $row) {
            $data[$row->source] = $row->total;
        }

        return $data;
    }

    /**
     * 対応者別分析
     */
    private function getHandlerAnalysis(Carbon $start, Carbon $end, ?int $storeId): array
    {
        // handled_by から取得
        $query = MedicalRecord::selectRaw('
            COALESCE(handled_by, "不明") as handler,
            COUNT(*) as total
        ')
        ->whereBetween('treatment_date', [$start, $end])
        ->whereNotNull('handled_by')
        ->where('handled_by', '!=', '')
        ->groupBy('handler')
        ->orderByDesc('total')
        ->limit(15);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $results = $query->get();

        $data = [];
        foreach ($results as $row) {
            $data[$row->handler] = $row->total;
        }

        return $data;
    }

    /**
     * 月別トレンド
     */
    private function getMonthlyTrend(Carbon $start, Carbon $end, ?int $storeId): array
    {
        $query = Reservation::selectRaw('
            strftime("%Y-%m", reservation_date) as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled
        ')
        ->whereBetween('reservation_date', [$start, $end])
        ->groupBy('month')
        ->orderBy('month');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $results = $query->get();

        $data = [];
        foreach ($results as $row) {
            $data[$row->month] = [
                'total' => $row->total,
                'completed' => $row->completed,
                'cancelled' => $row->cancelled
            ];
        }

        return $data;
    }

    /**
     * リピート率分析
     */
    private function getRepeatAnalysis(Carbon $start, Carbon $end, ?int $storeId): array
    {
        // 期間内に初来店した顧客
        $newCustomerQuery = Reservation::select('customer_id')
            ->whereBetween('reservation_date', [$start, $end])
            ->whereNotNull('customer_id')
            ->where('status', 'completed');

        if ($storeId) {
            $newCustomerQuery->where('store_id', $storeId);
        }

        $customerIds = $newCustomerQuery->distinct()->pluck('customer_id');

        // 2回以上来店した顧客
        $repeatCustomers = Reservation::selectRaw('customer_id, COUNT(*) as visit_count')
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) >= 2')
            ->count();

        $totalNewCustomers = $customerIds->count();

        return [
            'total_customers' => $totalNewCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $totalNewCustomers > 0 ? round($repeatCustomers / $totalNewCustomers * 100, 1) : 0
        ];
    }

    /**
     * キャンセル分析
     */
    private function getCancelAnalysis(Carbon $start, Carbon $end, ?int $storeId): array
    {
        $query = Reservation::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = "no_show" THEN 1 ELSE 0 END) as no_show
        ')
        ->whereBetween('reservation_date', [$start, $end]);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $result = $query->first();

        return [
            'total' => $result->total ?? 0,
            'cancelled' => $result->cancelled ?? 0,
            'no_show' => $result->no_show ?? 0,
            'cancel_rate' => ($result->total ?? 0) > 0
                ? round(($result->cancelled + $result->no_show) / $result->total * 100, 1)
                : 0
        ];
    }

    /**
     * サブスク契約分析
     */
    private function getSubscriptionAnalysis(Carbon $start, Carbon $end, ?int $storeId): array
    {
        $query = CustomerSubscription::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled
        ')
        ->whereBetween('created_at', [$start, $end]);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $result = $query->first();

        return [
            'new_contracts' => $result->total ?? 0,
            'active' => $result->active ?? 0,
            'cancelled' => $result->cancelled ?? 0
        ];
    }

    /**
     * 分析プロンプトを構築
     */
    private function buildAnalysisPrompt(array $data, string $startDate, string $endDate): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
あなたは目のトレーニングサロンの経営コンサルタントです。
以下のマーケティングデータを分析し、経営改善に役立つインサイトを日本語で提供してください。

【分析期間】
{$startDate} 〜 {$endDate}

【データ】
```json
{$json}
```

【分析してほしいこと】

1. **曜日別傾向**
   - 予約が多い/少ない曜日
   - キャンセル率が高い曜日
   - おすすめのスタッフ配置

2. **時間帯分析**
   - 人気の時間帯
   - 空きが多い時間帯
   - 予約を増やせる可能性のある時間帯

3. **媒体（集客チャネル）分析**
   - 効果的な媒体
   - 改善が必要な媒体
   - 広告予算の配分提案

4. **対応者（スタッフ）分析**
   - 対応件数の多いスタッフ
   - 注目すべきパフォーマンス

5. **トレンド分析**
   - 月別の傾向（増加/減少）
   - 季節性があれば指摘

6. **課題と改善提案**
   - 最も重要な3つの課題
   - 具体的なアクションプラン

【出力形式】
- 見出しは【】で囲む
- 箇条書きで簡潔に
- 数値は具体的に引用
- 経営者が即座に行動できる提案を含める
- ポジティブな点も必ず言及する
PROMPT;
    }

    /**
     * APIが利用可能かチェック
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }
}
