<?php
/**
 * Plugin Name: Gemz Affiliate Tracker
 * Description: Private, admin-only sub-affiliate click tracking and payout calculator for the tiny homes affiliate site. Not visible to site visitors except the /go/{code} redirect itself.
 * Version: 1.0.0
 * Author: Cary Robinson
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GAT_VERSION', '2.2.0' );
define( 'GAT_DB_VERSION', '3' );
define( 'GAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GAT_PLUGIN_FILE', __FILE__ );

require_once GAT_PLUGIN_DIR . 'includes/class-gat-roles.php';
require_once GAT_PLUGIN_DIR . 'includes/class-gat-db.php';
require_once GAT_PLUGIN_DIR . 'includes/class-gat-redirect.php';
require_once GAT_PLUGIN_DIR . 'includes/class-gat-admin.php';
require_once GAT_PLUGIN_DIR . 'includes/class-gat-frontend.php';
require_once GAT_PLUGIN_DIR . 'includes/class-gat-rest.php';

register_activation_hook( __FILE__, array( 'GAT_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GAT_Redirect', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'GAT_DB', 'maybe_upgrade' ) );

GAT_Redirect::init();
GAT_Admin::init();
GAT_Frontend::init();
GAT_REST::init();
