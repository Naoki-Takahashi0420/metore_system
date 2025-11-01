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

        // 平均客単価
        $avgTicket = $totalReservations > 0
            ? round($totalRevenue / $totalReservations, 0)
            : 0;

        return [
            'total_reservations' => $totalReservations,
            'new_customers' => $newCustomers,
            'total_revenue' => $totalRevenue,
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

        $staffData = User::query()
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereIn('roles.name', ['staff', 'manager'])
            ->when($storeId, function($q) use ($storeId) {
                $q->join('store_managers', 'users.id', '=', 'store_managers.user_id')
                    ->where('store_managers.store_id', $storeId);
            })
            ->select('users.id', 'users.name')
            ->get();

        $performanceData = [];

        foreach ($staffData as $staff) {
            // 予約件数（指名回数）
            $reservationCount = Reservation::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('reservation_date', [$startDate, $endDate])
                ->whereIn('status', ['completed', 'in_progress'])
                ->count();

            // 売上金額
            $revenue = Sale::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('sale_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->sum('total_amount');

            // 新規顧客数
            $newCustomers = Reservation::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('reservation_date', [$startDate, $endDate])
                ->whereIn('status', ['completed'])
                ->whereHas('customer', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->distinct('customer_id')
                ->count('customer_id');

            // サブスク転換数（新規顧客からサブスクへ）
            $subscriptionConversions = CustomerSubscription::query()
                ->whereHas('customer.reservations', function($q) use ($staff, $startDate, $endDate) {
                    $q->where('staff_id', $staff->id)
                        ->whereBetween('reservation_date', [$startDate, $endDate]);
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            // 転換率
            $conversionRate = $newCustomers > 0
                ? round(($subscriptionConversions / $newCustomers) * 100, 1)
                : 0;

            // リピート予約獲得数
            $repeatReservations = Reservation::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('reservation_date', [$startDate, $endDate])
                ->whereIn('status', ['completed', 'confirmed'])
                ->whereHas('customer', function($q) use ($startDate) {
                    $q->where('created_at', '<', $startDate);
                })
                ->count();

            // 初回顧客のうちリピートした人数
            $firstTimeCustomerIds = Reservation::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('reservation_date', [$startDate, $endDate])
                ->whereIn('status', ['completed'])
                ->whereHas('customer', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->pluck('customer_id');

            $repeatCustomersFromNew = 0;
            if ($firstTimeCustomerIds->count() > 0) {
                $repeatCustomersFromNew = Reservation::query()
                    ->whereIn('customer_id', $firstTimeCustomerIds)
                    ->where('staff_id', $staff->id)
                    ->where('reservation_date', '>', $endDate)
                    ->whereIn('status', ['completed', 'confirmed', 'pending'])
                    ->distinct('customer_id')
                    ->count('customer_id');
            }

            // リピート獲得率（初回顧客のうち次回予約を取った割合）
            $repeatAcquisitionRate = $newCustomers > 0
                ? round(($repeatCustomersFromNew / $newCustomers) * 100, 1)
                : 0;

            // 顧客継続率（担当した既存顧客のうち期間後も継続している割合）
            $existingCustomers = Reservation::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('reservation_date', [$startDate, $endDate])
                ->whereIn('status', ['completed'])
                ->whereHas('customer', function($q) use ($startDate) {
                    $q->where('created_at', '<', $startDate);
                })
                ->distinct('customer_id')
                ->count('customer_id');

            $continuingCustomers = Reservation::query()
                ->where('staff_id', $staff->id)
                ->where('reservation_date', '>', $endDate)
                ->where('reservation_date', '<=', $endDate->copy()->addDays(60))
                ->whereIn('status', ['completed', 'confirmed', 'pending'])
                ->whereHas('customer', function($q) use ($startDate) {
                    $q->where('created_at', '<', $startDate);
                })
                ->distinct('customer_id')
                ->count('customer_id');

            $customerRetentionRate = $existingCustomers > 0
                ? round(($continuingCustomers / $existingCustomers) * 100, 1)
                : 0;

            // 平均接客間隔（日数）- データベース互換性対応
            $customerVisits = Reservation::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('reservation_date', [$startDate, $endDate])
                ->whereIn('status', ['completed'])
                ->select('customer_id')
                ->selectRaw('COUNT(*) as visit_count')
                ->selectRaw('MAX(reservation_date) as max_date')
                ->selectRaw('MIN(reservation_date) as min_date')
                ->groupBy('customer_id')
                ->havingRaw('visit_count > 1')
                ->get();

            $avgVisitInterval = 0;
            if ($customerVisits->count() > 0) {
                $intervals = $customerVisits->map(function($visit) {
                    $maxDate = Carbon::parse($visit->max_date);
                    $minDate = Carbon::parse($visit->min_date);
                    $dateSpan = $maxDate->diffInDays($minDate);
                    return $dateSpan / ($visit->visit_count - 1);
                });
                $avgVisitInterval = round($intervals->avg(), 1);
            }

            // 顧客満足度スコア（キャンセル率の逆数で推定）
            $totalCustomerReservations = Reservation::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('reservation_date', [$startDate, $endDate])
                ->count();

            $cancelledReservations = Reservation::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('reservation_date', [$startDate, $endDate])
                ->whereIn('status', ['cancelled', 'no_show'])
                ->count();

            $satisfactionScore = $totalCustomerReservations > 0
                ? round((1 - ($cancelledReservations / $totalCustomerReservations)) * 100, 1)
                : 100;

            $performanceData[] = [
                'id' => $staff->id,
                'name' => $staff->name,
                'reservation_count' => $reservationCount,
                'revenue' => $revenue,
                'new_customers' => $newCustomers,
                'subscription_conversions' => $subscriptionConversions,
                'conversion_rate' => $conversionRate,
                'repeat_reservations' => $repeatReservations,
                'repeat_acquisition_rate' => $repeatAcquisitionRate,
                'customer_retention_rate' => $customerRetentionRate,
                'avg_visit_interval' => $avgVisitInterval,
                'satisfaction_score' => $satisfactionScore,
                'avg_ticket' => $reservationCount > 0
                    ? round($revenue / $reservationCount, 0)
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

        return [
            'new_customers_trend' => $newCustomersTrend,
            'existing_customer_visits' => $existingCustomerVisits,
            'churn_risk_customers' => $churnRiskCustomers,
            'segments' => $segments,
            'cancel_rate' => $cancelRate,
            'no_show_rate' => $noShowRate,
        ];
    }

    /**
     * コンバージョンファネルデータを取得
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

        // 1. 新規顧客獲得
        $newCustomers = Customer::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->count();

        $funnel[] = [
            'stage' => '新規顧客登録',
            'count' => $newCustomers,
            'rate' => 100,
        ];

        // 2. 初回予約完了
        $firstReservations = Reservation::query()
            ->whereBetween('reservation_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereIn('status', ['completed'])
            ->whereHas('customer', function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->distinct('customer_id')
            ->count('customer_id');

        $funnel[] = [
            'stage' => '初回来店',
            'count' => $firstReservations,
            'rate' => $newCustomers > 0
                ? round(($firstReservations / $newCustomers) * 100, 1)
                : 0,
        ];

        // 3. 2回目予約
        $secondReservations = DB::table('reservations as r1')
            ->join('reservations as r2', function($join) use ($startDate, $endDate) {
                $join->on('r1.customer_id', '=', 'r2.customer_id')
                    ->whereRaw('r2.reservation_date > r1.reservation_date')
                    ->whereBetween('r2.reservation_date', [$startDate, $endDate]);
            })
            ->whereBetween('r1.reservation_date', [$startDate, $endDate])
            ->when($storeId, fn($q) => $q->where('r1.store_id', $storeId))
            ->whereIn('r1.status', ['completed'])
            ->whereIn('r2.status', ['completed', 'confirmed'])
            ->distinct('r1.customer_id')
            ->count('r1.customer_id');

        $funnel[] = [
            'stage' => '2回目予約',
            'count' => $secondReservations,
            'rate' => $firstReservations > 0
                ? round(($secondReservations / $firstReservations) * 100, 1)
                : 0,
        ];

        // 4. サブスク契約
        $subscriptions = CustomerSubscription::query()
            ->whereHas('customer', function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('status', 'active')
            ->count();

        $funnel[] = [
            'stage' => 'サブスク契約',
            'count' => $subscriptions,
            'rate' => $secondReservations > 0
                ? round(($subscriptions / $secondReservations) * 100, 1)
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