<?php

/**
 * GLPI Evolution Notify - WhatsApp notification via Evolution API.
 *
 * @license   GPLv2+ https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/glpi-evolution-notify
 * @since     1.0.0
 */

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

/**
 * Handles reading plugin config and sending WhatsApp messages via Evolution API.
 */
class PluginGlpievolutionnotifyNotification
{
    private const CONFIG_TABLE = 'glpi_plugin_evolutionnotify_configs';

    /**
     * Retrieve plugin configuration from database.
     *
     * @return array{api_url: string, api_token: string, instance: string,
     *               send_on_waiting: int, send_on_accepted: int, send_on_refused: int}|null
     */
    public static function getConfig(): ?array
    {
        global $DB;

        $table = self::CONFIG_TABLE;

        if (!$DB->tableExists($table)) {
            Toolbox::logInFile('evolution_notify', "[CONFIG] Config table '$table' does not exist.\n", true);
            return null;
        }

        $iterator = $DB->request([
            'SELECT' => '*',
            'FROM'   => $table,
            'LIMIT'  => 1,
        ]);

        if (count($iterator) === 0) {
            Toolbox::logInFile('evolution_notify', "[CONFIG] No configuration row found in '$table'.\n", true);
            return null;
        }

        $row = $iterator->current();

        if (empty($row['api_url']) || empty($row['api_token']) || empty($row['instance'])) {
            Toolbox::logInFile('evolution_notify', "[CONFIG] Incomplete config: api_url, api_token or instance is empty.\n", true);
            return null;
        }

        return [
            'api_url'           => $row['api_url'],
            'api_token'         => $row['api_token'],
            'instance'          => $row['instance'],
            'send_on_waiting'   => (int)$row['send_on_waiting'],
            'send_on_accepted'  => (int)$row['send_on_accepted'],
            'send_on_refused'   => (int)$row['send_on_refused'],
        ];
    }

    /**
     * Send WhatsApp notification for a TicketValidation event.
     *
     * @param CommonITILValidation $item
     * @return void
     */
    public static function send(CommonITILValidation $item): void
    {
        try {
            $validationId = (int)$item->fields['id'];
            $status       = (int)($item->fields['status'] ?? 0);

            Toolbox::logInFile('evolution_notify', "[SEND] send() called for TicketValidation #$validationId status=$status\n", true);

            // Debug: dump all fields
            Toolbox::logInFile('evolution_notify', "[SEND FIELDS] " . json_encode($item->fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", true);

            $eventType = match ($status) {
                CommonITILValidation::WAITING  => 'WAITING',
                CommonITILValidation::ACCEPTED => 'ACCEPTED',
                CommonITILValidation::REFUSED  => 'REFUSED',
                default                        => null,
            };

            if ($eventType === null) {
                Toolbox::logInFile('evolution_notify', "[SEND] Unknown status $status, skipping.\n", true);
                return;
            }

            if (self::isNotified($validationId, $eventType)) {
                Toolbox::logInFile('evolution_notify', "[SEND] Already notified ($eventType), skipping.\n", true);
                return;
            }

            $config = self::getConfig();
            if (!$config) {
                Toolbox::logInFile('evolution_notify', "[SEND] No config available, aborting.\n", true);
                return;
            }

            $userId = self::getTargetUserId($item);
            if ($userId <= 0) {
                Toolbox::logInFile('evolution_notify', "[SEND] No target user for TicketValidation #$validationId.\n", true);
                return;
            }

            Toolbox::logInFile('evolution_notify', "[SEND] Target user ID: $userId\n", true);

            $phone = self::getValidatorPhone($userId);
            if (empty($phone)) {
                Toolbox::logInFile('evolution_notify', "[SEND] No phone for user #$userId.\n", true);
                return;
            }

            Toolbox::logInFile('evolution_notify', "[SEND] Phone: $phone\n", true);
            $phone = self::sanitizePhone($phone);

            $message = self::buildMessage($item);
            self::postToEvolutionApi($config, $phone, $message, $validationId);
            self::markNotified($validationId, $eventType, $phone);

        } catch (\Throwable $e) {
            Toolbox::logInFile('evolution_notify', "[SEND EXCEPTION] " . $e->getMessage()
                . " in " . $e->getFile() . ":" . $e->getLine()
                . "\nStack: " . $e->getTraceAsString() . "\n", true);
        }
    }

    /**
     * Fetch the validator's phone number (mobile preferred, fallback to phone).
     *
     * @param int $userId
     * @return string
     */
    private static function getValidatorPhone(int $userId): string
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['mobile', 'phone'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['id' => $userId],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) === 0) {
            Toolbox::logInFile('evolution_notify', "[PHONE] User #$userId not found in glpi_users.\n", true);
            return '';
        }

        $row = $iterator->current();

        $mobile = trim((string)($row['mobile'] ?? ''));
        $phone  = trim((string)($row['phone']  ?? ''));

        Toolbox::logInFile('evolution_notify', "[PHONE] User #$userId -> mobile='$mobile' phone='$phone'\n", true);

        return $mobile !== '' ? $mobile : $phone;
    }

