# 🎨 フロントエンド設計書

## 概要

Xsyumeno Laravel版のフロントエンド設計書です。Blade + Alpine.js + Tailwind CSS を基盤とし、管理画面はFilamentを使用したモダンなWebアプリケーションを構築します。

## 技術スタック

### フロントエンド技術
- **Blade Templates**: Laravel標準テンプレートエンジン
- **Alpine.js 3.x**: 軽量フロントエンドフレームワーク
- **Tailwind CSS 3.x**: ユーティリティファーストCSS
- **Livewire**: リアクティブコンポーネント（必要に応じて）
- **Filament 3.x**: 管理画面

### ビルドツール
- **Vite**: 高速ビルドツール
- **PostCSS**: CSS処理
- **Autoprefixer**: ベンダープレフィックス自動付与

## アーキテクチャ設計

### フロントエンド構造
```
resources/
├── views/
│   ├── layouts/           # レイアウトテンプレート
│   │   ├── app.blade.php  # メインレイアウト
│   │   ├── guest.blade.php # ゲスト用レイアウト
│   │   └── components/    # レイアウトコンポーネント
│   ├── components/        # 再利用可能コンポーネント
│   │   ├── ui/           # UIコンポーネント
│   │   ├── forms/        # フォームコンポーネント
│   │   └── modals/       # モーダルコンポーネント
│   ├── pages/            # ページテンプレート
│   │   ├── home.blade.php
│   │   ├── stores/       # 店舗関連ページ
│   │   ├── auth/         # 認証ページ
│   │   └── customer/     # 顧客マイページ
│   └── errors/           # エラーページ
├── js/
│   ├── app.js           # メインJSファイル
│   ├── components/      # Alpine.jsコンポーネント
│   └── utils/          # ユーティリティ関数
└── css/
    └── app.css         # メインCSSファイル
```

## デザインシステム

### カラーパレット
```javascript
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8',
          900: '#1e3a8a',
        },
        success: {
          50: '#f0fdf4',
          500: '#22c55e',
          600: '#16a34a',
        },
        warning: {
          50: '#fffbeb',
          500: '#f59e0b',
          600: '#d97706',
        },
        danger: {
          50: '#fef2f2',
          500: '#ef4444',
          600: '#dc2626',
        },
        gray: {
          50: '#f9fafb',
          100: '#f3f4f6',
          500: '#6b7280',
          900: '#111827',
        }
      },
      fontFamily: {
        sans: ['Inter', 'Noto Sans JP', 'Hiragino Sans', 'sans-serif'],
      },
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
      }
    }
  }
}
```

### タイポグラフィ
```css
/* resources/css/app.css */
@import 'tailwindcss/base';
@import 'tailwindcss/components';
@import 'tailwindcss/utilities';

@layer base {
  html {
    font-feature-settings: "palt";
  }
  
  body {
    @apply font-sans text-gray-900 antialiased;
  }
  
  h1 { @apply text-3xl font-bold tracking-tight; }
  h2 { @apply text-2xl font-semibold tracking-tight; }
  h3 { @apply text-xl font-semibold; }
  h4 { @apply text-lg font-medium; }
  
  .text-body { @apply text-base leading-relaxed; }
  .text-small { @apply text-sm; }
  .text-xs { @apply text-xs; }
}
```

## レイアウトシステム

### メインレイアウト
```blade
{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ $title ?? config('app.name') }}</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Additional Head Content -->
    @stack('head')
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        @include('layouts.partials.header')
        
        <!-- Main Content -->
        <main>
            @yield('content')
        </main>
        
        <!-- Footer -->
        @include('layouts.partials.footer')
    </div>
    
    <!-- Modals -->
    @stack('modals')
    
    <!-- Scripts -->
    @stack('scripts')
</body>
</html>
```

