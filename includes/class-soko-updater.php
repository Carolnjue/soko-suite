<?php
/**
 * Keeps Soko Suite up to date from its GitHub repository.
 * Updates show on the normal Plugins screen, install automatically,
 * and can be pulled straight away from Desk → Settings → System.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Updater {

	const REPO   = 'Carolnjue/soko-suite';
	const BRANCH = 'main';
	const SLUG   = 'soko-suite';
	const KEY    = 'soko_remote_version';

	private static $file;

	public static function init( $file ) {
		self::$file = plugin_basename( $file );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source' ), 10, 4 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto' ), 20, 2 );
		add_filter( 'upgrader_pre_install', array( __CLASS__, 'remember_active' ), 5, 2 );
		add_filter( 'upgrader_post_install', array( __CLASS__, 'keep_active' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'done' ), 10, 2 );
	}

	public static function current() {
		return SOKO_VERSION;
	}

	public static function package() {
		return 'https://codeload.github.com/' . self::REPO . '/zip/refs/heads/' . self::BRANCH;
	}

	public static function remote( $force = false ) {
		$v = get_site_transient( self::KEY );
		if ( false !== $v && ! $force ) {
			return $v;
		}
		$v    = '';
		$urls = array(
			array( 'https://api.github.com/repos/' . self::REPO . '/contents/soko-suite.php?ref=' . self::BRANCH, array( 'Accept' => 'application/vnd.github.raw', 'User-Agent' => 'Soko-Suite' ) ),
			array( 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::BRANCH . '/soko-suite.php', array() ),
		);
		foreach ( $urls as $u ) {
			$r = wp_remote_get( $u[0], array( 'timeout' => 12, 'headers' => $u[1] ) );
			if ( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) && preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/mi', wp_remote_retrieve_body( $r ), $m ) ) {
				$v = $m[1];
				break;
			}
		}
		set_site_transient( self::KEY, $v, $v ? 3 * HOUR_IN_SECONDS : 30 * MINUTE_IN_SECONDS );
		return $v;
	}

	public static function check( $t ) {
		if ( ! is_object( $t ) ) {
			$t = new stdClass();
		}
		$remote = self::remote();
		$item   = (object) array(
			'id'          => 'github.com/' . self::REPO,
			'slug'        => self::SLUG,
			'plugin'      => self::$file,
			'new_version' => $remote ? $remote : self::current(),
			'url'         => 'https://github.com/' . self::REPO,
			'package'     => self::package(),
		);
		if ( $remote && version_compare( $remote, self::current(), '>' ) ) {
			$t->response[ self::$file ] = $item;
		} else {
			$t->no_update[ self::$file ] = $item;
		}
		return $t;
	}

	public static function info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $res;
		}
		return (object) array(
			'name'          => 'Soko Suite',
			'slug'          => self::SLUG,
			'version'       => self::remote(),
			'author'        => 'Soko Suite',
			'homepage'      => 'https://github.com/' . self::REPO,
			'download_link' => self::package(),
			'sections'      => array( 'description' => 'Storefront, M-Pesa checkout, invoicing, CRM, stock, installations and reports with a front-end business desk.' ),
		);
	}

	/** GitHub zips unpack to "soko-suite-main"; rename to the plugin folder. */
	public static function fix_source( $source, $remote_source, $upgrader, $extra = array() ) {
		global $wp_filesystem;
		$ours = ( isset( $extra['plugin'] ) && self::$file === $extra['plugin'] ) || false !== strpos( basename( untrailingslashit( $source ) ), self::SLUG );
		if ( ! $ours || ! $wp_filesystem ) {
			return $source;
		}
		$target = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( untrailingslashit( $source ) !== untrailingslashit( $target ) ) {
			if ( ! $wp_filesystem->move( $source, $target, true ) ) {
				return new WP_Error( 'soko_rename', 'Could not prepare the Soko Suite update.' );
			}
			$source = $target;
		}
		return $source;
	}

	public static function auto( $update, $item ) {
		return ( isset( $item->plugin ) && self::$file === $item->plugin ) ? true : $update;
	}

	public static function remember_active( $ok, $extra ) {
		if ( isset( $extra['plugin'] ) && self::$file === $extra['plugin'] && is_plugin_active( self::$file ) ) {
			update_option( 'soko_was_active', 1, false );
		}
		return $ok;
	}

	public static function keep_active( $ok, $extra, $result ) {
		if ( isset( $extra['plugin'] ) && self::$file === $extra['plugin'] && get_option( 'soko_was_active' ) ) {
			$active = (array) get_option( 'active_plugins', array() );
			if ( ! in_array( self::$file, $active, true ) ) {
				$active[] = self::$file;
				update_option( 'active_plugins', array_values( array_unique( $active ) ) );
			}
			delete_option( 'soko_was_active' );
		}
		return $ok;
	}

	public static function done() {
		delete_site_transient( self::KEY );
		update_option( 'soko_flush', 1, false );
	}

	/** Pull the latest version from GitHub right now. Returns a status array. */
	public static function update_now() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		$before = self::current();
		$remote = self::remote( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		/* Reinstall from the branch head even when the version number has not changed. */
		$result   = $upgrader->install( self::package(), array( 'overwrite_package' => true ) );
		if ( ! is_plugin_active( self::$file ) ) {
			activate_plugin( self::$file );
		}
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . self::$file, false, false );
		return array(
			'before'   => $before,
			'remote'   => $remote,
			'now'      => $data['Version'],
			'ok'       => ! is_wp_error( $result ) && false !== $result,
			'messages' => $skin->get_upgrade_messages(),
		);
	}
}
