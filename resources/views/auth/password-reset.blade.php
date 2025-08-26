<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新しいパスワードの設定 - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    新しいパスワードの設定
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    8文字以上の安全なパスワードを設定してください
                </p>
            </div>
            
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            
            <form class="mt-8 space-y-6" action="{{ route('password.update') }}" method="POST">
                @csrf
                
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">
                
                <div class="space-y-4">
                    <div>
                        <label for="email-display" class="block text-sm font-medium text-gray-700">
                            メールアドレス
                        </label>
                        <input id="email-display" type="email" disabled
                               class="mt-1 appearance-none relative block w-full px-3 py-3 border border-gray-300 bg-gray-100 text-gray-500 rounded-md sm:text-sm"
                               value="{{ $email }}">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            新しいパスワード
                        </label>
                        <input id="password" name="password" type="password" required
                               class="mt-1 appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 focus:z-10 sm:text-sm"
                               placeholder="8文字以上">
                    </div>
                    
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                            パスワード（確認）
                        </label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required
                               class="mt-1 appearance-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 focus:z-10 sm:text-sm"
                               placeholder="もう一度入力">
                    </div>
                </div>
                
                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-emerald-600 to-green-500 hover:from-emerald-700 hover:to-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        パスワードを変更
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>