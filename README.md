# 🚀 Evolution Notify - Plugin GLPI

![License](https://img.shields.io/badge/License-GPL%20v2-blue.svg)
![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-4F5B93.svg)
![GLPI Version](https://img.shields.io/badge/GLPI-10.0%2B%20%7C%2011.0%2B-green.svg)
![WhatsApp](https://img.shields.io/badge/WhatsApp-Integration-25D366.svg)

> 📱 **Notifique aprovadores via WhatsApp em tempo real** sobre chamados, validações e eventos do GLPI usando Evolution API

Um plugin robusto e profissional que integra seu GLPI com WhatsApp, enviando notificações automáticas e personalizadas para aprovadores, solicitantes e equipes.

---

## ✨ Principais Funcionalidades

### 🔔 Notificações Inteligentes
- **Validações de Chamados** — notifica aprovadores quando uma validação é solicitada
- **Aprovação/Rejeição** — confirma decisões via WhatsApp
- **Novo Chamado** — avisa o solicitante imediatamente após criação
- **Mudança de Status** — rastreia alterações de tickets em tempo real
- **Solução Adicionada** — notifica quando uma solução é registrada
- **Notificação ao Solicitante** — feedback automático após validação respondida

### ⚙️ Recursos Avançados
- ✅ **Suporte a Grupos** — notifica todos os membros quando o alvo é um grupo
- ✅ **Multi-entidade** — configurações independentes por entidade GLPI
- ✅ **Templates Customizáveis** — personalize textos com placeholders dinâmicos
- ✅ **Webhook Nativo GLPI 11** — integração direta com webhooks do sistema
- ✅ **Cron de Fallback** — captura eventos não processados pelos hooks
- ✅ **Histórico Completo** — registra todas as notificações enviadas
- ✅ **Deduplicação** — evita notificações duplicadas e spam
- ✅ **Teste de Envio** — valide a integração com um clique

---

## 📋 Placeholders Disponíveis

Use esses placeholders para personalizar suas mensagens:

| Placeholder | Descrição |
|---|---|
| `{ticket_id}` | ID do chamado |
| `{ticket_title}` | Título/Assunto do chamado |
| `{status}` | Status da validação (ex: "Aprovado", "Recusado") |
| `{comment}` | Comentário da validação ou solução |
| `{comment_block}` | Bloco formatado "Comentário:" + texto (vazio se nenhum) |
| `{requester}` | Nome do solicitante |
| `{requester_id}` | ID do solicitante |
| `{approver}` | Nome do aprovador |
| `{approver_id}` | ID do aprovador |
| `{url}` | Link direto para o chamado |
| `{glpi_url}` | URL base do GLPI |

**Exemplo de template:**
```
Novo chamado #{ticket_id}: {ticket_title}
Solicitante: {requester}
Abra em: {url}
```

---

## 🎯 Requisitos de Sistema

- **GLPI**: versão 10.0.x ou 11.0.x
- **PHP**: 8.1 ou superior
- **Evolution API**: instância ativa e configurada (veja [documentação oficial](https://doc.evolution-api.com/v2))
- **cURL**: habilitado no PHP

---

## 📦 Instalação

### 1️⃣ Baixe e extraia o plugin

```bash
# Faça download do repositório
unzip glpievolutionnotify.zip -d /var/glpi/marketplace/
```

### 2️⃣ Renomeie o diretório (se necessário)

```bash
# Caso tenha sido extraído com sufixo -main
mv /var/glpi/marketplace/glpievolutionnotify-main /var/glpi/marketplace/glpievolutionnotify
```

### 3️⃣ Ative no GLPI

1. Acesse **Configuração → Plugins**
2. Localize **Evolution Notify**
3. Clique em **Instalar** → **Ativar**

✅ **Pronto!** O plugin está ativo e pronto para configuração.

---

## ⚡ Configuração Rápida

### Passo 1: Acesse as Configurações

1. No menu do GLPI, vá para **Administração → Evolution Notify**
2. Selecione a entidade desejada (opcional)

### Passo 2: Credenciais da Evolution API

Preencha as seguintes informações:

```
API URL      → https://evo.seudominio.com
API Token    → seu_token_de_autenticacao
Instance     → nome_da_instancia
```

### Passo 3: Selecione Eventos

Marque quais eventos devem disparar notificações:
- [ ] Validação solicitada
- [ ] Validação aprovada
- [ ] Validação recusada
- [ ] Novo chamado criado
- [ ] Status alterado
- [ ] Solução adicionada

### Passo 4: Customize Templates

Edite os textos das mensagens usando os placeholders disponíveis. Exemplo:

**Validação Solicitada:**
```
Olá {approver}, você tem uma validação pendente!

Chamado: {ticket_title} (#{ticket_id})
Solicitante: {requester}
Abra aqui: {url}
```

### Passo 5: Teste a Integração

1. Na página de configuração, clique em **Testar Envio**
2. Escolha um contato para enviar a mensagem de teste
3. Confirme que a mensagem chegou no WhatsApp ✅

---

## 🪝 Configuração do Webhook (GLPI 11+)

Para receber eventos em tempo real, configure um webhook:

### No GLPI:

1. Acesse **Configuração → Webhooks**
2. Clique em **Adicionar webhook**
3. Preencha com:
   - **URL**: `https://seuglpi.com/plugins/glpievolutionnotify/front/webhook.php`
   - **Eventos**: Selecione `approval.*`
   - **Ativo**: Sim

4. Clique em **Salvar**

### Resultado:

O plugin receberá eventos automaticamente e enviará notificações em tempo real!

---

## 📊 Histórico de Notificações

Acesse **Administração → Evolution Notify - Histórico** para visualizar:

- 📅 Data e hora de envio
- 📱 Número de telefone (parcialmente mascarado)
- 🏷️ Tipo de evento (validação, novo chamado, etc.)
- ✅ Status de entrega (HTTP 200, 400, etc.)
- 🔄 Tentativas de reenvio

---

## 📁 Estrutura do Projeto

```
glpievolutionnotify/
├── ajax/
│   ├── save_config.php        # Handler AJAX para salvar configurações
│   └── test_send.php          # Teste de envio de mensagens
├── front/
│   ├── config.php             # Interface de configuração (multi-entidade)
│   ├── history.php            # Página de histórico de notificações
│   └── webhook.php            # Endpoint para webhooks GLPI 11+
├── inc/
│   └── notification.class.php  # Núcleo da lógica (envio, templates, grupos, cron)
├── hook.php                    # Instalação/desinstalação e callbacks
├── setup.php                   # Metadados do plugin e registro de hooks
└── README.md                   # Este arquivo
```

---

## 🔒 Segurança

- ✅ Tokens de autenticação criptografados
- ✅ Validação de webhook com segurança GLPI
- ✅ Prevenção de injeção SQL e XSS
- ✅ Logs de auditoria de todas as notificações
- ✅ Conformidade com permissões do GLPI

---

## 🐛 Troubleshooting

### Notificações não são enviadas?

1. Verifique as credenciais da Evolution API
2. Confirme que a instância existe e está ativa
3. Teste a conexão usando o botão **Testar Envio**
4. Verifique o Histórico para erros de entrega
5. Consulte os logs do GLPI: `/var/log/glpi/glpi.log`

### Mensagens chegam duplicadas?

1. Verifique se o webhook e o cron estão ambos ativos
2. Aumente o intervalo do cron de fallback

### Números de telefone não reconhecidos?

1. Certifique-se de que os contatos estão com formato válido (+55 11 99999-9999)
2. Verifique se estão salvos na Evolution API

---

## 📝 Licença

Este projeto está licenciado sob a **GNU General Public License v2.0 ou posterior**.

Consulte [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) para o texto completo.

---

## 👨‍💻 Autor

**Thomas Lage** - [@lagethomas](https://github.com/lagethomas)

---

## 🤝 Contribuições

Sugestões e melhorias são bem-vindas! Sinta-se à vontade para:
- 🐛 Reportar bugs
- 💡 Sugerir novas funcionalidades
- 🔧 Enviar pull requests

---

## 📞 Suporte

Dúvidas ou problemas? Abra uma [issue](https://github.com/lagethomas/glpievolutionnotify/issues) no repositório!

---

**Última atualização:** 2026-08-14
