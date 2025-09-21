<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\Store;
use App\Models\Menu;
use App\Models\MedicalRecord;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Notifications\Notification;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\Actions;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Filament\Forms\Components\Select;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;

class ReservationCalendarWidget extends FullCalendarWidget
{
    protected static ?int $sort = 30;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = '予約カレンダー';

    protected function getHeading(): ?string
    {
        $legend = '
            <div class="flex items-center justify-between w-full">
                <span>予約カレンダー</span>
                <div class="flex items-center gap-2 text-xs">
                    <span class="px-1 py-0.5 rounded" style="background-color: #86efac; color: #166534;">空き</span>
                    <span class="px-1 py-0.5 rounded" style="background-color: #fde047; color: #713f12;">普通</span>
                    <span class="px-1 py-0.5 rounded" style="background-color: #fb923c; color: #7c2d12;">混雑</span>
                    <span class="px-1 py-0.5 rounded" style="background-color: #dc2626; color: #ffffff;">満員</span>
                </div>
            </div>
        ';
        return $legend;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    public ?int $selectedStoreId = null;

    public function mount(): void
    {
        // 親ページから店舗IDを受け取る、または初期値を設定
        if (!$this->selectedStoreId) {
            $user = auth()->user();

            if ($user->hasRole('super_admin')) {
                // スーパーアドミンの場合、最初の店舗を選択
                $firstStore = Store::first();
                $this->selectedStoreId = $firstStore?->id;
            } else {
                // 店舗管理者の場合、自分の店舗IDを設定
                $this->selectedStoreId = $user->store_id;
            }
        }
    }

    #[On('store-changed')]
    public function updateStoreId($storeId, $date = null): void
    {
        $this->selectedStoreId = $storeId;
        // FullCalendarのイベントを再取得
        // $this->refreshRecords(); // このメソッドは存在しない可能性がある
        $this->dispatch('refreshCalendar'); // 代わりにイベントを発火
    }

    protected function getBaseQuery()
    {
        $query = Reservation::query();
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // スーパーアドミンは全予約を表示
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // オーナーは管理可能店舗の予約のみ表示
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $query->whereIn('store_id', $manageableStoreIds);
        }

        // 店長・スタッフは所属店舗の予約のみ表示
        if ($user->hasRole(['manager', 'staff'])) {
            if ($user->store_id) {
                return $query->where('store_id', $user->store_id);
            }
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }

    public function fetchEvents(array $info): array
    {
        try {
            $start = Carbon::parse($info['start']);
            $end = Carbon::parse($info['end']);

            $query = $this->getBaseQuery()
                ->whereBetween('reservation_date', [$start, $end]);

        // 店舗フィルタリング
        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }

        // 日付別にグループ化して件数を集計
        $reservationsByDate = $query
            ->selectRaw('reservation_date, COUNT(*) as count,
                        SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_count')
            ->groupBy('reservation_date')
            ->get();

        return $reservationsByDate->map(function ($group) {
            $date = Carbon::parse($group->reservation_date);
            $activeCount = $group->count - $group->cancelled_count;

            // ヒートマップスタイルの色分け（緑→黄→オレンジ→赤）
            $backgroundColor = '#f3f4f6'; // デフォルトグレー
            $textColor = '#6b7280';
            $statusEmoji = '';

            if ($activeCount == 0) {
                $backgroundColor = '#f3f4f6'; // 薄いグレー（予約なし）
                $textColor = '#9ca3af';
            } elseif ($activeCount <= 2) {
                $backgroundColor = '#86efac'; // 薄い緑（空いている）
                $textColor = '#166534';
                $statusEmoji = '🟢 ';
            } elseif ($activeCount <= 4) {
                $backgroundColor = '#bef264'; // 緑（余裕あり）
                $textColor = '#365314';
                $statusEmoji = '🟢 ';
            } elseif ($activeCount <= 6) {
                $backgroundColor = '#fde047'; // 黄色（普通）
                $textColor = '#713f12';
                $statusEmoji = '🟡 ';
            } elseif ($activeCount <= 8) {
                $backgroundColor = '#fb923c'; // オレンジ（やや混雑）
                $textColor = '#7c2d12';
                $statusEmoji = '🟠 ';
            } elseif ($activeCount <= 10) {
                $backgroundColor = '#f87171'; // 薄い赤（混雑）
                $textColor = '#991b1b';
                $statusEmoji = '🔴 ';
            } else {
                $backgroundColor = '#dc2626'; // 濃い赤（非常に混雑）
                $textColor = '#ffffff';
                $statusEmoji = '🔥 ';
            }

            // タイトルに件数を表示（絵文字付き）
            $title = $statusEmoji . $activeCount . '件';
            if ($group->cancelled_count > 0) {
                $title .= "\n(ｷｬﾝｾﾙ" . $group->cancelled_count . ')';
            }

            return [
                'id' => 'count_' . $date->format('Y-m-d'),
                'title' => $title,
                'start' => $date->format('Y-m-d'),
                'allDay' => true, // 終日イベントとして表示
                'backgroundColor' => $backgroundColor,
                'borderColor' => $backgroundColor,
                'textColor' => $textColor,
                'className' => 'reservation-heat-' . min($activeCount, 10), // CSSクラス追加
                'extendedProps' => [
                    'date' => $date->format('Y年m月d日'),
                    'totalCount' => $group->count,
                    'activeCount' => $activeCount,
                    'cancelledCount' => $group->cancelled_count,
                    'statusEmoji' => $statusEmoji,
                ],
            ];
        })->toArray();
        } catch (\Exception $e) {
            // エラーが発生した場合は空の配列を返す
            \Log::error('ReservationCalendarWidget fetchEvents error: ' . $e->getMessage());
            return [];
        }
    }


    public $showModal = false;
    public $selectedReservation = null;

    public function openReservationDetail($reservationId)
    {
        $reservation = Reservation::with(['customer', 'store', 'menu'])->find($reservationId);

        if ($reservation) {
            $this->selectedReservation = $reservation;
            $this->showModal = true;
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedReservation = null;
    }

    // 親クラスのrenderメソッドを使用

    protected function modalActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('予約一覧')
                ->modalHeading(function (array $arguments) {
                    $date = $arguments['event']['extendedProps']['date'] ?? '';
                    return $date . 'の予約一覧';
                })
                ->modalWidth('md')
                ->form([
                    Forms\Components\Placeholder::make('summary')
                        ->label('')
                        ->content(function (array $arguments) {
                            $activeCount = $arguments['event']['extendedProps']['activeCount'] ?? 0;
                            $cancelledCount = $arguments['event']['extendedProps']['cancelledCount'] ?? 0;
                            $totalCount = $arguments['event']['extendedProps']['totalCount'] ?? 0;

                            $summary = "予約件数: {$activeCount}件\n";
                            if ($cancelledCount > 0) {
                                $summary .= "キャンセル: {$cancelledCount}件\n";
                                $summary .= "合計: {$totalCount}件";
                            }
                            return $summary;
                        }),
                ])
                ->modalActions([
                    // 予約一覧へのリンクボタン
                    \Filament\Actions\Action::make('view_reservations')
                        ->label('予約一覧を見る')
                        ->icon('heroicon-o-list-bullet')
                        ->color('primary')
                        ->url(function (array $arguments) {
                            // 日付から予約一覧ページへのリンクを生成
                            $date = str_replace('count_', '', $arguments['event']['id'] ?? '');
                            return '/admin/reservations?tableFilters[reservation_date][date]=' . $date;
                        })
                        ->openUrlInNewTab()
                        ->action(function () {
                            // URLリンクなのでアクションは不要
                        }),
                ])
                ->fillForm(function (array $arguments) {
                    // カウントデータをフォームに表示
                    return [];
                })
        ];
    }

