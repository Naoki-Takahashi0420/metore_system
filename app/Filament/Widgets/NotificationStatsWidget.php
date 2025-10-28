<?php

namespace App\Filament\Widgets;

use App\Models\NotificationLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NotificationStatsWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected function getStats(): array
    {
        // 過去7日間の統計
        $successRates = NotificationLog::getSuccessRateByChannel(7);

        // 今日の統計
        $todayTotal = NotificationLog::whereDate('created_at', today())->count();
        $todaySuccess = NotificationLog::whereDate('created_at', today())
            ->where('status', 'sent')
            ->count();

        // 過去1時間の統計
        $lastHourTotal = NotificationLog::where('created_at', '>=', now()->subHour())->count();
        $lastHourFailed = NotificationLog::where('created_at', '>=', now()->subHour())
            ->where('status', 'failed')
            ->count();

        return [
            Stat::make('今日の通知送信', $todayTotal)
                ->description($todaySuccess . '件成功')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color($todaySuccess === $todayTotal ? 'success' : 'warning'),

            Stat::make('LINE成功率（7日間）', $successRates['line']['rate'] . '%')
                ->description($successRates['line']['total'] . '件中 ' . $successRates['line']['success'] . '件成功')
                ->descriptionIcon('heroicon-o-chat-bubble-left-ellipsis')
                ->color($successRates['line']['rate'] >= 90 ? 'success' : ($successRates['line']['rate'] >= 70 ? 'warning' : 'danger')),

            Stat::make('SMS成功率（7日間）', $successRates['sms']['rate'] . '%')
                ->description($successRates['sms']['total'] . '件中 ' . $successRates['sms']['success'] . '件成功')
                ->descriptionIcon('heroicon-o-device-phone-mobile')
                ->color($successRates['sms']['rate'] >= 90 ? 'success' : ($successRates['sms']['rate'] >= 70 ? 'warning' : 'danger')),

            Stat::make('メール成功率（7日間）', $successRates['email']['rate'] . '%')
                ->description($successRates['email']['total'] . '件中 ' . $successRates['email']['success'] . '件成功')
                ->descriptionIcon('heroicon-o-envelope')
                ->color($successRates['email']['rate'] >= 90 ? 'success' : ($successRates['email']['rate'] >= 70 ? 'warning' : 'danger')),

            Stat::make('過去1時間の送信', $lastHourTotal)
                ->description($lastHourFailed > 0 ? $lastHourFailed . '件失敗' : '全て成功')
                ->descriptionIcon($lastHourFailed > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($lastHourFailed === 0 ? 'success' : 'danger'),
        ];
    }
}