### ヘッダーコンポーネント
```blade
{{-- resources/views/layouts/partials/header.blade.php --}}
<header class="bg-white shadow-sm border-b border-gray-200" x-data="{ mobileMenuOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="{{ route('home') }}" class="flex items-center">
                    <img class="h-8 w-auto" src="{{ asset('images/logo.svg') }}" alt="Xsyumeno">
                    <span class="ml-2 text-xl font-bold text-gray-900">Xsyumeno</span>
                </a>
            </div>
            
            <!-- Desktop Navigation -->
            <nav class="hidden md:flex space-x-8">
                <a href="{{ route('home') }}" 
                   class="text-gray-500 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">
                    ホーム
                </a>
                <a href="{{ route('stores.index') }}" 
                   class="text-gray-500 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">
                    店舗一覧
                </a>
                @auth('customer')
                    <a href="{{ route('customer.reservations') }}" 
                       class="text-gray-500 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">
                        予約管理
                    </a>
                @endauth
            </nav>
            
            <!-- User Menu -->
            <div class="flex items-center space-x-4">
                @guest('customer')
                    <a href="{{ route('auth.login') }}" 
                       class="bg-primary-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-primary-700 transition-colors">
                        ログイン
                    </a>
                @else
                    <div x-data="{ userMenuOpen: false }" class="relative">
                        <button @click="userMenuOpen = !userMenuOpen" 
                                class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900">
                            {{ auth('customer')->user()->full_name }}
                            <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <div x-show="userMenuOpen" 
                             @click.away="userMenuOpen = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="{{ route('customer.profile') }}" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                プロフィール
                            </a>
                            <a href="{{ route('customer.reservations') }}" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                予約履歴
                            </a>
                            <form method="POST" action="{{ route('auth.logout') }}">
                                @csrf
                                <button type="submit" 
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    ログアウト
                                </button>
                            </form>
                        </div>
                    </div>
                @endguest
                
                <!-- Mobile Menu Button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" 
                        class="md:hidden text-gray-500 hover:text-gray-900">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" 
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="transform opacity-0 scale-95"
             x-transition:enter-end="transform opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="transform opacity-100 scale-100"
             x-transition:leave-end="transform opacity-0 scale-95"
             class="md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="{{ route('home') }}" 
                   class="text-gray-700 hover:text-gray-900 block px-3 py-2 text-base font-medium">
                    ホーム
                </a>
                <a href="{{ route('stores.index') }}" 
                   class="text-gray-700 hover:text-gray-900 block px-3 py-2 text-base font-medium">
                    店舗一覧
                </a>
            </div>
        </div>
    </div>
</header>
```

## コンポーネントシステム

### Bladeコンポーネント定義

#### ボタンコンポーネント
```blade
{{-- resources/views/components/ui/button.blade.php --}}
@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'disabled' => false,
    'loading' => false,
    'href' => null
])

@php
$classes = [
    'inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed',
    
    // Variants
    'primary' => 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500',
    'secondary' => 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
    'success' => 'bg-success-600 text-white hover:bg-success-700 focus:ring-success-500',
    'danger' => 'bg-danger-600 text-white hover:bg-danger-700 focus:ring-danger-500',
    'outline' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-primary-500',
    
    // Sizes
    'sm' => 'px-3 py-1.5 text-sm',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-6 py-3 text-base',
][$variant] . ' ' . [
    'sm' => 'px-3 py-1.5 text-sm',
    'md' => 'px-4 py-2 text-sm',
    'lg' => 'px-6 py-3 text-base',
][$size];
@endphp

@if($href)
    <a href="{{ $href }}" 
       class="{{ $classes }}" 
       {{ $attributes->except(['href']) }}>
        @if($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" 
            class="{{ $classes }}" 
            @if($disabled || $loading) disabled @endif
            {{ $attributes->except(['type', 'variant', 'size', 'disabled', 'loading']) }}>
        @if($loading)
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
```

#### 入力フィールドコンポーネント
```blade
{{-- resources/views/components/forms/input.blade.php --}}
@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'required' => false,
    'error' => null,
    'help' => null,
    'value' => null
])

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-danger-500">*</span>
            @endif
        </label>
    @endif
    
    <input type="{{ $type }}" 
           id="{{ $name }}" 
           name="{{ $name }}"
           value="{{ old($name, $value) }}"
           @if($required) required @endif
           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm {{ $error ? 'border-danger-300 text-danger-900 placeholder-danger-300 focus:border-danger-500 focus:ring-danger-500' : '' }}"
           {{ $attributes->except(['label', 'name', 'type', 'required', 'error', 'help', 'value']) }}>
    
    @if($help)
        <p class="text-sm text-gray-500">{{ $help }}</p>
    @endif
    
    @if($error)
        <p class="text-sm text-danger-600">{{ $error }}</p>
    @endif
</div>
```

