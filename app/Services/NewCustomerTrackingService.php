<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\MedicalRecord;
use App\Models\CustomerSubscription;
use App\Models\CustomerTicket;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 新規顧客追跡サービス
 *
 * スプレッドシート形式のマーケティング分析を行う
 * - 新規結果シート: 顧客ごとの1回目→2回目→3回目の追跡
 * - 全体集計: 媒体別×結果のクロス集計
 * - 対応者別: スタッフ別のパフォーマンス
 *
 * 予約を起点として追跡（キャンセル・飛びも含む）
 */
class NewCustomerTrackingService
{
    /**
     * 結果の種類
     */
    const RESULT_SUBSCRIPTION = 'サブスク';
    const RESULT_TICKET = '回数券';
    const RESULT_NEXT_RESERVATION = '次回予約';
    const RESULT_NO_RESERVATION = '予約なし';
    const RESULT_CANCELLED = 'キャンセル';
    const RESULT_NO_SHOW = '飛び';

    /**
     * 媒体の正規化マッピング
     * ※ メタ広告・Instagram は別々に表示（広告効果測定のため）
     */
    private array $sourceMapping = [
        'Google' => 'Google',
        'google' => 'Google',
        'グーグル' => 'Google',
        'SNS' => 'SNS',
        'instagram' => 'Instagram',
        'Instagram' => 'Instagram',
        'インスタ' => 'Instagram',
        'メタ広告' => 'Meta広告',
        'meta広告' => 'Meta広告',
        'Meta広告' => 'Meta広告',
        'Facebook' => 'Meta広告',
        'facebook' => 'Meta広告',
        'line' => '公式LINE',
        'LINE' => '公式LINE',
        '公式ライン' => '公式LINE',
        '公式LINE' => '公式LINE',
        'くまポン' => 'くまポン',
        'くまぽん' => 'くまポン',
        'お客様紹介' => 'お客様紹介',
        'referral' => 'お客様紹介',
        '紹介' => 'お客様紹介',
        '交流会' => '交流会',
        '従業員紹介' => '従業員紹介',
        '他店紹介' => '他店紹介',
        'ビラ' => 'ビラ',
        'HP' => 'HP',
        'ホームページ' => 'HP',
        '不明' => '不明',
    ];

