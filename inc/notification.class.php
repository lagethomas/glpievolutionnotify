<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

class PluginGlpievolutionnotifyNotification
{
    private const CONFIG_TABLE  = 'glpi_plugin_evolutionnotify_configs';
    private const NOTIFIED_TABLE = 'glpi_plugin_evolutionnotify_notified';

    // ──────────────────────────────────────────────
    //  CONFIG (entity-aware)
    // ──────────────────────────────────────────────

    public static function getConfig(?int $entitiesId = null): ?array
    {
        global $DB;

        if ($entitiesId === null && isset($_SESSION['glpiactive_entity'])) {
            $entitiesId = (int)$_SESSION['glpiactive_entity'];
        }
        if ($entitiesId === null) {
            $entitiesId = 0;
        }

        $table = self::CONFIG_TABLE;
        if (!$DB->tableExists($table)) {
            return null;
        }

        // Try entity-specific first, then root
        foreach ([$entitiesId, 0] as $eid) {
            $iterator = $DB->request([
                'SELECT' => '*',
                'FROM'   => $table,
                'WHERE'  => ['entities_id' => $eid],
                'LIMIT'  => 1,
            ]);
            if (count($iterator) > 0) {
                $row = (array)$iterator->current();
                if (!empty($row['api_url']) && !empty($row['api_token']) && !empty($row['instance'])) {
                    return self::rowToConfig($row);
                }
            }
        }

        return null;
    }

    private static function rowToConfig(array $row): array
    {
        return [
            'entities_id'           => (int)$row['entities_id'],
            'api_url'               => $row['api_url'],
            'api_token'             => $row['api_token'],
            'instance'              => $row['instance'],
            'send_on_waiting'       => (int)($row['send_on_waiting'] ?? 1),
            'send_on_accepted'      => (int)($row['send_on_accepted'] ?? 1),
            'send_on_refused'       => (int)($row['send_on_refused'] ?? 1),
            'send_on_ticket_created'  => (int)($row['send_on_ticket_created'] ?? 0),
            'send_on_status_changed'  => (int)($row['send_on_status_changed'] ?? 0),
            'send_on_solution_added'  => (int)($row['send_on_solution_added'] ?? 0),
            'template_waiting'        => $row['template_waiting'] ?? null,
            'template_accepted'       => $row['template_accepted'] ?? null,
            'template_refused'        => $row['template_refused'] ?? null,
            'template_ticket_created'  => $row['template_ticket_created'] ?? null,
            'template_status_changed'  => $row['template_status_changed'] ?? null,
            'template_solution_added'  => $row['template_solution_added'] ?? null,
        ];
    }

    // ──────────────────────────────────────────────
    //  PHONE RESOLUTION
    // ──────────────────────────────────────────────