#### カードコンポーネント
```blade
{{-- resources/views/components/ui/card.blade.php --}}
@props([
    'header' => null,
    'footer' => null,
    'padding' => true
])

<div class="bg-white shadow rounded-lg overflow-hidden" {{ $attributes }}>
    @if($header)
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            {{ $header }}
        </div>
    @endif
    
    <div class="{{ $padding ? 'px-4 py-5 sm:p-6' : '' }}">
        {{ $slot }}
    </div>
    
    @if($footer)
        <div class="px-4 py-3 sm:px-6 bg-gray-50 border-t border-gray-200">
            {{ $footer }}
        </div>
    @endif
</div>
```

## Alpine.js コンポーネント

### OTP認証コンポーネント
```javascript
// resources/js/components/otp-form.js
document.addEventListener('alpine:init', () => {
    Alpine.data('otpForm', () => ({
        phone: '',
        otpCode: '',
        step: 'phone', // 'phone' | 'otp' | 'register'
        loading: false,
        countdown: 0,
        errors: {},
        
        async sendOtp() {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch('/api/auth/send-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ phone: this.phone })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.step = 'otp';
                    this.startCountdown();
                } else {
                    this.errors = data.error.details || { phone: [data.error.message] };
                }
            } catch (error) {
                this.errors = { phone: ['通信エラーが発生しました'] };
            } finally {
                this.loading = false;
            }
        },
        
        async verifyOtp() {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await fetch('/api/auth/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        phone: this.phone,
                        otp_code: this.otpCode
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (data.data.is_new_customer) {
                        this.step = 'register';
                        // 一時トークンを保存
                        sessionStorage.setItem('temp_token', data.data.temp_token);
                    } else {
                        // ログイン成功
                        localStorage.setItem('customer_token', data.data.token);
                        window.location.href = '/customer/dashboard';
                    }
                } else {
                    this.errors = { otp_code: [data.error.message] };
                }
            } catch (error) {
                this.errors = { otp_code: ['通信エラーが発生しました'] };
            } finally {
                this.loading = false;
            }
        },
        
        startCountdown() {
            this.countdown = 60;
            const timer = setInterval(() => {
                this.countdown--;
                if (this.countdown <= 0) {
                    clearInterval(timer);
                }
            }, 1000);
        },
        
        formatCountdown() {
            const minutes = Math.floor(this.countdown / 60);
            const seconds = this.countdown % 60;
            return `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
    }));
});
```

### 予約カレンダーコンポーネント
```javascript
// resources/js/components/reservation-calendar.js
document.addEventListener('alpine:init', () => {
    Alpine.data('reservationCalendar', () => ({
        selectedDate: null,
        selectedTime: null,
        availableSlots: [],
        currentMonth: new Date(),
        loading: false,
        
        init() {
            this.selectedDate = this.formatDate(new Date());
            this.loadAvailableSlots();
        },
        
        async loadAvailableSlots() {
            if (!this.selectedDate) return;
            
            this.loading = true;
            
            try {
                const storeId = this.$el.dataset.storeId;
                const response = await fetch(`/api/stores/${storeId}/availability?date=${this.selectedDate}`);
                const data = await response.json();
                
                if (data.success) {
                    this.availableSlots = data.data.available_slots;
                }
            } catch (error) {
                console.error('空き状況の取得に失敗:', error);
            } finally {
                this.loading = false;
            }
        },
        
        selectDate(date) {
            this.selectedDate = this.formatDate(date);
            this.selectedTime = null;
            this.loadAvailableSlots();
        },
        
        selectTime(slot) {
            this.selectedTime = slot.start_time;
            // 予約フォームに値を設定
            this.$dispatch('time-selected', {
                date: this.selectedDate,
                time: this.selectedTime,
                staffId: slot.staff_id
            });
        },
        
        formatDate(date) {
            return date.toISOString().split('T')[0];
        },
        
        isToday(date) {
            const today = new Date();
            return date.toDateString() === today.toDateString();
        },
        
        isPast(date) {
            const today = new Date();
            return date < today;
        }
    }));
});
```

## ページテンプレート

### ホームページ
```blade
{{-- resources/views/pages/home.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="min-h-screen">
    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-primary-600 to-primary-800 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-4xl md:text-6xl font-bold mb-6">
                    視力改善への<br>新しいアプローチ
                </h1>
                <p class="text-xl md:text-2xl mb-8 opacity-90">
                    科学的根拠に基づいた視力トレーニングで、<br>あなたの視力を改善しませんか？
                </p>
                <div class="space-x-4">
                    <x-ui.button href="{{ route('stores.index') }}" size="lg">
                        店舗を探す
                    </x-ui.button>
                    <x-ui.button href="{{ route('auth.login') }}" variant="outline" size="lg" class="bg-white text-primary-600 hover:bg-gray-50">
                        ログイン
                    </x-ui.button>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">
                    Xsyumenoの特徴
                </h2>
                <p class="text-lg text-gray-600">
                    最新の視力トレーニング技術で、あなたの視力改善をサポートします
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="bg-primary-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">科学的アプローチ</h3>
                    <p class="text-gray-600">
                        最新の視力科学研究に基づいた効果的なトレーニング方法を提供
                    </p>
                </div>
                
                <div class="text-center">
                    <div class="bg-primary-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">専門スタッフ</h3>
                    <p class="text-gray-600">
                        経験豊富な視力トレーニング専門スタッフがサポート
                    </p>
                </div>
                
                <div class="text-center">
                    <div class="bg-primary-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">効果測定</h3>
                    <p class="text-gray-600">
                        定期的な視力測定で改善効果を数値で確認
                    </p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="bg-gray-50 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
                今すぐ始めてみませんか？
            </h2>
            <p class="text-lg text-gray-600 mb-8">
                無料カウンセリングで、あなたに最適なトレーニングプランをご提案します
            </p>
            <x-ui.button href="{{ route('stores.index') }}" size="lg">
                店舗を探して予約する
            </x-ui.button>
        </div>
    </section>
</div>
@endsection
```

### 認証ページ
```blade
{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.guest')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <h2 class="mt-6 text-3xl font-bold text-gray-900">
                ログイン
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                電話番号でログインできます
            </p>
        </div>
        
        <div x-data="otpForm" class="mt-8 space-y-6">
            <!-- 電話番号入力ステップ -->
            <div x-show="step === 'phone'">
                <form @submit.prevent="sendOtp">
                    <x-forms.input 
                        name="phone"
                        label="電話番号"
                        type="tel"
                        placeholder="090-1234-5678"
                        required
                        x-model="phone"
                        :error="$errors->first('phone')"
                    />
                    
                    <div class="mt-6">
                        <x-ui.button 
                            type="submit" 
                            size="lg" 
                            class="w-full"
                            :loading="loading">
                            認証コードを送信
                        </x-ui.button>
                    </div>
                </form>
            </div>
            
            <!-- OTP入力ステップ -->
            <div x-show="step === 'otp'">
                <div class="text-center mb-4">
                    <p class="text-sm text-gray-600">
                        <span x-text="phone"></span> に認証コードを送信しました
                    </p>
                </div>
                
                <form @submit.prevent="verifyOtp">
                    <x-forms.input 
                        name="otp_code"
                        label="認証コード"
                        type="text"
                        placeholder="123456"
                        maxlength="6"
                        required
                        x-model="otpCode"
                    />
                    
                    <div class="mt-6 space-y-3">
                        <x-ui.button 
                            type="submit" 
                            size="lg" 
                            class="w-full"
                            :loading="loading">
                            ログイン
                        </x-ui.button>
                        
                        <div class="text-center">
                            <button 
                                @click="sendOtp" 
                                :disabled="countdown > 0"
                                class="text-sm text-primary-600 hover:text-primary-500 disabled:text-gray-400">
                                <span x-show="countdown > 0">
                                    再送信まで <span x-text="formatCountdown()"></span>
                                </span>
                                <span x-show="countdown === 0">
                                    認証コードを再送信
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- 会員登録ステップ -->
            <div x-show="step === 'register'">
                @include('auth.partials.register-form')
            </div>
        </div>
    </div>
</div>
@endsection
```

## レスポンシブデザイン

### ブレークポイント戦略
```css
/* Tailwind CSS デフォルトブレークポイント */
sm: 640px   /* タブレット */
md: 768px   /* 小型ラップトップ */
lg: 1024px  /* デスクトップ */
xl: 1280px  /* 大型デスクトップ */
2xl: 1536px /* 超大型ディスプレイ */
```

### モバイルファースト設計
- デフォルトはモバイル向けスタイル
- 大きな画面向けにプログレッシブエンハンスメント
- タッチインターフェースを考慮した設計

## パフォーマンス最適化

### CSS最適化
```javascript
// tailwind.config.js
module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './app/View/Components/**/*.php',
  ],
  
  // 未使用CSSの削除
  purge: {
    enabled: process.env.NODE_ENV === 'production',
  }
}
```

### JavaScript最適化
```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['alpinejs'],
                }
            }
        }
    }
});
```

このフロントエンド設計により、モダンで使いやすく、パフォーマンスに優れたWebアプリケーションを構築できます。