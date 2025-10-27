# デプロイ待ち修正内容（2025-10-27）

## 📋 概要

本ドキュメントは、ローカルテスト中でまだ本番デプロイしていない修正内容をまとめたものです。

---

## 1. Remember Me機能の恒久的修正

### 🔴 問題の背景

**発見した問題**:
- サーバー側は正しく30日トークンを発行（本番DBで確認済み）
- フロントエンドがクライアント側で有効期限を計算していた
- サーバーとクライアントの有効期限が不一致になる可能性

**本番DB調査結果**:
```
customer-auth-remember: 720時間（30日） - 232個
customer-auth: 2時間 - 149個
→ サーバー側は100%正常動作
```

### ✅ 修正内容

#### 1-1. サーバーレスポンスに`expires_at`を追加

**ファイル**: `app/Http/Controllers/Api/Auth/CustomerAuthController.php`

**変更箇所**: L189-206

```php
// デバッグログ：トークン発行パラメータ
\Log::info('[CustomerAuth::verifyOtp] Token generation', [
    'customer_id' => $customer->id,
    'remember_me' => $rememberMe,
    'token_name' => $tokenName,
    'expires_at' => $expiresAt->toIso8601String(),
    'hours_valid' => $rememberMe ? 720 : 2,
]);

$token = $customer->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

return response()->json([
    'success' => true,
    'data' => [
        'is_new_customer' => false,
        'token' => $token,
        'expires_at' => $expiresAt->toIso8601String(),  // ✅ 追加
        'customer' => [
            // ...
        ],
    ],
]);
```

**理由**: フロントエンドにサーバーが実際に設定した有効期限を返す

#### 1-2. `/api/auth/customer/me` APIを実装

**ファイル**: `app/Http/Controllers/Api/Auth/CustomerAuthController.php`

**変更箇所**: L501-537（新規追加）

```php
/**
 * 顧客情報取得（トークン検証も兼ねる）
 */
public function me(Request $request)
{
    $customer = $request->user();

    if (!$customer) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'トークンが無効です',
            ],
        ], 401);
    }

    // トークンの有効期限を取得
    $token = $request->user()->currentAccessToken();
    $expiresAt = $token->expires_at;

    return response()->json([
        'success' => true,
        'data' => [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'last_name' => $customer->last_name,
                'first_name' => $customer->first_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'store_id' => $customer->store_id,
            ],
            'token_expires_at' => $expiresAt ? $expiresAt->toIso8601String() : null,
        ],
    ]);
}
```

**理由**:
- ログインページ起動時にトークンの有効性をサーバーで検証
- localStorageの期限だけに頼らず、実際のトークン状態を確認

