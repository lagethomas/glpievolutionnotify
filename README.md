# Evolution Notify

Plugin GLPI que envia notificações WhatsApp em tempo real para validações de chamados via [Evolution API](https://github.com/EvolutionAPI/evolution-api).

## Funcionalidades

- Envia mensagens WhatsApp quando uma validação de chamado é **solicitada**, **aprovada** ou **recusada**
- Integração com Evolution API (self-hosted ou cloud)
- Configurável por tipo de evento (aguardando / aprovado / recusado)
- Botão de teste na página de configuração
- Suporte a fluxo de múltiplas aprovações (GLPI 11)
- Controle de deduplicação para evitar notificações duplicadas
- Cron como fallback para eventos perdidos

## Requisitos

- GLPI **10.0.x** ou **11.0.x**
- PHP **8.1+**
- Uma instância Evolution API (veja [documentação Evolution API](https://doc.evolution-api.com/v2))

## Instalação

1. Baixe o plugin e extraia no diretório `marketplace/` do GLPI:

```bash
unzip glpievolutionnotify.zip -d /var/glpi/marketplace/
```

2. Renomeie o diretório se necessário (deve ser `glpievolutionnotify`):

```bash
mv /var/glpi/marketplace/glpievolutionnotify-main /var/glpi/marketplace/glpievolutionnotify
```

3. Acesse **Configuração → Plugins**, localize *Evolution Notify* e clique em **Instalar** e depois **Ativar**.

## Configuração

1. Acesse **Configuração → Evolution Notify** no menu do GLPI.
2. Preencha as credenciais da Evolution API:
   - **API URL** — URL base da sua Evolution API (ex: `https://evo.exemplo.com`)
   - **API Token** — token de autenticação da Evolution API
   - **Instance** — nome da instância na Evolution API
3. Selecione quais eventos disparam notificações:
   - Enviar quando **Aguardando** aprovação
   - Enviar quando **Aprovado**
   - Enviar quando **Recusado**
4. Clique em **Salvar Configurações**.
5. Use o botão **Testar Envio** para verificar a entrega no WhatsApp.

## Como funciona

### Hooks (tempo real)

Quando uma validação de chamado é criada ou atualizada, o plugin aciona os hooks `item_add` / `item_update` em `TicketValidation` / `CommonITILValidation` e envia a mensagem WhatsApp imediatamente.

### Cron (fallback)

Uma tarefa cron (`PluginGlpievolutionnotifyNotification::cronNotify`) é executada a cada minuto e processa validações que não foram capturadas pelos hooks. A tabela de controle `glpi_plugin_evolutionnotify_notified` evita envios duplicados.

### Compatibilidade GLPI 11

O GLPI 11 introduziu validação em múltiplas etapas. O plugin lida com:
- `itemtype_target` / `items_id_target` para resolução do usuário alvo
- `comment_submission` / `comment_validation` para comentários de solicitação e aprovação
- Constantes `\Glpi\Plugin\Hooks` quando disponíveis

## Logs

Os logs são gravados em `evolution_notify.log` no diretório de logs do GLPI (`GLPI_LOG_DIR`). Útil para depurar problemas de entrega.

## Estrutura de arquivos

```
glpievolutionnotify/
├── ajax/
│   ├── save_config.php      # Handler AJAX para salvar configuração
│   └── test_send.php        # Handler AJAX para teste de envio
├── front/
│   └── config.php           # Página de configuração
├── inc/
│   └── notification.class.php  # Lógica principal (send, cron, dedup)
├── hook.php                 # Install/uninstall + callbacks dos hooks
├── setup.php                # Metadados do plugin + registro de hooks/cron
└── README.md
```

## Licença

GNU General Public License v2.0 ou posterior.

Veja [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) para o texto completo.
