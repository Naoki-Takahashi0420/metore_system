<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Customer;
use App\Helpers\PhoneHelper;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 一時的にUNIQUE制約を無効化するため、トランザクション内で処理
        DB::transaction(function () {
            // 全ての顧客を取得
            $customers = Customer::whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderBy('created_at', 'asc') // 古い順
                ->get();

            $normalizedPhones = [];
            $mergedCount = 0;
            $normalizedCount = 0;
            $deletedIds = [];

            foreach ($customers as $customer) {
                // すでに削除されたレコードはスキップ
                if (in_array($customer->id, $deletedIds)) {
                    continue;
                }

                $normalizedPhone = PhoneHelper::normalize($customer->phone);

                // 正規化した電話番号が既に存在する場合
                if (isset($normalizedPhones[$normalizedPhone])) {
                    $existingCustomer = $normalizedPhones[$normalizedPhone];

                    // 予約データを既存の顧客に移行
                    DB::table('reservations')
                        ->where('customer_id', $customer->id)
                        ->update(['customer_id' => $existingCustomer->id]);

                    // サブスクデータを既存の顧客に移行
                    DB::table('customer_subscriptions')
                        ->where('customer_id', $customer->id)
                        ->update(['customer_id' => $existingCustomer->id]);

                    // カルテデータを既存の顧客に移行
                    DB::table('medical_records')
                        ->where('customer_id', $customer->id)
                        ->update(['customer_id' => $existingCustomer->id]);

                    // 回数券データを既存の顧客に移行
                    DB::table('customer_tickets')
                        ->where('customer_id', $customer->id)
                        ->update(['customer_id' => $existingCustomer->id]);

                    // 重複レコードを削除
                    $customer->delete();
                    $deletedIds[] = $customer->id;
                    $mergedCount++;

                    \Log::info('顧客レコードをマージして削除', [
                        'deleted_id' => $customer->id,
                        'merged_into_id' => $existingCustomer->id,
                        'phone' => $normalizedPhone,
                        'name' => $customer->last_name . ' ' . $customer->first_name
                    ]);
                } else {
                    // 正規化した電話番号で更新
                    if ($normalizedPhone !== $customer->phone) {
                        $customer->update(['phone' => $normalizedPhone]);
                        $normalizedCount++;
                    }

                    // 正規化後の電話番号を記録
                    $normalizedPhones[$normalizedPhone] = $customer;
                }
            }

            \Log::info('顧客電話番号正規化完了', [
                'total_customers' => $customers->count(),
                'normalized_count' => $normalizedCount,
                'merged_count' => $mergedCount,
                'deleted_count' => count($deletedIds)
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 正規化前の状態に戻すことはできないため、何もしない
        \Log::warning('顧客電話番号の正規化をロールバックすることはできません');
    }
};