    /**
     * 新規顧客の追跡データを取得（新規結果シート形式）
     * 予約を起点として追跡
     */
    public function getNewCustomerTracking(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): Collection {
        // デフォルトは今月（パフォーマンス考慮）
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        // 期間内に初めて来店した顧客を特定
        // 注意: 新規顧客の判定は「全店舗を通じて」行う（店舗フィルターは適用しない）
        // 表示フィルターは後で適用（初回来店店舗が選択店舗の顧客のみ表示）
        $firstReservations = Reservation::query()
            ->select('customer_id', DB::raw('MIN(reservation_date) as first_reservation_date'))
            ->selectRaw('(SELECT store_id FROM reservations r2 WHERE r2.customer_id = reservations.customer_id AND r2.status = \'completed\' ORDER BY reservation_date, start_time LIMIT 1) as first_store_id')
            ->whereNotNull('customer_id')
            ->where('status', 'completed')  // 実際に来店した予約のみ
            // 新規判定は全店舗で行う（店舗フィルターは表示時に適用）
            ->groupBy('customer_id')
            ->havingRaw('MIN(reservation_date) >= ?', [$start])
            ->havingRaw('MIN(reservation_date) <= ?', [$end])
            // 店舗フィルター: 初回来店が選択店舗で行われた顧客のみ
            ->when($storeId, function($q) use ($storeId) {
                $q->havingRaw('(SELECT store_id FROM reservations r2 WHERE r2.customer_id = reservations.customer_id AND r2.status = \'completed\' ORDER BY reservation_date, start_time LIMIT 1) = ?', [$storeId]);
            })
            ->get();

        $newCustomerIds = $firstReservations->pluck('customer_id')->toArray();

        if (empty($newCustomerIds)) {
            return collect();
        }

        // 新規顧客の全データを取得
        $customers = Customer::whereIn('id', $newCustomerIds)
            ->with([
                'reservations' => function($q) use ($storeId) {
                    $q->with('store')->orderBy('reservation_date', 'asc')->orderBy('start_time', 'asc');
                    if ($storeId) $q->where('store_id', $storeId);
                },
                'medicalRecords' => function($q) use ($storeId) {
                    $q->with('staff')->orderBy('treatment_date', 'asc');
                    if ($storeId) $q->where('store_id', $storeId);
                },
                'subscriptions' => function($q) use ($storeId) {
                    $q->orderBy('created_at', 'asc');
                    if ($storeId) $q->where('store_id', $storeId);
                },
                'tickets' => function($q) use ($storeId) {
                    $q->orderBy('purchased_at', 'asc');
                    if ($storeId) $q->where('store_id', $storeId);
                }
            ])
            ->get();

        $result = collect();

        foreach ($customers as $customer) {
            // 来店済み（completed）の予約のみを追跡対象とする
            $completedReservations = $customer->reservations
                ->filter(fn($r) => $r->status === 'completed')
                ->values();
            $records = $customer->medicalRecords->values();

            if ($completedReservations->isEmpty()) continue;

            $firstReservation = $completedReservations->first();

            // 予約日（created_at）と来店予定日（reservation_date）
            $bookingDate = $firstReservation->created_at;
            $visitDate = $firstReservation->reservation_date;

            // カルテを取得（優先順位: 1.予約日マッチ → 2.最初のカルテ）
            $firstRecord = $this->findRecordForReservation($records, $firstReservation);
            if (!$firstRecord && $records->isNotEmpty()) {
                // 日付マッチしない場合は最初のカルテを使用
                $firstRecord = $records->first();
            }

            // 媒体: カルテがあればカルテから、なければ不明
            $source = $this->getSourceFromRecord($firstRecord);

            // 1回目結果を判定
            $visit1Result = $this->determineReservationResult($customer, $completedReservations, 0);

            // 1回目対応者: handled_by（テキスト）を優先、なければstaff.nameを使用
            // ※ handled_byには実際に対応した人の名前が手入力されることが多い
            // ※ staff_idは店舗名が選択されていることがある（藤沢店、秋葉原など）
            $visit1Handler = null;
            if ($firstRecord) {
                // まずhandled_byから取得（テキスト入力パターン - 優先）
                if ($firstRecord->handled_by) {
                    $visit1Handler = $this->extractHandlerName($firstRecord->handled_by);
                }
                // handled_byがない場合はstaff.nameから（選択パターン - フォールバック）
                if (!$visit1Handler && $firstRecord->staff) {
                    $visit1Handler = $this->extractHandlerName($firstRecord->staff->name);
                }
            }

            // 店舗名を取得（短縮名：「目のトレーニング吉祥寺店」→「吉祥寺」）
            $storeName = '-';
            if ($firstReservation->store) {
                $storeName = $firstReservation->store->name;
                // 「目のトレーニング」と「店」を除去
                $storeName = str_replace(['目のトレーニング', '店'], '', $storeName);
                // 「目トレ」も除去
                $storeName = str_replace('目トレ', '', $storeName);
                $storeName = trim($storeName) ?: '-';
            }

            $trackingData = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->last_name . $customer->first_name,
                'phone' => $customer->phone,
                'store' => $storeName,
                'booking_date' => $bookingDate ? Carbon::parse($bookingDate)->format('Y/m/d') : null,
                'visit1_date' => Carbon::parse($visitDate)->format('Y/m/d'),
                'source' => $source,
                'visit1_result' => $visit1Result,
                'visit1_handler' => $visit1Handler,
                'subscription_plan' => null,
                'year_month' => Carbon::parse($visitDate)->format('Y-m'),
            ];

            // 2回目, 3回目のデータ
            for ($i = 2; $i <= 3; $i++) {
                $reservation = $completedReservations->get($i - 1);
                if ($reservation) {
                    // カルテを取得（優先順位: 1.予約日マッチ → 2.i番目のカルテ）
                    $record = $this->findRecordForReservation($records, $reservation);
                    if (!$record && $records->count() >= $i) {
                        $record = $records->get($i - 1);
                    }

                    $trackingData["visit{$i}_date"] = Carbon::parse($reservation->reservation_date)->format('Y/m/d');
                    $trackingData["visit{$i}_result"] = $this->determineReservationResult($customer, $completedReservations, $i - 1);

                    // 対応者: handled_by（テキスト）を優先、なければstaff.nameを使用
                    $handler = null;
                    if ($record) {
                        if ($record->handled_by) {
                            $handler = $this->extractHandlerName($record->handled_by);
                        }
                        if (!$handler && $record->staff) {
                            $handler = $this->extractHandlerName($record->staff->name);
                        }
                    }
                    $trackingData["visit{$i}_handler"] = $handler;
                } else {
                    $trackingData["visit{$i}_date"] = null;
                    $trackingData["visit{$i}_result"] = null;
                    $trackingData["visit{$i}_handler"] = null;
                }
            }

            // サブスク契約情報
            $subscription = $customer->subscriptions->first();
            if ($subscription) {
                $trackingData['subscription_plan'] = $subscription->plan_name;
            }

            $result->push($trackingData);
        }

