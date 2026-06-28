<?php
/**
 * Área administrativa — menu "Produtos Waitlist", tela de Leads e Configurações.
 *
 * @package Dumont_Product_Waitlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Dumont_Waitlist_Admin
 */
class Dumont_Waitlist_Admin {

	const CAPABILITY = 'manage_options';
	const MENU_SLUG  = 'dumont-waitlist';

	/**
	 * Registra hooks de admin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Menu e submenus.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page(
			__( 'Produtos Waitlist', 'dumont-product-waitlist' ),
			__( 'Produtos Waitlist', 'dumont-product-waitlist' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_leads_page' ),
			'dashicons-email-alt',
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Leads', 'dumont-product-waitlist' ),
			__( 'Leads', 'dumont-product-waitlist' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_leads_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Configurações', 'dumont-product-waitlist' ),
			__( 'Configurações', 'dumont-product-waitlist' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * CSS do admin (só nas telas do plugin).
	 *
	 * @param string $hook Hook da tela atual.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'dumont-waitlist-admin',
			DUMONT_WAITLIST_URL . 'assets/css/admin.css',
			array(),
			DUMONT_WAITLIST_VERSION
		);
	}

	/**
	 * Registra a opção de settings (Settings API).
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'dumont_waitlist_group',
			'dumont_waitlist_settings',
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitiza o array de configurações antes de salvar.
	 *
	 * @param array $input Dados crus.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();

		$out['from_email']      = isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '';
		$out['from_name']       = isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '';
		$out['email_subject']   = isset( $input['email_subject'] ) ? sanitize_text_field( $input['email_subject'] ) : '';
		$out['email_template']  = isset( $input['email_template'] ) ? sanitize_textarea_field( $input['email_template'] ) : '';
		$out['button_text']     = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : '';
		$out['success_message'] = isset( $input['success_message'] ) ? sanitize_text_field( $input['success_message'] ) : '';
		$out['error_message']   = isset( $input['error_message'] ) ? sanitize_text_field( $input['error_message'] ) : '';
		$out['store_whatsapp']  = ( isset( $input['store_whatsapp'] ) && '1' === (string) $input['store_whatsapp'] ) ? '1' : '0';

		return $out;
	}

	/**
	 * Processa ações de admin (marcar notificado, excluir, notificar produto).
	 *
	 * @return void
	 */
	public static function handle_actions() {
		if ( ! isset( $_GET['page'] ) ) {
			return;
		}
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		if ( 0 !== strpos( $page, self::MENU_SLUG ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		// Ações por lead (GET com nonce).
		if ( isset( $_GET['dwl_action'], $_GET['lead'] ) ) {
			$action  = sanitize_text_field( wp_unslash( $_GET['dwl_action'] ) );
			$lead_id = absint( $_GET['lead'] );
			$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			if ( 'mark_notified' === $action && wp_verify_nonce( $nonce, 'dwl_mark_' . $lead_id ) ) {
				Dumont_Waitlist_Database::mark_notified( $lead_id );
				self::redirect_with_notice( 'marked' );
			}

			if ( 'delete' === $action && wp_verify_nonce( $nonce, 'dwl_delete_' . $lead_id ) ) {
				Dumont_Waitlist_Database::delete( $lead_id );
				self::redirect_with_notice( 'deleted' );
			}
		}

		// Notificar todos os pendentes de um produto (POST com nonce).
		if ( isset( $_POST['dwl_notify_product'] ) ) {
			check_admin_referer( 'dwl_notify_product' );
			$pid   = absint( $_POST['dwl_notify_product'] );
			$count = dumont_notify_waitlist( $pid );
			self::redirect_with_notice( 'notified', array( 'count' => $count, 'filter_product' => $pid ) );
		}
	}

	/**
	 * Redireciona de volta para a tela de Leads com um aviso.
	 *
	 * @param string $code  Código do aviso.
	 * @param array  $extra Query args extras a preservar.
	 * @return void
	 */
	protected static function redirect_with_notice( $code, $extra = array() ) {
		$args = array_merge(
			array(
				'page'       => self::MENU_SLUG,
				'dwl_notice' => $code,
			),
			$extra
		);
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Exibe o aviso (notice) pós-ação.
	 *
	 * @return void
	 */
	protected static function maybe_render_notice() {
		if ( ! isset( $_GET['dwl_notice'] ) ) {
			return;
		}
		$code  = sanitize_text_field( wp_unslash( $_GET['dwl_notice'] ) );
		$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;

		$messages = array(
			'marked'   => __( 'Lead marcado como notificado.', 'dumont-product-waitlist' ),
			'deleted'  => __( 'Lead excluído.', 'dumont-product-waitlist' ),
			'notified' => sprintf(
				/* translators: %d: quantidade de interessados notificados. */
				_n( '%d interessado notificado por e-mail.', '%d interessados notificados por e-mail.', $count, 'dumont-product-waitlist' ),
				$count
			),
		);

		if ( isset( $messages[ $code ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $code ] )
			);
		}
	}

	/**
	 * Tela "Leads".
	 *
	 * @return void
	 */
	public static function render_leads_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$product_id = isset( $_GET['filter_product'] ) ? absint( $_GET['filter_product'] ) : 0;
		$status     = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
		$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$leads    = Dumont_Waitlist_Database::get_leads(
			array(
				'product_id' => $product_id,
				'status'     => $status,
				'search'     => $search,
				'limit'      => 300,
			)
		);
		$products = Dumont_Waitlist_Database::get_products_with_leads();
		?>
		<div class="wrap dumont-waitlist-admin">
			<h1><?php esc_html_e( 'Leads — Lista de espera', 'dumont-product-waitlist' ); ?></h1>

			<?php self::maybe_render_notice(); ?>

			<form method="get" class="dumont-waitlist-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>">

				<label class="screen-reader-text" for="filter_product"><?php esc_html_e( 'Filtrar por produto', 'dumont-product-waitlist' ); ?></label>
				<select name="filter_product" id="filter_product">
					<option value="0"><?php esc_html_e( 'Todos os produtos', 'dumont-product-waitlist' ); ?></option>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p->product_id ); ?>" <?php selected( $product_id, (int) $p->product_id ); ?>>
							<?php echo esc_html( $p->product_title ? $p->product_title : ( '#' . $p->product_id ) ); ?> (<?php echo esc_html( $p->total ); ?>)
						</option>
					<?php endforeach; ?>
				</select>

				<label class="screen-reader-text" for="filter_status"><?php esc_html_e( 'Filtrar por status', 'dumont-product-waitlist' ); ?></label>
				<select name="filter_status" id="filter_status">
					<option value="" <?php selected( $status, '' ); ?>><?php esc_html_e( 'Todos os status', 'dumont-product-waitlist' ); ?></option>
					<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pendente', 'dumont-product-waitlist' ); ?></option>
					<option value="notified" <?php selected( $status, 'notified' ); ?>><?php esc_html_e( 'Notificado', 'dumont-product-waitlist' ); ?></option>
				</select>

				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar nome, e-mail ou WhatsApp', 'dumont-product-waitlist' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'dumont-product-waitlist' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>" class="button-link"><?php esc_html_e( 'Limpar', 'dumont-product-waitlist' ); ?></a>
			</form>

			<?php if ( $product_id ) : ?>
				<form method="post" class="dumont-waitlist-notify-bar">
					<?php wp_nonce_field( 'dwl_notify_product' ); ?>
					<input type="hidden" name="dwl_notify_product" value="<?php echo esc_attr( $product_id ); ?>">
					<button type="submit" class="button button-primary"
					        onclick="return confirm('<?php echo esc_js( __( 'Enviar e-mail para todos os interessados PENDENTES deste produto?', 'dumont-product-waitlist' ) ); ?>');">
						<?php esc_html_e( 'Avisar interessados deste produto', 'dumont-product-waitlist' ); ?>
					</button>
					<span class="description"><?php esc_html_e( 'Envia o e-mail só para quem ainda está como "Pendente".', 'dumont-product-waitlist' ); ?></span>
				</form>
			<?php endif; ?>

			<table class="widefat striped dumont-waitlist-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Produto', 'dumont-product-waitlist' ); ?></th>
						<th><?php esc_html_e( 'Nome', 'dumont-product-waitlist' ); ?></th>
						<th><?php esc_html_e( 'E-mail', 'dumont-product-waitlist' ); ?></th>
						<th><?php esc_html_e( 'WhatsApp', 'dumont-product-waitlist' ); ?></th>
						<th><?php esc_html_e( 'Mensagem', 'dumont-product-waitlist' ); ?></th>
						<th><?php esc_html_e( 'Status', 'dumont-product-waitlist' ); ?></th>
						<th><?php esc_html_e( 'Cadastro', 'dumont-product-waitlist' ); ?></th>
						<th><?php esc_html_e( 'Notificado em', 'dumont-product-waitlist' ); ?></th>
						<th><?php esc_html_e( 'Ações', 'dumont-product-waitlist' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $leads ) ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'Nenhum lead encontrado.', 'dumont-product-waitlist' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $leads as $lead ) : ?>
							<?php
							$wa_num  = preg_replace( '/\D+/', '', (string) $lead->whatsapp );
							$wa_msg  = sprintf(
								/* translators: 1: nome do produto, 2: link do produto. */
								__( 'Olá, tudo bem? O produto %1$s voltou ao estoque. Veja aqui: %2$s', 'dumont-product-waitlist' ),
								$lead->product_title,
								$lead->product_url
							);
							$wa_link = $wa_num ? 'https://wa.me/' . $wa_num . '?text=' . rawurlencode( $wa_msg ) : '';

