<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GAT_Frontend {

	public static function init() {
		add_shortcode( 'gat_affiliate_signup', array( __CLASS__, 'render_signup' ) );
		add_shortcode( 'gat_affiliate_dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'init', array( __CLASS__, 'maybe_create_pages' ) );
		add_action( 'admin_post_gat_affiliate_signup', array( __CLASS__, 'handle_signup' ) );
		add_action( 'admin_post_nopriv_gat_affiliate_signup', array( __CLASS__, 'handle_signup' ) );
		add_action( 'admin_post_gat_affiliate_login', array( __CLASS__, 'handle_login' ) );
		add_action( 'admin_post_nopriv_gat_affiliate_login', array( __CLASS__, 'handle_login' ) );
		add_action( 'admin_post_gat_change_password', array( __CLASS__, 'handle_change_password' ) );
		add_action( 'admin_post_gat_save_payment_info', array( __CLASS__, 'handle_save_payment_info' ) );
	}

	public static function enqueue_assets() {
		if ( is_singular() ) {
			global $post;
			if ( $post && ( has_shortcode( $post->post_content, 'gat_affiliate_signup' ) || has_shortcode( $post->post_content, 'gat_affiliate_dashboard' ) ) ) {
				wp_enqueue_style( 'gat-frontend', plugins_url( 'assets/gat-frontend.css', GAT_PLUGIN_FILE ), array(), GAT_VERSION );
			}
		}
	}

	/**
	 * Auto-create the signup and dashboard pages, once, similar to how
	 * WooCommerce creates its Cart/Checkout pages on first run.
	 */
	public static function maybe_create_pages() {
		if ( ! get_option( 'gat_signup_page_id' ) ) {
			$id = wp_insert_post( array(
				'post_title'   => 'Become an Affiliate',
				'post_name'    => 'become-an-affiliate',
				'post_content' => '[gat_affiliate_signup]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_option( 'gat_signup_page_id', $id );
			}
		}
		if ( ! get_option( 'gat_dashboard_page_id' ) ) {
			$id = wp_insert_post( array(
				'post_title'   => 'Affiliate Dashboard',
				'post_name'    => 'affiliate-dashboard',
				'post_content' => '[gat_affiliate_dashboard]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_option( 'gat_dashboard_page_id', $id );
			}
		}
	}

	private static function dashboard_url() {
		$id = get_option( 'gat_dashboard_page_id' );
		return $id ? get_permalink( $id ) : home_url( '/affiliate-dashboard/' );
	}

	private static function signup_url() {
		$id = get_option( 'gat_signup_page_id' );
		return $id ? get_permalink( $id ) : home_url( '/become-an-affiliate/' );
	}

	private static function get_active_partners() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT id, name FROM ' . GAT_DB::table( 'partners' ) . ' ORDER BY name ASC' );
	}

	private static function generate_unique_code( $name ) {
		global $wpdb;
		$table = GAT_DB::table( 'codes' );
		$base  = sanitize_title( $name );
		if ( '' === $base ) {
			$base = 'affiliate';
		}
		$code = $base;
		$i    = 0;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) ) ) {
			$i++;
			$code = $base . '-' . $i;
		}
		return $code;
	}

	/* ---------------------------------------------------------------- *
	 * SIGNUP
	 * ---------------------------------------------------------------- */

	public static function render_signup() {
		if ( is_user_logged_in() && GAT_Roles::is_affiliate() ) {
			return '<div class="gat-notice">You already have an affiliate account. <a href="' . esc_url( self::dashboard_url() ) . '">Go to your dashboard &rarr;</a></div>';
		}

		ob_start();

		if ( isset( $_GET['gat_signup'] ) && 'success' === $_GET['gat_signup'] ) {
			echo '<div class="gat-notice gat-notice-success"><p>Thanks for signing up! Your account is pending approval. Once approved, your affiliate link and dashboard will be active.</p><p><a href="' . esc_url( self::dashboard_url() ) . '">Go to your dashboard &rarr;</a></p></div>';
			return ob_get_clean();
		}

		$error = isset( $_GET['gat_error'] ) ? sanitize_text_field( wp_unslash( $_GET['gat_error'] ) ) : '';
		if ( $error ) {
			echo '<div class="gat-notice gat-notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		$partners = self::get_active_partners();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gat-form">
			<?php wp_nonce_field( 'gat_affiliate_signup' ); ?>
			<input type="hidden" name="action" value="gat_affiliate_signup">
			<p style="position:absolute;left:-9999px;" aria-hidden="true">
				<label>Leave this field empty<input type="text" name="gat_hp" tabindex="-1" autocomplete="off"></label>
			</p>

			<p>
				<label for="gat_name">Your name</label><br>
				<input type="text" id="gat_name" name="name" required class="gat-input">
			</p>
			<p>
				<label for="gat_email">Email</label><br>
				<input type="email" id="gat_email" name="email" required class="gat-input">
			</p>
			<p>
				<label for="gat_partner">Which builder do you want to promote?</label><br>
				<select id="gat_partner" name="partner_id" required class="gat-input">
					<option value="">-- choose one --</option>
					<?php foreach ( $partners as $p ) : ?>
						<option value="<?php echo esc_attr( $p->id ); ?>"><?php echo esc_html( $p->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="gat_password">Choose a password</label><br>
				<input type="password" id="gat_password" name="password" required minlength="8" class="gat-input">
			</p>
			<p>
				<label for="gat_password2">Confirm password</label><br>
				<input type="password" id="gat_password2" name="password2" required minlength="8" class="gat-input">
			</p>
			<p>
				<button type="submit" class="gat-button">Sign up</button>
			</p>
			<p class="gat-fineprint">Already have an account? <a href="<?php echo esc_url( self::dashboard_url() ); ?>">Log in on your dashboard</a>.</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function handle_signup() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'gat_affiliate_signup' ) ) {
			wp_die( 'Security check failed. Please go back and try again.' );
		}

		$redirect_back = wp_get_referer() ? wp_get_referer() : self::signup_url();

		// Honeypot: if filled, silently pretend success without creating anything.
		if ( ! empty( $_POST['gat_hp'] ) ) {
			wp_safe_redirect( add_query_arg( 'gat_signup', 'success', $redirect_back ) );
			exit;
		}

		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$partner_id  = isset( $_POST['partner_id'] ) ? absint( $_POST['partner_id'] ) : 0;
		$password    = isset( $_POST['password'] ) ? (string) $_POST['password'] : '';
		$password2   = isset( $_POST['password2'] ) ? (string) $_POST['password2'] : '';

		$fail = function( $msg ) use ( $redirect_back ) {
			wp_safe_redirect( add_query_arg( 'gat_error', rawurlencode( $msg ), $redirect_back ) );
			exit;
		};

		if ( '' === $name || ! is_email( $email ) || ! $partner_id || strlen( $password ) < 8 ) {
			$fail( 'Please fill in every field. Passwords need to be at least 8 characters.' );
		}
		if ( $password !== $password2 ) {
			$fail( 'Passwords do not match.' );
		}
		if ( email_exists( $email ) ) {
			$fail( 'That email is already registered. Try logging in instead.' );
		}

		$username = self::generate_unique_username( $email );

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $name,
			'first_name'   => $name,
			'role'         => GAT_Roles::ROLE,
		) );

		if ( is_wp_error( $user_id ) ) {
			$fail( 'Could not create account: ' . $user_id->get_error_message() );
		}

		update_user_meta( $user_id, 'gat_status', 'pending' );

		$code = self::generate_unique_code( $name );

		global $wpdb;
		$wpdb->insert(
			GAT_DB::table( 'codes' ),
			array(
				'code'               => $code,
				'sub_affiliate_name' => $name,
				'partner_id'         => $partner_id,
				'wp_user_id'         => $user_id,
				'status'             => 'pending',
				'cut_type'           => 'percent',
				'cut_value'          => 0,
				'active'             => 0,
				'notes'              => 'Self-signup, pending approval. Set the real cut rate before approving.',
				'created_at'         => current_time( 'mysql' ),
			)
		);

		// Log them in so their dashboard shows "pending" immediately.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		// Notify the site admin.
		wp_mail(
			get_option( 'admin_email' ),
			'New affiliate signup: ' . $name,
			"A new affiliate signed up and is pending approval.\n\nName: {$name}\nEmail: {$email}\nCode: {$code}\n\nReview and approve in wp-admin under Affiliate Tracker > Affiliates."
		);

		wp_safe_redirect( add_query_arg( 'gat_signup', 'success', self::signup_url() ) );
		exit;
	}

	private static function generate_unique_username( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'affiliate';
		}
		$username = $base;
		$i        = 0;
		while ( username_exists( $username ) ) {
			$i++;
			$username = $base . $i;
		}
		return $username;
	}

	/* ---------------------------------------------------------------- *
	 * LOGIN
	 * ---------------------------------------------------------------- */

	public static function handle_login() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'gat_affiliate_login' ) ) {
			wp_die( 'Security check failed. Please go back and try again.' );
		}

		$creds = array(
			'user_login'    => isset( $_POST['gat_username'] ) ? sanitize_text_field( wp_unslash( $_POST['gat_username'] ) ) : '',
			'user_password' => isset( $_POST['gat_login_password'] ) ? (string) $_POST['gat_login_password'] : '',
			'remember'      => true,
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( add_query_arg( 'gat_error', rawurlencode( 'Login failed: check your email/username and password.' ), self::dashboard_url() ) );
			exit;
		}

		wp_safe_redirect( self::dashboard_url() );
		exit;
	}

	/* ---------------------------------------------------------------- *
	 * DASHBOARD
	 * ---------------------------------------------------------------- */

	public static function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_form();
		}

		if ( ! GAT_Roles::is_affiliate() ) {
			return '<div class="gat-notice">This dashboard is for affiliates only. <a href="' . esc_url( wp_logout_url( self::signup_url() ) ) . '">Log out</a> and sign up as an affiliate, or contact us if you think this is a mistake.</div>';
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$status  = get_user_meta( $user_id, 'gat_status', true ) ?: 'active';

		ob_start();

		if ( isset( $_GET['gat_notice'] ) ) {
			$notices = array(
				'password_updated' => 'Password updated.',
				'payment_updated'  => 'Payment information saved.',
			);
			$key = sanitize_text_field( wp_unslash( $_GET['gat_notice'] ) );
			if ( isset( $notices[ $key ] ) ) {
				echo '<div class="gat-notice gat-notice-success"><p>' . esc_html( $notices[ $key ] ) . '</p></div>';
			}
		}
		if ( isset( $_GET['gat_error'] ) ) {
			echo '<div class="gat-notice gat-notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gat_error'] ) ) ) . '</p></div>';
		}

		echo '<div class="gat-dashboard">';
		echo '<p>Welcome back, ' . esc_html( $user->display_name ) . '. <a href="' . esc_url( wp_logout_url( self::dashboard_url() ) ) . '">Log out</a></p>';

		if ( 'pending' === $status ) {
			echo '<div class="gat-notice">Your account is pending approval. Your link will start working once approved &mdash; check back soon.</div>';
		} elseif ( 'rejected' === $status ) {
			echo '<div class="gat-notice gat-notice-error">Your affiliate application was not approved. Contact us if you have questions.</div>';
		}

		self::render_stats_section( $user_id );
		self::render_password_section();
		self::render_payment_section( $user_id );

		echo '</div>';

		return ob_get_clean();
	}

	private static function render_login_form() {
		ob_start();
		$error = isset( $_GET['gat_error'] ) ? sanitize_text_field( wp_unslash( $_GET['gat_error'] ) ) : '';
		if ( $error ) {
			echo '<div class="gat-notice gat-notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gat-form">
			<?php wp_nonce_field( 'gat_affiliate_login' ); ?>
			<input type="hidden" name="action" value="gat_affiliate_login">
			<p>
				<label for="gat_username">Email or username</label><br>
				<input type="text" id="gat_username" name="gat_username" required class="gat-input">
			</p>
			<p>
				<label for="gat_login_password">Password</label><br>
				<input type="password" id="gat_login_password" name="gat_login_password" required class="gat-input">
			</p>
			<p><button type="submit" class="gat-button">Log in</button></p>
			<p class="gat-fineprint">
				Not an affiliate yet? <a href="<?php echo esc_url( self::signup_url() ); ?>">Sign up here</a>.
				Forgot your password? <a href="<?php echo esc_url( wp_lostpassword_url( self::dashboard_url() ) ); ?>">Reset it</a>.
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function render_stats_section( $user_id ) {
		global $wpdb;
		$codes_table    = GAT_DB::table( 'codes' );
		$partners_table = GAT_DB::table( 'partners' );
		$clicks_table   = GAT_DB::table( 'clicks' );
		$payouts_table  = GAT_DB::table( 'payouts' );

		$codes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, p.name AS partner_name FROM {$codes_table} c
				 LEFT JOIN {$partners_table} p ON p.id = c.partner_id
				 WHERE c.wp_user_id = %d ORDER BY c.created_at ASC",
				$user_id
			)
		);

		echo '<h2>Your links</h2>';
		if ( ! $codes ) {
			echo '<p>No affiliate links yet.</p>';
			return;
		}

		foreach ( $codes as $c ) {
			$link        = home_url( '/go/' . rawurlencode( $c->code ) . '/' );
			$click_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$clicks_table} WHERE code_id = %d", $c->id ) );
			$totals      = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(subaffiliate_cut),0) AS total_owed, COUNT(*) AS payout_count FROM {$payouts_table} WHERE code_id = %d", $c->id ) );

			echo '<div class="gat-code-card">';
			echo '<p><strong>' . esc_html( $c->partner_name ) . '</strong> &mdash; status: ' . esc_html( $c->status ) . '</p>';
			echo '<p>Your link: <code>' . esc_html( $link ) . '</code></p>';
			echo '<div class="gat-stat-row">';
			echo '<div class="gat-stat"><span class="gat-stat-num">' . esc_html( $click_count ) . '</span><span class="gat-stat-label">Clicks</span></div>';
			echo '<div class="gat-stat"><span class="gat-stat-num">' . esc_html( $totals->payout_count ) . '</span><span class="gat-stat-label">Paid sales</span></div>';
			echo '<div class="gat-stat"><span class="gat-stat-num">$' . esc_html( number_format( (float) $totals->total_owed, 2 ) ) . '</span><span class="gat-stat-label">Total earned</span></div>';
			echo '</div>';
			echo '</div>';
		}
	}

	private static function render_password_section() {
		?>
		<h2>Change password</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gat-form">
			<?php wp_nonce_field( 'gat_change_password' ); ?>
			<input type="hidden" name="action" value="gat_change_password">
			<p>
				<label for="gat_current_password">Current password</label><br>
				<input type="password" id="gat_current_password" name="current_password" required class="gat-input">
			</p>
			<p>
				<label for="gat_new_password">New password</label><br>
				<input type="password" id="gat_new_password" name="new_password" required minlength="8" class="gat-input">
			</p>
			<p>
				<label for="gat_new_password2">Confirm new password</label><br>
				<input type="password" id="gat_new_password2" name="new_password2" required minlength="8" class="gat-input">
			</p>
			<p><button type="submit" class="gat-button">Update password</button></p>
		</form>
		<?php
	}

	public static function handle_change_password() {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Please log in first.' );
		}
		check_admin_referer( 'gat_change_password' );

		$user_id  = get_current_user_id();
		$user     = wp_get_current_user();
		$current  = isset( $_POST['current_password'] ) ? (string) $_POST['current_password'] : '';
		$new_pass = isset( $_POST['new_password'] ) ? (string) $_POST['new_password'] : '';
		$new_pass2 = isset( $_POST['new_password2'] ) ? (string) $_POST['new_password2'] : '';

		$fail = function( $msg ) {
			wp_safe_redirect( add_query_arg( 'gat_error', rawurlencode( $msg ), self::dashboard_url() ) );
			exit;
		};

		if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			$fail( 'Current password is incorrect.' );
		}
		if ( strlen( $new_pass ) < 8 || $new_pass !== $new_pass2 ) {
			$fail( 'New password must be at least 8 characters and match its confirmation.' );
		}

		wp_set_password( $new_pass, $user_id );

		// wp_set_password() invalidates the current session, so log back in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		wp_safe_redirect( add_query_arg( 'gat_notice', 'password_updated', self::dashboard_url() ) );
		exit;
	}

	private static function render_payment_section( $user_id ) {
		$existing = get_user_meta( $user_id, 'gat_payment_details', true );
		?>
		<h2>Payment information</h2>
		<p class="gat-fineprint">Tell us how you'd like to be paid &mdash; PayPal email, Venmo, Zelle, etc. Do not enter full bank account or card numbers here.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gat-form">
			<?php wp_nonce_field( 'gat_save_payment_info' ); ?>
			<input type="hidden" name="action" value="gat_save_payment_info">
			<p>
				<textarea name="payment_details" rows="3" class="gat-input" placeholder="e.g. PayPal: name@example.com"><?php echo esc_textarea( $existing ); ?></textarea>
			</p>
			<p><button type="submit" class="gat-button">Save</button></p>
		</form>
		<?php
	}

	public static function handle_save_payment_info() {
		if ( ! is_user_logged_in() ) {
			wp_die( 'Please log in first.' );
		}
		check_admin_referer( 'gat_save_payment_info' );

		$user_id = get_current_user_id();
		$details = isset( $_POST['payment_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['payment_details'] ) ) : '';

		update_user_meta( $user_id, 'gat_payment_details', $details );

		wp_safe_redirect( add_query_arg( 'gat_notice', 'payment_updated', self::dashboard_url() ) );
		exit;
	}
}
