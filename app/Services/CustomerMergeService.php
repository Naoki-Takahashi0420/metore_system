<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\MedicalRecord;
use App\Models\CustomerSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerMergeService
{
    /**
     * 類似顧客を検索（同姓同名）
     */
    public function findSimilarCustomers(Customer $customer): array
    {
        return Customer::where('last_name', $customer->last_name)
            ->where('first_name', $customer->first_name)
            ->where('id', '!=', $customer->id)
            ->whereNull('deleted_at')
            ->with(['reservations' => function($query) {
                $query->latest('reservation_date')->limit(1);
            }])
            ->withCount('reservations')
            ->get()
            ->map(function($similarCustomer) {
                return [
                    'id' => $similarCustomer->id,
                    'name' => $similarCustomer->last_name . $similarCustomer->first_name,
                    'phone' => $similarCustomer->phone ?: '(電話番号なし)',
                    'email' => $similarCustomer->email ?: '(メールなし)',
                    'reservations_count' => $similarCustomer->reservations_count,
                    'last_visit' => $similarCustomer->reservations->first()
                        ? $similarCustomer->reservations->first()->reservation_date->format('Y/m/d')
                        : '来店なし',
                    'completeness_score' => $this->calculateCompleteness($similarCustomer)
                ];
            })
            ->toArray();
    }

    /**
     * 2つの顧客を統合
     */
    public function merge(Customer $customerA, Customer $customerB): Customer
    {
        return DB::transaction(function () use ($customerA, $customerB) {
            // どちらをベースにするか決定
            $base = $this->determineBaseCustomer($customerA, $customerB);
            $source = ($base->id === $customerA->id) ? $customerB : $customerA;

            Log::info('顧客統合開始', [
                'base_id' => $base->id,
                'base_name' => $base->last_name . $base->first_name,
                'source_id' => $source->id,
                'source_name' => $source->last_name . $source->first_name,
            ]);

            // データをマージ
            $this->mergeCustomerData($base, $source);

            // 関連データを移行
            $this->transferRelatedData($source->id, $base->id);

            // 統合元を削除
            $source->delete();

            Log::info('顧客統合完了', [
                'merged_customer_id' => $base->id,
                'deleted_customer_id' => $source->id
            ]);

            return $base->fresh();
        });
    }

    /**
     * 統合プレビューデータを生成
     */
    public function getPreviewData(Customer $customerA, Customer $customerB): array
    {
        $base = $this->determineBaseCustomer($customerA, $customerB);
        $source = ($base->id === $customerA->id) ? $customerB : $customerA;

        return [
            'base' => [
                'id' => $base->id,
                'name' => $base->last_name . $base->first_name,
                'phone' => $base->phone,
                'email' => $base->email,
                'address' => $this->formatAddress($base),
                'reservations_count' => $base->reservations()->count(),
                'last_visit' => $this->getLastVisit($base),
            ],
            'source' => [
                'id' => $source->id,
                'name' => $source->last_name . $source->first_name,
                'phone' => $source->phone,
                'email' => $source->email,
                'address' => $this->formatAddress($source),
                'reservations_count' => $source->reservations()->count(),
                'last_visit' => $this->getLastVisit($source),
            ],
            'merged' => [
                'phone' => $base->phone ?: $source->phone,
                'email' => $base->email ?: $source->email,
                'address' => $this->formatAddress($base) ?: $this->formatAddress($source),
                'total_reservations' => $base->reservations()->count() + $source->reservations()->count(),
            ]
        ];
    }

    /**
     * ベースとなる顧客を決定（情報が充実している方）
     */
    private function determineBaseCustomer(Customer $a, Customer $b): Customer
    {
        // 予約がある方を優先
        $aReservations = $a->reservations()->count();
        $bReservations = $b->reservations()->count();

        if ($aReservations > 0 && $bReservations === 0) {
            return $a;
        }
        if ($bReservations > 0 && $aReservations === 0) {
            return $b;
        }

        // 情報の充実度で判定
        $scoreA = $this->calculateCompleteness($a);
        $scoreB = $this->calculateCompleteness($b);

        return $scoreA >= $scoreB ? $a : $b;
    }

    /**
     * 顧客情報の充実度を計算
     */
    private function calculateCompleteness(Customer $customer): int
    {
        $score = 0;

        if ($customer->phone) $score += 30;
        if ($customer->email) $score += 20;
        if ($customer->birth_date) $score += 15;
        if ($customer->address) $score += 15;
        if ($customer->last_name_kana && $customer->first_name_kana) $score += 10;
        if ($customer->gender) $score += 5;
        if ($customer->notes) $score += 5;

        // 予約数ボーナス
        $score += $customer->reservations()->count() * 10;

        return $score;
    }

    /**
     * 顧客データをマージ（空欄を埋める方式）
     */
    private function mergeCustomerData(Customer $base, Customer $source): void
    {
        // 空欄を埋める方式でマージ
        $base->phone = $base->phone ?: $source->phone;
        $base->email = $base->email ?: $source->email;
        $base->last_name_kana = $base->last_name_kana ?: $source->last_name_kana;
        $base->first_name_kana = $base->first_name_kana ?: $source->first_name_kana;
        $base->gender = $base->gender ?: $source->gender;
        $base->birth_date = $base->birth_date ?: $source->birth_date;
        $base->postal_code = $base->postal_code ?: $source->postal_code;
        $base->prefecture = $base->prefecture ?: $source->prefecture;
        $base->city = $base->city ?: $source->city;
        $base->address = $base->address ?: $source->address;
        $base->building = $base->building ?: $source->building;
        $base->customer_number = $base->customer_number ?: $source->customer_number;

        // 備考は両方を結合
        if ($source->notes && trim($source->notes) !== trim($base->notes ?: '')) {
            $baseNotes = trim($base->notes ?: '');
            $sourceNotes = trim($source->notes);

            if ($baseNotes) {
                $base->notes = $baseNotes . "\n\n--- 統合情報 ---\n" . $sourceNotes;
            } else {
                $base->notes = $sourceNotes;
            }
        }

        $base->save();
    }

    /**
     * 関連データを移行
     */
    private function transferRelatedData(int $sourceId, int $baseId): void
    {
        // 予約を移行
        $reservationCount = Reservation::where('customer_id', $sourceId)->count();
        if ($reservationCount > 0) {
            Reservation::where('customer_id', $sourceId)
                ->update(['customer_id' => $baseId]);
            Log::info("予約{$reservationCount}件を移行", ['from' => $sourceId, 'to' => $baseId]);
        }

        // カルテを移行
        $medicalRecordCount = MedicalRecord::where('customer_id', $sourceId)->count();
        if ($medicalRecordCount > 0) {
            MedicalRecord::where('customer_id', $sourceId)
                ->update(['customer_id' => $baseId]);
            Log::info("カルテ{$medicalRecordCount}件を移行", ['from' => $sourceId, 'to' => $baseId]);
        }

        // サブスクリプションを移行（存在する場合）
        if (class_exists(CustomerSubscription::class)) {
            $subscriptionCount = CustomerSubscription::where('customer_id', $sourceId)->count();
            if ($subscriptionCount > 0) {
                CustomerSubscription::where('customer_id', $sourceId)
                    ->update(['customer_id' => $baseId]);
                Log::info("サブスク{$subscriptionCount}件を移行", ['from' => $sourceId, 'to' => $baseId]);
            }
        }
    }

    /**
     * 住所をフォーマット
     */
    private function formatAddress(Customer $customer): ?string
    {
        if (!$customer->prefecture && !$customer->city && !$customer->address) {
            return null;
        }

        return trim(
            ($customer->prefecture ?: '') .
            ($customer->city ?: '') .
            ($customer->address ?: '') .
            ($customer->building ? ' ' . $customer->building : '')
        );
    }

    /**
     * 最後の来店日を取得
     */
    private function getLastVisit(Customer $customer): ?string
    {
        $lastReservation = $customer->reservations()
            ->latest('reservation_date')
            ->first();

        return $lastReservation
            ? $lastReservation->reservation_date->format('Y/m/d')
            : null;
    }
}