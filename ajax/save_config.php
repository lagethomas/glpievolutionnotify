<?php

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

$configTable  = 'glpi_plugin_evolutionnotify_configs';
$entitiesId   = (int)($_POST['entities_id'] ?? 0);

$data = [
    'entities_id'            => $entitiesId,
    'api_url'                => trim((string)($_POST['api_url'] ?? '')),
    'api_token'              => trim((string)($_POST['api_token'] ?? '')),
    'instance'               => trim((string)($_POST['instance'] ?? '')),
    'send_on_waiting'        => (int)($_POST['send_on_waiting'] ?? 0),
    'send_on_accepted'       => (int)($_POST['send_on_accepted'] ?? 0),
    'send_on_refused'        => (int)($_POST['send_on_refused'] ?? 0),
    'send_on_ticket_created' => (int)($_POST['send_on_ticket_created'] ?? 0),
    'send_on_status_changed' => (int)($_POST['send_on_status_changed'] ?? 0),
    'send_on_solution_added' => (int)($_POST['send_on_solution_added'] ?? 0),
    'template_waiting'        => trim((string)($_POST['template_waiting'] ?? '')),
    'template_accepted'       => trim((string)($_POST['template_accepted'] ?? '')),
    'template_refused'        => trim((string)($_POST['template_refused'] ?? '')),
    'template_ticket_created'  => trim((string)($_POST['template_ticket_created'] ?? '')),
    'template_status_changed'  => trim((string)($_POST['template_status_changed'] ?? '')),
    'template_solution_added'  => trim((string)($_POST['template_solution_added'] ?? '')),
];

try {
    $iterator = $DB->request([
        'SELECT' => 'id',
        'FROM'   => $configTable,
        'WHERE'  => ['entities_id' => $entitiesId],
        'LIMIT'  => 1,
    ]);
    $row = count($iterator) > 0 ? $iterator->current() : null;

    if ($row) {
        $DB->update($configTable, $data, ['id' => $row['id']]);
    } else {
        $DB->insertOrDie($configTable, $data);
    }

    Toolbox::logInFile('evolution_notify', "[CONFIG] Saved for entity #$entitiesId.\n", true);

    echo json_encode(['ok' => true, 'msg' => 'Configuração salva com sucesso!']);
} catch (\Throwable $e) {
    Toolbox::logInFile('evolution_notify', "[CONFIG ERROR] " . $e->getMessage() . "\n", true);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
