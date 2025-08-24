@extends('layouts.app')

@section('title', '目のトレーニング - 視る力を、鍛える。')

@section('content')
<!-- Hero Section -->
<section class="min-h-screen relative overflow-hidden bg-gradient-to-br from-slate-900 to-emerald-900">
    <!-- Background Image Slider -->
    <div class="absolute inset-0 hero-slider">
        @php
            $heroImages = [
                '/test/fv/DSC01230.jpg',
                '/test/fv/DSC01237.jpg',
                '/test/fv/DSC01323.jpg',
                '/test/fv/DSC01570.jpg',
                '/test/fv/DSC01591.jpg'
            ];
        @endphp
        
        @foreach($heroImages as $index => $image)
            <div class="hero-slide absolute inset-0 overflow-hidden" 
                 style="opacity: {{ $index === 0 ? '1' : '0' }}; z-index: {{ $index === 0 ? '1' : '0' }};" 
                 data-slide="{{ $index }}">
                <!-- Background Image -->
                <img src="{{ $image }}" 
                     alt="背景画像{{ $index + 1 }}" 
                     class="absolute inset-0 w-full h-full object-cover"
                     onerror="console.error('画像読み込みエラー:', this.src)">
                <!-- Background Overlay -->
                <div class="absolute inset-0 bg-gradient-to-br from-slate-900/75 via-emerald-900/70 to-slate-900/80"></div>
            </div>
        @endforeach
        
        <!-- Elegant Pattern Overlay -->
        <div class="absolute inset-0 opacity-10 z-10" style="background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="white" fill-opacity="0.1"%3E%3Cpath d="M30 30c0-16.569-13.431-30-30-30v60c16.569 0 30-13.431 30-30zM0 0h60v60H0z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
    </div>
    
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-32 z-20">
        <div class="text-center">
            <!-- Logo -->
            <div class="mb-16 animate-fade-in">
                <img src="{{ asset('test/metore_logo.png') }}" alt="目のトレーニング" class="h-20 mx-auto mb-8 filter drop-shadow-lg">
            </div>
            
            <!-- Main Headline -->
            <div class="mb-12 animate-slide-up">
                <h1 class="font-serif font-bold text-white mb-8 leading-[1.1] tracking-wide" style="font-size: clamp(3rem, 8vw, 6rem);">
                    <span class="whitespace-nowrap">あなたの<span class="bg-gradient-to-r from-emerald-300 via-green-200 to-teal-300 bg-clip-text text-transparent">"目"</span>に、</span><br>
                    <span class="bg-gradient-to-r from-teal-300 via-emerald-200 to-green-300 bg-clip-text text-transparent font-light whitespace-nowrap">視る力</span><span class="whitespace-nowrap">を。</span>
                </h1>
            </div>
            
            <!-- Subtitle -->
            <div class="mb-16 animate-slide-up delay-200">
                <p class="font-light tracking-wider text-emerald-100 mb-6" style="font-size: clamp(1.25rem, 3vw, 1.875rem);">
                    <span class="whitespace-nowrap">スマホ・PC時代の救世主。</span>
                </p>
                <div class="font-light text-gray-300 max-w-4xl mx-auto leading-relaxed" style="font-size: clamp(1rem, 2.5vw, 1.5rem);">
                    <span class="whitespace-nowrap">視力・疲れ目・老眼・ドライアイまで、</span><br class="hidden md:block">
                    <span class="whitespace-nowrap">目の悩みに寄り添う新感覚サロン。</span>
                </div>
            </div>
            
            <!-- CTA Button -->
            <div class="mb-20 animate-slide-up delay-300">
                <a href="{{ url('/stores') }}" 
                   class="group inline-flex items-center px-16 py-6 bg-gradient-to-r from-emerald-600 via-green-500 to-emerald-500 text-white text-xl font-medium rounded-full hover:from-emerald-700 hover:via-green-600 hover:to-emerald-600 transition-all duration-500 transform hover:scale-105 shadow-2xl hover:shadow-emerald-500/30 border border-emerald-400/30">
                    <span class="mr-3 tracking-wide whitespace-nowrap">ご予約はこちら</span>
                    <svg class="w-6 h-6 transition-transform group-hover:translate-x-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </a>
            </div>
            
            <!-- 既存お客様向けリンク -->
            <div class="animate-fade-in delay-500">
                <a href="{{ url('/customer/login') }}" 
                   class="inline-flex items-center gap-3 text-emerald-200 hover:text-white transition-colors duration-300 text-base border border-emerald-400/50 px-6 py-3 rounded-full hover:border-emerald-300 backdrop-blur-sm bg-white/5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    2回目以降のお客様はこちら（予約確認・変更）
                </a>
            </div>
        </div>
    </div>
    
    <!-- Scroll Indicator -->
    <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 animate-bounce z-20">
        <div class="w-6 h-10 border-2 border-emerald-300 rounded-full flex justify-center">
            <div class="w-1 h-3 bg-emerald-300 rounded-full mt-2 animate-pulse"></div>
        </div>
    </div>
