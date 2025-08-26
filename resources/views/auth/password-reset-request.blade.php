<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードリセット - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    パスワードリセット
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    登録されているメールアドレスを入力してください
                </p>
            </div>
            
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-4">
                    {{ session('status') }}
                </div>
            @endif
            
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            
            <form class="mt-8 space-y-6" action="{{ route('password.email') }}" method="POST">
                @csrf
                
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="email" class="sr-only">メールアドレス</label>
                        <input id="email" name="email" type="email" required
                               class="appearance-none rounded-md relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 focus:z-10 sm:text-sm"
                               placeholder="メールアドレス"
                               value="{{ old('email') }}">
                    </div>
                </div>
                
                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-emerald-600 to-green-500 hover:from-emerald-700 hover:to-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        リセットリンクを送信
                    </button>
                </div>
                
                <div class="text-center">
                    <a href="{{ url('/admin/login') }}" class="text-sm text-emerald-600 hover:text-emerald-500">
                        ログイン画面に戻る
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>