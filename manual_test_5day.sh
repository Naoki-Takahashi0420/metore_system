#!/bin/bash

echo "=== 須藤亜希子さん（ID: 2813）の5日間ルールテスト ==="
echo ""
echo "Step 1: トークン生成"
echo "----------------------------------------"

# Artisan tinker を使ってトークン生成
TOKEN=$(php artisan tinker --execute="
\$customer = App\Models\Customer::find(2813);
if (\$customer) {
    \$token = \$customer->createToken('test-5day-rule')->plainTextToken;
    echo \$token;
} else {
    echo 'ERROR: Customer not found';
    exit(1);
}
" 2>/dev/null | tail -1)

if [[ $TOKEN == ERROR* ]]; then
    echo "❌ 顧客が見つかりません"
    exit 1
fi

echo "✅ トークン生成成功"
echo "Token: ${TOKEN:0:20}..."
echo ""

echo "Step 2: ログファイルをクリア"
echo "----------------------------------------"
> storage/logs/laravel.log
echo "✅ ログファイルをクリアしました"
echo ""

echo "Step 3: カレンダーAPI呼び出し"
echo "----------------------------------------"
echo "URL: http://localhost:8000/reservation/calendar?store_id=1&menu_id=93&start_date=2025-10-15"
echo ""

HTTP_CODE=$(curl -s -o /tmp/calendar_response.json -w "%{http_code}" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json" \
    "http://localhost:8000/reservation/calendar?store_id=1&menu_id=93&start_date=2025-10-15")

echo "HTTPステータス: $HTTP_CODE"
echo ""

if [ "$HTTP_CODE" = "200" ]; then
    echo "✅ API呼び出し成功"
    echo ""
    echo "Step 4: ログ確認"
    echo "----------------------------------------"

    # 優先度ログを確認
    if grep -q "【優先1】" storage/logs/laravel.log; then
        echo "✅ Context経由で顧客ID取得:"
        grep "【優先1】" storage/logs/laravel.log | tail -1
    elif grep -q "【優先2】" storage/logs/laravel.log; then
        echo "✅ API認証経由で顧客ID取得:"
        grep "【優先2】" storage/logs/laravel.log | tail -1
    elif grep -q "【優先3】" storage/logs/laravel.log; then
        echo "✅ Session経由で顧客ID取得:"
        grep "【優先3】" storage/logs/laravel.log | tail -1
    else
        echo "⚠️  優先度ログが見つかりません"
    fi

    echo ""

    # 5日間ルール適用ログを確認
    if grep -q "5日間隔" storage/logs/laravel.log; then
        echo "✅ 5日間ルール適用確認:"
        grep "5日間隔" storage/logs/laravel.log | tail -3
    else
        echo "❌ 5日間ルールのログが見つかりません"
    fi

    echo ""
    echo "=== 完全なログを確認する場合 ==="
    echo "tail -100 storage/logs/laravel.log"
else
    echo "❌ API呼び出し失敗"
    echo "レスポンス:"
    cat /tmp/calendar_response.json | head -20
fi

echo ""
echo "=== テスト完了 ==="
