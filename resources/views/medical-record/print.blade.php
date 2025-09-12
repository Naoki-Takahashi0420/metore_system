<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カルテ印刷 - {{ $customer->full_name }}</title>
    <style>
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .page-break {
                page-break-after: always;
            }
        }
        
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            background-color: #fff;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-section {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }
        
        .info-section h2 {
            font-size: 16px;
            color: #555;
            margin: 0 0 10px 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
            width: 120px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: #333;
            flex-grow: 1;
        }
        
        .vision-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .vision-table th,
        .vision-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        
        .vision-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            color: #555;
        }
        
        .vision-table .row-header {
            background-color: #f9f9f9;
            font-weight: bold;
            text-align: left;
        }
        
        .notes-section {
            background-color: #fff9e6;
            border: 1px solid #ffd966;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .notes-section h2 {
            font-size: 16px;
            color: #555;
            margin: 0 0 10px 0;
        }
        
        .notes-content {
            white-space: pre-wrap;
            line-height: 1.8;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        
        .print-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .print-button:hover {
            background-color: #45a049;
        }
        
        .close-button {
            background-color: #666;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-left: 10px;
        }
        
        .close-button:hover {
            background-color: #555;
        }
        
        .button-container {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="button-container no-print">
        <button onclick="window.print()" class="print-button">印刷する</button>
        <button onclick="window.close()" class="close-button">閉じる</button>
    </div>
    
    <div class="container">
        <div class="header">
            <h1>視力検査カルテ</h1>
            <div class="subtitle">{{ config('app.name', 'Xsyumeno') }} - 検査記録</div>
        </div>
        
        <div class="info-grid">
            <div class="info-section">
                <h2>患者情報</h2>
                <div class="info-row">
                    <span class="info-label">氏名：</span>
                    <span class="info-value">{{ $customer->full_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">フリガナ：</span>
                    <span class="info-value">{{ $customer->full_name_kana ?? '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">生年月日：</span>
                    <span class="info-value">
                        @if($customer->birth_date)
                            {{ $customer->birth_date->format('Y年m月d日') }}
                            （{{ $customer->birth_date->age }}歳）
                        @else
                            -
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">性別：</span>
                    <span class="info-value">
                        @if($customer->gender === 'male')
                            男性
                        @elseif($customer->gender === 'female')
                            女性
                        @else
                            -
                        @endif
                    </span>
                </div>
            </div>
            
            <div class="info-section">
                <h2>検査情報</h2>
                <div class="info-row">
                    <span class="info-label">検査日：</span>
                    <span class="info-value">{{ $record->treatment_date->format('Y年m月d日') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">検査種別：</span>
                    <span class="info-value">{{ $publicData['examination_type'] ?? '通常検査' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">対応者：</span>
                    <span class="info-value">{{ $record->handled_by ?? '-' }}</span>
                </div>
                @if($record->reservation)
                <div class="info-row">
                    <span class="info-label">店舗：</span>
                    <span class="info-value">{{ $record->reservation->store->name ?? '-' }}</span>
                </div>
                @endif
            </div>
        </div>
        
        <h2 style="font-size: 18px; color: #333; margin-bottom: 15px;">視力検査結果</h2>
        
        <table class="vision-table">
            <thead>
                <tr>
                    <th style="width: 120px;">項目</th>
                    <th>右眼 (OD)</th>
                    <th>左眼 (OS)</th>
                    <th>両眼 (OU)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="row-header">裸眼視力</td>
                    <td>{{ $publicData['unaided_vision_right'] ?? '-' }}</td>
                    <td>{{ $publicData['unaided_vision_left'] ?? '-' }}</td>
                    <td>{{ $publicData['unaided_vision_both'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="row-header">矯正視力</td>
                    <td>{{ $publicData['corrected_vision_right'] ?? '-' }}</td>
                    <td>{{ $publicData['corrected_vision_left'] ?? '-' }}</td>
                    <td>{{ $publicData['corrected_vision_both'] ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="row-header">老眼鏡使用</td>
                    <td>{{ $publicData['reading_vision_right'] ?? '-' }}</td>
                    <td>{{ $publicData['reading_vision_left'] ?? '-' }}</td>
                    <td>{{ $publicData['reading_vision_both'] ?? '-' }}</td>
                </tr>
            </tbody>
        </table>
        
        @if(!empty($publicData['eye_condition']))
        <div class="info-section" style="margin-bottom: 30px;">
            <h2>眼の状態</h2>
            <p style="margin: 0;">{{ $publicData['eye_condition'] }}</p>
        </div>
        @endif
        
        @if(!empty($publicData['symptoms']))
        <div class="info-section" style="margin-bottom: 30px;">
            <h2>症状・自覚症状</h2>
            <p style="margin: 0;">{{ $publicData['symptoms'] }}</p>
        </div>
        @endif
        
        @if(!empty($publicData['notes']))
        <div class="notes-section">
            <h2>備考・特記事項</h2>
            <div class="notes-content">{{ $publicData['notes'] }}</div>
        </div>
        @endif
        
        <div class="footer">
            <p>印刷日時: {{ now()->format('Y年m月d日 H:i') }}</p>
            <p>{{ config('app.name', 'Xsyumeno') }} - このカルテは診療記録として保管してください</p>
        </div>
    </div>
</body>
</html>