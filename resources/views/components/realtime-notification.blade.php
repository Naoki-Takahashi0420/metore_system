{{-- リアルタイム予約通知コンポーネント --}}
<div id="realtime-notification-container">
    {{-- 通知ベル（ヘッダー右上） --}}
    <div id="notification-bell" class="cursor-pointer hidden" style="position: fixed; top: 16px; right: 16px; z-index: 9999;">
        <div class="relative">
            <button
                onclick="toggleNotificationPanel()"
                class="p-2 bg-white rounded-full shadow-lg hover:shadow-xl transition-shadow"
                id="bell-button"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </button>
            {{-- 未読バッジ --}}
            <span
                id="notification-badge"
                class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden"
            >
                0
            </span>
        </div>
    </div>

    {{-- 通知パネル（ドロップダウン） --}}
    <div id="notification-panel" class="bg-white rounded-lg shadow-2xl border hidden overflow-hidden" style="position: fixed; top: 64px; right: 16px; width: 320px; max-height: 400px; z-index: 9999;">
        <div class="p-3 bg-blue-50 border-b flex justify-between items-center">
            <span class="font-bold text-blue-800">新規予約通知</span>
            <button onclick="clearNotifications()" class="text-xs text-gray-500 hover:text-gray-700">
                すべて消去
            </button>
        </div>
        <div id="notification-list" class="overflow-y-auto max-h-72">
            <p class="p-4 text-gray-400 text-center text-sm">通知はありません</p>
        </div>
    </div>

    {{-- トースト通知（画面下から出現） --}}
    <div id="toast-container" style="position: fixed; bottom: 16px; right: 16px; z-index: 9999; display: flex; flex-direction: column; gap: 8px;">
    </div>
</div>

{{-- 通知音（Web Audio APIで生成） --}}
<audio id="notification-sound" preload="auto"></audio>

<style>
/* 通知ベルの点滅アニメーション */
@keyframes bell-ring {
    0%, 100% { transform: rotate(0deg); }
    10%, 30%, 50%, 70%, 90% { transform: rotate(15deg); }
    20%, 40%, 60%, 80% { transform: rotate(-15deg); }
}

@keyframes bell-glow {
    0%, 100% { box-shadow: 0 0 5px rgba(239, 68, 68, 0.5); }
    50% { box-shadow: 0 0 20px rgba(239, 68, 68, 1), 0 0 30px rgba(239, 68, 68, 0.7); }
}

.bell-ringing {
    animation: bell-ring 0.5s ease-in-out;
}

.bell-glowing {
    animation: bell-glow 1s ease-in-out infinite;
    background-color: #fef2f2 !important;
}

/* トースト通知のスライドイン */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.toast-enter {
    animation: slideIn 0.3s ease-out forwards;
}

.toast-exit {
    animation: slideOut 0.3s ease-in forwards;
}
</style>

