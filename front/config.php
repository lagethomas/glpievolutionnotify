<?php

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

require_once(GLPI_ROOT . '/inc/includes.php');

global $DB;

Session::checkRight('config', UPDATE);

$configTable = 'glpi_plugin_evolutionnotify_configs';
$entitiesId   = (int)($_GET['entities_id'] ?? $_SESSION['glpiactive_entity'] ?? 0);
$row          = [];

if ($DB->tableExists($configTable)) {
    $iterator = $DB->request([
        'SELECT' => '*',
        'FROM'   => $configTable,
        'WHERE'  => ['entities_id' => $entitiesId],
        'LIMIT'  => 1,
    ]);
    if (count($iterator) > 0) {
        $row = (array)$iterator->current();
    } else {
        $iterator = $DB->request([
            'SELECT' => '*',
            'FROM'   => $configTable,
            'WHERE'  => ['entities_id' => 0],
            'LIMIT'  => 1,
        ]);
        if (count($iterator) > 0) {
            $row = (array)$iterator->current();
        }
    }
}

Html::header(
    'Evolution Notify',
    $_SERVER['PHP_SELF'],
    'admin',
    'evolutionnotify'
);

$csrfToken = Session::getNewCSRFToken();

?><style>
:root {
    --evo-primary: #25D366;
    --evo-primary-dark: #128C7E;
    --evo-primary-light: #DCF8C6;
    --evo-bg: #f0f2f5;
    --evo-card-bg: #ffffff;
    --evo-border: #e0e0e0;
    --evo-text: #1a1a1a;
    --evo-text-secondary: #666;
    --evo-shadow: 0 2px 12px rgba(0,0,0,0.08);
    --evo-shadow-hover: 0 4px 20px rgba(0,0,0,0.12);
    --evo-radius: 12px;
    --evo-radius-sm: 8px;
}

.evo-container {
    max-width: 100%;
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
    background: linear-gradient(135deg, var(--evo-primary-dark), var(--evo-primary));
    border-radius: var(--evo-radius);
    color: #fff;
    box-shadow: var(--evo-shadow);
}

.evo-header-icon {
    width: 48px;
    height: 48px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.evo-header h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
}

.evo-header p {
    margin: 2px 0 0;
    font-size: 13px;
    opacity: 0.85;
}

.evo-card {
    background: var(--evo-card-bg);
    border-radius: var(--evo-radius);
    box-shadow: var(--evo-shadow);
    margin-bottom: 20px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}

.evo-card:hover {
    box-shadow: var(--evo-shadow-hover);
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

.evo-card-header i {
    color: var(--evo-primary-dark);
    font-size: 18px;
}

.evo-card-body {
    padding: 20px 24px;
}

.evo-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 18px;
}

.evo-form-group label {
    font-size: 13px;
    font-weight: 500;
    color: var(--evo-text-secondary);
}

.evo-form-group input[type="url"],
.evo-form-group input[type="password"],
.evo-form-group input[type="text"],
.evo-form-group textarea,
.evo-form-group select {
    padding: 10px 14px;
    border: 1.5px solid var(--evo-border);
    border-radius: var(--evo-radius-sm);
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #fff;
    color: var(--evo-text);
    font-family: inherit;
}

.evo-form-group input:focus,
.evo-form-group textarea:focus,
.evo-form-group select:focus {
    outline: none;
    border-color: var(--evo-primary);
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.15);
}

.evo-form-group textarea {
    resize: vertical;
    min-height: 100px;
    font-size: 13px;
    line-height: 1.5;
}

.evo-toggle {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 0;
}

.evo-toggle input[type="checkbox"] {
    display: none;
}

.evo-toggle-slider {
    position: relative;
    width: 44px;
    height: 24px;
    background: #ccc;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.3s;
    flex-shrink: 0;
}

.evo-toggle-slider::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.3s;
}

.evo-toggle input:checked + .evo-toggle-slider {
    background: var(--evo-primary);
}

.evo-toggle input:checked + .evo-toggle-slider::after {
    transform: translateX(20px);
}

.evo-toggle-label {
    font-size: 14px;
    color: var(--evo-text);
    cursor: pointer;
}

.evo-toggle-desc {
    font-size: 12px;
    color: var(--evo-text-secondary);
    margin-left: 58px;
    margin-top: -4px;
    margin-bottom: 4px;
}

.evo-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

@media (max-width: 600px) {
    .evo-grid-2 {
        grid-template-columns: 1fr;
    }
}

