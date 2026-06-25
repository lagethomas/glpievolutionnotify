<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

define('PLUGIN_GLPINVOLUTIONNOTIFY_DIR', __DIR__);

define('PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_WAITING',
    "*GLPI - Notificação de Validação*\n\n"
    . "Olá *{approver}*,\n\n"
    . "O chamado *{ticket_id} - {ticket_title}*\n"
    . "Está com status: *{status}*\n\n"
    . "{comment_block}"
    . "Acesse: {url}"
);

define('PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_ACCEPTED',
    "*GLPI - Validação Aprovada*\n\n"
    . "Olá *{requester}*,\n\n"
    . "O chamado *{ticket_id} - {ticket_title}*\n"
    . "Foi *{status}* por *{approver}*\n\n"
    . "{comment_block}"
    . "Acesse: {url}"
);

define('PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_REFUSED',
    "*GLPI - Validação Recusada*\n\n"
    . "Olá *{requester}*,\n\n"
    . "O chamado *{ticket_id} - {ticket_title}*\n"
    . "Foi *{status}* por *{approver}*\n\n"
    . "{comment_block}"
    . "Acesse: {url}"
);

define('PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_TICKET_CREATED',
    "*GLPI - Novo Chamado*\n\n"
    . "Olá *{requester}*,\n\n"
    . "Seu chamado *{ticket_id} - {ticket_title}*\n"
    . "Foi criado com sucesso.\n\n"
    . "Acesse: {url}"
);

define('PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_STATUS_CHANGED',
    "*GLPI - Status Alterado*\n\n"
    . "Olá *{requester}*,\n\n"
    . "O chamado *{ticket_id} - {ticket_title}*\n"
    . "Teve o status alterado para: *{status}*\n\n"
    . "Acesse: {url}"
);

define('PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_SOLUTION_ADDED',
    "*GLPI - Solução Adicionada*\n\n"
    . "Olá *{requester}*,\n\n"
    . "O chamado *{ticket_id} - {ticket_title}*\n"
    . "Possui uma nova solução.\n\n"
    . "{comment_block}"
    . "Acesse: {url}"
);