</section>

<!-- Brand Message Section -->
<section class="py-32 bg-gradient-to-br from-white to-emerald-50 relative overflow-hidden">
    <div class="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-emerald-200 to-transparent"></div>
    
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-20">
            <h2 class="font-serif font-light text-slate-900 mb-12 leading-[1.2] tracking-wide text-responsive-5xl prevent-orphan">
                <span class="nowrap-jp">目の専門サロンとして</span><br class="md:hidden">
                <span class="bg-gradient-to-r from-emerald-700 via-green-600 to-emerald-600 bg-clip-text text-transparent font-medium nowrap-jp">全国に展開</span>
            </h2>
            
            <div class="max-w-5xl mx-auto space-y-8">
                <p class="text-slate-700 leading-relaxed font-light text-responsive-2xl smart-break">
                    <span class="nowrap-jp">特許取得機器を用いた"目の筋肉ケア"で、</span><br>
                    <span class="nowrap-jp">その場で視力回復を実感する方が</span><span class="text-emerald-700 font-bold text-responsive-3xl">90%</span><span class="nowrap-jp">以上。</span>
                </p>
                
                <div class="bg-gradient-to-r from-emerald-50 via-green-50 to-emerald-50 rounded-3xl p-12 md:p-16 border border-emerald-100 shadow-lg">
                    <p class="text-emerald-900 leading-relaxed font-light tracking-wide text-responsive-xl smart-break">
                        <span class="nowrap-jp">「見えにくい」「疲れる」から解放され、</span><br>
                        <span class="nowrap-jp">日常がクリアに変わっていく体験をお届けします。</span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- 実績画像プレースホルダー -->
        <div class="grid md:grid-cols-3 gap-8 mb-16">
            <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-3xl h-56 flex items-center justify-center border border-emerald-200 shadow-lg hover:shadow-xl transition-shadow duration-300">
                <div class="text-center text-emerald-600">
                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p class="text-sm font-medium">実績グラフ画像</p>
                </div>
            </div>
            <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-3xl h-56 flex items-center justify-center border border-emerald-200 shadow-lg hover:shadow-xl transition-shadow duration-300">
                <div class="text-center text-emerald-600">
                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                    <p class="text-sm font-medium">お客様の声画像</p>
                </div>
            </div>
            <div class="bg-gradient-to-br from-emerald-50 to-green-100 rounded-3xl h-56 flex items-center justify-center border border-emerald-200 shadow-lg hover:shadow-xl transition-shadow duration-300">
                <div class="text-center text-emerald-600">
                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <p class="text-sm font-medium">店舗展開マップ</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Before/After Section -->
