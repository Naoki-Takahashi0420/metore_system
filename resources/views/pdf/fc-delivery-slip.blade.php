<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>納品書 {{ $order->order_number }}</title>
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

        .order-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .order-info-left, .order-info-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .order-info-right {
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

        .order-details table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .order-details th, .order-details td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }

        .order-details th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .order-details .text-left {
            text-align: left;
        }

        .order-details .text-right {
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

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        .notes {
            clear: both;
            margin-top: 40px;
            padding: 10px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
        }

        .stamp-area {
            clear: both;
            margin-top: 40px;
            text-align: right;
        }

        .stamp-box {
            display: inline-block;
            width: 80px;
            height: 80px;
            border: 1px solid #000;
            text-align: center;
            line-height: 80px;
            font-size: 10px;
            color: #999;
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <div class="header">
        <h1>納 品 書</h1>
    </div>

    <!-- 発注情報 -->
    <div class="order-info">
        <div class="order-info-left">
            <strong>発注番号:</strong> {{ $order->order_number }}<br>
            <strong>発注日:</strong> {{ $order->ordered_at ? $order->ordered_at->format('Y年m月d日') : '-' }}<br>
            <strong>発送日:</strong> {{ $order->shipped_at ? $order->shipped_at->format('Y年m月d日') : '-' }}
            @if($order->shipping_tracking_number)
                <br><strong>追跡番号:</strong> {{ $order->shipping_tracking_number }}
            @endif
        </div>
        <div class="order-info-right">
            <strong>納品書発行日:</strong> {{ now()->format('Y年m月d日') }}
        </div>
    </div>

    <!-- 納品先・納品元 -->
    <div class="billing-section">
        <div style="width: 48%; float: left;">
            <div class="section-title">納品先</div>
            <div class="company-info">
                <div class="company-name">{{ $order->fcStore->company_name ?? $order->fcStore->name }} 御中</div>
                @if($order->fcStore->company_contact_person)
                    {{ $order->fcStore->company_contact_person }} 様<br>
                @endif
                @if($order->fcStore->company_postal_code)
                    〒{{ $order->fcStore->company_postal_code }}<br>
                @elseif($order->fcStore->postal_code)
                    〒{{ $order->fcStore->postal_code }}<br>
                @endif
                @if($order->fcStore->company_address)
                    {{ $order->fcStore->company_address }}<br>
                @elseif($order->fcStore->address)
                    {{ $order->fcStore->address }}<br>
                @endif
                @if($order->fcStore->company_phone)
                    TEL: {{ $order->fcStore->company_phone }}<br>
                @elseif($order->fcStore->phone)
                    TEL: {{ $order->fcStore->phone }}<br>
                @endif
            </div>
        </div>

        <div style="width: 48%; float: right;">
            <div class="section-title">納品元</div>
            <div class="company-info">
                <div class="company-name">{{ $order->headquartersStore->company_name ?? $order->headquartersStore->name }}</div>
                @if($order->headquartersStore->company_postal_code)
                    〒{{ $order->headquartersStore->company_postal_code }}<br>
                @elseif($order->headquartersStore->postal_code)
                    〒{{ $order->headquartersStore->postal_code }}<br>
                @endif
                @if($order->headquartersStore->company_address)
                    {{ $order->headquartersStore->company_address }}<br>
                @elseif($order->headquartersStore->address)
                    {{ $order->headquartersStore->address }}<br>
                @endif
                @if($order->headquartersStore->company_phone)
                    TEL: {{ $order->headquartersStore->company_phone }}<br>
                @elseif($order->headquartersStore->phone)
                    TEL: {{ $order->headquartersStore->phone }}<br>
                @endif
            </div>
        </div>
        <div style="clear: both;"></div>
    </div>

    <!-- 納品明細 -->
    <div class="order-details">
        <div class="section-title">納品明細</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">No.</th>
                    <th style="width: 40%;">商品名</th>
                    <th style="width: 12%;">数量</th>
                    <th style="width: 15%;">単価</th>
                    <th style="width: 15%;">金額</th>
                    <th style="width: 10%;">税率</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="text-left">{{ $item->product_name }}</td>
                    <td>{{ number_format($item->quantity) }}</td>
                    <td class="text-right">¥{{ number_format($item->unit_price) }}</td>
                    <td class="text-right">¥{{ number_format($item->subtotal) }}</td>
                    <td>{{ $item->tax_rate ?? 10 }}%</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #666;">明細がありません</td>
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
                <td>¥{{ number_format($order->subtotal) }}</td>
            </tr>
            <tr>
                <th>消費税</th>
                <td>¥{{ number_format($order->tax_amount) }}</td>
            </tr>
            <tr class="total-row">
                <th>合計金額</th>
                <td>¥{{ number_format($order->total_amount) }}</td>
            </tr>
        </table>
    </div>

    <!-- 受領印欄 -->
    <div class="stamp-area">
        <div class="stamp-box">受領印</div>
    </div>

    <!-- 備考 -->
    @if($order->notes)
    <div class="notes">
        <div class="section-title">備考</div>
        {{ $order->notes }}
    </div>
    @endif

    <!-- フッター -->
    <div class="footer">
        上記の通り納品いたしました。ご確認ください。<br>
        生成日時: {{ now()->format('Y年m月d日 H:i') }}
    </div>
</body>
</html>
