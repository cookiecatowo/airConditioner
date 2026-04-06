# ❄️ 空調報價系統開發手冊 (GEMINI.md)

## 📅 2026-04-06 狀態更新
- **進度**：已完成「報價單新增功能 1.0」。
- **重點修正**：電話 (`customer_phone`) 與地址 (`install_address`) 改為 **nullable (選填)**，解決存檔報錯。
- **架構對應**：Docker 三階段建構已設定完成 (PHP -> Node -> Runtime)。

---

## 🛠️ 系統重要線路圖 (核心檔案路徑)

1. **神經中樞 (路由)**: `routes/web.php`
   - 控制網址 `/quotations/create` (看頁面) 與 `POST /quotations` (存檔)。

2. **邏輯大腦 (控制器)**: `app/Http/Controllers/QuotationController.php`
   - 負責驗證資料、計算總額 (`price * quantity`) 並寫入資料庫。

3. **保險箱規則 (模型)**: `app/Models/Quotation.php`
   - 定義哪些欄位可以寫入 (fillable)，包含：姓名、型號、單價、數量、總額、備註。

4. **系統參數 (身分證)**: `.env`
   - 資料庫連線 (SQLite/MySQL) 與 APP_KEY 都在這設定。

---

## 🚀 常用維護指令 (龍哥專用)
- **改完畫面要編譯**：`npm run build`
- **資料庫格式化/更新**：`php artisan migrate:refresh`
- **Docker 啟動/更新**：`docker compose up -d --build`
- **查看系統日誌**：`docker logs air-app`

---

## 📝 待辦事項 (TODO)
- [ ] 測試資料庫數據寫入是否正確。
- [ ] 開發「報價單列表」頁面。
- [ ] 增加 Word/PDF 匯出功能。


# 空調安裝維修報價系統 - 開發手冊 (GEMINI.md)

## 專案概述
本專案為一套專為空調工程設計的報價與管理系統，採用 **Laravel 12**, **Vue 3 (Inertia.js)**, 與 **Tailwind CSS** 構建。

## 技術架構
- **後端**: Laravel 12 / PHP 8.3.x
- **前端**: Vue 3 (Composition API) + Inertia.js + Vite 7
- **資料庫**: SQLite (預設)
- **報表**: 使用 `phpoffice/phpword` 產出 Word 格式報價單。

## 部署環境 (Docker)

### 1. Dockerfile 多階段建構順序 (Critical)
本專案採用三階段建構以優化體積並解決套件依賴：
1.  **Stage 1: php-vendor**: 安裝 PHP 依賴 (Composer)。這是為了獲取 `vendor/tightenco/ziggy` 供前端編譯使用。
2.  **Stage 2: node-builder**: 
    - 複製 `php-vendor` 的成果。
    - 執行 `npm install` 與 `npm run build`。
    - 確保 Vite 能正確讀取 Ziggy 路由檔完成前端打包。
3.  **Stage 3: Production Runtime**:
    - 基於 `serversideup/php:8.3-fpm-nginx`。
    - 複製 Stage 1 & 2 的產出。
    - **APP_KEY 生成**: 在建構時自動生成 `APP_KEY` 並寫入 `.env`。
    - **系統權限處理**: 切換 `USER root` 處理 `/etc/entrypoint.d/` 權限。

### 2. 啟動初始化邏輯 (docker/init.sh)
容器啟動時會自動觸發 `/etc/entrypoint.d/init.sh` 腳本，執行流程如下：
1.  **換行符修復**: Dockerfile 已使用 `sed` 將 Windows CRLF 轉為 Linux LF，確保腳本可執行。
2.  **資料庫自動化**:
    - 執行 `php artisan migrate --force` (建立資料表)。
    - 執行 `php artisan db:seed --class=AdminUserSeeder --force` (確保管理員帳號存在)。
3.  **檔案連結**: 執行 `php artisan storage:link` 確保上傳功能正常。

### 3. Docker Compose 結構
- **Container Name**: `air-app`
- **Port Mapping**: `80:8080` (將外部 80 埠對應至 ServersideUp 內部的 8080)。
- **Volumes (持久化)**:
    - `./database`: 儲存 SQLite 資料庫檔案。
    - `./storage`: 儲存 Log、Session 與使用者上傳的報價單檔案。

### 4. 預設管理員帳號
- **機制**: 透過 `AdminUserSeeder` 的 `updateOrCreate` 實作，確保帳號恆久存在。

## 資料庫核心邏輯
1. **Snapshots (快照機制)**: 
    - `orders` 表記錄施工當下的地址、統編。
    - `order_equipment` 與 `order_material` 記錄成交當下的價格與規格。
2. **手動調整項 (Adjustments)**: 
    - 允許在報價單加入非資料庫現有項目（如：折扣、特殊工資）。
    - **技術實作**: `equipment_id` 與 `material_id` 設為 `nullable`。
    - **儲存/讀取**: 避開 Eloquent 關聯，使用 `DB::table` 直接操作以支援 `NULL` ID。

## UI 與 報表規範 (核心)
### 1. 報價單檢視 (Show.vue)
- **雙重佈局**: 上方為網頁版資訊卡（快速檢視），下方為模擬 A4 紙張預覽（高度還原 PDF）。
- **表格結構 (7 欄位)**: 項目 | 品名 | 規格 | 數量(含單位) | 單價 | 金額 | 備註。
- **文字對齊**: 表格內所有文字一律靠中對齊。

## 待辦事項 (TODO)
- [ ] **報價單詳情頁 (Show)**: 詳情頁中顯示與編輯後台備註(僅顯示在網站中)、與修改報表標題。
- [ ] **顧客管理**: 新增與編輯刪除顧客資料，連結到顧客所關聯的訂單。
- [ ] **報表下載格式**: 擴展報表下載的格式資源，同時可以下載Word與Pdf檔。

## 開發指令參考 (Docker 環境)
- **重新建構並啟動**: `docker compose up -d --build`
- **手動重設管理員**: `docker exec air-app php artisan db:seed --class=AdminUserSeeder --force`
- **查看啟動日誌**: `docker logs air-app`
