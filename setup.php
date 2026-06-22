<?php

/**
 * GLPI Evolution Notify - WhatsApp Notification via Evolution API
 *
 * Monitors Ticket Validation events and sends WhatsApp notifications.
 *
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/glpi-evolution-notify
 * @since     1.0.0
 */

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

define('PLUGIN_GLPINVOLUTIONNOTIFY_VERSION', '1.0.0');

/**
 * Plugin initialization: register hooks, cron and configuration.
 */
function plugin_init_glpievolutionnotify(): void
{
    Toolbox::logInFile('evolution_notify', "[SETUP] plugin_init_glpievolutionnotify() called\n", true);
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpievolutionnotify'] = true;

    $PLUGIN_HOOKS['cron']['glpievolutionnotify'] = [
        PluginGlpievolutionnotifyNotification::class,
    ];

    $hookItemAdd    = class_exists(\Glpi\Plugin\Hooks::class) ? \Glpi\Plugin\Hooks::ITEM_ADD : 'item_add';
    $hookItemUpdate = class_exists(\Glpi\Plugin\Hooks::class) ? \Glpi\Plugin\Hooks::ITEM_UPDATE : 'item_update';

    $targetClasses = [
        \TicketValidation::class,
        \CommonITILValidation::class,
    ];

    foreach ($targetClasses as $class) {
        if (class_exists($class)) {
            $PLUGIN_HOOKS[$hookItemAdd]['glpievolutionnotify'][$class]
                = 'plugin_glpievolutionnotify_item_add';
            $PLUGIN_HOOKS[$hookItemUpdate]['glpievolutionnotify'][$class]
                = 'plugin_glpievolutionnotify_item_update';
        } else {
            Toolbox::logInFile('evolution_notify',
                "[SETUP] Class $class not found, skipping hook.\n", true);
        }
    }

    if (class_exists(\Session::class) && \Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['glpievolutionnotify'] = 'front/config.php';
    }

    Toolbox::logInFile('evolution_notify', "[SETUP] plugin_init finished.\n", true);
}

/**
 * Plugin version metadata.
 *
 * @return array<string, mixed>
 */
function plugin_version_glpievolutionnotify(): array
{
    return [
        'name'           => 'Evolution Notify',
        'version'        => PLUGIN_GLPINVOLUTIONNOTIFY_VERSION,
        'author'         => 'GLPI Evolution Team',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://github.com/glpi-evolution-notify',
        'minGlpiVersion' => '10.0.0',
        'maxGlpiVersion' => '11.0.99',
    ];
}

/**
 * Prerequisites check.
 *
 * @return bool
 */
function plugin_glpievolutionnotify_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, '10.0.0', 'lt')) {
        echo 'This plugin requires GLPI >= 10.0.0';
        return false;
    }
    return true;
}

/**
 * Config check.
 *
 * @return bool
 */
function plugin_glpievolutionnotify_check_config(): bool
{
    return true;
}
