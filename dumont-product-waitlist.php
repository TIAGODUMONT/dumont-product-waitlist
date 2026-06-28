<?php
/**
 * Plugin Name:       Dumont Product Waitlist
 * Plugin URI:        https://dumontweb.com.br
 * Description:       Captura, armazena e notifica interessados em produtos esgotados — sem depender do WooCommerce. Use o shortcode [dumont_waitlist_form product_id="123"] ou a função dumont_waitlist_form( get_the_ID() ).
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * Author:            Dumont Web
 * Author URI:        https://dumontweb.com.br
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dumont-product-waitlist
 *
 * @package Dumont_Product_Waitlist
 */

// Impede acesso direto ao arquivo.
defined( 'ABSPATH' ) || exit;

/* ── Constantes ─────────────────────────────────────────────────────────── */
define( 'DUMONT_WAITLIST_VERSION', '1.0.0' );
define( 'DUMONT_WAITLIST_FILE', __FILE__ );
define( 'DUMONT_WAITLIST_DIR', plugin_dir_path( __FILE__ ) );
define( 'DUMONT_WAITLIST_URL', plugin_dir_url( __FILE__ ) );

/* ── Includes ───────────────────────────────────────────────────────────── */
require_once DUMONT_WAITLIST_DIR . 'includes/helpers.php';
require_once DUMONT_WAITLIST_DIR . 'includes/class-database.php';
require_once DUMONT_WAITLIST_DIR . 'includes/class-activator.php';
require_once DUMONT_WAITLIST_DIR . 'includes/class-form.php';
require_once DUMONT_WAITLIST_DIR . 'includes/class-notifier.php';
require_once DUMONT_WAITLIST_DIR . 'includes/class-admin.php';

/* ── Ativação: cria a tabela e as opções padrão ─────────────────────────── */
register_activation_hook( __FILE__, array( 'Dumont_Waitlist_Activator', 'activate' ) );

/**
 * Inicializa o plugin (shortcode, formulário, admin).
 *
 * @return void
 */
function dumont_waitlist_init() {
	Dumont_Waitlist_Form::init();

	if ( is_admin() ) {
		Dumont_Waitlist_Admin::init();
	}
}
add_action( 'plugins_loaded', 'dumont_waitlist_init' );

/**
 * Carrega os assets públicos (CSS/JS) no front-end.
 *
 * @return void
 */
function dumont_waitlist_enqueue_public() {
	wp_enqueue_style(
		'dumont-waitlist-public',
		DUMONT_WAITLIST_URL . 'assets/css/public.css',
		array(),
		DUMONT_WAITLIST_VERSION
	);
	wp_enqueue_script(
		'dumont-waitlist-public',
		DUMONT_WAITLIST_URL . 'assets/js/public.js',
		array(),
		DUMONT_WAITLIST_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'dumont_waitlist_enqueue_public' );
