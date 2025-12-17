<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-PW6PX69M');</script>
    <!-- End Google Tag Manager -->

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', '目のトレーニング') - 目のトレーニング</title>
    
    <meta name="description" content="9割以上のお客様から視力回復、向上のお声を頂いております。目のトレーニング専門サロンで、独自のトレーニング技術をご体験ください。">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('test/metore_logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="57x57" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="60x60" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="72x72" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="114x114" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="120x120" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="144x144" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{ asset('test/metore_logo.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('test/metore_logo.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('test/metore_logo.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('test/metore_logo.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('test/metore_logo.png') }}">
    <meta name="msapplication-TileImage" content="{{ asset('test/metore_logo.png') }}">
    <meta name="msapplication-TileColor" content="#059669">
    <meta name="theme-color" content="#059669">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                        }
                    }
                }
            }
        }
    </script>

    @stack('styles')
</head>

<body class="h-full bg-gray-50 font-sans antialiased">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PW6PX69M"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <div class="min-h-full flex flex-col">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-20">
                    <!-- Logo -->
                    <div class="flex-shrink-0 flex items-center">
                        <a href="{{ url('/') }}" class="flex items-center">
                            <img src="{{ asset('images/logo.png') }}" alt="目のトレーニング" class="h-14 w-auto">
                        </a>
                    </div>

                    <!-- Navigation Links -->
                    <div class="hidden md:flex items-center space-x-8">
                        <a href="{{ url('/') }}" class="text-gray-600 hover:text-primary-600 px-3 py-2 text-sm font-medium transition-colors">
                            ホーム
                        </a>
                        <a href="{{ url('/stores') }}" class="text-gray-600 hover:text-primary-600 px-3 py-2 text-sm font-medium transition-colors">
                            店舗一覧
                        </a>
                        <a href="{{ url('/customer/login') }}" class="text-gray-600 hover:text-primary-600 px-3 py-2 text-sm font-medium transition-colors">
                            予約確認・カルテ
                        </a>
                        <a href="{{ url('/stores') }}" class="bg-gradient-to-r from-emerald-600 via-green-500 to-emerald-500 text-white px-6 py-2 rounded-lg text-sm font-medium hover:from-emerald-700 hover:via-green-600 hover:to-emerald-600 transition-all duration-300 shadow-md hover:shadow-lg nowrap-jp">
                            ご予約
                        </a>
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden" x-data="{ open: false }">
                        <button @click="open = !open" class="text-gray-600 hover:text-primary-600 p-2">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        
                        <!-- Mobile menu -->
                        <div x-show="open" @click.away="open = false" x-transition class="absolute top-20 right-4 bg-white rounded-lg shadow-lg py-2 px-4 min-w-[200px] z-50">
                            <a href="{{ url('/') }}" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-600">ホーム</a>
                            <a href="{{ url('/stores') }}" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-600">店舗一覧</a>
                            <a href="{{ url('/customer/login') }}" class="block px-3 py-2 text-sm text-gray-600 hover:text-primary-600">予約確認・カルテ</a>
                            <a href="{{ url('/stores') }}" class="block px-3 py-2 text-sm bg-gradient-to-r from-emerald-600 via-green-500 to-emerald-500 text-white rounded mt-2 text-center hover:from-emerald-700 hover:via-green-600 hover:to-emerald-600 transition-all duration-300 nowrap-jp">ご予約</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-gray-900 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="grid md:grid-cols-3 gap-8">
                    <!-- Company Info -->
                    <div class="md:col-span-2">
                        <div class="mb-4">
                            <img src="{{ asset('images/logo.png') }}" alt="目のトレーニング" class="h-16 w-auto filter brightness-0 invert">
                        </div>
                        <p class="text-gray-300 mb-4 max-w-md">
                            9割以上のお客様から視力回復、向上のお声を頂いております。最新の技術と専門的なトレーニングで、目の健康をサポートします。
                        </p>
                        <div class="text-sm text-gray-400">
                            <p>© 2025 目のトレーニング. All rights reserved.</p>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div>
                        <h4 class="text-lg font-semibold mb-4">クイックリンク</h4>
                        <ul class="space-y-2 text-sm">
                            <li><a href="{{ url('/') }}" class="text-gray-300 hover:text-white transition-colors">ホーム</a></li>
                            <li><a href="{{ url('/stores') }}" class="text-gray-300 hover:text-white transition-colors">店舗一覧</a></li>
                            <li><a href="{{ url('/stores') }}" class="text-gray-300 hover:text-white transition-colors">新規ご予約</a></li>
                            <li><a href="{{ url('/customer/login') }}" class="text-gray-300 hover:text-white transition-colors">予約確認・カルテ（マイページ）</a></li>
                            <li><a href="{{ url('/admin') }}" class="text-gray-300 hover:text-white transition-colors">スタッフ専用</a></li>
                        </ul>
                    </div>

                </div>
            </div>
        </footer>
    </div>

    <!-- Toast notifications -->
    <div x-data="{ 
        show: false, 
        message: '', 
        type: 'success',
        showToast(msg, toastType = 'success') {
            this.message = msg;
            this.type = toastType;
            this.show = true;
            setTimeout(() => this.show = false, 3000);
        }
    }" 
    @show-toast.window="showToast($event.detail.message, $event.detail.type)"
    x-show="show" 
    x-transition
    class="fixed top-4 right-4 z-50 max-w-sm">
        <div :class="{
            'bg-green-500': type === 'success',
            'bg-red-500': type === 'error',
            'bg-blue-500': type === 'info'
        }" class="text-white px-6 py-4 rounded-lg shadow-lg">
            <p x-text="message"></p>
        </div>
    </div>

    @stack('scripts')
</body>
</html>