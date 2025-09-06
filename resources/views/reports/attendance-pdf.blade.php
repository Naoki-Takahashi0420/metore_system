<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>月次勤怠レポート</title>
    <style>
        @page { margin: 20mm; }
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #333;
        }
        h1 {
            font-size: 18px;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
        }
        h2 {
            font-size: 14px;
            background: #f0f0f0;
            padding: 5px;
            margin: 15px 0 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
        }
        th {
            background: #e0e0e0;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .summary {
            background: #f8f8f8;
            font-weight: bold;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .info-item {
            display: table-cell;
            padding: 5px;
        }
        .label {
            font-weight: bold;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>月次勤怠レポート</h1>
    
    <div class="info-grid">
        <div class="info-item">
            <span class="label">対象期間：</span>
            {{ $reportData['period'] }}
        </div>
        <div class="info-item">
            <span class="label">店舗名：</span>
            {{ $reportData['store']->name }}
        </div>
        <div class="info-item">
            <span class="label">生成日時：</span>
            {{ $reportData['generated_at'] }}
        </div>
    </div>

    <h2>スタッフ別集計</h2>
    <table>
        <thead>
            <tr>
                <th>氏名</th>
                <th class="text-center">勤務日数</th>
                <th class="text-center">総勤務時間</th>
                <th class="text-center">休憩時間</th>
                <th class="text-center">実働時間</th>
                <th>シフトパターン</th>
            </tr>
        </thead>
        <tbody>
            @foreach($staffSummary as $staff)
            <tr>
                <td>{{ $staff['name'] }}</td>
                <td class="text-center">{{ $staff['days'] }}日</td>
                <td class="text-center">{{ $staff['total_hours'] }}時間</td>
                <td class="text-center">{{ $staff['break_hours'] }}時間</td>
                <td class="text-center">{{ $staff['actual_hours'] }}時間</td>
                <td>
                    @foreach($staff['patterns'] as $pattern => $count)
                        {{ $pattern }}: {{ $count }}回
                        @if(!$loop->last) / @endif
                    @endforeach
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="summary">
                <td>合計</td>
                <td class="text-center">{{ collect($staffSummary)->sum('days') }}日</td>
                <td class="text-center">{{ collect($staffSummary)->sum('total_hours') }}時間</td>
                <td class="text-center">{{ collect($staffSummary)->sum('break_hours') }}時間</td>
                <td class="text-center">{{ collect($staffSummary)->sum('actual_hours') }}時間</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <h2>シフトパターン分析</h2>
    <table>
        <tr>
            @foreach($patternAnalysis as $pattern => $count)
            <th>{{ $pattern }}</th>
            @endforeach
        </tr>
        <tr>
            @foreach($patternAnalysis as $pattern => $count)
            <td class="text-center">{{ $count }}回</td>
            @endforeach
        </tr>
    </table>

    <h2>日別詳細</h2>
    <table>
        <thead>
            <tr>
                <th width="15%">日付</th>
                <th width="70%">スタッフ</th>
                <th width="15%" class="text-center">合計時間</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dailySummary ?? [] as $day)
            <tr>
                <td>{{ $day['date'] }} {{ $day['day'] }}</td>
                <td>
                    @if(count($day['staff']) > 0)
                        @foreach($day['staff'] as $staff)
                            {{ $staff['name'] }} ({{ $staff['time'] }})@if(!$loop->last), @endif
                        @endforeach
                    @else
                        -
                    @endif
                </td>
                <td class="text-center">{{ $day['total_hours'] }}時間</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>