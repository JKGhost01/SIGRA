# SIGRA · 世界の地震情報と市民ネットワーク

**v1.4.0.0 · 世界地図とモバイル対応** · `20261120.1` · 2026-11-20

> モバイル地図、世界の地震ハザード分布、統合カタログを備えた安定版。 [1.4.0.0](docs/PRODUCCION.md)

[Español](README.md) · [English](README.en.md) · [Português](README.pt.md) · [Français](README.fr.md) · [Deutsch](README.de.md) · [Italiano](README.it.md) · [日本語](README.ja.md) · [简体中文](README.zh-CN.md) · [العربية](README.ar.md)

このガイドでは、プロジェクトの概要と導入手順を日本語で説明します。[完全なドキュメント](README.md)とリンク先の技術ガイドはスペイン語で管理されています。コマンド、パス、API の契約名は変更していません。Web インターフェースはスペイン語と英語に対応しています。

## 概要

SIGRA は、**SGC、USGS、EMSC、GeoNet、GEOFON/GFZ、INGV** が公開する地震情報を、一つの検索可能な地図にまとめるオープンソースの PHP アプリケーションです。履歴、絞り込み、JSON API、非公開の市民報告を備え、指定した地点の近くで発生した地震も検索できます。

スマートフォンのセンサーネットワークは**実験段階**です。SIGRA は地震を予知せず、早期警報を保証せず、行政機関や公式の警報システムに代わるものでもありません。Android クライアントは別のリポジトリ [SIGRA_MOBILE](https://github.com/JKGhost01/SIGRA_MOBILE) で開発されています。

## 機能とデータソース

- Leaflet 地図、履歴、詳細表示、場所・情報源・マグニチュード・距離による絞り込み。
- コロンビアの SGC、およびそれぞれの制約のもとで世界を対象とする USGS、EMSC、GEOFON。
- ニュージーランドの GeoNet、イタリアと周辺地域の INGV。
- Web・Android 用 API、非公開の市民報告、インストール可能な PWA。
- 複数の情報源の正規化と照合。接続障害時も保存済みのカタログを維持。

収録範囲と更新頻度は情報源ごとに異なります。HTTP 応答が正常でも、科学的な最新性や網羅性が保証されるわけではありません。[情報源と制約](docs/FUENTES_SISMICAS.md)を参照してください。

## インストール

必要な環境は、`curl`、`intl`、`mbstring`、`mysqli` を有効にした PHP 8.2 以上、Composer 2、MySQL または MariaDB です。CodeIgniter は `system/` に含まれています。依存関係は `composer.lock` に従ってインストールします。検証には Node 22 以上、Python 3、PHP-CGI も必要です。

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
```

空のデータベースを作成し、`.env` の `database.default.*` と `app.baseURL` を設定してから、次を実行します。

```bash
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

<http://127.0.0.1:8080/index.php/sismos> を開いてください。新規環境で公開するディレクトリは `public/` のみにしてください。鍵と権限の設定は[インストールガイド](docs/INSTALLATION.md)に従ってください。

## 設定と同期

| 設定 | 用途 |
|---|---|
| `CI_ENVIRONMENT` | ローカルでは `development`、サーバーでは `production`。 |
| `app.baseURL` | 末尾にスラッシュを付けた公開 URL。 |
| `APP_API_TOKEN` | 保護されたルートの Bearer トークン。 |
| `database.default.*` | データベース接続。 |
| `encryption.key` | 非公開の暗号鍵。 |
| `quakes.*` | 情報源、フィルター、更新間隔。 |
| `sigra.version`, `sigra.build` | バージョン情報の任意の上書き。 |

地図は自動的に更新を要求します。全体の同期には最低5分、SGC にはホストごとに15分の間隔があります。キャッシュ、ロック、エラー後の待機状態はプロセス間で共有されます。cron は任意です。通常の同期は次のコマンドで開始できます。

```bash
php spark sigra:sync
```

このコマンドは待機時間を守り、地震データをデータベースに保存する場合があります。`/acerca-de#sources-health` は記録済みの状態を表示するページです。このページの更新ボタンは情報提供元への問い合わせを行いません。デプロイ時も `writable/cache/providers` を保持してください。

## API、プライバシー、検証

保護されたルートには `Authorization: Bearer <APP_API_TOKEN>` を使用します。[地震 API](docs/API_SISMOS.md)、[市民ネットワーク API](docs/API_RED.md)、[OpenAPI](docs/api/openapi.yaml) を参照してください。契約バージョンは `v1` のままです。

位置情報の共有は任意です。代替手段として IP による概算位置を使う場合は `ipapi.co` を利用します。トークン、認証情報、非公開の報告内容を公開 issue に掲載しないでください。

```bash
composer test
composer analyse
python3 tools/check-git.py
```

MySQL のテストには、明示的に選択した使い捨てのテスト用データベースが必要です。本番データベースは使用しないでください。

## 更新と関連ドキュメント

1.3.1.3 は SGC との HTTP 互換性を復旧するバージョンです。ローカルでの実接続テストでは HTTP 200 を受信し、100件のイベントを正規化しました。その後、ユーザーが修正のデプロイ後に動作したことを確認しています。このリビジョンに新しいマイグレーションはありません。[アップロード対象ファイルと復元手順](docs/SGC_CONEXION_2026-11-19.md)を確認してください。

- [運用と復旧](docs/PRODUCCION.md)
- [アーキテクチャ](docs/architecture/README.md)
- [バージョン](docs/VERSIONES.md)と[変更履歴](CHANGELOG.md)
- [貢献ガイド](CONTRIBUTING.md)と[行動規範](CODE_OF_CONDUCT.md)
- [MIT ライセンス](LICENSE)と[第三者の権利表示](THIRD_PARTY_NOTICES.md)