<section class="py-32 bg-gradient-to-br from-slate-50 to-emerald-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <!-- Before -->
            <div class="space-y-8">
                <div class="text-center lg:text-left">
                    <h3 class="text-3xl md:text-4xl font-bold text-gray-900 mb-8">
                        こんなお悩みありませんか？
                    </h3>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-start space-x-3 p-4 bg-white rounded-xl shadow-sm">
                        <div class="flex-shrink-0 w-6 h-6 bg-red-100 rounded-full flex items-center justify-center mt-1">
                            <span class="text-red-600 text-sm font-bold">✕</span>
                        </div>
                        <p class="text-gray-800 font-medium">仕事や勉強で夕方には視界がぼやける</p>
                    </div>
                    
                    <div class="flex items-start space-x-3 p-4 bg-white rounded-xl shadow-sm">
                        <div class="flex-shrink-0 w-6 h-6 bg-red-100 rounded-full flex items-center justify-center mt-1">
                            <span class="text-red-600 text-sm font-bold">✕</span>
                        </div>
                        <p class="text-gray-800 font-medium">最近、老眼鏡が手放せない</p>
                    </div>
                    
                    <div class="flex items-start space-x-3 p-4 bg-white rounded-xl shadow-sm">
                        <div class="flex-shrink-0 w-6 h-6 bg-red-100 rounded-full flex items-center justify-center mt-1">
                            <span class="text-red-600 text-sm font-bold">✕</span>
                        </div>
                        <p class="text-gray-800 font-medium">目の奥が重い・痛い</p>
                    </div>
                    
                    <div class="flex items-start space-x-3 p-4 bg-white rounded-xl shadow-sm">
                        <div class="flex-shrink-0 w-6 h-6 bg-red-100 rounded-full flex items-center justify-center mt-1">
                            <span class="text-red-600 text-sm font-bold">✕</span>
                        </div>
                        <p class="text-gray-800 font-medium">ドライアイで集中できない</p>
                    </div>
                </div>
            </div>
            
            <!-- After -->
            <div class="text-center lg:text-left">
                <div class="bg-gradient-to-r from-emerald-700 via-green-600 to-emerald-600 rounded-3xl p-12 md:p-16 text-white shadow-2xl">
                    <div class="flex items-center justify-center lg:justify-start mb-8">
                        <div class="w-16 h-16 bg-white/90 rounded-full flex items-center justify-center mr-6">
                            <svg class="w-8 h-8 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h3 class="font-serif text-3xl md:text-4xl font-light tracking-wide">そんな悩みを、30分の施術でクリアに。</h3>
                    </div>
                    
                    <div class="space-y-6 mb-10">
                        <p class="text-2xl font-light text-emerald-100 tracking-wide">「目が軽くなった」</p>
                        <p class="text-2xl font-light text-emerald-100 tracking-wide">「裸眼で見える世界が違う」</p>
                    </div>
                    
                    <p class="text-xl leading-relaxed font-light">
                        利用者の声が広がり、いま全国で話題です。
                    </p>
                </div>
                
                <!-- Before/After 画像プレースホルダー -->
                <div class="mt-10">
                    <div class="bg-gradient-to-r from-emerald-50 to-green-100 rounded-3xl h-80 flex items-center justify-center border border-emerald-200 shadow-xl">
                        <div class="text-center text-emerald-600">
                            <svg class="w-20 h-20 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-base font-medium">Before/After比較画像</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust Section -->
