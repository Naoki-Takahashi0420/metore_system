<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <span>{{ $this->getHeading() }}</span>
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-xs text-gray-500">予約状況：</span>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #86efac; color: #166534;">🟢 空き(0-2)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #bef264; color: #365314;">🟢 余裕(3-4)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #fde047; color: #713f12;">🟡 普通(5-6)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #fb923c; color: #7c2d12;">🟠 混雑(7-8)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #f87171; color: #991b1b;">🔴 満員(9-10)</span>
                        <span class="px-2 py-1 rounded text-xs font-semibold" style="background-color: #dc2626; color: #ffffff;">🔥 超満員(11+)</span>
                    </div>
                </div>
            </div>
        </x-slot>

        <div>
            {!! $this->calendar() !!}
        </div>

        <style>
            /* カレンダーイベントのスタイル調整 */
            .fc-event {
                border: none !important;
                border-radius: 6px !important;
                padding: 2px 6px !important;
                font-weight: 600 !important;
                font-size: 13px !important;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
                transition: all 0.2s ease !important;
            }

            .fc-event:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
            }

            /* 予約数に応じたアニメーション */
            .reservation-heat-9,
            .reservation-heat-10 {
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }

            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.8;
                }
            }

            /* カレンダーの日付セルのスタイル */
            .fc-daygrid-day-frame {
                min-height: 60px !important;
            }

            /* 今日の日付を強調 */
            .fc-day-today {
                background-color: rgba(59, 130, 246, 0.05) !important;
            }

            /* カレンダーヘッダーのスタイル */
            .fc-col-header-cell {
                background-color: #f9fafb;
                font-weight: 600;
            }
        </style>
    </x-filament::section>
</x-filament-widgets::widget>