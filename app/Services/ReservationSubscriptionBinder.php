<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\CustomerSubscription;
use App\Models\Menu;
use Illuminate\Support\Facades\Log;

/**
 * 予約とサブスクリプションの自動紐付けサービス
 *
 * すべての予約作成・変更経路で使用し、適切なcustomer_subscription_idを設定する
 */
class ReservationSubscriptionBinder
{
    /**
     * 予約データ配列にサブスクリプションIDを自動設定
     *
     * @param array $reservationData 予約データ配列
     * @param string $operation 'create' または 'update'
     * @return array customer_subscription_idが追加された予約データ
     */
    public function bind(array $reservationData, string $operation = 'create'): array
    {
        // 必須パラメータチェック
        if (!isset($reservationData['customer_id']) || !isset($reservationData['store_id'])) {
            Log::warning('ReservationSubscriptionBinder: customer_id または store_id が未設定', [
                'has_customer_id' => isset($reservationData['customer_id']),
                'has_store_id' => isset($reservationData['store_id'])
            ]);
            return $reservationData;
        }

        // 既にcustomer_subscription_idが設定されている場合は尊重（上書きしない）
        if (!empty($reservationData['customer_subscription_id'])) {
            Log::debug('ReservationSubscriptionBinder: 既にサブスクIDが設定されているためスキップ', [
                'customer_subscription_id' => $reservationData['customer_subscription_id']
            ]);
            return $reservationData;
        }

        // アクティブなサブスク契約を検索
        $subscription = $this->findActiveSubscription(
            $reservationData['customer_id'],
            $reservationData['store_id'],
            $reservationData['menu_id'] ?? null
        );

        if ($subscription) {
            // メニューがサブスク専用かチェック
            if (isset($reservationData['menu_id'])) {
                $menu = Menu::find($reservationData['menu_id']);

                // サブスクメニューの場合のみ自動設定
                if ($menu && $menu->is_subscription) {
                    $reservationData['customer_subscription_id'] = $subscription->id;

                    Log::debug('ReservationSubscriptionBinder: サブスクIDを自動設定', [
                        'operation' => $operation,
                        'customer_id' => $reservationData['customer_id'],
                        'store_id' => $reservationData['store_id'],
                        'menu_id' => $reservationData['menu_id'],
                        'subscription_id' => $subscription->id,
                        'subscription_plan' => $subscription->plan_name
                    ]);
                } else {
                    Log::debug('ReservationSubscriptionBinder: メニューがサブスク専用ではないためスキップ', [
                        'menu_id' => $reservationData['menu_id'],
                        'menu_name' => $menu->name ?? 'N/A',
                        'is_subscription' => $menu->is_subscription ?? false
                    ]);
                }
            }
        } else {
            Log::debug('ReservationSubscriptionBinder: アクティブなサブスク契約が見つからない', [
                'customer_id' => $reservationData['customer_id'],
                'store_id' => $reservationData['store_id'],
                'menu_id' => $reservationData['menu_id'] ?? 'N/A'
            ]);
        }

        return $reservationData;
    }

    /**
     * 予約モデルにサブスクリプションIDを自動設定（更新）
     *
     * @param Reservation $reservation 予約モデル
     * @param bool $force 既存のサブスクIDがある場合も強制的に再評価するか
     * @return bool サブスクIDが設定されたかどうか
     */
    public function bindModel(Reservation $reservation, bool $force = false): bool
    {
        // 既にcustomer_subscription_idが設定されている場合は尊重（forceフラグがない限り）
        if (!$force && $reservation->customer_subscription_id) {
            Log::debug('ReservationSubscriptionBinder: 既にサブスクIDが設定されているためスキップ', [
                'reservation_id' => $reservation->id,
                'customer_subscription_id' => $reservation->customer_subscription_id
            ]);
            return false;
        }

        // アクティブなサブスク契約を検索
        $subscription = $this->findActiveSubscription(
            $reservation->customer_id,
            $reservation->store_id,
            $reservation->menu_id
        );

        if (!$subscription) {
            Log::debug('ReservationSubscriptionBinder: アクティブなサブスク契約が見つからない', [
                'reservation_id' => $reservation->id,
                'customer_id' => $reservation->customer_id,
                'store_id' => $reservation->store_id,
                'menu_id' => $reservation->menu_id
            ]);

            // forceモードかつ既存のサブスクIDがある場合は外す
            if ($force && $reservation->customer_subscription_id) {
                $reservation->update(['customer_subscription_id' => null]);
                Log::warning('ReservationSubscriptionBinder: サブスクIDを削除（アクティブな契約なし）', [
                    'reservation_id' => $reservation->id,
                    'old_subscription_id' => $reservation->customer_subscription_id
                ]);
            }

            return false;
        }

        // メニューがサブスク専用かチェック
        $menu = $reservation->menu;
        if (!$menu || !$menu->is_subscription) {
            Log::debug('ReservationSubscriptionBinder: メニューがサブスク専用ではないためスキップ', [
                'reservation_id' => $reservation->id,
                'menu_id' => $reservation->menu_id,
                'menu_name' => $menu->name ?? 'N/A',
                'is_subscription' => $menu->is_subscription ?? false
            ]);

            // forceモードかつ既存のサブスクIDがある場合は外す
            if ($force && $reservation->customer_subscription_id) {
                $reservation->update(['customer_subscription_id' => null]);
                Log::warning('ReservationSubscriptionBinder: サブスクIDを削除（非サブスクメニュー）', [
                    'reservation_id' => $reservation->id,
                    'old_subscription_id' => $reservation->customer_subscription_id
                ]);
            }

            return false;
        }

        // サブスクIDを設定
        $reservation->update(['customer_subscription_id' => $subscription->id]);

        Log::debug('ReservationSubscriptionBinder: サブスクIDを設定（モデル更新）', [
            'reservation_id' => $reservation->id,
            'customer_id' => $reservation->customer_id,
            'store_id' => $reservation->store_id,
            'menu_id' => $reservation->menu_id,
            'subscription_id' => $subscription->id,
            'subscription_plan' => $subscription->plan_name,
            'force' => $force
        ]);

        return true;
    }

