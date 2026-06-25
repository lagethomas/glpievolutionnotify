<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

require_once(GLPI_ROOT . '/inc/includes.php');

global $DB;

Session::checkRight('config', UPDATE);

Html::header(
    'Evolution Notify - Histórico',
    $_SERVER['PHP_SELF'],
    'admin',
    'evolutionnotify_history'
);

$table = 'glpi_plugin_evolutionnotify_notified';
$start  = max(0, (int)($_GET['start'] ?? 0));
$limit  = 50;

?><style>
:root {
    --evo-primary: #25D366;
    --evo-primary-dark: #128C7E;
    --evo-bg: #f0f2f5;
    --evo-card-bg: #ffffff;
    --evo-border: #e0e0e0;
    --evo-text: #1a1a1a;
    --evo-text-secondary: #666;
    --evo-shadow: 0 2px 12px rgba(0,0,0,0.08);
    --evo-radius: 12px;
    --evo-radius-sm: 8px;
}

.evo-container {
    max-width: 1100px;
    margin: 20px auto;
    padding: 0 16px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
    color: var(--evo-text);
}

.evo-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
    padding: 20px 24px;
    background: linear-gradient(135deg, #1a1a2e, #16213e);
    border-radius: var(--evo-radius);
    color: #fff;
    box-shadow: var(--evo-shadow);
}

.evo-header-icon {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.evo-header h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
}

.evo-header p {
    margin: 2px 0 0;
    font-size: 13px;
    opacity: 0.75;
}

.evo-card {
    background: var(--evo-card-bg);
    border-radius: var(--evo-radius);
    box-shadow: var(--evo-shadow);
    margin-bottom: 20px;
    overflow: hidden;
}

.evo-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 24px;
    background: #f8f9fa;
    border-bottom: 1px solid var(--evo-border);
    font-weight: 600;
    font-size: 15px;
    color: var(--evo-text);
}

.evo-card-header i { color: var(--evo-primary-dark); font-size: 18px; }

.evo-card-body { padding: 16px 24px; }

.evo-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.evo-table th {
    text-align: left;
    padding: 10px 12px;
    background: #f8f9fa;
    color: var(--evo-text-secondary);
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--evo-border);
}

.evo-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f0f0f0;
    color: var(--evo-text);
}

.evo-table tr:hover td {
    background: #f8fbfa;
}

.evo-table .evo-empty td {
    text-align: center;
    padding: 40px;
    color: var(--evo-text-secondary);
    font-style: italic;
}

.evo-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.evo-badge-ok {
    background: #d4edda;
    color: #155724;
}

.evo-badge-err {
    background: #f8d7da;
    color: #721c24;
}

.evo-badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

.evo-badge-event {
    background: #e8e8e8;
    color: #555;
    font-family: monospace;
    font-size: 10px;
}

.evo-ticket-link {
    color: var(--evo-primary-dark);
    text-decoration: none;
    font-weight: 600;
}

.evo-ticket-link:hover {
    text-decoration: underline;
}

.evo-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 0;
}

.evo-card-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.evo-card-full {
    grid-column: 1 / -1;
}

@media (max-width: 700px) {
    .evo-card-grid {
        grid-template-columns: 1fr;
    }
}

.evo-pagination a,
.evo-pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    border-radius: 6px;
    font-size: 13px;
    text-decoration: none;
    color: var(--evo-text);
    transition: all 0.2s;
}

.evo-pagination a:hover {
    background: var(--evo-primary-light);
    color: var(--evo-primary-dark);
}

.evo-pagination .active {
    background: var(--evo-primary);
    color: #fff;
    font-weight: 600;
}

.evo-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.evo-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #f8f9fa;
    border-radius: var(--evo-radius-sm);
    font-size: 13px;
}

.evo-stat strong {
    font-size: 18px;
    color: var(--evo-primary-dark);
}

@media (max-width: 768px) {
    .evo-table th:nth-child(2),
    .evo-table td:nth-child(2),
    .evo-table th:nth-child(5),
    .evo-table td:nth-child(5) {
        display: none;
    }
}
</style>