.evo-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    border: none;
    border-radius: var(--evo-radius-sm);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}

.evo-btn-primary {
    background: linear-gradient(135deg, var(--evo-primary-dark), var(--evo-primary));
    color: #fff;
}

.evo-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.35);
}

.evo-btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.evo-btn-secondary {
    background: #e8e8e8;
    color: var(--evo-text);
}

.evo-btn-secondary:hover {
    background: #ddd;
}

.evo-btn-success {
    background: var(--evo-primary);
    color: #fff;
}

.evo-btn-success:hover {
    background: #1ebe5d;
}

.evo-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    padding: 14px 20px;
    border-radius: var(--evo-radius-sm);
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    display: none;
    max-width: 400px;
    animation: evo-slide-in 0.3s ease;
}

.evo-toast-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.evo-toast-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

@keyframes evo-slide-in {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.evo-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.evo-badge-waiting {
    background: #fff3cd;
    color: #856404;
}

.evo-badge-success {
    background: #d4edda;
    color: #155724;
}

.evo-badge-danger {
    background: #f8d7da;
    color: #721c24;
}

.evo-placeholder-hint {
    font-size: 12px;
    color: var(--evo-text-secondary);
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: var(--evo-radius-sm);
    margin-bottom: 10px;
    line-height: 1.6;
}

.evo-placeholder-hint code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    color: var(--evo-primary-dark);
}

.evo-separator {
    height: 1px;
    background: var(--evo-border);
    margin: 20px 0;
}

.evo-entity-selector {
    margin-bottom: 20px;
}

.evo-entity-selector label {
    font-weight: 600;
    font-size: 14px;
    display: block;
    margin-bottom: 6px;
    color: var(--evo-text);
}

.evo-entity-selector select {
    width: 100%;
    max-width: 400px;
    padding: 10px 14px;
    border: 1.5px solid var(--evo-border);
    border-radius: var(--evo-radius-sm);
    font-size: 14px;
    background: #fff;
    cursor: pointer;
}

.evo-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.evo-modal-overlay.active {
    display: flex;
}

.evo-modal {
    background: #fff;
    border-radius: var(--evo-radius);
    width: 90%;
    max-width: 440px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: evo-modal-in 0.2s ease;
}

@keyframes evo-modal-in {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.evo-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--evo-border);
}

.evo-modal-header h3 {
    margin: 0;
    font-size: 16px;
}

.evo-modal-close {
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: #999;
    padding: 4px 8px;
    border-radius: 6px;
}

.evo-modal-close:hover {
    background: #f0f0f0;
    color: #333;
}

.evo-modal-body {
    padding: 20px;
}

.evo-modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 12px 20px;
    border-top: 1px solid var(--evo-border);
}

.evo-form-actions {
    display: flex;
    gap: 12px;
    margin-top: 8px;
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

/* Hide GLPI's default big title */
header#header + div:not(.evo-container) { display: none; }

.ph-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: #f8f9fa;
    border: 1px solid var(--evo-border);
    border-radius: var(--evo-radius-sm);
    cursor: pointer;
    transition: all 0.2s;
}

.ph-item:hover {
    background: var(--evo-primary-light);
    border-color: var(--evo-primary);
    transform: translateY(-1px);
}

.ph-item:active {
    transform: scale(0.97);
}

.ph-item.copied {
    background: #d4edda;
    border-color: #28a745;
}

.ph-code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 13px;
    font-weight: 600;
    color: var(--evo-primary-dark);
    background: #fff;
    padding: 3px 8px;
    border-radius: 4px;
    border: 1px solid #ddd;
    white-space: nowrap;
}

.ph-desc {
    font-size: 12px;
    color: var(--evo-text-secondary);
}
</style>

