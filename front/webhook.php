<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

include(GLPI_ROOT . '/inc/includes.php');

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

$event = $payload['event'] ?? '';
$item  = $payload['item'] ?? [];

if (empty($event) || empty($item)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing event or item fields.']);
    exit;
}

// Only process approval.* events
if (!str_starts_with($event, 'approval.')) {
    echo json_encode(['ok' => true, 'msg' => 'Event ignored (not an approval event).']);
    exit;
}

try {
    PluginGlpievolutionnotifyNotification::handleWebhook($payload);
    echo json_encode(['ok' => true, 'msg' => 'Notification processed.']);
} catch (\Throwable $e) {
    Toolbox::logInFile('evolution_notify', "[WEBHOOK ERROR] " . $e->getMessage() . "\n", true);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
