<?php
declare(strict_types=1);

const WEBHOOK_URL    = 'https://discord.com/api/webhooks/TAVS_WEBHOOK_SEIT';
const ALLOWED_DOMAIN = '@jak.lv';
const MAX_BODY_BYTES = 4096;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_BODY_BYTES) { http_response_code(413); exit; }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); exit; }

$email   = substr((string) ($data['email'] ?? ''), 0, 200);
$action  = substr((string) ($data['action'] ?? 'Event'), 0, 80);
$phone   = isset($data['phone'])   ? substr((string) $data['phone'], 0, 32)   : null;
$country = isset($data['country']) ? substr((string) $data['country'], 0, 4)  : null;

if ($email && !str_ends_with($email, ALLOWED_DOMAIN)) { http_response_code(422); exit; }

$fields = [];

if ($email) {
    $fields[] = ['name' => '📧 E-pasts', 'value' => $email, 'inline' => true];
}

$fields[] = [
    'name'   => '🔑 Parole ievadīta?',
    'value'  => !empty($data['hasPassword']) ? '✅ Jā' : '❌ Nē',
    'inline' => true,
];

// ── TE PARĀDĀSIES NUMURS ──
if ($phone !== null && $phone !== '') {
    $phoneDisplay = $phone;
    if ($country) {
        $phoneDisplay .= " ({$country})";
    }
    $fields[] = [
        'name'   => '📱 Telefona numurs',
        'value'  => $phoneDisplay,
        'inline' => true,
    ];
}

$fields[] = [
    'name'   => '🔄 Mēģinājums',
    'value'  => (string) (int) ($data['attempt'] ?? 1),
    'inline' => true,
];

$fields[] = [
    'name'   => '🕐 Laiks',
    'value'  => date('c'),
    'inline' => false,
];

$payload = json_encode([
    'embeds' => [[
        'title'     => '🔐 ' . $action,
        'color'     => !empty($data['clickOnly']) ? 0x00bfff : 0xff4444,
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
http_response_code(204);
