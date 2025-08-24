@extends('layouts.app')

@section('title', 'カルテ・診療記録')

@section('content')
<div class="bg-gray-50 min-h-screen py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">カルテ・診療記録</h1>
                    <p class="text-gray-600" id="customer-info">
                        読み込み中...
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="/customer/reservations" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                        予約履歴
                    </a>
                    <a href="{{ url('/reservation') }}" class="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                        新規予約
                    </a>
                    <button id="logout-btn" class="bg-gray-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors">
                        ログアウト
                    </button>
                </div>
            </div>
        </div>

        <!-- Medical Records List -->
        <div id="medical-records-container">
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto"></div>
                <p class="text-gray-500 mt-2">カルテ情報を読み込み中...</p>
            </div>
        </div>

        <!-- Empty State -->
        <div id="empty-state" class="hidden bg-white rounded-lg shadow-md p-8 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">診療記録がありません</h3>
            <p class="text-gray-500 mb-6">まだ診療を受けていらっしゃいません。初回カウンセリングをご予約ください。</p>
            <a href="{{ url('/reservation') }}" class="bg-primary-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-primary-700 transition-colors inline-block">
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
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="border-b border-gray-200 pb-4 mb-4">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            診療記録 - ${formatDate(record.record_date)}
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
                                ${record.created_by ? `
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        担当: ${record.created_by.name}
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                    <div class="text-right">
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                            診療記録
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6">
                ${record.chief_complaint ? `
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">主訴・お悩み</h4>
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <p class="text-gray-700">${record.chief_complaint}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.symptoms ? `
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">症状・現状</h4>
                        <div class="bg-yellow-50 p-3 rounded-lg">
                            <p class="text-gray-700">${record.symptoms}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.diagnosis ? `
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">診断・所見</h4>
                        <div class="bg-green-50 p-3 rounded-lg">
                            <p class="text-gray-700">${record.diagnosis}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.treatment ? `
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">治療・施術内容</h4>
                        <div class="bg-purple-50 p-3 rounded-lg">
                            <p class="text-gray-700">${record.treatment}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.prescription ? `
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">処方・指導</h4>
                        <div class="bg-indigo-50 p-3 rounded-lg">
                            <p class="text-gray-700">${record.prescription}</p>
                        </div>
                    </div>
                ` : ''}
                
                ${record.medical_history ? `
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">既往歴・医療履歴</h4>
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="text-gray-700">${record.medical_history}</p>
                        </div>
                    </div>
                ` : ''}
            </div>
            
            ${record.notes ? `
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h4 class="font-semibold text-gray-900 mb-2">備考・その他</h4>
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-gray-700">${record.notes}</p>
                    </div>
                </div>
            ` : ''}
            
            ${record.next_visit_date ? `
                <div class="mt-4 pt-4 border-t border-gray-200">
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
@endsection