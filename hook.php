<?php

/**
 * GLPI Evolution Notify - Hook callbacks and install/uninstall.
 *
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/glpi-evolution-notify
 * @since     1.0.0
 */

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

define('PLUGIN_GLPINVOLUTIONNOTIFY_DIR', __DIR__);

/**
 * Install plugin: create config and tracking tables.
 *
 * @param array $params
 * @return bool
 */
function plugin_glpievolutionnotify_install(array $params = []): bool
{
    global $DB;

    try {
        $table1 = 'glpi_plugin_evolutionnotify_configs';
        $table2 = 'glpi_plugin_evolutionnotify_notified';

        if (!$DB->tableExists($table1)) {
            $query = "CREATE TABLE `$table1` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `api_url` varchar(255) NOT NULL DEFAULT '',
                `api_token` varchar(255) NOT NULL DEFAULT '',
                `instance` varchar(255) NOT NULL DEFAULT '',
                `send_on_waiting` tinyint(1) NOT NULL DEFAULT 1,
                `send_on_accepted` tinyint(1) NOT NULL DEFAULT 1,
                `send_on_refused` tinyint(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $result = $DB->doQuery($query);
            if (!$result) {
                Toolbox::logInFile('evolution_notify', "[INSTALL ERROR] Failed to create config table: " . $DB->error() . "\n", true);
                return false;
            }

            $DB->insertOrDie($table1, [
                'api_url'   => 'http://localhost:8080',
                'api_token' => '',
                'instance'  => 'default',
            ]);
        }

        if (!$DB->tableExists($table2)) {
            $query = "CREATE TABLE `$table2` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `ticketvalidations_id` int unsigned NOT NULL,
                `event_type` varchar(20) NOT NULL DEFAULT '',
                `phone` varchar(30) NOT NULL DEFAULT '',
                `notified_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `ticketvalidations_id` (`ticketvalidations_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $result = $DB->doQuery($query);
            if (!$result) {
                Toolbox::logInFile('evolution_notify', "[INSTALL ERROR] Failed to create notified table: " . $DB->error() . "\n", true);
                return false;
            }
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

/**
 * Uninstall plugin: drop tables.
 *
 * @param array $params
 * @return bool
 */
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

/**
 * Hook: fired after a TicketValidation is added.
 *
 * @param CommonITILValidation $item
 * @return void
 */
function plugin_glpievolutionnotify_item_add(CommonITILValidation $item): void
{
    Toolbox::logInFile('evolution_notify', "[HOOK] item_add called for " . get_class($item) . " #{$item->fields['id']} status={$item->fields['status']}\n", true);

    $config = PluginGlpievolutionnotifyNotification::getConfig();
    if (!$config) {
        return;
    }

    if ((int)$item->fields['status'] === CommonITILValidation::WAITING
        && (int)($config['send_on_waiting'] ?? 1) === 1
    ) {
        PluginGlpievolutionnotifyNotification::send($item);
    }
}

/**
 * Hook: fired after a TicketValidation is updated.
 *
 * @param CommonITILValidation $item
 * @return void
 */
function plugin_glpievolutionnotify_item_update(CommonITILValidation $item): void
{
    Toolbox::logInFile('evolution_notify', "[HOOK] item_update called for " . get_class($item) . " #{$item->fields['id']}\n", true);

    $config = PluginGlpievolutionnotifyNotification::getConfig();
    if (!$config) {
        return;
    }

    if (!in_array('status', $item->updates, true)) {
        return;
    }

    $newStatus = (int)$item->fields['status'];

    $statusMap = [
        CommonITILValidation::WAITING  => (int)($config['send_on_waiting']  ?? 1),
        CommonITILValidation::ACCEPTED => (int)($config['send_on_accepted'] ?? 1),
        CommonITILValidation::REFUSED  => (int)($config['send_on_refused']  ?? 1),
    ];

    if (isset($statusMap[$newStatus]) && $statusMap[$newStatus] === 1) {
        PluginGlpievolutionnotifyNotification::send($item);
    }
}
