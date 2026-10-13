<?php
/**
 * Проксі-ендпоінт на VPS: приймає POST з сайту і створює замовлення в KeyCRM з UTM.
 *
 * Налаштування:
 * 1) У KeyCRM: Налаштування → API → ключ; джерело замовлень → source_id.
 * 2) На VPS: export KEYCRM_API_TOKEN='...' або прописати нижче (не комітьте токен у git).
 * 3) Змініть SOURCE_ID на ваше джерело з KeyCRM.
 */

header('Content-Type: application/json; charset=utf-8');

// Змініть на ваш source_id з KeyCRM
define('KEYCRM_SOURCE_ID', 0);

function env($key, $default = '') {
  $v = getenv($key);
  return $v !== false && $v !== '' ? $v : $default;
}

$token = env('KEYCRM_API_TOKEN', '');
if ($token === '') {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'KEYCRM_API_TOKEN is not set']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  $input = $_POST;
}

$name  = isset($input['name']) ? trim((string) $input['name']) : '';
$email = isset($input['email']) ? trim((string) $input['email']) : '';
$phone = isset($input['phone']) ? trim((string) $input['phone']) : '';

if ($name === '' && $email === '' && $phone === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Provide at least one of: name, email, phone']);
  exit;
}

$utmSource   = isset($input['utm_source']) ? (string) $input['utm_source'] : '';
$utmCampaign = isset($input['utm_campaign']) ? (string) $input['utm_campaign'] : '';
$utmContent  = isset($input['utm_content']) ? (string) $input['utm_content'] : '';
$utmTerm     = isset($input['utm_term']) ? (string) $input['utm_term'] : '';
$utmMedium   = isset($input['utm_medium']) ? (string) $input['utm_medium'] : '';

$marketing = array_filter([
  'utm_source'   => $utmSource,
  'utm_medium'   => $utmMedium,
  'utm_campaign' => $utmCampaign,
  'utm_term'     => $utmTerm,
  'utm_content'  => $utmContent,
], function ($v) {
  return $v !== '';
});

$data = [
  'source_id' => KEYCRM_SOURCE_ID,
  'buyer' => array_filter([
    'full_name' => $name,
    'email'     => $email,
    'phone'     => $phone,
  ], function ($v) {
    return $v !== '';
  }),
];

if (!empty($marketing)) {
  $data['marketing'] = $marketing;
}

// Приклад товару — підставте свій sku з каталогу KeyCRM або приберіть блок, якщо API дозволяє без товарів
if (!empty($input['product_sku'])) {
  $data['products'] = [[
    'sku'      => (string) $input['product_sku'],
    'quantity' => isset($input['quantity']) ? (int) $input['quantity'] : 1,
    'price'    => isset($input['price']) ? (float) $input['price'] : 0,
  ]];
}

if (KEYCRM_SOURCE_ID === 0) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Set KEYCRM_SOURCE_ID in order-to-keycrm.php']);
  exit;
}

$payload = json_encode($data, JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://openapi.keycrm.app/v1/order');
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $payload,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token,
  ],
]);

$responseBody = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode >= 200 && $httpCode < 300 ? 200 : $httpCode);
echo $responseBody !== false ? $responseBody : json_encode(['ok' => false, 'error' => 'curl failed']);
