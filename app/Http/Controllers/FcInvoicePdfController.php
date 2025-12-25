<?php

namespace App\Http\Controllers;

use App\Models\FcInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class FcInvoicePdfController extends Controller
{
    /**
     * PDF表示/ダウンロード
     */
    public function show(FcInvoice $fcInvoice)
    {
        // 権限チェック
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        // super_admin は全て閲覧可能
        if ($user->hasRole('super_admin')) {
            // OK
        }
        // 本部店舗のユーザーは全て閲覧可能
        elseif ($user->store && $user->store->isHeadquarters()) {
            // OK
        }
        // FC店舗のユーザーは自店舗のものだけ
        elseif ($user->store && $user->store->isFcStore()) {
            if ($fcInvoice->fc_store_id !== $user->store_id) {
                abort(403);
            }
        }
        else {
            abort(403);
        }

        // itemsをeager load
        $fcInvoice->load(['items', 'fcStore', 'headquartersStore']);

        // PDF生成
        $pdf = Pdf::loadView('pdf.fc-invoice', [
            'invoice' => $fcInvoice,
        ]);

        // A4サイズ、縦向きに設定
        $pdf->setPaper('A4', 'portrait');

        // ファイル名
        $filename = "請求書_{$fcInvoice->invoice_number}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * PDFダウンロード
     */
    public function download(FcInvoice $fcInvoice)
    {
        // 権限チェック（同じロジック）
        $user = auth()->user();
        if (!$user) {
            abort(401);
        }

        if ($user->hasRole('super_admin')) {
            // OK
        }
        elseif ($user->store && $user->store->isHeadquarters()) {
            // OK
        }
        elseif ($user->store && $user->store->isFcStore()) {
            if ($fcInvoice->fc_store_id !== $user->store_id) {
                abort(403);
            }
        }
        else {
            abort(403);
        }

        // itemsをeager load
        $fcInvoice->load(['items', 'fcStore', 'headquartersStore']);

        // PDF生成
        $pdf = Pdf::loadView('pdf.fc-invoice', [
            'invoice' => $fcInvoice,
        ]);

        $pdf->setPaper('A4', 'portrait');

        // ファイル名
        $filename = "請求書_{$fcInvoice->invoice_number}.pdf";

        return $pdf->download($filename);
    }
}