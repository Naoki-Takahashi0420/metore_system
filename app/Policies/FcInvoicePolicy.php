<?php

namespace App\Policies;

use App\Models\FcInvoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FcInvoicePolicy
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
    public function view(User $user, FcInvoice $fcInvoice): bool
    {
        // super_adminは全て閲覧可能
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // 本部のユーザーは全て閲覧可能
        if ($user->store && $user->store->fc_type === 'headquarters') {
            return true;
        }

        // FC加盟店のユーザーは自店舗の請求書のみ閲覧可能
        if ($user->store && $user->store->fc_type === 'fc_store') {
            return $fcInvoice->fc_store_id === $user->store_id;
        }

        return false;
    }

    /**
     * 作成権限
     */
    public function create(User $user): bool
    {
        // 本部のユーザーまたはsuper_adminのみ作成可能
        return $user->hasRole('super_admin') || 
               ($user->store && $user->store->fc_type === 'headquarters');
    }

    /**
     * 編集権限
     */
    public function update(User $user, FcInvoice $fcInvoice): bool
    {
        // 本部のユーザーまたはsuper_adminのみ編集可能
        $canManage = $user->hasRole('super_admin') || 
                    ($user->store && $user->store->fc_type === 'headquarters');

        // 下書き状態の場合のみ編集可能
        return $canManage && $fcInvoice->status === FcInvoice::STATUS_DRAFT;
    }

    /**
     * 削除権限
     */
    public function delete(User $user, FcInvoice $fcInvoice): bool
    {
        // 本部のユーザーまたはsuper_adminのみ削除可能
        $canManage = $user->hasRole('super_admin') || 
                    ($user->store && $user->store->fc_type === 'headquarters');

        // 下書き状態の場合のみ削除可能
        return $canManage && $fcInvoice->status === FcInvoice::STATUS_DRAFT;
    }

    /**
     * 請求書発行権限
     */
    public function issue(User $user, FcInvoice $fcInvoice): bool
    {
        // 本部のユーザーまたはsuper_adminのみ発行可能
        $canManage = $user->hasRole('super_admin') || 
                    ($user->store && $user->store->fc_type === 'headquarters');

        // 下書き状態の場合のみ発行可能
        return $canManage && $fcInvoice->status === FcInvoice::STATUS_DRAFT;
    }

    /**
     * 入金記録権限
     */
    public function recordPayment(User $user, FcInvoice $fcInvoice): bool
    {
        // 本部のユーザーまたはsuper_adminのみ入金記録可能
        $canManage = $user->hasRole('super_admin') || 
                    ($user->store && $user->store->fc_type === 'headquarters');

        // 発行済みまたは送付済みの場合のみ入金記録可能
        $statusOk = in_array($fcInvoice->status, [FcInvoice::STATUS_ISSUED, FcInvoice::STATUS_SENT]);

        return $canManage && $statusOk;
    }

    /**
     * PDF表示権限
     */
    public function downloadPdf(User $user, FcInvoice $fcInvoice): bool
    {
        // 表示権限と同じ
        return $this->view($user, $fcInvoice);
    }

    /**
     * 明細編集権限
     */
    public function editItems(User $user, FcInvoice $fcInvoice): bool
    {
        // 編集権限と同じ
        return $this->update($user, $fcInvoice);
    }
}