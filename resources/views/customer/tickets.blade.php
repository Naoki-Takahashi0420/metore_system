@extends('layouts.app')

@section('title', '回数券')

@section('head')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')
<div class="bg-gray-50 min-h-screen py-8 pb-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- ヘッダー -->
        <div class="bg-white rounded-lg shadow-md p-4 md:p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <a href="/customer/dashboard" class="text-gray-600 hover:text-gray-900">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">回数券</h1>
                </div>
                <button id="logout-btn" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors">
                    ログアウト
                </button>
            </div>

            <!-- 統計情報 -->
            <div id="stats-section" class="hidden grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">有効な回数券</p>
                    <p id="active-count" class="text-2xl font-bold text-blue-600">-</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">残り回数</p>
                    <p id="remaining-count" class="text-2xl font-bold text-green-600">-</p>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">期限間近</p>
                    <p id="expiring-count" class="text-2xl font-bold text-yellow-600">-</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-600 mb-1">合計</p>
                    <p id="total-count" class="text-2xl font-bold text-gray-900">-</p>
                </div>
            </div>
        </div>

        <!-- 読み込み中 -->
        <div id="loading" class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="flex flex-col items-center gap-4">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-gray-900"></div>
                <p class="text-gray-600">回数券情報を読み込んでいます...</p>
            </div>
        </div>

        <!-- 回数券一覧 -->
        <div id="tickets-list" class="hidden space-y-4">
            <!-- 動的に生成 -->
        </div>

        <!-- 回数券がない場合 -->
        <div id="no-tickets" class="hidden bg-white rounded-lg shadow-md p-8 text-center">
            <div class="flex flex-col items-center gap-4">
                <div class="bg-gray-100 p-6 rounded-full">
                    <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">回数券がありません</h3>
                <p class="text-gray-600">店舗で回数券をご購入いただくと、こちらに表示されます</p>
            </div>
        </div>

        <!-- エラー表示 -->
        <div id="error" class="hidden bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p id="error-message" class="text-red-800"></p>
            </div>
        </div>
    </div>
</div>