function plugin_glpievolutionnotify_install(array $params = []): bool
{
    global $DB;

    try {
        $table1 = 'glpi_plugin_evolutionnotify_configs';
        $table2 = 'glpi_plugin_evolutionnotify_notified';

        if (!$DB->tableExists($table1)) {
            $query = "CREATE TABLE `$table1` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` int NOT NULL DEFAULT 0,
                `api_url` varchar(255) NOT NULL DEFAULT '',
                `api_token` varchar(255) NOT NULL DEFAULT '',
                `instance` varchar(255) NOT NULL DEFAULT '',
                `send_on_waiting` tinyint(1) NOT NULL DEFAULT 1,
                `send_on_accepted` tinyint(1) NOT NULL DEFAULT 1,
                `send_on_refused` tinyint(1) NOT NULL DEFAULT 1,
                `send_on_ticket_created` tinyint(1) NOT NULL DEFAULT 0,
                `send_on_status_changed` tinyint(1) NOT NULL DEFAULT 0,
                `send_on_solution_added` tinyint(1) NOT NULL DEFAULT 0,
                `template_waiting` text DEFAULT NULL,
                `template_accepted` text DEFAULT NULL,
                `template_refused` text DEFAULT NULL,
                `template_ticket_created` text DEFAULT NULL,
                `template_status_changed` text DEFAULT NULL,
                `template_solution_added` text DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `entities_id` (`entities_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $result = $DB->doQuery($query);
            if (!$result) {
                Toolbox::logInFile('evolution_notify', "[INSTALL ERROR] Failed to create config table: " . $DB->error() . "\n", true);
                return false;
            }
        } else {
            $migrations = [
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `entities_id` int NOT NULL DEFAULT 0 AFTER `id`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `send_on_ticket_created` tinyint(1) NOT NULL DEFAULT 0 AFTER `send_on_refused`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `send_on_status_changed` tinyint(1) NOT NULL DEFAULT 0 AFTER `send_on_ticket_created`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `send_on_solution_added` tinyint(1) NOT NULL DEFAULT 0 AFTER `send_on_status_changed`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `template_waiting` text DEFAULT NULL AFTER `send_on_solution_added`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `template_accepted` text DEFAULT NULL AFTER `template_waiting`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `template_refused` text DEFAULT NULL AFTER `template_accepted`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `template_ticket_created` text DEFAULT NULL AFTER `template_refused`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `template_status_changed` text DEFAULT NULL AFTER `template_ticket_created`",
                "ALTER TABLE `$table1` ADD COLUMN IF NOT EXISTS `template_solution_added` text DEFAULT NULL AFTER `template_status_changed`",
            ];
            foreach ($migrations as $sql) {
                try {
                    $DB->doQuery($sql);
                } catch (\Throwable $e) {
                    Toolbox::logInFile('evolution_notify', "[MIGRATION] Skipped (likely already exists): " . $e->getMessage() . "\n", true);
                }
            }
        }

        if (!$DB->tableExists($table2)) {
            $query = "CREATE TABLE `$table2` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `itemtype` varchar(50) NOT NULL DEFAULT '',
                `items_id` int unsigned NOT NULL DEFAULT 0,
                `tickets_id` int unsigned NOT NULL DEFAULT 0,
                `event_type` varchar(30) NOT NULL DEFAULT '',
                `phone` varchar(30) NOT NULL DEFAULT '',
                `http_code` int NOT NULL DEFAULT 0,
                `notified_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `item` (`itemtype`, `items_id`),
                KEY `tickets_id` (`tickets_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $result = $DB->doQuery($query);
            if (!$result) {
                Toolbox::logInFile('evolution_notify', "[INSTALL ERROR] Failed to create notified table: " . $DB->error() . "\n", true);
                return false;
            }
        } else {
            // Drop old column that conflicts with new schema
            $oldCol = $DB->doQuery("SHOW COLUMNS FROM `$table2` LIKE 'ticketvalidations_id'");
            if ($oldCol && $oldCol->num_rows > 0) {
                $DB->doQuery("ALTER TABLE `$table2` DROP COLUMN `ticketvalidations_id`");
            }

            $migrations2 = [
                "ALTER TABLE `$table2` ADD COLUMN IF NOT EXISTS `itemtype` varchar(50) NOT NULL DEFAULT '' AFTER `id`",
                "ALTER TABLE `$table2` ADD COLUMN IF NOT EXISTS `items_id` int unsigned NOT NULL DEFAULT 0 AFTER `itemtype`",
                "ALTER TABLE `$table2` ADD COLUMN IF NOT EXISTS `tickets_id` int unsigned NOT NULL DEFAULT 0 AFTER `items_id`",
                "ALTER TABLE `$table2` ADD COLUMN IF NOT EXISTS `http_code` int NOT NULL DEFAULT 0 AFTER `phone`",
                "ALTER TABLE `$table2` MODIFY COLUMN `event_type` varchar(30) NOT NULL DEFAULT ''",
            ];
            foreach ($migrations2 as $sql) {
                try {
                    $DB->doQuery($sql);
                } catch (\Throwable $e) {
                    Toolbox::logInFile('evolution_notify', "[MIGRATION] Skipped: " . $e->getMessage() . "\n", true);
                }
            }
        }

        $iterator = $DB->request(['SELECT' => 'id', 'FROM' => $table1, 'LIMIT' => 1]);
        if (count($iterator) === 0) {
            $DB->insertOrDie($table1, [
                'entities_id' => 0,
                'api_url'     => 'http://localhost:8080',
                'api_token'   => '',
                'instance'    => 'default',
            ]);
        }

        CronTask::Register(PluginGlpievolutionnotifyNotification::class, 'cronNotify', MINUTE_TIMESTAMP, [
            'param' => 100,
            'name'  => 'Evolution Notify - Process pending ticket validations',
        ]);

        Toolbox::logInFile('evolution_notify', "[INSTALL] SUCCESS\n", true);
        return true;
    } catch (\Throwable $e) {
        Toolbox::logInFile('evolution_notify', "[INSTALL EXCEPTION] " . $e->getMessage()
            . " in " . $e->getFile() . ":" . $e->getLine()
            . "\nStack: " . $e->getTraceAsString() . "\n", true);
        return false;
    }
}

function plugin_glpievolutionnotify_uninstall(array $params = []): bool
{
    global $DB;

    try {
        foreach (['glpi_plugin_evolutionnotify_configs', 'glpi_plugin_evolutionnotify_notified'] as $table) {
            if ($DB->tableExists($table)) {
                $DB->doQuery("DROP TABLE `$table`");
            }
        }
        return true;
    } catch (\Throwable $e) {
        Toolbox::logInFile('evolution_notify', "[UNINSTALL EXCEPTION] " . $e->getMessage() . "\n", true);
        return false;
    }
}

function plugin_glpievolutionnotify_item_add(CommonITILValidation $item): void
{
    Toolbox::logInFile('evolution_notify', "[HOOK] item_add " . get_class($item) . " #{$item->fields['id']} status={$item->fields['status']}\n", true);

    if ((int)$item->fields['status'] === CommonITILValidation::WAITING) {
        PluginGlpievolutionnotifyNotification::sendValidation($item);
    }
}

function plugin_glpievolutionnotify_item_update(CommonITILValidation $item): void
{
    Toolbox::logInFile('evolution_notify', "[HOOK] item_update " . get_class($item) . " #{$item->fields['id']}\n", true);

    if (!in_array('status', $item->updates, true)) {
        return;
    }

    PluginGlpievolutionnotifyNotification::sendValidation($item);
}

function plugin_glpievolutionnotify_item_add_ticket(CommonDBTM $item): void
{
    if (!$item instanceof Ticket) {
        return;
    }

    Toolbox::logInFile('evolution_notify', "[HOOK] ticket_add #{$item->fields['id']}\n", true);

    $config = PluginGlpievolutionnotifyNotification::getConfig((int)$item->fields['entities_id']);
    if (!$config || !$config['send_on_ticket_created']) {
        return;
    }

    PluginGlpievolutionnotifyNotification::sendTicketCreated($item);
}

function plugin_glpievolutionnotify_item_update_ticket(CommonDBTM $item): void
{
    if (!$item instanceof Ticket || !in_array('status', $item->updates, true)) {
        return;
    }

    Toolbox::logInFile('evolution_notify', "[HOOK] ticket_status_change #{$item->fields['id']} -> {$item->fields['status']}\n", true);

    $config = PluginGlpievolutionnotifyNotification::getConfig((int)$item->fields['entities_id']);
    if (!$config || !$config['send_on_status_changed']) {
        return;
    }

    PluginGlpievolutionnotifyNotification::sendTicketStatusChanged($item);
}

function plugin_glpievolutionnotify_item_add_solution(CommonDBTM $item): void
{
    $ticketId = (int)($item->fields['items_id'] ?? 0);
    if ($ticketId <= 0) {
        return;
    }

    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        return;
    }

    Toolbox::logInFile('evolution_notify', "[HOOK] solution_added for ticket #$ticketId\n", true);

    $config = PluginGlpievolutionnotifyNotification::getConfig((int)$ticket->fields['entities_id']);
    if (!$config || !$config['send_on_solution_added']) {
        return;
    }

    PluginGlpievolutionnotifyNotification::sendSolutionAdded($ticket);
}

function plugin_glpievolutionnotify_redefine_menus(array $menus): array
{
    $menus['admin']['content']['evolutionnotify'] = [
        'title' => 'Evolution Notify',
        'page'  => '/plugins/glpievolutionnotify/front/config.php',
        'icon'  => 'fab fa-whatsapp',
    ];

    $menus['admin']['content']['evolutionnotify_history'] = [
        'title' => 'Evolution Notify - Histórico',
        'page'  => '/plugins/glpievolutionnotify/front/history.php',
        'icon'  => 'fas fa-history',
    ];

    return $menus;
}
