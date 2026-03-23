#!/bin/sh

# 確保在啟動時執行 Migrate 與 Seed
echo " Running Laravel database automations..."
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan storage:link
