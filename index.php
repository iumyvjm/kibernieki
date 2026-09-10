<?php
// /t/index.php — link tracker. /t/<student> → Discord + redirect.
declare(strict_types=1);

const WEBHOOK_URL        = 'https://discord.com/api/webhooks/1547540540994621440/I295Pa8pLWiW3OzAbSUTbU-ad-prxpoXqJkeyQDCZF1xYjEZ0hNoz47Y8aQuDF_Bnk8w';
const ALLOWED_DOMAIN     = '@jak.lv';
const LOG_DIR            = __DIR__ . '/.logs';
const RATE_LIMIT_SECONDS = 5;
const RATE_LIMIT_FILE    = LOG_DIR . '/rate.json';
const ALLOW_LOGGING_IP   = true;

$path    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$parts   = array_values(array_filter(explode('/', trim($path, '/'))));
$student = end($parts) ?: '';

if (!preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $student) || str_contains($student, '..')) {
    $student = 'unknown';
}

$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$botPatterns = ['bot', 'crawler', 'spider', 'preview', 'scanner', 'curl', 'wget', 'python-requests'];
foreach ($botPatterns as $needle) {
    if (str_contains($ua, $needle)) {
        header('Location: https://edu-mykoob.com/');
        exit;
    }
}

@mkdir(LOG_DIR, 0700, true);
$now   = time();
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipKey = hash('sha256', $ip . '|' . $student);
$rate  = is_file(RATE_LIMIT_FILE) ? (json_decode((string) file_get_contents(RATE_LIMIT_FILE), true) ?: []) : [];
$rate  = array_filter($rate, fn($t) => ($now - (int) $t) < RATE_LIMIT_SECONDS);
if (isset($rate[$ipKey])) {
    header('Location: https://edu-mykoob.com/');
    exit;
}
$rate[$ipKey] = $now;
file_put_contents(RATE_LIMIT_FILE, json_encode($rate), LOCK_EX);

$email  = $student !== 'unknown' ? "{$student}" . ALLOWED_DOMAIN : 'unknown';
$fields = [
    ['name' => '📧 E-pasts', 'value' => $email, 'inline' => true],
    ['name' => '🕐 Laiks',   'value' => date('Y-m-d H:i:s'), 'inline' => true],
];
if (ALLOW_LOGGING_IP) {
    $fields[] = ['name' => '🌐 IP', 'value' => $ip, 'inline' => true];
}

$payload = json_encode([
    'embeds' => [[
        'title'     => '👆 Saites atvēršana',
        'color'     => 0x00bfff,
        'fields'    => $fields,
        'footer'    => ['text' => 'Mykoob Phishing Simulation'],
        'timestamp' => date('c'),
    ]],
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(WEBHOOK_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 2,
    CURLOPT_CONNECTTIMEOUT => 1,
]);
curl_exec($ch);
curl_close($ch);

header('Location: https://edu-mykoob.com/');
exit;