    private static function getUserPhone(int $userId): string
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['mobile', 'phone'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['id' => $userId],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) === 0) {
            return '';
        }

        $row = $iterator->current();
        $mobile = trim((string)($row['mobile'] ?? ''));
        $phone  = trim((string)($row['phone']  ?? ''));

        return $mobile !== '' ? $mobile : $phone;
    }

    public static function getPhonesForUser(int $userId): array
    {
        $phone = self::getUserPhone($userId);
        if ($phone === '') {
            return [];
        }
        return [self::sanitizePhone($phone)];
    }

    public static function getPhonesForGroup(int $groupId): array
    {
        global $DB;
        $phones = [];

        $iterator = $DB->request([
            'SELECT' => ['glpi_users.mobile', 'glpi_users.phone'],
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => ['glpi_groups_users.groups_id' => $groupId],
            'LEFT JOIN' => [
                'glpi_users' => [
                    'ON' => [
                        'glpi_groups_users' => 'users_id',
                        'glpi_users'        => 'id',
                    ],
                ],
            ],
        ]);

        foreach ($iterator as $row) {
            $mobile = trim((string)($row['mobile'] ?? ''));
            $phone  = trim((string)($row['phone']  ?? ''));
            $p = $mobile !== '' ? $mobile : $phone;
            if ($p !== '') {
                $phones[] = self::sanitizePhone($p);
            }
        }

        return array_unique($phones);
    }

    public static function getPhonesForTicketRequester(int $ticketId): array
    {
        global $DB;
        $phones = [];

        $iterator = $DB->request([
            'SELECT' => ['glpi_users.mobile', 'glpi_users.phone'],
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => [
                'glpi_tickets_users.tickets_id' => $ticketId,
                'glpi_tickets_users.type'       => 1,
            ],
            'LEFT JOIN' => [
                'glpi_users' => [
                    'ON' => [
                        'glpi_tickets_users' => 'users_id',
                        'glpi_users'         => 'id',
                    ],
                ],
            ],
        ]);

        foreach ($iterator as $row) {
            $mobile = trim((string)($row['mobile'] ?? ''));
            $phone  = trim((string)($row['phone']  ?? ''));
            $p = $mobile !== '' ? $mobile : $phone;
            if ($p !== '') {
                $phones[] = self::sanitizePhone($p);
            }
        }

        return array_unique($phones);
    }

    public static function sanitizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) <= 10) {
            $digits = '55' . $digits;
        }
        return $digits;
    }

    // ──────────────────────────────────────────────
    //  TEMPLATE SYSTEM
    // ──────────────────────────────────────────────

    private static function getDefaultTemplate(string $eventType): string
    {
        return match ($eventType) {
            'WAITING'         => PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_WAITING,
            'ACCEPTED'        => PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_ACCEPTED,
            'REFUSED'         => PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_REFUSED,
            'TICKET_CREATED'  => PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_TICKET_CREATED,
            'STATUS_CHANGED'  => PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_STATUS_CHANGED,
            'SOLUTION_ADDED'  => PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_SOLUTION_ADDED,
            default           => PLUGIN_EVOLUTION_NOTIFY_TEMPLATE_WAITING,
        };
    }

    private static function getTemplate(string $eventType, array $config): string
    {
        $field = match ($eventType) {
            'WAITING'         => 'template_waiting',
            'ACCEPTED'        => 'template_accepted',
            'REFUSED'         => 'template_refused',
            'TICKET_CREATED'  => 'template_ticket_created',
            'STATUS_CHANGED'  => 'template_status_changed',
            'SOLUTION_ADDED'  => 'template_solution_added',
            default           => null,
        };

        if ($field !== null && !empty($config[$field])) {
            return $config[$field];
        }

        return self::getDefaultTemplate($eventType);
    }

    private static function replacePlaceholders(string $template, array $placeholders): string
    {
        $search  = [];
        $replace = [];

        foreach ($placeholders as $key => $value) {
            $search[]  = '{' . $key . '}';
            $replace[] = $value;
        }

        // Handle {comment_block} specially — empty if comment is blank
        if (empty($placeholders['comment'] ?? '')) {
            $search[]  = '{comment_block}';
            $replace[] = '';
        }

        return str_replace($search, $replace, $template);
    }

    private static function buildPlaceholdersForValidation(
        CommonITILValidation $item,
        string $eventType,
        array $config
    ): array {
        $ticketId   = (int)$item->fields['tickets_id'];
        $status     = (int)$item->fields['status'];
        $cfg = Config::getConfigurationValues('core', ['url_base']);
        $baseUrl = $cfg['url_base'] ?? 'http://localhost/glpi';

        $targetUserId = self::getTargetUserId($item);
        $approverUserId = (int)($item->fields['users_id_validate'] ?? 0);
        $requesterIds   = self::getTicketRequesterIds($ticketId);

        $statusLabel = match ($status) {
            CommonITILValidation::WAITING  => 'Aguardando aprovação',
            CommonITILValidation::ACCEPTED => 'Aprovado',
            CommonITILValidation::REFUSED  => 'Recusado',
            default                        => 'Status desconhecido',
        };

        $commentField = $status === CommonITILValidation::WAITING
            ? 'comment_submission'
            : 'comment_validation';
        $comment = trim(strip_tags((string)($item->fields[$commentField] ?? '')));

        $approverName = self::getUserName($approverUserId > 0 ? $approverUserId : $targetUserId);
        $firstRequester = $requesterIds[0] ?? 0;
        $requesterName = self::getUserName($firstRequester);

        return [
            'ticket_id'    => (string)$ticketId,
            'ticket_title' => self::getTicketTitle($ticketId),
            'status'       => $statusLabel,
            'comment'      => $comment,
            'comment_block'=> "*Comentário:*\n$comment\n\n",
            'requester'    => $requesterName,
            'requester_id' => (string)$firstRequester,
            'approver'     => $approverName,
            'approver_id'  => (string)($approverUserId > 0 ? $approverUserId : $targetUserId),
            'url'          => rtrim($baseUrl, '/') . "/front/ticket.form.php?id=$ticketId",
            'glpi_url'     => rtrim($baseUrl, '/'),
        ];
    }

    private static function buildPlaceholdersForTicket(
        Ticket $ticket,
        string $eventType,
        ?string $comment = null
    ): array {
        $ticketId = (int)$ticket->fields['id'];
        $cfg = Config::getConfigurationValues('core', ['url_base']);
        $baseUrl = $cfg['url_base'] ?? 'http://localhost/glpi';
        $requesterIds = self::getTicketRequesterIds($ticketId);
        $firstRequester = $requesterIds[0] ?? 0;

        $statusLabel = match ((int)$ticket->fields['status']) {
            CommonITILObject::INCOMING      => 'Novo',
            CommonITILObject::ASSIGNED      => 'Atribuído',
            CommonITILObject::PLANNED       => 'Planejado',
            CommonITILObject::WAITING       => 'Aguardando',
            CommonITILObject::SOLVED        => 'Resolvido',
            CommonITILObject::CLOSED        => 'Fechado',
            default                          => 'Desconhecido',
        };

        $comment = trim(strip_tags((string)($comment ?? '')));

        return [
            'ticket_id'    => (string)$ticketId,
            'ticket_title' => self::getTicketTitle($ticketId),
            'status'       => $statusLabel,
            'comment'      => $comment,
            'comment_block'=> $comment !== '' ? "*Comentário:*\n$comment\n\n" : '',
            'requester'    => self::getUserName($firstRequester),
            'requester_id' => (string)$firstRequester,
            'approver'     => '',
            'approver_id'  => '0',
            'url'          => rtrim($baseUrl, '/') . "/front/ticket.form.php?id=$ticketId",
            'glpi_url'     => rtrim($baseUrl, '/'),
        ];
    }

    // ──────────────────────────────────────────────
    //  SENDING
    // ──────────────────────────────────────────────

    private static function sendToTargets(
        array $config,
        array $phones,
        string $message,
        string $itemtype,
        int $itemsId,
        int $ticketId,
        string $eventType
    ): void {
        foreach ($phones as $phone) {
            $httpCode = self::postToEvolutionApi($config, $phone, $message, $itemsId);
            self::markNotified($itemtype, $itemsId, $ticketId, $eventType, $phone, $httpCode);
        }
    }

    // ──────────────────────────────────────────────
    //  VALIDATION SENDER (ACCEPTED/REFUSED → requester + target, WAITING → target)
    // ──────────────────────────────────────────────

    public static function sendValidation(CommonITILValidation $item): void
    {
        try {
            $validationId = (int)$item->fields['id'];
            $status       = (int)$item->fields['status'];

            $eventType = match ($status) {
                CommonITILValidation::WAITING  => 'WAITING',
                CommonITILValidation::ACCEPTED => 'ACCEPTED',
                CommonITILValidation::REFUSED  => 'REFUSED',
                default                        => null,
            };

            if ($eventType === null) {
                return;
            }

            if (self::isNotified('CommonITILValidation', $validationId, $eventType)) {
                Toolbox::logInFile('evolution_notify', "[SEND] Already notified ($eventType), skipping.\n", true);
                return;
            }

            $ticketId = (int)$item->fields['tickets_id'];
            $entitiesId = self::getTicketEntitiesId($ticketId);

            $config = self::getConfig($entitiesId);
            if (!$config) {
                return;
            }

            // Check if this event type is enabled
            $flag = match ($eventType) {
                'WAITING'  => $config['send_on_waiting'],
                'ACCEPTED' => $config['send_on_accepted'],
                'REFUSED'  => $config['send_on_refused'],
            };
            if (!$flag) {
                return;
            }

            $placeholders = self::buildPlaceholdersForValidation($item, $eventType, $config);
            $template     = self::getTemplate($eventType, $config);
            $message      = self::replacePlaceholders($template, $placeholders);

            $allPhones = [];
            $targetUserId = self::getTargetUserId($item);

            // WAITING → notify target user (or group)
            if ($eventType === 'WAITING') {
                $targetType = $item->fields['itemtype_target'] ?? 'User';
                $targetId   = (int)($item->fields['items_id_target'] ?? 0);

                if ($targetType === 'Group' && $targetId > 0) {
                    $allPhones = self::getPhonesForGroup($targetId);
                } elseif ($targetUserId > 0) {
                    $allPhones = self::getPhonesForUser($targetUserId);
                }
            } else {
                // ACCEPTED/REFUSED → notify requester(s)
                $allPhones = self::getPhonesForTicketRequester($ticketId);

                // Also notify the approver/user who validated if configured
                $userId = (int)($item->fields['users_id_validate'] ?? 0);
                if ($userId > 0) {
                    $allPhones = array_merge($allPhones, self::getPhonesForUser($userId));
                }
            }

            $allPhones = array_values(array_unique($allPhones));

            if (empty($allPhones)) {
                Toolbox::logInFile('evolution_notify', "[SEND] No phones found for validation #$validationId.\n", true);
                return;
            }

            self::sendToTargets(
                $config, $allPhones, $message,
                'CommonITILValidation', $validationId, $ticketId, $eventType
            );

        } catch (\Throwable $e) {
            Toolbox::logInFile('evolution_notify', "[SEND EXCEPTION] " . $e->getMessage()
                . " in " . $e->getFile() . ":" . $e->getLine()
                . "\nStack: " . $e->getTraceAsString() . "\n", true);
        }
    }

    // ──────────────────────────────────────────────
    //  TICKET EVENT SENDERS
    // ──────────────────────────────────────────────

    public static function sendTicketCreated(Ticket $ticket): void
    {
        try {
            $ticketId = (int)$ticket->fields['id'];
            $eventType = 'TICKET_CREATED';

            if (self::isNotified('Ticket', $ticketId, $eventType)) {
                return;
            }

            $config = self::getConfig((int)$ticket->fields['entities_id']);
            if (!$config || !$config['send_on_ticket_created']) {
                return;
            }

            $placeholders = self::buildPlaceholdersForTicket($ticket, $eventType);
            $template     = self::getTemplate($eventType, $config);
            $message      = self::replacePlaceholders($template, $placeholders);

            $phones = self::getPhonesForTicketRequester($ticketId);
            if (empty($phones)) {
                return;
            }

            self::sendToTargets($config, $phones, $message, 'Ticket', $ticketId, $ticketId, $eventType);

        } catch (\Throwable $e) {
            Toolbox::logInFile('evolution_notify', "[TICKET CREATED EXCEPTION] " . $e->getMessage() . "\n", true);
        }
    }

    public static function sendTicketStatusChanged(Ticket $ticket): void
    {
        try {
            $ticketId = (int)$ticket->fields['id'];
            $eventType = 'STATUS_CHANGED';

            if (self::isNotified('Ticket', $ticketId, $eventType)) {
                return;
            }

            $config = self::getConfig((int)$ticket->fields['entities_id']);
            if (!$config || !$config['send_on_status_changed']) {
                return;
            }

            $placeholders = self::buildPlaceholdersForTicket($ticket, $eventType);
            $template     = self::getTemplate($eventType, $config);
            $message      = self::replacePlaceholders($template, $placeholders);

            $phones = self::getPhonesForTicketRequester($ticketId);
            if (empty($phones)) {
                return;
            }

            self::sendToTargets($config, $phones, $message, 'Ticket', $ticketId, $ticketId, $eventType);

        } catch (\Throwable $e) {
            Toolbox::logInFile('evolution_notify', "[STATUS CHANGED EXCEPTION] " . $e->getMessage() . "\n", true);
        }
    }

    public static function sendSolutionAdded(Ticket $ticket): void
    {
        try {
            $ticketId = (int)$ticket->fields['id'];
            $eventType = 'SOLUTION_ADDED';

            if (self::isNotified('Ticket', $ticketId, $eventType)) {
                return;
            }

            $config = self::getConfig((int)$ticket->fields['entities_id']);
            if (!$config || !$config['send_on_solution_added']) {
                return;
            }

            // Get the latest solution content
            global $DB;
            $solutionText = '';
            $iter = $DB->request([
                'SELECT' => ['content'],
                'FROM'   => 'glpi_itilsolutions',
                'WHERE'  => ['items_id' => $ticketId, 'itemtype' => 'Ticket'],
                'ORDER'  => ['date_creation DESC'],
                'LIMIT'  => 1,
            ]);
            if (count($iter) > 0) {
                $solutionText = (string)$iter->current()['content'];
            }

            $placeholders = self::buildPlaceholdersForTicket($ticket, $eventType, $solutionText);
            $template     = self::getTemplate($eventType, $config);
            $message      = self::replacePlaceholders($template, $placeholders);

            $phones = self::getPhonesForTicketRequester($ticketId);
            if (empty($phones)) {
                return;
            }

            self::sendToTargets($config, $phones, $message, 'Ticket', $ticketId, $ticketId, $eventType);

        } catch (\Throwable $e) {
            Toolbox::logInFile('evolution_notify', "[SOLUTION ADDED EXCEPTION] " . $e->getMessage() . "\n", true);
        }
    }

    // ──────────────────────────────────────────────
    //  TRACKING
    // ──────────────────────────────────────────────

    private static function isNotified(string $itemtype, int $itemsId, string $eventType): bool
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::NOTIFIED_TABLE,
            'WHERE'  => [
                'itemtype'   => $itemtype,
                'items_id'   => $itemsId,
                'event_type' => $eventType,
            ],
            'LIMIT' => 1,
        ]);

        return count($iterator) > 0;
    }

    private static function markNotified(
        string $itemtype,
        int $itemsId,
        int $ticketId,
        string $eventType,
        string $phone,
        int $httpCode
    ): void {
        global $DB;

        $DB->insertOrDie(self::NOTIFIED_TABLE, [
            'itemtype'    => $itemtype,
            'items_id'    => $itemsId,
            'tickets_id'  => $ticketId,
            'event_type'  => $eventType,
            'phone'       => $phone,
            'http_code'   => $httpCode,
            'notified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ──────────────────────────────────────────────
    //  CRON TASK (fallback for validation events)
    // ──────────────────────────────────────────────

    public static function cronNotify(?CronTask $task = null): int
    {
        Toolbox::logInFile('evolution_notify', "[CRON] cronNotify() START\n", true);

        $config = self::getConfig(0);
        if (!$config) {
            return 0;
        }

        global $DB;

        $statuses = [];
        if ($config['send_on_waiting'])  $statuses[] = CommonITILValidation::WAITING;
        if ($config['send_on_accepted']) $statuses[] = CommonITILValidation::ACCEPTED;
        if ($config['send_on_refused'])  $statuses[] = CommonITILValidation::REFUSED;

        if (empty($statuses)) {
            return 0;
        }

        $iterator = $DB->request([
            'SELECT' => '*',
            'FROM'   => 'glpi_itilvalidations',
            'WHERE'  => ['status' => $statuses],
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
                    default                        => null,
                };

                if ($eventType === null || self::isNotified('CommonITILValidation', $validationId, $eventType)) {
                    continue;
                }

                $ticketId = (int)$row['tickets_id'];
                $configE = self::getConfig(self::getTicketEntitiesId($ticketId)) ?? $config;

                $flag = match ($eventType) {
                    'WAITING'  => $configE['send_on_waiting'],
                    'ACCEPTED' => $configE['send_on_accepted'],
                    'REFUSED'  => $configE['send_on_refused'],
                };
                if (!$flag) {
                    continue;
                }

                $targetUserId = 0;
                $userIdFromField = (int)($row['users_id_validate'] ?? 0);
                $targetType = $row['itemtype_target'] ?? 'User';
                $targetId   = (int)($row['items_id_target'] ?? 0);

                if ($userIdFromField > 0) {
                    $targetUserId = $userIdFromField;
                } elseif ($targetType === 'User' && $targetId > 0) {
                    $targetUserId = $targetId;
                }

                $allPhones = [];

                if ($eventType === 'WAITING') {
                    if ($targetType === 'Group' && $targetId > 0) {
                        $allPhones = self::getPhonesForGroup($targetId);
                    } elseif ($targetUserId > 0) {
                        $allPhones = self::getPhonesForUser($targetUserId);
                    }
                } else {
                    $allPhones = self::getPhonesForTicketRequester($ticketId);
                    if ($userIdFromField > 0) {
                        $allPhones = array_merge($allPhones, self::getPhonesForUser($userIdFromField));
                    }
                }

                $allPhones = array_values(array_unique($allPhones));
                if (empty($allPhones)) {
                    continue;
                }

                $statusLabel = match ($status) {
                    CommonITILValidation::WAITING  => 'Aguardando aprovação',
                    CommonITILValidation::ACCEPTED => 'Aprovado',
                    CommonITILValidation::REFUSED  => 'Recusado',
                    default                        => 'Status desconhecido',
                };

                $commentField = $status === CommonITILValidation::WAITING
                    ? 'comment_submission'
                    : 'comment_validation';
                $comment = trim(strip_tags((string)($row[$commentField] ?? '')));

                $approverName = self::getUserName($userIdFromField > 0 ? $userIdFromField : $targetUserId);
                $requesterIds = self::getTicketRequesterIds($ticketId);
                $requesterName = self::getUserName($requesterIds[0] ?? 0);

                $cfg = Config::getConfigurationValues('core', ['url_base']);
                $baseUrl = $cfg['url_base'] ?? 'http://localhost/glpi';
                $ticketUrl = rtrim($baseUrl, '/') . "/front/ticket.form.php?id=$ticketId";

                $placeholders = [
                    'ticket_id'    => (string)$ticketId,
                    'ticket_title' => self::getTicketTitle($ticketId),
                    'status'       => $statusLabel,
                    'comment'      => $comment,
                    'comment_block'=> $comment !== '' ? "*Comentário:*\n$comment\n\n" : '',
                    'requester'    => $requesterName,
                    'requester_id' => (string)($requesterIds[0] ?? 0),
                    'approver'     => $approverName,
                    'approver_id'  => (string)($userIdFromField > 0 ? $userIdFromField : $targetUserId),
                    'url'          => $ticketUrl,
                    'glpi_url'     => rtrim($baseUrl, '/'),
                ];

                $template = self::getTemplate($eventType, $configE);
                $message  = self::replacePlaceholders($template, $placeholders);

                foreach ($allPhones as $phone) {
                    $httpCode = self::postToEvolutionApi($configE, $phone, $message, $validationId);
                    self::markNotified('CommonITILValidation', $validationId, $ticketId, $eventType, $phone, $httpCode);
                    $count++;
                    if ($task) {
                        $task->addVolume(1);
                    }
                }
            } catch (\Throwable $e) {
                Toolbox::logInFile('evolution_notify',
                    "[CRON ERROR] Validation #{$row['id']}: " . $e->getMessage() . "\n", true);
            }
        }

        Toolbox::logInFile('evolution_notify', "[CRON] cronNotify() END. Processed: $count\n", true);
        return $count;
    }

    // ──────────────────────────────────────────────
    //  WEBHOOK HANDLER (GLPI 11 native webhooks)
    // ──────────────────────────────────────────────

    public static function handleWebhook(array $payload): void
    {
        $event  = $payload['event'] ?? '';
        $item   = $payload['item'] ?? [];

        if (empty($event) || empty($item)) {
            Toolbox::logInFile('evolution_notify', "[WEBHOOK] Invalid payload.\n", true);
            return;
        }

        $validationId = (int)($item['id'] ?? 0);
        $status       = (int)($item['status'] ?? 0);
        $ticketId     = (int)($item['tickets_id'] ?? 0);

        Toolbox::logInFile('evolution_notify',
            "[WEBHOOK] Event=$event validation=$validationId status=$status ticket=$ticketId\n", true);

        $eventType = match ($status) {
            2 => 'WAITING',
            3 => 'ACCEPTED',
            4 => 'REFUSED',
            default => null,
        };

        if ($eventType === null) {
            return;
        }

        if (self::isNotified('CommonITILValidation', $validationId, $eventType)) {
            return;
        }

        $entitiesId = self::getTicketEntitiesId($ticketId);
        $config = self::getConfig($entitiesId);
        if (!$config) {
            return;
        }

        $flag = match ($eventType) {
            'WAITING'  => $config['send_on_waiting'],
            'ACCEPTED' => $config['send_on_accepted'],
            'REFUSED'  => $config['send_on_refused'],
        };
        if (!$flag) {
            return;
        }

        $targetUserId = (int)($item['requested_approver_id'] ?? 0);
        $approverUserId = (int)(($item['approver']['id'] ?? 0));

        $statusLabel = match ($status) {
            2 => 'Aguardando aprovação',
            3 => 'Aprovado',
            4 => 'Recusado',
            default => 'Status desconhecido',
        };

        $comment = '';
        if ($eventType === 'WAITING') {
            $comment = trim(strip_tags((string)($item['submission_comment'] ?? '')));
        } else {
            $comment = trim(strip_tags((string)($item['approval_comment'] ?? '')));
        }

        $approverName = $item['approver']['name'] ?? self::getUserName($approverUserId > 0 ? $approverUserId : $targetUserId);
        $requesterName = $item['requester']['name'] ?? '';

        $cfg = Config::getConfigurationValues('core', ['url_base']);
        $baseUrl = $cfg['url_base'] ?? 'http://localhost/glpi';
        $ticketUrl = rtrim($baseUrl, '/') . "/front/ticket.form.php?id=$ticketId";

        $placeholders = [
            'ticket_id'    => (string)$ticketId,
            'ticket_title' => self::getTicketTitle($ticketId),
            'status'       => $statusLabel,
            'comment'      => $comment,
            'comment_block'=> $comment !== '' ? "*Comentário:*\n$comment\n\n" : '',
            'requester'    => $requesterName,
            'requester_id' => (string)((int)($item['requester']['id'] ?? 0)),
            'approver'     => $approverName,
            'approver_id'  => (string)$approverUserId,
            'url'          => $ticketUrl,
            'glpi_url'     => rtrim($baseUrl, '/'),
        ];

        $template = self::getTemplate($eventType, $config);
        $message  = self::replacePlaceholders($template, $placeholders);

        $allPhones = [];
        if ($eventType === 'WAITING') {
            $phone = self::getUserPhone($targetUserId);
            if ($phone !== '') {
                $allPhones[] = self::sanitizePhone($phone);
            }
        } else {
            $allPhones = self::getPhonesForTicketRequester($ticketId);
            if ($approverUserId > 0) {
                $allPhones = array_merge($allPhones, self::getPhonesForUser($approverUserId));
            }
        }

        $allPhones = array_values(array_unique($allPhones));
        if (empty($allPhones)) {
            return;
        }

        foreach ($allPhones as $phone) {
            $httpCode = self::postToEvolutionApi($config, $phone, $message, $validationId);
            self::markNotified('CommonITILValidation', $validationId, $ticketId, $eventType, $phone, $httpCode);
        }
    }

    // ──────────────────────────────────────────────
    //  EVOLUTION API
    // ──────────────────────────────────────────────

    private static function postToEvolutionApi(
        array $config,
        string $phone,
        string $message,
        int $referenceId
    ): int {
        $url = rtrim($config['api_url'], '/')
            . '/message/sendText/'
            . rawurlencode($config['instance']);

        $payload = [
            'number' => $phone,
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

        $logCtx = "Ref #$referenceId -> $phone";

        if ($errno !== 0) {
            Toolbox::logInFile('evolution_notify', "[API ERROR] cURL ($logCtx): [$errno] $error\n", true);
            return $errno;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            Toolbox::logInFile('evolution_notify', "[API OK] Sent ($logCtx). HTTP $httpCode\n", true);
        } else {
            Toolbox::logInFile('evolution_notify', "[API ERROR] HTTP $httpCode ($logCtx).\nResponse: $response\n", true);
        }

        return $httpCode;
    }

    // ──────────────────────────────────────────────
    //  HELPERS
    // ──────────────────────────────────────────────

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

        $realname  = trim((string)($row['realname'] ?? ''));
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

    private static function getTicketRequesterIds(int $ticketId): array
    {
        global $DB;

        $ids  = [];
        $iter = $DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => [
                'tickets_id' => $ticketId,
                'type'       => 1,
            ],
        ]);

        foreach ($iter as $row) {
            $ids[] = (int)$row['users_id'];
        }

        return $ids;
    }

    private static function getTicketEntitiesId(int $ticketId): int
    {
        global $DB;

        $iter = $DB->request([
            'SELECT' => ['entities_id'],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => ['id' => $ticketId],
            'LIMIT'  => 1,
        ]);

        if (count($iter) > 0) {
            return (int)$iter->current()['entities_id'];
        }

        return 0;
    }
}
