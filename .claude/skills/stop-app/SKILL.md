---
name: stop-app
description: 關閉這個空調報價系統專案的 Docker 容器。Use when the user says 關閉專案 / 關掉專案 / 停止專案 / 收工 / 關起來, or stop / shut down / close the project, app, containers, or local site.
---

# 關閉空調報價系統

```bash
cd "D:/AI Base/airConditioner" && docker compose down
```

確認已停止並回報：

```bash
docker compose ps
```

輸出應該沒有任何容器（或 `air-app` 不在清單中）。

## 資料安全

`docker compose down` 對這個專案是安全的 —— `docker-compose.yml` 用的是 bind mount
（`./database`、`./storage`），資料留在主機磁碟上，不是具名 volume。

**絕對不要加 `-v` 或 `--volumes`**，也不要用 `docker system prune`。
使用者沒有明確要求刪除資料時，只跑不帶旗標的 `docker compose down`。

## 只是暫停的話

若使用者只想暫停、待會還要繼續用（保留容器狀態，下次啟動更快）：

```bash
docker compose stop
```

之後用 `docker compose start` 恢復，不需要重新建置。
不確定使用者要哪一種時，預設用 `down`。
