## 空調安裝維修報價系統 (Air Conditioning Quotation System)

<p align="center">
    <img src="https://img.shields.io/badge/Built%20with-Gemini%20CLI-blueviolet?style=flat&logo=google-gemini" alt="Built with Gemini CLI">
    <img src="https://img.shields.io/badge/Laravel-12.x-red" alt="Laravel 12">
    <img src="https://img.shields.io/badge/Vue-3.x-green" alt="Vue 3">
    <img src="https://img.shields.io/badge/Tailwind-CSS-blue" alt="Tailwind CSS">
    <img src="https://img.shields.io/badge/Database-SQLite-lightgrey" alt="SQLite">
</p>

本系統是專為**空調工程、安裝與維修**設計的自動化報價管理系統。基於最新的 Laravel 12 與 Vue 3 (Inertia.js) 構建，旨在簡化工程報價流程、確保歷史資料準確性，並能產出符合業界標準的專業 Word 報價單。

---

## 🚀 核心功能

*   **專業報價單管理**：支援兩階段檢視（網頁版快速預覽 + A4 紙張高度還原模擬）。
*   **資料快照機制 (Snapshot)**：成交當下的價格、規格、地址、統編將被永久鎖定，不受未來基礎資料異動影響。
*   **靈活調整項 (Adjustments)**：支援手動輸入資料庫以外的特殊項目（如：折扣、特殊工資、吊車費等）。
*   **智慧連動輸入**：
    *   **顧客連動**：輸入姓名自動帶入電話、地址與統編。
    *   **設備連動**：自動根據品牌帶入型號前綴。
    *   **動態材料行**：輸入完成自動新增下一行，提升錄入效率。
*   **專業報表導出**：一鍵導出為 Word 格式 (`.docx`)，樣式完全對標 A4 預覽版。

---

## 🛠️ 技術棧

### 後端 (Backend)
*   **Framework**: [Laravel 12](https://laravel.com)
*   **Packages**:
    *   `phpoffice/phpword`: 用於生成高品質 Word 報價文件。
    *   `inertiajs/inertia-laravel`: 打造無縫的單頁應用 (SPA) 體驗。
    *   `tightenco/ziggy`: 在 JavaScript 中直接使用 Laravel 路由。

### 前端 (Frontend)
*   **Framework**: [Vue 3 (Composition API)](https://vuejs.org)
*   **Styling**: [Tailwind CSS](https://tailwindcss.com)
*   **Bundler**: [Vite 7](https://vitejs.dev)

### 基礎設施 (Infrastructure)
*   **Database**: SQLite (專為輕量、高效與易於轉移設計)。
*   **Containerization**: Docker (生產環境等級封裝)。

---

## 📦 環境安裝 (Docker)

本專案已完成生產環境封裝，只需一條指令即可完成所有套件安裝、前端編譯與資料庫初始化。

### 1. 系統要求
*   已安裝 [Docker](https://www.docker.com/) 與 [Docker Compose](https://docs.docker.com/compose/)。

### 2. 一鍵啟動
在專案根目錄執行：
```bash
docker compose up -d --build
```

此指令將自動完成：
*   **前端編譯**：執行 `npm run build` 生成最佳化的 JS/CSS。
*   **後端優化**：執行 `composer install --optimize-autoloader`。
*   **環境初始化**：自動生成 `APP_KEY`、建立 `database.sqlite` 並執行資料庫遷移 (`migrate`)。

啟動後，請訪問：[http://localhost](http://localhost)

---

## 📖 開發與維護規範 (GEMINI.md)

本專案遵循嚴格的開發規範，詳細邏輯請參考 `GEMINI.md`：
*   **兩層階層設備管理**：品牌 -> 設備系列 -> 具體規格。
*   **A4 預覽規範**：固定 `210mm x 297mm` 比例，字體優先採用「新細明體」。
*   **表格對齊**：報價單內文字一律靠中對齊。
*   **手動項處理**：特殊項目 ID 為 `NULL`，存取時跳過 Eloquent 關聯，使用原生 Query Builder 確保系統不崩潰。

---

## 📄 License

The Laravel framework is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
