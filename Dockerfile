# --- 階段 1: 獲取 PHP 依賴 (為了 Ziggy) ---
FROM serversideup/php:8.3-fpm-nginx AS php-vendor
USER root
RUN install-php-extensions gd
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-progress

# --- 階段 2: 建構前端 (Vue 3/Vite) ---
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
COPY --from=php-vendor /app/vendor ./vendor
RUN npm run build

# --- 階段 3: 最終生產環境 ---
FROM serversideup/php:8.3-fpm-nginx
USER root
RUN install-php-extensions gd
USER www-data

WORKDIR /var/www/html

# 從 node-builder 複製編譯好的前端資源
COPY --from=node-builder --chown=www-data:www-data /app/public/build ./public/build

# 複製專案檔案 (排除 .dockerignore 中的檔案)
COPY --chown=www-data:www-data . .

# 再次執行優化後的 composer install
RUN composer install --no-dev --optimize-autoloader --no-interaction

# --- 關鍵修正：在建構時自動生成 .env 並填入 APP_KEY ---
RUN if [ ! -f .env ]; then cp .env.example .env; fi \
    && php artisan key:generate --no-interaction
# 暫時切換到 root 使用者以設定系統目錄
USER root

# 將自動化初始化腳本放入 ServersideUp v3 官方推薦的啟動目錄 (/etc/entrypoint.d/)
COPY docker/init.sh /etc/entrypoint.d/init.sh

# 修正換行符、賦予權限，並確保檔案所有權屬於 www-data
RUN sed -i 's/\r$//' /etc/entrypoint.d/init.sh \
    && chmod +x /etc/entrypoint.d/init.sh \
    && chown www-data:www-data /etc/entrypoint.d/init.sh

# 切換回預設的 www-data 使用者
USER www-data

# 設定環境變數
ENV AUTORUN_ENABLED=true
ENV AUTORUN_LARAVEL_MIGRATION=true
ENV AUTORUN_LARAVEL_SEED=true
ENV AUTORUN_LARAVEL_STORAGE_LINK=true
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_LOCALE=zh_TW
ENV APP_TIMEZONE=Asia/Taipei
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=/var/www/html/database/database.sqlite
ENV SESSION_DRIVER=file