							$mark_url = wp_nonce_url(
								add_query_arg(
									array( 'page' => self::MENU_SLUG, 'dwl_action' => 'mark_notified', 'lead' => $lead->id ),
									admin_url( 'admin.php' )
								),
								'dwl_mark_' . $lead->id
							);
							$del_url = wp_nonce_url(
								add_query_arg(
									array( 'page' => self::MENU_SLUG, 'dwl_action' => 'delete', 'lead' => $lead->id ),
									admin_url( 'admin.php' )
								),
								'dwl_delete_' . $lead->id
							);
							?>
							<tr>
								<td>
									<?php if ( $lead->product_url ) : ?>
										<a href="<?php echo esc_url( $lead->product_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $lead->product_title ? $lead->product_title : ( '#' . $lead->product_id ) ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $lead->product_title ? $lead->product_title : ( '#' . $lead->product_id ) ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $lead->name ); ?></td>
								<td><a href="<?php echo esc_url( 'mailto:' . $lead->email ); ?>"><?php echo esc_html( $lead->email ); ?></a></td>
								<td><?php echo esc_html( $lead->whatsapp ); ?></td>
								<td><?php echo esc_html( $lead->message ); ?></td>
								<td>
									<span class="dumont-waitlist-status dumont-waitlist-status--<?php echo esc_attr( $lead->status ); ?>">
										<?php echo 'notified' === $lead->status ? esc_html__( 'Notificado', 'dumont-product-waitlist' ) : esc_html__( 'Pendente', 'dumont-product-waitlist' ); ?>
									</span>
								</td>
								<td><?php echo esc_html( self::format_date( $lead->created_at ) ); ?></td>
								<td><?php echo esc_html( self::format_date( $lead->notified_at ) ); ?></td>
								<td class="dumont-waitlist-actions">
									<?php if ( $wa_link ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir WhatsApp', 'dumont-product-waitlist' ); ?></a>
									<?php endif; ?>
									<?php if ( 'notified' !== $lead->status ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $mark_url ); ?>"><?php esc_html_e( 'Marcar notificado', 'dumont-product-waitlist' ); ?></a>
									<?php endif; ?>
									<a class="button button-small button-link-delete" href="<?php echo esc_url( $del_url ); ?>"
									   onclick="return confirm('<?php echo esc_js( __( 'Excluir este lead?', 'dumont-product-waitlist' ) ); ?>');"><?php esc_html_e( 'Excluir', 'dumont-product-waitlist' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Formata data para exibição (ou "—" se vazia).
	 *
	 * @param string|null $datetime Data MySQL.
	 * @return string
	 */
	protected static function format_date( $datetime ) {
		if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return '—';
		}
		$ts = strtotime( $datetime );
		return $ts ? date_i18n( 'd/m/Y H:i', $ts ) : '—';
	}

	/**
	 * Tela "Configurações".
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$s = dumont_waitlist_get_settings();
		?>
		<div class="wrap dumont-waitlist-admin">
			<h1><?php esc_html_e( 'Configurações — Produtos Waitlist', 'dumont-product-waitlist' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'dumont_waitlist_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dwl-from-email"><?php esc_html_e( 'E-mail remetente', 'dumont-product-waitlist' ); ?></label></th>
						<td><input type="email" id="dwl-from-email" class="regular-text" name="dumont_waitlist_settings[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="dwl-from-name"><?php esc_html_e( 'Nome do remetente', 'dumont-product-waitlist' ); ?></label></th>
						<td><input type="text" id="dwl-from-name" class="regular-text" name="dumont_waitlist_settings[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="dwl-subject"><?php esc_html_e( 'Assunto do e-mail', 'dumont-product-waitlist' ); ?></label></th>
						<td><input type="text" id="dwl-subject" class="large-text" name="dumont_waitlist_settings[email_subject]" value="<?php echo esc_attr( $s['email_subject'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="dwl-template"><?php esc_html_e( 'Modelo da mensagem de e-mail', 'dumont-product-waitlist' ); ?></label></th>
						<td>
							<textarea id="dwl-template" class="large-text" rows="8" name="dumont_waitlist_settings[email_template]"><?php echo esc_textarea( $s['email_template'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Variáveis disponíveis: {name}, {product_title}, {product_url}, {site_name}.', 'dumont-product-waitlist' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dwl-btn"><?php esc_html_e( 'Texto do botão', 'dumont-product-waitlist' ); ?></label></th>
						<td><input type="text" id="dwl-btn" class="regular-text" name="dumont_waitlist_settings[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="dwl-success"><?php esc_html_e( 'Mensagem de sucesso', 'dumont-product-waitlist' ); ?></label></th>
						<td><input type="text" id="dwl-success" class="large-text" name="dumont_waitlist_settings[success_message]" value="<?php echo esc_attr( $s['success_message'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="dwl-error"><?php esc_html_e( 'Mensagem de erro', 'dumont-product-waitlist' ); ?></label></th>
						<td><input type="text" id="dwl-error" class="large-text" name="dumont_waitlist_settings[error_message]" value="<?php echo esc_attr( $s['error_message'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Salvar WhatsApp', 'dumont-product-waitlist' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dumont_waitlist_settings[store_whatsapp]" value="1" <?php checked( $s['store_whatsapp'], '1' ); ?>>
								<?php esc_html_e( 'Mostrar e salvar o campo de WhatsApp no formulário', 'dumont-product-waitlist' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
