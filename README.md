# Evolution Notify

Plugin GLPI que envia notificações WhatsApp em tempo real para validações de chamados e outros eventos via [Evolution API](https://github.com/EvolutionAPI/evolution-api).

## Funcionalidades

- **Validações de chamados** — notifica quando uma validação é solicitada, aprovada ou recusada
- **Notificação ao solicitante** — quando a validação é respondida, o autor do chamado é notificado
- **Suporte a grupos** — quando o alvo da validação é um grupo, todos os membros são notificados
- **Novo chamado** — avisa o solicitante quando o ticket é criado
- **Mudança de status** — notifica quando o status do chamado é alterado
- **Solução adicionada** — envia mensagem quando uma solução é registrada
- **Multi-entidade** — configurações independentes por entidade GLPI
- **Templates customizáveis** — edite o texto da mensagem com placeholders
- **Webhook nativo GLPI 11** — endpoint para receber eventos diretamente do sistema de webhooks do GLPI
- **Cron de fallback** — processa validações não capturadas pelos hooks
- **Histórico de envios** — página com registro de todas as notificações enviadas
- **Controle de deduplicação** — evita notificações duplicadas
- **Teste de envio** — botão na página de configuração para testar a integração

## Placeholders dos Templates

| Placeholder | Descrição |
|---|---|
| `{ticket_id}` | ID do chamado |
| `{ticket_title}` | Título do chamado |
| `{status}` | Label do status (ex: "Aprovado") |
| `{comment}` | Comentário da validação/solução |
| `{comment_block}` | Bloco "Comentário:" + texto (vazio se sem comentário) |
| `{requester}` | Nome do solicitante |
| `{requester_id}` | ID do solicitante |
| `{approver}` | Nome do aprovador |
| `{approver_id}` | ID do aprovador |
| `{url}` | Link direto para o chamado |
| `{glpi_url}` | URL base do GLPI |

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

1. Acesse **Administração → Evolution Notify** no menu do GLPI.
2. Selecione a entidade (opcional).
3. Preencha as credenciais da Evolution API:
   - **API URL** — URL base da sua Evolution API (ex: `https://evo.exemplo.com`)
   - **API Token** — token de autenticação da Evolution API
   - **Instance** — nome da instância na Evolution API
4. Marque quais eventos devem disparar notificações.
5. Edite os templates das mensagens conforme desejado.
6. Clique em **Salvar Configurações**.
7. Use o botão **Testar Envio** para verificar a entrega no WhatsApp.

## Webhook Nativo (GLPI 11)

1. No GLPI, acesse **Configuração → Webhooks → Adicionar**.
2. Defina a URL: `https://seuglpi/plugins/glpievolutionnotify/front/webhook.php`
3. Selecione os eventos `approval.*`.
4. Salve.

O plugin processará os eventos recebidos e enviará as notificações.

## Histórico

Acesse **Administração → Evolution Notify - Histórico** para visualizar todas as notificações enviadas, com data, tipo de evento, telefone e código HTTP de resposta.

## Estrutura de arquivos

```
glpievolutionnotify/
├── ajax/
│   ├── save_config.php      # Handler AJAX para salvar configuração
│   └── test_send.php        # Handler AJAX para teste de envio
├── front/
│   ├── config.php           # Página de configuração (multi-entidade + templates)
│   ├── history.php          # Página de histórico de notificações
│   └── webhook.php          # Endpoint para webhook nativo GLPI 11
├── inc/
│   └── notification.class.php  # Lógica principal (send, cron, templates, grupos)
├── hook.php                 # Install/uninstall + callbacks dos hooks
├── setup.php                # Metadados do plugin + registro de hooks/cron/menu
└── README.md
```

## Licença

GNU General Public License v2.0 ou posterior.

Veja [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) para o texto completo.
