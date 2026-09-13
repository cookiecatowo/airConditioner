---
name: upload
description: 把目前的改動 commit 並 push 到 GitHub（origin/main）。Use when the user says 上傳 / 上傳一下 / 提交並推送 / 推上去 / 存檔上傳, or commit and push the current changes.
---

# 上傳（commit + push）

遠端：`origin` → https://github.com/cookiecatowo/airConditioner.git，分支 `main`。
使用者說「上傳」就是授權這次的 commit 與 push，不需要再問一次要不要推。
但下面的檢查步驟不能省。

## 步驟

1. 先看清楚要提交什麼：

   ```bash
   cd "D:/AI Base/airConditioner" && git status --short && git diff --stat
   ```

2. **檢查有沒有不該進版控的東西**。特別留意：
   - `.env`（含 `APP_KEY`，已在 `.gitignore`，但若出現在清單裡要停下來問使用者）
   - `database/database.sqlite`（客戶與訂單資料）
   - 任何 `*.xlsx`、`*.docx` 報價檔或含個資的檔案

   看到可疑檔案就停下來問，不要自行 `git add`。

3. 暫存與提交。commit message 用繁體中文，簡短描述這次改了什麼
   （跟著現有歷史的風格，例如「新增業務類型」「品牌未新建的Bug」）：

   ```bash
   git add -A
   git diff --cached --name-only   # 確認清單符合預期再 commit
   git commit -m "訊息"
   ```

   訊息結尾要加上這一行（中間空一行）：

   ```
   Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
   ```

4. 推送：

   ```bash
   git push origin main
   ```

5. 回報：commit hash、訊息，以及推了幾個 commit。

## 注意事項

- 本機分支可能領先 origin 好幾個 commit（之前累積的），push 會一次推完。
  推之前先用 `git log --oneline origin/main..HEAD` 看清楚會推出去哪些，並在回報時說明。
- push 被拒（遠端有新 commit）時，先 `git fetch` 看差異再決定，**不要**用 `--force`。
- 不要用 `--no-verify` 跳過 hook。
- **commit 前務必確認 staged 清單**：先前的 `git add` 可能在失敗的 commit 後仍殘留，
  導致下一個 commit 吃進非預期的檔案。用 `git diff --cached --name-only` 核對。
- 還沒 push 的 commit 切錯了，用 `git reset --soft HEAD~1` 退回重做（保留工作區內容）。

## 給接手者的說明

這個專案之後由不會寫程式的使用者管理。對方說「上傳」時：
- commit message 自己決定，不用問。
- 但如果工作區裡有你不確定是誰改的、或看起來不該提交的檔案，
  用白話問他（例如「有個檔案叫 XXX，是你改的嗎？要一起存嗎？」），不要自行判斷後默默帶過。
- 推送完回報「已上傳，存了 N 筆修改」即可，不用貼 hash 和 diff。
