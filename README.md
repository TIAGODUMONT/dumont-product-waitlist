# Dumont Product Waitlist

Plugin WordPress para capturar, armazenar e notificar interessados em **produtos esgotados** — sem depender do WooCommerce. Funciona com qualquer post, página ou custom post type (o `product_id` é o ID do post).

- **Nome:** Dumont Product Waitlist
- **Slug:** `dumont-product-waitlist`
- **Versão:** 1.0.0
- **Requer:** WordPress 5.5+ / PHP 7.2+

---

## Como instalar

1. Copie a pasta `dumont-product-waitlist/` para `wp-content/plugins/` do site (via FTP ou pelo painel: **Plugins → Adicionar novo → Enviar plugin** com o `.zip`).
2. No painel, vá em **Plugins** e clique em **Ativar** no "Dumont Product Waitlist".

## Como ativar

Ao ativar, o plugin cria automaticamente a tabela `wp_dumont_product_waitlist` no banco e grava as configurações padrão. Não precisa de mais nada.

## Como usar o shortcode

Em qualquer página/post/produto, insira:

```
[dumont_waitlist_form product_id="123"]
```

Se omitir o `product_id`, ele usa o ID do post atual:

```
[dumont_waitlist_form]
```

## Como chamar via PHP no tema

Dentro do template do produto (ex.: `single-produto.php`), no lugar onde fica a peça esgotada:

```php
<?php echo dumont_waitlist_form( get_the_ID() ); ?>
```

> Dica: exiba o formulário só quando a peça estiver esgotada. Exemplo no tema Bruntiano:
> ```php
> <?php if ( $esgotado ) : ?>
>     <?php echo dumont_waitlist_form( get_the_ID() ); ?>
> <?php endif; ?>
> ```

O formulário coleta **nome**, **e-mail**, **WhatsApp** (opcional) e **mensagem** (opcional). Valida obrigatórios, valida e-mail, sanitiza tudo, usa nonce + honeypot anti-spam e evita e-mail duplicado para o mesmo produto.

## Como notificar os interessados

Quando a peça voltar ao estoque, há duas formas:

1. **Pelo painel:** vá em **Produtos Waitlist → Leads**, filtre pelo produto e clique em **"Avisar interessados deste produto"**. O plugin envia e-mail para todos os leads pendentes daquele produto, marca cada um como *notificado* e preenche a data de notificação.

2. **Via código:**
   ```php
   dumont_notify_waitlist( $product_id ); // retorna a qtd. de notificados
   ```
   Útil, por exemplo, para disparar automaticamente quando você desmarcar "esgotado" no produto.

Há também, ao lado de cada lead, um botão **"Abrir WhatsApp"** com a mensagem pronta (envio manual — esta versão não usa API automática de WhatsApp).

## Como personalizar mensagens

Em **Produtos Waitlist → Configurações** você define:

- E-mail e nome do remetente
- Assunto e modelo (corpo) do e-mail — com as variáveis `{name}`, `{product_title}`, `{product_url}`, `{site_name}`
- Texto do botão do formulário
- Mensagens de sucesso e de erro
- Ativar/desativar o campo de WhatsApp

## Estrutura

```
dumont-product-waitlist/
├── dumont-product-waitlist.php   # bootstrap do plugin
├── includes/
│   ├── class-activator.php       # cria a tabela na ativação
│   ├── class-database.php        # CRUD/queries (tabela própria)
│   ├── class-form.php            # shortcode + processamento do formulário
│   ├── class-admin.php           # menu, Leads, Configurações
│   ├── class-notifier.php        # envio de e-mails (wp_mail)
│   └── helpers.php               # dumont_waitlist_form(), dumont_notify_waitlist(), settings
├── assets/
│   ├── css/{public.css, admin.css}
│   └── js/public.js
└── README.md
```

## Segurança

`$wpdb->prepare()`, `sanitize_text_field()`, `sanitize_email()`, `sanitize_textarea_field()`, `esc_html()`, `esc_url()`, `esc_attr()`, `wp_nonce_field()`/`wp_verify_nonce()`, `current_user_can('manage_options')` no admin e `ABSPATH` em todos os arquivos. Prefixos exclusivos: funções `dumont_waitlist_` / `dumont_notify_waitlist`, classes `Dumont_Waitlist_`.

## Desinstalação

A tabela e as opções **não** são removidas automaticamente ao desativar (para não perder leads). Para limpar manualmente, remova a tabela `wp_dumont_product_waitlist` e as opções `dumont_waitlist_settings` / `dumont_waitlist_db_version`.
