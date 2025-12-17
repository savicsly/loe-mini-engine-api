import WebSocket from "ws";

// Reverb WebSocket connection URL
const wsUrl =
    "ws://localhost:8080/app/2g2rmr6hgfbcvvozo9fd?protocol=7&client=js&version=7.0.3";

console.log("Connecting to Reverb at:", wsUrl);

const ws = new WebSocket(wsUrl);

ws.on("open", function open() {
    console.log("✅ Connected to Reverb WebSocket");

    // Subscribe to the user-updates channel
    const subscribeMessage = {
        event: "pusher:subscribe",
        data: {
            channel: "user-updates",
        },
    };

    console.log("📡 Subscribing to user-updates channel...");
    ws.send(JSON.stringify(subscribeMessage));
});

ws.on("message", function message(data) {
    const msg = JSON.parse(data.toString());
    console.log("📨 Received message:", JSON.stringify(msg, null, 2));

    if (msg.event === "pusher:subscription_succeeded") {
        console.log("✅ Successfully subscribed to channel:", msg.channel);
    }

    if (msg.event === "OrderCreated" || msg.event === "BalanceUpdated") {
        console.log("🎉 Received broadcast event:", msg.event);
        console.log("📋 Event data:", JSON.stringify(msg.data, null, 2));
    }
});

ws.on("error", function error(err) {
    console.error("❌ WebSocket error:", err);
});

ws.on("close", function close() {
    console.log("🔌 WebSocket connection closed");
});

// Send ping every 30 seconds to keep connection alive
setInterval(() => {
    if (ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ event: "pusher:ping", data: {} }));
        console.log("🏓 Sent ping");
    }
}, 30000);

console.log("🎧 Listening for events... Press Ctrl+C to exit");
