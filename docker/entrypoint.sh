#!/bin/sh

# 確保環境變數檔案存在
if [ ! -f .env ]; then
    cp .env.example .env
fi

# 產生成金鑰 (如果尚未生成)
if ! grep -q "APP_KEY=base64" .env; then
    php artisan key:generate
fi

# 建立 SQLite 資料庫檔案 (如果不存在)
touch database/database.sqlite

# 執行資料庫遷移 (自動執行)
php artisan migrate --force

# 設定目錄權限，確保 www-data 有權寫入掛載的 volume
chown -R www-data:www-data storage bootstrap/cache database

# 啟動 PHP-FPM
exec php-fpm
