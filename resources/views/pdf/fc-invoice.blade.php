<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>請求書 {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: bold;
        }

        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .invoice-info-left, .invoice-info-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .invoice-info-right {
            text-align: right;
        }

        .billing-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 3px;
        }

        .company-info {
            margin-bottom: 15px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .invoice-details table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .invoice-details th, .invoice-details td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }

        .invoice-details th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .invoice-details .text-left {
            text-align: left;
        }

        .invoice-details .text-right {
            text-align: right;
        }

        .summary-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table th, .summary-table td {
            border: 1px solid #000;
            padding: 8px;
        }

        .summary-table th {
            background-color: #f0f0f0;
            text-align: left;
            width: 150px;
        }

        .summary-table td {
            text-align: right;
        }

        .total-row {
            background-color: #e0e0e0;
            font-weight: bold;
            font-size: 14px;
        }

        .bank-info {
            clear: both;
            margin-top: 40px;
            border: 1px solid #000;
            padding: 15px;
        }

        .bank-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        .notes {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <div class="header">
        <h1>請求書</h1>
    </div>

    <!-- 請求書情報 -->
    <div class="invoice-info">
        <div class="invoice-info-left">
            <strong>請求書番号:</strong> {{ $invoice->invoice_number }}<br>
            <strong>発行日:</strong> {{ $invoice->issue_date->format('Y年m月d日') }}<br>
            <strong>支払期限:</strong> {{ $invoice->due_date->format('Y年m月d日') }}
        </div>
        <div class="invoice-info-right">
            <strong>請求期間:</strong> {{ $invoice->billing_period_start->format('Y年m月d日') }} ～ {{ $invoice->billing_period_end->format('Y年m月d日') }}
        </div>
    </div>

    <!-- 請求先・請求元 -->
    <div class="billing-section">
        <div style="width: 48%; float: left;">
            <div class="section-title">請求先</div>
            <div class="company-info">
                <div class="company-name">{{ $invoice->fcStore->name }}</div>
                @if($invoice->fcStore->address)
                    {{ $invoice->fcStore->address }}<br>
                @endif
                @if($invoice->fcStore->phone)
                    TEL: {{ $invoice->fcStore->phone }}<br>
                @endif
            </div>
        </div>

        <div style="width: 48%; float: right;">
            <div class="section-title">請求元</div>
            <div class="company-info">
                <div class="company-name">{{ $invoice->headquartersStore->name }}</div>
                @if($invoice->headquartersStore->address)
                    {{ $invoice->headquartersStore->address }}<br>
                @endif
                @if($invoice->headquartersStore->phone)
                    TEL: {{ $invoice->headquartersStore->phone }}<br>
                @endif
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- 請求明細 -->
    <div class="invoice-details">
        <div class="section-title">請求明細</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">項目</th>
                    <th style="width: 35%;">商品・サービス名</th>
                    <th style="width: 8%;">数量</th>
                    <th style="width: 12%;">単価</th>
                    <th style="width: 12%;">小計</th>
                    <th style="width: 8%;">税率</th>
                    <th style="width: 15%;">備考</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $item)
                <tr>
                    <td>{{ $item->getTypeLabel() }}</td>
                    <td class="text-left">{{ $item->description }}</td>
                    <td>{{ number_format($item->quantity, $item->quantity == (int)$item->quantity ? 0 : 2) }}</td>
                    <td class="text-right">¥{{ number_format($item->unit_price) }}</td>
                    <td class="text-right">¥{{ number_format($item->subtotal) }}</td>
                    <td>{{ $item->tax_rate }}%</td>
                    <td class="text-left" style="font-size: 10px;">{{ $item->notes }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #666;">明細がありません</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- 合計金額 -->
    <div class="summary-section">
        <table class="summary-table">
            <tr>
                <th>小計（税抜）</th>
                <td>¥{{ number_format($invoice->subtotal) }}</td>
            </tr>
            <tr>
                <th>消費税</th>
                <td>¥{{ number_format($invoice->tax_amount) }}</td>
            </tr>
            <tr class="total-row">
                <th>合計金額</th>
                <td>¥{{ number_format($invoice->total_amount) }}</td>
            </tr>
            @if($invoice->paid_amount > 0)
            <tr>
                <th>入金済み</th>
                <td>¥{{ number_format($invoice->paid_amount) }}</td>
            </tr>
            <tr>
                <th>未払い残高</th>
                <td>¥{{ number_format($invoice->outstanding_amount) }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- 振込先情報 -->
    <div class="bank-info">
        <div class="bank-title">お振込先</div>
        @if($invoice->headquartersStore->bank_name)
            <strong>銀行名:</strong> {{ $invoice->headquartersStore->bank_name }}<br>
        @endif
        @if($invoice->headquartersStore->bank_branch)
            <strong>支店名:</strong> {{ $invoice->headquartersStore->bank_branch }}<br>
        @endif
        @if($invoice->headquartersStore->bank_account_type)
            <strong>預金種別:</strong> {{ $invoice->headquartersStore->bank_account_type }}<br>
        @endif
        @if($invoice->headquartersStore->bank_account_number)
            <strong>口座番号:</strong> {{ $invoice->headquartersStore->bank_account_number }}<br>
        @endif
        @if($invoice->headquartersStore->bank_account_name)
            <strong>口座名義:</strong> {{ $invoice->headquartersStore->bank_account_name }}<br>
        @endif
        
        @if(!$invoice->headquartersStore->bank_name)
            <div style="color: #666; font-style: italic;">
                振込先情報が設定されていません。管理画面の店舗設定で振込先を設定してください。
            </div>
        @endif
    </div>

    <!-- 備考 -->
    @if($invoice->notes)
    <div class="notes">
        <div class="section-title">備考</div>
        {{ $invoice->notes }}
    </div>
    @endif

    <!-- フッター -->
    <div class="footer">
        生成日時: {{ isset($generatedAt) ? $generatedAt->format('Y年m月d日 H:i') : now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>