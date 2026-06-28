<?php
/**
 * Ativação do plugin — cria a tabela e grava as opções padrão.
 *
 * @package Dumont_Product_Waitlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Dumont_Waitlist_Activator
 */
class Dumont_Waitlist_Activator {

	/**
	 * Roda na ativação do plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		// Cria/atualiza a tabela própria.
		Dumont_Waitlist_Database::create_table();

		// Grava as configurações padrão (só se ainda não existirem).
		if ( false === get_option( 'dumont_waitlist_settings' ) ) {
			add_option( 'dumont_waitlist_settings', dumont_waitlist_default_settings() );
		}

		// Guarda a versão (útil para futuras migrações de schema).
		update_option( 'dumont_waitlist_db_version', DUMONT_WAITLIST_VERSION );
	}
}
