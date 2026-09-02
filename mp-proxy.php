<?php
/**
 * Proxy Mercado Pago — coloque este arquivo na mesma pasta da loja (HTTPS).
 * Evita erro de CORS e não depende de proxy externo.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Idempotency-Key, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Use POST']);
  exit;
}

// Access Token (mesmo do checkout — em produção use variável de ambiente)
$MP_ACCESS_TOKEN = 'APP_USR-798594934317014-090120-786513793dfbfaa8b1aa358fb7f7fff9-3659527026';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
  http_response_code(400);
  echo json_encode(['error' => 'JSON inválido']);
  exit;
}

$action = isset($body['_action']) ? $body['_action'] : 'payments';
unset($body['_action']);

if ($action === 'payments') {
  $url = 'https://api.mercadopago.com/v1/payments';
} else {
  http_response_code(400);
  echo json_encode(['error' => 'Ação inválida']);
  exit;
}

$idem = isset($_SERVER['HTTP_X_IDEMPOTENCY_KEY']) ? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] : ('php-' . uniqid());

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $MP_ACCESS_TOKEN,
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Idempotency-Key: ' . $idem,
  ],
  CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
  CURLOPT_TIMEOUT => 45,
]);
$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno) {
  http_response_code(502);
  echo json_encode(['error' => 'Falha cURL: ' . $error]);
  exit;
}

http_response_code($status > 0 ? $status : 502);
echo $response !== false ? $response : json_encode(['error' => 'Resposta vazia']);
