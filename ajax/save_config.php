<?php

/**
 * GLPI Evolution Notify - AJAX save config endpoint.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

Session::checkCSRF($_POST);

$configTable = 'glpi_plugin_evolutionnotify_configs';

$data = [
    'api_url'          => trim((string)($_POST['api_url'] ?? '')),
    'api_token'        => trim((string)($_POST['api_token'] ?? '')),
    'instance'         => trim((string)($_POST['instance'] ?? '')),
    'send_on_waiting'  => isset($_POST['send_on_waiting']) ? 1 : 0,
    'send_on_accepted' => isset($_POST['send_on_accepted']) ? 1 : 0,
    'send_on_refused'  => isset($_POST['send_on_refused']) ? 1 : 0,
];

$iterator = $DB->request(['SELECT' => 'id', 'FROM' => $configTable, 'LIMIT' => 1]);
$row      = count($iterator) > 0 ? $iterator->current() : null;

try {
    if ($row) {
        $DB->update($configTable, $data, ['id' => $row['id']]);
    } else {
        $DB->insertOrDie($configTable, $data);
    }

    Toolbox::logInFile('evolution_notify', "[CONFIG] Config saved via AJAX.\n", true);

    echo json_encode(['ok' => true, 'msg' => 'Configuração salva com sucesso!']);
} catch (\Throwable $e) {
    Toolbox::logInFile('evolution_notify', "[CONFIG ERROR] " . $e->getMessage() . "\n", true);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
