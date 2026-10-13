<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.local.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Create config.local.php from config.example.php']);
    exit;
}

$config = require $configFile;

if (!is_array($config)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Invalid config.local.php']);
    exit;
}

$token = trim((string) ($config['api_token'] ?? ''));
if ($token === '' || $token === 'YOUR_KEYCRM_API_TOKEN') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Set api_token in config.local.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

$name = trim((string) ($input['name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));

if ($name === '' && $email === '' && $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Provide at least one of: name, email, phone']);
    exit;
}

$sourceId = (int) ($input['source_id'] ?? ($config['default_source_id'] ?? 0));
$allowedSourceIds = $config['allowed_source_ids'] ?? [];
if ($sourceId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'source_id is required']);
    exit;
}
if (is_array($allowedSourceIds) && $allowedSourceIds !== [] && !in_array($sourceId, $allowedSourceIds, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'source_id is not allowed']);
    exit;
}

$marketing = array_filter([
    'utm_source' => (string) ($input['utm_source'] ?? ''),
    'utm_medium' => (string) ($input['utm_medium'] ?? ''),
    'utm_campaign' => (string) ($input['utm_campaign'] ?? ''),
    'utm_term' => (string) ($input['utm_term'] ?? ''),
    'utm_content' => (string) ($input['utm_content'] ?? ''),
], static fn(string $value): bool => $value !== '');

$mode = (string) ($input['mode'] ?? ($config['default_mode'] ?? 'order'));
if ($mode === 'pipeline') {
    $payload = [
        'source_id' => $sourceId,
        'contact' => array_filter([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
        ], static fn(string $value): bool => $value !== ''),
        'utm_source' => (string) ($input['utm_source'] ?? ''),
        'utm_medium' => (string) ($input['utm_medium'] ?? ''),
        'utm_campaign' => (string) ($input['utm_campaign'] ?? ''),
        'utm_term' => (string) ($input['utm_term'] ?? ''),
        'utm_content' => (string) ($input['utm_content'] ?? ''),
    ];

  if (!empty($config['pipeline_id'])) {
        $payload['pipeline_id'] = (int) $config['pipeline_id'];
    }

    $url = 'https://openapi.keycrm.app/v1/pipelines/cards';
} else {
    $payload = [
        'source_id' => $sourceId,
        'buyer' => array_filter([
            'full_name' => $name,
            'email' => $email,
            'phone' => $phone,
        ], static fn(string $value): bool => $value !== ''),
    ];

    if ($marketing !== []) {
        $payload['marketing'] = $marketing;
    }

    if (!empty($input['product_sku'])) {
        $payload['products'] = [[
            'sku' => (string) $input['product_sku'],
            'quantity' => isset($input['quantity']) ? max(1, (int) $input['quantity']) : 1,
            'price' => isset($input['price']) ? (float) $input['price'] : 0,
        ]];
    }

    $url = 'https://openapi.keycrm.app/v1/order';
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ],
]);

$responseBody = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($responseBody === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $curlError !== '' ? $curlError : 'KeyCRM request failed']);
    exit;
}

$decoded = json_decode($responseBody, true);
$success = $httpCode >= 200 && $httpCode < 300;

http_response_code($success ? 200 : $httpCode);
echo json_encode([
    'ok' => $success,
    'mode' => $mode,
    'source_id' => $sourceId,
    'marketing' => $marketing,
    'keycrm' => is_array($decoded) ? $decoded : ['raw' => $responseBody],
    'error' => $success ? null : ($decoded['message'] ?? $decoded['error'] ?? 'KeyCRM returned an error'),
], JSON_UNESCAPED_UNICODE);
