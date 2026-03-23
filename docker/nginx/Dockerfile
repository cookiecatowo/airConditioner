# 使用多階段建構獲取前端資源
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# 最終 Nginx 鏡像
FROM nginx:alpine
WORKDIR /var/www/html

# 複製 Nginx 設定
COPY .docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# 只從建構階段複製 public 資料夾（包含編譯後的 JS/CSS）
COPY --from=node-builder /app/public /var/www/html/public
