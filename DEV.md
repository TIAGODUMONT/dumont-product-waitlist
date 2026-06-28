# Dumont Product Waitlist — Guia de evolução (DEV)

Documento técnico para continuar o desenvolvimento do plugin. Para uso/instalação, veja o `README.md`.

> **Versão atual:** 1.0.0 · **Última atualização:** 2026-06-27

---

## Onde mexer (arquitetura)

| Arquivo | Responsabilidade | Mexa aqui quando... |
|---------|------------------|---------------------|
| `dumont-product-waitlist.php` | Bootstrap: constantes, includes, hook de ativação, enqueue de assets públicos | adicionar includes, mudar versão, registrar novos assets |
| `includes/class-activator.php` | Roda na ativação: cria tabela + opções padrão | precisar migrar schema entre versões |
| `includes/class-database.php` | Toda a camada de dados (CRUD, filtros, contagem). Queries seguras com `$wpdb->prepare()` | adicionar campos, novas consultas, exportações |
| `includes/class-form.php` | Shortcode `[dumont_waitlist_form]` + processamento do POST (validação, sanitização, anti-spam, duplicidade) | mudar campos do formulário, regras de validação |
| `includes/class-notifier.php` | Envio de e-mail (`wp_mail`) + marcação de notificado | mudar template/headers, adicionar canais (ex.: API WhatsApp) |
| `includes/class-admin.php` | Menu, tela de Leads (tabela/filtros/ações), tela de Configurações (Settings API) | colunas novas, ações em massa, novas opções |
| `includes/helpers.php` | Funções globais: `dumont_waitlist_form()`, `dumont_notify_waitlist()`, settings | helpers reutilizáveis no tema |
| `assets/css/public.css` | Estilo do formulário público | visual do form |
| `assets/css/admin.css` | Estilo do admin | visual da tela de Leads |
| `assets/js/public.js` | UX do form (evita duplo envio) | validação client-side extra |

**Prefixos (sempre usar):** funções `dumont_waitlist_` / `dumont_notify_waitlist`; classes `Dumont_Waitlist_`; meta/opções `dumont_waitlist_`.

## Modelo de dados

Tabela `wp_dumont_product_waitlist` (criada por `Dumont_Waitlist_Database::create_table()` via `dbDelta`):

`id`, `product_id`, `product_title`, `product_url`, `name`, `email`, `whatsapp`, `message`, `status` (`pending`|`notified`), `notified_at`, `created_at`, `updated_at`.

Opções (wp_options): `dumont_waitlist_settings` (array), `dumont_waitlist_db_version`.

> **Ao mudar o schema:** suba a versão em `dumont-product-waitlist.php` e ajuste `create_table()`. `dbDelta` aplica diffs na reativação. Para migração com dado existente, comparar `dumont_waitlist_db_version` na ativação e rodar a migração.

## Variáveis do template de e-mail
`{name}`, `{product_title}`, `{product_url}`, `{site_name}` — substituídas em `Dumont_Waitlist_Notifier::notify_product()` (valores já escapados).

## Como testar localmente
1. Copiar a pasta para `wp-content/plugins/` e ativar.
2. Pôr `[dumont_waitlist_form product_id="123"]` numa página (ou usar a integração do tema em produto esgotado).
3. Enviar um lead → conferir em **Produtos Waitlist → Leads**.
4. E-mails: em dev, use um plugin tipo "WP Mail Logging" ou Mailpit/Mailhog para inspecionar o `wp_mail()`.

## Integração atual no tema Bruntiano
- `single-produto.php`: em produto esgotado, chama `dumont_waitlist_form( get_the_ID() )` (guardado por `function_exists`; fallback = botão WhatsApp).
- `template-parts/product-card.php`: card esgotado linka pra página do produto (onde está o form).

---

## Changelog

### 1.0.0 — 2026-06-27
- Versão inicial. Tabela própria, shortcode + função PHP, validação/sanitização/nonce/honeypot, anti-duplicidade.
- Admin: Leads (filtros por produto/status, busca, marcar notificado, excluir, Abrir WhatsApp, "Avisar interessados deste produto") + Configurações (Settings API).
- Notificação por e-mail (`wp_mail`) com template configurável.

---

## Roadmap / ideias (próximas versões)

- [ ] **Exportar leads em CSV** (botão na tela de Leads).
- [ ] **Contador de interessados** por produto (coluna na listagem de produtos do WP, ou metabox no editor).
- [ ] **Disparo automático**: ao desmarcar "esgotado" no produto (`save_post` / mudança do meta `_bt_esgotado`), chamar `dumont_notify_waitlist()` automaticamente. Adicionar opção on/off nas Configurações.
- [ ] **Paginação** na tela de Leads (hoje limita a 300).
- [ ] **WP_List_Table** para ordenação por coluna e ações em massa.
- [ ] **Double opt-in** (confirmação por e-mail antes de entrar na lista) — anti-spam mais forte.
- [ ] **E-mail para o admin** a cada novo lead (opção).
- [ ] **Internacionalização**: gerar `.pot` e traduções (text domain já é `dumont-product-waitlist`).
- [ ] **Desinstalação opcional**: `uninstall.php` que remove tabela/opções se o admin marcar "limpar ao desinstalar".
- [ ] **Reabrir lista**: ação para voltar um lead `notified` → `pending`.
- [ ] **Rate limit** por IP/e-mail no envio do formulário (anti-flood).