    /**
     * Strip non-numeric characters and ensure country-code prefix.
     *
     * @param string $phone
     * @return string
     */
    public static function sanitizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) <= 10) {
            $digits = '55' . $digits;
        }

        return $digits;
    }

    /**
     * Build the notification message text.
     *
     * @param TicketValidation $item
     * @return string
     */
    private static function buildMessage(CommonITILValidation $item): string
    {
        $ticketId   = (int)($item->fields['tickets_id'] ?? 0);
        $status     = (int)($item->fields['status'] ?? 0);
        $ticketTitle = self::getTicketTitle($ticketId);
        $cfg = Config::getConfigurationValues('core', ['url_base']);
        $baseUrl = $cfg['url_base'] ?? 'http://localhost/glpi';
        $ticketUrl  = rtrim($baseUrl, '/') . "/front/ticket.form.php?id=$ticketId";

        $statusLabels = [
            CommonITILValidation::WAITING  => 'Aguardando aprovação',
            CommonITILValidation::ACCEPTED => 'Aprovado',
            CommonITILValidation::REFUSED  => 'Recusado',
        ];

        $statusLabel = $statusLabels[$status] ?? 'Status desconhecido';

        $approverUserId = (int)($item->fields['users_id_validate'] ?? 0);
        $validatorName  = self::getUserName($approverUserId > 0 ? $approverUserId : self::getTargetUserId($item));

        $msg = "*GLPI - Notificação de Validação*\n\n"
            . "Olá *$validatorName*,\n\n"
            . "O chamado *#$ticketId - $ticketTitle*\n"
            . "Está com status: *$statusLabel*\n\n";

        $commentField = $status === CommonITILValidation::WAITING
            ? 'comment_submission'
            : 'comment_validation';
        $comment = trim((string)($item->fields[$commentField] ?? ''));
        if ($comment !== '') {
            $msg .= "*Comentário:*\n" . strip_tags($comment) . "\n\n";
        }

        $msg .= "Acesse: $ticketUrl";

        return $msg;
    }

    /**
     * Get ticket title from database.
     *
     * @param int $ticketId
     * @return string
     */
    private static function getTicketTitle(int $ticketId): string
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['name'],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => ['id' => $ticketId],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) === 0) {
            return '(sem título)';
        }

        return trim((string)$iterator->current()['name']);
    }

    /**
     * Get user's real name or login.
     *
     * @param int $userId
     * @return string
     */
    private static function getUserName(int $userId): string
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['name', 'realname', 'firstname'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['id' => $userId],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) === 0) {
            return 'Usuário';
        }

        $row = $iterator->current();

        $realname = trim((string)($row['realname'] ?? ''));
        $firstname = trim((string)($row['firstname'] ?? ''));

        if ($realname !== '' && $firstname !== '') {
            return "$firstname $realname";
        }
        if ($realname !== '') {
            return $realname;
        }
        if ($firstname !== '') {
            return $firstname;
        }

        return (string)($row['name'] ?? 'Usuário');
    }

    /**
     * Get the actual target user ID from a validation item.
     * Handles GLPI 11's itemtype_target/items_id_target system.
     *
     * @param CommonITILValidation $item
     * @return int
     */
    private static function getTargetUserId(CommonITILValidation $item): int
    {
        $userId = (int)($item->fields['users_id_validate'] ?? 0);

        if ($userId > 0) {
            return $userId;
        }

        $targetType = $item->fields['itemtype_target'] ?? '';
        $targetId   = (int)($item->fields['items_id_target'] ?? 0);

        if ($targetType === 'User' && $targetId > 0) {
            return $targetId;
        }

        return 0;
    }

    /**
     * Check if a validation has already been notified for a given event type.
     *
     * @param int    $validationId
     * @param string $eventType
     * @return bool
     */
    private static function isNotified(int $validationId, string $eventType): bool
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_evolutionnotify_notified',
            'WHERE'  => [
                'ticketvalidations_id' => $validationId,
                'event_type'           => $eventType,
            ],
            'LIMIT' => 1,
        ]);

        return count($iterator) > 0;
    }

    /**
     * Mark a validation as notified.
     *
     * @param int    $validationId
     * @param string $eventType
     * @param string $phone
     * @return void
     */
    private static function markNotified(int $validationId, string $eventType, string $phone): void
    {
        global $DB;

        $DB->insertOrDie(
            'glpi_plugin_evolutionnotify_notified',
            [
                'ticketvalidations_id' => $validationId,
                'event_type'           => $eventType,
                'phone'                => $phone,
                'notified_at'          => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Cron task: process pending ticket validations and send notifications.
     * Called by GLPI cron every minute.
     *
     * @param CronTask|null $task
     * @return int
     */
    public static function cronNotify(?CronTask $task = null): int
    {
        Toolbox::logInFile('evolution_notify', "[CRON] cronNotify() START\n", true);

        $config = self::getConfig();
        if (!$config) {
            Toolbox::logInFile('evolution_notify', "[CRON] No config, aborting.\n", true);
            return 0;
        }

        global $DB;

        $statuses = [];
        if ($config['send_on_waiting']) {
            $statuses[] = CommonITILValidation::WAITING;
        }
        if ($config['send_on_accepted']) {
            $statuses[] = CommonITILValidation::ACCEPTED;
        }
        if ($config['send_on_refused']) {
            $statuses[] = CommonITILValidation::REFUSED;
        }

        if (empty($statuses)) {
            return 0;
        }

        $iterator = $DB->request([
            'SELECT' => '*',
            'FROM'   => 'glpi_itilvalidations',
            'WHERE'  => [
                'status' => $statuses,
            ],
        ]);

        $count = 0;

        foreach ($iterator as $row) {
            try {
                $validationId = (int)$row['id'];
                $status       = (int)$row['status'];

                $eventType = match ($status) {
                    CommonITILValidation::WAITING  => 'WAITING',
                    CommonITILValidation::ACCEPTED => 'ACCEPTED',
                    CommonITILValidation::REFUSED  => 'REFUSED',
                    default                        => 'UNKNOWN',
                };

                if (self::isNotified($validationId, $eventType)) {
                    continue;
                }

                $userId = 0;
                $userIdFromField = (int)($row['users_id_validate'] ?? 0);

                if ($userIdFromField > 0) {
                    $userId = $userIdFromField;
                } else {
                    $targetType = $row['itemtype_target'] ?? '';
                    $targetId   = (int)($row['items_id_target'] ?? 0);
                    if ($targetType === 'User' && $targetId > 0) {
                        $userId = $targetId;
                    }
                }

                if ($userId <= 0) {
                    Toolbox::logInFile('evolution_notify',
                        "[CRON] Validation #$validationId: no target user found (itemtype_target=$targetType, items_id_target=$targetId)\n", true);
                    continue;
                }

                $phone = self::getValidatorPhone($userId);
                if (empty($phone)) {
                    Toolbox::logInFile('evolution_notify',
                        "[CRON] Validation #$validationId: no phone for user #$userId.\n", true);
                    continue;
                }

                $phone = self::sanitizePhone($phone);

                $ticketId   = (int)$row['tickets_id'];
                $ticketTitle = self::getTicketTitle($ticketId);
                $userName   = self::getUserName($userId);
                $statusLabel = match ($status) {
                    CommonITILValidation::WAITING  => 'Aguardando aprovação',
                    CommonITILValidation::ACCEPTED => 'Aprovado',
                    CommonITILValidation::REFUSED  => 'Recusado',
                    default                        => 'Status desconhecido',
                };
                $cfg = Config::getConfigurationValues('core', ['url_base']);
                $baseUrl = $cfg['url_base'] ?? 'http://localhost/glpi';
                $ticketUrl = rtrim($baseUrl, '/') . "/front/ticket.form.php?id=$ticketId";

                $message = "*GLPI - Notificação de Validação*\n\n"
                    . "Olá *$userName*,\n\n"
                    . "O chamado *#$ticketId - $ticketTitle*\n"
                    . "Está com status: *$statusLabel*\n\n";

                $commentField = $status === CommonITILValidation::WAITING
                    ? 'comment_submission'
                    : 'comment_validation';
                $comment = trim((string)($row[$commentField] ?? ''));
                if ($comment !== '') {
                    $message .= "*Comentário:*\n" . strip_tags($comment) . "\n\n";
                }

                $message .= "Acesse: $ticketUrl";

                self::postToEvolutionApi($config, $phone, $message, $validationId);
                self::markNotified($validationId, $eventType, $phone);

                $count++;

                if ($task) {
                    $task->addVolume(1);
                }
            } catch (\Throwable $e) {
                Toolbox::logInFile('evolution_notify',
                    "[CRON ERROR] Validation #{$row['id']}: " . $e->getMessage() . "\n", true);
            }
        }

        Toolbox::logInFile('evolution_notify', "[CRON] cronNotify() END. Processed: $count\n", true);
        return $count;
    }

    /**
     * POST message payload to Evolution API via cURL.
     *
     * @param array{api_url: string, api_token: string, instance: string} $config
     * @param string $phone
     * @param string $message
     * @param int    $ticketValidationId
     * @return void
     */
    private static function postToEvolutionApi(
        array $config,
        string $phone,
        string $message,
        int $ticketValidationId
    ): void {
        $url = rtrim($config['api_url'], '/')
            . '/message/sendText/'
            . rawurlencode($config['instance']);

        $payload = [
            'number' => $phone,
            'text'   => $message,
        ];

        Toolbox::logInFile('evolution_notify', "[API] POST $url\n", true);

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

        $logContext = "TicketValidation #$ticketValidationId -> $phone";

        if ($errno !== 0) {
            Toolbox::logInFile('evolution_notify', "[API ERROR] cURL failure ($logContext): [$errno] $error\n", true);
            return;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            Toolbox::logInFile('evolution_notify', "[API OK] Sent ($logContext). HTTP $httpCode\n", true);
        } else {
            Toolbox::logInFile('evolution_notify', "[API ERROR] HTTP $httpCode ($logContext).\nResponse: $response\n", true);
        }
    }
}
