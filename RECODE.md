# 空調報價系統開發手冊 (RECODE.md)

---

## 系統重要線路圖 (核心檔案路徑)

1. **路由**: `routes/web.php`
   - 訂單 CRUD: `orders.index / create / store / show / edit / update / destroy`
   - 狀態更新: `PATCH /orders/{order}/status` → `orders.updateStatus`
   - 快速編輯: `PATCH /orders/{order}/quick-edit` → `orders.quickEdit`
   - 大金匯入: `POST /import/daikin` → `ImportController@importDaikin`
   - Word 匯出: `GET /orders/{order}/export` → `orders.export`

2. **邏輯大腦 (控制器)**: `app/Http/Controllers/OrderController.php`
   - 核心控制器。負責建立/編輯訂單、計算金額、處理設備與材料 pivot。
   - `index()`: 支援搜尋(search)、四維度篩選(ps/pmt/tp/wc)。
   - `store() / update()`: 驗證、儲存設備(含 unit)、材料、調整項。
   - `quickEdit()`: 快速更新 report_title 與 notes (inline 編輯)。
   - `updateStatus()`: 更新 processing_status / payment_status，規則：未結清不能設已完成。
   - `export()`: 使用 phpoffice/phpword 產出 Word 報價單。
   - `loadOrderFullDetails()`: 讀取訂單含完整 pivot (unit, price, quantity...)。
   - `syncOrderItems()`: 同步設備/材料/調整項到 pivot 表。

3. **大金匯入**: `app/Imports/DaikinProductsImport.php`
   - 使用 PhpSpreadsheet 直接讀取 Excel（非 maatwebsite/excel）。
   - 公開方法: `import(string $filePath): int` 回傳寫入筆數。
   - 支援兩種版型：一般型(外內機發票價在 col[11])、多聯型(col[9])。
   - model_name = sheet標題 + "室外機"/"室內機"；specs = 型號；cost = 發票價×0.88；sale = 發票價。
   - 寫入 `equipments` 表，品牌固定為 "Daikin 大金"。

4. **模型**: `app/Models/Order.php`
   - fillable 含: type, work_category, processing_status, payment_status, report_title, notes...
   - `equipments()` 關聯的 withPivot 含: unit, quantity, price, model_number...

5. **系統參數**: `.env`
   - 資料庫: SQLite (database/database.sqlite)
   - APP_URL: http://localhost:8000

---

## 本地開發啟動方式 (Laravel Herd)

```bash
# 在專案目錄下執行
composer run dev
```

- 啟動網址: **http://localhost:8000**
- 這個指令會同時啟動:
  - `php artisan serve` (後端)
  - `php artisan queue:listen` (佇列)
  - `npm run dev` (Vite 前端)
- **注意**: Windows 環境不支援 `php artisan pail`，已從 dev script 移除。
- Composer 路徑 (Herd): `C:\Users\user\.config\herd\bin\composer.bat`

---

## 資料庫核心邏輯

### 主要資料表
- `orders`: 訂單主表，含 type, work_category, processing_status, payment_status, report_title, notes, address, tax_id, total_amount...
- `order_equipment`: 設備 pivot，含 unit, quantity, price, model_number, specs, adjustment_name...
- `order_material`: 材料 pivot，含 quantity, price, adjustment_name...
- `equipments`: 設備主表 (品牌/型號/成本/售價)
- `materials`: 材料主表
- `customers`: 顧客主表

### 核心設計決策
1. **快照機制**: `order_equipment` / `order_material` 記錄成交當下的價格與規格，不隨主表變動。
2. **手動調整項**: `equipment_id` 與 `material_id` 設為 nullable，允許非資料庫現有項目（折扣、特殊工資等）。
3. **儲存調整項**: 避開 Eloquent 關聯，使用 `DB::table` 直接操作以支援 NULL ID。