        // 来店日でソート
        return $result->sortBy('visit1_date')->values();
    }

    /**
     * 予約に対応するカルテを探す
     */
    private function findRecordForReservation(Collection $records, Reservation $reservation): ?MedicalRecord
    {
        $reservationDate = Carbon::parse($reservation->reservation_date)->format('Y-m-d');

        return $records->first(function ($record) use ($reservationDate) {
            return Carbon::parse($record->treatment_date)->format('Y-m-d') === $reservationDate;
        });
    }

    /**
     * カルテから媒体を取得
     */
    private function getSourceFromRecord(?MedicalRecord $record): string
    {
        if (!$record || !$record->reservation_source) {
            return '不明';
        }
        return $this->normalizeSource($record->reservation_source);
    }

    /**
     * 来店の結果を判定
     *
     * @param Customer $customer 顧客
     * @param Collection $reservations 来店済み予約（completed、時系列順）
     * @param int $index 対象の予約インデックス（0始まり）
     */
    private function determineReservationResult(
        Customer $customer,
        Collection $reservations,
        int $index
    ): string {
        $reservation = $reservations->get($index);
        if (!$reservation) {
            return self::RESULT_NO_RESERVATION;
        }

        // キャンセルの場合
        if ($reservation->status === 'cancelled') {
            return self::RESULT_CANCELLED;
        }

        // ノーショー（飛び）の場合
        if ($reservation->status === 'no_show') {
            return self::RESULT_NO_SHOW;
        }

        // 完了した予約の場合、その後の結果を判定
        $reservationDate = Carbon::parse($reservation->reservation_date);

        // サブスク契約を確認（予約日から30日以内）
        $subscription = $customer->subscriptions
            ->filter(function ($sub) use ($reservationDate) {
                $createdAt = Carbon::parse($sub->created_at);
                return $createdAt >= $reservationDate && $createdAt <= $reservationDate->copy()->addDays(30);
            })
            ->first();

        if ($subscription) {
            return self::RESULT_SUBSCRIPTION;
        }

        // 回数券購入を確認（予約日から30日以内）
        $ticket = $customer->tickets
            ->filter(function ($t) use ($reservationDate) {
                if (!$t->purchased_at) return false;
                $purchasedAt = Carbon::parse($t->purchased_at);
                return $purchasedAt >= $reservationDate && $purchasedAt <= $reservationDate->copy()->addDays(30);
            })
            ->first();

        if ($ticket) {
            return self::RESULT_TICKET;
        }

        // 次の予約があるか確認
        $nextReservation = $reservations->get($index + 1);
        if ($nextReservation) {
            return self::RESULT_NEXT_RESERVATION;
        }

        // 将来の予約（booked/confirmed）があるか確認
        // ※ 顧客の全予約から確認（completedReservationsではなく）
        $futureReservation = $customer->reservations
            ->filter(function ($r) use ($reservationDate) {
                return Carbon::parse($r->reservation_date) > $reservationDate
                    && in_array($r->status, ['booked', 'confirmed']);
            })
            ->first();

        if ($futureReservation) {
            return self::RESULT_NEXT_RESERVATION;
        }

        return self::RESULT_NO_RESERVATION;
    }

    /**
     * 全体集計（媒体別×結果のクロス集計）
     */
    public function getSourceResultSummary(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        int $visitNumber = 1
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        $resultKey = "visit{$visitNumber}_result";
        $sources = $tracking->pluck('source')->unique()->filter()->sort()->values();
        $results = [
            self::RESULT_CANCELLED,
            self::RESULT_SUBSCRIPTION,
            self::RESULT_TICKET,
            self::RESULT_NEXT_RESERVATION,
            self::RESULT_NO_SHOW,
            self::RESULT_NO_RESERVATION,
        ];

        $matrix = [];
        foreach ($sources as $source) {
            $row = ['source' => $source];
            $total = 0;
            $positive = 0;

            foreach ($results as $result) {
                $count = $tracking->where('source', $source)->where($resultKey, $result)->count();
                $row[$result] = $count;
                $total += $count;
                if (in_array($result, [self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION])) {
                    $positive += $count;
                }
            }

            $row['total'] = $total;
            $row['conversion_rate'] = $total > 0 ? round($positive / $total, 2) : 0;
            $matrix[] = $row;
        }

        // 合計行
        $totalRow = ['source' => '総計'];
        $grandTotal = 0;
        $grandPositive = 0;

        foreach ($results as $result) {
            $count = $tracking->where($resultKey, $result)->count();
            $totalRow[$result] = $count;
            $grandTotal += $count;
            if (in_array($result, [self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION])) {
                $grandPositive += $count;
            }
        }
        $totalRow['total'] = $grandTotal;
        $totalRow['conversion_rate'] = $grandTotal > 0 ? round($grandPositive / $grandTotal, 2) : 0;
        $matrix[] = $totalRow;

        return [
            'headers' => array_merge(['媒体'], $results, ['総計', '打率']),
            'data' => $matrix,
            'visit_number' => $visitNumber,
        ];
    }

    /**
     * 対応者別集計
     */
    public function getHandlerResultSummary(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        int $visitNumber = 1
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        $handlerKey = "visit{$visitNumber}_handler";
        $resultKey = "visit{$visitNumber}_result";
        $handlers = $tracking->pluck($handlerKey)->unique()->filter()->sort()->values();
        $results = [
            self::RESULT_CANCELLED,
            self::RESULT_SUBSCRIPTION,
            self::RESULT_TICKET,
            self::RESULT_NEXT_RESERVATION,
            self::RESULT_NO_SHOW,
            self::RESULT_NO_RESERVATION,
        ];

        $matrix = [];

        // 対応者nullのデータを「不明」として追加
        $unknownData = $tracking->filter(fn($row) => $row[$handlerKey] === null);
        if ($unknownData->count() > 0) {
            $row = ['handler' => '不明'];
            $total = 0;
            $positive = 0;

            foreach ($results as $result) {
                $count = $unknownData->where($resultKey, $result)->count();
                $row[$result] = $count;
                $total += $count;
                if (in_array($result, [self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION])) {
                    $positive += $count;
                }
            }

            $row['total'] = $total;
            $row['conversion_rate'] = $total > 0 ? round($positive / $total, 2) : 0;
            $matrix[] = $row;
        }

        // 個別対応者の行
        foreach ($handlers as $handler) {
            $row = ['handler' => $handler];
            $total = 0;
            $positive = 0;

            foreach ($results as $result) {
                $count = $tracking->where($handlerKey, $handler)->where($resultKey, $result)->count();
                $row[$result] = $count;
                $total += $count;
                if (in_array($result, [self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION])) {
                    $positive += $count;
                }
            }

            $row['total'] = $total;
            $row['conversion_rate'] = $total > 0 ? round($positive / $total, 2) : 0;
            $matrix[] = $row;
        }

        // 合計行
        $totalRow = ['handler' => '総計'];
        $grandTotal = 0;
        $grandPositive = 0;

        foreach ($results as $result) {
            $count = $tracking->where($resultKey, $result)->count();
            $totalRow[$result] = $count;
            $grandTotal += $count;
            if (in_array($result, [self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION])) {
                $grandPositive += $count;
            }
        }
        $totalRow['total'] = $grandTotal;
        $totalRow['conversion_rate'] = $grandTotal > 0 ? round($grandPositive / $grandTotal, 2) : 0;
        $matrix[] = $totalRow;

        return [
            'headers' => array_merge(['対応者'], $results, ['総計', '打率']),
            'data' => $matrix,
            'visit_number' => $visitNumber,
        ];
    }

    /**
     * サブスク内訳（媒体別×プラン）
     */
    public function getSubscriptionBreakdown(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        // サブスク契約者のみ
        $subscribers = $tracking->whereNotNull('subscription_plan');

        $sources = $subscribers->pluck('source')->unique()->filter()->sort()->values();
        $plans = $subscribers->pluck('subscription_plan')->unique()->filter()->sort()->values();

        $matrix = [];
        foreach ($sources as $source) {
            $row = ['source' => $source];
            $total = 0;

            foreach ($plans as $plan) {
                $count = $subscribers->where('source', $source)->where('subscription_plan', $plan)->count();
                $row[$plan] = $count;
                $total += $count;
            }

            $row['total'] = $total;
            $matrix[] = $row;
        }

        // 合計行
        $totalRow = ['source' => '総計'];
        $grandTotal = 0;

        foreach ($plans as $plan) {
            $count = $subscribers->where('subscription_plan', $plan)->count();
            $totalRow[$plan] = $count;
            $grandTotal += $count;
        }
        $totalRow['total'] = $grandTotal;
        $matrix[] = $totalRow;

        return [
            'headers' => array_merge(['媒体'], $plans->toArray(), ['総計']),
            'data' => $matrix,
        ];
    }

    /**
     * サブスク契約の対応者別詳細リスト（インセンティブ計算用）
     *
     * カルテのhandled_byを元に、誰がどのサブスク契約を獲得したかを表示
     */
    public function getSubscriptionHandlerDetails(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        // サブスク契約者のみ
        $subscribers = $tracking->whereNotNull('subscription_plan');

        // 対応者別に集計
        $handlerSummary = [];
        $detailList = [];

        foreach ($subscribers as $row) {
            $handler = $row['visit1_handler'] ?? '不明';
            $plan = $row['subscription_plan'] ?? '不明';

            // 集計
            if (!isset($handlerSummary[$handler])) {
                $handlerSummary[$handler] = [
                    'handler' => $handler,
                    'total' => 0,
                    'plans' => [],
                ];
            }
            $handlerSummary[$handler]['total']++;
            $handlerSummary[$handler]['plans'][$plan] = ($handlerSummary[$handler]['plans'][$plan] ?? 0) + 1;

            // 詳細リスト
            $detailList[] = [
                'customer_name' => $row['customer_name'] ?? '',
                'handler' => $handler,
                'plan' => $plan,
                'source' => $row['source'] ?? '',
                'visit1_date' => $row['visit1_date'] ?? '',
                'subscription_start' => $row['subscription_start'] ?? '',
            ];
        }

        // 対応者別サマリーをソート（件数順）
        $handlerSummary = collect($handlerSummary)
            ->sortByDesc('total')
            ->values()
            ->toArray();

        // 詳細リストを契約日順にソート
        $detailList = collect($detailList)
            ->sortByDesc('subscription_start')
            ->values()
            ->toArray();

        return [
            'summary' => $handlerSummary,
            'details' => $detailList,
            'total_count' => count($detailList),
        ];
    }

    /**
     * 回数券購入の対応者別詳細リスト（インセンティブ計算用）
     */
    public function getTicketHandlerDetails(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        // 回数券購入者のみ
        $ticketBuyers = $tracking->whereNotNull('ticket_plan');

        $handlerSummary = [];
        $detailList = [];

        foreach ($ticketBuyers as $row) {
            $handler = $row['visit1_handler'] ?? '不明';
            $plan = $row['ticket_plan'] ?? '不明';

            if (!isset($handlerSummary[$handler])) {
                $handlerSummary[$handler] = [
                    'handler' => $handler,
                    'total' => 0,
                    'plans' => [],
                ];
            }
            $handlerSummary[$handler]['total']++;
            $handlerSummary[$handler]['plans'][$plan] = ($handlerSummary[$handler]['plans'][$plan] ?? 0) + 1;

            $detailList[] = [
                'customer_name' => $row['customer_name'] ?? '',
                'handler' => $handler,
                'plan' => $plan,
                'source' => $row['source'] ?? '',
                'visit1_date' => $row['visit1_date'] ?? '',
            ];
        }

        $handlerSummary = collect($handlerSummary)
            ->sortByDesc('total')
            ->values()
            ->toArray();

        $detailList = collect($detailList)
            ->sortByDesc('visit1_date')
            ->values()
            ->toArray();

        return [
            'summary' => $handlerSummary,
            'details' => $detailList,
            'total_count' => count($detailList),
        ];
    }

    /**
     * 次回予約獲得の対応者別詳細リスト（インセンティブ計算用）
     *
     * @param int $visitNumber 回数（1回目、2回目、3回目）
     */
    public function getNextReservationHandlerDetails(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        int $visitNumber = 1
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        $resultKey = "visit{$visitNumber}_result";
        $handlerKey = "visit{$visitNumber}_handler";
        $dateKey = "visit{$visitNumber}_date";
        $nextDateKey = "visit" . ($visitNumber + 1) . "_date";

        // 次回予約ありの顧客のみ（サブスク・回数券以外で次回予約がある）
        $nextReservations = $tracking->filter(function ($row) use ($resultKey) {
            return ($row[$resultKey] ?? null) === self::RESULT_NEXT_RESERVATION;
        });

        $handlerSummary = [];
        $detailList = [];

        foreach ($nextReservations as $row) {
            $handler = $row[$handlerKey] ?? '不明';

            if (!isset($handlerSummary[$handler])) {
                $handlerSummary[$handler] = [
                    'handler' => $handler,
                    'total' => 0,
                ];
            }
            $handlerSummary[$handler]['total']++;

            $detailList[] = [
                'customer_name' => $row['customer_name'] ?? '',
                'handler' => $handler,
                'source' => $row['source'] ?? '',
                'visit_date' => $row[$dateKey] ?? '',
                'next_visit_date' => $row[$nextDateKey] ?? '',
            ];
        }

        $handlerSummary = collect($handlerSummary)
            ->sortByDesc('total')
            ->values()
            ->toArray();

        $detailList = collect($detailList)
            ->sortByDesc('visit1_date')
            ->values()
            ->toArray();

        return [
            'summary' => $handlerSummary,
            'details' => $detailList,
            'total_count' => count($detailList),
        ];
    }

    /**
     * 月別集計
     */
    public function getMonthlyHandlerStats(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        $months = $tracking->pluck('year_month')->unique()->sort()->values();
        $handlers = $tracking->pluck('visit1_handler')->unique()->filter()->sort()->values();

        $result = [
            'months' => $months,
            'handlers' => $handlers,
            'new_customer_counts' => [],
            'next_reservation_counts' => [],
            'subscription_counts' => [],
        ];

        foreach ($months as $month) {
            $monthData = $tracking->where('year_month', $month);

            $newCustomers = [];
            $nextReservations = [];
            $subscriptions = [];

            foreach ($handlers as $handler) {
                $handlerData = $monthData->where('visit1_handler', $handler);
                $newCustomers[$handler] = $handlerData->count();
                $nextReservations[$handler] = $handlerData->where('visit1_result', self::RESULT_NEXT_RESERVATION)->count();
                $subscriptions[$handler] = $handlerData->where('visit1_result', self::RESULT_SUBSCRIPTION)->count();
            }

            $newCustomers['total'] = $monthData->count();
            $nextReservations['total'] = $monthData->where('visit1_result', self::RESULT_NEXT_RESERVATION)->count();
            $subscriptions['total'] = $monthData->where('visit1_result', self::RESULT_SUBSCRIPTION)->count();

            $result['new_customer_counts'][$month] = $newCustomers;
            $result['next_reservation_counts'][$month] = $nextReservations;
            $result['subscription_counts'][$month] = $subscriptions;
        }

        // 対応者別の打率計算
        $handlerRates = [];
        foreach ($handlers as $handler) {
            $handlerData = $tracking->where('visit1_handler', $handler);
            $total = $handlerData->count();
            $positive = $handlerData->whereIn('visit1_result', [
                self::RESULT_SUBSCRIPTION,
                self::RESULT_TICKET,
                self::RESULT_NEXT_RESERVATION
            ])->count();

            $handlerRates[$handler] = [
                'total' => $total,
                'positive' => $positive,
                'conversion_rate' => $total > 0 ? round($positive / $total, 2) : 0,
                'subscription_rate' => $total > 0
                    ? round($handlerData->where('visit1_result', self::RESULT_SUBSCRIPTION)->count() / $total, 2)
                    : 0,
            ];
        }
        $result['handler_rates'] = $handlerRates;

        return $result;
    }

    /**
     * CSV出力用データ
     */
    public function exportToCsv(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): string {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        $headers = [
            '予約日', '氏名', '来店日', '媒体', '1回目結果', '対応者',
            '来店日2', '2回目結果', '対応者2',
            '来店日3', '3回目結果', '対応者3',
            'サブスク', '年月'
        ];

        $rows = [$headers];

        foreach ($tracking as $data) {
            $rows[] = [
                $data['booking_date'] ?? '',
                $data['customer_name'],
                $data['visit1_date'],
                $data['source'] ?? '',
                $data['visit1_result'] ?? '',
                $data['visit1_handler'] ?? '',
                $data['visit2_date'] ?? '',
                $data['visit2_result'] ?? '',
                $data['visit2_handler'] ?? '',
                $data['visit3_date'] ?? '',
                $data['visit3_result'] ?? '',
                $data['visit3_handler'] ?? '',
                $data['subscription_plan'] ?? '',
                $data['year_month'],
            ];
        }

        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        // UTF-8 BOM（Excel対応）
        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * 媒体を正規化
     */
    private function normalizeSource(?string $source): string
    {
        if (!$source || $source === '') {
            return '不明';
        }

        return $this->sourceMapping[$source] ?? $source;
    }

    /**
     * 対応者名を取得（入力されたまま表示）
     */
    private function extractHandlerName(?string $handledBy): ?string
    {
        if (!$handledBy || $handledBy === '') {
            return null;
        }

        return $handledBy;
    }

    /**
     * ファネルチャート用データ（1回目→2回目→3回目の離脱分析）
     */
    public function getFunnelData(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        $total = $tracking->count();

        // 1回目の結果
        $visit1Completed = $tracking->whereNotIn('visit1_result', [self::RESULT_CANCELLED, self::RESULT_NO_SHOW])->count();
        $visit1Positive = $tracking->whereIn('visit1_result', [
            self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION
        ])->count();

        // 2回目に進んだ人（2回目の日付がある）
        $visit2Exists = $tracking->whereNotNull('visit2_date')->count();
        $visit2Positive = $tracking->whereIn('visit2_result', [
            self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION
        ])->count();

        // 3回目に進んだ人
        $visit3Exists = $tracking->whereNotNull('visit3_date')->count();
        $visit3Positive = $tracking->whereIn('visit3_result', [
            self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION
        ])->count();

        // サブスク契約者
        $subscriptions = $tracking->where('visit1_result', self::RESULT_SUBSCRIPTION)
            ->merge($tracking->where('visit2_result', self::RESULT_SUBSCRIPTION))
            ->merge($tracking->where('visit3_result', self::RESULT_SUBSCRIPTION))
            ->unique('customer_name')
            ->count();

        return [
            'labels' => ['新規予約', '1回目来店', '2回目来店', '3回目来店', 'サブスク契約'],
            'values' => [$total, $visit1Completed, $visit2Exists, $visit3Exists, $subscriptions],
            'colors' => ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EF4444'],
            'percentages' => [
                100,
                $total > 0 ? round($visit1Completed / $total * 100, 1) : 0,
                $total > 0 ? round($visit2Exists / $total * 100, 1) : 0,
                $total > 0 ? round($visit3Exists / $total * 100, 1) : 0,
                $total > 0 ? round($subscriptions / $total * 100, 1) : 0,
            ],
        ];
    }

    /**
     * 結果別円グラフ用データ
     */
    public function getResultPieData(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        int $visitNumber = 1
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);
        $resultKey = "visit{$visitNumber}_result";

        $results = [
            self::RESULT_SUBSCRIPTION => ['label' => 'サブスク', 'color' => '#10B981'],
            self::RESULT_TICKET => ['label' => '回数券', 'color' => '#3B82F6'],
            self::RESULT_NEXT_RESERVATION => ['label' => '次回予約', 'color' => '#F59E0B'],
            self::RESULT_NO_RESERVATION => ['label' => '予約なし', 'color' => '#6B7280'],
            self::RESULT_CANCELLED => ['label' => 'キャンセル', 'color' => '#EF4444'],
            self::RESULT_NO_SHOW => ['label' => '飛び', 'color' => '#DC2626'],
        ];

        $data = [];
        foreach ($results as $key => $meta) {
            $count = $tracking->where($resultKey, $key)->count();
            if ($count > 0) {
                $data[] = [
                    'label' => $meta['label'],
                    'value' => $count,
                    'color' => $meta['color'],
                ];
            }
        }

        return $data;
    }

    /**
     * 打率ランキング用データ（媒体別・対応者別）
     */
    public function getConversionRankingData(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        string $groupBy = 'source', // 'source' or 'handler'
        int $visitNumber = 1
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);
        $resultKey = "visit{$visitNumber}_result";
        $groupKey = $groupBy === 'handler' ? "visit{$visitNumber}_handler" : 'source';

        $groups = $tracking->pluck($groupKey)->unique()->filter()->values();

        $data = [];
        foreach ($groups as $group) {
            $groupData = $tracking->where($groupKey, $group);
            $total = $groupData->count();
            $positive = $groupData->whereIn($resultKey, [
                self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION
            ])->count();

            if ($total >= 3) { // 最低3件以上
                $data[] = [
                    'label' => $group,
                    'total' => $total,
                    'positive' => $positive,
                    'rate' => $total > 0 ? round($positive / $total * 100, 1) : 0,
                ];
            }
        }

        // 打率でソート（降順）
        usort($data, fn($a, $b) => $b['rate'] <=> $a['rate']);

        return $data;
    }

    /**
     * 月別トレンド用データ
     */
    public function getMonthlyTrendData(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);

        $months = $tracking->pluck('year_month')->unique()->sort()->values();

        $newCustomers = [];
        $subscriptions = [];
        $conversionRates = [];

        foreach ($months as $month) {
            $monthData = $tracking->where('year_month', $month);
            $total = $monthData->count();
            $subCount = $monthData->where('visit1_result', self::RESULT_SUBSCRIPTION)->count();
            $positive = $monthData->whereIn('visit1_result', [
                self::RESULT_SUBSCRIPTION, self::RESULT_TICKET, self::RESULT_NEXT_RESERVATION
            ])->count();

            $newCustomers[] = $total;
            $subscriptions[] = $subCount;
            $conversionRates[] = $total > 0 ? round($positive / $total * 100, 1) : 0;
        }

        return [
            'labels' => $months->toArray(),
            'datasets' => [
                [
                    'label' => '新規顧客数',
                    'data' => $newCustomers,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'サブスク契約数',
                    'data' => $subscriptions,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => '打率(%)',
                    'data' => $conversionRates,
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    /**
     * ヒートマップ用データ（対応者×結果）
     */
    public function getHeatmapData(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        int $visitNumber = 1
    ): array {
        $tracking = $this->getNewCustomerTracking($startDate, $endDate, $storeId);
        $handlerKey = "visit{$visitNumber}_handler";
        $resultKey = "visit{$visitNumber}_result";

        // 件数が多い順に対応者を取得（上位10名）
        $handlers = $tracking->pluck($handlerKey)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->keys()
            ->values();

        $results = [
            self::RESULT_SUBSCRIPTION,
            self::RESULT_TICKET,
            self::RESULT_NEXT_RESERVATION,
            self::RESULT_NO_RESERVATION,
            self::RESULT_CANCELLED,
            self::RESULT_NO_SHOW,
        ];

        $data = [];
        $maxValue = 0;

        foreach ($handlers as $hIndex => $handler) {
            foreach ($results as $rIndex => $result) {
                $count = $tracking->where($handlerKey, $handler)->where($resultKey, $result)->count();
                $data[] = [
                    'x' => $rIndex,
                    'y' => $hIndex,
                    'v' => $count,
                ];
                $maxValue = max($maxValue, $count);
            }
        }

        return [
            'handlers' => $handlers->toArray(),
            'results' => $results,
            'data' => $data,
            'maxValue' => $maxValue,
        ];
    }
}
