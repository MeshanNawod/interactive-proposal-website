<?php
/*
 * Valentine Response Server
 * ------------------------------------------------------------
 * Receives a YES/NO response from the Valentine HTML and sends
 * a Telegram notification.
 *
 * SECURITY:
 * - Keep this PHP file private to your own server.
 * - Put your Telegram bot token and chat ID below.
 * - Do NOT put the Telegram bot token in the HTML file.
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Content-Type: application/json");
    echo json_encode(["ok" => false, "error" => "POST only"]);
    exit;
}

/* ====== CHANGE THESE TWO VALUES ====== */
$BOT_TOKEN = "PUT_YOUR_TELEGRAM_BOT_TOKEN_HERE";
$CHAT_ID   = "PUT_YOUR_TELEGRAM_CHAT_ID_HERE";
/* ===================================== */

if (
    $BOT_TOKEN === "PUT_YOUR_TELEGRAM_BOT_TOKEN_HERE" ||
    $CHAT_ID === "PUT_YOUR_TELEGRAM_CHAT_ID_HERE"
) {
    http_response_code(500);
    header("Content-Type: application/json");
    echo json_encode(["ok" => false, "error" => "Telegram is not configured"]);
    exit;
}

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode(["ok" => false, "error" => "Invalid JSON"]);
    exit;
}

$response = strtoupper(trim((string)($data["response"] ?? "")));
if ($response !== "YES" && $response !== "NO") {
    http_response_code(400);
    header("Content-Type: application/json");
    echo json_encode(["ok" => false, "error" => "Invalid response"]);
    exit;
}

$id = trim((string)($data["id"] ?? ""));
$recipient = trim((string)($data["recipient"] ?? ""));
$sender = trim((string)($data["sender"] ?? ""));
$clientTime = trim((string)($data["clientTime"] ?? ""));
$page = trim((string)($data["page"] ?? ""));

function clean_text($value, $max = 300) {
    $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
    return mb_substr($value, 0, $max);
}

$id = clean_text($id, 100);
$recipient = clean_text($recipient, 100);
$sender = clean_text($sender, 100);
$clientTime = clean_text($clientTime, 100);
$page = clean_text($page, 500);

$emoji = ($response === "YES") ? "❤️" : "💔";

$message =
    $emoji . " <b>Valentine Response</b>\n\n" .
    "<b>Response:</b> " . htmlspecialchars($response, ENT_QUOTES, "UTF-8") . "\n" .
    "<b>Recipient:</b> " . htmlspecialchars($recipient ?: "Unknown", ENT_QUOTES, "UTF-8") . "\n" .
    "<b>Valentine ID:</b> <code>" . htmlspecialchars($id ?: "Unknown", ENT_QUOTES, "UTF-8") . "</code>\n" .
    "<b>Time:</b> " . htmlspecialchars($clientTime ?: date("c"), ENT_QUOTES, "UTF-8") . "\n";

if ($sender !== "") {
    $message .= "<b>Sender:</b> " . htmlspecialchars($sender, ENT_QUOTES, "UTF-8") . "\n";
}

/* Do not send the full page URL if you don't want it in Telegram. */
if ($page !== "") {
    $message .= "<b>Page:</b> " . htmlspecialchars($page, ENT_QUOTES, "UTF-8") . "\n";
}

$url = "https://api.telegram.org/bot" . rawurlencode($BOT_TOKEN) . "/sendMessage";

$postFields = [
    "chat_id" => $CHAT_ID,
    "text" => $message,
    "parse_mode" => "HTML",
    "disable_web_page_preview" => "true"
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postFields),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
]);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

header("Content-Type: application/json");

if ($result === false || $httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);
    echo json_encode([
        "ok" => false,
        "error" => "Telegram request failed",
        "details" => $curlError
    ]);
    exit;
}

echo json_encode(["ok" => true]);
?>