### 訂單分類設計 (兩個獨立欄位)
- **`type`** (install/repair/maintenance): 控制是否顯示「設備」區塊。install 才有設備；repair/maintenance 只有材料。
- **`work_category`** (ac/surveillance/other): 業務分類標籤，純顯示用，不影響任何邏輯。

### 狀態欄位
- **`processing_status`**: 1=處理中, 2=已完成, 3=垃圾桶
- **`payment_status`**: 1=未收款, 2=已收訂金, 3=已結清
- **業務規則**: payment_status 未達 3(已結清) 時，不允許設定 processing_status=2(已完成)。

---

## 資料庫 Migration 記錄 (2026-04-07 新增)

| 檔案 | 內容 |
|------|------|
| `2026_04_07_000000_add_unit_to_order_equipment_table.php` | 新增 `unit` 欄位 (預設 '台') |
| `2026_04_07_100000_add_status_to_orders_table.php` | 新增 `processing_status`, `payment_status` |
| `2026_04_07_110000_add_report_title_to_orders_table.php` | 新增 `report_title` (預設 '估價單') |
| `2026_04_07_120000_add_work_category_to_orders_table.php` | 新增 `work_category` (nullable) |

---

## 前端頁面說明

### `Orders/Create.vue` & `Edit.vue`
- 預設材料: 建立新訂單時自動填入 10 筆常用材料（可修改/清空）。
- 設備欄位: 數量與單位並排顯示，單位可自由輸入（預設 '台'）。
- 類型切換: 切換到維修/保養時自動清空設備列表。
- 材料區塊: 右上角有「清空」按鈕。

### `Orders/Index.vue`
- 四維度篩選按鈕: 業務(wc) / 類型(tp) / 處理(ps) / 收款(pmt)，多選，空選=不篩選。
- **重要**: 篩選狀態使用 `computed()` 從 `props.filters` 衍生，不用 `ref()`。原因：Inertia.js 同元件導航時不重新初始化 ref，導致狀態與 URL 脫鉤。
- 類型欄: 先顯示業務分類(work_category) badge，下方顯示訂單類型(type) badge。
- 狀態欄: processing_status 和 payment_status 各一個下拉選單，直接 PATCH 更新。
- 垃圾桶訂單 (processing_status=3): 列表顯示半透明 (opacity-50)。

### `Orders/Show.vue`
- A4 預覽區: 報表標題(report_title)可直接點擊編輯，blur 後自動儲存。
- 內部備註: 右側 amber 色文字區，有獨立「儲存備註」按鈕與「已儲存✓」回饋。
- 資訊卡: 顯示訂單類型與業務分類 badge。
- 以上均透過 `PATCH /orders/{id}/quick-edit` 儲存，不重新載入頁面。

---

## 常用維護指令

```bash
# 開發
composer run dev                          # 啟動本地伺服器
npm run build                             # 編譯前端資源

# 資料庫
php artisan migrate                       # 執行新 migration
php artisan migrate:fresh --seed          # 重置並填入種子資料

# 大金資料匯入
# 透過網頁 /import/daikin 頁面上傳 .xlsx 檔案
```

---

## 待辦事項 (TODO)

- [ ] **顧客管理**: 新增與編輯刪除顧客資料，連結到顧客所關聯的訂單。
- [ ] **報表下載格式**: 同時支援下載 Word 與 PDF 格式。
- [x] ~~**報價單詳情頁 (Show)**: inline 編輯報表標題與內部備註~~ ← 已完成 (2026-04-07)

---

## 技術架構

- **後端**: Laravel 12 / PHP 8.3.x
- **前端**: Vue 3 (Composition API) + Inertia.js + Vite 7
- **CSS**: Tailwind CSS
- **資料庫**: SQLite (預設)
- **報表**: `phpoffice/phpword` → Word 格式
- **Excel 解析**: `phpoffice/phpspreadsheet` (直接使用，非 maatwebsite/excel)
- **本地環境**: Laravel Herd (Windows)