**ルート追加**: `routes/api.php` L29

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [CustomerAuthController::class, 'me']);  // ✅ 追加
    Route::post('switch-store', [CustomerAuthController::class, 'switchStore']);
    Route::post('logout', [CustomerAuthController::class, 'logout']);
});
```

#### 1-3. フロントエンドをサーバーの`expires_at`採用に修正

**ファイル**: `resources/views/customer/login.blade.php`

**変更箇所1**: L167-206（起動時のトークン検証）

```javascript
document.addEventListener('DOMContentLoaded', async function() {
    // 既存のトークンをサーバーで検証
    const existingToken = localStorage.getItem('customer_token');

    if (existingToken) {
        console.log('🔍 Existing token found, verifying with server...');

        try {
            const response = await fetch('/api/auth/customer/me', {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${existingToken}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                console.log('✅ Token valid, redirecting to dashboard');

                // トークン有効期限を更新（サーバーから取得）
                if (data.data.token_expires_at) {
                    localStorage.setItem('token_expiry', data.data.token_expires_at);
                }

                window.location.href = '/customer/dashboard';
                return;
            } else {
                // トークン無効（401）またはその他のエラー
                console.log('❌ Token invalid or expired, clearing localStorage');
                localStorage.removeItem('customer_token');
                localStorage.removeItem('customer_data');
                localStorage.removeItem('token_expiry');
                localStorage.removeItem('remember_me');
            }
        } catch (error) {
            console.error('Token validation error:', error);
            // ネットワークエラーの場合はlocalStorageをクリアせず、ログインフォームを表示
        }
    }
```

**変更箇所2**: L360-376（ログイン成功時の処理）

```javascript
// ✅ サーバーから返された有効期限を使用（クライアント計算を廃止）
if (data.data.expires_at) {
    localStorage.setItem('token_expiry', data.data.expires_at);
    localStorage.setItem('remember_me', rememberMe ? 'true' : 'false');
    console.log('✅ Token expiry set from server:', data.data.expires_at);
} else {
    // フォールバック：サーバーがexpires_atを返さない場合（互換性維持）
    if (rememberMe) {
        localStorage.setItem('remember_me', 'true');
        localStorage.setItem('token_expiry', new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString());
    } else {
        localStorage.setItem('remember_me', 'false');
        localStorage.setItem('token_expiry', new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString());
    }
    console.warn('⚠️ Server did not return expires_at, using client-side calculation');
}
```

**理由**:
- サーバーから返された`expires_at`をそのまま使用
- クライアント側での計算を廃止（サーバーとの不一致を防止）
- フォールバック機能で互換性維持

### 📊 変更前後の比較

| 項目 | 変更前 | 変更後 |
|-----|-------|-------|
| 有効期限の計算 | フロントエンドで計算 | サーバーから受け取る |
| 起動時の検証 | localStorageの期限のみ確認 | サーバーAPIで実際のトークンを検証 |
| 不一致リスク | あり | なし |
| ログ出力 | なし | トークン発行時にログ出力 |

---

## 2. タイムライン実所要時間判定の修正

### 🔴 問題の背景

**発見した問題**:
- サーバー側は実所要時間（メニュー+オプション）で空き判定済み
- フロントエンド（Blade）は固定の最小枠時間で判定
- クリック可否が実際の空き状況と不一致

### ✅ 修正内容

**ファイル**: `resources/views/filament/widgets/reservation-timeline.blade.php`

**変更箇所1**: L853-868（実所要時間の計算）

```php
// ✅ 実所要時間（選択メニュー+オプション）での空き判定
$actualDuration = null;
if ($this->selectedMenuDuration) {
    // メニュー選択済み: 実所要時間 = メニュー + オプション
    $actualDuration = $this->selectedMenuDuration + ($this->selectedOptionsDuration ?? 0);
} else {
    // メニュー未選択: フォールバック（店舗の最小枠時間 or 店舗メニューの最大所要時間）
    $storeMenus = \App\Models\Menu::where('store_id', $currentStore->id)
        ->where('is_active', true)
        ->get();
    $maxMenuDuration = $storeMenus->max('duration_minutes') ?? 0;
    $minSlotDuration = $currentStore->reservation_slot_duration ?? 30;
    $actualDuration = max($maxMenuDuration, $minSlotDuration);
}

$endTime = \Carbon\Carbon::parse($slot)->addMinutes($actualDuration)->format('H:i');
```

**変更箇所2**: L881-892（クリック不可時の処理）

```php
if (!$availabilityResult['can_reserve']) {
    // ✅ 実所要時間では入らない → クリック不可 + 理由表示
    $canClickSlot = false;
    $reason = $availabilityResult['reason'] ?? '空きなし';
    if ($this->selectedMenuDuration) {
        $tooltipMessage = "予約不可（{$slot}〜{$endTime}の{$actualDuration}分間: {$reason}）";
    } else {
        $tooltipMessage = "予約不可（最大{$actualDuration}分の施術: {$reason}）";
    }
} else {
    $tooltipMessage = "予約可能（空き: {$availabilityResult['available_slots']}/{$availabilityResult['total_capacity']}席）";
}
```

### 📊 変更前後の比較

| 項目 | 変更前 | 変更後 |
|-----|-------|-------|
| 判定基準 | 固定の最小枠時間（30分） | 実所要時間（メニュー+オプション） |
| メニュー未選択時 | 30分固定 | 店舗の最大メニュー所要時間 |
| クリック可否 | 不正確（最小時間で判定） | 正確（実所要時間で判定） |
| ツールチップ | "クリックして予約時間を選択" | "予約不可（10:00〜11:30の90分間: 理由）" |

---

## 3. 予約変更時の5日間ルール除外

### 🔴 問題の背景

**発見した問題**:
- 顧客が既存予約を変更する際、5日間ルールが適用されて変更できない日時が発生
- 予約作成フロー（`store`メソッド）は既に変更時の5日間ルールをスキップ実装済み
- 空き状況API（`checkAvailability`）と取得関数（`getAvailability`）が変更モードを考慮していない
- フロントエンドのカレンダー表示で△（予約不可）マークが表示される

**影響範囲**:
- サブスクリプション予約の日程変更時
- 回数券予約の日程変更時
- 顧客が既存予約を別の日に移動したい場合

### ✅ 修正内容

#### 3-1. サーバー側APIで変更モードを受け取る

**ファイル**: `app/Http/Controllers/PublicReservationController.php`

**変更箇所1**: L3007（`checkAvailability`メソッド - バリデーション）

```php
$validated = $request->validate([
    'store_id' => 'required|exists:stores,id',
    'menu_id' => 'required|exists:menus,id',
    'date' => 'required|date',
    'time' => 'required',
    'customer_id' => 'nullable|exists:customers,id',
    'change_mode' => 'nullable|boolean'  // ✅ 変更モードフラグを追加
]);
```

**変更箇所2**: L3022-3028（`checkAvailability`メソッド - 変更モード取得とログ）

```php
$changeMode = $validated['change_mode'] ?? false;  // ✅ 変更モードを取得

\Log::info('checkAvailability processing', [
    'customer_id' => $customerId,
    'menu_id' => $menu->id,
    'menu_is_subscription' => $menu->is_subscription,
    'date' => $validated['date'],
    'time' => $time,
    'change_mode' => $changeMode  // ✅ ログに変更モードを追加
]);
```

**変更箇所3**: L3158-3206（`checkAvailability`メソッド - 5日間ルールスキップ）

```php
// ✅ 変更モードの場合は5日間制限チェックをスキップ
if ($customerId && $menu->is_subscription && !$changeMode) {
    $customer = Customer::find($customerId);
    if ($customer) {
        // ... existing reservation checks ...

        // 予約間隔制限のチェック（店舗設定による）
        foreach ($existingReservations as $reservation) {
            $existingDate = Carbon::parse($reservation->reservation_date);
            $daysDiff = $existingDate->diffInDays($date, false);

            if (abs($daysDiff) < ($minIntervalDays + 1)) {
                $subscriptionInfo['within_five_days'] = true;
                // ... logging ...
                break;
            }
        }
    }
} elseif ($changeMode) {
    \Log::info('予約間隔制限チェックをスキップ (変更モード)', [
        'customer_id' => $customerId,
        'check_date' => $validated['date'],
        'change_mode' => true
    ]);
}
```

**変更箇所4**: L1272-1288（`getAvailability`関数 - 変更モードパラメータ追加）

```php
private function getAvailability($storeId, $store, $startDate, $dates, $menuDuration = 60, $customerId = null, $staffId = null, $changeMode = false)
{
    // ...

    $isChangeReservation = Session::get('change_reservation_id') ? true : false;  // ✅ セッションからも変更モードを検出

    // 既存顧客（マイページ・回数券・サブスク全て）に5日間制限を適用
    // ただし、変更モードの場合はスキップ
    // 店舗ごとに独立した5日間ルールを適用するため、store_idでもフィルタ
    if ($customerId && !$changeMode && !$isChangeReservation) {
        // ... 5日間ルールチェック ...
    }
}
```

**理由**:
- サーバー側で変更モードかどうかを判断し、5日間ルールを条件付きで適用
- セッションベースの検出とAPIパラメータベースの検出の両方に対応
- 既存の予約作成ロジックと一貫性を保つ

#### 3-2. フロントエンド（サブスク予約）で変更モードフラグを送信

**ファイル**: `resources/views/reservation/subscription-booking.blade.php`

**変更箇所1**: L142（変更モードをグローバル変数に）

```javascript
let isChangeMode = false; // ✅ グローバル変数として宣言

document.addEventListener('DOMContentLoaded', async function() {
    const urlParams = new URLSearchParams(window.location.search);
    isChangeMode = urlParams.get('change') === 'true' || sessionStorage.getItem('isChangingReservation') === 'true';
```

**変更箇所2**: L362（API呼び出し時に変更モードフラグを送信）

```javascript
const requestBody = {
    store_id: storeId,
    menu_id: menuId,
    customer_id: customerId,
    date: date,
    time: time,
    change_mode: isChangeMode  // ✅ 変更モードフラグを追加
};
```

**変更箇所3**: L392-397（△表示ロジックで変更モード時は表示しない）

```javascript
} else if (sub.within_five_days && !isChangeMode) {
    // ✅ 変更モード時は5日間制限を無視
    // 前回予約から5日以内（予約不可）
    console.log(`⚠️ ${date} ${time} - Within 5 days restriction`);
    td.innerHTML = '<span class="text-yellow-500 text-xl font-bold">△</span>';
    td.title = '前回予約から5日以内（予約不可）';
```

**理由**:
- APIに変更モードを正しく伝達
- サーバーが`within_five_days`を返しても、変更モード時は△を表示しない（二重防御）

#### 3-3. フロントエンド（公開予約）で変更モード時の△非表示

**ファイル**: `resources/views/reservation/public/index.blade.php`

**変更箇所1**: L303-318（Bladeテンプレート - 変更モード時は○を表示）

```php
@elseif($withinFiveDays && !Session::has('is_reservation_change'))
    {{-- ✅ 変更モード時は5日間制限を無視 --}}
    {{-- 既存顧客の5日間制限内の場合は△を表示 --}}
    <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-gray-400 text-white font-bold flex items-center justify-center border-2 border-gray-500 shadow-md text-xs sm:text-base mx-auto"
         title="前回予約から5日以内のため予約できません">
        △
    </div>
@elseif($withinFiveDays && Session::has('is_reservation_change'))
    {{-- ✅ 変更モード時は予約可能として表示 --}}
    <button type="button"
            class="time-slot w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-green-500 text-white font-bold hover:bg-green-600 text-xs sm:text-base"
            data-date="{{ $dateStr }}"
            data-time="{{ $slot }}"
            onclick="selectTimeSlot(this)">
        ○
    </button>
```

**変更箇所2**: L770（JavaScript - 変更モード時は5日間制限を適用しない）

```javascript
} else if (isWithinFiveDays && isSubscriptionBooking && @json($isExistingCustomer ?? false) && !isReservationChange) {
    // ✅ 変更モード時は5日間制限を無視
    // 既存顧客のサブスク予約でのみ5日制限を適用
    // ...
}
```

**理由**:
- サーバー側レンダリングとクライアント側の両方で変更モードを考慮
- 変更モード時は`within_five_days`フラグがtrueでも予約可能として表示

### 📊 変更前後の比較

| 項目 | 変更前 | 変更後 |
|-----|-------|-------|
| 変更時の5日間ルール | 一律適用（変更できない） | 変更モード時はスキップ（変更可能） |
| checkAvailability API | change_modeなし | change_mode パラメータ追加 |
| getAvailability関数 | 変更モード未対応 | changeMode + セッション検出 |
| subscription-booking.blade.php | 変更フラグ未送信 | change_mode を送信 |
| public/index.blade.php | △を一律表示 | 変更モード時は○を表示 |

### 🎯 期待される動作

#### 新規予約時（従来通り）
- 前回予約から5日以内の日程: △表示、予約不可
- 5日以上空いた日程: ○表示、予約可能

#### 予約変更時（改善後）
- 前回予約から5日以内の日程: ○表示、予約可能 ✅
- 5日以上空いた日程: ○表示、予約可能 ✅
- 既存予約の日時: 黄色●で現在の予約を表示

---

## 4. 調査用ワークフロー（既にデプロイ済み）

以下のワークフローは既にmainブランチにコミット済み：

1. `.github/workflows/check-tokens.yml` - トークン有効期限調査用
2. `.github/workflows/check-db-config.yml` - DB設定確認用

---

## 🧪 ローカルテスト手順

### テスト1: Remember Me機能

```bash
# 1. キャッシュクリア
php artisan cache:clear
php artisan config:clear

# 2. ブラウザでログインページにアクセス
# http://localhost:8000/customer/login

# 3. 開発者ツールのコンソールを開く

# 4. Remember Meをチェックしてログイン
# 期待: "✅ Token expiry set from server: ..." が表示

# 5. localStorageを確認
# - token_expiry がISO8601形式で保存されている
# - 30日後の日時になっている

# 6. ページをリロード
# 期待: "🔍 Existing token found, verifying with server..."
# 期待: 自動的にダッシュボードへリダイレクト

# 7. Laravelログを確認
tail -f storage/logs/laravel.log | grep "CustomerAuth::verifyOtp"

# 期待されるログ:
# [CustomerAuth::verifyOtp] Token generation
# {
#   "customer_id": 123,
#   "remember_me": true,
#   "token_name": "customer-auth-remember",
#   "expires_at": "2025-11-26T12:00:00+09:00",
#   "hours_valid": 720
# }
```

### テスト2: タイムライン実所要時間判定

```bash
# 1. Filament管理画面にログイン
# http://localhost:8000/admin/login

# 2. タイムラインウィジェットを開く

# 3. 予約作成モーダルでメニューを選択
# - 60分のメニューを選択
# - 30分のオプションを追加

# 4. タイムライン上のスロットをホバー
# 期待: "予約可能（空き: X/Y席）" または
#      "予約不可（10:00〜11:30の90分間: スタッフ不足）"

# 5. 空きがないスロットをクリック
# 期待: クリック不可（カーソルが変わらない）
```

### テスト3: 予約変更時の5日間ルール除外

```bash
# 1. マイページにログイン
# http://localhost:8000/customer/login
# （既にサブスク予約がある顧客でログイン）

# 2. マイページで既存のサブスク予約を確認
# 例: 10月20日 10:00の予約がある

# 3. 「予約を変更」ボタンをクリック
# → サブスク予約カレンダー画面へ遷移

# 4. カレンダーで10月23日（3日後）のスロットを確認
# 期待: ○（予約可能）が表示される ✅
# 従来: △（予約不可）が表示されていた ❌

# 5. ブラウザの開発者ツールでコンソールを確認
# 期待: "📤 API Request: { ..., change_mode: true }" が表示される

# 6. 23日の10:00スロットをクリックして予約を変更
# 期待: 正常に予約が変更される

# 7. マイページで予約一覧を確認
# 期待: 20日の予約が23日に変更されている
```

### テスト4: データベース確認

```bash
# ローカルDBでトークンを確認
sqlite3 database/database.sqlite << 'SQL'
SELECT
  name,
  datetime(expires_at) as expires_at,
  ROUND((julianday(expires_at) - julianday(created_at)) * 24, 2) as hours_valid
FROM personal_access_tokens
WHERE name LIKE 'customer-auth%'
ORDER BY created_at DESC
LIMIT 5;
SQL

# 期待される結果:
# customer-auth-remember | 2025-11-26 12:00:00 | 720.0
# customer-auth          | 2025-10-27 14:00:00 | 2.0
```

---

## 🚀 デプロイ手順（テスト完了後）

### ステップ1: 最終確認

```bash
# 1. 修正ファイル一覧を確認
git status

# 期待されるファイル:
# - app/Http/Controllers/Api/Auth/CustomerAuthController.php
# - app/Http/Controllers/PublicReservationController.php
# - resources/views/customer/login.blade.php
# - resources/views/filament/widgets/reservation-timeline.blade.php
# - resources/views/reservation/subscription-booking.blade.php
# - resources/views/reservation/public/index.blade.php
# - routes/api.php

# 2. 差分を確認
git diff app/Http/Controllers/Api/Auth/CustomerAuthController.php
git diff app/Http/Controllers/PublicReservationController.php
git diff resources/views/customer/login.blade.php
git diff resources/views/filament/widgets/reservation-timeline.blade.php
git diff resources/views/reservation/subscription-booking.blade.php
git diff resources/views/reservation/public/index.blade.php
git diff routes/api.php
```

### ステップ2: コミット

```bash
# 1. ファイルをステージング
git add app/Http/Controllers/Api/Auth/CustomerAuthController.php
git add app/Http/Controllers/PublicReservationController.php
git add resources/views/customer/login.blade.php
git add resources/views/filament/widgets/reservation-timeline.blade.php
git add resources/views/reservation/subscription-booking.blade.php
git add resources/views/reservation/public/index.blade.php
git add routes/api.php

# 2. コミット
git commit -m "fix: Remember Me機能・タイムライン・予約変更時5日間ルールの修正

1. Remember Me機能の恒久的修正
- CustomerAuthController: サーバーレスポンスにexpires_atを追加、ログ出力追加
- /api/auth/customer/me API実装（トークン検証を兼ねる）
- login.blade.php: サーバーのexpires_at採用、起動時のトークン検証
- サーバーとクライアントの有効期限不一致を防止

2. タイムライン実所要時間判定の改善
- reservation-timeline.blade.php: 実所要時間でクリック可否判定
- タイムラインのクリック可否が実際の空き状況と一致

3. 予約変更時の5日間ルール除外
- PublicReservationController: change_modeパラメータ追加、変更時は5日間ルールスキップ
- subscription-booking.blade.php: change_modeフラグ送信、△表示ロジック改善
- public/index.blade.php: 変更モード時は△を○に変更
- 既存予約の日程変更時に5日間制限を受けないように改善

🤖 Generated with Claude Code

Co-Authored-By: Claude <noreply@anthropic.com>"
```

### ステップ3: プッシュとデプロイ

```bash
# 1. mainブランチにプッシュ
git push origin main

# 2. デプロイワークフローが自動実行される
# GitHub Actionsで確認:
gh run list --workflow="Deploy Simple" --limit 1

# 3. デプロイ完了を待つ（約3-5分）
gh run watch $(gh run list --workflow="Deploy Simple" --limit 1 --json databaseId -q '.[0].databaseId')
```

### ステップ4: 本番環境で動作確認

```bash
# 1. 本番ログを確認
gh workflow run "Check Latest Logs"
sleep 30
gh run watch $(gh run list --workflow="Check Latest Logs" --limit 1 --json databaseId -q '.[0].databaseId')

# 2. トークン発行ログを確認
# 期待: [CustomerAuth::verifyOtp] Token generation のログが出力される

# 3. 本番DBでトークンを確認
gh workflow run "Download Production DB"
sleep 60
gh run download $(gh run list --workflow="Download Production DB" --limit 1 --json databaseId -q '.[0].databaseId') --dir /tmp/prod-db-verify

sqlite3 /tmp/prod-db-verify/production-database/*.sqlite << 'SQL'
SELECT name, datetime(expires_at), datetime(created_at)
FROM personal_access_tokens
WHERE name LIKE 'customer-auth%'
ORDER BY created_at DESC
LIMIT 3;
SQL
```

---

## 📝 注意事項

### デプロイ前の確認事項

- [ ] ローカルで全テストケースを確認済み
- [ ] Remember Meのチェックあり/なしの両方をテスト
- [ ] タイムラインでメニュー選択あり/なしの両方をテスト
- [ ] 予約変更時に5日以内の日程が○で表示されることを確認
- [ ] 予約変更時に5日以内の日程に予約できることを確認
- [ ] 新規予約時は従来通り5日間ルールが適用されることを確認
- [ ] コンソールログにエラーがないことを確認
- [ ] Laravelログにエラーがないことを確認

### デプロイ後の確認事項

- [ ] 本番環境でログインできることを確認
- [ ] Remember Meが機能することを確認（30日後も自動ログイン）
- [ ] タイムラインでクリック可否が正しく判定されることを確認
- [ ] 予約変更時に5日間ルールが適用されないことを確認（○表示）
- [ ] 新規予約時は従来通り5日間ルールが適用されることを確認（△表示）
- [ ] ログに [CustomerAuth::verifyOtp] が出力されることを確認
- [ ] ログに「予約間隔制限チェックをスキップ (変更モード)」が出力されることを確認（変更時）

---

## 🔄 ロールバック手順（問題発生時）

```bash
# 1. 直前のコミットに戻す
git revert HEAD

# 2. プッシュ
git push origin main

# 3. デプロイ完了を待つ
gh run watch $(gh run list --workflow="Deploy Simple" --limit 1 --json databaseId -q '.[0].databaseId')
```

---

## 📚 関連ドキュメント

- `/DEBUGGING-PROTOCOL.md` - エラー修正時の必須手順
- `/CLAUDE.md` - プロジェクト全体の注意事項
- 本ドキュメント（デプロイ後は削除可能）

---

**作成日**: 2025-10-27
**作成者**: Claude Code
**ステータス**: ローカルテスト中（デプロイ待ち）
