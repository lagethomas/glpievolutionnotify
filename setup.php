<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

define('PLUGIN_GLPINVOLUTIONNOTIFY_VERSION', '2.0.0');

function plugin_init_glpievolutionnotify(): void
{
    Toolbox::logInFile('evolution_notify', "[SETUP] plugin_init_glpievolutionnotify() called\n", true);
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpievolutionnotify'] = true;

    // --- Cron ---
    $PLUGIN_HOOKS['cron']['glpievolutionnotify'] = [
        PluginGlpievolutionnotifyNotification::class,
    ];

    // --- Validation hooks ---
    $hookItemAdd    = class_exists(\Glpi\Plugin\Hooks::class) ? \Glpi\Plugin\Hooks::ITEM_ADD : 'item_add';
    $hookItemUpdate = class_exists(\Glpi\Plugin\Hooks::class) ? \Glpi\Plugin\Hooks::ITEM_UPDATE : 'item_update';

    $validationClasses = [
        \TicketValidation::class,
        \CommonITILValidation::class,
    ];

    foreach ($validationClasses as $class) {
        if (class_exists($class)) {
            $PLUGIN_HOOKS[$hookItemAdd]['glpievolutionnotify'][$class]
                = 'plugin_glpievolutionnotify_item_add';
            $PLUGIN_HOOKS[$hookItemUpdate]['glpievolutionnotify'][$class]
                = 'plugin_glpievolutionnotify_item_update';
        }
    }

    // --- Ticket hooks ---
    if (class_exists(\Ticket::class)) {
        $PLUGIN_HOOKS[$hookItemAdd]['glpievolutionnotify'][\Ticket::class]
            = 'plugin_glpievolutionnotify_item_add_ticket';
        $PLUGIN_HOOKS[$hookItemUpdate]['glpievolutionnotify'][\Ticket::class]
            = 'plugin_glpievolutionnotify_item_update_ticket';
    }

    // --- Solution hooks (ITILSolution) ---
    if (class_exists(\ITILSolution::class)) {
        $PLUGIN_HOOKS[$hookItemAdd]['glpievolutionnotify'][\ITILSolution::class]
            = 'plugin_glpievolutionnotify_item_add_solution';
    }

    // --- Menu ---
    if (class_exists(\Session::class) && \Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['glpievolutionnotify'] = 'front/config.php';
        $PLUGIN_HOOKS['redefine_menus']['glpievolutionnotify'] = 'plugin_glpievolutionnotify_redefine_menus';
    }

    Toolbox::logInFile('evolution_notify', "[SETUP] plugin_init finished.\n", true);
}

function plugin_version_glpievolutionnotify(): array
{
    return [
        'name'           => 'Evolution Notify',
        'version'        => PLUGIN_GLPINVOLUTIONNOTIFY_VERSION,
        'author'         => 'Thomas Marcelino',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://github.com/glpi-evolution-notify',
        'minGlpiVersion' => '10.0.0',
        'maxGlpiVersion' => '11.0.99',
    ];
}

function plugin_glpievolutionnotify_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, '10.0.0', 'lt')) {
        echo 'This plugin requires GLPI >= 10.0.0';
        return false;
    }
    return true;
}

function plugin_glpievolutionnotify_check_config(): bool
{
    return true;
}
