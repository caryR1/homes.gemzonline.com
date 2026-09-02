<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAT_DB {

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'gat_' . $name;
	}

	public static function activate() {
		self::create_tables();
		self::seed_partners();
		GAT_Roles::add_role();
		update_option( 'gat_db_version', GAT_DB_VERSION );

		// Rewrite rule needs to exist before we flush.
		GAT_Redirect::add_rewrite_rule();
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'gat_db_version' ) !== GAT_DB_VERSION ) {
			self::create_tables();
			self::seed_partners();
			GAT_Roles::add_role();
			update_option( 'gat_db_version', GAT_DB_VERSION );
		}
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$partners = self::table( 'partners' );
		$codes    = self::table( 'codes' );
		$clicks   = self::table( 'clicks' );
		$payouts  = self::table( 'payouts' );

		$sql = "CREATE TABLE {$partners} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			slug VARCHAR(64) NOT NULL,
			name VARCHAR(191) NOT NULL,
			payout_type VARCHAR(20) NOT NULL DEFAULT 'flat',
			payout_amount DECIMAL(10,2) NULL,
			payout_percent DECIMAL(5,2) NULL,
			installments_json TEXT NULL,
			destination_url VARCHAR(500) NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};

		CREATE TABLE {$codes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code VARCHAR(64) NOT NULL,
			sub_affiliate_name VARCHAR(191) NOT NULL,
			partner_id BIGINT UNSIGNED NOT NULL,
			wp_user_id BIGINT UNSIGNED NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			cut_type VARCHAR(20) NOT NULL DEFAULT 'percent',
			cut_value DECIMAL(10,2) NOT NULL DEFAULT 0,
			active TINYINT(1) NOT NULL DEFAULT 1,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY partner_id (partner_id),
			KEY wp_user_id (wp_user_id)
		) {$charset_collate};

		CREATE TABLE {$clicks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code_id BIGINT UNSIGNED NOT NULL,
			code VARCHAR(64) NOT NULL,
			partner_id BIGINT UNSIGNED NULL,
			clicked_at DATETIME NOT NULL,
			ip_address VARCHAR(45) NULL,
			user_agent VARCHAR(255) NULL,
			PRIMARY KEY  (id),
			KEY code_id (code_id),
			KEY clicked_at (clicked_at)
		) {$charset_collate};

		CREATE TABLE {$payouts} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code_id BIGINT UNSIGNED NOT NULL,
			code VARCHAR(64) NOT NULL,
			partner_id BIGINT UNSIGNED NOT NULL,
			sale_amount DECIMAL(10,2) NOT NULL,
			installment_label VARCHAR(191) NULL,
			gross_commission DECIMAL(10,2) NOT NULL,
			subaffiliate_cut DECIMAL(10,2) NOT NULL,
			net_to_cary DECIMAL(10,2) NOT NULL,
			entered_at DATETIME NOT NULL,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			KEY code_id (code_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Seed the three known fulfillment partners if they don't already exist.
	 * Destination URLs are left blank until real affiliate links are approved.
	 */
	private static function seed_partners() {
		global $wpdb;
		$table = self::table( 'partners' );
		$now   = current_time( 'mysql' );

		$defaults = array(
			array(
				'slug'              => 'smarter-tiny-homes',
				'name'              => 'Smarter Tiny Homes',
				'payout_type'       => 'flat',
				'payout_amount'     => 500.00,
				'payout_percent'    => null,
				'installments_json' => null,
				'notes'             => 'Flat $500 per sale, single payment.',
			),
			array(
				'slug'              => 'craftsman-tiny-homes',
				'name'              => 'Craftsman Tiny Homes',
				'payout_type'       => 'percent',
				'payout_amount'     => null,
				'payout_percent'    => 8.00,
				'installments_json' => wp_json_encode( array(
					array( 'label' => 'Deposit / Signing', 'fraction' => 0.5 ),
					array( 'label' => 'Delivery', 'fraction' => 0.5 ),
				) ),
				'notes'             => '8% total, paid as two 4% installments: deposit/signing and delivery.',
			),
			array(
				'slug'              => 'connecticut-tiny-homes',
				'name'              => 'Connecticut Tiny Homes (ADU builder)',
				'payout_type'       => 'flat',
				'payout_amount'     => 3000.00,
				'payout_percent'    => null,
				'installments_json' => null,
				'notes'             => 'Flat $3,000 per successful referral, single payment.',
			),
		);

		foreach ( $defaults as $partner ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s", $partner['slug'] )
			);
			if ( $exists ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'slug'              => $partner['slug'],
					'name'              => $partner['name'],
					'payout_type'       => $partner['payout_type'],
					'payout_amount'     => $partner['payout_amount'],
					'payout_percent'    => $partner['payout_percent'],
					'installments_json' => $partner['installments_json'],
					'destination_url'   => '',
					'notes'             => $partner['notes'],
					'created_at'        => $now,
				)
			);
		}
	}
}
