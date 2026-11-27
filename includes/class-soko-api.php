<?php
/**
 * REST API for the storefront and Dynamic Desk.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_API {

	const NS = 'soko/v1';

	/** Simple records: table, capability, writable fields, searchable fields. */
	public static function entities() {
		return array(
			'customers' => array( 'customers', 'soko_crm', array( 'name', 'type', 'phone', 'email', 'kra', 'county', 'address', 'notes' ), array( 'name', 'phone', 'email', 'kra' ), array( 'soko_sales', 'soko_jobs', 'soko_payments' ) ),
			'leads'     => array( 'leads', 'soko_crm', array( 'name', 'phone', 'email', 'source', 'stage', 'value', 'interest', 'county', 'notes', 'owner_id', 'customer_id', 'next_action' ), array( 'name', 'phone', 'interest' ), array() ),
			'jobs'      => array( 'jobs', 'soko_jobs', array( 'title', 'customer_id', 'doc_id', 'stage', 'techs', 'date', 'location', 'size', 'checklist', 'notes' ), array( 'title', 'location', 'techs' ), array( 'soko_schedule' ) ),
			'events'    => array( 'events', 'soko_schedule', array( 'title', 'kind', 'start', 'who', 'location', 'job_id', 'remind', 'done' ), array( 'title', 'who', 'location' ), array( 'soko_jobs' ) ),
			'suppliers' => array( 'suppliers', 'soko_purchases', array( 'name', 'contact', 'phone', 'email', 'kra', 'notes' ), array( 'name', 'phone' ), array( 'soko_expenses', 'soko_stock' ) ),
			'expenses'  => array( 'expenses', 'soko_expenses', array( 'date', 'category', 'description', 'amount', 'vat', 'method', 'reference', 'receipt' ), array( 'description', 'category', 'reference' ), array() ),
			'licences'  => array( 'licences', 'soko_compliance', array( 'name', 'holder', 'number', 'expires', 'notes' ), array( 'name', 'holder', 'number' ), array() ),
		);
	}

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	private static function perm( $cap, $also = array() ) {
		return function () use ( $cap, $also ) {
			if ( soko_can( $cap ) ) {
				return true;
			}
			foreach ( $also as $c ) {
				if ( soko_can( $c ) ) {
					return true;
				}
			}
			return new WP_Error( 'soko_forbidden', 'You do not have access to this.', array( 'status' => 403 ) );
		};
	}

	public static function routes() {
		$r = function ( $path, $methods, $cb, $perm ) {
			register_rest_route( self::NS, $path, array( 'methods' => $methods, 'callback' => $cb, 'permission_callback' => $perm ) );
		};
		$open = '__return_true';
		$staff = function () {
			return soko_is_staff() ? true : new WP_Error( 'soko_forbidden', 'Please sign in.', array( 'status' => 401 ) );
		};

		/* ---- storefront (public) ---- */
		$r( '/store/search', 'GET', array( __CLASS__, 'store_search' ), $open );
		$r( '/store/checkout', 'POST', array( __CLASS__, 'store_checkout' ), $open );
		$r( '/store/pay', 'POST', array( __CLASS__, 'store_pay' ), $open );
		$r( '/store/status', 'GET', array( __CLASS__, 'store_status' ), $open );
		$r( '/store/lead', 'POST', array( __CLASS__, 'store_lead' ), $open );
		$r( '/store/find', 'POST', array( __CLASS__, 'store_find' ), $open );
		$r( '/doc-pdf', 'GET', array( __CLASS__, 'doc_pdf_public' ), $open );

		/* ---- M-Pesa callbacks (token in the path) ---- */
		$r( '/mpesa/(?P<token>[A-Za-z0-9]+)/(?P<what>stk|confirm|validate)', 'POST', array( __CLASS__, 'mpesa_callback' ), $open );
		$r( '/sms/(?P<token>[A-Za-z0-9]+)', 'POST,GET', array( 'Soko_Mpesa_Free', 'on_sms' ), $open );
		$r( '/payments/sms-info', 'GET', function () { return array( 'url' => Soko_Mpesa_Free::sms_url(), 'last' => get_option( 'soko_sms_last', '' ) ); }, self::perm( 'soko_settings' ) );
		$r( '/payments/statement', 'POST', function ( $req ) { $f = $req->get_file_params(); if ( empty( $f['file']['tmp_name'] ) ) { return array( 'ok' => false, 'message' => 'Choose the statement file.' ); } return Soko_Mpesa_Free::import_csv( $f['file']['tmp_name'] ); }, self::perm( 'soko_payments' ) );

		/* ---- desk ---- */
		$r( '/me', 'GET', array( __CLASS__, 'me' ), $staff );
		$r( '/dashboard', 'GET', function () { return Soko_Reports::dashboard(); }, self::perm( 'soko_dashboard' ) );
		$r( '/search', 'GET', array( __CLASS__, 'search' ), $staff );

		foreach ( self::entities() as $name => $e ) {
			$r( '/' . $name, 'GET', function ( $req ) use ( $name ) { return self::list_entity( $name, $req ); }, self::perm( $e[1], $e[4] ) );
			$r( '/' . $name, 'POST', function ( $req ) use ( $name ) { return self::save_entity( $name, $req ); }, self::perm( $e[1], $e[4] ) );
			$r( '/' . $name . '/(?P<id>\d+)', 'POST', function ( $req ) use ( $name ) { return self::save_entity( $name, $req, (int) $req['id'] ); }, self::perm( $e[1], $e[4] ) );
			$r( '/' . $name . '/(?P<id>\d+)', 'DELETE', function ( $req ) use ( $name ) { return self::delete_entity( $name, (int) $req['id'] ); }, self::perm( $e[1] ) );
		}

		$r( '/products', 'GET', array( __CLASS__, 'products' ), $staff );
		$r( '/products', 'POST', array( __CLASS__, 'save_product' ), self::perm( 'soko_stock' ) );
		$r( '/products/(?P<id>\d+)', 'POST', array( __CLASS__, 'save_product' ), self::perm( 'soko_stock' ) );
		$r( '/products/(?P<id>\d+)', 'DELETE', array( __CLASS__, 'delete_product' ), self::perm( 'soko_stock' ) );
		$r( '/products/(?P<id>\d+)/moves', 'GET', array( __CLASS__, 'moves' ), self::perm( 'soko_stock' ) );
		$r( '/products/(?P<id>\d+)/adjust', 'POST', array( __CLASS__, 'adjust' ), self::perm( 'soko_stock' ) );
		$r( '/categories', 'GET', function () { return soko_categories(); }, $staff );
		$r( '/categories', 'POST', array( __CLASS__, 'save_categories' ), self::perm( 'soko_stock' ) );

		$r( '/docs', 'GET', array( __CLASS__, 'docs' ), self::perm( 'soko_sales', array( 'soko_payments', 'soko_reports' ) ) );
		$r( '/docs', 'POST', array( __CLASS__, 'save_doc' ), self::perm( 'soko_sales' ) );
		$r( '/docs/(?P<id>\d+)', 'GET', array( __CLASS__, 'get_doc' ), self::perm( 'soko_sales', array( 'soko_payments', 'soko_reports', 'soko_jobs' ) ) );
		$r( '/docs/(?P<id>\d+)', 'POST', array( __CLASS__, 'save_doc' ), self::perm( 'soko_sales' ) );
		$r( '/docs/(?P<id>\d+)/action', 'POST', array( __CLASS__, 'doc_action' ), self::perm( 'soko_sales', array( 'soko_payments' ) ) );
		$r( '/docs/(?P<id>\d+)/pdf', 'GET', array( __CLASS__, 'doc_pdf' ), self::perm( 'soko_sales', array( 'soko_payments', 'soko_reports', 'soko_jobs' ) ) );

		$r( '/payments', 'GET', array( __CLASS__, 'payments' ), self::perm( 'soko_payments' ) );
		$r( '/payments', 'POST', array( __CLASS__, 'add_payment' ), self::perm( 'soko_payments' ) );
		$r( '/payments/(?P<id>\d+)/match', 'POST', array( __CLASS__, 'match_payment' ), self::perm( 'soko_payments' ) );
		$r( '/mpesa/test', 'POST', function () { return Soko_Mpesa::test(); }, self::perm( 'soko_settings' ) );
		$r( '/mpesa/register', 'POST', function () { return Soko_Mpesa::register_c2b(); }, self::perm( 'soko_settings' ) );

		$r( '/purchases', 'GET', array( __CLASS__, 'purchases' ), self::perm( 'soko_purchases', array( 'soko_stock' ) ) );
		$r( '/purchases', 'POST', array( __CLASS__, 'save_purchase' ), self::perm( 'soko_purchases' ) );
		$r( '/purchases/(?P<id>\d+)', 'POST', array( __CLASS__, 'save_purchase' ), self::perm( 'soko_purchases' ) );
		$r( '/purchases/(?P<id>\d+)/receive', 'POST', array( __CLASS__, 'receive_purchase' ), self::perm( 'soko_purchases', array( 'soko_stock' ) ) );

		$r( '/reports/(?P<type>[a-z]+)', 'GET', array( __CLASS__, 'report' ), self::perm( 'soko_reports' ) );
		$r( '/activity', 'GET', array( __CLASS__, 'activity' ), self::perm( 'soko_settings', array( 'soko_reports' ) ) );

		$r( '/blog', 'GET', function () { return Soko_Blog::posts( array( 'publish', 'draft' ) ); }, self::perm( 'soko_site' ) );
		$r( '/blog', 'POST', function ( $req ) { return Soko_Blog::save( $req->get_json_params() ); }, self::perm( 'soko_site' ) );
		$r( '/blog/(?P<id>\d+)', 'GET', function ( $req ) { $p = get_post( (int) $req['id'] ); return $p && 'post' === $p->post_type ? Soko_Blog::row( $p, true ) : new WP_Error( 'nf', 'Not found', array( 'status' => 404 ) ); }, self::perm( 'soko_site' ) );
		$r( '/blog/(?P<id>\d+)', 'DELETE', function ( $req ) { $p = get_post( (int) $req['id'] ); if ( $p && 'post' === $p->post_type ) { wp_trash_post( $p->ID ); soko_log( 'Deleted blog post', 'blog', $p->post_title ); } return array( 'ok' => true ); }, self::perm( 'soko_site' ) );
		$r( '/site', 'GET', function () { return Soko_Sections::site(); }, self::perm( 'soko_site' ) );
		$r( '/site', 'POST', array( __CLASS__, 'save_site' ), self::perm( 'soko_site' ) );
		$r( '/site/schema', 'GET', function () { return Soko_Sections::schema(); }, self::perm( 'soko_site' ) );

		$r( '/settings', 'GET', function () { return Soko_Settings::public_view( soko_can( 'soko_settings' ) ); }, $staff );
		$r( '/settings', 'POST', array( __CLASS__, 'save_settings' ), self::perm( 'soko_settings' ) );
		$r( '/system/update', 'POST', function () { return Soko_Updater::update_now(); }, function () { return current_user_can( 'update_plugins' ) || soko_can( 'soko_settings' ); } );
		$r( '/system/demo', 'POST', array( __CLASS__, 'demo' ), self::perm( 'soko_settings' ) );
		$r( '/system/test-email', 'POST', array( __CLASS__, 'test_email' ), self::perm( 'soko_settings' ) );

		$r( '/users', 'GET', array( __CLASS__, 'users' ), self::perm( 'soko_users' ) );
		$r( '/users', 'POST', array( __CLASS__, 'save_user' ), self::perm( 'soko_users' ) );
		$r( '/users/(?P<id>\d+)', 'POST', array( __CLASS__, 'save_user' ), self::perm( 'soko_users' ) );
		$r( '/team', 'GET', array( __CLASS__, 'team' ), $staff );
	}

	/* ================================================================ store */

	private static function throttle( $key, $max = 20 ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$k  = 'soko_rl_' . md5( $key . $ip );
		$n  = (int) get_transient( $k );
		if ( $n >= $max ) {
			return new WP_Error( 'soko_slow', 'Too many attempts. Please wait a few minutes.', array( 'status' => 429 ) );
		}
		set_transient( $k, $n + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	public static function store_search( $req ) {
		$q = sanitize_text_field( $req['q'] );
		if ( strlen( $q ) < 2 ) {
			return array();
		}
		return array_map(
			function ( $p ) {
				return array( 'name' => $p['name'], 'url' => $p['url'], 'price' => soko_money( $p['price'] ), 'cat' => $p['cat_name'], 'icon' => soko_icon( $p['icon'], 28 ), 'img' => $p['images'] ? $p['images'][0] : '' );
			},
			array_slice( Soko_Ledger::products( array( 'visible' => 1, 'q' => $q ) ), 0, 6 )
		);
	}

	public static function store_checkout( $req ) {
		$t = self::throttle( 'checkout', 12 );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$b = $req->get_json_params();
		if ( ! empty( $b['website'] ) ) {
			return new WP_Error( 'soko', 'Sorry, something went wrong.', array( 'status' => 400 ) );
		}
		$c = isset( $b['customer'] ) ? (array) $b['customer'] : array();
		if ( empty( $c['name'] ) || strlen( soko_phone( isset( $c['phone'] ) ? $c['phone'] : '' ) ) !== 12 ) {
			return new WP_Error( 'soko', 'Please enter your name and a valid phone number.', array( 'status' => 400 ) );
		}
		if ( ! empty( $c['email'] ) && ! is_email( $c['email'] ) ) {
			return new WP_Error( 'soko', 'Please check your email address, for example name@gmail.com.', array( 'status' => 400 ) );
		}
		$lines = array();
		foreach ( (array) ( isset( $b['items'] ) ? $b['items'] : array() ) as $it ) {
			$p = Soko_Ledger::product( (int) $it['id'] );
			if ( ! $p || ! $p['visible'] ) {
				continue;
			}
			$qty     = max( 1, min( 500, (int) $it['qty'] ) );
			$lines[] = array( 'product_id' => $p['id'], 'qty' => $qty );
			if ( ! empty( $it['install'] ) && $p['install_fee'] > 0 ) {
				$lines[] = array( 'description' => 'Installation: ' . $p['name'], 'qty' => $qty, 'price' => $p['install_fee'], 'vatable' => 1 );
			}
		}
		if ( ! $lines ) {
			return new WP_Error( 'soko', 'Your cart is empty.', array( 'status' => 400 ) );
		}
		$del = soko_setting( 'delivery' );
		$di  = isset( $b['delivery'] ) ? (int) $b['delivery'] : 0;
		if ( isset( $del[ $di ] ) && (float) $del[ $di ]['fee'] > 0 ) {
			$lines[] = array( 'description' => 'Delivery: ' . $del[ $di ]['label'], 'qty' => 1, 'price' => (float) $del[ $di ]['fee'], 'vatable' => 1 );
		}
		$notes = array();
		if ( isset( $del[ $di ] ) ) {
			$notes[] = 'Delivery: ' . $del[ $di ]['label'];
		}
		if ( ! empty( $b['install_date'] ) ) {
			$notes[] = 'Preferred installation date: ' . sanitize_text_field( $b['install_date'] );
		}
		if ( ! empty( $b['notes'] ) ) {
			$notes[] = sanitize_textarea_field( $b['notes'] );
		}
		$c['type'] = ! empty( $c['kra'] ) ? 'Business' : 'Home';
		$doc = Soko_Ledger::save_doc(
			array(
				'type'     => 'invoice',
				'customer' => $c,
				'lines'    => $lines,
				'source'   => 'web',
				'notes'    => implode( "\n", $notes ),
				'due'      => soko_today(),
				'meta'     => array( 'delivery' => $di, 'install_date' => isset( $b['install_date'] ) ? sanitize_text_field( $b['install_date'] ) : '' ),
			)
		);
		global $wpdb;
		$wpdb->update( soko_t( 'docs' ), array( 'fulfil' => 'awaiting_payment' ), array( 'id' => $doc['id'] ) );
		Soko_Cron::notify( 'New website order ' . $doc['number'], sprintf( "%s (%s) placed order %s for %s.\n\nOpen Dynamic Desk → Sales to see it.", $doc['customer']['name'], soko_phone_display( $doc['customer']['phone'] ), $doc['number'], soko_money( $doc['total'] ) ) );
		$stk = Soko_Mpesa::stk( $doc, ! empty( $b['pay_phone'] ) ? $b['pay_phone'] : $c['phone'] );
		return array(
			'number' => $doc['number'],
			'token'  => $doc['token'],
			'total'  => $doc['total'],
			'stk'    => $stk,
			'pay'    => Soko_Mpesa::instructions( $doc['number'] ),
			'url'    => $doc['pay_url'],
		);
	}

	private static function public_doc( $req ) {
		$d = Soko_Ledger::doc( sanitize_text_field( $req['doc'] ) );
		if ( ! $d || ! hash_equals( (string) $d['token'], (string) $req['t'] ) ) {
			return new WP_Error( 'soko', 'Document not found.', array( 'status' => 404 ) );
		}
		return $d;
	}

	public static function store_pay( $req ) {
		$t = self::throttle( 'pay', 10 );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$d = self::public_doc( $req );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( 'void' === $d['status'] || 'void' === $d['state'] || $d['balance'] <= 0 ) {
			return new WP_Error( 'soko', 'void' === $d['state'] || 'void' === $d['status'] ? 'This order was cancelled. Please call us if you would like to order again.' : 'Nothing to pay on this order.', array( 'status' => 400 ) );
		}
		$amt = isset( $req['amount'] ) && (float) $req['amount'] > 0 ? min( (float) $req['amount'], $d['balance'] ) : null;
		return Soko_Mpesa::stk( $d, $req['phone'], $amt );
	}

	public static function store_status( $req ) {
		$d = self::public_doc( $req );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$stk = ! empty( $req['checkout'] ) ? Soko_Mpesa::stk_status( sanitize_text_field( $req['checkout'] ) ) : null;
		$receipts = array();
		global $wpdb;
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT number, token, total, date FROM ' . soko_t( 'docs' ) . " WHERE type = 'receipt' AND parent_id = %d ORDER BY id", $d['id'] ), ARRAY_A ) as $r ) { // phpcs:ignore
			$r['pdf']   = rest_url( self::NS . '/doc-pdf?doc=' . rawurlencode( $r['number'] ) . '&t=' . $r['token'] );
			unset( $r['token'] );
			$receipts[] = $r;
		}
		return array(
			'number'   => $d['number'],
			'state'    => $d['state'],
			'total'    => $d['total'],
			'paid'     => $d['paid'],
			'balance'  => 'void' === $d['state'] ? 0 : $d['balance'],
			'void'     => 'void' === $d['state'],
			'fulfil'   => $d['fulfil'],
			'stk'      => $stk ? array( 'status' => $stk['status'], 'message' => $stk['message'] ) : null,
			'pdf'      => rest_url( self::NS . '/doc-pdf?doc=' . rawurlencode( $d['number'] ) . '&t=' . $d['token'] ),
			'receipts' => $receipts,
		);
	}

	public static function doc_pdf_public( $req ) {
		$d = self::public_doc( $req );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		self::send_file( Soko_PDF::doc( $d ), 'application/pdf', $d['number'] . '.pdf', ! empty( $req['download'] ) );
	}

	public static function store_lead( $req ) {
		$t = self::throttle( 'lead', 8 );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$b = $req->get_json_params();
		if ( ! empty( $b['website'] ) ) {
			return array( 'ok' => true );
		}
		if ( empty( $b['name'] ) || strlen( soko_phone( isset( $b['phone'] ) ? $b['phone'] : '' ) ) !== 12 ) {
			return new WP_Error( 'soko', 'Please enter your name and a valid phone number.', array( 'status' => 400 ) );
		}
		if ( ! empty( $b['email'] ) && ! is_email( $b['email'] ) ) {
			return new WP_Error( 'soko', 'Please check your email address, for example name@gmail.com.', array( 'status' => 400 ) );
		}
		global $wpdb;
		$wpdb->insert(
			soko_t( 'leads' ),
			array(
				'name'     => sanitize_text_field( $b['name'] ),
				'phone'    => soko_phone( $b['phone'] ),
				'email'    => sanitize_email( isset( $b['email'] ) ? $b['email'] : '' ),
				'source'   => sanitize_text_field( isset( $b['source'] ) ? $b['source'] : 'Website' ),
				'stage'    => 'New',
				'interest' => sanitize_text_field( isset( $b['interest'] ) ? $b['interest'] : '' ),
				'county'   => sanitize_text_field( isset( $b['county'] ) ? $b['county'] : '' ),
				'value'    => isset( $b['value'] ) ? (float) $b['value'] : 0,
				'notes'    => sanitize_textarea_field( isset( $b['message'] ) ? $b['message'] : '' ),
				'data'     => wp_json_encode( isset( $b['data'] ) ? $b['data'] : array() ),
				'created'  => soko_now(),
				'updated'  => soko_now(),
			)
		);
		Soko_Cron::notify( 'New enquiry from ' . sanitize_text_field( $b['name'] ), sprintf( "%s (%s) asked about: %s\n\n%s\n\nOpen Dynamic Desk → Customers and leads.", sanitize_text_field( $b['name'] ), sanitize_text_field( $b['phone'] ), sanitize_text_field( isset( $b['interest'] ) ? $b['interest'] : '' ), sanitize_textarea_field( isset( $b['message'] ) ? $b['message'] : '' ) ) );
		return array( 'ok' => true );
	}

	public static function store_find( $req ) {
		$t = self::throttle( 'find', 10 );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$b = $req->get_json_params();
		$d = Soko_Ledger::doc( strtoupper( sanitize_text_field( isset( $b['number'] ) ? $b['number'] : '' ) ) );
		if ( ! $d || 'invoice' !== $d['type'] || ! $d['customer'] || soko_phone( $d['customer']['phone'] ) !== soko_phone( isset( $b['phone'] ) ? $b['phone'] : '' ) ) {
			return new WP_Error( 'soko', 'We could not find an order with that number and phone.', array( 'status' => 404 ) );
		}
		return array( 'url' => $d['pay_url'] );
	}

	public static function mpesa_callback( $req ) {
		$cfg = soko_setting( 'mpesa' );
		if ( empty( $cfg['token'] ) || ! Soko_Mpesa::ready() || ! hash_equals( (string) $cfg['token'], (string) $req['token'] ) ) {
			return new WP_Error( 'soko', 'Not found', array( 'status' => 404 ) );
		}
		$b = $req->get_json_params();
		if ( 'stk' === $req['what'] ) {
			Soko_Mpesa::on_stk( $b );
		} elseif ( 'confirm' === $req['what'] ) {
			Soko_Mpesa::on_c2b( $b );
		}
		return array( 'ResultCode' => 0, 'ResultDesc' => 'Accepted' );
	}

	/* ================================================================= desk */

	public static function me() {
		$u    = wp_get_current_user();
		$mods = array();
		foreach ( soko_modules() as $k => $m ) {
			if ( soko_can( $m[1] ) ) {
				$mods[] = array( 'id' => $k, 'label' => $m[0], 'group' => $m[2] );
			}
		}
		$role = 'Administrator';
		foreach ( soko_roles() as $r => $def ) {
			if ( in_array( $r, (array) $u->roles, true ) ) {
				$role = $def[0];
			}
		}
		return array(
			'id'       => $u->ID,
			'name'     => $u->display_name,
			'first'    => $u->first_name ? $u->first_name : $u->display_name,
			'role'     => $role,
			'admin'    => current_user_can( 'manage_options' ),
			'modules'  => $mods,
			'settings' => Soko_Settings::public_view( soko_can( 'soko_settings' ) ),
			'cats'     => soko_categories(),
			'version'  => SOKO_VERSION,
			'site'     => home_url( '/' ),
			'callbacks' => soko_can( 'soko_settings' ) ? array( 'stk' => Soko_Mpesa::callback_url( 'stk' ), 'confirm' => Soko_Mpesa::callback_url( 'confirm' ), 'validate' => Soko_Mpesa::callback_url( 'validate' ) ) : null,
			'mpesa_ready' => Soko_Mpesa::ready(),
		);
	}

	public static function search( $req ) {
		global $wpdb;
		$q    = sanitize_text_field( $req['q'] );
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$out  = array();
		if ( soko_can( 'soko_sales' ) ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT d.id, d.type, d.number, d.total, c.name FROM ' . soko_t( 'docs' ) . ' d LEFT JOIN ' . soko_t( 'customers' ) . ' c ON c.id = d.customer_id WHERE d.number LIKE %s OR c.name LIKE %s OR c.phone LIKE %s ORDER BY d.id DESC LIMIT 8', $like, $like, $like ), ARRAY_A ) as $r ) { // phpcs:ignore
				$out[] = array( 'kind' => $r['type'], 'id' => (int) $r['id'], 'title' => $r['number'] . ' · ' . $r['name'], 'sub' => soko_money( $r['total'] ) );
			}
		}
		if ( soko_can( 'soko_payments' ) ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, code, amount, payer FROM ' . soko_t( 'payments' ) . ' WHERE code LIKE %s OR payer LIKE %s OR phone LIKE %s LIMIT 5', $like, $like, $like ), ARRAY_A ) as $r ) { // phpcs:ignore
				$out[] = array( 'kind' => 'payment', 'id' => (int) $r['id'], 'title' => $r['code'] . ' · ' . $r['payer'], 'sub' => soko_money( $r['amount'] ) );
			}
		}
		if ( soko_can( 'soko_crm' ) ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, phone FROM ' . soko_t( 'customers' ) . ' WHERE name LIKE %s OR phone LIKE %s LIMIT 5', $like, $like ), ARRAY_A ) as $r ) { // phpcs:ignore
				$out[] = array( 'kind' => 'customer', 'id' => (int) $r['id'], 'title' => $r['name'], 'sub' => soko_phone_display( $r['phone'] ) );
			}
		}
		if ( soko_can( 'soko_stock' ) ) {
			foreach ( Soko_Ledger::products( array( 'q' => $q ) ) as $i => $p ) {
				if ( $i > 4 ) {
					break;
				}
				$out[] = array( 'kind' => 'product', 'id' => $p['id'], 'title' => $p['name'], 'sub' => $p['stock'] . ' in stock' );
			}
		}
		return $out;
	}

	/* ---- generic records ---- */

	public static function list_entity( $name, $req ) {
		global $wpdb;
		$e     = self::entities()[ $name ];
		$t     = soko_t( $e[0] );
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $req['q'] ) ) {
			$like  = '%' . $wpdb->esc_like( sanitize_text_field( $req['q'] ) ) . '%';
			$parts = array();
			foreach ( $e[3] as $f ) {
				$parts[] = "$f LIKE %s";
				$vals[]  = $like;
			}
			$where[] = '(' . implode( ' OR ', $parts ) . ')';
		}
		if ( 'events' === $name && ! empty( $req['from'] ) ) {
			$where[] = 'start >= %s';
			$vals[]  = sanitize_text_field( $req['from'] ) . ' 00:00:00';
		}
		if ( 'events' === $name && ! empty( $req['to'] ) ) {
			$where[] = 'start <= %s';
			$vals[]  = sanitize_text_field( $req['to'] ) . ' 23:59:59';
		}
		if ( 'expenses' === $name && ! empty( $req['from'] ) ) {
			$where[] = 'date BETWEEN %s AND %s';
			$vals[]  = sanitize_text_field( $req['from'] );
			$vals[]  = sanitize_text_field( $req['to'] );
		}
		$order = array( 'events' => 'start ASC', 'expenses' => 'date DESC, id DESC', 'licences' => 'expires ASC', 'jobs' => 'date ASC, id DESC' );
		$sql   = "SELECT * FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY ' . ( isset( $order[ $name ] ) ? $order[ $name ] : 'id DESC' ) . ' LIMIT 1000';
		$rows  = $vals ? $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
		if ( 'customers' === $name ) {
			$D = soko_t( 'docs' );
			$agg = $wpdb->get_results( "SELECT customer_id, SUM(total) t, SUM(paid) p, COUNT(*) n FROM $D WHERE type = 'invoice' AND status <> 'void' GROUP BY customer_id", OBJECT_K ); // phpcs:ignore
			foreach ( $rows as &$r ) {
				$a        = isset( $agg[ $r['id'] ] ) ? $agg[ $r['id'] ] : null;
				$r['ltv'] = $a ? (float) $a->t : 0;
				$r['owes'] = $a ? max( 0, (float) $a->t - (float) $a->p ) : 0;
				$r['invoices'] = $a ? (int) $a->n : 0;
			}
		}
		if ( in_array( $name, array( 'jobs' ), true ) ) {
			foreach ( $rows as &$r ) {
				$c              = $r['customer_id'] ? Soko_Ledger::customer( $r['customer_id'] ) : null;
				$r['customer']  = $c ? $c['name'] : '';
				$r['phone']     = $c ? $c['phone'] : '';
				$r['checklist'] = soko_json( $r['checklist'] );
				$r['doc_number'] = $r['doc_id'] ? $wpdb->get_var( $wpdb->prepare( 'SELECT number FROM ' . soko_t( 'docs' ) . ' WHERE id = %d', $r['doc_id'] ) ) : ''; // phpcs:ignore
			}
		}
		if ( 'leads' === $name ) {
			foreach ( $rows as &$r ) {
				$r['data'] = soko_json( $r['data'] );
			}
		}
		return $rows;
	}

	public static function save_entity( $name, $req, $id = 0 ) {
		global $wpdb;
		$e   = self::entities()[ $name ];
		$b   = $req->get_json_params();
		$row = array();
		foreach ( $e[2] as $f ) {
			if ( array_key_exists( $f, (array) $b ) ) {
				$v = $b[ $f ];
				if ( is_array( $v ) ) {
					$v = wp_json_encode( $v );
				} elseif ( in_array( $f, array( 'notes' ), true ) ) {
					$v = sanitize_textarea_field( $v );
				} elseif ( 'email' === $f ) {
					$v = sanitize_email( $v );
				} elseif ( 'phone' === $f ) {
					$v = soko_phone( $v );
				} elseif ( in_array( $f, array( 'date', 'expires', 'next_action' ), true ) ) {
					$v = $v ? sanitize_text_field( $v ) : null;
				} else {
					$v = is_numeric( $v ) && ! in_array( $f, array( 'number', 'kra', 'reference' ), true ) ? $v + 0 : sanitize_text_field( $v );
				}
				$row[ $f ] = $v;
			}
		}
		if ( 'expenses' === $name && ! isset( $row['user_id'] ) ) {
			$row['user_id'] = get_current_user_id();
		}
		$t = soko_t( $e[0] );
		if ( $id ) {
			if ( in_array( $name, array( 'leads', 'jobs' ), true ) ) {
				$row['updated'] = soko_now();
			}
			if ( 'licences' === $name && isset( $row['expires'] ) ) {
				$row['reminded'] = 0;
			}
			$wpdb->update( $t, $row, array( 'id' => $id ) );
		} else {
			$row['created'] = soko_now();
			if ( in_array( $name, array( 'leads', 'jobs' ), true ) ) {
				$row['updated'] = soko_now();
			}
			$wpdb->insert( $t, $row );
			$id = (int) $wpdb->insert_id;
		}
		if ( 'leads' === $name && isset( $row['stage'] ) && 'Won' === $row['stage'] ) {
			$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
			if ( ! $lead['customer_id'] ) {
				$cid = Soko_Ledger::customer_for( array( 'name' => $lead['name'], 'phone' => $lead['phone'], 'email' => $lead['email'], 'county' => $lead['county'] ) );
				$wpdb->update( $t, array( 'customer_id' => $cid ), array( 'id' => $id ) );
			}
		}
		soko_log( ( $req['id'] ? 'Updated ' : 'Added ' ) . rtrim( $name, 's' ), $name, $id );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
	}

	public static function delete_entity( $name, $id ) {
		global $wpdb;
		$e = self::entities()[ $name ];
		$wpdb->delete( soko_t( $e[0] ), array( 'id' => $id ) );
		soko_log( 'Deleted ' . rtrim( $name, 's' ), $name, $id );
		return array( 'deleted' => $id );
	}

	/* ---- products ---- */

	public static function products( $req ) {
		$ps = Soko_Ledger::products( array( 'q' => $req['q'], 'cat' => $req['cat'] ) );
		if ( ! soko_can( 'soko_stock' ) && ! soko_can( 'soko_reports' ) ) {
			foreach ( $ps as &$p ) {
				unset( $p['cost'] );
			}
		}
		return $ps;
	}

	public static function save_product( $req ) {
		$b = $req->get_json_params();
		if ( empty( $b['name'] ) && empty( $req['id'] ) ) {
			return new WP_Error( 'soko', 'Give the product a name.', array( 'status' => 400 ) );
		}
		return Soko_Ledger::save_product( $b, (int) $req['id'] );
	}

	public static function delete_product( $req ) {
		global $wpdb;
		$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . soko_t( 'lines' ) . ' WHERE product_id = %d', $req['id'] ) ); // phpcs:ignore
		if ( $used ) {
			$wpdb->update( soko_t( 'products' ), array( 'visible' => 0 ), array( 'id' => (int) $req['id'] ) );
			return array( 'hidden' => true, 'message' => 'This product is on past invoices, so it was hidden from the shop instead of deleted.' );
		}
		$wpdb->delete( soko_t( 'products' ), array( 'id' => (int) $req['id'] ) );
		return array( 'deleted' => (int) $req['id'] );
	}

	public static function moves( $req ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT m.*, u.display_name AS user FROM ' . soko_t( 'moves' ) . " m LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id WHERE product_id = %d ORDER BY m.id DESC LIMIT 100", $req['id'] ), ARRAY_A ); // phpcs:ignore
	}

	public static function adjust( $req ) {
		$b = $req->get_json_params();
		$q = (float) $b['qty'];
		if ( ! $q ) {
			return new WP_Error( 'soko', 'Enter a quantity.', array( 'status' => 400 ) );
		}
		Soko_Ledger::move( (int) $req['id'], $q, sanitize_text_field( $b['reason'] ? $b['reason'] : 'Adjustment' ), sanitize_text_field( isset( $b['ref'] ) ? $b['ref'] : '' ) );
		return Soko_Ledger::product( (int) $req['id'] );
	}

	public static function save_categories( $req ) {
		$out = array();
		foreach ( (array) $req->get_json_params() as $c ) {
			if ( empty( $c['name'] ) ) {
				continue;
			}
			$out[] = array(
				'id'    => ! empty( $c['id'] ) ? sanitize_key( $c['id'] ) : sanitize_key( sanitize_title( $c['name'] ) ),
				'name'  => sanitize_text_field( $c['name'] ),
				'icon'  => in_array( $c['icon'], soko_icon_names(), true ) ? $c['icon'] : 'panel',
				'group' => sanitize_text_field( $c['group'] ? $c['group'] : 'More' ),
				'image' => isset( $c['image'] ) ? esc_url_raw( $c['image'] ) : '',
			);
		}
		update_option( 'soko_categories', soko_rel( $out ), false );
		return $out;
	}

	/* ---- documents ---- */

	public static function docs( $req ) {
		global $wpdb;
		$D     = soko_t( 'docs' );
		$C     = soko_t( 'customers' );
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $req['type'] ) ) {
			$where[] = 'd.type = %s';
			$vals[]  = sanitize_key( $req['type'] );
		}
		if ( ! empty( $req['customer'] ) ) {
			$where[] = 'd.customer_id = %d';
			$vals[]  = (int) $req['customer'];
		}
		if ( ! empty( $req['source'] ) ) {
			$where[] = 'd.source = %s';
			$vals[]  = sanitize_key( $req['source'] );
		}
		if ( ! empty( $req['q'] ) ) {
			$like    = '%' . $wpdb->esc_like( sanitize_text_field( $req['q'] ) ) . '%';
			$where[] = '(d.number LIKE %s OR c.name LIKE %s OR c.phone LIKE %s)';
			array_push( $vals, $like, $like, $like );
		}
		if ( ! empty( $req['from'] ) ) {
			$where[] = 'd.date BETWEEN %s AND %s';
			$vals[]  = sanitize_text_field( $req['from'] );
			$vals[]  = sanitize_text_field( $req['to'] );
		}
		$sql  = "SELECT d.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email FROM $D d LEFT JOIN $C c ON c.id = d.customer_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY d.id DESC LIMIT 500';
		$rows = $vals ? $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( $rows as $r ) {
			$d = Soko_Ledger::doc_out( $r, false );
			$d['customer_name']  = $r['customer_name'];
			$d['customer_phone'] = $r['customer_phone'];
			$d['customer_email'] = $r['customer_email'];
			if ( ! empty( $req['state'] ) && $req['state'] !== $d['state'] ) {
				continue;
			}
			$out[] = $d;
		}
		return $out;
	}

	public static function get_doc( $req ) {
		global $wpdb;
		$d = Soko_Ledger::doc( (int) $req['id'] );
		if ( ! $d ) {
			return new WP_Error( 'soko', 'Not found', array( 'status' => 404 ) );
		}
		$d['payments'] = $wpdb->get_results( $wpdb->prepare( 'SELECT p.*, r.number AS receipt FROM ' . soko_t( 'payments' ) . ' p LEFT JOIN ' . soko_t( 'docs' ) . ' r ON r.id = p.receipt_id WHERE p.doc_id = %d ORDER BY p.received', $d['id'] ), ARRAY_A ); // phpcs:ignore
		$d['children'] = $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, number, total FROM ' . soko_t( 'docs' ) . ' WHERE parent_id = %d', $d['id'] ), ARRAY_A ); // phpcs:ignore
		if ( $d['parent_id'] ) {
			$d['parent'] = $wpdb->get_row( $wpdb->prepare( 'SELECT id, type, number FROM ' . soko_t( 'docs' ) . ' WHERE id = %d', $d['parent_id'] ), ARRAY_A ); // phpcs:ignore
		}
		$d['stk'] = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'stk' ) . ' WHERE doc_id = %d ORDER BY id DESC LIMIT 5', $d['id'] ), ARRAY_A ); // phpcs:ignore
		$d['public_pdf'] = rest_url( self::NS . '/doc-pdf?doc=' . rawurlencode( $d['number'] ) . '&t=' . $d['token'] );
		return $d;
	}

	public static function save_doc( $req ) {
		$b  = $req->get_json_params();
		$id = (int) $req['id'];
		if ( $id ) {
			$cur = Soko_Ledger::doc( $id );
			if ( $cur && 'invoice' === $cur['type'] && $cur['paid'] > 0 && ! current_user_can( 'manage_options' ) && ! soko_can( 'soko_settings' ) ) {
				return new WP_Error( 'soko', 'This invoice has payments on it. Ask the owner to change it, or issue a new invoice.', array( 'status' => 400 ) );
			}
			$b['type'] = $cur['type'];
		}
		if ( empty( $b['lines'] ) ) {
			return new WP_Error( 'soko', 'Add at least one item.', array( 'status' => 400 ) );
		}
		$d = Soko_Ledger::save_doc( $b, $id );
		if ( ! empty( $b['send'] ) && $d['customer'] && $d['customer']['email'] ) {
			Soko_Ledger::email_doc( $d, $d['customer']['email'] );
		}
		return $d;
	}

	public static function doc_action( $req ) {
		$b  = $req->get_json_params();
		$id = (int) $req['id'];
		$d  = Soko_Ledger::doc( $id );
		if ( ! $d ) {
			return new WP_Error( 'soko', 'Not found', array( 'status' => 404 ) );
		}
		switch ( $b['action'] ) {
			case 'convert':
				$r = Soko_Ledger::convert( $id, ! empty( $b['deposit'] ) );
				return is_wp_error( $r ) ? $r : array( 'doc' => $r, 'message' => 'Invoice ' . $r['number'] . ' created from ' . $d['number'] . '.' );
			case 'status':
				return array( 'doc' => Soko_Ledger::set_status( $id, $b['status'] ), 'message' => 'Updated.' );
			case 'email':
				$to = sanitize_email( ! empty( $b['to'] ) ? $b['to'] : ( $d['customer'] ? $d['customer']['email'] : '' ) );
				if ( ! $to ) {
					return new WP_Error( 'soko', 'Add an email address for this customer first.', array( 'status' => 400 ) );
				}
				$ok = Soko_Ledger::email_doc( $d, $to, isset( $b['message'] ) ? sanitize_textarea_field( $b['message'] ) : '' );
				if ( 'quote' === $d['type'] && 'draft' === $d['status'] ) {
					Soko_Ledger::set_status( $id, 'sent' );
				}
				soko_log( 'Emailed ' . $d['number'] . ' to ' . $to, $d['type'], $d['number'] );
				return array( 'ok' => $ok, 'message' => $ok ? 'Sent to ' . $to . '.' : 'The email could not be sent. Check the site email settings.' );
			case 'stk':
				$r = Soko_Mpesa::stk( $d, ! empty( $b['phone'] ) ? $b['phone'] : ( $d['customer'] ? $d['customer']['phone'] : '' ), ! empty( $b['amount'] ) ? (float) $b['amount'] : null );
				return $r;
			case 'deliver':
				global $wpdb;
				Soko_Ledger::fulfil_stock( $d );
				$wpdb->update( soko_t( 'docs' ), array( 'fulfil' => 'delivered' ), array( 'id' => $id ) );
				soko_log( 'Delivered ' . $d['number'], 'invoice', $d['number'] );
				return array( 'doc' => Soko_Ledger::doc( $id ), 'message' => 'Marked delivered. Stock updated.' );
			case 'fulfil':
				global $wpdb;
				$wpdb->update( soko_t( 'docs' ), array( 'fulfil' => sanitize_key( $b['fulfil'] ) ), array( 'id' => $id ) );
				return array( 'doc' => Soko_Ledger::doc( $id ), 'message' => 'Updated.' );
			case 'etims':
				return array( 'doc' => Soko_Etims::record( $id, isset( $b['ref'] ) ? $b['ref'] : '' ), 'message' => 'eTIMS number saved.' );
			case 'void':
				if ( $d['paid'] > 0 ) {
					return new WP_Error( 'soko', 'This invoice has payments. Refund or move the payments first.', array( 'status' => 400 ) );
				}
				return array( 'doc' => Soko_Ledger::set_status( $id, 'void' ), 'message' => $d['number'] . ' cancelled.' );
			case 'duplicate':
				$n = Soko_Ledger::save_doc( array( 'type' => $d['type'], 'customer_id' => $d['customer_id'], 'lines' => $d['lines'], 'notes' => $d['notes'], 'status' => 'quote' === $d['type'] ? 'draft' : 'issued' ) );
				return array( 'doc' => $n, 'message' => $n['number'] . ' created as a copy.' );
			case 'job':
				global $wpdb;
				$wpdb->insert(
					soko_t( 'jobs' ),
					array(
						'title'       => sanitize_text_field( ! empty( $b['title'] ) ? $b['title'] : 'Installation for ' . $d['number'] ),
						'customer_id' => $d['customer_id'],
						'doc_id'      => $d['id'],
						'stage'       => 'Survey',
						'location'    => $d['customer'] ? trim( $d['customer']['address'] . ' ' . $d['customer']['county'] ) : '',
						'checklist'   => '[]',
						'created'     => soko_now(),
						'updated'     => soko_now(),
					)
				);
				return array( 'message' => 'Installation booked. Set the date and team.', 'job_id' => (int) $wpdb->insert_id );
		}
		return new WP_Error( 'soko', 'Unknown action.', array( 'status' => 400 ) );
	}

	public static function doc_pdf( $req ) {
		$d = Soko_Ledger::doc( (int) $req['id'] );
		if ( ! $d ) {
			return new WP_Error( 'soko', 'Not found', array( 'status' => 404 ) );
		}
		self::send_file( Soko_PDF::doc( $d ), 'application/pdf', $d['number'] . '.pdf', ! empty( $req['download'] ) );
	}

	private static function send_file( $body, $mime, $name, $download = true ) {
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( $body ) );
		echo $body; // phpcs:ignore
		exit;
	}

	/* ---- payments ---- */

	public static function payments( $req ) {
		global $wpdb;
		$P    = soko_t( 'payments' );
		$D    = soko_t( 'docs' );
		$rows = $wpdb->get_results( "SELECT p.*, d.number AS invoice, r.number AS receipt FROM $P p LEFT JOIN $D d ON d.id = p.doc_id LEFT JOIN $D r ON r.id = p.receipt_id ORDER BY p.received DESC LIMIT 500", ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			unset( $r['raw'] );
			$r['amount'] = (float) $r['amount'];
		}
		return $rows;
	}

	public static function add_payment( $req ) {
		$b = $req->get_json_params();
		if ( empty( $b['amount'] ) || (float) $b['amount'] <= 0 ) {
			return new WP_Error( 'soko', 'Enter the amount received.', array( 'status' => 400 ) );
		}
		if ( 'mpesa' === $b['method'] && empty( $b['code'] ) ) {
			return new WP_Error( 'soko', 'Enter the M-Pesa transaction code.', array( 'status' => 400 ) );
		}
		$doc = ! empty( $b['doc_id'] ) ? Soko_Ledger::doc( (int) $b['doc_id'] ) : null;
		$p   = Soko_Ledger::record_payment(
			array(
				'method'      => sanitize_key( $b['method'] ),
				'code'        => isset( $b['code'] ) ? $b['code'] : '',
				'amount'      => (float) $b['amount'],
				'phone'       => isset( $b['phone'] ) ? $b['phone'] : ( $doc && $doc['customer'] ? $doc['customer']['phone'] : '' ),
				'payer'       => isset( $b['payer'] ) && $b['payer'] ? $b['payer'] : ( $doc && $doc['customer'] ? $doc['customer']['name'] : '' ),
				'account_ref' => $doc ? $doc['number'] : ( isset( $b['account_ref'] ) ? $b['account_ref'] : '' ),
				'doc_id'      => $doc ? $doc['id'] : 0,
				'received'    => ! empty( $b['date'] ) ? sanitize_text_field( $b['date'] ) . ' ' . current_time( 'H:i:s' ) : '',
			)
		);
		return array( 'payment' => $p, 'message' => 'matched' === $p['status'] ? 'Payment recorded and receipt issued.' : 'Payment recorded. Match it to an invoice.' );
	}

	public static function match_payment( $req ) {
		$b = $req->get_json_params();
		$r = Soko_Ledger::apply_payment( (int) $req['id'], (int) $b['doc_id'] );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'message' => 'Matched to ' . $r['invoice']['number'] . '. Receipt ' . $r['receipt']['number'] . ' issued.' );
	}

	/* ---- purchases ---- */

	public static function purchases() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT p.*, s.name AS supplier FROM ' . soko_t( 'purchases' ) . ' p LEFT JOIN ' . soko_t( 'suppliers' ) . ' s ON s.id = p.supplier_id ORDER BY p.id DESC LIMIT 300', ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			$r['items'] = soko_json( $r['items'] );
		}
		return $rows;
	}

	public static function save_purchase( $req ) {
		global $wpdb;
		$b     = $req->get_json_params();
		$lines = array();
		$total = 0;
		foreach ( (array) $b['lines'] as $l ) {
			$q = (float) $l['qty'];
			$c = (float) $l['cost'];
			if ( $q <= 0 ) {
				continue;
			}
			$p       = ! empty( $l['product_id'] ) ? Soko_Ledger::product( (int) $l['product_id'] ) : null;
			$lines[] = array( 'product_id' => $p ? $p['id'] : 0, 'name' => $p ? $p['name'] : sanitize_text_field( $l['name'] ), 'qty' => $q, 'cost' => $c );
			$total  += $q * $c;
		}
		$row = array(
			'supplier_id' => (int) $b['supplier_id'],
			'date'        => ! empty( $b['date'] ) ? sanitize_text_field( $b['date'] ) : soko_today(),
			'expected'    => ! empty( $b['expected'] ) ? sanitize_text_field( $b['expected'] ) : null,
			'items'       => wp_json_encode( $lines ),
			'total'       => $total,
			'vat'         => ! empty( $b['vat_included'] ) ? round( $total * soko_setting( 'vat_rate' ) / ( 100 + soko_setting( 'vat_rate' ) ), 2 ) : 0,
			'notes'       => sanitize_textarea_field( isset( $b['notes'] ) ? $b['notes'] : '' ),
		);
		$id = (int) $req['id'];
		if ( $id ) {
			$wpdb->update( soko_t( 'purchases' ), $row, array( 'id' => $id ) );
		} else {
			$row['number']  = Soko_Settings::next_number( 'po' );
			$row['status']  = 'ordered';
			$row['created'] = soko_now();
			$wpdb->insert( soko_t( 'purchases' ), $row );
			$id = (int) $wpdb->insert_id;
		}
		soko_log( 'Saved purchase order', 'purchase', $id );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'purchases' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
	}

	public static function receive_purchase( $req ) {
		global $wpdb;
		$b  = $req->get_json_params();
		$po = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'purchases' ) . ' WHERE id = %d', $req['id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! $po || 'received' === $po['status'] ) {
			return new WP_Error( 'soko', 'Already received.', array( 'status' => 400 ) );
		}
		foreach ( soko_json( $po['items'] ) as $l ) {
			if ( $l['product_id'] ) {
				Soko_Ledger::move( $l['product_id'], $l['qty'], 'Received', $po['number'] );
				if ( ! empty( $b['update_cost'] ) && $l['cost'] > 0 ) {
					$wpdb->update( soko_t( 'products' ), array( 'cost' => $l['cost'] ), array( 'id' => $l['product_id'] ) );
				}
			}
		}
		$wpdb->update(
			soko_t( 'purchases' ),
			array(
				'status'   => 'received',
				'received' => soko_now(),
				'paid'     => isset( $b['paid'] ) ? (float) $b['paid'] : (float) $po['total'],
			),
			array( 'id' => $po['id'] )
		);
		soko_log( 'Received ' . $po['number'] . ' into stock', 'purchase', $po['number'] );
		return array( 'message' => $po['number'] . ' received. Stock updated.' );
	}

	/* ---- reports ---- */

	public static function report( $req ) {
		$from = sanitize_text_field( $req['from'] ? $req['from'] : current_time( 'Y-m-01' ) );
		$to   = sanitize_text_field( $req['to'] ? $req['to'] : current_time( 'Y-m-t' ) );
		$fmt  = sanitize_key( $req['format'] );
		if ( in_array( $fmt, array( 'pdf', 'xlsx', 'csv' ), true ) ) {
			list( $body, $mime, $name ) = Soko_Reports::export( sanitize_key( $req['type'] ), $from, $to, $fmt );
			soko_log( 'Downloaded report ' . $name, 'report', $req['type'] );
			self::send_file( $body, $mime, $name, true );
		}
		return Soko_Reports::run( sanitize_key( $req['type'] ), $from, $to );
	}

	public static function activity() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT a.*, u.display_name AS user FROM ' . soko_t( 'activity' ) . " a LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id ORDER BY a.id DESC LIMIT 200", ARRAY_A ); // phpcs:ignore
	}

	/* ---- site, settings, users ---- */

	public static function save_site( $req ) {
		$site = Soko_Sections::save( $req->get_json_params() );
		soko_log( 'Published website changes', 'site', '' );
		return $site;
	}

	public static function save_settings( $req ) {
		$b   = $req->get_json_params();
		$cur = Soko_Settings::all();
		if ( isset( $b['mpesa'] ) ) {
			foreach ( array( 'key', 'secret', 'passkey' ) as $k ) {
				if ( isset( $b['mpesa'][ $k ] ) && 0 === strpos( (string) $b['mpesa'][ $k ], '••••' ) ) {
					$b['mpesa'][ $k ] = $cur['mpesa'][ $k ];
				}
			}
			$b['mpesa']['token'] = $cur['mpesa']['token'];
			delete_transient( 'soko_mpesa_token' );
		}
		if ( isset( $b['smtp']['pass'] ) && 0 === strpos( (string) $b['smtp']['pass'], '••••' ) ) {
			$b['smtp']['pass'] = $cur['smtp']['pass'];
		}
		Soko_Settings::save( $b );
		Soko_Cron::schedule();
		soko_log( 'Changed settings', 'settings', '' );
		return Soko_Settings::public_view( true );
	}

	public static function users() {
		$roles = array_keys( soko_roles() );
		$out   = array();
		foreach ( get_users( array( 'role__in' => array_merge( $roles, array( 'administrator' ) ) ) ) as $u ) {
			$role = 'administrator';
			foreach ( $roles as $r ) {
				if ( in_array( $r, (array) $u->roles, true ) ) {
					$role = $r;
				}
			}
			$last  = get_user_meta( $u->ID, 'soko_last_seen', true );
			$out[] = array(
				'id'       => $u->ID,
				'name'     => $u->display_name,
				'email'    => $u->user_email,
				'login'    => $u->user_login,
				'role'     => $role,
				'label'    => 'administrator' === $role ? 'Web team (support)' : soko_roles()[ $role ][0],
				'last'     => $last ? date_i18n( 'j M, H:i', $last ) : 'Never',
				'phone'    => get_user_meta( $u->ID, 'soko_phone', true ),
				'disabled' => (bool) get_user_meta( $u->ID, 'soko_disabled', true ),
			);
		}
		return array(
			'users' => $out,
			'roles' => array_map( function ( $r, $d ) { return array( 'id' => $r, 'label' => $d[0], 'caps' => array_values( $d[1] ) ); }, array_keys( soko_roles() ), soko_roles() ),
			'modules' => array_map( function ( $k, $m ) { return array( 'id' => $k, 'label' => $m[0], 'cap' => $m[1] ); }, array_keys( soko_modules() ), soko_modules() ),
		);
	}

	public static function team() {
		$out = array();
		foreach ( get_users( array( 'role__in' => array_keys( soko_roles() ) ) ) as $u ) {
			$out[] = array( 'id' => $u->ID, 'name' => $u->display_name );
		}
		return $out;
	}

	public static function save_user( $req ) {
		$b    = $req->get_json_params();
		$id   = (int) $req['id'];
		$role = sanitize_key( $b['role'] );
		if ( ! isset( soko_roles()[ $role ] ) ) {
			return new WP_Error( 'soko', 'Choose a role.', array( 'status' => 400 ) );
		}
		if ( $id ) {
			$u = get_userdata( $id );
			if ( ! $u || user_can( $u, 'manage_options' ) ) {
				return new WP_Error( 'soko', 'This is the web team support account and cannot be changed here.', array( 'status' => 400 ) );
			}
			wp_update_user( array( 'ID' => $id, 'display_name' => sanitize_text_field( $b['name'] ), 'user_email' => sanitize_email( $b['email'] ) ) );
			$u = new WP_User( $id );
			$u->set_role( $role );
			update_user_meta( $id, 'soko_phone', sanitize_text_field( isset( $b['phone'] ) ? $b['phone'] : '' ) );
			update_user_meta( $id, 'soko_disabled', ! empty( $b['disabled'] ) ? 1 : 0 );
			if ( ! empty( $b['reset'] ) ) {
				retrieve_password( $u->user_login );
			}
			soko_log( 'Updated team member ' . $b['name'], 'user', $id );
			return array( 'message' => 'Saved.' );
		}
		$email = sanitize_email( $b['email'] );
		if ( ! is_email( $email ) || email_exists( $email ) ) {
			return new WP_Error( 'soko', 'Use a valid email address that is not already in use.', array( 'status' => 400 ) );
		}
		$login = sanitize_user( strtolower( strtok( $email, '@' ) ), true );
		$base  = $login;
		$i     = 2;
		while ( username_exists( $login ) ) {
			$login = $base . $i++;
		}
		$pass = ! empty( $b['password'] ) ? $b['password'] : wp_generate_password( 14, true );
		$uid  = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => sanitize_text_field( $b['name'] ),
				'first_name'   => sanitize_text_field( strtok( $b['name'], ' ' ) ),
				'role'         => $role,
			)
		);
		if ( is_wp_error( $uid ) ) {
			return $uid;
		}
		update_user_meta( $uid, 'soko_phone', sanitize_text_field( isset( $b['phone'] ) ? $b['phone'] : '' ) );
		update_user_meta( $uid, 'show_admin_bar_front', 'false' );
		$sent = soko_mail(
			$email,
			'Your Dynamic Desk account',
			sprintf( "Hello %s,\n\nAn account has been created for you on Dynamic Desk.\n\nSign in: %s\nEmail: %s\nPassword: %s\n\nPlease change your password after signing in.\n\n%s", sanitize_text_field( $b['name'] ), home_url( '/desk/' ), $email, $pass, soko_setting( 'business' ) )
		);
		soko_log( 'Invited ' . $b['name'] . ' as ' . soko_roles()[ $role ][0], 'user', $uid );
		return array( 'id' => $uid, 'login' => $login, 'password' => empty( $b['password'] ) ? $pass : '', 'sent' => $sent, 'message' => 'Account created.' );
	}

	public static function test_email() {
		$to = soko_setting( 'notify_email' );
		$ok = soko_mail( $to, 'Dynamic Desk test email', 'Email from Dynamic Desk is working.' );
		return array( 'ok' => $ok, 'message' => $ok ? 'Test email sent to ' . $to . '.' : 'Sending failed. Fill in Outgoing email below with your hosting mailbox, save, then try again.' );
	}

	public static function demo( $req ) {
		$b = $req->get_json_params();
		if ( ! empty( $b['remove'] ) ) {
			Soko_Demo::remove();
			return array( 'message' => 'Sample data removed.' );
		}
		Soko_Demo::load();
		return array( 'message' => 'Sample data loaded.' );
	}
}
