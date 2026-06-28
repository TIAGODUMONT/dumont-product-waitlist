<?php
/**
 * Funções helper do plugin — usáveis no tema e internamente.
 *
 * @package Dumont_Product_Waitlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Configurações padrão do plugin.
 *
 * @return array
 */
function dumont_waitlist_default_settings() {
	return array(
		'from_email'      => get_option( 'admin_email' ),
		'from_name'       => get_bloginfo( 'name' ),
		'email_subject'   => 'A peça "{product_title}" voltou! 💛',
		'email_template'  => "Olá, {name}!\n\nBoa notícia: a peça \"{product_title}\" que você aguardava voltou ao estoque.\n\nVeja aqui: {product_url}\n\nCom carinho,\n{site_name}",
		'button_text'     => 'Avise-me quando chegar',
		'success_message' => 'Pronto! Você entrou na lista de espera. Avisaremos assim que a peça voltar. 💛',
		'error_message'   => 'Não foi possível registrar agora. Confira os campos e tente novamente.',
		'store_whatsapp'  => '1',
	);
}

/**
 * Devolve todas as configurações (salvas + defaults).
 *
 * @return array
 */
function dumont_waitlist_get_settings() {
	$saved = get_option( 'dumont_waitlist_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, dumont_waitlist_default_settings() );
}

/**
 * Devolve UMA configuração pelo nome.
 *
 * @param string $key     Chave da configuração.
 * @param string $default Valor padrão se não existir.
 * @return mixed
 */
function dumont_waitlist_get_option( $key, $default = '' ) {
	$settings = dumont_waitlist_get_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Renderiza o formulário de lista de espera (para usar no tema).
 *
 * Exemplo no template do produto:
 *   <?php echo dumont_waitlist_form( get_the_ID() ); ?>
 *
 * @param int $product_id ID do produto/post. Se 0, usa o post atual.
 * @return string HTML do formulário (já escapado).
 */
function dumont_waitlist_form( $product_id = 0 ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		$product_id = (int) get_the_ID();
	}
	return Dumont_Waitlist_Form::render( $product_id );
}

/**
 * Notifica (por e-mail) todos os interessados pendentes de um produto.
 *
 * @param int $product_id ID do produto/post.
 * @return int Quantidade de leads notificados.
 */
function dumont_notify_waitlist( $product_id ) {
	return Dumont_Waitlist_Notifier::notify_product( $product_id );
}