<!-- 利用履歴モーダル -->
<div id="history-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">利用履歴</h2>
                <button id="close-history-modal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <p id="history-ticket-name" class="text-sm text-gray-600 mt-2"></p>
        </div>
        <div id="history-content" class="p-6 overflow-y-auto max-h-[calc(90vh-160px)]">
            <!-- 動的に生成 -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const token = localStorage.getItem('customer_auth_token');

    if (!token) {
        window.location.href = '/customer/login';
        return;
    }

    // ログアウト処理
    document.getElementById('logout-btn').addEventListener('click', function() {
        localStorage.removeItem('customer_auth_token');
        window.location.href = '/customer/login';
    });

    // 回数券データを取得
    fetchTickets();

    function fetchTickets() {
        fetch('/api/customer/tickets-token', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('認証エラー');
            }
            return response.json();
        })
        .then(data => {
            displayTickets(data);
        })
        .catch(error => {
            showError('回数券情報の取得に失敗しました: ' + error.message);
        });
    }

    function displayTickets(data) {
        document.getElementById('loading').classList.add('hidden');

        if (data.tickets.length === 0) {
            document.getElementById('no-tickets').classList.remove('hidden');
            return;
        }

        // 統計情報を表示
        document.getElementById('stats-section').classList.remove('hidden');
        document.getElementById('active-count').textContent = data.stats.active_tickets + '枚';
        document.getElementById('remaining-count').textContent = data.stats.total_remaining + '回';
        document.getElementById('expiring-count').textContent = data.stats.expiring_soon + '枚';
        document.getElementById('total-count').textContent = data.stats.total_tickets + '枚';

        // 回数券リストを表示
        const ticketsList = document.getElementById('tickets-list');
        ticketsList.classList.remove('hidden');
        ticketsList.innerHTML = '';

        data.tickets.forEach(ticket => {
            const ticketCard = createTicketCard(ticket);
            ticketsList.appendChild(ticketCard);
        });
    }

    function createTicketCard(ticket) {
        const div = document.createElement('div');
        div.className = 'bg-white rounded-lg shadow-md overflow-hidden';

        const statusColors = {
            'active': 'bg-green-100 text-green-800',
            'expired': 'bg-red-100 text-red-800',
            'used_up': 'bg-gray-100 text-gray-800',
            'cancelled': 'bg-gray-100 text-gray-600'
        };

        const statusColor = statusColors[ticket.status] || 'bg-gray-100 text-gray-800';

        const expiryWarning = ticket.is_expiring_soon ?
            '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 ml-2">⚠️ 期限間近</span>' : '';

        div.innerHTML = `
            <div class="p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">${ticket.plan_name}</h3>
                        <p class="text-sm text-gray-600">${ticket.store_name}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-medium ${statusColor}">${ticket.status_label}</span>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">残り回数</p>
                        <p class="text-2xl font-bold text-blue-600">${ticket.remaining_count}<span class="text-sm text-gray-500">/${ticket.total_count}回</span></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 mb-1">有効期限</p>
                        <p class="text-sm font-medium text-gray-900">${ticket.expires_at || '無期限'}</p>
                        ${expiryWarning}
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-3 mb-4">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">購入日</span>
                        <span class="font-medium text-gray-900">${ticket.purchased_at}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm mt-2">
                        <span class="text-gray-600">利用済み</span>
                        <span class="font-medium text-gray-900">${ticket.used_count}回</span>
                    </div>
                </div>

                ${ticket.status === 'active' && ticket.remaining_count > 0 ? `
                    <div class="flex gap-2">
                        <a href="/reservation/category?ticket_id=${ticket.id}"
                           class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors text-center">
                            この回数券で予約する
                        </a>
                        <button onclick="showHistory(${ticket.id}, '${ticket.plan_name}')"
                                class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                            利用履歴を見る
                        </button>
                    </div>
                ` : `
                    <button onclick="showHistory(${ticket.id}, '${ticket.plan_name}')"
                            class="w-full border border-gray-300 text-gray-700 px-4 py-2 rounded-lg font-medium hover:bg-gray-50 transition-colors">
                        利用履歴を見る
                    </button>
                `}
            </div>
        `;

        return div;
    }

    // 利用履歴を表示
    window.showHistory = function(ticketId, planName) {
        document.getElementById('history-modal').classList.remove('hidden');
        document.getElementById('history-ticket-name').textContent = planName;
        document.getElementById('history-content').innerHTML = '<p class="text-center text-gray-600">読み込み中...</p>';

        fetch(`/api/customer/tickets/${ticketId}/history`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            displayHistory(data);
        })
        .catch(error => {
            document.getElementById('history-content').innerHTML = '<p class="text-center text-red-600">履歴の取得に失敗しました</p>';
        });
    };

    function displayHistory(data) {
        const historyContent = document.getElementById('history-content');

        if (data.history.length === 0) {
            historyContent.innerHTML = '<p class="text-center text-gray-600">利用履歴がありません</p>';
            return;
        }

        historyContent.innerHTML = '';

        data.history.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = index < data.history.length - 1 ? 'border-b border-gray-200 pb-4 mb-4' : '';

            const cancelled = item.is_cancelled ?
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-2">取消済み</span>' : '';

            div.innerHTML = `
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">${item.used_at}${cancelled}</p>
                        ${item.reservation ? `
                            <p class="text-sm text-gray-600 mt-1">予約番号: ${item.reservation.reservation_number}</p>
                            <p class="text-sm text-gray-600">予約日: ${item.reservation.reservation_date} ${item.reservation.start_time}</p>
                            <p class="text-sm text-gray-600">メニュー: ${item.reservation.menu_name}</p>
                        ` : '<p class="text-sm text-gray-600 mt-1">手動使用</p>'}
                        ${item.is_cancelled && item.cancel_reason ? `
                            <p class="text-sm text-red-600 mt-1">理由: ${item.cancel_reason}</p>
                        ` : ''}
                    </div>
                    <span class="text-lg font-bold ${item.is_cancelled ? 'text-red-600' : 'text-gray-900'}">${item.used_count}回</span>
                </div>
            `;

            historyContent.appendChild(div);
        });
    }

    // モーダルを閉じる
    document.getElementById('close-history-modal').addEventListener('click', function() {
        document.getElementById('history-modal').classList.add('hidden');
    });

    // モーダルの外側をクリックで閉じる
    document.getElementById('history-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });

    function showError(message) {
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('error').classList.remove('hidden');
        document.getElementById('error-message').textContent = message;
    }
});
</script>
@endsection
