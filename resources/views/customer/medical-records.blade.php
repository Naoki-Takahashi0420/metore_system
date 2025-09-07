@extends('layouts.app')

@section('title', 'カルテ')

@section('content')
<div class="bg-white min-h-screen pb-20 md:pb-0">
    <div class="max-w-4xl mx-auto px-4">
        <!-- Header -->
        <div class="py-6 border-b border-gray-100">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">カルテ</h1>
                    <p class="text-sm text-gray-500 mt-1" id="customer-info">
                        読み込み中...
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="history.back()" class="text-gray-600 hover:text-gray-900 p-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <a href="/customer/dashboard" class="text-gray-600 hover:text-gray-900 p-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </a>
                    <a href="/customer/reservations" class="text-gray-600 hover:text-gray-900 px-3 py-2 text-sm">
                        予約履歴
                    </a>
                    <a href="/stores" class="bg-gray-900 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-800 transition-colors">
                        新規予約
                    </a>
                </div>
            </div>
        </div>

        <!-- Medical Records List -->
        <div id="medical-records-container" class="py-4">
            <div class="text-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 mx-auto"></div>
                <p class="text-gray-500 mt-3 text-sm">カルテ情報を読み込み中...</p>
            </div>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden py-16 text-center">
            <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h3 class="text-base font-medium text-gray-900 mb-2">診療記録がありません</h3>
            <p class="text-sm text-gray-500 mb-6">まだ診療を受けていらっしゃいません</p>
            <a href="/stores" class="bg-gray-900 text-white px-5 py-2.5 rounded-lg text-sm hover:bg-gray-800 transition-colors inline-block">
                初回カウンセリングを予約
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const token = localStorage.getItem('customer_token');
    const customerData = localStorage.getItem('customer_data');
    
    // トークンがない場合はログインページにリダイレクト
    if (!token) {
        window.location.href = '/customer/login';
        return;
    }
    
    // 顧客情報を表示
    if (customerData) {
        try {
            const customer = JSON.parse(customerData);
            document.getElementById('customer-info').textContent = 
                `${customer.last_name} ${customer.first_name} 様のカルテ`;
        } catch (e) {
            console.error('Customer data parse error:', e);
        }
    }
    
    // カルテ情報を取得
    try {
        const response = await fetch('/api/customer/medical-records', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            if (response.status === 401) {
                // トークンが無効
                localStorage.removeItem('customer_token');
                localStorage.removeItem('customer_data');
                window.location.href = '/customer/login';
                return;
            }
            throw new Error('Failed to fetch medical records');
        }
        
        const data = await response.json();
        displayMedicalRecords(data.data || []);
        
    } catch (error) {
        console.error('Error fetching medical records:', error);
        document.getElementById('medical-records-container').innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-800">カルテ情報の取得に失敗しました。再度お試しください。</p>
            </div>
        `;
    }
    
    // ログアウト処理
    document.getElementById('logout-btn').addEventListener('click', function() {
        localStorage.removeItem('customer_token');
        localStorage.removeItem('customer_data');
        window.location.href = '/customer/login';
    });
});

function displayMedicalRecords(records) {
    const container = document.getElementById('medical-records-container');
    const emptyState = document.getElementById('empty-state');
    
    if (records.length === 0) {
        container.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }
    
    container.innerHTML = records.map(record => `
        <div class="border-b border-gray-100 py-6">
            <div class="pb-3 mb-3">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-base font-medium text-gray-900 mb-1">
                            ${formatDate(record.treatment_date || record.record_date || record.created_at)}
                        </h3>
                        ${record.reservation ? `
                            <div class="text-sm text-gray-600 space-y-1">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    ${record.reservation.store?.name || '店舗情報なし'}
                                </div>
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z" />
                                    </svg>
                                    ${record.reservation.menu?.name || 'メニュー情報なし'}
                                </div>
                                ${record.handled_by || record.created_by ? `
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        担当: ${record.handled_by || (record.created_by ? record.created_by.name : '')}
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                    <div class="text-right">
                        <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">
                            診療記録
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-4">
                ${record.chief_complaint ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">主訴・お悩み</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.chief_complaint}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.symptoms ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">症状・現状</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.symptoms}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.diagnosis ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">診断・所見</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.diagnosis}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.treatment ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">治療・施術内容</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.treatment}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.prescription ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">処方・指導</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.prescription}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.medical_history ? `
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-1">既往歴・医療履歴</h4>
                        <div class="bg-gray-50 p-3 rounded">
                            <p class="text-sm text-gray-700">${record.medical_history}</p>
                        </div>
                    </div>
                ` : ''}
            </div>
            
            ${record.vision_records && record.vision_records.length > 0 ? record.vision_records.map((vision, index) => {
                // 視力データの取得
                const hasNakedData = vision.before_naked_left || vision.before_naked_right || vision.after_naked_left || vision.after_naked_right;
                const hasCorrectedData = vision.before_corrected_left || vision.before_corrected_right || vision.after_corrected_left || vision.after_corrected_right;
                
                if (!hasNakedData && !hasCorrectedData) return '';
                
                return `
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">視力検査記録</h4>
                        
                        ${hasNakedData ? `
                            <div class="mb-3">
                                <p class="text-xs text-gray-600 mb-2">裸眼視力</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500"></th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">施術前</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">施術後</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">変化</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr>
                                                <td class="px-3 py-2 text-xs font-medium text-gray-900">左眼</td>
                                                <td class="px-3 py-2 text-center text-sm text-gray-700">${vision.before_naked_left || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm font-medium text-gray-900">${vision.after_naked_left || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm">
                                                    ${vision.before_naked_left && vision.after_naked_left ? (() => {
                                                        const change = (parseFloat(vision.after_naked_left) - parseFloat(vision.before_naked_left)).toFixed(2);
                                                        const changeNum = parseFloat(change);
                                                        const color = changeNum > 0 ? 'text-green-600' : changeNum < 0 ? 'text-red-600' : 'text-gray-600';
                                                        return `<span class="${color} font-medium">${changeNum > 0 ? '+' : ''}${change}</span>`;
                                                    })() : '−'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 text-xs font-medium text-gray-900">右眼</td>
                                                <td class="px-3 py-2 text-center text-sm text-gray-700">${vision.before_naked_right || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm font-medium text-gray-900">${vision.after_naked_right || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm">
                                                    ${vision.before_naked_right && vision.after_naked_right ? (() => {
                                                        const change = (parseFloat(vision.after_naked_right) - parseFloat(vision.before_naked_right)).toFixed(2);
                                                        const changeNum = parseFloat(change);
                                                        const color = changeNum > 0 ? 'text-green-600' : changeNum < 0 ? 'text-red-600' : 'text-gray-600';
                                                        return `<span class="${color} font-medium">${changeNum > 0 ? '+' : ''}${change}</span>`;
                                                    })() : '−'}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${hasCorrectedData ? `
                            <div class="mb-3">
                                <p class="text-xs text-gray-600 mb-2">矯正視力</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr class="bg-gray-50">
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500"></th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">施術前</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">施術後</th>
                                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">変化</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr>
                                                <td class="px-3 py-2 text-xs font-medium text-gray-900">左眼</td>
                                                <td class="px-3 py-2 text-center text-sm text-gray-700">${vision.before_corrected_left || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm font-medium text-gray-900">${vision.after_corrected_left || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm">
                                                    ${vision.before_corrected_left && vision.after_corrected_left ? (() => {
                                                        const change = (parseFloat(vision.after_corrected_left) - parseFloat(vision.before_corrected_left)).toFixed(2);
                                                        const changeNum = parseFloat(change);
                                                        const color = changeNum > 0 ? 'text-green-600' : changeNum < 0 ? 'text-red-600' : 'text-gray-600';
                                                        return `<span class="${color} font-medium">${changeNum > 0 ? '+' : ''}${change}</span>`;
                                                    })() : '−'}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2 text-xs font-medium text-gray-900">右眼</td>
                                                <td class="px-3 py-2 text-center text-sm text-gray-700">${vision.before_corrected_right || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm font-medium text-gray-900">${vision.after_corrected_right || '−'}</td>
                                                <td class="px-3 py-2 text-center text-sm">
                                                    ${vision.before_corrected_right && vision.after_corrected_right ? (() => {
                                                        const change = (parseFloat(vision.after_corrected_right) - parseFloat(vision.before_corrected_right)).toFixed(2);
                                                        const changeNum = parseFloat(change);
                                                        const color = changeNum > 0 ? 'text-green-600' : changeNum < 0 ? 'text-red-600' : 'text-gray-600';
                                                        return `<span class="${color} font-medium">${changeNum > 0 ? '+' : ''}${change}</span>`;
                                                    })() : '−'}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${vision.public_memo ? `
                            <div class="mt-3 p-3 bg-gray-50 rounded">
                                <p class="text-sm text-gray-700">${vision.public_memo}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('') : ''}
            
            ${record.next_visit_date ? `
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        次回来院予定: ${formatDate(record.next_visit_date)}
                    </div>
                </div>
            ` : ''}
        </div>
    `).join('');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        weekday: 'short'
    });
}
</script>

{{-- モバイル用固定ナビゲーションバー --}}
@include('components.mobile-nav')
@endsection