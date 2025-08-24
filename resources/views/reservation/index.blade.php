@extends('layouts.app')

@section('title', '予約')

@section('content')
<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- ヘッダー -->
        <div class="text-center mb-12">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">ご予約</h1>
            <p class="text-lg text-gray-600">お客様の状況に応じて選択してください</p>
        </div>

        <!-- 選択カード -->
        <div class="grid md:grid-cols-2 gap-8">
            <!-- 初回のお客様 -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6 text-white">
                    <div class="flex items-center justify-center mb-4">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-center">初めてのお客様</h2>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-6">
                        初めてXsyumenoをご利用のお客様はこちらから。簡単な情報入力で予約が完了します。
                    </p>
                    <ul class="space-y-2 mb-6">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-600">新規会員登録不要</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-600">電話番号で簡単予約</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-600">初回限定メニューあり</span>
                        </li>
                    </ul>
                    <a href="{{ url('/stores') }}" 
                       class="block w-full bg-blue-600 text-white text-center py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                        初回予約をする
                    </a>
                </div>
            </div>

            <!-- 2回目以降のお客様 -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="bg-gradient-to-r from-green-500 to-green-600 p-6 text-white">
                    <div class="flex items-center justify-center mb-4">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-center">2回目以降のお客様</h2>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-6">
                        既にご利用いただいているお客様はこちら。カルテ情報を元に最適なメニューをご提案します。
                    </p>
                    <ul class="space-y-2 mb-6">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-600">過去の施術履歴を参照</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-600">予約の変更・キャンセル可能</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-sm text-gray-600">リピーター特典あり</span>
                        </li>
                    </ul>
                    <a href="{{ url('/admin') }}" 
                       class="block w-full bg-green-600 text-white text-center py-3 px-6 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                        マイページにログイン
                    </a>
                </div>
            </div>
        </div>

        <!-- 注意事項 -->
        <div class="mt-12 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex items-start">
                <svg class="w-6 h-6 text-yellow-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <h3 class="font-semibold text-yellow-800 mb-2">ご注意事項</h3>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• 同一の電話番号で同日に複数の予約はできません</li>
                        <li>• 2回目以降のお客様は、前回の施術から期間が空いている場合、初回と同じ流れでご予約いただく場合があります</li>
                        <li>• 予約の変更・キャンセルは前日までにお願いします</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- お問い合わせ -->
        <div class="mt-8 text-center">
            <p class="text-gray-600">
                ご不明な点がございましたら、
                <a href="tel:0120-XXX-XXX" class="text-blue-600 hover:underline">0120-XXX-XXX</a>
                までお問い合わせください
            </p>
        </div>
    </div>
</div>
@endsection