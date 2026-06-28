<?php
/**
 * Camada de banco de dados — tabela própria wp_dumont_product_waitlist.
 *
 * Todas as queries usam $wpdb->prepare() / métodos seguros do $wpdb.
 *
 * @package Dumont_Product_Waitlist
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Dumont_Waitlist_Database
 */
class Dumont_Waitlist_Database {

	/**
	 * Nome completo da tabela (com prefixo).
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dumont_product_waitlist';
	}

	/**
	 * Cria/atualiza a tabela via dbDelta.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_title varchar(255) NOT NULL DEFAULT '',
			product_url varchar(255) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			whatsapp varchar(40) NOT NULL DEFAULT '',
			message text NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			notified_at datetime NULL DEFAULT NULL,
			created_at datetime NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY email (email),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insere um lead. Espera dados JÁ sanitizados.
	 *
	 * @param array $data Dados do lead.
	 * @return int|false ID inserido ou false em erro.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$row = array(
			'product_id'    => isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0,
			'product_title' => isset( $data['product_title'] ) ? (string) $data['product_title'] : '',
			'product_url'   => isset( $data['product_url'] ) ? (string) $data['product_url'] : '',
			'name'          => isset( $data['name'] ) ? (string) $data['name'] : '',
			'email'         => isset( $data['email'] ) ? (string) $data['email'] : '',
			'whatsapp'      => isset( $data['whatsapp'] ) ? (string) $data['whatsapp'] : '',
			'message'       => isset( $data['message'] ) ? (string) $data['message'] : '',
			'status'        => 'pending',
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$ok = $wpdb->insert( self::table_name(), $row, $formats );

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Verifica se já existe lead com este e-mail para este produto (evita duplicidade).
	 *
	 * @param string $email      E-mail.
	 * @param int    $product_id ID do produto.
	 * @return bool
	 */
	public static function email_exists_for_product( $email, $product_id ) {
		global $wpdb;
		$table = self::table_name();
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE email = %s AND product_id = %d",
				$email,
				absint( $product_id )
			)
		);
		return (int) $count > 0;
	}

	/**
	 * Leads pendentes de um produto (para notificar).
	 *
	 * @param int $product_id ID do produto.
	 * @return array
	 */
	public static function get_pending_by_product( $product_id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d AND status = %s ORDER BY created_at ASC",
				absint( $product_id ),
				'pending'
			)
		);
	}

	/**
	 * Busca um lead pelo ID.
	 *
	 * @param int $id ID do lead.
	 * @return object|null
	 */
	public static function get_lead( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
		);
	}

	/**
	 * Marca um lead como notificado.
	 *
	 * @param int $id ID do lead.
	 * @return int|false Linhas afetadas ou false.
	 */
	public static function mark_notified( $id ) {
		global $wpdb;
		$now = current_time( 'mysql' );
		return $wpdb->update(
			self::table_name(),
			array(
				'status'      => 'notified',
				'notified_at' => $now,
				'updated_at'  => $now,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Exclui um lead.
	 *
	 * @param int $id ID do lead.
	 * @return int|false
	 */
	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Monta cláusula WHERE + parâmetros a partir dos filtros.
	 *
	 * @param array $args Filtros (product_id, status, search).
	 * @return array [ where_sql, params[] ]
	 */
	protected static function build_where( $args ) {
		global $wpdb;
		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['product_id'] ) ) {
			$where   .= ' AND product_id = %d';
			$params[] = absint( $args['product_id'] );
		}
		if ( isset( $args['status'] ) && '' !== $args['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND ( name LIKE %s OR email LIKE %s OR whatsapp LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( $where, $params );
	}

	/**
	 * Lista leads com filtros, ordenação e paginação.
	 *
	 * @param array $args Argumentos.
	 * @return array
	 */
	public static function get_leads( $args = array() ) {
		global $wpdb;
		$table = self::table_name();

		$defaults = array(
			'product_id' => 0,
			'status'     => '',
			'search'     => '',
			'orderby'    => 'created_at',
			'order'      => 'DESC',
			'limit'      => 200,
			'offset'     => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		list( $where, $params ) = self::build_where( $args );

		// Whitelist de orderby/order (não vão pelo prepare — por isso o whitelist).
		$allowed_orderby = array( 'id', 'created_at', 'product_title', 'status', 'notified_at', 'name', 'email' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$sql      = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = absint( $args['limit'] );
		$params[] = absint( $args['offset'] );

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Conta leads (com os mesmos filtros).
	 *
	 * @param array $args Argumentos.
	 * @return int
	 */
	public static function count_leads( $args = array() ) {
		global $wpdb;
		$table = self::table_name();

		list( $where, $params ) = self::build_where( $args );

		$sql = "SELECT COUNT(*) FROM {$table} {$where}";

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Lista de produtos que têm leads (para o filtro do admin).
	 *
	 * @return array
	 */
	public static function get_products_with_leads() {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_results(
			"SELECT product_id, product_title, COUNT(*) AS total
			 FROM {$table}
			 GROUP BY product_id, product_title
			 ORDER BY product_title ASC"
		);
	}
}
