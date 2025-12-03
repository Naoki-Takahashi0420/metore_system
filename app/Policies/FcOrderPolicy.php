<?php

namespace App\Policies;

use App\Models\FcOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FcOrderPolicy
{
    /**
     * 一覧表示権限
     */
    public function viewAny(User $user): bool
    {
        // super_admin、本部、FC加盟店のユーザーは一覧表示可能
        return $user->hasRole('super_admin') || 
               ($user->store && in_array($user->store->fc_type, ['headquarters', 'fc_store']));
    }

    /**
     * 個別表示権限
     */
    public function view(User $user, FcOrder $fcOrder): bool
    {
        // super_adminは全て閲覧可能
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // 本部のユーザーは全て閲覧可能
        if ($user->store && $user->store->fc_type === 'headquarters') {
            return true;
        }

        // FC加盟店のユーザーは自店舗の注文のみ閲覧可能
        if ($user->store && $user->store->fc_type === 'fc_store') {
            return $fcOrder->fc_store_id === $user->store_id;
        }

        return false;
    }

    /**
     * 作成権限
     */
    public function create(User $user): bool
    {
        // FC加盟店のユーザーと本部、super_adminは作成可能
        return $user->hasRole('super_admin') || 
               ($user->store && in_array($user->store->fc_type, ['headquarters', 'fc_store']));
    }

    /**
     * 編集権限
     */
    public function update(User $user, FcOrder $fcOrder): bool
    {
        // FC加盟店：自店舗の下書き状態のみ編集可能
        if ($user->store && $user->store->fc_type === 'fc_store') {
            return $fcOrder->fc_store_id === $user->store_id && $fcOrder->isEditable();
        }

        // 本部：全ての注文を編集可能（ステータス管理）
        if ($user->store && $user->store->fc_type === 'headquarters') {
            return true;
        }

        // super_admin：全て編集可能
        return $user->hasRole('super_admin');
    }

    /**
     * 削除権限
     */
    public function delete(User $user, FcOrder $fcOrder): bool
    {
        // FC加盟店：自店舗の下書き状態のみ削除可能
        if ($user->store && $user->store->fc_type === 'fc_store') {
            return $fcOrder->fc_store_id === $user->store_id && $fcOrder->isEditable();
        }

        // 本部とsuper_adminは削除可能
        return $user->hasRole('super_admin') || 
               ($user->store && $user->store->fc_type === 'headquarters');
    }

    /**
     * 発送処理権限
     */
    public function ship(User $user, FcOrder $fcOrder): bool
    {
        // 本部のユーザーまたはsuper_adminのみ発送処理可能
        $canManage = $user->hasRole('super_admin') || 
                    ($user->store && $user->store->fc_type === 'headquarters');

        // 発注済み状態の場合のみ発送可能
        return $canManage && $fcOrder->isShippable();
    }

    /**
     * 納品完了権限
     */
    public function deliver(User $user, FcOrder $fcOrder): bool
    {
        // 本部のユーザーまたはsuper_adminのみ納品完了処理可能
        $canManage = $user->hasRole('super_admin') || 
                    ($user->store && $user->store->fc_type === 'headquarters');

        // 発送済み状態の場合のみ納品完了可能
        return $canManage && $fcOrder->isDeliverable();
    }

    /**
     * キャンセル権限
     */
    public function cancel(User $user, FcOrder $fcOrder): bool
    {
        // FC加盟店：自店舗の注文でキャンセル可能状態
        if ($user->store && $user->store->fc_type === 'fc_store') {
            return $fcOrder->fc_store_id === $user->store_id && $fcOrder->isCancellable();
        }

        // 本部とsuper_adminはキャンセル可能
        $canManage = $user->hasRole('super_admin') || 
                    ($user->store && $user->store->fc_type === 'headquarters');

        return $canManage && $fcOrder->isCancellable();
    }

    /**
     * 部分発送権限
     */
    public function partialShip(User $user, FcOrder $fcOrder): bool
    {
        // 発送権限と同じ
        return $this->ship($user, $fcOrder);
    }

    /**
     * 発送ボタン表示権限（FC店舗側では非表示）
     */
    public function showShippingButton(User $user, FcOrder $fcOrder): bool
    {
        // FC加盟店のユーザーには発送ボタンを表示しない
        if ($user->store && $user->store->fc_type === 'fc_store') {
            return false;
        }

        // 本部のユーザーとsuper_adminにのみ発送ボタンを表示
        return $user->hasRole('super_admin') || 
               ($user->store && $user->store->fc_type === 'headquarters');
    }
}