<section class="py-24 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-20">
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-8">
                <span class="bg-gradient-to-r from-blue-600 to-cyan-600 bg-clip-text text-transparent">ブランドの信頼性</span>
            </h2>
        </div>
        
        <div class="grid md:grid-cols-2 gap-16 items-center mb-20">
            <!-- Trust Points -->
            <div class="space-y-8">
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.707-4.293c1.392 1.465 2.293 3.453 2.293 5.707 0 4.418-3.582 8-8 8s-8-3.582-8-8c0-2.254.901-4.242 2.293-5.707m11.414 5.707c0 1.657-1.343 3-3 3s-3-1.343-3-3 1.343-3 3-3 3 1.343 3 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">特許取得機器による安心施術</h3>
                        <p class="text-gray-600 leading-relaxed">厚生労働省認可の特許技術を使用し、安全で確実な効果を提供します。</p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">専門研修を受けたスタッフが対応</h3>
                        <p class="text-gray-600 leading-relaxed">視覚トレーニングの専門知識を持つ認定スタッフが、丁寧にサポート。</p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">全国直営＆フランチャイズ展開</h3>
                        <p class="text-gray-600 leading-relaxed">銀座・新宿・吉祥寺・名古屋・宮崎…全国各地で同じ品質のサービスを提供。</p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">医師・専門家も注目のケア</h3>
                        <p class="text-gray-600 leading-relaxed">眼科医師や視覚研究の専門家からも高い評価を受けている技術です。</p>
                    </div>
                </div>
            </div>
            
            <!-- Trust Image -->
            <div class="space-y-6">
                <!-- 特許機器・施術風景画像 -->
                <div class="relative rounded-3xl overflow-hidden border border-emerald-200 shadow-xl">
                    <img src="{{ asset('test/brand/DSC01254.jpg') }}" 
                         alt="特許機器・施術風景" 
                         class="w-full h-96 object-cover">
                    <div class="bg-gradient-to-t from-emerald-900/90 to-transparent absolute bottom-0 left-0 right-0 p-6">
                        <p class="text-white text-lg font-medium">特許取得機器による施術風景</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-6">
                    <!-- 認定証 -->
                    <div class="rounded-3xl overflow-hidden border border-emerald-200 shadow-lg group hover:shadow-xl transition-shadow duration-300">
                        <div class="relative">
                            <img src="{{ asset('test/brand/DSC01290.jpg') }}" 
                                 alt="認定証" 
                                 class="w-full h-40 object-cover group-hover:scale-105 transition-transform duration-300">
                            <div class="absolute inset-0 bg-gradient-to-t from-emerald-900/70 to-transparent flex items-end">
                                <p class="text-white text-sm font-medium p-4">認定証</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 専門医推薦 -->
                    <div class="rounded-3xl overflow-hidden border border-emerald-200 shadow-lg group hover:shadow-xl transition-shadow duration-300">
                        <div class="relative">
                            <img src="{{ asset('test/brand/DSC01311.jpg') }}" 
                                 alt="専門医推薦" 
                                 class="w-full h-40 object-cover group-hover:scale-105 transition-transform duration-300">
                            <div class="absolute inset-0 bg-gradient-to-t from-emerald-900/70 to-transparent flex items-end">
                                <p class="text-white text-sm font-medium p-4">専門医推薦</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mission Statement -->
        <div class="text-center">
            <div class="bg-gradient-to-r from-emerald-50 via-green-50 to-emerald-50 rounded-3xl p-16 md:p-20 border border-emerald-200 shadow-xl">
                <h3 class="font-serif text-3xl md:text-4xl font-light text-slate-900 mb-8 tracking-wide">
                    私たちの使命は、
                </h3>
                <p class="text-2xl md:text-3xl font-light text-emerald-800 leading-relaxed tracking-wide">
                    「目の健康を守り、人生をもっとクリアにすること」。
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Stores Section -->
<section class="py-32 bg-gradient-to-br from-emerald-900 via-slate-900 to-emerald-800 relative overflow-hidden">
    <!-- Background Effects -->
    <div class="absolute inset-0 bg-gradient-to-r from-emerald-600/20 to-green-500/20"></div>
    <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"white\" fill-opacity=\"0.03\"%3E%3Cpath d=\"M30 30c0-16.569-13.431-30-30-30v60c16.569 0 30-13.431 30-30zM0 0h60v60H0z\"/%3E%3C/g%3E%3C/svg%3E')"></div>
    
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <!-- Main CTA -->
        <div class="mb-20">
            <h2 class="font-serif font-light text-white mb-12 leading-[1.2] tracking-wide text-responsive-6xl">
                <span class="nowrap-jp">まずは、体験から。</span>
            </h2>
            
            <div class="bg-gradient-to-r from-emerald-600 via-green-500 to-emerald-500 rounded-3xl p-16 md:p-20 mb-16 border border-emerald-400/30 shadow-2xl">
                <div class="text-center">
                    <p class="font-light text-white mb-6 tracking-wide text-responsive-4xl nowrap-jp">初回体験</p>
                    <div class="flex items-baseline justify-center gap-3 mb-8">
                        <span class="font-serif text-6xl md:text-8xl font-light text-white">30</span>
                        <span class="text-3xl md:text-4xl font-light text-emerald-100">分</span>
                    </div>
                    <div class="flex items-baseline justify-center gap-3 mb-12">
                        <span class="font-serif text-5xl md:text-7xl font-medium text-yellow-200">¥3,300</span>
                        <span class="text-2xl md:text-3xl font-light text-emerald-100">（税込）</span>
                    </div>
                    
                    <a href="{{ url('/stores') }}" 
                       class="inline-flex items-center px-20 py-8 bg-white text-emerald-700 text-2xl md:text-3xl font-medium rounded-full hover:bg-emerald-50 transition-all duration-500 transform hover:scale-105 shadow-2xl hover:shadow-white/25 border border-white/20">
                        <span class="mr-4 tracking-wide">今すぐ予約する</span>
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Store Access -->
        <div class="bg-white/10 backdrop-blur-sm rounded-3xl p-12 md:p-16 border border-white/20">
            <h3 class="font-serif text-3xl md:text-4xl font-light text-white mb-12 tracking-wide">全国の店舗でお待ちしています</h3>
            
            <!-- 動的店舗表示 -->
            @php
                $stores = \App\Models\Store::where('is_active', true)->limit(6)->get();
            @endphp
            
            @if($stores->count() > 0)
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
                    @foreach($stores as $store)
                        <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 group">
                            <div class="text-center">
                                <div class="w-20 h-20 bg-gradient-to-r from-emerald-400 to-green-400 rounded-2xl flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <h4 class="text-xl font-medium text-white mb-2 tracking-wide">{{ $store->name }}</h4>
                                <p class="text-emerald-200 text-sm mb-3">{{ $store->address ?? '駅近立地' }}</p>
                                <div class="text-xs text-emerald-300">
                                    @if($store->business_hours_start && $store->business_hours_end)
                                        {{ $store->business_hours_start }} - {{ $store->business_hours_end }}
                                    @else
                                        9:00 - 18:00
                                    @endif
                                </div>
                                <a href="{{ url('/stores/' . $store->id . '/reservation') }}" 
                                   class="mt-4 inline-block bg-emerald-500 hover:bg-emerald-400 text-white px-4 py-2 rounded-lg text-sm transition-colors duration-300">
                                    この店舗で予約
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="grid md:grid-cols-3 gap-6 mb-12">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <p class="text-white font-medium">銀座・新宿・吉祥寺</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <p class="text-white font-medium">名古屋・大阪・福岡</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-white font-medium">宮崎・札幌・沖縄</p>
                    </div>
                </div>
            @endif
            
            <a href="{{ url('/stores') }}" 
               class="inline-flex items-center px-10 py-4 border-2 border-emerald-300 text-white font-medium rounded-full hover:bg-emerald-300 hover:text-emerald-900 transition-all duration-300 text-lg">
                <span class="mr-3 tracking-wide">全店舗を見る</span>
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- Footer Section -->
<footer class="bg-emerald-950 py-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <!-- Logo -->
            <div class="mb-8">
                <img src="{{ asset('test/metore_logo.png') }}" alt="目のトレーニング" class="h-12 mx-auto mb-4">
            </div>
            
            <!-- Company Info -->
            <div class="space-y-4 text-gray-300">
                <h3 class="font-serif text-2xl font-medium text-white tracking-wide">目のトレーニング株式会社</h3>
                <p class="text-xl font-light tracking-wider text-emerald-200">― 視る力を、鍛える。 ―</p>
            </div>
            
            <!-- Contact Info -->
            @php
                $activeStores = \App\Models\Store::where('is_active', true)->count();
                $mainStore = \App\Models\Store::where('is_active', true)->first();
            @endphp
            
            <div class="mt-16 grid md:grid-cols-3 gap-12 text-sm text-emerald-300">
                <div>
                    <p class="font-medium text-emerald-200 mb-3 tracking-wide nowrap-jp">お問い合わせ</p>
                    @if($mainStore && $mainStore->phone)
                        <p class="nowrap-jp">{{ $mainStore->phone }}</p>
                    @else
                        <p class="nowrap-jp">お電話での予約受付中</p>
                    @endif
                    <p class="text-xs text-emerald-400 nowrap-jp">（WEB予約が便利です）</p>
                </div>
                
                <div>
                    <p class="font-medium text-emerald-200 mb-3 tracking-wide nowrap-jp">営業時間</p>
                    @if($mainStore && $mainStore->business_hours_start && $mainStore->business_hours_end)
                        <p class="nowrap-jp">{{ $mainStore->business_hours_start }} - {{ $mainStore->business_hours_end }}</p>
                    @else
                        <p class="nowrap-jp">9:00 - 18:00</p>
                    @endif
                    <p class="text-xs text-emerald-400 nowrap-jp">（店舗により異なります）</p>
                </div>
                
                <div>
                    <p class="font-medium text-emerald-200 mb-3 tracking-wide nowrap-jp">店舗数</p>
                    <p class="nowrap-jp">全国 {{ $activeStores }}店舗</p>
                    <p class="text-xs text-emerald-400 nowrap-jp">駅近立地・アクセス良好</p>
                </div>
            </div>
            
            <!-- Links -->
            <div class="mt-16 pt-10 border-t border-emerald-800 flex flex-col md:flex-row items-center justify-between">
                <div class="flex flex-wrap justify-center md:justify-start gap-4 md:gap-8 mb-6 md:mb-0">
                    <a href="{{ url('/customer/login') }}" class="text-emerald-300 hover:text-white transition-colors text-base font-light tracking-wide nowrap-jp">
                        予約確認・変更
                    </a>
                    <a href="{{ url('/stores') }}" class="text-emerald-300 hover:text-white transition-colors text-base font-light tracking-wide nowrap-jp">
                        店舗一覧
                    </a>
                    <a href="{{ url('/admin') }}" class="text-emerald-300 hover:text-white transition-colors text-base font-light tracking-wide nowrap-jp">
                        スタッフログイン
                    </a>
                </div>
                
                <p class="text-emerald-400 text-sm font-light nowrap-jp">
                    © {{ date('Y') }} 目のトレーニング株式会社. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>

