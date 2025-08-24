<x-filament-panels::page>
    @php
        $todayShifts = \App\Models\Shift::with(['user', 'store'])
            ->whereDate('shift_date', today())
            ->orderBy('start_time')
            ->get();
        $currentUser = auth()->user();
    @endphp

    <div class="space-y-6">
        <!-- 大きな現在時刻表示 -->
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <div class="text-6xl font-bold text-blue-600 mb-2" id="current-time">
                {{ now()->format('H:i') }}
            </div>
            <div class="text-lg text-gray-600">
                {{ now()->format('Y年m月d日') }}
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">今日のシフト</h2>
            
            @if($todayShifts->isEmpty())
                <div class="text-center py-12">
                    <p class="text-gray-500 text-lg font-medium">今日はシフトがありません</p>
                    <p class="text-gray-400 text-sm mt-2">シフトが登録されていない場合は、管理者にお問い合わせください</p>
                </div>
            @else
                <div class="grid gap-6">
                    @foreach($todayShifts as $shift)
                        @php
                            $canOperate = $currentUser->id === $shift->user_id || $currentUser->role === 'superadmin';
                        @endphp
                        
                        <div class="border rounded-lg p-6 {{ $shift->actual_end_time ? 'bg-green-50 border-green-200' : ($shift->actual_start_time ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200') }}">
                            <!-- スタッフ情報 -->
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900">
                                        {{ $shift->user->name }}
                                        @if(!$canOperate)
                                            <span class="text-sm text-gray-500">(操作不可)</span>
                                        @endif
                                    </h3>
                                    <p class="text-gray-600">{{ $shift->store->name }}</p>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-medium text-gray-900">
                                        予定: {{ \Carbon\Carbon::parse($shift->start_time)->format('H:i') }}-{{ \Carbon\Carbon::parse($shift->end_time)->format('H:i') }}
                                    </div>
                                    @if($shift->break_start && $shift->break_end)
                                        <div class="text-sm text-gray-600">
                                            休憩: {{ \Carbon\Carbon::parse($shift->break_start)->format('H:i') }}-{{ \Carbon\Carbon::parse($shift->break_end)->format('H:i') }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- 実績表示 -->
                            @if($shift->actual_start_time || $shift->actual_end_time || $shift->actual_break_start || $shift->actual_break_end)
                                <div class="mb-6 p-4 bg-white rounded-lg border">
                                    <h4 class="font-medium text-gray-900 mb-2">実績</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-600">出勤:</span>
                                            <span class="font-medium">{{ $shift->actual_start_time ? \Carbon\Carbon::parse($shift->actual_start_time)->format('H:i') : '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">休憩開始:</span>
                                            <span class="font-medium">{{ $shift->actual_break_start ? \Carbon\Carbon::parse($shift->actual_break_start)->format('H:i') : '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">休憩終了:</span>
                                            <span class="font-medium">{{ $shift->actual_break_end ? \Carbon\Carbon::parse($shift->actual_break_end)->format('H:i') : '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">退勤:</span>
                                            <span class="font-medium">{{ $shift->actual_end_time ? \Carbon\Carbon::parse($shift->actual_end_time)->format('H:i') : '-' }}</span>
                                        </div>
                                    </div>
                                    @if($shift->actual_working_hours)
                                        <div class="mt-2 text-right">
                                            <span class="text-sm text-gray-600">実働時間: </span>
                                            <span class="font-bold text-blue-600">{{ $shift->actual_working_hours }}時間</span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <!-- 大きなボタン -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <!-- 出勤ボタン -->
                                @if($canOperate && !$shift->actual_start_time)
                                    <button 
                                        wire:click="clockIn({{ $shift->id }})" 
                                        wire:confirm="出勤時刻を記録しますか？"
                                        class="h-20 text-lg font-bold rounded-lg bg-green-500 hover:bg-green-600 text-white shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center"
                                    >
                                        <span class="whitespace-nowrap">出勤</span>
                                    </button>
                                @else
                                    <div class="h-20 text-lg font-bold rounded-lg flex items-center justify-center text-center {{ $shift->actual_start_time ? 'bg-green-200 text-green-800' : 'bg-gray-300 text-gray-600' }}">
                                        @if($shift->actual_start_time)
                                            <div>
                                                <div>出勤済み</div>
                                                <div class="text-sm">{{ \Carbon\Carbon::parse($shift->actual_start_time)->format('H:i') }}</div>
                                            </div>
                                        @else
                                            <div>
                                                <div>出勤</div>
                                                <div class="text-xs">操作不可</div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <!-- 休憩開始ボタン -->
                                @if($canOperate && $shift->actual_start_time && !$shift->actual_break_start && !$shift->actual_end_time)
                                    <button 
                                        wire:click="startBreak({{ $shift->id }})" 
                                        wire:confirm="休憩開始時刻を記録しますか？"
                                        class="h-20 text-lg font-bold rounded-lg bg-orange-500 hover:bg-orange-600 text-white shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center"
                                    >
                                        <span class="whitespace-nowrap">休憩開始</span>
                                    </button>
                                @else
                                    <div class="h-20 text-lg font-bold rounded-lg flex items-center justify-center text-center {{ $shift->actual_break_start ? 'bg-orange-200 text-orange-800' : 'bg-gray-300 text-gray-600' }}">
                                        @if($shift->actual_break_start)
                                            <div>
                                                <div>休憩中</div>
                                                <div class="text-sm">{{ \Carbon\Carbon::parse($shift->actual_break_start)->format('H:i') }}〜</div>
                                            </div>
                                        @else
                                            <div>
                                                <div>休憩開始</div>
                                                <div class="text-xs">{{ !$canOperate ? '操作不可' : '出勤後可能' }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <!-- 休憩終了ボタン -->
                                @if($canOperate && $shift->actual_break_start && !$shift->actual_break_end && !$shift->actual_end_time)
                                    <button 
                                        wire:click="endBreak({{ $shift->id }})" 
                                        wire:confirm="休憩終了時刻を記録しますか？"
                                        class="h-20 text-lg font-bold rounded-lg bg-blue-500 hover:bg-blue-600 text-white shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center"
                                    >
                                        <span class="whitespace-nowrap">休憩終了</span>
                                    </button>
                                @else
                                    <div class="h-20 text-lg font-bold rounded-lg flex items-center justify-center text-center {{ $shift->actual_break_end ? 'bg-blue-200 text-blue-800' : 'bg-gray-300 text-gray-600' }}">
                                        @if($shift->actual_break_end)
                                            <div>
                                                <div>休憩終了済み</div>
                                                <div class="text-sm">{{ \Carbon\Carbon::parse($shift->actual_break_end)->format('H:i') }}</div>
                                            </div>
                                        @else
                                            <div>
                                                <div>休憩終了</div>
                                                <div class="text-xs">{{ !$canOperate ? '操作不可' : '休憩後可能' }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                <!-- 退勤ボタン -->
                                @if($canOperate && $shift->actual_start_time && !$shift->actual_end_time)
                                    <button 
                                        wire:click="clockOut({{ $shift->id }})" 
                                        wire:confirm="退勤時刻を記録しますか？"
                                        class="h-20 text-lg font-bold rounded-lg bg-red-500 hover:bg-red-600 text-white shadow-lg transition-all duration-200 transform hover:scale-105 flex items-center justify-center"
                                    >
                                        <span class="whitespace-nowrap">退勤</span>
                                    </button>
                                @else
                                    <div class="h-20 text-lg font-bold rounded-lg flex items-center justify-center text-center {{ $shift->actual_end_time ? 'bg-red-200 text-red-800' : 'bg-gray-300 text-gray-600' }}">
                                        @if($shift->actual_end_time)
                                            <div>
                                                <div>退勤済み</div>
                                                <div class="text-sm">{{ \Carbon\Carbon::parse($shift->actual_end_time)->format('H:i') }}</div>
                                            </div>
                                        @else
                                            <div>
                                                <div>退勤</div>
                                                <div class="text-xs">{{ !$canOperate ? '操作不可' : '出勤後可能' }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- ステータス表示 -->
                            <div class="mt-4 text-center">
                                @if($shift->actual_end_time)
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-green-100 text-green-700">
                                        勤務完了
                                    </span>
                                @elseif($shift->actual_break_start && !$shift->actual_break_end)
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-orange-100 text-orange-700 animate-pulse">
                                        休憩中
                                    </span>
                                @elseif($shift->actual_start_time)
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-blue-100 text-blue-700 animate-pulse">
                                        勤務中
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-gray-100 text-gray-700">
                                        勤務開始前
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <script>
        // 現在時刻を1秒ごとに更新
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ja-JP', { 
                hour: '2-digit', 
                minute: '2-digit', 
                hour12: false 
            });
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        // 1秒ごとに時刻更新
        setInterval(updateTime, 1000);
    </script>
</x-filament-panels::page>