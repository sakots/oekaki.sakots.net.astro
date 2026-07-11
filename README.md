# さこちゃねる

## 概要

- ファイル名がUNIXミリ秒の画像を`imgs/`ディレクトリに配置してビルドしたら自動で画像ギャラリーができる

## やりかた

```bash
pnpm install
```

`src/content/blog/`内各.mdファイルを編集

```astro
---
title: 'ページタイトル'
pubDate: YYYY-MM-DD
description: 'ページ概要'
tags: ["タグ","複数可能"]
url: pageUrl
imageDir: imageDir
---
```

`src/imgs/imageDir`内に画像を配置する

```bash
pnpm build
```

## システムの更新履歴

### [2026/07/11]

- 更新日時も画像のタイムスタンプで自動生成するようにした