    public function getModel(): string
    {
        return Reservation::class;
    }

    protected function getOptions(): array
    {
        return [
            'locale' => 'ja',
            'firstDay' => 1, // 月曜始まり
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
            ],
            'initialView' => 'dayGridMonth', // 月表示をデフォルトに
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '21:00:00',
            'slotDuration' => '00:30:00',
            'slotLabelInterval' => '01:00',
            'weekNumbers' => false,
            'weekNumberCalculation' => 'ISO',
            'height' => 700,
            'contentHeight' => 'auto',
            'aspectRatio' => 1.8,
            'nowIndicator' => true,
            'selectable' => true,
            'selectMirror' => true,
            'businessHours' => [
                'daysOfWeek' => [1, 2, 3, 4, 5, 6],
                'startTime' => '09:00',
                'endTime' => '20:00',
            ],
            'displayEventTime' => false, // 時間表示を無効化（件数のみ表示）
            'displayEventEnd' => false,

            // 月表示での設定
            'dayMaxEventRows' => false, // 制限なし
            'moreLinkClick' => 'popover', // 「+more」クリック時にポップオーバー表示

            // イベントの文字サイズ調整
            'eventDisplay' => 'block',
            'eventTextColor' => '#ffffff',

            'buttonText' => [
                'today' => '今日',
                'month' => '月',
                'week' => '週',
                'day' => '日',
                'list' => 'リスト',
            ],
            'allDayText' => '終日',

            // イベントクリック処理を有効化
            'eventClick' => true,

            'editable' => false,

            // ツールチップ設定（件数情報表示）
            'eventDidMount' => 'function(info) {
                var activeCount = info.event.extendedProps.activeCount || 0;
                var cancelledCount = info.event.extendedProps.cancelledCount || 0;
                var date = info.event.extendedProps.date || "";
                var statusEmoji = info.event.extendedProps.statusEmoji || "";

                var status = "";
                if (activeCount == 0) {
                    status = "予約なし";
                } else if (activeCount <= 2) {
                    status = "空いています";
                } else if (activeCount <= 4) {
                    status = "余裕があります";
                } else if (activeCount <= 6) {
                    status = "通常";
                } else if (activeCount <= 8) {
                    status = "混雑しています";
                } else if (activeCount <= 10) {
                    status = "満員に近いです";
                } else {
                    status = "非常に混雑";
                }

                var tooltip = date + "\\n" +
                    "状態: " + status + "\\n" +
                    "予約: " + activeCount + "件";
                if (cancelledCount > 0) {
                    tooltip += "\\nキャンセル: " + cancelledCount + "件";
                }

                info.el.setAttribute("title", tooltip);
                info.el.style.cursor = "pointer";

                // ホバー効果を追加
                info.el.addEventListener("mouseenter", function() {
                    info.el.style.opacity = "0.9";
                });
                info.el.addEventListener("mouseleave", function() {
                    info.el.style.opacity = "1";
                });
            }',
        ];
    }
}