    /**
     * アクティブなサブスク契約を検索
     *
     * 検索条件：
     * - 顧客IDと店舗IDが一致
     * - isActive() がtrue（status=active、休止中でない、決済失敗していない、期間内）
     * - menu_idが指定されている場合は優先的に一致するものを返す
     *
     * @param int $customerId 顧客ID
     * @param int $storeId 店舗ID
     * @param int|null $menuId メニューID（オプション）
     * @return CustomerSubscription|null アクティブなサブスク契約
     */
    public function findActiveSubscription(int $customerId, int $storeId, ?int $menuId = null): ?CustomerSubscription
    {
        // 顧客IDと店舗IDで基本検索（activeStrictスコープで厳密な条件を適用）
        $query = CustomerSubscription::where('customer_id', $customerId)
            ->where('store_id', $storeId)
            ->activeStrict(); // スコープを使用して条件を一元管理

        // 複数契約がある場合のログ記録用に総数を取得
        $totalCount = (clone $query)->count();

        // menu_idが指定されている場合、優先的に一致するものを取得
        if ($menuId) {
            // メニューIDが一致する契約を優先
            $subscription = (clone $query)
                ->where('menu_id', $menuId)
                ->first();

            if ($subscription) {
                // 複数契約がある場合は選択理由を明示
                if ($totalCount > 1) {
                    Log::debug('ReservationSubscriptionBinder: 複数のアクティブ契約からmenu_id一致で選択', [
                        'customer_id' => $customerId,
                        'store_id' => $storeId,
                        'total_active_subscriptions' => $totalCount,
                        'selected_subscription_id' => $subscription->id,
                        'selected_menu_id' => $subscription->menu_id,
                        'reason' => 'menu_id一致を優先'
                    ]);
                }
                return $subscription;
            }

            // メニューIDが一致しない場合は、任意のアクティブな契約を返す
            $subscription = $query->first();

            if ($subscription && $totalCount > 1) {
                Log::debug('ReservationSubscriptionBinder: 複数のアクティブ契約から最初の1件を選択', [
                    'customer_id' => $customerId,
                    'store_id' => $storeId,
                    'total_active_subscriptions' => $totalCount,
                    'selected_subscription_id' => $subscription->id,
                    'selected_menu_id' => $subscription->menu_id,
                    'requested_menu_id' => $menuId,
                    'reason' => 'menu_id不一致のため最初の契約を使用'
                ]);
            }

            return $subscription;
        }

        // menu_idが指定されていない場合は、最初のアクティブな契約を返す
        $subscription = $query->first();

        if ($subscription && $totalCount > 1) {
            Log::debug('ReservationSubscriptionBinder: 複数のアクティブ契約から最初の1件を選択', [
                'customer_id' => $customerId,
                'store_id' => $storeId,
                'total_active_subscriptions' => $totalCount,
                'selected_subscription_id' => $subscription->id,
                'selected_menu_id' => $subscription->menu_id,
                'reason' => 'menu_id未指定のため最初の契約を使用'
            ]);
        }

        return $subscription;
    }
}
