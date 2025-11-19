<?php

namespace App\Filament\Widgets;

use App\Models\FcInvoice;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class FcUnpaidInvoicesWidget extends Widget
{
    protected static string $view = 'filament.widgets.fc-unpaid-invoices';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    // 自動検出を無効化（特定ページでのみ手動登録）
    protected static bool $isDiscovered = false;

    public function getInvoices()
    {
        return FcInvoice::query()
            ->whereIn('status', ['issued', 'sent'])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();
    }

    public static function canView(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // super_adminまたは本部店舗のみ表示
        return $user->hasRole('super_admin') || $user->store?->isHeadquarters();
    }
}