<div class="evo-container">
    <div class="evo-header">
        <div class="evo-header-icon"><i class="fas fa-history"></i></div>
        <div>
            <h1>Histórico de Notificações</h1>
            <p>Registro de todas as mensagens WhatsApp enviadas pelo plugin</p>
        </div>
    </div>

    <?php if ($DB->tableExists($table)):
        $total = $DB->request(['COUNT' => 'c', 'FROM' => $table])->current()['c'];
        $successCount = $DB->request([
            'COUNT' => 'c', 'FROM' => $table,
            'WHERE' => ['http_code' => ['>', 0], 'http_code' => ['<', 300]],
        ])->current()['c'];
        $errorCount = $total - $successCount;
    ?>

    <div class="evo-card-grid">

    <!-- Stats: Total -->
    <div class="evo-card">
        <div class="evo-card-body">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:44px;height:44px;background:#e8f5e9;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--evo-primary-dark);">
                    <i class="fas fa-rocket"></i>
                </div>
                <div>
                    <div style="font-size:24px;font-weight:700;color:var(--evo-text);"><?= $total ?></div>
                    <div style="font-size:12px;color:var(--evo-text-secondary);">Total de Notificações</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats: Sucesso -->
    <div class="evo-card">
        <div class="evo-card-body">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:44px;height:44px;background:#e8f5e9;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#28a745;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div style="font-size:24px;font-weight:700;color:var(--evo-text);"><?= $successCount ?></div>
                    <div style="font-size:12px;color:var(--evo-text-secondary);">Enviadas com Sucesso</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats: Erros -->
    <div class="evo-card">
        <div class="evo-card-body">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:44px;height:44px;background:#fbe9e7;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#dc3545;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <div style="font-size:24px;font-weight:700;color:var(--evo-text);"><?= $errorCount ?></div>
                    <div style="font-size:12px;color:var(--evo-text-secondary);">Com Falha</div>
                </div>
            </div>
        </div>
    </div>

    </div> <!-- /card-grid -->

    <!-- Table (full width) -->
    <div class="evo-card evo-card-full">
        <div class="evo-card-header">
            <i class="fas fa-list"></i> Notificações Enviadas
        </div>
        <div class="evo-card-body" style="padding:0;overflow-x:auto;">
            <table class="evo-table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Chamado</th>
                        <th>Evento</th>
                        <th>Telefone</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total === 0): ?>
                        <tr class="evo-empty"><td colspan="6"><i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;opacity:0.3;"></i>Nenhuma notificação enviada ainda.</td></tr>
                    <?php else:
                        $iterator = $DB->request([
                            'SELECT' => '*',
                            'FROM'   => $table,
                            'ORDER'  => ['notified_at DESC'],
                            'START'  => $start,
                            'LIMIT'  => $limit,
                        ]);
                        foreach ($iterator as $row):
                            $httpCode = (int)($row['http_code'] ?? 0);
                            $httpOk = $httpCode > 0 && $httpCode < 300;
                            $badge = $httpOk ? 'evo-badge-ok' : ($httpCode > 0 ? 'evo-badge-err' : 'evo-badge-info');
                            $httpLabel = $httpOk ? "OK ($httpCode)" : ($httpCode > 0 ? "Falha ($httpCode)" : '—');

                            $ticketLink = '';
                            $tid = (int)($row['tickets_id'] ?? 0);
                            if ($tid > 0) {
                                $ticketLink = "<a class='evo-ticket-link' href='" . GLPI_ROOT . "/front/ticket.form.php?id=$tid' target='_blank'><i class='fas fa-external-link-alt' style='font-size:10px'></i> #$tid</a>";
                            }

                            $eventIcon = match ($row['event_type'] ?? '') {
                                'WAITING' => 'fa-clock',
                                'ACCEPTED' => 'fa-check-circle',
                                'REFUSED' => 'fa-times-circle',
                                'TICKET_CREATED' => 'fa-plus-circle',
                                'STATUS_CHANGED' => 'fa-exchange-alt',
                                'SOLUTION_ADDED' => 'fa-check-double',
                                default => 'fa-bell',
                            };

                            echo "<tr>";
                            echo "<td style='white-space:nowrap;'>" . htmlspecialchars($row['notified_at'] ?? '') . "</td>";
                            echo "<td><span class='evo-badge evo-badge-info'>" . htmlspecialchars($row['itemtype'] ?? '') . "</span></td>";
                            echo "<td>$ticketLink</td>";
                            echo "<td><span class='evo-badge evo-badge-event'><i class='fas $eventIcon'></i> " . htmlspecialchars($row['event_type'] ?? '') . "</span></td>";
                            echo "<td>" . htmlspecialchars($row['phone'] ?? '') . "</td>";
                            echo "<td><span class='evo-badge $badge'>$httpLabel</span></td>";
                            echo "</tr>";
                        endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php
    $totalPages = max(1, (int)ceil($total / $limit));
    if ($totalPages > 1):
    ?>
    <div class="evo-card">
        <div class="evo-card-body">
            <div class="evo-pagination">
                <?php if ($start > 0): ?>
                    <a href="?start=<?= $start - $limit ?>"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($p = 0; $p < $totalPages; $p++):
                    $pageStart = $p * $limit;
                    $active = $start === $pageStart ? 'active' : '';
                ?>
                    <a href="?start=<?= $pageStart ?>" class="<?= $active ?>"><?= $p + 1 ?></a>
                <?php endfor; ?>
                <?php if ($start + $limit < $total): ?>
                    <a href="?start=<?= $start + $limit ?>"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="evo-card">
        <div class="evo-card-body" style="text-align:center;padding:40px;">
            <i class="fas fa-database" style="font-size:48px;color:#ccc;margin-bottom:12px;display:block;"></i>
            Plugin não instalado corretamente. Reinstale o plugin.
        </div>
    </div>
    <?php endif; ?>
</div>
<?php

Html::footer();