<style>
    /* Import Google Fonts for Luxury Typography */
    @import url('https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@200;300;400;500;600;700;900&family=Noto+Sans+JP:wght@100;200;300;400;500;600;700;800;900&display=swap');
    
    /* Root Font Size for REM Management */
    :root {
        font-size: 16px;
    }
    
    @media (max-width: 768px) {
        :root {
            font-size: 14px;
        }
    }
    
    @media (max-width: 480px) {
        :root {
            font-size: 13px;
        }
    }
    
    /* Luxury Typography */
    .font-serif {
        font-family: 'Noto Serif JP', 'Times New Roman', serif;
        font-optical-sizing: auto;
        letter-spacing: 0.025em;
    }
    
    body {
        font-family: 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-weight: 300;
        letter-spacing: 0.01em;
        line-height: 1.7;
    }
    
    /* Responsive Typography Classes */
    .text-responsive-xs { font-size: clamp(0.75rem, 2vw, 0.875rem); }
    .text-responsive-sm { font-size: clamp(0.875rem, 2.5vw, 1rem); }
    .text-responsive-base { font-size: clamp(1rem, 3vw, 1.125rem); }
    .text-responsive-lg { font-size: clamp(1.125rem, 3.5vw, 1.25rem); }
    .text-responsive-xl { font-size: clamp(1.25rem, 4vw, 1.5rem); }
    .text-responsive-2xl { font-size: clamp(1.5rem, 5vw, 2rem); }
    .text-responsive-3xl { font-size: clamp(1.875rem, 6vw, 2.5rem); }
    .text-responsive-4xl { font-size: clamp(2.25rem, 7vw, 3rem); }
    .text-responsive-5xl { font-size: clamp(3rem, 8vw, 4rem); }
    .text-responsive-6xl { font-size: clamp(3.5rem, 10vw, 5rem); }
    .text-responsive-7xl { font-size: clamp(4rem, 12vw, 6rem); }
    
    /* Line Break Control */
    .prevent-orphan {
        text-wrap: balance;
        word-break: keep-all;
        overflow-wrap: break-word;
    }
    
    .smart-break {
        word-break: keep-all;
        line-break: strict;
    }
    
    /* No Wrap Utility for Japanese */
    .nowrap-jp {
        white-space: nowrap;
        word-break: keep-all;
    }
    
    /* Premium Animations */
    @keyframes fade-in {
        from { 
            opacity: 0; 
            transform: translateY(30px) scale(0.95);
            filter: blur(2px);
        }
        to { 
            opacity: 1; 
            transform: translateY(0) scale(1);
            filter: blur(0);
        }
    }
    
    @keyframes slide-up {
        from { 
            opacity: 0; 
            transform: translateY(50px) rotateX(10deg);
        }
        to { 
            opacity: 1; 
            transform: translateY(0) rotateX(0);
        }
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }
    
    .animate-fade-in {
        animation: fade-in 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        opacity: 0;
    }
    
    .animate-slide-up {
        animation: slide-up 1s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        opacity: 0;
    }
    
    .animate-float {
        animation: float 3s ease-in-out infinite;
    }
    
    .delay-200 { animation-delay: 0.2s; }
    .delay-300 { animation-delay: 0.3s; }
    .delay-400 { animation-delay: 0.4s; }
    .delay-500 { animation-delay: 0.5s; }
    
    /* Luxury Gradient Text */
    .bg-clip-text {
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    /* Premium Transitions */
    * {
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    
    /* Luxury Scrollbar */
    ::-webkit-scrollbar {
        width: 10px;
    }
    
    ::-webkit-scrollbar-track {
        background: linear-gradient(to bottom, #f0fdf4, #ecfdf5);
    }
    
    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #059669, #10b981, #34d399);
        border-radius: 12px;
        border: 2px solid #f0fdf4;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, #047857, #059669, #10b981);
    }
    
    /* Glass Morphism Effect */
    .backdrop-blur-sm {
        backdrop-filter: blur(8px) saturate(180%);
        -webkit-backdrop-filter: blur(8px) saturate(180%);
    }
    
    /* Luxury Shadow Effects */
    .shadow-luxury {
        box-shadow: 
            0 10px 25px -5px rgba(5, 150, 105, 0.1),
            0 10px 10px -5px rgba(5, 150, 105, 0.04),
            0 0 0 1px rgba(5, 150, 105, 0.05);
    }
    
    .shadow-luxury-lg {
        box-shadow: 
            0 25px 50px -12px rgba(5, 150, 105, 0.15),
            0 25px 25px -5px rgba(5, 150, 105, 0.1),
            0 0 0 1px rgba(5, 150, 105, 0.05);
    }
    
    /* Elegant Hover Effects */
    .group:hover .group-hover\\:scale-110 {
        transform: scale(1.1);
    }
    
    /* High-end Border Radius */
    .rounded-luxury {
        border-radius: 2rem;
    }
    
    .rounded-luxury-lg {
        border-radius: 3rem;
    }
    
    /* Smooth Page Transitions */
    html {
        scroll-behavior: smooth;
    }
    
    /* Text Selection */
    ::selection {
        background-color: rgba(16, 185, 129, 0.2);
        color: inherit;
    }
    
    ::-moz-selection {
        background-color: rgba(16, 185, 129, 0.2);
        color: inherit;
    }
    
    /* Hero Slider */
    .hero-slider {
        position: relative;
    }
    
    .hero-slide {
        transition: opacity 2s ease-in-out;
    }
    
    .hero-slide.active {
        opacity: 1;
    }
    
    .hero-slide.inactive {
        opacity: 0;
    }
    
    /* Preload images */
    .hero-slide::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: inherit;
        background-size: cover;
        background-position: center;
        opacity: 0;
        z-index: -1;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.hero-slide');
    const totalSlides = slides.length;
    let currentSlide = 0;
    let isTransitioning = false;
    
    console.log('Hero slider initialized with', totalSlides, 'slides');
    
    if (totalSlides <= 1) return;
    
    function showSlide(index) {
        if (isTransitioning) return;
        isTransitioning = true;
        
        slides.forEach((slide, i) => {
            if (i === index) {
                slide.style.transition = 'opacity 2s ease-in-out';
                slide.style.opacity = '1';
                slide.style.zIndex = '2';
            } else if (i === currentSlide && i !== index) {
                // Fade out current slide
                slide.style.transition = 'opacity 2s ease-in-out';
                slide.style.opacity = '0';
                slide.style.zIndex = '1';
            } else {
                slide.style.opacity = '0';
                slide.style.zIndex = '0';
            }
        });
        
        setTimeout(() => {
            isTransitioning = false;
        }, 2000);
    }
    
    function nextSlide() {
        const nextIndex = (currentSlide + 1) % totalSlides;
        showSlide(nextIndex);
        currentSlide = nextIndex;
    }
    
    // Initialize
    slides.forEach((slide, i) => {
        slide.style.position = 'absolute';
        slide.style.top = '0';
        slide.style.left = '0';
        slide.style.width = '100%';
        slide.style.height = '100%';
        slide.style.zIndex = i === 0 ? '2' : '0';
    });
    
    // Start slideshow after a delay
    setTimeout(() => {
        setInterval(nextSlide, 5000);
    }, 3000);
    
    // Preload all images
    const imageUrls = [
        '/test/fv/DSC01230.jpg',
        '/test/fv/DSC01237.jpg',
        '/test/fv/DSC01323.jpg',
        '/test/fv/DSC01570.jpg',
        '/test/fv/DSC01591.jpg'
    ];
    
    imageUrls.forEach(url => {
        const img = new Image();
        img.src = url;
        img.onload = () => console.log('Loaded:', url);
        img.onerror = () => console.error('Failed to load:', url);
    });
});
</script>
@endsection