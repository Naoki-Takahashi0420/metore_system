{{-- ヘッダー統合版：通知ベル --}}
<div class="relative flex items-center">
    {{-- ベルボタン（Filamentのスタイルに合わせる） --}}
    <button
        type="button"
        onclick="toggleNotificationPanel()"
        class="relative flex items-center justify-center w-10 h-10 text-gray-500 transition rounded-lg hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
        id="header-bell-button"
        aria-label="通知"
    >
        {{-- ベルアイコン --}}
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        {{-- 未読バッジ --}}
        <span
            id="header-notification-badge"
            class="absolute top-0 right-0 flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full hidden"
        >
            0
        </span>
    </button>

    {{-- 通知パネル（ドロップダウン） --}}
    <div
        id="header-notification-panel"
        class="absolute right-0 z-50 hidden bg-white border rounded-lg shadow-2xl top-12"
        style="width: 320px; max-height: 400px;"
    >
        <div class="flex items-center justify-between p-3 border-b bg-blue-50">
            <span class="font-bold text-blue-800">新規予約通知</span>
            <button onclick="clearHeaderNotifications()" class="text-xs text-gray-500 hover:text-gray-700">
                すべて消去
            </button>
        </div>
        <div id="header-notification-list" class="overflow-y-auto max-h-72">
            <p class="p-4 text-sm text-center text-gray-400">通知はありません</p>
        </div>
    </div>
</div>

<style>
/* ベルアニメーション */
@keyframes header-bell-ring {
    0%, 100% { transform: rotate(0deg); }
    10%, 30%, 50%, 70%, 90% { transform: rotate(15deg); }
    20%, 40%, 60%, 80% { transform: rotate(-15deg); }
}

.header-bell-ringing {
    animation: header-bell-ring 0.5s ease-in-out;
}

.header-bell-glowing {
    background-color: #fef2f2 !important;
}
</style>

<script>
// ヘッダーベルの初期化
(function() {
    if (!window.location.pathname.startsWith('/admin')) return;

    // グローバル関数として公開（既存のrealtime-notificationから呼ばれる）
    window.toggleNotificationPanel = function() {
        const panel = document.getElementById('header-notification-panel');
        const badge = document.getElementById('header-notification-badge');
        const button = document.getElementById('header-bell-button');

        if (panel) {
            panel.classList.toggle('hidden');

            // パネルを開いたときにバッジをリセット
            if (!panel.classList.contains('hidden')) {
                if (badge) {
                    badge.textContent = '0';
                    badge.classList.add('hidden');
                }
                if (button) {
                    button.classList.remove('header-bell-glowing');
                }
            }
        }
    };

    window.clearHeaderNotifications = function() {
        const list = document.getElementById('header-notification-list');
        if (list) {
            list.innerHTML = '<p class="p-4 text-sm text-center text-gray-400">通知はありません</p>';
        }

        const badge = document.getElementById('header-notification-badge');
        if (badge) {
            badge.textContent = '0';
            badge.classList.add('hidden');
        }

        const button = document.getElementById('header-bell-button');
        if (button) {
            button.classList.remove('header-bell-glowing');
        }

        // 既存のクリア関数も呼ぶ
        if (window.clearNotifications) {
            window.clearNotifications();
        }
    };

    // パネル外クリックで閉じる
    document.addEventListener('click', function(e) {
        const panel = document.getElementById('header-notification-panel');
        const button = document.getElementById('header-bell-button');

        if (panel && button && !panel.contains(e.target) && !button.contains(e.target)) {
            panel.classList.add('hidden');
        }
    });

    // 既存のrealtime-notificationから通知を受け取る
    // updateBadge関数を上書きしてヘッダーバッジも更新
    const originalUpdateBadge = window.updateBadge;
    window.updateBadge = function(increment) {
        // 既存のバッジ更新
        if (originalUpdateBadge) {
            originalUpdateBadge(increment);
        }

        // ヘッダーバッジも更新
        const headerBadge = document.getElementById('header-notification-badge');
        if (headerBadge) {
            let count = parseInt(headerBadge.textContent) || 0;
            count += increment;
            if (count < 0) count = 0;
            headerBadge.textContent = count;

            if (count > 0) {
                headerBadge.classList.remove('hidden');
            } else {
                headerBadge.classList.add('hidden');
            }
        }

        // ベルアニメーション
        const headerButton = document.getElementById('header-bell-button');
        if (headerButton && increment > 0) {
            headerButton.classList.add('header-bell-ringing', 'header-bell-glowing');
            setTimeout(function() {
                headerButton.classList.remove('header-bell-ringing');
            }, 500);
        }
    };

    // 通知リストにも追加
    const originalAddToNotificationList = window.addToNotificationList;
    if (originalAddToNotificationList) {
        window.addToNotificationList = function(data, skipSave, type) {
            // 既存のリストに追加
            originalAddToNotificationList(data, skipSave, type);

            // ヘッダーリストにも追加
            const headerList = document.getElementById('header-notification-list');
            if (!headerList) return;

            // 「通知はありません」メッセージを削除
            const emptyMsg = headerList.querySelector('p.text-gray-400');
            if (emptyMsg) {
                emptyMsg.remove();
            }

            // 経過時間を計算
            let timeAgo = 'たった今';
            if (data.timestamp) {
                const diff = Date.now() - data.timestamp;
                const minutes = Math.floor(diff / 60000);
                const hours = Math.floor(diff / 3600000);
                if (hours > 0) {
                    timeAgo = hours + '時間前';
                } else if (minutes > 0) {
                    timeAgo = minutes + '分前';
                }
            }

            // 種類別の設定
            const notificationType = type || data.type || 'created';
            const config = {
                created: { bgColor: 'bg-green-100', iconColor: 'text-green-600', label: '新規' },
                changed: { bgColor: 'bg-amber-100', iconColor: 'text-amber-600', label: '変更' },
                cancelled: { bgColor: 'bg-red-100', iconColor: 'text-red-600', label: 'キャンセル' }
            };
            const c = config[notificationType] || config.created;

            const item = document.createElement('div');
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

            headerList.insertBefore(item, headerList.firstChild);
        };
    }
})();
</script>
