<?php
/**
 * Notificação por e-mail dos interessados.
 *
 * @package Dumont_Product_Waitlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Dumont_Waitlist_Notifier
 */
class Dumont_Waitlist_Notifier {

	/**
	 * Notifica todos os leads PENDENTES de um produto.
	 *
	 * Para cada lead: envia e-mail (wp_mail), marca como notificado, preenche
	 * notified_at e muda o status para "notified".
	 *
	 * @param int $product_id ID do produto.
	 * @return int Quantidade de leads notificados com sucesso.
	 */
	public static function notify_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return 0;
		}

		$leads = Dumont_Waitlist_Database::get_pending_by_product( $product_id );
		if ( empty( $leads ) ) {
			return 0;
		}

		$from_name    = dumont_waitlist_get_option( 'from_name' );
		$from_email   = dumont_waitlist_get_option( 'from_email' );
		$subject_tpl  = dumont_waitlist_get_option( 'email_subject' );
		$body_tpl     = dumont_waitlist_get_option( 'email_template' );

		// Cabeçalhos do e-mail.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $from_email && is_email( $from_email ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
		}

		// Dados do produto (iguais para todos os leads deste produto).
		$product_title = get_the_title( $product_id );
		$product_url   = get_permalink( $product_id );
		$site_name     = get_bloginfo( 'name' );

		$sent = 0;

		foreach ( $leads as $lead ) {
			// Placeholders — valores dinâmicos já escapados para uso em HTML.
			$replace = array(
				'{name}'          => esc_html( $lead->name ),
				'{product_title}' => esc_html( $product_title ),
				'{product_url}'   => esc_url( $product_url ),
				'{site_name}'     => esc_html( $site_name ),
			);

			$subject = strtr( (string) $subject_tpl, $replace );
			$body    = strtr( (string) $body_tpl, $replace );
			$body    = wpautop( $body ); // quebras de linha viram <p>/<br>.

			$ok = wp_mail( $lead->email, $subject, $body, $headers );

			if ( $ok ) {
				Dumont_Waitlist_Database::mark_notified( $lead->id );
				$sent++;
			}
		}

		return $sent;
	}
}
