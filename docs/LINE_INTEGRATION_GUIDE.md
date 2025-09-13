# LINE連携実装ガイド - LIFF活用による確実なユーザー紐付け

## 概要
このドキュメントは、LINE連携を実装する際の成功要因と、LIFFを使用した確実なユーザー紐付けの方法を説明します。

## 🎯 核心的な成功要因

### 1. LIFF（LINE Front-end Framework）の活用が必須
- **問題**: 通常のWebブラウザからLINEログインすると、アプリ内のLINEユーザーIDと紐付けできない
- **解決**: LIFFを使用することで、LINEアプリ内でシームレスな認証が可能

### 2. パラメータ保持の仕組み
LIFFの認証フロー中でパラメータが失われる問題への対処：

```javascript
// 3つの方法でパラメータを保持・取得
function getReservationNumber() {
    // 1. 通常のURLパラメータ
    const urlParams = new URLSearchParams(window.location.search);
    let reservation = urlParams.get('reservation');
    if (reservation) return reservation;
    
    // 2. LIFF特有のliff.stateパラメータ（リダイレクト時に自動付与）
    const liffState = urlParams.get('liff.state');
    if (liffState) {
        const decodedState = decodeURIComponent(liffState);
        const match = decodedState.match(/reservation=([^&]+)/);
        if (match) return match[1];
    }
    
    // 3. sessionStorageへのフォールバック
    return sessionStorage.getItem('reservation_number');
}

// 初期パラメータをsessionStorageに保存
const reservationNumber = getReservationNumber();
if (reservationNumber) {
    sessionStorage.setItem('reservation_number', reservationNumber);
}
```

## 📋 実装手順

### Step 1: LINE Developersでの設定
1. LINEログインチャネルを作成
2. LIFF URLを追加（エンドポイントURLを指定）
3. 必要なスコープを設定（profile, openid）

### Step 2: 連携開始ポイントの実装
```html
<!-- 予約完了画面などからLIFF URLへ直接リンク -->
<a href="https://liff.line.me/{{ LIFF_ID }}?reservation={{ reservation_number }}" 
   class="btn-line-link">
   LINEで連携
</a>
```

### Step 3: LIFF初期化とトークン取得
```javascript
async function initializeLiff() {
    try {
        await liff.init({ 
            liffId: LIFF_ID,
            withLoginOnExternalBrowser: true // 外部ブラウザでも動作
        });
        
        if (!liff.isLoggedIn()) {
            liff.login({
                redirectUri: window.location.href // 現在のURLにリダイレクト
            });
            return;
        }
        
        // IDトークン取得
        const idToken = liff.getIDToken();
        return idToken;
    } catch (error) {
        console.error('LIFF初期化エラー:', error);
    }
}
```

### Step 4: バックエンドでのトークン検証
```php
// JWT検証（Firebase JWT v6対応）
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;

class LineTokenVerificationService
{
    public function verifyIdToken(string $idToken): array
    {
        // JWKs取得
        $jwks = $this->fetchJWKs();
        
        // ヘッダーからkeyIdを取得
        $header = $this->decodeHeader($idToken);
        $keyId = $header['kid'];
        
        // 公開鍵を取得（Key型で返す）
        $publicKey = $this->findPublicKey($jwks, $keyId);
        
        // トークン検証
        $decoded = JWT::decode($idToken, $publicKey);
        
        return [
            'user_id' => $decoded->sub,  // LINEユーザーID
            'name' => $decoded->name ?? null,
            'picture' => $decoded->picture ?? null,
            'email' => $decoded->email ?? null
        ];
    }
    
    private function findPublicKey(array $jwks, string $keyId): Key
    {
        foreach ($jwks['keys'] as $key) {
            if ($key['kid'] === $keyId) {
                return JWK::parseKey($key); // Key型を直接返す
            }
        }
        throw new Exception('Public key not found');
    }
}
```

### Step 5: ユーザー紐付けとレスポンス
```php
public function linkByReservation(Request $request)
{
    // IDトークン検証
    $lineUserData = $this->tokenVerificationService->verifyIdToken(
        $request->input('id_token')
    );
    
    // 予約番号から顧客を特定
    $reservation = Reservation::where('reservation_number', $request->input('reservation_number'))
        ->firstOrFail();
    $customer = $reservation->customer;
    
    // LINE連携実行
    $customer->update([
        'line_user_id' => $lineUserData['user_id'],
        'line_display_name' => $lineUserData['name'],
        'line_picture_url' => $lineUserData['picture']
    ]);
    
    // 連携完了メッセージをLINEに送信
    $this->sendWelcomeMessage($customer, $lineUserData['user_id']);
    
    return response()->json(['success' => true]);
}
```

## ⚠️ 重要な注意点

### 1. URLスキーム
- ❌ `liff://` は使用不可（ブラウザが認識しない）
- ✅ `https://liff.line.me/XXXX` を使用

### 2. パラメータ保持
- LIFF認証中にURLパラメータが変化する
- 必ず複数の方法でパラメータを保持する仕組みを実装

### 3. トークン検証
- Firebase JWT v6では返り値の型が変更されている
- `Key`型を直接使用する必要がある

### 4. エラーハンドリング
```javascript
// LIFF初期化失敗時の処理
if (!liff.isInClient() && !liff.isLoggedIn()) {
    // 通常のWebログインにフォールバック
    showAlternativeLoginMethod();
}
```

## 🔍 デバッグのポイント

1. **LIFF初期化の確認**
```javascript
console.log('LIFF環境:', {
    isInClient: liff.isInClient(),
    isLoggedIn: liff.isLoggedIn(),
    os: liff.getOS(),
    version: liff.getVersion()
});
```

2. **パラメータ取得の確認**
```javascript
console.log('パラメータ取得:', {
    url: window.location.href,
    params: Object.fromEntries(new URLSearchParams(window.location.search)),
    sessionStorage: sessionStorage.getItem('reservation_number')
});
```

3. **トークン検証の確認**
```php
Log::info('LINE token verification', [
    'has_token' => !empty($idToken),
    'token_length' => strlen($idToken),
    'header' => $this->decodeHeader($idToken)
]);
```

## 📊 成功指標

1. **連携成功率**: 95%以上を目標
2. **エラー率**: 5%以下
3. **ユーザー体験**: 3タップ以内で連携完了

## 🚀 実装チェックリスト

- [ ] LINE Developersでチャネル作成
- [ ] LIFF URL設定
- [ ] フロントエンドでLIFF SDK導入
- [ ] パラメータ保持の3段階実装
- [ ] バックエンドでJWT検証実装
- [ ] エラーハンドリング実装
- [ ] ログ出力の実装
- [ ] テスト（新規ユーザー）
- [ ] テスト（既存友だち）
- [ ] テスト（外部ブラウザ）

## まとめ

LIFFを正しく活用することで、確実なLINE連携が実現できます。特に重要なのは：

1. **LIFF URLの使用**（通常のWebログインではない）
2. **パラメータ保持の多層防御**
3. **適切なトークン検証**

これらを実装することで、ユーザーにとってシームレスで確実な連携体験を提供できます。