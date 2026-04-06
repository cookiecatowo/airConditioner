const { GoogleGenerativeAI } = require("@google/generative-ai");
const readline = require("readline");

// 這是帳號P，1758產出的最新鑰匙
const genAI = new GoogleGenerativeAI("AIzaSyDkEVs6oY4Wd4NI7sVg__CBqYhm37yE_r0");

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout,
});

async function run() {
  // 注意：這裡改用 2026 年最新的 2.0 模型
  const model = genAI.getGenerativeModel({ model: "gemini-2.0-flash" });
  console.log("--- Gemini 連線成功 (D槽基地 2.0版) ---");

  const chat = model.startChat();

  const askQuestion = () => {
    rl.question("> 請輸入你想說的話：\n", async (input) => {
      if (input.toLowerCase() === "exit") {
        rl.close();
        return;
      }

      try {
        const result = await chat.sendMessage(input);
        const response = await result.response;
        console.log("\nGemini 回覆：", response.text());
        console.log("\n------------------------------");
      } catch (error) {
        console.error("\n連線出錯，具體原因：", error.message);
      }
      askQuestion();
    });
  };

  askQuestion();
}

run();