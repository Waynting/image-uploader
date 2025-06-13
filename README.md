# 📸 Image Uploader for WordPress

一個簡潔的 WordPress 外掛，讓你可以從後台一次上傳多張圖片，自動壓縮後儲存到指定資料夾，並用 Shortcode 插入到文章中。

## ✨ 功能特色

- 支援多圖上傳
- 支持 JPEG 壓縮（預設品質 75，可自訂）
- 自訂儲存資料夾（相對於 `wp-content`）
- 支援自動補完已有資料夾名稱
- 可選擇「若已存在是否覆蓋」
- 自動產生 Shortcode：`[img file="你的路徑（相對於 wp-content）"]`

## 🔧 安裝方式

1. 將整個資料夾上傳到 `wp-content/plugins/` 目錄中 or 用Wordpress的後台下載外掛。
2. 在 WordPress 後台啟用 Plugin。
3. 點擊左側選單的「圖片上傳工具」，開始使用。

## 🖼️ Shortcode 用法


使用以下 Shortcode 插入圖片：
```
[img file="相對wp-content的連結"]
```
以上
歡迎大家自行取用
