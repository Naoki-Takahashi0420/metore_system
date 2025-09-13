<div class="prose dark:prose-invert max-w-none">
    <h4>CSV形式</h4>
    <ul>
        <li>文字コード: Shift-JIS</li>
        <li>区切り文字: カンマ（,）</li>
        <li>1行目: ヘッダー行（必須）</li>
    </ul>

    <h4>必須項目</h4>
    <ul>
        <li>顧客番号</li>
        <li>顧客名</li>
        <li>ふりがな</li>
    </ul>

    <h4>重複処理</h4>
    <ul>
        <li>同一店舗内で同じメールアドレスまたは電話番号が存在する場合はスキップ</li>
        <li>異なる店舗の場合は新規登録</li>
    </ul>

    <h4>データ変換</h4>
    <ul>
        <li>電話番号: ハイフンを除去して保存</li>
        <li>誕生日: yyyy/mm/dd形式をyyyy-mm-dd形式に変換</li>
        <li>性別: 「男性」→「male」、「女性」→「female」に変換</li>
        <li>血液型: 「A型」→「A」のように「型」を除去</li>
    </ul>

    <h4>エラー処理</h4>
    <ul>
        <li>エラーが発生した行はスキップして処理を継続</li>
        <li>エラー内容はCSVファイルとしてダウンロード可能</li>
    </ul>
</div>