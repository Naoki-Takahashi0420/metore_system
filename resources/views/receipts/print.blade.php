<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>レシート - {{ $sale->receipt_number }}</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            width: 80mm;
            margin: 0 auto;
            padding: 10mm 5mm;
        }
        
        .receipt {
            background: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        
        .store-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .store-info {
            font-size: 10px;
            color: #666;
        }
        
        .receipt-title {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin: 10px 0;
        }
        
        .info-section {
            margin: 10px 0;
            font-size: 11px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        
        .items-section {
            margin: 15px 0;
            border-top: 1px dashed #666;
            border-bottom: 1px dashed #666;
            padding: 10px 0;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
        
        .item-name {
            flex: 1;
        }
        
        .item-qty {
            width: 30px;
            text-align: center;
        }
        
        .item-price {
            width: 70px;
            text-align: right;
        }
        
        .totals-section {
            margin: 15px 0;
            padding-top: 10px;
            border-top: 2px solid #000;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 11px;
        }
        
        .total-row.grand-total {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #000;
        }
        
        .payment-section {
            margin: 15px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px dashed #666;
            font-size: 10px;
            color: #666;
        }
        
        .barcode {
            text-align: center;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 10px;
        }
        
        .print-button {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background: #007bff;
            color: white;
            text-align: center;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
        
        @media screen {
            body {
                background: #f0f0f0;
                padding: 20px;
            }
            
            .receipt {
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
                padding: 20px;
                max-width: 400px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">レシートを印刷</button>
    
    <div class="receipt">
        <div class="header">
            <div class="store-name">{{ $store->name }}</div>
            <div class="store-info">
                {{ $store->address }}<br>
                TEL: {{ $store->phone }}
            </div>
        </div>
        
        <div class="receipt-title">領収書</div>
        
        <div class="info-section">
            <div class="info-row">
                <span>レシート番号:</span>
                <span>{{ $sale->receipt_number }}</span>
            </div>
            <div class="info-row">
                <span>日付:</span>
                <span>{{ $sale->sale_date->format('Y年m月d日 H:i') }}</span>
            </div>
            <div class="info-row">
                <span>お客様:</span>
                <span>{{ $customer->name }} 様</span>
            </div>
            @if($staff)
            <div class="info-row">
                <span>担当:</span>
                <span>{{ $staff->name }}</span>
            </div>
            @endif
        </div>
        
        <div class="items-section">
            @foreach($items as $item)
            <div class="item-row">
                <span class="item-name">{{ $item['name'] }}</span>
                <span class="item-qty">×{{ $item['quantity'] }}</span>
                <span class="item-price">¥{{ number_format($item['price'] * $item['quantity']) }}</span>
            </div>
            
            @if(isset($item['options']) && count($item['options']) > 0)
                @foreach($item['options'] as $option)
                <div class="item-row" style="padding-left: 10px; font-size: 10px;">
                    <span class="item-name">└ {{ $option['name'] }}</span>
                    <span class="item-qty">×{{ $option['quantity'] }}</span>
                    <span class="item-price">¥{{ number_format($option['price'] * $option['quantity']) }}</span>
                </div>
                @endforeach
            @endif
            @endforeach
        </div>
        
        <div class="totals-section">
            <div class="total-row">
                <span>小計:</span>
                <span>¥{{ number_format($sale->subtotal) }}</span>
            </div>
            
            @if($sale->discount_amount > 0)
            <div class="total-row">
                <span>割引:</span>
                <span>-¥{{ number_format($sale->discount_amount) }}</span>
            </div>
            @endif
            
            <div class="total-row">
                <span>消費税({{ $sale->tax_rate }}%):</span>
                <span>¥{{ number_format($sale->tax_amount) }}</span>
            </div>
            
            <div class="total-row grand-total">
                <span>合計:</span>
                <span>¥{{ number_format($sale->total_amount) }}</span>
            </div>
        </div>
        
        <div class="payment-section">
            <div class="info-row">
                <span>お支払い方法:</span>
                <span>{{ $paymentMethodLabels[$sale->payment_method] ?? $sale->payment_method }}</span>
            </div>
            
            @if($sale->payment_method === 'cash')
            <div class="info-row">
                <span>お預かり:</span>
                <span>¥{{ number_format($sale->received_amount ?? $sale->total_amount) }}</span>
            </div>
            <div class="info-row">
                <span>お釣り:</span>
                <span>¥{{ number_format(($sale->received_amount ?? $sale->total_amount) - $sale->total_amount) }}</span>
            </div>
            @endif
        </div>
        
        <div class="barcode">
            *{{ $sale->receipt_number }}*
        </div>
        
        <div class="footer">
            <p>ご来店ありがとうございました</p>
            <p>またのお越しをお待ちしております</p>
            <p style="margin-top: 10px; font-size: 9px;">
                {{ $store->name }}<br>
                発行日時: {{ now()->format('Y/m/d H:i:s') }}
            </p>
        </div>
    </div>
    
    <script>
        // 自動印刷オプション
        @if(request()->get('auto_print') === 'true')
            window.onload = function() {
                window.print();
            }
        @endif
    </script>
</body>
</html>