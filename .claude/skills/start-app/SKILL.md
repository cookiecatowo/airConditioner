---
name: start-app
description: 啟動這個空調報價系統專案（Laravel 12 + Vue 3，以 Docker 執行）。Use when the user says 打開這個專案 / 開啟專案 / 啟動專案 / 把專案跑起來 / 開起來, or start / open / launch / run the project, app, or local site.
---

# 啟動空調報價系統

Docker 是本機唯一可用的執行方式 —— 這台機器沒有安裝 PHP、Composer、Node 或 Laravel Herd，
所以 `composer run dev` / `php artisan serve` / `npm run dev` 一定會失敗，不要嘗試。

## 步驟

1. 確認 Docker 引擎有在跑：

   ```bash
   docker version --format '{{.Server.Version}}'
   ```

   失敗代表 Docker Desktop 沒開。請使用者手動開啟 Docker Desktop 後再繼續，不要自行安裝或改動 Docker 設定。

2. 在專案根目錄啟動：

   ```bash
   cd "D:/AI Base/airConditioner" && docker compose up -d --build
   ```

   - 程式碼與依賴都沒動過時，省略 `--build` 會快很多。
   - 建置需要數分鐘。用 `run_in_background`，或把 timeout 設到 600000。
   - 用管線（`| tail`）會吃掉失敗的 exit code，記得檢查 `${PIPESTATUS[0]}` 或直接看輸出有沒有 `ERROR`。

3. 確認容器狀態與網站回應：

   ```bash
   docker compose ps
   curl -s -o /dev/null -w "%{http_code}\n" -L http://localhost/
   ```

   `air-app` 應為 `Up (healthy)`，HTTP 應為 200。

4. 回報網址：**http://localhost**
   （`docker-compose.yml` 將容器 8080 對應到主機 80 埠）

## 驗證前端真的有載到建置產物

健康檢查過了不代表畫面是好的。確認頁面引用的是 `/build/assets/...`：

```bash
curl -s -L http://localhost/ | grep -oE '(src|href)="[^"]*(build|5173)[^"]*"' | head
```

出現 `5173`（Vite dev server）就是壞的 —— 表示有 `public/hot` 殘檔被帶進映像。

## 已知地雷（都已修好，重建時別改回去）

- **`ext-gd`**：`phpoffice/phpspreadsheet`（經由 `maatwebsite/excel`）需要 gd，但
  `serversideup/php:8.3-fpm-nginx` 基底映像沒有。`Dockerfile` 第 1 與第 3 階段都要
  `USER root` + `install-php-extensions gd`。第 3 階段不能省 —— 大金 Excel 匯入在執行期會用到。
- **`public/hot`**：舊機器跑 `npm run dev` 留下的殘檔，會讓 Laravel 指向不存在的 Vite dev server，
  整個前端掛掉。已加入 `.dockerignore`。

## 注意事項

- 容器啟動時 `docker/init.sh` 會自動執行 `migrate`、`db:seed --class=AdminUserSeeder`、`storage:link`。
  log 裡的 `The [public/storage] link already exists` 是無害的重複執行，可忽略。
- 登入帳號由 `database/seeders/AdminUserSeeder.php` 建立：`lszhan@msn.com`。
- `./database` 與 `./storage` 是 volume 掛載，重建映像檔不會遺失資料。
- 映像檔以 `APP_ENV=production` 建置，前端是 `npm run build` 的靜態產物，**沒有 Vite HMR**。
  改了 `resources/js` 底下的東西，必須重跑 `--build` 才看得到效果。

## 排錯

- 看 log：`docker compose logs app`
- 80 埠被佔用：`docker compose ps` 確認，或改 `docker-compose.yml` 的 port 對應。
- 關閉專案見 `stop-app` skill。
