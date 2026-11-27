<?php
/**
 * Plugin Name:       Soko Suite
 * Plugin URI:        https://github.com/Carolnjue/soko-suite
 * Description:       Storefront, M-Pesa checkout, invoicing, CRM, stock, installations, accounting reports and a front-end business desk in one plugin.
 * Version:           1.5.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Soko Suite
 * License:           GPL-2.0-or-later
 * Text Domain:       soko-suite
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

define( 'SOKO_VERSION', '1.5.1' );
define( 'SOKO_DB_VERSION', '7' );
define( 'SOKO_FILE', __FILE__ );
define( 'SOKO_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOKO_URL', plugin_dir_url( __FILE__ ) );

require_once SOKO_DIR . 'includes/helpers.php';
require_once SOKO_DIR . 'includes/class-soko-install.php';
require_once SOKO_DIR . 'includes/class-soko-updater.php';
require_once SOKO_DIR . 'includes/class-soko-settings.php';
require_once SOKO_DIR . 'includes/class-soko-ledger.php';
require_once SOKO_DIR . 'includes/class-soko-mpesa.php';
require_once SOKO_DIR . 'includes/class-soko-etims.php';
require_once SOKO_DIR . 'includes/class-soko-pdf.php';
require_once SOKO_DIR . 'includes/class-soko-xlsx.php';
require_once SOKO_DIR . 'includes/class-soko-reports.php';
require_once SOKO_DIR . 'includes/class-soko-api.php';
require_once SOKO_DIR . 'includes/class-soko-sections.php';
require_once SOKO_DIR . 'includes/class-soko-store.php';
require_once SOKO_DIR . 'includes/class-soko-desk.php';
require_once SOKO_DIR . 'includes/class-soko-cron.php';
require_once SOKO_DIR . 'includes/class-soko-demo.php';
require_once SOKO_DIR . 'includes/class-soko-blog.php';
require_once SOKO_DIR . 'includes/class-soko-mpesa-free.php';

register_activation_hook( __FILE__, array( 'Soko_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Soko_Install', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	Soko_Install::maybe_upgrade();
	Soko_Updater::init( SOKO_FILE );
	Soko_API::init();
	Soko_Store::init();
	Soko_Desk::init();
	Soko_Cron::init();
} );
