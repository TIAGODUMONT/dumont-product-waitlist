<?php
/**
 * Formulário público — shortcode [dumont_waitlist_form] + processamento do POST.
 *
 * @package Dumont_Product_Waitlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Dumont_Waitlist_Form
 */
class Dumont_Waitlist_Form {

	/**
	 * Resultado do envio no request atual (para exibir mensagem inline).
	 *
	 * @var array|null array{ type:string, product_id:int, message:string }
	 */
	protected static $result = null;

	/**
	 * Registra shortcode e o handler de envio.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'dumont_waitlist_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'maybe_handle_submission' ) );
	}

	/**
	 * Processa o POST do formulário (validação, sanitização, gravação).
	 *
	 * @return void
	 */
	public static function maybe_handle_submission() {
		if ( ! isset( $_POST['dumont_waitlist_submit'] ) ) {
			return;
		}

		$product_id = isset( $_POST['dumont_waitlist_product_id'] ) ? absint( $_POST['dumont_waitlist_product_id'] ) : 0;

		// Segurança: nonce.
		$nonce = isset( $_POST['dumont_waitlist_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dumont_waitlist_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dumont_waitlist_submit_' . $product_id ) ) {
			self::$result = self::result( 'error', $product_id, dumont_waitlist_get_option( 'error_message' ) );
			return;
		}

		// Anti-spam básico (honeypot): campo que humanos não preenchem.
		if ( ! empty( $_POST['dumont_waitlist_hp'] ) ) {
			// Provável bot: finge sucesso e NÃO grava nada.
			self::$result = self::result( 'success', $product_id, dumont_waitlist_get_option( 'success_message' ) );
			return;
		}

		// Sanitização.
		$name     = isset( $_POST['dumont_waitlist_name'] ) ? sanitize_text_field( wp_unslash( $_POST['dumont_waitlist_name'] ) ) : '';
		$email    = isset( $_POST['dumont_waitlist_email'] ) ? sanitize_email( wp_unslash( $_POST['dumont_waitlist_email'] ) ) : '';
		$whatsapp = isset( $_POST['dumont_waitlist_whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['dumont_waitlist_whatsapp'] ) ) : '';
		$message  = isset( $_POST['dumont_waitlist_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dumont_waitlist_message'] ) ) : '';

		// Se o salvamento de WhatsApp estiver desativado, descarta o valor.
		if ( '1' !== dumont_waitlist_get_option( 'store_whatsapp' ) ) {
			$whatsapp = '';
		}

		// Validação dos obrigatórios.
		$errors = array();
		if ( '' === $name ) {
			$errors[] = __( 'Informe seu nome.', 'dumont-product-waitlist' );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			$errors[] = __( 'Informe um e-mail válido.', 'dumont-product-waitlist' );
		}
		if ( ! $product_id ) {
			$errors[] = __( 'Produto inválido.', 'dumont-product-waitlist' );
		}

		if ( ! empty( $errors ) ) {
			self::$result = self::result( 'error', $product_id, implode( ' ', $errors ) );
			return;
		}

		// Evita duplicidade do mesmo e-mail no mesmo produto.
		if ( Dumont_Waitlist_Database::email_exists_for_product( $email, $product_id ) ) {
			self::$result = self::result(
				'success',
				$product_id,
				__( 'Você já está na lista de espera desta peça. Avisaremos assim que ela voltar. 💛', 'dumont-product-waitlist' )
			);
			return;
		}

		$inserted = Dumont_Waitlist_Database::insert(
			array(
				'product_id'    => $product_id,
				'product_title' => get_the_title( $product_id ),
				'product_url'   => get_permalink( $product_id ),
				'name'          => $name,
				'email'         => $email,
				'whatsapp'      => $whatsapp,
				'message'       => $message,
			)
		);

		if ( $inserted ) {
			self::$result = self::result( 'success', $product_id, dumont_waitlist_get_option( 'success_message' ) );
		} else {
			self::$result = self::result( 'error', $product_id, dumont_waitlist_get_option( 'error_message' ) );
		}
	}

	/**
	 * Monta o array de resultado.
	 *
	 * @param string $type       success|error.
	 * @param int    $product_id ID do produto.
	 * @param string $message    Mensagem.
	 * @return array
	 */
	protected static function result( $type, $product_id, $message ) {
		return array(
			'type'       => $type,
			'product_id' => (int) $product_id,
			'message'    => (string) $message,
		);
	}

	/**
	 * Callback do shortcode.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts       = shortcode_atts( array( 'product_id' => 0 ), $atts, 'dumont_waitlist_form' );
		$product_id = absint( $atts['product_id'] );
		if ( ! $product_id ) {
			$product_id = (int) get_the_ID();
		}
		return self::render( $product_id );
	}

	/**
	 * Renderiza o formulário (ou a mensagem de sucesso).
	 *
	 * @param int $product_id ID do produto.
	 * @return string HTML escapado.
	 */
	public static function render( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return '';
		}

		$btn_text = dumont_waitlist_get_option( 'button_text' );
		$store_wa = ( '1' === dumont_waitlist_get_option( 'store_whatsapp' ) );
		$uid      = esc_attr( $product_id );

		ob_start();

		// Mensagem de resultado (só para o produto que enviou).
		if ( self::$result && (int) self::$result['product_id'] === $product_id ) {
			printf(
				'<div class="dumont-waitlist__notice dumont-waitlist__notice--%1$s">%2$s</div>',
				esc_attr( self::$result['type'] ),
				esc_html( self::$result['message'] )
			);
			// Em caso de sucesso, não mostra o formulário de novo (evita reenvio).
			if ( 'success' === self::$result['type'] ) {
				return ob_get_clean();
			}
		}
		?>
		<form class="dumont-waitlist-form" method="post" action="">
			<?php wp_nonce_field( 'dumont_waitlist_submit_' . $product_id, 'dumont_waitlist_nonce' ); ?>
			<input type="hidden" name="dumont_waitlist_product_id" value="<?php echo esc_attr( $product_id ); ?>">

			<?php /* Honeypot anti-spam: escondido via CSS; bots tendem a preencher. */ ?>
			<input type="text" name="dumont_waitlist_hp" value="" tabindex="-1" autocomplete="off" class="dumont-waitlist-form__hp" aria-hidden="true">

			<div class="dumont-waitlist-form__row">
				<label for="dwl-name-<?php echo $uid; ?>"><?php esc_html_e( 'Nome', 'dumont-product-waitlist' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" id="dwl-name-<?php echo $uid; ?>" name="dumont_waitlist_name" required>
			</div>

			<div class="dumont-waitlist-form__row">
				<label for="dwl-email-<?php echo $uid; ?>"><?php esc_html_e( 'E-mail', 'dumont-product-waitlist' ); ?> <span aria-hidden="true">*</span></label>
				<input type="email" id="dwl-email-<?php echo $uid; ?>" name="dumont_waitlist_email" required>
			</div>

			<?php if ( $store_wa ) : ?>
				<div class="dumont-waitlist-form__row">
					<label for="dwl-wa-<?php echo $uid; ?>"><?php esc_html_e( 'WhatsApp', 'dumont-product-waitlist' ); ?></label>
					<input type="tel" id="dwl-wa-<?php echo $uid; ?>" name="dumont_waitlist_whatsapp" placeholder="(00) 00000-0000">
				</div>
			<?php endif; ?>

			<div class="dumont-waitlist-form__row">
				<label for="dwl-msg-<?php echo $uid; ?>"><?php esc_html_e( 'Mensagem (opcional)', 'dumont-product-waitlist' ); ?></label>
				<textarea id="dwl-msg-<?php echo $uid; ?>" name="dumont_waitlist_message" rows="3"></textarea>
			</div>

			<button type="submit" name="dumont_waitlist_submit" value="1" class="dumont-waitlist-form__submit">
				<?php echo esc_html( $btn_text ); ?>
			</button>
		</form>
		<?php
		return ob_get_clean();
	}
}