<div class="evo-container">

    <!-- Header -->
    <div class="evo-header">
        <div class="evo-header-icon"><i class="fab fa-whatsapp"></i></div>
        <div>
            <h1>Evolution Notify</h1>
            <p>Notificações WhatsApp para eventos do GLPI</p>
        </div>
    </div>

    <!-- Row 1: Entity + API -->
    <div class="evo-card-grid">

    <!-- Entity Selector -->
    <div class="evo-card evo-entity-selector">
        <div class="evo-card-header">
            <i class="fas fa-building"></i> Entidade
        </div>
        <div class="evo-card-body">
            <form method="get" id="entity-form">
                <label for="evo-entity-select">Selecione a entidade para configurar:</label>
                <select name="entities_id" id="evo-entity-select" onchange="this.form.submit()">
                    <option value="0">Entidade Raiz</option>
                    <?php
                    $eIter = $DB->request(['SELECT' => ['id', 'completename'], 'FROM' => 'glpi_entities', 'ORDER' => 'completename']);
                    foreach ($eIter as $e) {
                        $sel = (int)$e['id'] === $entitiesId ? 'selected' : '';
                        echo "<option value='{$e['id']}' $sel>" . htmlspecialchars($e['completename']) . "</option>";
                    }
                    ?>
                </select>
            </form>
        </div>
    </div>

    <!-- API Configuration -->
    <div class="evo-card">
        <div class="evo-card-header">
            <i class="fas fa-plug"></i> Configuração da Evolution API
        </div>
        <div class="evo-card-body">
            <form id="form-config-evolution-notify">
                <input type="hidden" name="entities_id" value="<?= $entitiesId ?>">

                <div class="evo-form-group">
                    <label for="cfg_api_url">URL da API</label>
                    <input type="url" name="api_url" id="cfg_api_url"
                           value="<?= htmlspecialchars($row['api_url'] ?? '') ?>"
                           placeholder="https://evoapi.exemplo.com" required>
                </div>

                <div class="evo-form-group">
                    <label for="cfg_api_token">Token de Autenticação</label>
                    <input type="password" name="api_token" id="cfg_api_token"
                           value="<?= htmlspecialchars($row['api_token'] ?? '') ?>"
                           placeholder="Insira o token da Evolution API" required>
                </div>

                <div class="evo-form-group">
                    <label for="cfg_instance">Instância</label>
                    <input type="text" name="instance" id="cfg_instance"
                           value="<?= htmlspecialchars($row['instance'] ?? '') ?>"
                           placeholder="Nome da instância no Evolution API" required>
                </div>
            </form>
        </div>
    </div>

    </div> <!-- /card-grid -->

    <!-- Row 2: Validation + Other Events -->
    <div class="evo-card-grid">

    <!-- Validation Events -->
    <div class="evo-card">
        <div class="evo-card-header">
            <i class="fas fa-check-circle"></i> Eventos de Validação
        </div>
        <div class="evo-card-body">
            <p style="margin:0 0 8px;font-size:13px;color:var(--evo-text-secondary);">
                Selecione quais eventos de validação de chamados devem disparar notificações:
            </p>

            <div class="evo-toggle">
                <input type="checkbox" name="send_on_waiting" id="cfg_send_on_waiting" value="1" <?= !empty($row['send_on_waiting']) ? 'checked' : '' ?>>
                <label class="evo-toggle-slider" for="cfg_send_on_waiting"></label>
                <label class="evo-toggle-label" for="cfg_send_on_waiting">Validação solicitada (aguardando aprovação)</label>
            </div>

            <div class="evo-toggle">
                <input type="checkbox" name="send_on_accepted" id="cfg_send_on_accepted" value="1" <?= !empty($row['send_on_accepted']) ? 'checked' : '' ?>>
                <label class="evo-toggle-slider" for="cfg_send_on_accepted"></label>
                <label class="evo-toggle-label" for="cfg_send_on_accepted">Validação aprovada</label>
            </div>

            <div class="evo-toggle">
                <input type="checkbox" name="send_on_refused" id="cfg_send_on_refused" value="1" <?= !empty($row['send_on_refused']) ? 'checked' : '' ?>>
                <label class="evo-toggle-slider" for="cfg_send_on_refused"></label>
                <label class="evo-toggle-label" for="cfg_send_on_refused">Validação recusada</label>
            </div>
        </div>
    </div>

    <!-- Other Events -->
    <div class="evo-card">
        <div class="evo-card-header">
            <i class="fas fa-bell"></i> Outros Eventos do Chamado
        </div>
        <div class="evo-card-body">
            <p style="margin:0 0 8px;font-size:13px;color:var(--evo-text-secondary);">
                Ative notificações para estes eventos adicionais:
            </p>

            <div class="evo-toggle">
                <input type="checkbox" name="send_on_ticket_created" id="cfg_send_on_ticket_created" value="1" <?= !empty($row['send_on_ticket_created']) ? 'checked' : '' ?>>
                <label class="evo-toggle-slider" for="cfg_send_on_ticket_created"></label>
                <label class="evo-toggle-label" for="cfg_send_on_ticket_created">Novo chamado criado</label>
            </div>

            <div class="evo-toggle">
                <input type="checkbox" name="send_on_status_changed" id="cfg_send_on_status_changed" value="1" <?= !empty($row['send_on_status_changed']) ? 'checked' : '' ?>>
                <label class="evo-toggle-slider" for="cfg_send_on_status_changed"></label>
                <label class="evo-toggle-label" for="cfg_send_on_status_changed">Status do chamado alterado</label>
            </div>

            <div class="evo-toggle">
                <input type="checkbox" name="send_on_solution_added" id="cfg_send_on_solution_added" value="1" <?= !empty($row['send_on_solution_added']) ? 'checked' : '' ?>>
                <label class="evo-toggle-slider" for="cfg_send_on_solution_added"></label>
                <label class="evo-toggle-label" for="cfg_send_on_solution_added">Solução adicionada ao chamado</label>
            </div>
        </div>
    </div>

    </div> <!-- /card-grid -->

    <!-- Templates (full width) -->
    <div class="evo-card evo-card-full">
    <div class="evo-card">
    <div class="evo-card-header">
        <i class="fas fa-edit"></i> Modelos de Mensagem
        <button type="button" class="evo-btn evo-btn-placeholder" id="btn-show-placeholders" style="margin-left:auto;padding:5px 14px;font-size:12px;background:#e8e8e8;border:none;border-radius:6px;cursor:pointer;">
            <i class="fas fa-code"></i> Placeholders
        </button>
    </div>
        <div class="evo-card-body">
            <p style="margin:0 0 8px;font-size:13px;color:var(--evo-text-secondary);">
                Personalize o texto das mensagens WhatsApp. Deixe em branco para usar o padrão.
            </p>

            <div class="evo-placeholder-hint" id="evo-placeholder-bar" style="cursor:pointer;" onclick="document.getElementById('btn-show-placeholders').click()">
                <i class="fas fa-info-circle"></i> Clique em <strong>Placeholders</strong> para ver todos os códigos disponíveis e copiá-los.
            </div>

            <div class="evo-grid-2">
                <div class="evo-form-group">
                    <label for="cfg_template_waiting">Aguardando Aprovação</label>
                    <textarea name="template_waiting" id="cfg_template_waiting" rows="7"><?= htmlspecialchars($row['template_waiting'] ?? '') ?></textarea>
                </div>

                <div class="evo-form-group">
                    <label for="cfg_template_accepted">Aprovado</label>
                    <textarea name="template_accepted" id="cfg_template_accepted" rows="7"><?= htmlspecialchars($row['template_accepted'] ?? '') ?></textarea>
                </div>

                <div class="evo-form-group">
                    <label for="cfg_template_refused">Recusado</label>
                    <textarea name="template_refused" id="cfg_template_refused" rows="7"><?= htmlspecialchars($row['template_refused'] ?? '') ?></textarea>
                </div>

                <div class="evo-form-group">
                    <label for="cfg_template_ticket_created">Novo Chamado</label>
                    <textarea name="template_ticket_created" id="cfg_template_ticket_created" rows="7"><?= htmlspecialchars($row['template_ticket_created'] ?? '') ?></textarea>
                </div>

                <div class="evo-form-group">
                    <label for="cfg_template_status_changed">Status Alterado</label>
                    <textarea name="template_status_changed" id="cfg_template_status_changed" rows="7"><?= htmlspecialchars($row['template_status_changed'] ?? '') ?></textarea>
                </div>

                <div class="evo-form-group">
                    <label for="cfg_template_solution_added">Solução Adicionada</label>
                    <textarea name="template_solution_added" id="cfg_template_solution_added" rows="7"><?= htmlspecialchars($row['template_solution_added'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div> <!-- /evo-card-body -->

    </div> <!-- /templates card -->

    <!-- Actions (full width) -->
    <div class="evo-card evo-card-full">
    <div class="evo-card">
        <div class="evo-card-body">
            <div class="evo-form-actions">
                <button type="button" class="evo-btn evo-btn-primary" id="btn-save-config">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
                <button type="button" class="evo-btn evo-btn-success" id="btn-test-evolution-notify">
                    <i class="fab fa-whatsapp"></i> Testar Envio
                </button>
            </div>
        </div>
    </div>

</div>

<!-- Test Modal -->
<div class="evo-modal-overlay" id="modal-test-evolution-notify">
    <div class="evo-modal">
        <div class="evo-modal-header">
            <h3><i class="fab fa-whatsapp" style="color:var(--evo-primary)"></i> Testar Envio WhatsApp</h3>
            <button type="button" class="evo-modal-close" data-close-modal>&times;</button>
        </div>
        <div class="evo-modal-body">
            <label for="test-phone" style="font-weight:600;font-size:14px;">Número de telefone (com DDD):</label>
            <br><br>
            <input type="text" id="test-phone" class="evo-form-group" style="width:100%;padding:10px 14px;border:1.5px solid var(--evo-border);border-radius:var(--evo-radius-sm);font-size:14px;" placeholder="5511999998888">
            <br><br>
            <div id="test-result" style="display:none;padding:12px;border-radius:var(--evo-radius-sm);font-size:13px;font-weight:500;"></div>
        </div>
        <div class="evo-modal-footer">
            <button type="button" class="evo-btn evo-btn-secondary" data-close-modal>Cancelar</button>
            <button type="button" class="evo-btn evo-btn-primary" id="btn-send-test-msg">
                <i class="fab fa-whatsapp"></i> Enviar
            </button>
        </div>
    </div>
</div>

<!-- Placeholders Modal -->
<div class="evo-modal-overlay" id="modal-placeholders">
    <div class="evo-modal" style="max-width:580px;">
        <div class="evo-modal-header">
            <h3><i class="fas fa-code" style="color:var(--evo-primary-dark)"></i> Placeholders Disponíveis</h3>
            <button type="button" class="evo-modal-close" data-close-modal>&times;</button>
        </div>
        <div class="evo-modal-body">
            <p style="margin:0 0 12px;font-size:13px;color:var(--evo-text-secondary);">
                Clique em um placeholder para copiá-lo. Use nos modelos de mensagem acima.
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;" id="placeholder-list">
                <div class="ph-item" data-ph="{ticket_id}">
                    <code class="ph-code">{ticket_id}</code>
                    <span class="ph-desc">ID do chamado</span>
                </div>
                <div class="ph-item" data-ph="{ticket_title}">
                    <code class="ph-code">{ticket_title}</code>
                    <span class="ph-desc">Título do chamado</span>
                </div>
                <div class="ph-item" data-ph="{status}">
                    <code class="ph-code">{status}</code>
                    <span class="ph-desc">Status (ex: Aprovado)</span>
                </div>
                <div class="ph-item" data-ph="{comment}">
                    <code class="ph-code">{comment}</code>
                    <span class="ph-desc">Comentário da validação</span>
                </div>
                <div class="ph-item" data-ph="{comment_block}">
                    <code class="ph-code">{comment_block}</code>
                    <span class="ph-desc">Bloco "Comentário:" inteiro (vazio se sem comentário)</span>
                </div>
                <div class="ph-item" data-ph="{requester}">
                    <code class="ph-code">{requester}</code>
                    <span class="ph-desc">Nome do solicitante</span>
                </div>
                <div class="ph-item" data-ph="{requester_id}">
                    <code class="ph-code">{requester_id}</code>
                    <span class="ph-desc">ID do solicitante</span>
                </div>
                <div class="ph-item" data-ph="{approver}">
                    <code class="ph-code">{approver}</code>
                    <span class="ph-desc">Nome do aprovador</span>
                </div>
                <div class="ph-item" data-ph="{approver_id}">
                    <code class="ph-code">{approver_id}</code>
                    <span class="ph-desc">ID do aprovador</span>
                </div>
                <div class="ph-item" data-ph="{url}">
                    <code class="ph-code">{url}</code>
                    <span class="ph-desc">Link direto para o chamado</span>
                </div>
                <div class="ph-item" data-ph="{glpi_url}">
                    <code class="ph-code">{glpi_url}</code>
                    <span class="ph-desc">URL base do GLPI</span>
                </div>
            </div>
        </div>
        <div class="evo-modal-footer">
            <div id="ph-copied-msg" style="font-size:13px;color:#28a745;display:none;"><i class="fas fa-check-circle"></i> Copiado!</div>
            <button type="button" class="evo-btn evo-btn-secondary" data-close-modal>Fechar</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="evo-toast" id="evo-toast"></div>

<script>
(function() {
    var baseUrl = window.location.pathname.replace(/\/front\/config\.php$/, '');
    var ajaxUrl = baseUrl + '/ajax/save_config.php';
    var testUrl = baseUrl + '/ajax/test_send.php';

    function showToast(msg, type) {
        var t = $('#evo-toast');
        t.removeClass('evo-toast-success evo-toast-error');
        if (type === 'success') t.addClass('evo-toast-success');
        else t.addClass('evo-toast-error');
        t.html(msg).fadeIn(300);
        setTimeout(function() { t.fadeOut(300); }, 4000);
    }

    // Modal
    function openModal() {
        $('#modal-test-evolution-notify').addClass('active');
        $('#test-phone').val('').focus();
        $('#test-result').hide().html('').removeClass('alert-success alert-danger');
    }
    function closeModal() {
        $('#modal-test-evolution-notify').removeClass('active');
    }
    $('#btn-test-evolution-notify').on('click', openModal);
    $('[data-close-modal]').on('click', closeModal);
    $(document).on('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

    // Save config
    $('#btn-save-config').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                _glpi_csrf_token: '<?= $csrfToken ?>',
                entities_id:             $('input[name=entities_id]').val(),
                api_url:                 $('#cfg_api_url').val(),
                api_token:               $('#cfg_api_token').val(),
                instance:                $('#cfg_instance').val(),
                send_on_waiting:         $('#cfg_send_on_waiting').is(':checked') ? 1 : 0,
                send_on_accepted:        $('#cfg_send_on_accepted').is(':checked') ? 1 : 0,
                send_on_refused:         $('#cfg_send_on_refused').is(':checked') ? 1 : 0,
                send_on_ticket_created:  $('#cfg_send_on_ticket_created').is(':checked') ? 1 : 0,
                send_on_status_changed:  $('#cfg_send_on_status_changed').is(':checked') ? 1 : 0,
                send_on_solution_added:  $('#cfg_send_on_solution_added').is(':checked') ? 1 : 0,
                template_waiting:        $('#cfg_template_waiting').val(),
                template_accepted:       $('#cfg_template_accepted').val(),
                template_refused:        $('#cfg_template_refused').val(),
                template_ticket_created: $('#cfg_template_ticket_created').val(),
                template_status_changed: $('#cfg_template_status_changed').val(),
                template_solution_added: $('#cfg_template_solution_added').val()
            },
            success: function(data) {
                if (data.ok) {
                    showToast('Configuração salva com sucesso!', 'success');
                } else {
                    showToast('Erro: ' + data.error, 'error');
                }
            },
            error: function(xhr) {
                showToast('Erro de conexão: ' + xhr.statusText, 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save"></i> Salvar Configurações');
            }
        });
    });

    // Send test
    $('#btn-send-test-msg').on('click', function() {
        var phone = $.trim($('#test-phone').val());
        if (phone === '') {
            $('#test-result').show().css({'background':'#f8d7da','color':'#721c24'}).html('Informe um número de telefone.');
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

        $.ajax({
            url: testUrl,
            type: 'POST',
            dataType: 'json',
            data: { phone: phone, _glpi_csrf_token: '<?= $csrfToken ?>' },
            success: function(data) {
                if (data.ok) {
                    $('#test-result').show()
                        .css({'background':'#d4edda','color':'#155724'})
                        .html('<i class="fas fa-check-circle"></i> ' + data.msg);
                } else {
                    $('#test-result').show()
                        .css({'background':'#f8d7da','color':'#721c24'})
                        .html('<i class="fas fa-exclamation-circle"></i> ' + data.error);
                }
            },
            error: function(xhr) {
                $('#test-result').show()
                    .css({'background':'#f8d7da','color':'#721c24'})
                    .html('<i class="fas fa-exclamation-circle"></i> Erro de conexão: ' + xhr.statusText);
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fab fa-whatsapp"></i> Enviar');
            }
        });
    });

    // Placeholders modal
    function openPlaceholders() {
        $('#modal-placeholders').addClass('active');
        $('#ph-copied-msg').hide();
    }
    function closePlaceholders() {
        $('#modal-placeholders').removeClass('active');
    }
    $('#btn-show-placeholders').on('click', openPlaceholders);
    $('#modal-placeholders [data-close-modal]').on('click', closePlaceholders);

    // Click to copy
    $('#placeholder-list').on('click', '.ph-item', function() {
        var text = $(this).data('ph');
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);

        $('.ph-item').removeClass('copied');
        $(this).addClass('copied');
        $('#ph-copied-msg').fadeIn(200).delay(1500).fadeOut(200);
    });
})();
</script>
<?php

Html::footer();
