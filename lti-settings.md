Moodle への LTI1.3ツール の登録
========

1. BookRollのLTI1.3接続に利用するためのRSA鍵を作成する

  ```
  $ openssl genrsa -out keypair.pem 2048
  $ openssl rsa -in keypair.pem -pubout -out publickey.crt
  $ cat publickey.crt
  $ openssl pkcs8 -topk8 -inform PEM -outform PEM -nocrypt -in keypair.pem -out pkcs8.key
  $ cat pkcs8.key
  ```

2. 【BookRoll】BookRoll を起動する。
  ```
  ※下記のURLで起動した想定で説明する。
  http://localhost:8080
  ```

3. 【Moodle】下記ように移動します。
  サイト管理 → プラグイン → 活動モジュール → 外部ツール → ツールを管理する

4. 【Moodle】「ツールを管理する」の中で「ツールを追加する」枠内で「ツールを手動設定」リンクをクリックし新規のツールを追加する。
  または、既存のツールの「編集(歯車アイコン)」を選択する。

5. 【Moodle】外部ツール設定のフォームに次のデータを入力する。
- 外部ツール設定
  - ツール設定
    - ツール名: BookRoll
    - ツールURL: http://localhost:8080/lti3
    - LTIバージョン: LTI 1.3
    - クライアントID: (Moodleから提供される)
    - ログインURLを開始する: http://localhost:8080/oidc/login_initiations
    - リダイレクトURI: http://localhost:8080/lti3
    - ツール設定使用: 外部ツール追加時に事前設定ツールとして表示する
    - デフォルト起動コンテナ: 新しいウィンドウ

「変更を保存する」ボタンをクリックする。

6. 【Moodle】追加または編集したツールの「設定詳細を表示する(横棒アイコン)」をクリックする。
  下記のようにダイアログが表示される。(この値は使用するのでメモかなにかに保持する)
   (外部ツールごとに値が違う箇所があるので注意)
  ```
  ツール設定詳細

    プラットフォームID: https://md4.ksy.jpn.com
    クライアントID: imrm754kXqTkwne
    設置ID: 3
    公開鍵セットURL: https://md4.ksy.jpn.com/mod/lti/certs.php
    アクセストークンURL: https://md4.ksy.jpn.com/mod/lti/token.php
    認証リクエストURL: https://md4.ksy.jpn.com/mod/lti/auth.php
  ```

7. 【BookRoll】管理者でログインする。 http://localhost:8080/bookroll

   「サイト管理」>「LTI1.3 プラットフォーム配置 設定」に Moodle のツール設定詳細の値を設定する。

| 項目名              | ツール設定詳細 の項目                              | 実際の値                                      |
|------------------|------------------------------------------|-------------------------------------------|
| key id           | -                                        | 1                                         |
| iss              | プラットフォームID<br>Platform ID                | https://md4.ksy.jpn.com                   |
| client id        | クライアントID<br>Client ID                    | imrm754kXqTkwne                           |
| oidc endpoint    | 認証リクエストURL<br>Authentication request URL | https://md4.ksy.jpn.com/mod/lti/auth.php  |
| jwks endpoint    | 公開鍵セットURL<br>Public keyset URL           | https://md4.ksy.jpn.com/mod/lti/certs.php |
| OAuth2 token url | アクセストークンURL<br>Access token URL          | https://md4.ksy.jpn.com/mod/lti/token.php |
| OAuth2 token aud | -                                        | null                                      |
| deployment id    | 設置ID<br>Deployment ID                    | 3                                         |

   「サイト管理」>「LTI1.3 BookRoll 設定」に LTI1.3 で使用する値を設定する。
  秘密鍵(private key)と公開鍵(public key)は1の手順で作成した値を使う。

| 項目名              | 実際の値                           |
|------------------|--------------------------------|
| id               | 1                              |
| url              | http://localhost:8080/bookroll |
| private key      | (pkcs8.keyの値)                  |
| public key       | (publickey.crtの値)              |

8. 【Moodle】コースの「編集モードの開始」ボタンをクリックし編集モードにする。

  「活動またはリソースを追加する」リンクをクリックし 外部ツール を追加する。

  または、既存の外部ツールの「編集 → 設定を編集する」を選択する。


9. 【Moodle】「XXX外部ツールを更新中」のフォームに次のデータを入力する。
- 一般
  - 活動名: (任意の名前)
  - 事前設定ツール: BookRoll (4 で設定したツール名)

「保存してコースに戻る」ボタンをクリックする。

10. 【Moodle】「編集モードの終了」ボタンをクリックし編集モードを終了する。


11. 【BookRoll】管理者をログアウトする。


12. 【Moodle】コース → トピック の 8 で設定した活動リンクをクリックする。

Moodleのユーザで BookRoll 画面が表示される。