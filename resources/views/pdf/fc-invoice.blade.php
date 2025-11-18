<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>請求書 {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 20mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.6;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 24px;
            margin: 0;
            padding: 10px 0;
            border-bottom: 3px solid #333;
        }

        .invoice-info {
            margin-bottom: 30px;
        }

        .invoice-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-info td {
            padding: 5px;
            border: 1px solid #ddd;
        }

        .invoice-info .label {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 30%;
        }

        .billing-info {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .billing-to, .billing-from {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 10px;
        }

        .billing-to {
            border: 2px solid #333;
            padding: 15px;
        }

        .billing-to h2 {
            font-size: 16px;
            margin: 0 0 10px 0;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
        }

        .billing-from {
            text-align: right;
            padding-right: 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background-color: #333;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }

        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .items-table .text-right {
            text-align: right;
        }

        .items-table .text-center {
            text-align: center;
        }

        .total-section {
            width: 50%;
            margin-left: auto;
            border-collapse: collapse;
        }

        .total-section td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .total-section .label {
            background-color: #f5f5f5;
            font-weight: bold;
            width: 50%;
        }

        .total-section .amount {
            text-align: right;
            width: 50%;
        }

        .total-section .grand-total .label,
        .total-section .grand-total .amount {
            background-color: #333;
            color: white;
            font-size: 16px;
            font-weight: bold;
            padding: 12px;
        }

        .notes {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }

        .notes h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>請　求　書</h1>
    </div>

    <div class="invoice-info">
        <table>
            <tr>
                <td class="label">請求書番号</td>
                <td>{{ $invoice->invoice_number }}</td>
                <td class="label">発行日</td>
                <td>{{ $invoice->issue_date ? $invoice->issue_date->format('Y年m月d日') : '未設定' }}</td>
            </tr>
            <tr>
                <td class="label">請求対象期間</td>
                <td colspan="3">
                    {{ $invoice->billing_period_start->format('Y年m月d日') }} 〜
                    {{ $invoice->billing_period_end->format('Y年m月d日') }}
                </td>
            </tr>
            <tr>
                <td class="label">支払期限</td>
                <td colspan="3" style="font-weight: bold; color: #d00;">
                    {{ $invoice->due_date ? $invoice->due_date->format('Y年m月d日') : '未設定' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="billing-info">
        <div class="billing-to">
            <h2>請求先</h2>
            <p style="font-size: 18px; font-weight: bold; margin: 10px 0;">
                {{ $invoice->fcStore->name }} 御中
            </p>
            @if($invoice->fcStore->address)
            <p style="margin: 5px 0;">
                〒 {{ $invoice->fcStore->postal_code }}<br>
                {{ $invoice->fcStore->address }}
            </p>
            @endif
            @if($invoice->fcStore->phone)
            <p style="margin: 5px 0;">
                TEL: {{ $invoice->fcStore->phone }}
            </p>
            @endif
        </div>

        <div class="billing-from">
            <p style="font-size: 16px; font-weight: bold; margin: 10px 0;">
                {{ $invoice->headquartersStore->name }}
            </p>
            @if($invoice->headquartersStore->address)
            <p style="margin: 5px 0; font-size: 11px;">
                〒 {{ $invoice->headquartersStore->postal_code }}<br>
                {{ $invoice->headquartersStore->address }}
            </p>
            @endif
            @if($invoice->headquartersStore->phone)
            <p style="margin: 5px 0; font-size: 11px;">
                TEL: {{ $invoice->headquartersStore->phone }}
            </p>
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 60%;">項目</th>
                <th class="text-right" style="width: 20%;">金額（税抜）</th>
                <th class="text-right" style="width: 20%;">金額（税込）</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $invoice->billing_period_start->format('Y年m月') }} ご利用分<br>
                    <span style="font-size: 10px; color: #666;">
                        期間: {{ $invoice->billing_period_start->format('Y/m/d') }} - {{ $invoice->billing_period_end->format('Y/m/d') }}
                    </span>
                </td>
                <td class="text-right">¥{{ number_format($invoice->subtotal) }}</td>
                <td class="text-right">¥{{ number_format($invoice->subtotal + $invoice->tax_amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="total-section">
        <tr>
            <td class="label">小計（税抜）</td>
            <td class="amount">¥{{ number_format($invoice->subtotal) }}</td>
        </tr>
        <tr>
            <td class="label">消費税（10%）</td>
            <td class="amount">¥{{ number_format($invoice->tax_amount) }}</td>
        </tr>
        <tr class="grand-total">
            <td class="label">合計金額（税込）</td>
            <td class="amount">¥{{ number_format($invoice->total_amount) }}</td>
        </tr>
        @if($invoice->paid_amount > 0)
        <tr>
            <td class="label">入金済み</td>
            <td class="amount" style="color: #060;">¥{{ number_format($invoice->paid_amount) }}</td>
        </tr>
        <tr>
            <td class="label">未払い残高</td>
            <td class="amount" style="color: #d00; font-weight: bold;">¥{{ number_format($invoice->outstanding_amount) }}</td>
        </tr>
        @endif
    </table>

    @if($invoice->notes)
    <div class="notes">
        <h3>備考</h3>
        <p style="white-space: pre-line; margin: 0;">{{ $invoice->notes }}</p>
    </div>
    @endif

    <div style="margin-top: 30px; padding: 15px; border: 2px solid #333; background-color: #f0f0f0;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px;">お振込先</h3>
        <p style="margin: 0; font-size: 11px; line-height: 1.8;">
            【銀行名】○○銀行 ○○支店<br>
            【口座種別】普通預金<br>
            【口座番号】1234567<br>
            【口座名義】カ）メノトレーニング<br>
            <br>
            ※振込手数料はご負担ください
        </p>
    </div>

    <div class="footer">
        <p>この請求書に関するお問い合わせは、上記連絡先までお願いいたします。</p>
    </div>
</body>
</html>
