# Page snapshot

```yaml
- generic [ref=e1]:
  - main [ref=e4]:
    - generic [ref=e5]:
      - generic [ref=e6]:
        - generic [ref=e7]:
          - img "目のトレーニング 管理画面ロゴ" [ref=e8]
          - heading "管理画面にログイン" [level=1] [ref=e9]
        - generic [ref=e10]:
          - generic [ref=e11]:
            - generic [ref=e14]:
              - generic [ref=e17]:
                - text: メールアドレス
                - superscript [ref=e18]: "*"
              - textbox "メールアドレス*" [active] [ref=e22]
            - generic [ref=e25]:
              - generic [ref=e28]:
                - text: パスワード
                - superscript [ref=e29]: "*"
              - generic [ref=e31]:
                - textbox "パスワード*" [ref=e33]
                - button "パスワードを表示" [ref=e36] [cursor=pointer]:
                  - generic [ref=e37] [cursor=pointer]: パスワードを表示
                  - img [ref=e38] [cursor=pointer]
            - generic [ref=e45]:
              - checkbox "ログインしたままにする" [ref=e46]
              - generic [ref=e47]: ログインしたままにする
          - button "ログイン" [ref=e50] [cursor=pointer]:
            - generic [ref=e51] [cursor=pointer]: ログイン
      - generic:
        - dialog
      - generic:
        - dialog
      - generic:
        - dialog
  - generic:
    - status
```