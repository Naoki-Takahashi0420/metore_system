<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\CustomerSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarketingAnalyticsService
{
    /**
     * 月次KPIを取得
     */
    public function getMonthlyKpis(?string $period = 'month', ?int $storeId = null, ?string $customStartDate = null, ?string $customEndDate = null): array
    {
        if ($period === 'custom' && $customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getPeriodDates($period);
        }

        $query = Reservation::query()
            ->whereBetween('reservation_date', [$startDate, $endDate])
            ->whereIn('status', ['completed', 'in_progress']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        // 総予約数
        $totalReservations = $query->count();

        // 新規顧客数（初回予約）
        $newCustomers = Customer::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->count();

        // 売上高
        $totalRevenue = Sale::query()
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'completed')
            ->sum('total_amount');

        // リピート率（2回目以上の来店顧客数 / 総顧客数）
        $totalCustomers = $query->distinct('customer_id')->count('customer_id');
        $repeatCustomers = $query
            ->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $repeatRate = $totalCustomers > 0
            ? round(($repeatCustomers / $totalCustomers) * 100, 1)
            : 0;

        // 前期比較
        [$prevStartDate, $prevEndDate] = $this->getPreviousPeriodDates($period);

        $prevRevenue = Sale::query()
            ->whereBetween('sale_date', [$prevStartDate, $prevEndDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'completed')
            ->sum('total_amount');

        $revenueGrowth = $prevRevenue > 0
            ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1)
            : 0;

        // サブスク契約数
        $activeSubscriptions = CustomerSubscription::query()
            ->where('status', 'active')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->count();

        // 平均客単価（売上件数ベース）
        $salesCount = Sale::query()
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'completed')
            ->count();

        $avgTicket = $salesCount > 0
            ? round($totalRevenue / $salesCount, 0)
            : 0;

        return [
            'total_reservations' => $totalReservations,
            'new_customers' => $newCustomers,
            'total_revenue' => $totalRevenue,
            'total_transactions' => $salesCount,
            'repeat_rate' => $repeatRate,
            'revenue_growth' => $revenueGrowth,
            'active_subscriptions' => $activeSubscriptions,
            'avg_ticket' => $avgTicket,
            'period_label' => $period === 'custom'
                ? Carbon::parse($customStartDate)->format('Y/m/d') . ' 〜 ' . Carbon::parse($customEndDate)->format('Y/m/d')
                : $this->getPeriodLabel($period),
        ];
    }

    /**
     * スタッフ別パフォーマンスを取得
     */
    public function getStaffPerformance(?string $period = 'month', ?int $storeId = null, ?string $customStartDate = null, ?string $customEndDate = null): array
    {
        if ($period === 'custom' && $customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getPeriodDates($period);
        }

        // カルテのhandled_byから実際のスタッフ名を取得（店舗名を除外）
        $staffNamesFromRecords = \App\Models\MedicalRecord::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereNotNull('handled_by')
            ->where('handled_by', '!=', '')
            // 店舗名パターンを除外（「〜店」で終わる、または「〜店舗」など）
            ->where('handled_by', 'NOT LIKE', '%店')
            ->where('handled_by', 'NOT LIKE', '%店舗')
            ->where('handled_by', 'NOT LIKE', '%本部%')
            ->select('handled_by')
            ->distinct()
            ->pluck('handled_by');

        $staffData = collect($staffNamesFromRecords)->map(function($name) {
            // スタッフ名の抽出ロジック
            // パターン1: 「吉祥寺店 水島」→「水島」（スペース区切り）
            // パターン2: 「吉祥寺店志藤」→「志藤」（店名の後にスタッフ名が続く）

            // スペース区切りがある場合
            if (str_contains($name, ' ')) {
                $parts = explode(' ', $name);
                $cleanName = end($parts);
            } else {
                // スペースがない場合、「〜店」の後の文字列を抽出
                if (preg_match('/店(.+)$/', $name, $matches)) {
                    $cleanName = $matches[1]; // 「吉祥寺店志藤」→「志藤」
                } else {
                    $cleanName = $name;
                }
            }

            return (object)[
                'id' => null,
                'name' => $cleanName,
                'original_name' => $name, // 検索用に元の名前も保持
            ];
        })->filter(function($staff) {
            // さらに店舗名っぽいものを除外
            return !str_ends_with($staff->name, '店')
                && !str_ends_with($staff->name, '店舗')
                && !str_contains($staff->name, '本部')
                && !empty($staff->name); // 空文字も除外
        })->values();

        $performanceData = [];

        foreach ($staffData as $staff) {
            // ============================================================
            // 基本指標の集計
            // ============================================================

            // カルテ件数（対応回数）
            // このスタッフが期間内に作成したカルテの総数
            $recordCount = \App\Models\MedicalRecord::query()
                ->where(function($q) use ($staff) {
                    $q->where('handled_by', $staff->original_name)
                      ->orWhere('handled_by', $staff->name)
                      ->orWhere('handled_by', 'LIKE', '%' . $staff->name . '%');
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->count();

            // 売上金額（handled_by基準）
            // このスタッフが対応した売上の合計金額（完了済みのみ）
            $revenue = Sale::query()
                ->where(function($q) use ($staff) {
                    $q->where('handled_by', $staff->original_name)
                      ->orWhere('handled_by', $staff->name)
                      ->orWhere('handled_by', 'LIKE', '%' . $staff->name . '%');
                })
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->where('status', 'completed')
                ->sum('total_amount');

            // 売上件数
            // このスタッフが対応した売上の件数
            $salesCount = Sale::query()
                ->where(function($q) use ($staff) {
                    $q->where('handled_by', $staff->original_name)
                      ->orWhere('handled_by', $staff->name)
                      ->orWhere('handled_by', 'LIKE', '%' . $staff->name . '%');
                })
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->where('status', 'completed')
                ->count();

            // ユニーク顧客数（カルテから）
            // このスタッフが期間内に対応した顧客の人数（ユニーク）
            $newCustomers = \App\Models\MedicalRecord::query()
                ->where(function($q) use ($staff) {
                    $q->where('handled_by', $staff->original_name)
                      ->orWhere('handled_by', $staff->name)
                      ->orWhere('handled_by', 'LIKE', '%' . $staff->name . '%');
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->distinct('customer_id')
                ->count('customer_id');

            // ============================================================
            // サブスク転換率の計算
            // ============================================================
            // 【定義】
            // このスタッフが対応した顧客のうち、サブスク契約をした顧客の割合
            //
            // 【計算例】
            // - 玲奈さんが10月に10人の顧客にカルテ作成
            // - そのうち4人がサブスク契約
            // - サブスク転換率 = 4人 ÷ 10人 = 40%
            //
            // 【意味】
            // 商品・サービスへの満足度、営業力の指標
            //
            // 【注意】
            // サブスク契約の created_at は期間外でもOK（長期的な転換を追跡）
            // ============================================================
            $customerIdsFromRecords = \App\Models\MedicalRecord::query()
                ->where(function($q) use ($staff) {
                    $q->where('handled_by', $staff->original_name)
                      ->orWhere('handled_by', $staff->name)
                      ->orWhere('handled_by', 'LIKE', '%' . $staff->name . '%');
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->pluck('customer_id')
                ->unique();

            // サブスク契約数（期間外の契約も含む、長期的な転換を見る）
            $subscriptionConversions = \App\Models\CustomerSubscription::query()
                ->whereIn('customer_id', $customerIdsFromRecords)
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->where('status', 'active')
                ->distinct('customer_id')
                ->count('customer_id');

            $conversionRate = $customerIdsFromRecords->count() > 0
                ? round(($subscriptionConversions / $customerIdsFromRecords->count()) * 100, 1)
                : 0;

            // ============================================================
            // 顧客継続率（リテンション率）の計算
            // ============================================================
            // 【定義】
            // このスタッフが期間内に初回カルテ対応した顧客のうち、
            // 翌月末までに2回目の予約をした顧客の割合
            //
            // 【計算例】
            // - 10月に玲奈さんが新規顧客5人にカルテ作成（玲奈にとって初回対応）
            // - そのうち3人が11月末までに2回目の予約（どのスタッフでもOK）
            // - 顧客継続率 = 3人 ÷ 5人 = 60%
            //
            // 【意味】
            // スタッフが対応した顧客のうち、リピーターになってくれた割合
            // = 「一回限りで終わったか、継続してくれたか」の指標
            // ============================================================

            // ステップ1: このスタッフの全カルテを取得（全期間）
            $allRecordsByStaff = \App\Models\MedicalRecord::query()
                ->where(function($q) use ($staff) {
                    $q->where('handled_by', $staff->original_name)
                      ->orWhere('handled_by', $staff->name)
                      ->orWhere('handled_by', 'LIKE', '%' . $staff->name . '%');
                })
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->orderBy('created_at')
                ->get();

            // ステップ2: 期間内のカルテから、「このスタッフにとって初回対応」の顧客を抽出
            // 注: システム全体で初回ではなく、このスタッフにとって初回であることが重要
            // 例: 顧客Aさんが9月に涼介さん、10月に玲奈さんにカルテ作成
            //     → 玲奈さんにとっては初回対応としてカウント
            $firstTimeCustomerIds = collect();
            foreach ($allRecordsByStaff as $record) {
                if ($record->created_at >= $startDate && $record->created_at <= $endDate) {
                    // この顧客の、このスタッフによる過去のカルテがあるか確認
                    $hasPreviousRecord = $allRecordsByStaff
                        ->where('customer_id', $record->customer_id)
                        ->where('created_at', '<', $record->created_at)
                        ->isNotEmpty();

                    if (!$hasPreviousRecord) {
                        $firstTimeCustomerIds->push($record->customer_id);
                    }
                }
            }
            $firstTimeCustomerIds = $firstTimeCustomerIds->unique();

            // ステップ3: その顧客が期間終了後（翌月末まで）に2回目の予約をしたかチェック
            // 注: reservation_date（実際の予約日）で判定（created_atではない）
            // 注: どのスタッフへの予約でもOK（顧客起点で判定）
            $nextMonthEnd = $endDate->copy()->addMonth()->endOfMonth();
            $repeatCustomers = \App\Models\Reservation::query()
                ->whereIn('customer_id', $firstTimeCustomerIds)
                ->where('reservation_date', '>', $endDate)
                ->where('reservation_date', '<=', $nextMonthEnd)
                ->distinct('customer_id')
                ->count('customer_id');

            // ステップ4: 継続率を計算
            $customerRetentionRate = $firstTimeCustomerIds->count() > 0
                ? round(($repeatCustomers / $firstTimeCustomerIds->count()) * 100, 1)
                : 0;

            // ============================================================
            // リピート獲得率
            // ============================================================
            // 【定義】
            // 顧客継続率と同じ意味（初回対応顧客のリピート率）
            // ============================================================
            $repeatAcquisitionRate = $customerRetentionRate;

            // ============================================================
            // 満足度スコア
            // ============================================================
            // 【定義】
            // サブスク転換率と顧客継続率の平均
            //
            // 【計算例】
            // - サブスク転換率: 40%（商品への満足度）
            // - 顧客継続率: 60%（サービスへの満足度）
            // - 満足度スコア = (40 + 60) ÷ 2 = 50%
            //
            // 【意味】
            // - 転換率が高い = 商品・サービスに満足して契約
            // - 継続率が高い = 接客・対応に満足してリピート
            // - 両方の平均 = 総合的な顧客満足度の指標
            // ============================================================
            $satisfactionScore = round(($conversionRate + $customerRetentionRate) / 2, 1);

            $performanceData[] = [
                'id' => $staff->id,
                'name' => $staff->name,
                'record_count' => $recordCount,            // カルテ対応回数
                'revenue' => $revenue,                     // 売上金額
                'new_customers' => $newCustomers,          // ユニーク顧客数（カルテから）
                'sales_count' => $salesCount,              // 売上件数
                'conversion_rate' => $conversionRate,      // サブスク転換率
                'repeat_acquisition_rate' => $repeatAcquisitionRate,  // リピート率（計算不可）
                'customer_retention_rate' => $customerRetentionRate,  // 継続率（計算不可）
                'satisfaction_score' => $satisfactionScore,           // 満足度スコア
                'avg_ticket' => $salesCount > 0
                    ? round($revenue / $salesCount, 0)
                    : 0,
            ];
        }

        // 売上高でソート
        usort($performanceData, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        return $performanceData;
    }

    /**
     * 顧客分析データを取得
     */
    public function getCustomerAnalysis(?string $period = 'month', ?int $storeId = null, ?string $customStartDate = null, ?string $customEndDate = null): array
    {
        if ($period === 'custom' && $customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getPeriodDates($period);
        }

        // 新規顧客数の推移（日別）
        $newCustomersTrend = Customer::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // 既存顧客の来店数
        $existingCustomerVisits = Reservation::query()
            ->whereBetween('reservation_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereIn('status', ['completed'])
            ->whereHas('customer', function($q) use ($startDate) {
                $q->where('created_at', '<', $startDate);
            })
            ->count();

        // 離脱リスク顧客（60日以上来店なし）
        $churnRiskCustomers = Customer::query()
            ->where('last_visit_at', '<', Carbon::now()->subDays(60))
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->count();

        // 顧客セグメント別分布
        $segments = [
            'new' => Customer::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->count(),

            'active' => Customer::query()
                ->where('last_visit_at', '>=', Carbon::now()->subDays(30))
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->count(),

            'dormant' => Customer::query()
                ->whereBetween('last_visit_at', [
                    Carbon::now()->subDays(60),
                    Carbon::now()->subDays(30)
                ])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->count(),

            'lost' => Customer::query()
                ->where('last_visit_at', '<', Carbon::now()->subDays(60))
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->count(),
        ];

        // キャンセル・ノーショー率
        $totalReservations = Reservation::query()
            ->whereBetween('reservation_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->count();

        $cancelledReservations = Reservation::query()
            ->whereBetween('reservation_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'cancelled')
            ->count();

        $noShowReservations = Reservation::query()
            ->whereBetween('reservation_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'no_show')
            ->count();

        $cancelRate = $totalReservations > 0
            ? round(($cancelledReservations / $totalReservations) * 100, 1)
            : 0;

        $noShowRate = $totalReservations > 0
            ? round(($noShowReservations / $totalReservations) * 100, 1)
            : 0;

        // キャンセル顧客一覧（最新10件）
        $cancelledCustomers = Reservation::query()
            ->with('customer')
            ->whereBetween('reservation_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'cancelled')
            ->orderBy('reservation_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'customer_id' => $r->customer_id,
                'customer_name' => $r->customer?->full_name ?? '不明',
                'reservation_date' => $r->reservation_date,
                'cancelled_at' => $r->updated_at->format('Y-m-d H:i'),
            ]);

        // ノーショー顧客一覧（最新10件）
        $noShowCustomers = Reservation::query()
            ->with('customer')
            ->whereBetween('reservation_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'no_show')
            ->orderBy('reservation_date', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'customer_id' => $r->customer_id,
                'customer_name' => $r->customer?->full_name ?? '不明',
                'reservation_date' => $r->reservation_date,
            ]);

        // 流入経路別分析（CVR付き）
        // record_dateがNULLの場合はcreated_atを使用（吉祥寺店など一部データでrecord_dateが未設定）
        $medicalRecordsBySource = \DB::table('medical_records')
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('record_date', [$startDate, $endDate])
                  ->orWhere(function($q2) use ($startDate, $endDate) {
                      $q2->whereNull('record_date')
                         ->whereBetween('created_at', [$startDate, $endDate]);
                  });
            })
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereNotNull('reservation_source')
            ->where('reservation_source', '!=', '')
            ->get();

        $acquisitionSources = [];
        foreach ($medicalRecordsBySource->groupBy('reservation_source') as $source => $records) {
            $recordCount = $records->count();
            $customerIds = $records->pluck('customer_id')->unique();

            // サブスク契約数
            $subscriptionCount = \App\Models\CustomerSubscription::whereIn('customer_id', $customerIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->count();

            // 回数券契約数
            $ticketCount = \App\Models\CustomerTicket::whereIn('customer_id', $customerIds)
                ->whereBetween('purchased_at', [$startDate, $endDate])
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->count();

            $totalContracts = $subscriptionCount + $ticketCount;
            $conversionRate = $recordCount > 0
                ? round(($totalContracts / $recordCount) * 100, 1)
                : 0;

            $acquisitionSources[] = [
                'source' => $source,
                'record_count' => $recordCount,
                'subscription_count' => $subscriptionCount,
                'ticket_count' => $ticketCount,
                'total_contracts' => $totalContracts,
                'conversion_rate' => $conversionRate,
            ];
        }

        // 転換率の高い順にソート
        usort($acquisitionSources, function ($a, $b) {
            return $b['conversion_rate'] <=> $a['conversion_rate'];
        });

        return [
            'new_customers_trend' => $newCustomersTrend,
            'existing_customer_visits' => $existingCustomerVisits,
            'churn_risk_customers' => $churnRiskCustomers,
            'segments' => $segments,
            'cancel_rate' => $cancelRate,
            'no_show_rate' => $noShowRate,
            'cancelled_customers' => $cancelledCustomers,
            'no_show_customers' => $noShowCustomers,
            'acquisition_sources' => $acquisitionSources,
        ];
    }

    /**
     * コンバージョンファネルデータを取得
     */
    /**
     * コンバージョンファネル分析
     *
     * 【定義】期間内に登録した新規顧客の行動を追跡
     *
     * ステップ1: 新規顧客登録（期間内に created_at）
     * ステップ2: 初回来店（ステップ1の顧客が completed 予約を持つ）
     * ステップ3: 2回目予約（ステップ2の顧客が2回目の予約を持つ）
     * ステップ4: サブスク契約（ステップ1の顧客がアクティブなサブスク契約を持つ）
     *
     * 【重要】
     * - 各ステップは「新規顧客」を起点とする
     * - 既存顧客の予約は含まない
     * - 予約の完了は期間外でもカウント（顧客の長期的な行動を追跡）
     */
    public function getConversionFunnel(?string $period = 'month', ?int $storeId = null, ?string $customStartDate = null, ?string $customEndDate = null): array
    {
        if ($period === 'custom' && $customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getPeriodDates($period);
        }

        // ファネル各段階の数値
        $funnel = [];

        // ============================================================
        // ステップ1: 新規顧客登録
        // ============================================================
        // 期間内に登録された顧客
        $newCustomerIds = Customer::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->pluck('id');

        $newCustomersCount = $newCustomerIds->count();

        $funnel[] = [
            'stage' => '新規顧客登録',
            'count' => $newCustomersCount,
            'rate' => 100,
        ];

        // ============================================================
        // ステップ2: 初回来店（completed予約）
        // ============================================================
        // 新規顧客が completed 予約を持つ（期間外も含む、顧客の長期的な行動を追跡）
        $firstReservationsCustomerIds = Reservation::query()
            ->whereIn('customer_id', $newCustomerIds)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'completed')
            ->distinct('customer_id')
            ->pluck('customer_id');

        $firstReservationsCount = $firstReservationsCustomerIds->count();

        $funnel[] = [
            'stage' => '初回来店',
            'count' => $firstReservationsCount,
            'rate' => $newCustomersCount > 0
                ? round(($firstReservationsCount / $newCustomersCount) * 100, 1)
                : 0,
        ];

        // ============================================================
        // ステップ3: 2回目予約
        // ============================================================
        // ステップ2の顧客（初回来店済み）のうち、2回目の予約を持つ顧客
        // 各顧客について、2回以上の予約があるかチェック
        $secondReservationsCount = 0;
        if ($firstReservationsCustomerIds->isNotEmpty()) {
            $secondReservationsCount = DB::table('reservations')
                ->select('customer_id')
                ->whereIn('customer_id', $firstReservationsCustomerIds)
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->whereIn('status', ['completed', 'confirmed'])
                ->groupBy('customer_id')
                ->havingRaw('COUNT(*) >= 2')
                ->get()
                ->count();
        }

        $funnel[] = [
            'stage' => '2回目予約',
            'count' => $secondReservationsCount,
            'rate' => $firstReservationsCount > 0
                ? round(($secondReservationsCount / $firstReservationsCount) * 100, 1)
                : 0,
        ];

        // ============================================================
        // ステップ4: サブスク契約
        // ============================================================
        // 新規顧客がアクティブなサブスク契約を持つ
        $subscriptionsCount = CustomerSubscription::query()
            ->whereIn('customer_id', $newCustomerIds)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'active')
            ->distinct('customer_id')
            ->count('customer_id');

        $funnel[] = [
            'stage' => 'サブスク契約',
            'count' => $subscriptionsCount,
            'rate' => $newCustomersCount > 0
                ? round(($subscriptionsCount / $newCustomersCount) * 100, 1)
                : 0,
        ];

        return $funnel;
    }

    /**
     * 期間の開始日と終了日を取得
     */
    private function getPeriodDates(string $period): array
    {
        switch ($period) {
            case 'month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfDay();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'quarter':
                $startDate = Carbon::now()->startOfQuarter();
                $endDate = Carbon::now()->endOfDay();
                break;
            case 'year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfDay();
                break;
            default:
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    /**
     * 前期間の開始日と終了日を取得
     */
    private function getPreviousPeriodDates(string $period): array
    {
        switch ($period) {
            case 'month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonths(2)->startOfMonth();
                $endDate = Carbon::now()->subMonths(2)->endOfMonth();
                break;
            case 'quarter':
                $startDate = Carbon::now()->subQuarter()->startOfQuarter();
                $endDate = Carbon::now()->subQuarter()->endOfQuarter();
                break;
            case 'year':
                $startDate = Carbon::now()->subYear()->startOfYear();
                $endDate = Carbon::now()->subYear()->endOfYear();
                break;
            default:
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
        }

        return [$startDate, $endDate];
    }

    /**
     * 期間のラベルを取得
     */
    private function getPeriodLabel(string $period): string
    {
        switch ($period) {
            case 'month':
                return Carbon::now()->format('Y年n月');
            case 'last_month':
                return Carbon::now()->subMonth()->format('Y年n月');
            case 'quarter':
                $quarter = Carbon::now()->quarter;
                return Carbon::now()->format('Y年') . "第{$quarter}四半期";
            case 'year':
                return Carbon::now()->format('Y年');
            default:
                return '';
        }
    }

    /**
     * 完全なファネル分析（新規顧客→初回予約→カルテ→契約）
     * 店舗ごと・スタッフごとの詳細データをテーブル形式で取得
     *
     * @param string|null $period 期間
     * @param int|null $storeId 店舗ID
     * @param string|null $customStartDate カスタム開始日
     * @param string|null $customEndDate カスタム終了日
     * @return array 店舗別・スタッフ別の完全なファネルデータ
     */
    public function getCompleteConversionFunnel(
        ?string $period = 'month',
        ?int $storeId = null,
        ?string $customStartDate = null,
        ?string $customEndDate = null
    ): array {
        // 期間を決定
        if ($period === 'custom' && $customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getPeriodDates($period);
        }

        // 新規顧客を取得（期間内に登録された顧客）
        $newCustomersQuery = \App\Models\Customer::query()
            ->with(['reservations', 'medicalRecords.staff', 'subscriptions', 'tickets', 'store'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($storeId) {
            $newCustomersQuery->where('store_id', $storeId);
        }

        $newCustomers = $newCustomersQuery->get();

        // 店舗別・スタッフ別データを集計
        $storeData = [];
        $staffData = [];

        foreach ($newCustomers as $customer) {
            // 初回予約を取得して店舗を特定
            $firstReservation = $customer->reservations()
                ->orderBy('reservation_date', 'asc')
                ->first();

            // 店舗IDを特定（優先順位: 予約 > 顧客）
            $customerStoreId = null;
            $customerStoreName = '不明';

            if ($firstReservation && $firstReservation->store_id) {
                $customerStoreId = $firstReservation->store_id;
                $customerStoreName = $firstReservation->store ? $firstReservation->store->name : '不明';
            } elseif ($customer->store_id) {
                $customerStoreId = $customer->store_id;
                $customerStoreName = $customer->store ? $customer->store->name : '不明';
            }

            // 店舗が特定できない場合はスキップ
            if (!$customerStoreId) {
                continue;
            }

            // 店舗別データの初期化
            if (!isset($storeData[$customerStoreId])) {
                $storeData[$customerStoreId] = [
                    'store_id' => $customerStoreId,
                    'store_name' => $customerStoreName,
                    'new_customers' => 0,
                    'with_first_reservation' => 0,
                    'with_medical_record' => 0,
                    'with_next_reservation' => 0,
                    'with_subscription' => 0,
                    'with_ticket' => 0,
                    'with_any_contract' => 0,
                ];
            }

            $storeData[$customerStoreId]['new_customers']++;

            if ($firstReservation) {
                $storeData[$customerStoreId]['with_first_reservation']++;

                // カルテを取得
                $medicalRecord = $customer->medicalRecords()
                    ->whereNotNull('staff_id')
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($medicalRecord) {
                    $storeData[$customerStoreId]['with_medical_record']++;

                    $staffId = $medicalRecord->staff_id;
                    $staffName = $medicalRecord->staff ? $medicalRecord->staff->name : '不明';

                    // スタッフ別データの初期化
                    if (!isset($staffData[$staffId])) {
                        $staffData[$staffId] = [
                            'staff_id' => $staffId,
                            'staff_name' => $staffName,
                            'store_id' => $customerStoreId,
                            'store_name' => $customerStoreName,
                            'new_customers' => 0,
                            'with_first_reservation' => 0,
                            'with_medical_record' => 0,
                            'with_next_reservation' => 0,
                            'with_subscription' => 0,
                            'with_ticket' => 0,
                            'with_any_contract' => 0,
                        ];
                    }

                    $staffData[$staffId]['new_customers']++;
                    $staffData[$staffId]['with_first_reservation']++;
                    $staffData[$staffId]['with_medical_record']++;

                    // 次回予約を確認
                    $nextReservation = $customer->reservations()
                        ->where('created_at', '>', $medicalRecord->created_at)
                        ->orderBy('created_at', 'asc')
                        ->first();

                    if ($nextReservation) {
                        $storeData[$customerStoreId]['with_next_reservation']++;
                        $staffData[$staffId]['with_next_reservation']++;
                    }

                    // 契約を確認
                    $hasSubscription = $customer->subscriptions()->exists();
                    $hasTicket = $customer->tickets()->exists();

                    if ($hasSubscription) {
                        $storeData[$customerStoreId]['with_subscription']++;
                        $staffData[$staffId]['with_subscription']++;
                    }

                    if ($hasTicket) {
                        $storeData[$customerStoreId]['with_ticket']++;
                        $staffData[$staffId]['with_ticket']++;
                    }

                    if ($hasSubscription || $hasTicket) {
                        $storeData[$customerStoreId]['with_any_contract']++;
                        $staffData[$staffId]['with_any_contract']++;
                    }
                }
            }
        }

        // 転換率を計算
        foreach ($storeData as &$data) {
            $this->calculateConversionRates($data);
        }

        foreach ($staffData as &$data) {
            $this->calculateConversionRates($data);
        }

        // 契約転換率でソート
        usort($storeData, fn($a, $b) => $b['contract_conversion_rate'] <=> $a['contract_conversion_rate']);
        usort($staffData, fn($a, $b) => $b['contract_conversion_rate'] <=> $a['contract_conversion_rate']);

        return [
            'stores' => array_values($storeData),
            'staff' => array_values($staffData),
            'summary' => $this->calculateSummary($storeData),
        ];
    }

    /**
     * 転換率を計算
     */
    private function calculateConversionRates(array &$data): void
    {
        $newCustomers = $data['new_customers'];
        $withFirstReservation = $data['with_first_reservation'];
        $withMedicalRecord = $data['with_medical_record'];

        $data['first_reservation_rate'] = $newCustomers > 0
            ? round(($withFirstReservation / $newCustomers) * 100, 1)
            : 0;

        $data['medical_record_rate'] = $withFirstReservation > 0
            ? round(($withMedicalRecord / $withFirstReservation) * 100, 1)
            : 0;

        $data['next_reservation_rate'] = $withMedicalRecord > 0
            ? round(($data['with_next_reservation'] / $withMedicalRecord) * 100, 1)
            : 0;

        $data['contract_conversion_rate'] = $withMedicalRecord > 0
            ? round(($data['with_any_contract'] / $withMedicalRecord) * 100, 1)
            : 0;

        // 全体の転換率（新規顧客→契約）
        $data['overall_conversion_rate'] = $newCustomers > 0
            ? round(($data['with_any_contract'] / $newCustomers) * 100, 1)
            : 0;
    }

    /**
     * サマリーを計算
     */
    private function calculateSummary(array $storeData): array
    {
        $summary = [
            'new_customers' => 0,
            'with_first_reservation' => 0,
            'with_medical_record' => 0,
            'with_next_reservation' => 0,
            'with_subscription' => 0,
            'with_ticket' => 0,
            'with_any_contract' => 0,
        ];

        foreach ($storeData as $data) {
            foreach ($summary as $key => $value) {
                $summary[$key] += $data[$key];
            }
        }

        $this->calculateConversionRates($summary);

        return $summary;
    }

    /**
     * カルテ→予約→契約の転換分析を取得
     *
     * @param string|null $period 期間
     * @param int|null $storeId 店舗ID
     * @param string|null $customStartDate カスタム開始日
     * @param string|null $customEndDate カスタム終了日
     * @return array 対応者別の転換データ
     */
    public function getMedicalRecordConversionAnalysis(
        ?string $period = 'month',
        ?int $storeId = null,
        ?string $customStartDate = null,
        ?string $customEndDate = null,
        ?int $handlerId = null
    ): array {
        // 期間を決定
        if ($period === 'custom' && $customStartDate && $customEndDate) {
            $startDate = Carbon::parse($customStartDate)->startOfDay();
            $endDate = Carbon::parse($customEndDate)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getPeriodDates($period);
        }

        // 新規顧客を取得（期間内に登録された顧客）
        $newCustomersQuery = \App\Models\Customer::query()
            ->with(['reservations', 'medicalRecords.staff', 'subscriptions', 'tickets'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($storeId) {
            $newCustomersQuery->where('store_id', $storeId);
        }

        $newCustomers = $newCustomersQuery->get();

        // 各顧客の初回予約とカルテを分析
        $handlerData = [];

        foreach ($newCustomers as $customer) {
            // 初回予約を取得
            $firstReservation = $customer->reservations()
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$firstReservation) {
                continue;
            }

            // 初回予約後に作成されたカルテを取得
            $medicalRecord = $customer->medicalRecords()
                ->whereNotNull('staff_id')
                ->where('created_at', '>=', $firstReservation->created_at)
                ->when($handlerId, fn($q) => $q->where('staff_id', $handlerId))
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$medicalRecord || !$medicalRecord->staff) {
                continue;
            }

            $staffId = $medicalRecord->staff_id;

            if (!isset($handlerData[$staffId])) {
                $handlerData[$staffId] = [
                    'handler_id' => $staffId,
                    'handler_name' => $medicalRecord->staff->name,
                    'new_reservation_count' => 0,
                    'medical_record_count' => 0,
                    'next_reservation_count' => 0,
                    'subscription_count' => 0,
                    'ticket_count' => 0,
                    'total_contract_count' => 0,
                    'customers' => [],
                ];
            }

            // カウント
            $handlerData[$staffId]['new_reservation_count']++;
            $handlerData[$staffId]['medical_record_count']++;

            $customerData = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->last_name . ' ' . $customer->first_name,
                'reservation_date' => $firstReservation->created_at->format('Y-m-d'),
                'medical_record_date' => $medicalRecord->created_at->format('Y-m-d'),
                'has_next_reservation' => false,
                'next_reservation_date' => null,
                'contract_type' => null,
                'contract_date' => null,
            ];

            // 次回予約を確認（カルテ作成後の次の予約）
            $nextReservation = $customer->reservations()
                ->where('created_at', '>', $medicalRecord->created_at)
                ->orderBy('created_at', 'asc')
                ->first();

            if ($nextReservation) {
                $handlerData[$staffId]['next_reservation_count']++;
                $customerData['has_next_reservation'] = true;
                $customerData['next_reservation_date'] = $nextReservation->reservation_date;
            }

            // カルテ作成後の最初の契約を確認
            $subscription = $customer->subscriptions()
                ->where('created_at', '>', $medicalRecord->created_at)
                ->orderBy('created_at', 'asc')
                ->first();

            $ticket = $customer->tickets()
                ->where('created_at', '>', $medicalRecord->created_at)
                ->orderBy('created_at', 'asc')
                ->first();

            // 契約を判定
            if ($subscription && (!$ticket || $subscription->created_at <= $ticket->created_at)) {
                $handlerData[$staffId]['subscription_count']++;
                $handlerData[$staffId]['total_contract_count']++;
                $customerData['contract_type'] = 'サブスク';
                $customerData['contract_date'] = $subscription->created_at->format('Y-m-d');
            } elseif ($ticket) {
                $handlerData[$staffId]['ticket_count']++;
                $handlerData[$staffId]['total_contract_count']++;
                $customerData['contract_type'] = '回数券';
                $customerData['contract_date'] = $ticket->created_at->format('Y-m-d');
            }

            $handlerData[$staffId]['customers'][] = $customerData;
        }

        $results = array_values($handlerData);

        // 転換率を計算
        foreach ($results as &$data) {
            $newReservations = $data['new_reservation_count'];
            $medicalRecords = $data['medical_record_count'];

            $data['medical_record_rate'] = $newReservations > 0
                ? round(($medicalRecords / $newReservations) * 100, 1)
                : 0;
            $data['next_reservation_rate'] = $medicalRecords > 0
                ? round(($data['next_reservation_count'] / $medicalRecords) * 100, 1)
                : 0;
            $data['contract_rate'] = $medicalRecords > 0
                ? round(($data['total_contract_count'] / $medicalRecords) * 100, 1)
                : 0;
        }

        // 契約転換率でソート（降順）
        usort($results, function($a, $b) {
            return $b['contract_rate'] <=> $a['contract_rate'];
        });

        return $results;
    }
}