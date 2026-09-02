<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAT_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_gat_save_partner', array( __CLASS__, 'handle_save_partner' ) );
		add_action( 'admin_post_gat_save_code', array( __CLASS__, 'handle_save_code' ) );
		add_action( 'admin_post_gat_delete_code', array( __CLASS__, 'handle_delete_code' ) );
		add_action( 'admin_post_gat_calculate_payout', array( __CLASS__, 'handle_calculate_payout' ) );
		add_action( 'admin_post_gat_delete_payout', array( __CLASS__, 'handle_delete_payout' ) );
		add_action( 'admin_post_gat_approve_affiliate', array( __CLASS__, 'handle_approve_affiliate' ) );
		add_action( 'admin_post_gat_reject_affiliate', array( __CLASS__, 'handle_reject_affiliate' ) );
	}

	public static function add_menu() {
		add_menu_page(
			'Affiliate Tracker',
			'Affiliate Tracker',
			self::CAP,
			'gat-affiliates',
			array( __CLASS__, 'render_affiliates_page' ),
			'dashicons-randomize',
			58
		);
		add_submenu_page( 'gat-affiliates','Affiliates', 'Affiliates', self::CAP, 'gat-affiliates', array( __CLASS__, 'render_affiliates_page' ) );
		add_submenu_page( 'gat-affiliates','Sub-Affiliate Codes', 'Codes', self::CAP, 'gat-codes', array( __CLASS__, 'render_codes_page' ) );
		add_submenu_page( 'gat-affiliates','Partners', 'Partners', self::CAP, 'gat-partners', array( __CLASS__, 'render_partners_page' ) );
		add_submenu_page( 'gat-affiliates','Click Log', 'Click Log', self::CAP, 'gat-clicks', array( __CLASS__, 'render_clicks_page' ) );
		add_submenu_page( 'gat-affiliates','Payout Calculator', 'Payout Calculator', self::CAP, 'gat-calculator', array( __CLASS__, 'render_calculator_page' ) );
		add_submenu_page( 'gat-affiliates','Payout Ledger', 'Payout Ledger', self::CAP, 'gat-ledger', array( __CLASS__, 'render_ledger_page' ) );
	}

	private static function wrap_start( $title ) {
		echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	}

	private static function wrap_end() {
		echo '</div>';
	}

	private static function get_partners() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . GAT_DB::table( 'partners' ) . ' ORDER BY name ASC' );
	}

	private static function get_partner( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GAT_DB::table( 'partners' ) . ' WHERE id = %d', $id ) );
	}

	private static function get_codes() {
		global $wpdb;
		$codes_table    = GAT_DB::table( 'codes' );
		$partners_table = GAT_DB::table( 'partners' );
		return $wpdb->get_results(
			"SELECT c.*, p.name AS partner_name FROM {$codes_table} c
			 LEFT JOIN {$partners_table} p ON p.id = c.partner_id
			 ORDER BY c.created_at DESC"
		);
	}

	private static function get_code( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . GAT_DB::table( 'codes' ) . ' WHERE id = %d', $id ) );
	}

	/* ---------------------------------------------------------------- *
	 * AFFILIATES (self-signups)
	 * ---------------------------------------------------------------- */

	public static function render_affiliates_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		self::wrap_start( 'Affiliates' );

		if ( isset( $_GET['approved'] ) ) {
			echo '<div class="notice notice-success"><p>Affiliate approved &mdash; their link is now live.</p></div>';
		}
		if ( isset( $_GET['rejected'] ) ) {
			echo '<div class="notice notice-success"><p>Affiliate rejected.</p></div>';
		}

		global $wpdb;
		$codes_table    = GAT_DB::table( 'codes' );
		$partners_table = GAT_DB::table( 'partners' );

		$rows = $wpdb->get_results(
			"SELECT c.*, p.name AS partner_name FROM {$codes_table} c
			 LEFT JOIN {$partners_table} p ON p.id = c.partner_id
			 WHERE c.wp_user_id IS NOT NULL
			 ORDER BY (c.status = 'pending') DESC, c.created_at DESC"
		);

		if ( ! $rows ) {
			echo '<p>No self-signup affiliates yet. New signups from the "Become an Affiliate" page will show up here.</p>';
			self::wrap_end();
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Email</th><th>Partner</th><th>Code</th><th>Status</th><th>Payment info</th><th>Set cut &amp; action</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$user = get_userdata( $r->wp_user_id );
			$email = $user ? $user->user_email : '(deleted user)';
			$payment = $r->wp_user_id ? get_user_meta( $r->wp_user_id, 'gat_payment_details', true ) : '';

			echo '<tr>';
			echo '<td>' . esc_html( $r->sub_affiliate_name ) . '</td>';
			echo '<td>' . esc_html( $email ) . '</td>';
			echo '<td>' . esc_html( $r->partner_name ) . '</td>';
			echo '<td><code>' . esc_html( $r->code ) . '</code></td>';
			echo '<td>' . esc_html( $r->status ) . '</td>';
			echo '<td>' . ( $payment ? esc_html( $payment ) : '<em>not set</em>' ) . '</td>';
			echo '<td>';

			if ( 'pending' === $r->status ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:6px;">';
				wp_nonce_field( 'gat_approve_affiliate_' . $r->id );
				echo '<input type="hidden" name="action" value="gat_approve_affiliate">';
				echo '<input type="hidden" name="id" value="' . esc_attr( $r->id ) . '">';
				echo '<select name="cut_type" style="width:auto;display:inline-block;">';
				echo '<option value="percent">%</option><option value="flat">$ flat</option>';
				echo '</select> ';
				echo '<input type="number" step="0.01" min="0" name="cut_value" placeholder="e.g. 50" style="width:80px;" required> ';
				echo '<button type="submit" class="button button-primary button-small">Approve</button>';
				echo '</form>';

				$reject_url = wp_nonce_url( admin_url( 'admin-post.php?action=gat_reject_affiliate&id=' . $r->id ), 'gat_reject_affiliate_' . $r->id );
				echo '<a href="' . esc_url( $reject_url ) . '" class="button button-small" onclick="return confirm(\'Reject this affiliate?\');">Reject</a>';
			} else {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=gat-codes&edit=' . $r->id ) ) . '">Edit code / rate</a>';
			}

			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		self::wrap_end();
	}

	public static function handle_approve_affiliate() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'gat_approve_affiliate_' . $id );

		$cut_type  = isset( $_POST['cut_type'] ) && 'flat' === $_POST['cut_type'] ? 'flat' : 'percent';
		$cut_value = isset( $_POST['cut_value'] ) ? (float) $_POST['cut_value'] : 0;

		$code = self::get_code( $id );
		if ( ! $code ) {
			wp_die( 'Code not found.' );
		}

		global $wpdb;
		$wpdb->update(
			GAT_DB::table( 'codes' ),
			array(
				'status'    => 'active',
				'active'    => 1,
				'cut_type'  => $cut_type,
				'cut_value' => $cut_value,
			),
			array( 'id' => $id )
		);

		if ( $code->wp_user_id ) {
			update_user_meta( $code->wp_user_id, 'gat_status', 'active' );
			$user = get_userdata( $code->wp_user_id );
			if ( $user ) {
				wp_mail(
					$user->user_email,
					'Your affiliate account is approved',
					"Good news, {$user->display_name} — your affiliate account has been approved and your link is live.\n\nLog in to your dashboard to see your link and stats."
				);
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=gat-affiliates&approved=1' ) );
		exit;
	}

	public static function handle_reject_affiliate() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'gat_reject_affiliate_' . $id );

		$code = self::get_code( $id );
		if ( ! $code ) {
			wp_die( 'Code not found.' );
		}

		global $wpdb;
		$wpdb->update(
			GAT_DB::table( 'codes' ),
			array( 'status' => 'rejected', 'active' => 0 ),
			array( 'id' => $id )
		);

		if ( $code->wp_user_id ) {
			update_user_meta( $code->wp_user_id, 'gat_status', 'rejected' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=gat-affiliates&rejected=1' ) );
		exit;
	}

	/* ---------------------------------------------------------------- *
	 * CODES (sub-affiliates)
	 * ---------------------------------------------------------------- */

	public static function render_codes_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing = $edit_id ? self::get_code( $edit_id ) : null;
		$partners = self::get_partners();

		self::wrap_start( 'Sub-Affiliate Codes' );

		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success"><p>Saved.</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success"><p>Deleted.</p></div>';
		}

		echo '<h2>' . ( $editing ? 'Edit Code' : 'Add a Code' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'gat_save_code' );
		echo '<input type="hidden" name="action" value="gat_save_code">';
		if ( $editing ) {
			echo '<input type="hidden" name="id" value="' . esc_attr( $editing->id ) . '">';
		}
		echo '<table class="form-table"><tbody>';

		echo '<tr><th><label for="code">Code</label></th><td><input type="text" id="code" name="code" class="regular-text" required pattern="[a-zA-Z0-9_-]+" value="' . esc_attr( $editing->code ?? '' ) . '"> <p class="description">Used in the link as gemzonline.com/go/{code}. Letters, numbers, hyphens, underscores only.</p></td></tr>';

		echo '<tr><th><label for="sub_affiliate_name">Sub-affiliate name</label></th><td><input type="text" id="sub_affiliate_name" name="sub_affiliate_name" class="regular-text" required value="' . esc_attr( $editing->sub_affiliate_name ?? '' ) . '"></td></tr>';

		echo '<tr><th><label for="partner_id">Partner</label></th><td><select id="partner_id" name="partner_id" required>';
		echo '<option value="">-- select --</option>';
		foreach ( $partners as $p ) {
			$sel = ( $editing && (int) $editing->partner_id === (int) $p->id ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $p->id ) . '"' . $sel . '>' . esc_html( $p->name ) . '</option>';
		}
		echo '</select> <p class="description">Which partner this sub-affiliate\'s traffic goes to.</p></td></tr>';

		$cut_type = $editing->cut_type ?? 'percent';
		echo '<tr><th>Sub-affiliate cut</th><td>';
		echo '<select name="cut_type">';
		echo '<option value="percent"' . selected( $cut_type, 'percent', false ) . '>Percent of commission</option>';
		echo '<option value="flat"' . selected( $cut_type, 'flat', false ) . '>Flat dollar amount per sale</option>';
		echo '</select> ';
		echo '<input type="number" step="0.01" min="0" name="cut_value" value="' . esc_attr( $editing->cut_value ?? '' ) . '" placeholder="e.g. 50 for 50%, or 25.00 for $25 flat"> ';
		echo '<p class="description">This is what YOU owe the sub-affiliate, out of your own commission, per sale attributed to this code. Rates can differ per partner &mdash; add a separate code per partner if the same person promotes more than one.</p>';
		echo '</td></tr>';

		echo '<tr><th><label for="active">Active</label></th><td><label><input type="checkbox" id="active" name="active" value="1"' . checked( $editing->active ?? 1, 1, false ) . '> Redirect and log clicks for this code</label></td></tr>';

		echo '<tr><th><label for="notes">Notes</label></th><td><textarea id="notes" name="notes" class="large-text" rows="2">' . esc_textarea( $editing->notes ?? '' ) . '</textarea></td></tr>';

		echo '</tbody></table>';
		submit_button( $editing ? 'Update Code' : 'Add Code' );
		echo '</form>';

		if ( $editing ) {
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=gat-codes' ) ) . '">&larr; Cancel edit</a></p>';
		}

		echo '<h2>Existing Codes</h2>';
		$codes = self::get_codes();
		if ( ! $codes ) {
			echo '<p>No codes yet.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Link</th><th>Sub-affiliate</th><th>Partner</th><th>Cut</th><th>Active</th><th>Actions</th></tr></thead><tbody>';
			foreach ( $codes as $c ) {
				$link = home_url( '/go/' . rawurlencode( $c->code ) . '/' );
				$cut  = 'percent' === $c->cut_type ? esc_html( $c->cut_value ) . '%' : '$' . esc_html( number_format( (float) $c->cut_value, 2 ) ) . ' flat';
				echo '<tr>';
				echo '<td><code>' . esc_html( $c->code ) . '</code></td>';
				echo '<td><code>' . esc_html( $link ) . '</code></td>';
				echo '<td>' . esc_html( $c->sub_affiliate_name ) . '</td>';
				echo '<td>' . esc_html( $c->partner_name ?: '(none)' ) . '</td>';
				echo '<td>' . $cut . '</td>';
				echo '<td>' . ( $c->active ? 'Yes' : 'No' ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=gat-codes&edit=' . $c->id ) ) . '">Edit</a> | ';
				$del_url = wp_nonce_url( admin_url( 'admin-post.php?action=gat_delete_code&id=' . $c->id ), 'gat_delete_code_' . $c->id );
				echo '<a href="' . esc_url( $del_url ) . '" onclick="return confirm(\'Delete this code? Click history stays but will no longer link to a code.\');">Delete</a>';
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		self::wrap_end();
	}

	public static function handle_save_code() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'gat_save_code' );

		global $wpdb;
		$table = GAT_DB::table( 'codes' );

		$id                 = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$code               = isset( $_POST['code'] ) ? sanitize_title( wp_unslash( $_POST['code'] ) ) : '';
		$sub_affiliate_name = isset( $_POST['sub_affiliate_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sub_affiliate_name'] ) ) : '';
		$partner_id         = isset( $_POST['partner_id'] ) ? absint( $_POST['partner_id'] ) : 0;
		$cut_type           = isset( $_POST['cut_type'] ) && 'flat' === $_POST['cut_type'] ? 'flat' : 'percent';
		$cut_value          = isset( $_POST['cut_value'] ) ? (float) $_POST['cut_value'] : 0;
		$active             = isset( $_POST['active'] ) ? 1 : 0;
		$notes              = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		$data = array(
			'code'               => $code,
			'sub_affiliate_name' => $sub_affiliate_name,
			'partner_id'         => $partner_id,
			'cut_type'           => $cut_type,
			'cut_value'          => $cut_value,
			'active'             => $active,
			'notes'              => $notes,
		);

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=gat-codes&saved=1' ) );
		exit;
	}

	public static function handle_delete_code() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'gat_delete_code_' . $id );

		global $wpdb;
		$wpdb->delete( GAT_DB::table( 'codes' ), array( 'id' => $id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=gat-codes&deleted=1' ) );
		exit;
	}

	/* ---------------------------------------------------------------- *
	 * PARTNERS
	 * ---------------------------------------------------------------- */

	public static function render_partners_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing  = $edit_id ? self::get_partner( $edit_id ) : null;
		$partners = self::get_partners();

		self::wrap_start( 'Partners' );

		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success"><p>Saved.</p></div>';
		}

		if ( $editing ) {
			echo '<h2>Edit Partner: ' . esc_html( $editing->name ) . '</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'gat_save_partner' );
			echo '<input type="hidden" name="action" value="gat_save_partner">';
			echo '<input type="hidden" name="id" value="' . esc_attr( $editing->id ) . '">';
			echo '<table class="form-table"><tbody>';

			echo '<tr><th>Name</th><td><input type="text" name="name" class="regular-text" required value="' . esc_attr( $editing->name ) . '"></td></tr>';

			echo '<tr><th>Payout type</th><td><select name="payout_type" id="payout_type">';
			echo '<option value="flat"' . selected( $editing->payout_type, 'flat', false ) . '>Flat amount per sale</option>';
			echo '<option value="percent"' . selected( $editing->payout_type, 'percent', false ) . '>Percent of sale</option>';
			echo '</select></td></tr>';

			echo '<tr><th>Flat amount ($)</th><td><input type="number" step="0.01" min="0" name="payout_amount" value="' . esc_attr( $editing->payout_amount ?? '' ) . '"> <p class="description">Used only if payout type is Flat.</p></td></tr>';

			echo '<tr><th>Percent (%)</th><td><input type="number" step="0.01" min="0" max="100" name="payout_percent" value="' . esc_attr( $editing->payout_percent ?? '' ) . '"> <p class="description">Used only if payout type is Percent, e.g. 8 for 8%.</p></td></tr>';

			$installments = $editing->installments_json ? json_decode( $editing->installments_json, true ) : array();
			$inst1_label  = $installments[0]['label'] ?? '';
			$inst1_frac   = isset( $installments[0]['fraction'] ) ? $installments[0]['fraction'] * 100 : '';
			$inst2_label  = $installments[1]['label'] ?? '';
			$inst2_frac   = isset( $installments[1]['fraction'] ) ? $installments[1]['fraction'] * 100 : '';

			echo '<tr><th>Installments</th><td>';
			echo '<p class="description">Optional. If this partner pays the percent commission in stages (e.g. deposit then delivery), define up to two here. Leave blank for a single full payment.</p>';
			echo 'Installment 1 label: <input type="text" name="inst1_label" value="' . esc_attr( $inst1_label ) . '" placeholder="e.g. Deposit / Signing"> ';
			echo 'is <input type="number" step="0.01" min="0" max="100" name="inst1_pct" value="' . esc_attr( $inst1_frac ) . '" style="width:80px"> % of the total commission<br><br>';
			echo 'Installment 2 label: <input type="text" name="inst2_label" value="' . esc_attr( $inst2_label ) . '" placeholder="e.g. Delivery"> ';
			echo 'is <input type="number" step="0.01" min="0" max="100" name="inst2_pct" value="' . esc_attr( $inst2_frac ) . '" style="width:80px"> % of the total commission';
			echo '</td></tr>';

			echo '<tr><th>Destination URL</th><td><input type="url" name="destination_url" class="regular-text" value="' . esc_attr( $editing->destination_url ?? '' ) . '" placeholder="https://... (your real affiliate tracking link with this partner)"> <p class="description">Leave blank until the affiliate application is approved &mdash; codes for this partner will redirect visitors to the homepage in the meantime, but clicks still get logged.</p></td></tr>';

			echo '<tr><th>Notes</th><td><textarea name="notes" class="large-text" rows="3">' . esc_textarea( $editing->notes ?? '' ) . '</textarea></td></tr>';

			echo '</tbody></table>';
			submit_button( 'Update Partner' );
			echo '</form>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=gat-partners' ) ) . '">&larr; Back to partner list</a></p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Payout structure</th><th>Destination URL</th><th>Actions</th></tr></thead><tbody>';
			foreach ( $partners as $p ) {
				if ( 'flat' === $p->payout_type ) {
					$structure = '$' . number_format( (float) $p->payout_amount, 2 ) . ' flat';
				} else {
					$structure = esc_html( $p->payout_percent ) . '%';
					$inst = $p->installments_json ? json_decode( $p->installments_json, true ) : array();
					if ( $inst ) {
						$parts = array();
						foreach ( $inst as $i ) {
							$parts[] = esc_html( $i['label'] ) . ' (' . round( $i['fraction'] * 100, 2 ) . '%)';
						}
						$structure .= ' &mdash; ' . implode( ', ', $parts );
					}
				}
				echo '<tr>';
				echo '<td>' . esc_html( $p->name ) . '</td>';
				echo '<td>' . $structure . '</td>';
				echo '<td>' . ( $p->destination_url ? '<code>' . esc_html( $p->destination_url ) . '</code>' : '<em>not set yet</em>' ) . '</td>';
				echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=gat-partners&edit=' . $p->id ) ) . '">Edit</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}

		self::wrap_end();
	}

	public static function handle_save_partner() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'gat_save_partner' );

		global $wpdb;
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_die( 'Missing partner id.' );
		}

		$installments = array();
		if ( ! empty( $_POST['inst1_label'] ) && '' !== $_POST['inst1_pct'] ) {
			$installments[] = array(
				'label'    => sanitize_text_field( wp_unslash( $_POST['inst1_label'] ) ),
				'fraction' => (float) $_POST['inst1_pct'] / 100,
			);
		}
		if ( ! empty( $_POST['inst2_label'] ) && '' !== $_POST['inst2_pct'] ) {
			$installments[] = array(
				'label'    => sanitize_text_field( wp_unslash( $_POST['inst2_label'] ) ),
				'fraction' => (float) $_POST['inst2_pct'] / 100,
			);
		}

		$data = array(
			'name'              => sanitize_text_field( wp_unslash( $_POST['name'] ) ),
			'payout_type'       => 'percent' === $_POST['payout_type'] ? 'percent' : 'flat',
			'payout_amount'     => '' !== $_POST['payout_amount'] ? (float) $_POST['payout_amount'] : null,
			'payout_percent'    => '' !== $_POST['payout_percent'] ? (float) $_POST['payout_percent'] : null,
			'installments_json' => $installments ? wp_json_encode( $installments ) : null,
			'destination_url'   => isset( $_POST['destination_url'] ) ? esc_url_raw( wp_unslash( $_POST['destination_url'] ) ) : '',
			'notes'             => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		$wpdb->update( GAT_DB::table( 'partners' ), $data, array( 'id' => $id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=gat-partners&saved=1' ) );
		exit;
	}

	/* ---------------------------------------------------------------- *
	 * CLICK LOG
	 * ---------------------------------------------------------------- */

	public static function render_clicks_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		global $wpdb;
		self::wrap_start( 'Click Log' );

		$clicks_table   = GAT_DB::table( 'clicks' );
		$partners_table = GAT_DB::table( 'partners' );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$clicks_table}" );
		$per_page = 50;
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cl.*, p.name AS partner_name FROM {$clicks_table} cl
				 LEFT JOIN {$partners_table} p ON p.id = cl.partner_id
				 ORDER BY cl.clicked_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		echo '<p>' . esc_html( $total ) . ' total clicks logged.</p>';

		if ( ! $rows ) {
			echo '<p>No clicks yet.</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>Date/Time</th><th>Code</th><th>Partner</th><th>IP</th><th>User Agent</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr>';
				echo '<td>' . esc_html( $r->clicked_at ) . '</td>';
				echo '<td><code>' . esc_html( $r->code ) . '</code></td>';
				echo '<td>' . esc_html( $r->partner_name ?: '&mdash;' ) . '</td>';
				echo '<td>' . esc_html( $r->ip_address ) . '</td>';
				echo '<td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . esc_html( $r->user_agent ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';

			$total_pages = (int) ceil( $total / $per_page );
			if ( $total_pages > 1 ) {
				echo '<p>';
				for ( $i = 1; $i <= $total_pages; $i++ ) {
					if ( $i === $paged ) {
						echo '<strong>' . $i . '</strong> ';
					} else {
						echo '<a href="' . esc_url( admin_url( 'admin.php?page=gat-clicks&paged=' . $i ) ) . '">' . $i . '</a> ';
					}
				}
				echo '</p>';
			}
		}

		self::wrap_end();
	}

	/* ---------------------------------------------------------------- *
	 * PAYOUT CALCULATOR
	 * ---------------------------------------------------------------- */

	public static function render_calculator_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		self::wrap_start( 'Payout Calculator' );

		$codes = self::get_codes();

		if ( isset( $_GET['result'] ) ) {
			$result = get_transient( 'gat_calc_result_' . get_current_user_id() );
			if ( $result ) {
				echo '<div class="notice notice-success"><h2 style="margin-top:0">Result</h2>';
				echo '<p><strong>Gross commission (yours from the partner):</strong> $' . esc_html( number_format( $result['gross'], 2 ) ) . '</p>';
				echo '<p><strong>Sub-affiliate cut:</strong> $' . esc_html( number_format( $result['cut'], 2 ) ) . '</p>';
				echo '<p><strong>Net to you:</strong> $' . esc_html( number_format( $result['net'], 2 ) ) . '</p>';
				if ( ! empty( $result['saved'] ) ) {
					echo '<p>Saved to the <a href="' . esc_url( admin_url( 'admin.php?page=gat-ledger' ) ) . '">Payout Ledger</a>.</p>';
				}
				echo '</div>';
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'gat_calculate_payout' );
		echo '<input type="hidden" name="action" value="gat_calculate_payout">';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th>Code</th><td><select name="code_id" id="code_id" required onchange="gatUpdateInstallments()">';
		echo '<option value="">-- select a code --</option>';
		foreach ( $codes as $c ) {
			echo '<option value="' . esc_attr( $c->id ) . '" data-partner="' . esc_attr( $c->partner_id ) . '">' . esc_html( $c->code . ' — ' . $c->sub_affiliate_name . ' (' . $c->partner_name . ')' ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th>Sale amount ($)</th><td><input type="number" step="0.01" min="0" name="sale_amount" required></td></tr>';

		echo '<tr><th>Installment</th><td><select name="installment_index" id="installment_index"><option value="">Full amount / single payment</option></select> <p class="description">Only matters for partners paid in stages (e.g. Craftsman: deposit then delivery). Choose which payment this is.</p></td></tr>';

		echo '<tr><th>Notes</th><td><textarea name="notes" class="large-text" rows="2" placeholder="optional"></textarea></td></tr>';

		echo '</tbody></table>';
		echo '<p><button type="submit" name="save" value="0" class="button">Calculate only</button> ';
		echo '<button type="submit" name="save" value="1" class="button button-primary">Calculate &amp; save to ledger</button></p>';
		echo '</form>';

		// Inline data + tiny script to populate installment choices per selected code's partner.
		$partner_installments = array();
		foreach ( self::get_partners() as $p ) {
			$partner_installments[ $p->id ] = $p->installments_json ? json_decode( $p->installments_json, true ) : array();
		}
		echo '<script>
			var gatPartnerInstallments = ' . wp_json_encode( $partner_installments ) . ';
			function gatUpdateInstallments() {
				var codeSel = document.getElementById("code_id");
				var partnerId = codeSel.options[codeSel.selectedIndex] ? codeSel.options[codeSel.selectedIndex].getAttribute("data-partner") : null;
				var instSel = document.getElementById("installment_index");
				instSel.innerHTML = "<option value=\"\">Full amount / single payment</option>";
				if (partnerId && gatPartnerInstallments[partnerId] && gatPartnerInstallments[partnerId].length) {
					gatPartnerInstallments[partnerId].forEach(function(inst, i) {
						var opt = document.createElement("option");
						opt.value = i;
						opt.textContent = inst.label + " (" + (inst.fraction * 100) + "%)";
						instSel.appendChild(opt);
					});
				}
			}
		</script>';

		self::wrap_end();
	}

	public static function handle_calculate_payout() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'gat_calculate_payout' );

		$code_id            = isset( $_POST['code_id'] ) ? absint( $_POST['code_id'] ) : 0;
		$sale_amount        = isset( $_POST['sale_amount'] ) ? (float) $_POST['sale_amount'] : 0;
		$installment_index  = isset( $_POST['installment_index'] ) && '' !== $_POST['installment_index'] ? absint( $_POST['installment_index'] ) : null;
		$notes              = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$save               = ! empty( $_POST['save'] );

		$code = self::get_code( $code_id );
		if ( ! $code ) {
			wp_die( 'Code not found.' );
		}
		$partner = self::get_partner( $code->partner_id );
		if ( ! $partner ) {
			wp_die( 'Partner not found.' );
		}

		$installment_label = null;

		if ( 'flat' === $partner->payout_type ) {
			$gross = (float) $partner->payout_amount;
		} else {
			$installments = $partner->installments_json ? json_decode( $partner->installments_json, true ) : array();
			if ( $installments && null !== $installment_index && isset( $installments[ $installment_index ] ) ) {
				$fraction           = (float) $installments[ $installment_index ]['fraction'];
				$installment_label  = $installments[ $installment_index ]['label'];
				$gross              = $sale_amount * ( (float) $partner->payout_percent / 100 ) * $fraction;
			} else {
				$gross = $sale_amount * ( (float) $partner->payout_percent / 100 );
			}
		}

		if ( 'flat' === $code->cut_type ) {
			$cut = (float) $code->cut_value;
		} else {
			$cut = $gross * ( (float) $code->cut_value / 100 );
		}

		$net = $gross - $cut;

		$result = array(
			'gross' => $gross,
			'cut'   => $cut,
			'net'   => $net,
			'saved' => false,
		);

		if ( $save ) {
			global $wpdb;
			$wpdb->insert(
				GAT_DB::table( 'payouts' ),
				array(
					'code_id'            => $code->id,
					'code'               => $code->code,
					'partner_id'         => $partner->id,
					'sale_amount'        => $sale_amount,
					'installment_label'  => $installment_label,
					'gross_commission'   => $gross,
					'subaffiliate_cut'   => $cut,
					'net_to_cary'        => $net,
					'entered_at'         => current_time( 'mysql' ),
					'notes'              => $notes,
				)
			);
			$result['saved'] = true;
		}

		set_transient( 'gat_calc_result_' . get_current_user_id(), $result, 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=gat-calculator&result=1' ) );
		exit;
	}

	/* ---------------------------------------------------------------- *
	 * PAYOUT LEDGER
	 * ---------------------------------------------------------------- */

	public static function render_ledger_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		global $wpdb;
		self::wrap_start( 'Payout Ledger' );

		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success"><p>Entry deleted.</p></div>';
		}

		$payouts_table  = GAT_DB::table( 'payouts' );
		$partners_table = GAT_DB::table( 'partners' );

		$rows = $wpdb->get_results(
			"SELECT pay.*, p.name AS partner_name FROM {$payouts_table} pay
			 LEFT JOIN {$partners_table} p ON p.id = pay.partner_id
			 ORDER BY pay.entered_at DESC"
		);

		if ( ! $rows ) {
			echo '<p>No payouts recorded yet. Use the <a href="' . esc_url( admin_url( 'admin.php?page=gat-calculator' ) ) . '">Payout Calculator</a> and save a result to start the ledger.</p>';
		} else {
			$total_gross = 0;
			$total_cut   = 0;
			$total_net   = 0;
			echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Code</th><th>Partner</th><th>Sale</th><th>Installment</th><th>Gross</th><th>Sub-affiliate cut</th><th>Net to you</th><th>Notes</th><th></th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$total_gross += (float) $r->gross_commission;
				$total_cut   += (float) $r->subaffiliate_cut;
				$total_net   += (float) $r->net_to_cary;
				echo '<tr>';
				echo '<td>' . esc_html( $r->entered_at ) . '</td>';
				echo '<td><code>' . esc_html( $r->code ) . '</code></td>';
				echo '<td>' . esc_html( $r->partner_name ) . '</td>';
				echo '<td>$' . esc_html( number_format( (float) $r->sale_amount, 2 ) ) . '</td>';
				echo '<td>' . esc_html( $r->installment_label ?: '&mdash;' ) . '</td>';
				echo '<td>$' . esc_html( number_format( (float) $r->gross_commission, 2 ) ) . '</td>';
				echo '<td>$' . esc_html( number_format( (float) $r->subaffiliate_cut, 2 ) ) . '</td>';
				echo '<td>$' . esc_html( number_format( (float) $r->net_to_cary, 2 ) ) . '</td>';
				echo '<td>' . esc_html( $r->notes ) . '</td>';
				$del_url = wp_nonce_url( admin_url( 'admin-post.php?action=gat_delete_payout&id=' . $r->id ), 'gat_delete_payout_' . $r->id );
				echo '<td><a href="' . esc_url( $del_url ) . '" onclick="return confirm(\'Delete this ledger entry?\');">Delete</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '<h2>Totals</h2>';
			echo '<p><strong>Total gross commission:</strong> $' . esc_html( number_format( $total_gross, 2 ) ) . '<br>';
			echo '<strong>Total owed to sub-affiliates:</strong> $' . esc_html( number_format( $total_cut, 2 ) ) . '<br>';
			echo '<strong>Total net to you:</strong> $' . esc_html( number_format( $total_net, 2 ) ) . '</p>';
		}

		self::wrap_end();
	}

	public static function handle_delete_payout() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Not allowed.' );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'gat_delete_payout_' . $id );

		global $wpdb;
		$wpdb->delete( GAT_DB::table( 'payouts' ), array( 'id' => $id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=gat-ledger&deleted=1' ) );
		exit;
	}
}
