<?php

/**
 * GLPI Evolution Notify - AJAX test send endpoint.
 *
 * @license GPLv2+
 */

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

include(GLPI_ROOT . '/inc/includes.php');

global $DB;

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_POST['phone']) || trim($_POST['phone']) === '') {
    echo json_encode(['ok' => false, 'error' => 'Telefone não informado.']);
    exit;
}

$phone = trim($_POST['phone']);

$config = PluginGlpievolutionnotifyNotification::getConfig();
if (!$config) {
    echo json_encode(['ok' => false, 'error' => 'Configure a Evolution API antes de testar.']);
    exit;
}

$sanitized = PluginGlpievolutionnotifyNotification::sanitizePhone($phone);

$message = "*GLPI - Teste de Notificação*\n\n"
    . "Olá! Esta é uma mensagem de teste do plugin *Evolution Notify*.\n"
    . "Se você recebeu esta mensagem, a integração está funcionando corretamente.\n\n"
    . "Data/Hora: " . date('d/m/Y H:i:s');

$url = rtrim($config['api_url'], '/')
    . '/message/sendText/'
    . rawurlencode($config['instance']);

$payload = [
    'number' => $sanitized,
    'text'   => $message,
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'apikey: ' . $config['api_token'],
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
$errno    = curl_errno($ch);

curl_close($ch);

Toolbox::logInFile(
    'evolution_notify',
    "[TEST] Send to $sanitized. HTTP $httpCode\n"
    . ($errno !== 0 ? "cURL error: $error\n" : '')
    . "Response: $response\n",
    true
);

if ($errno !== 0) {
    echo json_encode(['ok' => false, 'error' => "Erro de conexão: $error"]);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode([
        'ok'   => true,
        'msg'  => "Mensagem enviada com sucesso para $sanitized!",
        'http' => $httpCode,
    ]);
} else {
    $respData = json_decode($response, true);
    $detail   = $respData['message'] ?? $respData['error'] ?? $response;
    echo json_encode([
        'ok'    => false,
        'error' => "Evolution API retornou HTTP $httpCode: $detail",
    ]);
}