{{-- CDNからPusher.jsを読み込む --}}
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<script>
(function() {
    // 管理画面でのみ動作
    if (!window.location.pathname.startsWith('/admin')) {
        return;
    }

    // ローカルストレージのキー
    var STORAGE_KEY = 'reservation_notifications';
    var STORAGE_EXPIRY = 24 * 60 * 60 * 1000; // 24時間（ミリ秒）

    // 保存された通知を読み込む
    function loadNotifications() {
        try {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (!stored) return [];
            var data = JSON.parse(stored);
            var now = Date.now();
            // 24時間以内の通知のみフィルタ
            return data.filter(function(n) {
                return (now - n.timestamp) < STORAGE_EXPIRY;
            });
        } catch (e) {
            return [];
        }
    }

    // 通知を保存
    function saveNotification(notification) {
        try {
            var notifications = loadNotifications();
            notification.timestamp = Date.now();
            notifications.unshift(notification);
            // 最大50件まで保持
            if (notifications.length > 50) {
                notifications = notifications.slice(0, 50);
            }
            localStorage.setItem(STORAGE_KEY, JSON.stringify(notifications));
        } catch (e) {
            // ストレージエラーは無視
        }
    }

    // 通知をクリア
    function clearStoredNotifications() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
    }

    // 通知ベルを表示
    var bellContainer = document.getElementById('notification-bell');
    if (bellContainer) {
        bellContainer.classList.remove('hidden');
    }

    // 起動時に保存された通知を読み込んで表示
    var storedNotifications = loadNotifications();
    if (storedNotifications.length > 0) {
        updateBadge(storedNotifications.length);
        storedNotifications.forEach(function(n) {
            addToNotificationList(n, true); // skipSave=true
        });
    }

    // Reverb設定
    var reverbKey = '{{ env("REVERB_APP_KEY", "metore-realtime-key") }}';
    var reverbHost = '{{ env("REVERB_HOST", "localhost") }}';
    var reverbPort = {{ env("REVERB_PORT", 8080) }};
    var reverbScheme = '{{ env("REVERB_SCHEME", "http") }}';

    // ユーザー権限と店舗情報
    @php
        $user = auth()->user();
        $isSuperAdminOrOwner = $user && ($user->hasRole('super_admin') || $user->hasRole('owner'));
        $userStoreId = $user?->store_id;
    @endphp
    var isSuperAdminOrOwner = {{ $isSuperAdminOrOwner ? 'true' : 'false' }};
    var userStoreId = {{ $userStoreId ?? 'null' }};

    // Pusher直接接続（Echoを使わない）
    var pusher = new Pusher(reverbKey, {
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        cluster: 'mt1'
    });

    // 接続エラー時のログ（本番デバッグ用）
    pusher.connection.bind('error', function(err) {
        console.error('WebSocket error:', err);
    });

    // チャンネル購読（権限に応じて）
    var channel;
    if (isSuperAdminOrOwner) {
        // super_admin/owner: 全店舗の通知を受信
        channel = pusher.subscribe('reservations');
    } else if (userStoreId) {
        // 店長・スタッフ: 自分の店舗のみ
        channel = pusher.subscribe('reservations.' + userStoreId);
    } else {
        // 店舗未設定の場合は全体チャンネル（フォールバック）
        channel = pusher.subscribe('reservations');
    }

    // 新規予約
    channel.bind('reservation.created', function(e) {
        handleNewReservation(e);
    });

    // 予約変更
    channel.bind('reservation.changed', function(e) {
        handleReservationChanged(e);
    });

    // 予約キャンセル
    channel.bind('reservation.cancelled', function(e) {
        handleReservationCancelled(e);
    });

    // 新規予約の処理
    function handleNewReservation(data) {
        playNotificationSound('created');
        ringBell();
        updateBadge(1);
        window.showToast(data, 'created');
        addToNotificationList(data, false, 'created');
        refreshTimeline();
    }

    // 予約変更の処理
    function handleReservationChanged(data) {
        playNotificationSound('changed');
        ringBell();
        updateBadge(1);
        window.showToast(data, 'changed');
        addToNotificationList(data, false, 'changed');
        refreshTimeline();
    }

    // 予約キャンセルの処理
    function handleReservationCancelled(data) {
        playNotificationSound('cancelled');
        ringBell();
        updateBadge(1);
        window.showToast(data, 'cancelled');
        addToNotificationList(data, false, 'cancelled');
        refreshTimeline();
    }

    // タイムラインを自動更新
    function refreshTimeline() {
        // Livewire v3
        if (window.Livewire) {
            try {
                // Livewire.dispatch でイベント発火
                window.Livewire.dispatch('refresh-timeline');
            } catch (e) {
                // フォールバック: 直接コンポーネントを探して更新
                var timelineComponent = document.querySelector('[wire\\:id]');
                if (timelineComponent && timelineComponent.__livewire) {
                    timelineComponent.__livewire.$refresh();
                }
            }
        }
    }

    // 音の再生が許可されているかどうか
    var audioEnabled = false;
    var audioContext = null;

    // 通知音を再生（Web Audio APIを使用）
    // type: 'created' | 'changed' | 'cancelled'
    function playNotificationSound(type) {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();

            function beep(startTime, freq, duration, waveType) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = freq;
                osc.type = waveType || 'sine';
                gain.gain.setValueAtTime(0.5, startTime);
                gain.gain.exponentialRampToValueAtTime(0.01, startTime + duration);
                osc.start(startTime);
                osc.stop(startTime + duration);
            }

            var now = ctx.currentTime;

            if (type === 'created') {
                // 新規予約: 明るい上昇音 ピッピッピッ・ピッピッピッ
                beep(now, 800, 0.15, 'sine');
                beep(now + 0.15, 1000, 0.15, 'sine');
                beep(now + 0.3, 1200, 0.15, 'sine');
                beep(now + 0.6, 800, 0.15, 'sine');
                beep(now + 0.75, 1000, 0.15, 'sine');
                beep(now + 0.9, 1200, 0.15, 'sine');
            } else if (type === 'changed') {
                // 予約変更: 2音の繰り返し ポンポン・ポンポン
                beep(now, 600, 0.2, 'triangle');
                beep(now + 0.25, 800, 0.2, 'triangle');
                beep(now + 0.6, 600, 0.2, 'triangle');
                beep(now + 0.85, 800, 0.2, 'triangle');
            } else if (type === 'cancelled') {
                // キャンセル: 低い下降音 ブーブー
                beep(now, 400, 0.3, 'square');
                beep(now + 0.4, 300, 0.3, 'square');
                beep(now + 0.8, 400, 0.3, 'square');
                beep(now + 1.2, 300, 0.3, 'square');
            } else {
                // デフォルト
                beep(now, 800, 0.15, 'sine');
                beep(now + 0.15, 1000, 0.15, 'sine');
                beep(now + 0.3, 1200, 0.15, 'sine');
            }

        } catch (err) {
            // 音の再生に失敗した場合は無視
        }
    }

    // 音を有効化する関数
    function enableAudio() {
        if (audioEnabled) return;
        try {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            var buffer = audioContext.createBuffer(1, 1, 22050);
            var source = audioContext.createBufferSource();
            source.buffer = buffer;
            source.connect(audioContext.destination);
            source.start(0);
            audioEnabled = true;
        } catch (err) {
            // 音の有効化に失敗した場合は無視
        }
    }

    // ベルを振動させる
    function ringBell() {
        var button = document.getElementById('bell-button');
        if (button) {
            button.classList.add('bell-ringing', 'bell-glowing');
            setTimeout(function() {
                button.classList.remove('bell-ringing');
            }, 500);
        }
    }

    // バッジを更新
    function updateBadge(increment) {
        var badge = document.getElementById('notification-badge');
        if (badge) {
            var count = parseInt(badge.textContent) || 0;
            count += increment;
            badge.textContent = count;

            if (count > 0) {
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    }
    window.updateBadge = updateBadge;

    // トースト通知を表示
    // type: 'created' | 'changed' | 'cancelled'
    window.showToast = function(data, type) {
        var container = document.getElementById('toast-container');
        if (!container) return;

        // 種類別の設定
        var config = {
            created: {
                borderColor: '#22c55e',
                iconColor: '#22c55e',
                icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />',
                title: '🔔 新規予約が入りました'
            },
            changed: {
                borderColor: '#f59e0b',
                iconColor: '#f59e0b',
                icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />',
                title: '📝 予約が変更されました'
            },
            cancelled: {
                borderColor: '#ef4444',
                iconColor: '#ef4444',
                icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />',
                title: '❌ 予約がキャンセルされました'
            }
        };

        var c = config[type] || config.created;

        var toast = document.createElement('div');
        toast.style.cssText = 'min-width: 320px; padding: 16px; background: white; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); border-left: 4px solid ' + c.borderColor + '; animation: slideIn 0.3s ease-out; cursor: pointer; transition: transform 0.1s;';
        toast.innerHTML =
            '<div style="display: flex; align-items: flex-start;">' +
                '<div style="flex-shrink: 0;">' +
                    '<svg style="width: 24px; height: 24px; color: ' + c.iconColor + ';" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
                        c.icon +
                    '</svg>' +
                '</div>' +
                '<div style="margin-left: 12px; flex: 1;">' +
                    '<p style="font-size: 14px; font-weight: bold; color: #111827; margin: 0;">' + c.title + '</p>' +
                    '<p style="margin-top: 4px; font-size: 13px; color: #4b5563; margin-bottom: 0;">' +
                        '<strong>' + (data.customer_name || '顧客名') + '</strong> 様<br>' +
                        '📅 ' + (data.reservation_date || '') + ' ' + (data.start_time || '') + '<br>' +
                        '📋 ' + (data.menu_name || '') +
                    '</p>' +
                '</div>' +
                '<span style="margin-left: 16px; color: #9ca3af; font-size: 11px;">クリックで閉じる</span>' +
            '</div>';

        // トースト全体をクリックで閉じる
        toast.onclick = function() {
            toast.style.animation = 'slideOut 0.3s ease-in forwards';
            setTimeout(function() { toast.remove(); }, 300);
        };
        toast.onmouseenter = function() { toast.style.transform = 'scale(1.02)'; };
        toast.onmouseleave = function() { toast.style.transform = 'scale(1)'; };

        container.appendChild(toast);

        // 15秒後に自動で消える
        setTimeout(function() {
            toast.classList.remove('toast-enter');
            toast.classList.add('toast-exit');
            setTimeout(function() { toast.remove(); }, 300);
        }, 15000);
    }

    // 通知リストに追加（skipSave=trueならlocalStorageに保存しない）
    // type: 'created' | 'changed' | 'cancelled'
    function addToNotificationList(data, skipSave, type) {
        var list = document.getElementById('notification-list');
        if (!list) return;

        // 新規通知の場合、localStorageに保存
        if (!skipSave) {
            data.type = type || 'created';
            saveNotification(data);
        }

        // 「通知はありません」メッセージを削除
        var emptyMsg = list.querySelector('p.text-gray-400');
        if (emptyMsg) {
            emptyMsg.remove();
        }

        // 経過時間を計算（保存された通知用）
        var timeAgo = '';
        if (data.timestamp) {
            var diff = Date.now() - data.timestamp;
            var minutes = Math.floor(diff / 60000);
            var hours = Math.floor(diff / 3600000);
            if (hours > 0) {
                timeAgo = hours + '時間前';
            } else if (minutes > 0) {
                timeAgo = minutes + '分前';
            } else {
                timeAgo = 'たった今';
            }
        } else {
            timeAgo = 'たった今';
        }

        // 種類別の設定
        var notificationType = type || data.type || 'created';
        var config = {
            created: { bgColor: 'bg-green-100', iconColor: 'text-green-600', label: '新規' },
            changed: { bgColor: 'bg-amber-100', iconColor: 'text-amber-600', label: '変更' },
            cancelled: { bgColor: 'bg-red-100', iconColor: 'text-red-600', label: 'キャンセル' }
        };
        var c = config[notificationType] || config.created;

        var item = document.createElement('div');
        item.className = 'p-3 border-b hover:bg-gray-50 cursor-pointer';
        item.innerHTML =
            '<div class="flex items-start">' +
                '<div class="flex-shrink-0 ' + c.bgColor + ' rounded-full p-2">' +
                    '<svg class="h-4 w-4 ' + c.iconColor + '" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />' +
                    '</svg>' +
                '</div>' +
                '<div class="ml-3">' +
                    '<p class="text-sm font-medium text-gray-900">' +
                        '<span class="inline-block px-1.5 py-0.5 text-xs rounded mr-1 ' + c.bgColor + ' ' + c.iconColor + '">' + c.label + '</span>' +
                        (data.customer_name || '顧客名') + ' 様' +
                    '</p>' +
                    '<p class="text-xs text-gray-500">' + (data.reservation_date || '') + ' ' + (data.start_time || '') + '</p>' +
                    '<p class="text-xs text-gray-400">' + (data.menu_name || '') + '</p>' +
                    '<p class="text-xs text-gray-300 mt-1">' + timeAgo + '</p>' +
                '</div>' +
            '</div>';

        // リストの先頭に追加
        list.insertBefore(item, list.firstChild);
    }

    // グローバル関数として公開
    window.toggleNotificationPanel = function() {
        // ベルをクリックしたときに音を有効化
        enableAudio();

        var panel = document.getElementById('notification-panel');
        if (panel) {
            panel.classList.toggle('hidden');
        }

        // パネルを開いたときにバッジをリセット
        var badge = document.getElementById('notification-badge');
        var button = document.getElementById('bell-button');
        if (badge && panel && !panel.classList.contains('hidden')) {
            badge.textContent = '0';
            badge.classList.add('hidden');
            if (button) {
                button.classList.remove('bell-glowing');
            }
        }
    };

    // ページ上のどこかをクリックしたら音を有効化
    document.addEventListener('click', function() {
        enableAudio();
    }, { once: true });

    window.clearNotifications = function() {
        var list = document.getElementById('notification-list');
        if (list) {
            list.innerHTML = '<p class="p-4 text-gray-400 text-center text-sm">通知はありません</p>';
        }
        updateBadge(-9999);

        var button = document.getElementById('bell-button');
        if (button) {
            button.classList.remove('bell-glowing');
        }

        // localStorageもクリア
        clearStoredNotifications();
    };

    // パネル外クリックで閉じる
    document.addEventListener('click', function(e) {
        var panel = document.getElementById('notification-panel');
        var bell = document.getElementById('notification-bell');

        if (panel && bell && !panel.contains(e.target) && !bell.contains(e.target)) {
            panel.classList.add('hidden');
        }
    });

})();
</script>
