# Evolution Notify

GLPI plugin that sends real-time WhatsApp notifications for ticket validations via [Evolution API](https://github.com/EvolutionAPI/evolution-api).

## Features

- Sends WhatsApp messages when a ticket validation is **requested**, **approved**, or **refused**
- Evolution API integration (self-hosted or cloud)
- Configurable per event type (waiting / accepted / refused)
- Test send button on config page
- Multiple-approval flow support (GLPI 11)
- Deduplication tracking to prevent duplicate notifications
- Cron fallback for missed events

## Requirements

- GLPI **10.0.x** or **11.0.x**
- PHP **8.1+**
- An Evolution API instance (see [Evolution API docs](https://doc.evolution-api.com/v2))

## Installation

1. Download the plugin and extract to the GLPI `marketplace/` directory:

```bash
unzip glpievolutionnotify.zip -d /var/glpi/marketplace/
```

2. Rename directory if needed (must be `glpievolutionnotify`):

```bash
mv /var/glpi/marketplace/glpievolutionnotify-main /var/glpi/marketplace/glpievolutionnotify
```

3. Go to **Configuration → Plugins**, find *Evolution Notify* and click **Install** then **Enable**.

## Configuration

1. Go to **Configuration → Evolution Notify** in the GLPI menu.
2. Fill in the Evolution API credentials:
   - **API URL** — your Evolution API base URL (e.g. `https://evo.example.com`)
   - **API Token** — your Evolution API authentication token
   - **Instance** — the Evolution API instance name
3. Select which events trigger notifications:
   - Send when **Waiting** approval
   - Send when **Accepted**
   - Send when **Refused**
4. Click **Save Settings**.
5. Use the **Test Send** button to verify WhatsApp delivery.

## How it works

### Hooks (real-time)

When a ticket validation is created or updated, the plugin hooks into `item_add` / `item_update` on `TicketValidation` / `CommonITILValidation` and sends the WhatsApp message immediately.

### Cron (fallback)

A cron task (`PluginGlpievolutionnotifyNotification::cronNotify`) runs every minute and processes any validations that were not caught by hooks. The tracking table `glpi_plugin_evolutionnotify_notified` prevents duplicate sends.

### GLPI 11 compatibility

GLPI 11 introduced multi-step validation. The plugin handles:
- `itemtype_target` / `items_id_target` for target user resolution
- `comment_submission` / `comment_validation` fields for submission and approval comments
- `\Glpi\Plugin\Hooks` constants when available

## Logs

Logs are written to `evolution_notify.log` in your GLPI log directory (`GLPI_LOG_DIR`). Useful for debugging delivery issues.

## Files

```
glpievolutionnotify/
├── ajax/
│   ├── save_config.php      # AJAX config save handler
│   └── test_send.php        # AJAX test send handler
├── front/
│   └── config.php           # Configuration page
├── inc/
│   └── notification.class.php  # Core logic (send, cron, dedup)
├── hook.php                 # Install/uninstall + hook callbacks
├── setup.php                # Plugin metadata + hook/cron registration
└── README.md
```

## License

GNU General Public License v2.0 or later.

See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for full text.
