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
			'expenses'  => array( 'expenses', 'soko_expenses', array( 'date', 'category', 'description', 'amount', 'vat', 'method', 'reference', 'supplier', 'supplier_pin', 'receipt' ), array( 'description', 'category', 'reference', 'supplier' ), array() ),
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
		$r( '/purchases/(?P<id>\d+)/pay', 'POST', array( __CLASS__, 'pay_purchase' ), self::perm( 'soko_purchases' ) );
		$r( '/purchases/(?P<id>\d+)/return', 'POST', array( __CLASS__, 'return_purchase' ), self::perm( 'soko_purchases' ) );
		$r( '/expenses/(?P<id>\d+)/pay', 'POST', array( __CLASS__, 'pay_expense' ), self::perm( 'soko_expenses' ) );
		$r( '/accounts/transfer', 'POST', array( __CLASS__, 'transfer' ), self::perm( 'soko_reports' ) );
		$r( '/accounts/depreciation', 'POST', array( __CLASS__, 'depreciation' ), self::perm( 'soko_reports' ) );
		$r( '/customers/(?P<id>\d+)/statement', 'GET', array( __CLASS__, 'statement' ), self::perm( 'soko_sales', array( 'soko_payments', 'soko_reports' ) ) );
		$r( '/purchases/(?P<id>\d+)/receive', 'POST', array( __CLASS__, 'receive_purchase' ), self::perm( 'soko_purchases', array( 'soko_stock' ) ) );

		$r( '/reports/(?P<type>[a-z]+)', 'GET', array( __CLASS__, 'report' ), self::perm( 'soko_reports' ) );
		$r( '/accounts', 'GET', array( __CLASS__, 'accounts' ), self::perm( 'soko_reports' ) );
		$r( '/accounts/opening', 'POST', array( __CLASS__, 'save_opening' ), self::perm( 'soko_reports' ) );
		$r( '/accounts', 'POST', function ( $req ) { $b = $req->get_json_params(); $c = Soko_Accounts::add_account( isset( $b['code'] ) ? $b['code'] : '', isset( $b['name'] ) ? $b['name'] : '', isset( $b['type'] ) ? $b['type'] : '' ); if ( is_wp_error( $c ) ) { return $c; } soko_log( 'Added account ' . $c, 'accounts', $c ); return array( 'message' => 'Account ' . $c . ' added.' ); }, self::perm( 'soko_reports' ) );
		$r( '/accounts/(?P<code>\d+)', 'DELETE', function ( $req ) { $x = Soko_Accounts::remove_account( (string) $req['code'] ); return is_wp_error( $x ) ? $x : array( 'message' => 'Account removed.' ); }, self::perm( 'soko_reports' ) );
		$r( '/accounts/(?P<code>\d+)/recon', 'GET', array( __CLASS__, 'recon' ), self::perm( 'soko_reports' ) );
		$r( '/accounts/(?P<code>\d+)/recon', 'POST', array( __CLASS__, 'save_recon' ), self::perm( 'soko_reports' ) );
		$r( '/journal', 'GET', array( __CLASS__, 'journals' ), self::perm( 'soko_reports' ) );
		$r( '/journal', 'POST', array( __CLASS__, 'save_journal' ), self::perm( 'soko_reports' ) );
		$r( '/journal/(?P<id>\d+)', 'DELETE', array( __CLASS__, 'delete_journal' ), self::perm( 'soko_reports' ) );
		$r( '/activity', 'GET', array( __CLASS__, 'activity' ), self::perm( 'soko_settings', array( 'soko_reports' ) ) );

		$r( '/blog', 'GET', function () { return Soko_Blog::posts( array( 'publish', 'draft' ) ); }, self::perm( 'soko_site' ) );
		$r( '/blog', 'POST', function ( $req ) { return Soko_Blog::save( $req->get_json_params() ); }, self::perm( 'soko_site' ) );
		$r( '/blog/(?P<id>\d+)', 'GET', function ( $req ) { $p = get_post( (int) $req['id'] ); return $p && 'post' === $p->post_type ? Soko_Blog::row( $p, true ) : new WP_Error( 'nf', 'Not found', array( 'status' => 404 ) ); }, self::perm( 'soko_site' ) );
		$r( '/blog/(?P<id>\d+)', 'DELETE', function ( $req ) { $p = get_post( (int) $req['id'] ); if ( $p && 'post' === $p->post_type ) { wp_trash_post( $p->ID ); soko_log( 'Deleted blog post', 'blog', $p->post_title ); } return array( 'ok' => true ); }, self::perm( 'soko_site' ) );
		$r( '/site', 'GET', function () { return Soko_Sections::site(); }, self::perm( 'soko_site' ) );
		$r( '/site', 'POST', array( __CLASS__, 'save_site' ), self::perm( 'soko_site' ) );
		$r( '/site/history', 'GET', function () { return Soko_Sections::history(); }, self::perm( 'soko_site' ) );
		$r( '/site/restore', 'POST', function ( $req ) { $b = $req->get_json_params(); return Soko_Sections::restore( (int) $b['i'] ); }, self::perm( 'soko_site' ) );
		$r( '/site/schema', 'GET', function () { return Soko_Sections::schema(); }, self::perm( 'soko_site' ) );

		$r( '/settings', 'GET', function () { return Soko_Settings::public_view( soko_can( 'soko_settings' ) ); }, $staff );
		$r( '/settings', 'POST', array( __CLASS__, 'save_settings' ), self::perm( 'soko_settings' ) );
		$r( '/system/update', 'POST', function () { return Soko_Updater::update_now(); }, function () { return current_user_can( 'update_plugins' ) || soko_can( 'soko_settings' ); } );
		$r( '/system/demo', 'POST', array( __CLASS__, 'demo' ), self::perm( 'soko_settings' ) );
		$r( '/system/test-email', 'POST', array( __CLASS__, 'test_email' ), self::perm( 'soko_settings' ) );

		$r( '/users', 'GET', array( __CLASS__, 'users' ), self::perm( 'soko_users' ) );
		$r( '/users', 'POST', array( __CLASS__, 'save_user' ), self::perm( 'soko_users' ) );
		$r( '/users/(?P<id>\d+)', 'POST', array( __CLASS__, 'save_user' ), self::perm( 'soko_users' ) );
		$r( '/cleanup', 'POST', array( __CLASS__, 'cleanup' ), function () { return current_user_can( 'manage_options' ); } );
		$r( '/me/password', 'POST', array( __CLASS__, 'my_password' ), $staff );
		$r( '/team', 'GET', array( __CLASS__, 'team' ), $staff );
	}

	/* ================================================================ store */

	/** A fixed 10-minute window per address, generous enough for shared mobile-network addresses. */
	private static function throttle( $key, $max = 20 ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$k   = 'soko_rl_' . md5( $key . $ip );
		$win = get_transient( $k );
		$win = is_array( $win ) ? $win : array( 'n' => 0, 'until' => time() + 10 * MINUTE_IN_SECONDS );
		if ( $win['n'] >= $max ) {
			$s = Soko_Settings::all();
			return new WP_Error( 'soko_slow', sprintf( 'We are receiving a lot of requests from your network. Please try again in a few minutes, or call or WhatsApp us on %s and we will take your order.', $s['phone'] ), array( 'status' => 429 ) );
		}
		++$win['n'];
		set_transient( $k, $win, max( 60, $win['until'] - time() ) );
		return true;
	}

	public static function store_search( $req ) {
		Soko_Ledger::$storefront = true;
		$q = sanitize_text_field( $req['q'] );
		if ( strlen( $q ) < 2 ) {
			return array();
		}
		return array_map(
			function ( $p ) {
				return array( 'name' => $p['name'], 'url' => $p['url'], 'price' => soko_money( $p['price'] ), 'cat' => $p['cat_name'], 'icon' => soko_icon( $p['icon'], 28 ), 'img' => $p['images'] ? $p['images'][0] : '' );
			},
			array_slice( self::rank_search( Soko_Ledger::products( array( 'visible' => 1, 'q' => $q ) ), $q ), 0, 6 )
		);
	}

	/* name starts with the words > name contains them > category matches > other (description, SKU) */
	public static function rank_search( $rows, $q ) {
		$q = strtolower( $q );
		$score = function ( $p ) use ( $q ) {
			$n = strtolower( $p['name'] );
			if ( 0 === strpos( $n, $q ) ) {
				return 0;
			}
			if ( preg_match( '/\\b' . preg_quote( $q, '/' ) . '/', $n ) ) {
				return 1;
			}
			if ( false !== strpos( $n, $q ) ) {
				return 2;
			}
			return false !== strpos( strtolower( (string) $p['cat_name'] ), $q ) ? 3 : 4;
		};
		foreach ( $rows as $i => &$r ) {
			$r['_s'] = $score( $r ) * 10000 + $i;
		}
		unset( $r );
		usort( $rows, function ( $a, $b ) { return $a['_s'] - $b['_s']; } );
		return $rows;
	}

	public static function store_checkout( $req ) {
		$t = self::throttle( 'checkout', 40 );
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
		$goods = false;
		$installing = false;
		foreach ( (array) ( isset( $b['items'] ) ? $b['items'] : array() ) as $it ) {
			$p = Soko_Ledger::product( (int) $it['id'] );
			if ( ! $p || ! $p['visible'] ) {
				continue;
			}
			if ( 'product' === $p['kind'] ) {
				$goods = true;
			}
			$qty     = max( 1, min( 500, (int) $it['qty'] ) );
			if ( 'product' === $p['kind'] && $p['available'] <= 0 && $qty > 20 ) {
				return new WP_Error( 'soko', sprintf( '%s is ordered in for you. For more than 20, please call us so we can confirm the delivery date.', $p['name'] ), array( 'status' => 409 ) );
			}
			if ( 'product' === $p['kind'] && $p['available'] > 0 && $qty > $p['available'] ) {
				return new WP_Error( 'soko', sprintf( 'Only %d × %s are available right now. Please lower the quantity, or call us to order more.', (int) $p['available'], $p['name'] ), array( 'status' => 409 ) );
			}
			$lines[] = array( 'product_id' => $p['id'], 'qty' => $qty );
			if ( ! empty( $it['install'] ) && $p['install_fee'] > 0 ) {
				$installing = true;
				$lines[] = array( 'description' => 'Installation: ' . $p['name'], 'qty' => $qty, 'price' => $p['install_fee'], 'vatable' => 1 );
			}
		}
		if ( ! $lines ) {
			return new WP_Error( 'soko', 'Your cart is empty.', array( 'status' => 400 ) );
		}
		if ( ! $goods && empty( $c['address'] ) ) {
			return new WP_Error( 'soko', 'Please add the address or location for the visit.', array( 'status' => 400 ) );
		}
		$del = soko_setting( 'delivery' );
		$di  = isset( $b['delivery'] ) ? (int) $b['delivery'] : 0;
		if ( $goods && ! $installing && isset( $del[ $di ] ) && (float) $del[ $di ]['fee'] > 0 ) {
			$lines[] = array( 'description' => 'Delivery: ' . $del[ $di ]['label'], 'qty' => 1, 'price' => (float) $del[ $di ]['fee'], 'vatable' => 1 );
		}
		$notes = array();
		if ( $installing ) {
			$notes[] = 'Delivery: delivered and installed by our team';
			$di      = -1;
		} elseif ( $goods && isset( $del[ $di ] ) ) {
			$notes[] = 'Delivery: ' . $del[ $di ]['label'] . ( preg_match( '/pick\s?-?up|collect/i', $del[ $di ]['label'] ) && soko_setting( 'pickup_address' ) ? ' at ' . soko_setting( 'pickup_address' ) : '' );
		}
		if ( ! empty( $b['install_date'] ) ) {
			$notes[] = ( $goods ? 'Preferred installation date: ' : 'Preferred visit date: ' ) . sanitize_text_field( $b['install_date'] );
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
				'meta'     => array( 'delivery' => $di, 'install' => $installing ? 1 : 0, 'install_date' => isset( $b['install_date'] ) ? sanitize_text_field( $b['install_date'] ) : '' ),
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
		$t = self::throttle( 'pay', 20 );
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
		$t = self::throttle( 'lead', 25 );
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
				'source'   => sanitize_text_field( isset( $b['source'] ) && $b['source'] ? $b['source'] : 'Website quote form' ),
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
		$t = self::throttle( 'find', 30 );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$b = $req->get_json_params();
		$num = strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( isset( $b['number'] ) ? $b['number'] : '' ) ) );
		if ( preg_match( '/^\d+$/', $num ) ) {
			$num = soko_setting( 'prefix_invoice', 'INV-' ) . $num; // "1057" means INV-1057
		} elseif ( preg_match( '/^([A-Z]+)(\d+)$/', $num, $m ) ) {
			$num = $m[1] . '-' . $m[2]; // "INV1057"
		}
		$d = Soko_Ledger::doc( $num );
		if ( ! $d || 'invoice' !== $d['type'] || ! $d['customer'] || soko_phone( $d['customer']['phone'] ) !== soko_phone( isset( $b['phone'] ) ? $b['phone'] : '' ) ) {
			return array( 'found' => false, 'message' => 'We could not find an order with that number and phone. Check the number on your confirmation message.' );
		}
		return array( 'found' => true, 'url' => $d['pay_url'] );
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
			'pay_number'  => ( $pi = Soko_Mpesa::instructions( '' ) ) && ! empty( $pi['number'] ) ? $pi['kind'] . ' ' . $pi['number'] : '',
		);
	}

	public static function search( $req ) {
		global $wpdb;
		$q    = sanitize_text_field( $req['q'] );
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$pl   = soko_phone_like( $q );
		$nl   = '%' . $wpdb->esc_like( strtoupper( preg_replace( '/[^a-z0-9]/i', '', $q ) ) ) . '%';
		$out  = array();
		if ( soko_can( 'soko_crm' ) ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, phone FROM ' . soko_t( 'customers' ) . ' WHERE name LIKE %s OR phone LIKE %s OR email LIKE %s LIMIT 5', $like, $pl, $like ), ARRAY_A ) as $r ) { // phpcs:ignore
				$out[] = array( 'kind' => 'customer', 'id' => (int) $r['id'], 'title' => $r['name'], 'sub' => soko_phone_display( $r['phone'] ) );
			}
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, phone, stage FROM ' . soko_t( 'leads' ) . " WHERE ( name LIKE %s OR phone LIKE %s OR interest LIKE %s ) AND stage NOT IN ('Won','Lost') LIMIT 4", $like, $pl, $like ), ARRAY_A ) as $r ) { // phpcs:ignore
				$out[] = array( 'kind' => 'lead', 'id' => (int) $r['id'], 'title' => $r['name'], 'sub' => $r['stage'] );
			}
		}
		if ( soko_can( 'soko_sales' ) ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT d.id, d.type, d.number, d.total, c.name FROM ' . soko_t( 'docs' ) . ' d LEFT JOIN ' . soko_t( 'customers' ) . " c ON c.id = d.customer_id WHERE d.number LIKE %s OR REPLACE(d.number, '-', '') LIKE %s OR c.name LIKE %s OR c.phone LIKE %s ORDER BY d.id DESC LIMIT 8", $like, $nl, $like, $pl ), ARRAY_A ) as $r ) { // phpcs:ignore
				$out[] = array( 'kind' => $r['type'], 'id' => (int) $r['id'], 'title' => $r['number'] . ' · ' . $r['name'], 'sub' => soko_money( $r['total'] ) );
			}
		}
		if ( soko_can( 'soko_payments' ) ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, code, amount, payer FROM ' . soko_t( 'payments' ) . ' WHERE code LIKE %s OR payer LIKE %s OR phone LIKE %s LIMIT 5', $like, $like, $pl ), ARRAY_A ) as $r ) { // phpcs:ignore
				$out[] = array( 'kind' => 'payment', 'id' => (int) $r['id'], 'title' => $r['code'] . ' · ' . $r['payer'], 'sub' => soko_money( $r['amount'] ) );
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
				$vals[]  = 'phone' === $f ? soko_phone_like( $req['q'] ) : $like;
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
			$agg = $wpdb->get_results( "SELECT customer_id, SUM(total) t, SUM(paid) p, COUNT(*) n, SUM(CASE WHEN total > paid THEN total - paid ELSE 0 END) o, SUM(CASE WHEN paid > total THEN paid - total ELSE 0 END) cr FROM $D WHERE type = 'invoice' AND status <> 'void' GROUP BY customer_id", OBJECT_K ); // phpcs:ignore
			foreach ( $rows as &$r ) {
				$a        = isset( $agg[ $r['id'] ] ) ? $agg[ $r['id'] ] : null;
				$r['ltv'] = $a ? (float) $a->t : 0;
				$r['owes'] = $a ? round( (float) $a->o, 2 ) : 0;
				$r['credit'] = $a ? round( (float) $a->cr, 2 ) : 0;
				$r['invoices'] = $a ? (int) $a->n : 0;
			}
		}
		if ( 'expenses' === $name ) {
			$pd = Soko_Accounts::po_paid_asat( soko_today() );
			foreach ( $rows as &$r ) {
				$r['unpaid'] = Soko_Accounts::is_unpaid_method( $r['method'] );
				$r['owed']   = $r['unpaid'] ? max( 0, round( (float) $r['amount'] - ( isset( $pd[ 'EXP-' . $r['id'] ] ) ? $pd[ 'EXP-' . $r['id'] ] : 0 ), 2 ) ) : 0;
			}
			unset( $r );
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
		if ( 'licences' === $name && ! $id && ( empty( $b['name'] ) || empty( $b['expires'] ) ) ) {
			return new WP_Error( 'soko', 'Enter the licence or permit name and its expiry date.', array( 'status' => 400 ) );
		}
		if ( in_array( $name, array( 'expenses' ), true ) && ! empty( $b['date'] ) && ( $lk = Soko_Accounts::locked( $b['date'] ) ) ) { // phpcs:ignore
			return $lk;
		}
		if ( 'expenses' === $name && ! empty( $b['date'] ) && $b['date'] > soko_today() ) {
			return new WP_Error( 'soko', 'The expense date is in the future. Use the date it was paid.', array( 'status' => 400 ) );
		}
		if ( 'expenses' === $name ) {
			if ( isset( $b['amount'] ) && (float) $b['amount'] <= 0 ) {
				return new WP_Error( 'soko', 'Enter an amount above zero.', array( 'status' => 400 ) );
			}
			if ( ! empty( $b['method'] ) && Soko_Accounts::is_unpaid_method( $b['method'] ) && empty( $b['supplier'] ) ) {
				return new WP_Error( 'soko', 'Add the supplier, so you know who the bill is owed to.', array( 'status' => 400 ) );
			}
			if ( ! empty( $b['supplier_pin'] ) && ! preg_match( '/^[AP]\d{9}[A-Z]$/i', trim( $b['supplier_pin'] ) ) ) {
				return new WP_Error( 'soko', 'Check the supplier KRA PIN. It looks like P051234567Q.', array( 'status' => 400 ) );
			}
			if ( ! empty( $b['vat'] ) && (float) $b['vat'] > 0 && empty( $b['supplier_pin'] ) ) {
				return new WP_Error( 'soko', 'Add the supplier KRA PIN from the receipt. KRA only allows VAT to be claimed with it.', array( 'status' => 400 ) );
			}
			if ( isset( $b['vat'] ) && (float) $b['vat'] < 0 ) {
				return new WP_Error( 'soko', 'VAT cannot be negative. Leave it at 0 if there was no VAT.', array( 'status' => 400 ) );
			}
			if ( isset( $b['vat'] ) && ( isset( $b['amount'] ) && (float) $b['vat'] >= (float) $b['amount'] ) ) {
				return new WP_Error( 'soko', 'VAT must be less than the amount. At 16%, VAT on an amount that includes VAT is the amount × 16 ÷ 116.', array( 'status' => 400 ) );
			}
		}
		if ( in_array( $name, array( 'leads', 'customers' ), true ) ) {
			if ( ! empty( $b['email'] ) && ! is_email( $b['email'] ) ) {
				return new WP_Error( 'soko', 'That email address does not look right, for example name@gmail.com.', array( 'status' => 400 ) );
			}
			if ( ! empty( $b['phone'] ) ) {
				$pd = soko_phone( $b['phone'] );
				if ( ! soko_phone_valid( $b['phone'] ) ) {
					return new WP_Error( 'soko', 'That phone number does not look right. Use 10 digits like 0712 345 678, or +254 712 345 678.', array( 'status' => 400 ) );
				}
				if ( 'customers' === $name && empty( $b['allow_dup'] ) ) {
					$dup = $wpdb->get_row( $wpdb->prepare( 'SELECT id, name FROM ' . soko_t( 'customers' ) . ' WHERE phone = %s AND id <> %d', $pd, $id ), ARRAY_A ); // phpcs:ignore
					if ( $dup ) {
						return new WP_Error( 'soko_dup', sprintf( '%s already has this phone number.', $dup['name'] ), array( 'status' => 409, 'customer_id' => (int) $dup['id'] ) );
					}
				}
			}
		}
		if ( ! $id && in_array( $name, array( 'leads', 'customers' ), true ) && empty( $row['name'] ) && empty( $row['phone'] ) ) {
			return new WP_Error( 'soko', 'Enter a name or phone number.', array( 'status' => 400 ) );
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
			if ( 'events' === $name ) {
				// A visit linked to a job keeps the job in step.
				$ev = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
				if ( $ev && (int) $ev['job_id'] ) {
					$jt  = soko_t( 'jobs' );
					$job = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $jt WHERE id = %d", (int) $ev['job_id'] ), ARRAY_A );
					if ( $job ) {
						$ju = array();
						if ( isset( $row['start'] ) && $row['start'] && substr( $row['start'], 0, 10 ) !== (string) $job['date'] && empty( $ev['done'] ) ) {
							$ju['date'] = substr( $row['start'], 0, 10 );
						}
						if ( ! empty( $row['done'] ) && 'Install' === $ev['kind'] ) {
							$st    = (array) soko_setting( 'job_stages' );
							$after = array_search( 'Commissioned', $st, true );
							$now   = array_search( $job['stage'], $st, true );
							if ( false !== $after && ( false === $now || $now < $after ) ) {
								$ju['stage'] = 'Commissioned';
							}
						}
						if ( $ju ) {
							$ju['updated'] = soko_now();
							$wpdb->update( $jt, $ju, array( 'id' => (int) $job['id'] ) );
						}
					}
				}
			}
			if ( 'jobs' === $name ) {
				$et = soko_t( 'events' );
				foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, start FROM $et WHERE job_id = %d AND done = 0", $id ), ARRAY_A ) as $ev ) { // phpcs:ignore
					$upd = array();
					if ( ! empty( $row['date'] ) && substr( (string) $ev['start'], 0, 10 ) !== $row['date'] ) {
						$upd['start'] = $row['date'] . ' ' . ( strlen( (string) $ev['start'] ) > 10 ? substr( $ev['start'], 11 ) : '08:00:00' );
					}
					if ( isset( $row['techs'] ) && '' !== $row['techs'] ) {
						$upd['who'] = $row['techs'];
					}
					if ( isset( $row['location'] ) && '' !== $row['location'] ) {
						$upd['location'] = $row['location'];
					}
					if ( $upd ) {
						$wpdb->update( $et, $upd, array( 'id' => (int) $ev['id'] ) );
					}
				}
			}
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
		if ( ! self::sees_cost() ) {
			foreach ( $ps as &$p ) {
				unset( $p['cost'] );
			}
		}
		return $ps;
	}

	/** Cost prices and margins are for owners, accounts and purchasing only. */
	/** Cancel an invoice: only with no payments or credits, and goods already delivered go back into stock. */
	public static function void_invoice( $d ) {
		global $wpdb;
		if ( 'invoice' !== $d['type'] || 'void' === $d['status'] ) {
			return new WP_Error( 'soko', 'This invoice is already cancelled.', array( 'status' => 400 ) );
		}
		if ( $d['paid'] > 0 ) {
			return new WP_Error( 'soko', 'This invoice has payments or credits. Refund or move them first.', array( 'status' => 400 ) );
		}
		$lk = Soko_Accounts::locked( $d['date'] );
		if ( $lk ) {
			return $lk;
		}
		$back = 0;
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, SUM(qty) q, MAX(cost) c FROM ' . soko_t( 'moves' ) . " WHERE ref = %s AND reason IN ('Sold','Returned') GROUP BY product_id", $d['number'] ), ARRAY_A ) as $m ) { // phpcs:ignore
			if ( (float) $m['q'] < 0 ) {
				Soko_Ledger::move( (int) $m['product_id'], -1 * (float) $m['q'], 'Returned', $d['number'], true, (float) $m['c'] );
				$back += -1 * (float) $m['q'];
			}
		}
		$doc = Soko_Ledger::set_status( $d['id'], 'void' );
		return array( 'doc' => $doc, 'message' => $d['number'] . ' cancelled.' . ( $back ? ' ' . ( $back + 0 ) . ' item(s) put back into stock.' : '' ) );
	}

	/** A date the user picked (Y-m-d), not in the future; today if none. */
	public static function pick_date( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return soko_today();
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) || ! strtotime( $v ) ) {
			return new WP_Error( 'soko', 'Pick a valid date.', array( 'status' => 400 ) );
		}
		if ( $v > soko_today() ) {
			return new WP_Error( 'soko', 'The date cannot be in the future.', array( 'status' => 400 ) );
		}
		return $v;
	}

	public static function sees_cost() {
		return soko_can( 'soko_reports' ) || soko_can( 'soko_purchases' );
	}

	public static function save_product( $req ) {
		$b = $req->get_json_params();
		if ( ! self::sees_cost() ) {
			unset( $b['cost'] );
		}
		if ( empty( $b['name'] ) && empty( $req['id'] ) ) {
			return new WP_Error( 'soko', 'Give the product a name.', array( 'status' => 400 ) );
		}
		if ( isset( $b['price'] ) && (float) $b['price'] <= 0 ) {
			return new WP_Error( 'soko', 'Enter a selling price above zero.', array( 'status' => 400 ) );
		}
		foreach ( array( 'was', 'cost', 'install_fee', 'reorder' ) as $nk ) {
			if ( isset( $b[ $nk ] ) && '' !== $b[ $nk ] && (float) $b[ $nk ] < 0 ) {
				return new WP_Error( 'soko', 'Prices, costs and reorder levels cannot be negative.', array( 'status' => 400 ) );
			}
		}
		if ( ! empty( $b['sku'] ) ) {
			global $wpdb;
			$dup = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . soko_t( 'products' ) . ' WHERE sku = %s AND id <> %d', sanitize_text_field( $b['sku'] ), (int) $req['id'] ) ); // phpcs:ignore
			if ( $dup ) {
				return new WP_Error( 'soko', sprintf( 'The product code %s is already used by %s. Choose a different code.', sanitize_text_field( $b['sku'] ), $dup ), array( 'status' => 400 ) );
			}
		}
		$out = Soko_Ledger::save_product( $b, (int) $req['id'] );
		if ( is_array( $out ) && ! self::sees_cost() ) {
			unset( $out['cost'] );
		}
		return $out;
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
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT m.*, u.display_name AS user FROM ' . soko_t( 'moves' ) . " m LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id WHERE product_id = %d ORDER BY m.id DESC LIMIT 100", $req['id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! self::sees_cost() ) {
			foreach ( $rows as &$r ) {
				unset( $r['cost'] );
			}
			unset( $r );
		}
		return $rows;
	}

	public static function adjust( $req ) {
		$b = $req->get_json_params();
		$q = (float) $b['qty'];
		if ( ! $q ) {
			return new WP_Error( 'soko', 'Enter a quantity.', array( 'status' => 400 ) );
		}
		if ( $q < 0 && ! self::sees_cost() ) {
			return new WP_Error( 'soko', 'Taking stock out (damaged, lost or written off) needs the owner or the stores team. Ask them to record it.', array( 'status' => 403 ) );
		}
		$prod = Soko_Ledger::product( (int) $req['id'] );
		if ( $prod && $q < 0 && ( (float) $prod['stock'] + $q ) < 0 ) {
			return new WP_Error( 'soko', sprintf( 'Only %s in stock, so you cannot remove %s.', (float) $prod['stock'] + 0, abs( $q ) ), array( 'status' => 400 ) );
		}
		Soko_Ledger::move( (int) $req['id'], $q, sanitize_text_field( $b['reason'] ? $b['reason'] : 'Adjustment' ), sanitize_text_field( isset( $b['ref'] ) ? $b['ref'] : '' ) );
		$out = Soko_Ledger::product( (int) $req['id'] );
		if ( ! self::sees_cost() ) {
			unset( $out['cost'] );
		}
		return $out;
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
		$kept = wp_list_pluck( $out, 'id' );
		foreach ( soko_categories() as $old ) {
			if ( ! in_array( $old['id'], $kept, true ) ) {
				global $wpdb;
				$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . soko_t( 'products' ) . ' WHERE cat = %s', $old['id'] ) ); // phpcs:ignore
				if ( $n ) {
					return new WP_Error( 'soko', sprintf( '%s still has %d products. Move them to another category before removing it.', $old['name'], $n ), array( 'status' => 400 ) );
				}
			}
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
			array_push( $vals, $like, $like, soko_phone_like( $req['q'] ) );
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
		if ( ! self::sees_cost() && ! empty( $d['lines'] ) ) {
			foreach ( $d['lines'] as $li => $ln ) {
				unset( $d['lines'][ $li ]['cost'] );
			}
		}
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
		// Only quotations and invoices are made here: credit notes, receipts and payments have their own steps.
		if ( ! $id && ! in_array( isset( $b['type'] ) ? $b['type'] : 'invoice', array( 'quote', 'invoice' ), true ) ) {
			return new WP_Error( 'soko', 'Use the Credit note button on an invoice to issue a credit note.', array( 'status' => 400 ) );
		}
		unset( $b['paid'], $b['parent_id'], $b['source'], $b['demo'], $b['token'] );
		if ( ! $id && isset( $b['status'] ) && ! in_array( $b['status'], 'quote' === ( isset( $b['type'] ) ? $b['type'] : 'invoice' ) ? array( 'draft', 'sent' ) : array( 'issued' ), true ) ) {
			unset( $b['status'] );
		}
		if ( ! empty( $b['date'] ) ) {
			$dt = self::pick_date( $b['date'] );
			if ( is_wp_error( $dt ) ) {
				return $dt;
			}
			$lk = Soko_Accounts::locked( $dt );
			if ( $lk ) {
				return $lk;
			}
		}
		if ( $id && ! empty( $cur ) ) {
			$lk = Soko_Accounts::locked( $cur['date'] );
			if ( $lk ) {
				return $lk;
			}
		}
		$d = Soko_Ledger::save_doc( $b, $id );
		if ( ! empty( $b['send'] ) ) {
			$ok         = $d['customer'] && $d['customer']['email'] ? Soko_Ledger::email_doc( $d, $d['customer']['email'] ) : false;
			$d['_mail'] = $ok ? 'sent' : ( $d['customer'] && $d['customer']['email'] ? 'failed' : 'noemail' );
			if ( ! $ok && 'quote' === $d['type'] && 'sent' === $d['status'] ) {
				Soko_Ledger::set_status( $d['id'], 'draft' );
				$d['status'] = 'draft';
			}
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
		if ( in_array( $b['action'], array( 'credit', 'apply_credit', 'refund' ), true ) && ! soko_can( 'soko_reports' ) && ! soko_can( 'soko_settings' ) ) {
			return new WP_Error( 'soko', 'Only the owner or the accounts team can issue credits and refunds. Ask them to do this.', array( 'status' => 403 ) );
		}
		$when = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
		if ( is_wp_error( $when ) ) {
			return $when;
		}
		if ( in_array( $b['action'], array( 'credit', 'refund', 'apply_credit', 'deliver' ), true ) && ( $lk = Soko_Accounts::locked( $when ) ) ) { // phpcs:ignore
			return $lk;
		}
		switch ( $b['action'] ) {
			case 'convert':
				if ( $lk = Soko_Accounts::locked( $when ) ) { // phpcs:ignore
					return $lk;
				}
				$r = Soko_Ledger::convert( $id, ! empty( $b['deposit'] ), $when );
				return is_wp_error( $r ) ? $r : array( 'doc' => $r, 'message' => 'Invoice ' . $r['number'] . ' created from ' . $d['number'] . '.' );
			case 'status':
				$to = sanitize_key( isset( $b['status'] ) ? $b['status'] : '' );
				if ( 'credit' === $d['type'] || 'receipt' === $d['type'] ) {
					if ( 'void' !== $to || ( ! soko_can( 'soko_reports' ) && ! soko_can( 'soko_settings' ) ) ) {
						return new WP_Error( 'soko', 'Only the owner or the accounts team can cancel a credit note.', array( 'status' => 403 ) );
					}
				} elseif ( 'invoice' === $d['type'] && 'void' === $to ) {
					return self::void_invoice( $d );
				} elseif ( 'invoice' === $d['type'] ) {
					return new WP_Error( 'soko', 'Invoices change status through payments, delivery and cancelling.', array( 'status' => 400 ) );
				}
				$lk = Soko_Accounts::locked( $d['date'] );
				if ( $lk ) {
					return $lk;
				}
				if ( 'credit' === $d['type'] && 'void' === $to && 'void' !== $d['status'] && $d['parent_id'] ) {
					global $wpdb;
					// Undo the credit on its invoice.
					$wpdb->query( $wpdb->prepare( 'UPDATE ' . soko_t( 'docs' ) . ' SET paid = paid - %f WHERE id = %d', (float) $d['total'], (int) $d['parent_id'] ) ); // phpcs:ignore
				}
				return array( 'doc' => Soko_Ledger::set_status( $id, $to ), 'message' => 'Updated.' );
			case 'email':
				$to = sanitize_email( ! empty( $b['to'] ) ? $b['to'] : ( $d['customer'] ? $d['customer']['email'] : '' ) );
				if ( ! $to ) {
					return new WP_Error( 'soko', 'Add an email address for this customer first.', array( 'status' => 400 ) );
				}
				$ok = Soko_Ledger::email_doc( $d, $to, isset( $b['message'] ) ? sanitize_textarea_field( $b['message'] ) : '' );
				if ( ! $ok ) {
					return new WP_Error( 'soko', 'The email could not be sent, so ' . $d['number'] . ' was not marked as sent. Check the email settings, or share it by WhatsApp instead.', array( 'status' => 502 ) );
				}
				if ( 'quote' === $d['type'] && 'draft' === $d['status'] ) {
					Soko_Ledger::set_status( $id, 'sent' );
				}
				soko_log( 'Emailed ' . $d['number'] . ' to ' . $to, $d['type'], $d['number'] );
				return array( 'ok' => true, 'message' => 'Sent to ' . $to . '.' );
			case 'stk':
				$r = Soko_Mpesa::stk( $d, ! empty( $b['phone'] ) ? $b['phone'] : ( $d['customer'] ? $d['customer']['phone'] : '' ), ! empty( $b['amount'] ) ? (float) $b['amount'] : null );
				return $r;
			case 'deliver':
				global $wpdb;
				Soko_Ledger::fulfil_stock( $d, $when );
				$wpdb->update( soko_t( 'docs' ), array( 'fulfil' => 'delivered' ), array( 'id' => $id ) );
				soko_log( 'Delivered ' . $d['number'], 'invoice', $d['number'] );
				return array( 'doc' => Soko_Ledger::doc( $id ), 'message' => 'Marked delivered. Stock updated.' );
			case 'fulfil':
				global $wpdb;
				$wpdb->update( soko_t( 'docs' ), array( 'fulfil' => sanitize_key( $b['fulfil'] ) ), array( 'id' => $id ) );
				return array( 'doc' => Soko_Ledger::doc( $id ), 'message' => 'Updated.' );
			case 'etims':
				$ref = strtoupper( trim( sanitize_text_field( isset( $b['ref'] ) ? $b['ref'] : '' ) ) );
				if ( ! $ref ) {
					return new WP_Error( 'soko', 'Type the eTIMS invoice number shown on KRA after signing.', array( 'status' => 400 ) );
				}
				if ( ! preg_match( '/^[A-Z0-9\-\/]{8,40}$/', $ref ) ) {
					return new WP_Error( 'soko', 'That does not look like an eTIMS number. It is usually 8 or more letters and numbers, for example KRACU0300001234.', array( 'status' => 400 ) );
				}
				$b['ref'] = $ref;
				return array( 'doc' => Soko_Etims::record( $id, isset( $b['ref'] ) ? $b['ref'] : '' ), 'message' => 'eTIMS number saved.' );
			case 'apply_credit':
				global $wpdb;
				$D    = soko_t( 'docs' );
				$need = max( 0, round( (float) $d['total'] - (float) $d['paid'], 2 ) );
				$used = 0;
				$from_list = array();
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, number, total, paid FROM $D WHERE type = 'invoice' AND status <> 'void' AND customer_id = %d AND paid > total AND id <> %d ORDER BY id", $d['customer_id'], $d['id'] ), ARRAY_A ) as $src ) { // phpcs:ignore
					if ( $need <= 0.005 ) {
						break;
					}
					$take = min( $need, round( (float) $src['paid'] - (float) $src['total'], 2 ) );
					$sm   = soko_json( $wpdb->get_var( $wpdb->prepare( "SELECT meta FROM $D WHERE id = %d", $src['id'] ) ) ); // phpcs:ignore
					$sm   = is_array( $sm ) ? $sm : array();
					$sm['credit_moved'][] = array( 'to' => $d['number'], 'amount' => $take, 'date' => soko_today() );
					$wpdb->update( $D, array( 'paid' => (float) $src['paid'] - $take, 'meta' => wp_json_encode( $sm ) ), array( 'id' => (int) $src['id'] ) );
					$from_list[] = array( 'from' => $src['number'], 'amount' => $take, 'date' => soko_today() );
					$need -= $take;
					$used += $take;
				}
				if ( $used <= 0 ) {
					return new WP_Error( 'soko', 'This customer has no credit to apply.', array( 'status' => 400 ) );
				}
				$tm = is_array( $d['meta'] ) ? $d['meta'] : array();
				$tm['credits_applied'] = array_merge( isset( $tm['credits_applied'] ) ? (array) $tm['credits_applied'] : array(), $from_list );
				$wpdb->update( $D, array( 'paid' => (float) $d['paid'] + $used, 'meta' => wp_json_encode( $tm ), 'updated' => soko_now() ), array( 'id' => $d['id'] ) );
				Soko_Accounts::flush();
				soko_log( 'Applied ' . soko_money( $used ) . ' customer credit to ' . $d['number'], 'invoice', $d['number'] );
				return array( 'message' => soko_money( $used ) . ' of credit applied to ' . $d['number'] . '.' );
			case 'credit':
				global $wpdb;
				if ( 'invoice' !== $d['type'] || 'void' === $d['status'] ) {
					return new WP_Error( 'soko', 'Credit notes can only be issued against an invoice.', array( 'status' => 400 ) );
				}
				$amt  = round( (float) ( isset( $b['amount'] ) ? $b['amount'] : 0 ), 2 );
				$done = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(total) FROM ' . soko_t( 'docs' ) . " WHERE type = 'credit' AND parent_id = %d AND status <> 'void'", $d['id'] ) ); // phpcs:ignore
				$max  = round( (float) $d['total'] - $done, 2 );
				if ( $amt <= 0 || $amt > $max + 0.005 ) {
					return new WP_Error( 'soko', sprintf( 'Enter an amount between 1 and %s.', soko_money_exact( $max ) ), array( 'status' => 400 ) );
				}
				$reason = sanitize_text_field( isset( $b['reason'] ) ? $b['reason'] : '' );
				if ( ! $reason ) {
					return new WP_Error( 'soko', 'Give the reason for the credit note, for example "Returned one panel".', array( 'status' => 400 ) );
				}
				$rate = soko_setting( 'vat_registered' ) ? (float) soko_setting( 'vat_rate', 16 ) : 0;
				$vat  = $d['total'] > 0 ? round( $amt * (float) $d['vat'] / (float) $d['total'], 2 ) : 0;
				$cn   = Soko_Settings::next_number( 'credit' );
				$wpdb->insert(
					soko_t( 'docs' ),
					array(
						'type'        => 'credit',
						'number'      => $cn,
						'customer_id' => $d['customer_id'],
						'status'      => 'issued',
						'source'      => 'desk',
						'date'        => $when,
						'subtotal'    => $amt - $vat,
						'vat'         => $vat,
						'total'       => $amt,
						'paid'        => 0,
						'parent_id'   => $d['id'],
						'notes'       => $reason,
						'meta'        => wp_json_encode( array( 'invoice' => $d['number'], 'reason' => $reason ) ),
						'token'       => wp_generate_password( 20, false ),
						'created'     => soko_now(),
					)
				);
				$cid = (int) $wpdb->insert_id;
				$wpdb->insert( soko_t( 'lines' ), array( 'doc_id' => $cid, 'description' => 'Credit against ' . $d['number'] . ': ' . $reason, 'qty' => 1, 'price' => $amt, 'vatable' => $vat > 0 ? 1 : 0, 'sort' => 0 ) );
				// Goods coming back go into stock at the cost they were sold at.
				$back = array();
				foreach ( (array) ( isset( $b['restock'] ) ? $b['restock'] : array() ) as $rs ) {
					$pid = (int) ( isset( $rs['product_id'] ) ? $rs['product_id'] : 0 );
					$q   = (float) ( isset( $rs['qty'] ) ? $rs['qty'] : 0 );
					if ( $pid <= 0 || $q <= 0 || 'product' !== $wpdb->get_var( $wpdb->prepare( 'SELECT kind FROM ' . soko_t( 'products' ) . ' WHERE id = %d', $pid ) ) ) { // phpcs:ignore
						continue;
					}
					$sold = 0;
					$lc   = 0;
					foreach ( $d['lines'] as $ln ) {
						if ( (int) $ln['product_id'] === $pid ) {
							$sold += (float) $ln['qty'];
							$lc    = (float) $ln['cost'];
						}
					}
					$gone = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(m.qty),0) FROM ' . soko_t( 'moves' ) . ' m JOIN ' . soko_t( 'docs' ) . " c ON c.number = m.ref WHERE m.reason = 'Returned' AND m.product_id = %d AND c.parent_id = %d", $pid, $d['id'] ) ); // phpcs:ignore
					$q    = min( $q, $sold - $gone );
					if ( $q > 0 ) {
						Soko_Ledger::move( $pid, $q, 'Returned', $cn, true, $lc, $when );
						$back[] = $q + 0;
					}
				}
				// The credit reduces what the customer owes on the invoice.
				$wpdb->update( soko_t( 'docs' ), array( 'paid' => (float) $d['paid'] + $amt, 'updated' => soko_now() ), array( 'id' => $d['id'] ) );
				Soko_Accounts::flush();
				soko_log( 'Issued credit note ' . $cn . ' for ' . soko_money( $amt ) . ' against ' . $d['number'], 'credit', $cn );
				$left = round( (float) $d['balance'] - $amt, 2 );
				return array( 'message' => 'Credit note ' . $cn . ' issued. ' . ( $left >= 0 ? $d['number'] . ' now has ' . soko_money( $left ) . ' to pay.' : 'The customer now has ' . soko_money( -$left ) . ' in credit: apply it to another invoice or refund it.' ) . ( $back ? ' ' . array_sum( $back ) . ' item(s) returned to stock.' : '' ), 'credit_id' => $cid );
			case 'refund':
				global $wpdb;
				$have = round( (float) $d['paid'] - (float) $d['total'], 2 );
				$amt  = round( (float) ( isset( $b['amount'] ) ? $b['amount'] : 0 ), 2 );
				if ( 'invoice' !== $d['type'] || $have <= 0.005 ) {
					return new WP_Error( 'soko', 'There is no credit on ' . $d['number'] . ' to refund.', array( 'status' => 400 ) );
				}
				if ( $amt <= 0 || $amt > $have + 0.005 ) {
					return new WP_Error( 'soko', sprintf( 'Enter an amount between 1 and %s.', soko_money_exact( $have ) ), array( 'status' => 400 ) );
				}
				$acc  = Soko_Accounts::money_account( isset( $b['method'] ) ? $b['method'] : 'mpesa' );
				$code = sanitize_text_field( isset( $b['code'] ) ? $b['code'] : '' );
				$j    = Soko_Accounts::journal( $when, 'Refund to ' . ( $d['customer'] ? $d['customer']['name'] : 'customer' ) . ' for ' . $d['number'] . ( $code ? ' (' . $code . ')' : '' ), array( array( '1100', $amt, 0 ), array( $acc, 0, $amt ) ), 'refund', $d['number'] );
				if ( is_wp_error( $j ) ) {
					return $j;
				}
				$m = is_array( $d['meta'] ) ? $d['meta'] : array();
				$m['refunds'][] = array( 'amount' => $amt, 'date' => $when, 'method' => sanitize_key( isset( $b['method'] ) ? $b['method'] : 'mpesa' ), 'code' => $code );
				$wpdb->update( soko_t( 'docs' ), array( 'paid' => (float) $d['paid'] - $amt, 'meta' => wp_json_encode( $m ), 'updated' => soko_now() ), array( 'id' => $d['id'] ) );
				Soko_Accounts::flush();
				soko_log( 'Refunded ' . soko_money( $amt ) . ' on ' . $d['number'], 'invoice', $d['number'] );
				return array( 'message' => 'Refund of ' . soko_money( $amt ) . ' recorded.' );
			case 'void':
				return self::void_invoice( $d );
			case 'duplicate':
				$n = Soko_Ledger::save_doc( array( 'type' => $d['type'], 'customer_id' => $d['customer_id'], 'lines' => $d['lines'], 'notes' => $d['notes'], 'status' => 'quote' === $d['type'] ? 'draft' : 'issued' ) );
				return array( 'doc' => $n, 'message' => $n['number'] . ' created as a copy.' );
			case 'job':
				global $wpdb;
				$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . soko_t( 'jobs' ) . ' WHERE doc_id = %d ORDER BY id LIMIT 1', $d['id'] ) ); // phpcs:ignore
				if ( $existing ) {
					return array( 'message' => 'This order already has an installation job. Opening it.', 'job_id' => $existing );
				}
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
		if ( ! empty( $b['received'] ) || ! empty( $b['date'] ) ) {
			$pd = self::pick_date( substr( ! empty( $b['received'] ) ? $b['received'] : $b['date'], 0, 10 ) );
			if ( is_wp_error( $pd ) ) {
				return $pd;
			}
			if ( $lk = Soko_Accounts::locked( $pd ) ) { // phpcs:ignore
				return $lk;
			}
		}
		$doc = ! empty( $b['doc_id'] ) ? Soko_Ledger::doc( (int) $b['doc_id'] ) : null;
		$code = strtoupper( trim( sanitize_text_field( isset( $b['code'] ) ? $b['code'] : '' ) ) );
		if ( $code ) {
			global $wpdb;
			$dup = $wpdb->get_row( $wpdb->prepare( 'SELECT code, amount, account_ref FROM ' . soko_t( 'payments' ) . ' WHERE code = %s', $code ), ARRAY_A ); // phpcs:ignore
			if ( $dup ) {
				return new WP_Error( 'soko', sprintf( 'Code %s has already been recorded (%s%s). Check the code and try again.', $code, soko_money( $dup['amount'] ), $dup['account_ref'] ? ' for ' . $dup['account_ref'] : '' ), array( 'status' => 409 ) );
			}
		}
		if ( $doc && empty( $b['allow_over'] ) ) {
			$bal = max( 0, (float) $doc['total'] - (float) $doc['paid'] );
			if ( 'void' === $doc['status'] ) {
				return new WP_Error( 'soko', $doc['number'] . ' is cancelled, so it cannot take payments.', array( 'status' => 400 ) );
			}
			if ( (float) $b['amount'] > $bal + 0.5 ) {
				return new WP_Error( 'soko_over', sprintf( 'This is more than the %s still owed on %s. The customer would be owed the difference.', soko_money( $bal ), $doc['number'] ), array( 'status' => 400 ) );
			}
		}
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
		$cost = self::sees_cost();
		foreach ( $rows as &$r ) {
			$r['items'] = soko_json( $r['items'] );
			if ( ! $cost ) {
				// Stock staff see what is coming in, not what it cost.
				foreach ( array( 'total', 'paid', 'amount', 'balance', 'vat' ) as $k ) {
					unset( $r[ $k ] );
				}
				if ( is_array( $r['items'] ) ) {
					foreach ( array_keys( $r['items'] ) as $ik ) {
						if ( is_array( $r['items'][ $ik ] ) ) {
							unset( $r['items'][ $ik ]['cost'], $r['items'][ $ik ]['total'] );
						}
					}
				}
			}
		}
		return $rows;
	}

	public static function save_purchase( $req ) {
		global $wpdb;
		$b     = $req->get_json_params();
		if ( ! empty( $req['id'] ) && 'received' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . soko_t( 'purchases' ) . ' WHERE id = %d', $req['id'] ) ) ) { // phpcs:ignore
			return new WP_Error( 'soko', 'This order has been received into stock, so it cannot be changed. Record a stock adjustment or a new order instead.', array( 'status' => 400 ) );
		}
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
		if ( ! empty( $b['expected'] ) && ! empty( $b['date'] ) && $b['expected'] < $b['date'] ) {
			return new WP_Error( 'soko', 'The expected delivery date is before the order date.', array( 'status' => 400 ) );
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

	/** Customer statement: everything charged and paid in a period, with a running balance. */
	public static function statement( $req ) {
		global $wpdb;
		$c = Soko_Ledger::customer( (int) $req['id'] );
		if ( ! $c ) {
			return new WP_Error( 'soko', 'Customer not found.', array( 'status' => 404 ) );
		}
		$from = ! empty( $req['from'] ) ? sanitize_text_field( $req['from'] ) : gmdate( 'Y-m-01', strtotime( soko_today() . ' -2 months' ) );
		$to   = ! empty( $req['to'] ) ? sanitize_text_field( $req['to'] ) : soko_today();
		$D    = soko_t( 'docs' );
		$ev   = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT d.id, d.type, d.number, d.date, d.total, d.meta, d.parent_id FROM $D d WHERE d.customer_id = %d AND d.type IN ('invoice','credit') AND d.status <> 'void' AND " . Soko_Accounts::confirmed_sql( 'd' ), $c['id'] ), ARRAY_A ) as $d ) { // phpcs:ignore
			if ( 'invoice' === $d['type'] ) {
				$ev[] = array( $d['date'], 'Invoice ' . $d['number'], (float) $d['total'], 0 );
				$m = soko_json( $d['meta'] );
				foreach ( isset( $m['refunds'] ) ? (array) $m['refunds'] : array() as $e ) {
					$ev[] = array( $e['date'], 'Refund on ' . $d['number'] . ( $e['code'] ? ' (' . $e['code'] . ')' : '' ), (float) $e['amount'], 0 );
				}
			} else {
				$ev[] = array( $d['date'], 'Credit note ' . $d['number'], 0, (float) $d['total'] );
			}
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT p.received, p.amount, p.method, p.code, d.number FROM ' . soko_t( 'payments' ) . " p JOIN $D d ON d.id = p.doc_id WHERE d.customer_id = %d AND p.status = 'matched'", $c['id'] ), ARRAY_A ) as $p ) { // phpcs:ignore
			$ev[] = array( substr( $p['received'], 0, 10 ), 'Payment for ' . $p['number'] . ( $p['code'] ? ' (' . $p['code'] . ')' : '' ), 0, (float) $p['amount'] );
		}
		usort( $ev, function ( $a, $b ) { return strcmp( $a[0], $b[0] ); } );
		$open = 0;
		$rows = array();
		$bal  = 0;
		foreach ( $ev as $e ) {
			if ( $e[0] < $from ) {
				$open += $e[2] - $e[3];
				continue;
			}
			if ( $e[0] > $to || ( 0 == $e[2] && 0 == $e[3] ) ) { // phpcs:ignore
				continue;
			}
			$rows[] = $e;
		}
		$bal  = $open;
		$out  = array( array( date_i18n( 'j M Y', strtotime( $from ) ), 'Balance brought forward', '', '', round( $open, 2 ), '_b' => 1 ) );
		foreach ( $rows as $e ) {
			$bal  += $e[2] - $e[3];
			$out[] = array( date_i18n( 'j M Y', strtotime( $e[0] ) ), $e[1], $e[2] ? round( $e[2], 2 ) : '', $e[3] ? round( $e[3], 2 ) : '', round( $bal, 2 ) );
		}
		$out[] = array( '', $bal >= 0 ? 'Balance due' : 'In credit', '', '', round( $bal, 2 ), '_b' => 1 );
		$title = 'Statement for ' . $c['name'];
		$per   = date_i18n( 'j M Y', strtotime( $from ) ) . ' to ' . date_i18n( 'j M Y', strtotime( $to ) );
		$secs  = array( array( 'title' => 'Account activity', 'head' => array( 'Date', 'Details', 'Charged', 'Paid or credited', 'Balance' ), 'rows' => $out, 'widths' => array( 26, 74, 26, 30, 24 ) ) );
		$kpis  = array( array( $bal >= 0 ? 'Balance due' : 'In credit', soko_money( abs( $bal ) ) ), array( 'Phone', soko_phone_display( $c['phone'] ) ) );
		if ( 'pdf' === $req['format'] ) {
			soko_log( 'Statement for ' . $c['name'], 'customer', $c['id'] );
			self::send_file( Soko_PDF::report( $title, $per, $secs, $kpis ), 'application/pdf', sanitize_file_name( 'statement-' . $c['name'] . '-' . $to ) . '.pdf', true );
		}
		return array( 'title' => $title, 'period' => $per, 'sections' => $secs, 'kpis' => $kpis, 'balance' => round( $bal, 2 ) );
	}

	/** Depreciation: spread the cost of equipment and vehicles over their working life (straight line, by month). */
	public static function depreciation( $req ) {
		$b     = $req->get_json_params();
		$chart = Soko_Accounts::chart();
		foreach ( array( '1590' => array( 'Accumulated depreciation', 'asset' ), '6970' => array( 'Depreciation', 'expense' ) ) as $code => $a ) {
			if ( ! isset( $chart[ $code ] ) ) {
				$x = Soko_Accounts::add_account( $code, $a[0], $a[1] );
				if ( is_wp_error( $x ) ) {
					return $x;
				}
			}
		}
		$date = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
		if ( is_wp_error( $date ) ) {
			return $date;
		}
		$bal  = Soko_Accounts::balances( $date );
		$cost = isset( $bal['1500'] ) ? (float) $bal['1500'] : 0;
		$acc  = isset( $bal['1590'] ) ? -(float) $bal['1590'] : 0;
		$rate = isset( $b['rate'] ) ? (float) $b['rate'] : 25;
		$amt  = isset( $b['amount'] ) && '' !== $b['amount'] ? round( (float) $b['amount'], 2 ) : round( $cost * $rate / 100 / 12, 2 );
		if ( ! empty( $b['preview'] ) ) {
			return array( 'cost' => round( $cost, 2 ), 'depreciated' => round( $acc, 2 ), 'suggested' => round( $cost * $rate / 100 / 12, 2 ) );
		}
		if ( $amt <= 0 ) {
			return new WP_Error( 'soko', 'There is no equipment value to depreciate yet.', array( 'status' => 400 ) );
		}
		if ( $acc + $amt > $cost + 0.01 ) {
			return new WP_Error( 'soko', 'That would take the equipment below zero. At most ' . soko_money_exact( max( 0, $cost - $acc ) ) . ' is left to depreciate.', array( 'status' => 400 ) );
		}
		$ref = 'DEP-' . substr( $date, 0, 7 );
		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . soko_t( 'journal' ) . " WHERE source = 'depreciation' AND ref = %s", $ref ) ) ) { // phpcs:ignore
			return new WP_Error( 'soko', 'Depreciation for ' . date_i18n( 'F Y', strtotime( $date ) ) . ' is already recorded.', array( 'status' => 400 ) );
		}
		$j = Soko_Accounts::journal( $date, 'Depreciation for ' . date_i18n( 'F Y', strtotime( $date ) ) . ' (' . ( $rate + 0 ) . '% a year)', array( array( '6970', $amt, 0 ), array( '1590', 0, $amt ) ), 'depreciation', $ref );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		return array( 'message' => 'Depreciation of ' . soko_money( $amt ) . ' recorded for ' . date_i18n( 'F Y', strtotime( $date ) ) . '.' );
	}

	/** Move money between cash, M-Pesa and bank (for example banking the day's cash). */
	public static function transfer( $req ) {
		$b    = $req->get_json_params();
		$from = (string) ( isset( $b['from'] ) ? $b['from'] : '' );
		$to   = (string) ( isset( $b['to'] ) ? $b['to'] : '' );
		$amt  = round( (float) ( isset( $b['amount'] ) ? $b['amount'] : 0 ), 2 );
		$fee  = round( (float) ( isset( $b['fee'] ) ? $b['fee'] : 0 ), 2 );
		$chart = Soko_Accounts::chart();
		$money = Soko_Accounts::money_codes();
		if ( ! in_array( $from, array_map( 'strval', $money ), true ) || ! in_array( $to, array_map( 'strval', $money ), true ) || $from === $to ) {
			return new WP_Error( 'soko', 'Choose two different money accounts.', array( 'status' => 400 ) );
		}
		if ( $amt <= 0 || $fee < 0 ) {
			return new WP_Error( 'soko', 'Enter the amount moved.', array( 'status' => 400 ) );
		}
		$date = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
		if ( is_wp_error( $date ) ) {
			return $date;
		}
		$lines = array( array( $to, $amt, 0 ), array( $from, 0, $amt + $fee ) );
		if ( $fee > 0 ) {
			$fx = '';
			foreach ( $chart as $c => $a ) {
				if ( 'expense' === $a['type'] && preg_match( '/bank|charges/i', $a['name'] ) ) {
					$fx = (string) $c;
					break;
				}
			}
			$lines[] = array( $fx ? $fx : '6000', $fee, 0 );
		}
		$j = Soko_Accounts::journal( $date, 'Moved from ' . $chart[ $from ]['name'] . ' to ' . $chart[ $to ]['name'], $lines, 'transfer' );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		soko_log( 'Moved ' . soko_money( $amt ) . ' from ' . $chart[ $from ]['name'] . ' to ' . $chart[ $to ]['name'], 'accounts', $j );
		return array( 'message' => soko_money( $amt ) . ' moved to ' . $chart[ $to ]['name'] . '.' );
	}

	/** Pay a bill that was recorded as "Not paid yet". */
	public static function pay_expense( $req ) {
		global $wpdb;
		$b = $req->get_json_params();
		$e = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'expenses' ) . ' WHERE id = %d', $req['id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! $e || ! Soko_Accounts::is_unpaid_method( $e['method'] ) ) {
			return new WP_Error( 'soko', 'This expense was already paid when it was recorded.', array( 'status' => 400 ) );
		}
		$date = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
		if ( is_wp_error( $date ) ) {
			return $date;
		}
		if ( $date < $e['date'] ) {
			return new WP_Error( 'soko', 'The payment date cannot be before the bill date.', array( 'status' => 400 ) );
		}
		$pd   = Soko_Accounts::po_paid_asat( soko_today() );
		$owed = round( (float) $e['amount'] - ( isset( $pd[ 'EXP-' . $e['id'] ] ) ? $pd[ 'EXP-' . $e['id'] ] : 0 ), 2 );
		$a    = round( (float) ( isset( $b['amount'] ) ? $b['amount'] : 0 ), 2 );
		if ( $a <= 0 || $a > $owed + 0.005 ) {
			return new WP_Error( 'soko', sprintf( 'Enter an amount between 1 and %s.', soko_money_exact( $owed ) ), array( 'status' => 400 ) );
		}
		$j = Soko_Accounts::journal( $date, 'Paid ' . ( $e['supplier'] ? $e['supplier'] : 'supplier' ) . ': ' . $e['category'], array( array( '2000', $a, 0 ), array( Soko_Accounts::money_account( isset( $b['method'] ) ? $b['method'] : 'mpesa' ), 0, $a ) ), 'supplier_pay', 'EXP-' . $e['id'] );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		soko_log( 'Paid bill ' . soko_money( $a ) . ' to ' . $e['supplier'], 'expense', 'EXP-' . $e['id'] );
		$left = $owed - $a;
		return array( 'message' => 'Payment saved. ' . ( $left > 0.5 ? soko_money( $left ) . ' still to pay.' : 'The bill is fully paid.' ) );
	}

	/** Send goods back to the supplier: stock out at what they cost, and the bill reduced (a supplier credit). */
	public static function return_purchase( $req ) {
		global $wpdb;
		$b  = $req->get_json_params();
		$po = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'purchases' ) . ' WHERE id = %d', $req['id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! $po || 'received' !== $po['status'] ) {
			return new WP_Error( 'soko', 'Only goods that were received can be returned.', array( 'status' => 400 ) );
		}
		$date = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
		if ( is_wp_error( $date ) ) {
			return $date;
		}
		if ( $lk = Soko_Accounts::locked( $date ) ) { // phpcs:ignore
			return $lk;
		}
		$items = soko_json( $po['items'] );
		$share = (float) $po['vat'] > 0 && (float) $po['total'] > 0 ? ( (float) $po['total'] - (float) $po['vat'] ) / (float) $po['total'] : 1;
		$gross = 0;
		$net   = 0;
		$done  = array();
		foreach ( (array) ( isset( $b['qty'] ) ? $b['qty'] : array() ) as $k => $q ) {
			$q = (float) $q;
			if ( $q <= 0 || ! isset( $items[ $k ] ) || ! $items[ $k ]['product_id'] ) {
				continue;
			}
			$l    = $items[ $k ];
			$back = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(-SUM(qty),0) FROM ' . soko_t( 'moves' ) . " WHERE ref = %s AND product_id = %d AND reason = 'Returned to supplier'", $po['number'], $l['product_id'] ) ); // phpcs:ignore
			$q    = min( $q, (float) $l['qty'] - $back );
			$prod = Soko_Ledger::product( (int) $l['product_id'] );
			if ( $q <= 0 || ! $prod || (float) $prod['stock'] < $q ) {
				return new WP_Error( 'soko', 'Not enough ' . $l['name'] . ' in stock to send back.', array( 'status' => 400 ) );
			}
			$unit = round( (float) $l['cost'] * $share, 2 );
			Soko_Ledger::move( (int) $l['product_id'], -$q, 'Returned to supplier', $po['number'], true, $unit, $date );
			$gross += $q * (float) $l['cost'];
			$net   += $q * $unit;
			$done[] = ( $q + 0 ) . ' × ' . $l['name'];
		}
		if ( ! $done ) {
			return new WP_Error( 'soko', 'Enter how many of each item are going back.', array( 'status' => 400 ) );
		}
		$gross = round( $gross, 2 );
		$net   = round( $net, 2 );
		$lines = array( array( '2000', $gross, 0 ), array( '1200', 0, $net ) );
		if ( $gross - $net > 0.005 ) {
			$lines[] = array( '1300', 0, round( $gross - $net, 2 ) );
		}
		$reason = sanitize_text_field( isset( $b['reason'] ) ? $b['reason'] : '' );
		$j = Soko_Accounts::journal( $date, 'Returned to supplier, ' . $po['number'] . ': ' . implode( ', ', $done ) . ( $reason ? ' (' . $reason . ')' : '' ), $lines, 'supplier_return', $po['number'] );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$wpdb->update( soko_t( 'purchases' ), array( 'paid' => (float) $po['paid'] + $gross ), array( 'id' => (int) $po['id'] ) );
		soko_log( 'Returned to supplier ' . soko_money( $gross ) . ' on ' . $po['number'], 'purchase', $po['number'] );
		$left = (float) $po['total'] - (float) $po['paid'] - $gross;
		return array( 'message' => 'Return recorded. The supplier owes you credit of ' . soko_money( $gross ) . ( $left < -0.5 ? ', which is more than the bill: ask them for a refund or use it on the next order' : '' ) . '.' );
	}

	public static function pay_purchase( $req ) {
		global $wpdb;
		$b  = $req->get_json_params();
		$t  = soko_t( 'purchases' );
		$po = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $req['id'] ), ARRAY_A ); // phpcs:ignore
		$a  = isset( $b['amount'] ) ? (float) $b['amount'] : 0;
		if ( ! $po ) {
			return new WP_Error( 'soko', 'Purchase order not found.', array( 'status' => 404 ) );
		}
		$pdate = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
		if ( is_wp_error( $pdate ) ) {
			return $pdate;
		}
		if ( $po && $pdate < substr( $po['date'], 0, 10 ) ) {
			return new WP_Error( 'soko', 'The payment date cannot be before the order date (' . date_i18n( 'j M Y', strtotime( $po['date'] ) ) . ').', array( 'status' => 400 ) );
		}
		$bal = (float) $po['total'] - (float) $po['paid'];
		if ( $a <= 0 || $a > $bal + 0.5 ) {
			return new WP_Error( 'soko', sprintf( 'Enter an amount between 1 and %s.', soko_money( $bal ) ), array( 'status' => 400 ) );
		}
		$wpdb->update( $t, array( 'paid' => (float) $po['paid'] + $a ), array( 'id' => (int) $po['id'] ) );
		Soko_Accounts::journal( $pdate, 'Paid supplier, ' . $po['number'], array( array( '2000', $a, 0 ), array( Soko_Accounts::money_account( isset( $b['method'] ) ? $b['method'] : 'bank' ), 0, $a ) ), 'supplier_pay', $po['number'] );
		soko_log( 'Paid supplier ' . soko_money( $a ) . ' on ' . $po['number'], 'purchase', $po['number'] );
		$left = $bal - $a;
		return array( 'message' => 'Payment saved. ' . ( $left > 0.5 ? soko_money( $left ) . ' still to pay.' : $po['number'] . ' is fully paid.' ) );
	}

	public static function receive_purchase( $req ) {
		global $wpdb;
		$b  = $req->get_json_params();
		$po = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'purchases' ) . ' WHERE id = %d', $req['id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! $po || 'received' === $po['status'] ) {
			return new WP_Error( 'soko', 'Already received.', array( 'status' => 400 ) );
		}
		$when = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
		if ( is_wp_error( $when ) ) {
			return $when;
		}
		if ( $lk = Soko_Accounts::locked( $when ) ) { // phpcs:ignore
			return $lk;
		}
		if ( $when < substr( $po['date'], 0, 10 ) ) {
			return new WP_Error( 'soko', 'Goods cannot be received before the order date (' . date_i18n( 'j M Y', strtotime( $po['date'] ) ) . ').', array( 'status' => 400 ) );
		}
		// Part of the order arrived: this order keeps what came, the rest moves to a new order still to arrive.
		$items = soko_json( $po['items'] );
		$got   = isset( $b['qty'] ) && is_array( $b['qty'] ) ? $b['qty'] : null;
		if ( $got ) {
			$now_i  = array();
			$rest_i = array();
			foreach ( $items as $k => $l ) {
				$q = isset( $got[ $k ] ) && '' !== $got[ $k ] ? max( 0, min( (float) $l['qty'], (float) $got[ $k ] ) ) : (float) $l['qty'];
				if ( $q > 0 ) {
					$now_i[] = array_merge( $l, array( 'qty' => $q ) );
				}
				if ( (float) $l['qty'] - $q > 0 ) {
					$rest_i[] = array_merge( $l, array( 'qty' => (float) $l['qty'] - $q ) );
				}
			}
			if ( ! $now_i ) {
				return new WP_Error( 'soko', 'Enter how many of each item arrived.', array( 'status' => 400 ) );
			}
			if ( $rest_i ) {
				$ratio = (float) $po['total'] > 0 ? (float) $po['vat'] / (float) $po['total'] : 0;
				$sum   = function ( $ls ) { $t = 0; foreach ( $ls as $x ) { $t += (float) $x['qty'] * (float) $x['cost']; } return round( $t, 2 ); };
				$t_now  = $sum( $now_i );
				$t_rest = $sum( $rest_i );
				$wpdb->insert(
					soko_t( 'purchases' ),
					array(
						'supplier_id' => (int) $po['supplier_id'],
						'number'      => Soko_Settings::next_number( 'po' ),
						'date'        => $po['date'],
						'expected'    => $po['expected'],
						'items'       => wp_json_encode( $rest_i ),
						'total'       => $t_rest,
						'vat'         => round( $t_rest * $ratio, 2 ),
						'notes'       => trim( 'Rest of ' . $po['number'] . '. ' . $po['notes'] ),
						'status'      => 'ordered',
						'created'     => soko_now(),
					)
				);
				$rest_no = $wpdb->get_var( $wpdb->prepare( 'SELECT number FROM ' . soko_t( 'purchases' ) . ' WHERE id = %d', $wpdb->insert_id ) ); // phpcs:ignore
				$wpdb->update( soko_t( 'purchases' ), array( 'items' => wp_json_encode( $now_i ), 'total' => $t_now, 'vat' => round( $t_now * $ratio, 2 ), 'notes' => trim( $po['notes'] . ' Rest still to come on ' . $rest_no . '.' ) ), array( 'id' => (int) $po['id'] ) );
				$po = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'purchases' ) . ' WHERE id = %d', $po['id'] ), ARRAY_A ); // phpcs:ignore
			}
		}
		$share = (float) $po['vat'] > 0 && (float) $po['total'] > 0 ? ( (float) $po['total'] - (float) $po['vat'] ) / (float) $po['total'] : 1;
		foreach ( soko_json( $po['items'] ) as $l ) {
			if ( ! $l['product_id'] ) {
				continue;
			}
			$net_cost = round( (float) $l['cost'] * $share, 2 ); // stock is carried at cost excluding reclaimable VAT
			$cur      = $wpdb->get_row( $wpdb->prepare( 'SELECT stock, cost FROM ' . soko_t( 'products' ) . ' WHERE id = %d', $l['product_id'] ), ARRAY_A ); // phpcs:ignore
			if ( $net_cost > 0 && $cur ) {
				// Weighted average: units already on hand keep their value, new units come in at what they cost.
				// Units already invoiced but not yet delivered are sold: they keep the cost they were sold at.
				$owed_out = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(l.qty),0) FROM ' . soko_t( 'lines' ) . ' l JOIN ' . soko_t( 'docs' ) . " d ON d.id = l.doc_id WHERE l.product_id = %d AND d.type = 'invoice' AND d.status <> 'void' AND COALESCE(d.meta,'') NOT LIKE %s AND " . Soko_Accounts::confirmed_sql( 'd' ), $l['product_id'], '%stock_done%' ) ); // phpcs:ignore
				$old      = max( 0, (float) $cur['stock'] - $owed_out );
				$qty = (float) $l['qty'];
				$avg = $old + $qty > 0 ? round( ( $old * (float) $cur['cost'] + $qty * $net_cost ) / ( $old + $qty ), 2 ) : $net_cost;
				$wpdb->update( soko_t( 'products' ), array( 'cost' => $avg ), array( 'id' => $l['product_id'] ) );
			}
			Soko_Ledger::move( $l['product_id'], $l['qty'], 'Received', $po['number'], true, $net_cost > 0 ? $net_cost : null, $when );
		}
		$wpdb->update(
			soko_t( 'purchases' ),
			array(
				'status'       => 'received',
				'received'     => $when === soko_today() ? soko_now() : $when . ' 12:00:00',
				'supplier_inv' => sanitize_text_field( isset( $b['supplier_inv'] ) ? $b['supplier_inv'] : '' ),
			),
			array( 'id' => $po['id'] )
		);
		// Any amount paid now is added to what was already paid, never replacing it.
		$now = isset( $b['pay_now'] ) ? round( (float) $b['pay_now'], 2 ) : 0;
		$due = round( (float) $po['total'] - (float) $po['paid'], 2 );
		if ( $now > 0 ) {
			$now = min( $now, max( 0, $due ) );
			$wpdb->update( soko_t( 'purchases' ), array( 'paid' => (float) $po['paid'] + $now ), array( 'id' => $po['id'] ) );
			Soko_Accounts::journal( $when, 'Paid supplier, ' . $po['number'], array( array( '2000', $now, 0 ), array( Soko_Accounts::money_account( isset( $b['method'] ) ? $b['method'] : 'bank' ), 0, $now ) ), 'supplier_pay', $po['number'] );
		}
		soko_log( 'Received ' . $po['number'] . ' into stock', 'purchase', $po['number'] );
		return array( 'message' => $po['number'] . ' received. Stock updated.' . ( ! empty( $rest_no ) ? ' The rest is now on ' . $rest_no . '.' : '' ) );
	}

	/* ---- reports ---- */

	public static function report( $req ) {
		$from = sanitize_text_field( $req['from'] ? $req['from'] : current_time( 'Y-m-01' ) );
		$to   = sanitize_text_field( $req['to'] ? $req['to'] : current_time( 'Y-m-t' ) );
		$fmt  = sanitize_key( $req['format'] );
		if ( 'pack' === $req['type'] ) {
			if ( $from > $to ) {
				list( $from, $to ) = array( $to, $from );
			}
			$types = array( 'pl', 'bs', 'cashflow', 'vat', 'tb', 'receivables', 'payables', 'sales', 'expenses', 'stock', 'gl' );
			$reps  = array();
			foreach ( $types as $t ) {
				$reps[] = Soko_Reports::run( $t, $from, $to );
			}
			$biz  = soko_setting( 'business' );
			$name = sanitize_file_name( 'accounts-pack-' . $from . '-to-' . $to );
			soko_log( 'Downloaded accounts pack ' . $from . ' to ' . $to, 'report', 'pack' );
			if ( 'xlsx' === $fmt ) {
				$sheets = array();
				$short = array( 'pl' => 'Profit and loss', 'bs' => 'Balance sheet', 'cashflow' => 'Cash flow', 'vat' => 'VAT', 'tb' => 'Trial balance', 'receivables' => 'Receivables', 'payables' => 'Payables', 'sales' => 'Sales', 'expenses' => 'Expenses', 'stock' => 'Stock', 'gl' => 'General ledger' );
				foreach ( $reps as $r ) {
					// One sheet per report: business, title and period on top, then each section.
					$rows = array( array( $r['title'] ), array( isset( $r['period'] ) && $r['period'] ? $r['period'] : date_i18n( 'j M Y', strtotime( $from ) ) . ' to ' . date_i18n( 'j M Y', strtotime( $to ) ) ), array() );
					foreach ( $r['sections'] as $sec ) {
						$rows[] = array( $sec['title'] );
						$rows[] = $sec['head'];
						foreach ( $sec['rows'] as $row ) {
							unset( $row['_b'] );
							$rows[] = array_values( $row );
						}
						$rows[] = array();
					}
					$sheets[ isset( $short[ $r['type'] ] ) ? $short[ $r['type'] ] : mb_substr( $r['type'], 0, 31 ) ] = array( 'head' => array( $biz ), 'rows' => $rows, 'freeze' => 0 );
				}
				self::send_file( Soko_XLSX::build( $sheets ), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $name . '.xlsx', true );
			}
			$cover = array(
				'title'  => 'Accounts pack',
				'period' => date_i18n( 'j M Y', strtotime( $from ) ) . ' to ' . date_i18n( 'j M Y', strtotime( $to ) ),
				'lines'  => array_merge( array( $biz . ( soko_setting( 'kra_pin' ) ? ' · KRA PIN ' . soko_setting( 'kra_pin' ) : '' ), '', 'Contents:' ), array_map( function ( $r ) { return '•  ' . $r['title']; }, $reps ) ),
			);
			self::send_file( Soko_PDF::pack( $reps, $cover ), 'application/pdf', $name . '.pdf', true );
		}
		if ( in_array( $fmt, array( 'pdf', 'xlsx', 'csv' ), true ) ) {
			list( $body, $mime, $name ) = Soko_Reports::export( sanitize_key( $req['type'] ), $from, $to, $fmt );
			soko_log( 'Downloaded report ' . $name, 'report', $req['type'] );
			self::send_file( $body, $mime, $name, true );
		}
		return Soko_Reports::run( sanitize_key( $req['type'] ), $from, $to );
	}

	/* ---- accounting ---- */

	public static function accounts() {
		$b   = Soko_Accounts::balances( soko_today() );
		$out = array();
		foreach ( Soko_Accounts::chart() as $code => $a ) {
			$v   = isset( $b[ $code ] ) ? round( $b[ $code ], 2 ) : 0;
			$pos = in_array( $a['type'], array( 'asset', 'expense' ), true ) ? $v : -$v;
			$out[] = array( 'code' => (string) $code, 'name' => $a['name'], 'type' => $a['type'], 'key' => $a['key'], 'balance' => round( $pos, 2 ), 'money' => in_array( (string) $code, Soko_Accounts::money_codes(), true ), 'custom' => 'custom' === $a['key'] );
		}
		global $wpdb;
		$D     = soko_t( 'docs' );
		$owed  = (float) $wpdb->get_var( "SELECT SUM(total - paid) FROM $D WHERE type = 'invoice' AND status <> 'void' AND paid < total AND " . Soko_Accounts::confirmed_sql( $D ) ); // phpcs:ignore
		$m0    = current_time( 'Y-m-01' );
		$m1    = current_time( 'Y-m-t' );
		$vat_m = -Soko_Accounts::sum( '2100', $m0, $m1, array( 'invoice', 'credit' ) ) - Soko_Accounts::sum( '1300', $m0, $m1, array( 'expense', 'bill' ) );
		return array( 'accounts' => $out, 'opening' => Soko_Accounts::opening(), 'owed' => round( $owed, 2 ), 'vat_month' => round( $vat_m, 2 ) );
	}

	public static function save_opening( $req ) {
		$b    = $req->get_json_params();
		$date = ! empty( $b['date'] ) ? sanitize_text_field( $b['date'] ) : soko_today();
		$r    = Soko_Accounts::set_opening( $date, isset( $b['amounts'] ) ? (array) $b['amounts'] : array() );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		soko_log( 'Saved opening balances', 'accounts', $date );
		return array( 'message' => 'Opening balances saved. Any difference goes to the Opening balances equity account.', 'opening' => Soko_Accounts::opening() );
	}

	public static function journals( $req ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT j.*, u.display_name AS user FROM ' . soko_t( 'journal' ) . " j LEFT JOIN {$wpdb->users} u ON u.ID = j.user_id WHERE j.source IN ('manual','supplier_pay','opening') ORDER BY j.date DESC, j.id DESC LIMIT 300", ARRAY_A ); // phpcs:ignore
		foreach ( $rows as &$r ) {
			$r['lines'] = json_decode( $r['entries'], true );
			unset( $r['entries'] );
			$r['amount'] = array_sum( wp_list_pluck( (array) $r['lines'], 'debit' ) );
		}
		return $rows;
	}

	public static function save_journal( $req ) {
		$b = $req->get_json_params();
		if ( empty( $b['date'] ) ) {
			return new WP_Error( 'soko', 'Choose the date.', array( 'status' => 400 ) );
		}
		if ( empty( $b['memo'] ) ) {
			return new WP_Error( 'soko', 'Describe what this entry is for.', array( 'status' => 400 ) );
		}
		$id = Soko_Accounts::journal( $b['date'], $b['memo'], isset( $b['lines'] ) ? $b['lines'] : array(), 'manual', isset( $b['ref'] ) ? $b['ref'] : '' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		soko_log( 'Added journal entry: ' . sanitize_text_field( $b['memo'] ), 'accounts', $id );
		return array( 'id' => $id, 'message' => 'Journal entry saved.' );
	}

	public static function delete_journal( $req ) {
		global $wpdb;
		$t = soko_t( 'journal' );
		$j = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $req['id'] ), ARRAY_A ); // phpcs:ignore
		if ( ! $j || 'manual' !== $j['source'] ) {
			return new WP_Error( 'soko', 'Only manual journal entries can be deleted here.', array( 'status' => 400 ) );
		}
		if ( $lk = Soko_Accounts::locked( $j['date'] ) ) { // phpcs:ignore
			return $lk;
		}
		foreach ( (array) json_decode( $j['entries'], true ) as $jl ) {
			$done = Soko_Accounts::reconciled( (string) $jl['code'] );
			if ( isset( $done[ 'manual:j' . $j['id'] . ':' . $jl['code'] ] ) ) {
				return new WP_Error( 'soko', 'This entry is ticked in a bank reconciliation. Untick it there first, or post a correcting entry instead.', array( 'status' => 400 ) );
			}
		}
		$wpdb->delete( $t, array( 'id' => (int) $j['id'] ) );
		Soko_Accounts::flush();
		soko_log( 'Deleted journal entry: ' . $j['memo'], 'accounts', $j['id'] );
		return array( 'message' => 'Journal entry deleted.' );
	}

	public static function recon( $req ) {
		$code  = (string) $req['code'];
		$done  = Soko_Accounts::reconciled( $code );
		$stmts = (array) get_option( 'soko_recon_stmt', array() );
		$st    = isset( $stmts[ $code ] ) ? (array) $stmts[ $code ] : array();
		$to    = ! empty( $req['to'] ) ? sanitize_text_field( $req['to'] ) : ( ! empty( $st['date'] ) ? $st['date'] : soko_today() );
		$rows  = array();
		$bal   = 0;
		$rec   = 0;
		foreach ( Soko_Accounts::postings() as $p ) {
			if ( (string) $p[1] !== $code || substr( $p[0], 0, 10 ) > $to ) {
				continue;
			}
			$k    = Soko_Accounts::recon_key( $p );
			$amt  = round( $p[2] - $p[3], 2 );
			$bal += $amt;
			if ( isset( $done[ $k ] ) ) {
				$rec += $amt;
			}
			$rows[] = array( 'key' => $k, 'date' => $p[0], 'memo' => $p[4], 'ref' => $p[5], 'amount' => $amt, 'done' => isset( $done[ $k ] ) );
		}
		$hist = (array) get_option( 'soko_recon_hist', array() );
		$last = ! empty( $hist[ $code ] ) ? end( $hist[ $code ] ) : null;
		return array(
			'begin'   => $last ? (float) $last['balance'] : 0,
			'begin_date' => $last ? $last['date'] : '',
			'code'    => $code,
			'name'    => Soko_Accounts::name( $code ),
			'to'      => $to,
			'stmt'    => isset( $st['balance'] ) && '' !== $st['balance'] ? (float) $st['balance'] : null,
			'book'    => round( $bal, 2 ),
			'cleared' => round( $rec, 2 ),
			'rows'    => array_reverse( $rows ),
			'history' => isset( $hist[ $code ] ) ? array_reverse( array_slice( (array) $hist[ $code ], -12 ) ) : array(),
		);
	}

	public static function save_recon( $req ) {
		$b    = $req->get_json_params();
		$code = (string) $req['code'];
		$do   = isset( $b['do'] ) ? sanitize_key( $b['do'] ) : 'tick';
		if ( 'statement' === $do ) {
			$d = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
			if ( is_wp_error( $d ) ) {
				return $d;
			}
			$stmts          = (array) get_option( 'soko_recon_stmt', array() );
			$stmts[ $code ] = array( 'date' => $d, 'balance' => isset( $b['balance'] ) && '' !== $b['balance'] ? round( (float) $b['balance'], 2 ) : '' );
			update_option( 'soko_recon_stmt', $stmts, false );
			$req['to'] = $d;
			return self::recon( $req );
		}
		if ( 'charge' === $do ) {
			$amt  = round( (float) ( isset( $b['amount'] ) ? $b['amount'] : 0 ), 2 );
			$d    = self::pick_date( isset( $b['date'] ) ? $b['date'] : '' );
			$kind = isset( $b['kind'] ) && 'interest' === $b['kind'] ? 'interest' : 'charge';
			if ( is_wp_error( $d ) ) {
				return $d;
			}
			if ( $amt <= 0 ) {
				return new WP_Error( 'soko', 'Enter the amount shown on the statement.', array( 'status' => 400 ) );
			}
			if ( 'charge' === $kind ) {
				$other = '';
				foreach ( Soko_Accounts::chart() as $c => $a ) {
					if ( 'expense' === $a['type'] && preg_match( '/bank|transaction|charges/i', $a['name'] ) ) {
						$other = (string) $c;
						break;
					}
				}
				if ( ! $other ) {
					$other = Soko_Accounts::add_account( '6950', 'Bank and M-Pesa charges', 'expense' );
					if ( is_wp_error( $other ) ) {
						return $other;
					}
				}
				$lines = array( array( $other, $amt, 0 ), array( $code, 0, $amt ) );
				$memo  = 'Charges of ' . soko_money_exact( $amt ) . ' on the ' . Soko_Accounts::name( $code ) . ' statement, ' . date_i18n( 'j M Y', strtotime( $d ) );
			} else {
				$lines = array( array( $code, $amt, 0 ), array( '4950', 0, $amt ) );
				$memo  = 'Interest of ' . soko_money_exact( $amt ) . ' on the ' . Soko_Accounts::name( $code ) . ' statement, ' . date_i18n( 'j M Y', strtotime( $d ) );
			}
			$j = Soko_Accounts::journal( $d, $memo, $lines, 'manual' );
			if ( is_wp_error( $j ) ) {
				return $j;
			}
			Soko_Accounts::flush();
			// It is on the statement by definition, so tick it.
			foreach ( Soko_Accounts::postings() as $p ) {
				if ( 'j' . (int) $j === (string) $p[7] && (string) $p[1] === $code ) {
					Soko_Accounts::set_reconciled( $code, array( Soko_Accounts::recon_key( $p ) ), true );
				}
			}
			return self::recon( $req );
		}
		if ( 'finish' === $do ) {
			$r = self::recon( $req );
			if ( null === $r['stmt'] || abs( $r['stmt'] - $r['cleared'] ) > 0.01 ) {
				return new WP_Error( 'soko', 'The ticked items must add up to the statement balance before you finish.', array( 'status' => 400 ) );
			}
			$hist            = (array) get_option( 'soko_recon_hist', array() );
			$hist[ $code ][] = array( 'date' => $r['to'], 'balance' => $r['stmt'], 'by' => wp_get_current_user()->display_name, 'at' => soko_now() );
			update_option( 'soko_recon_hist', $hist, false );
			$stmts = (array) get_option( 'soko_recon_stmt', array() );
			unset( $stmts[ $code ] ); // the next reconciliation starts fresh
			update_option( 'soko_recon_stmt', $stmts, false );
			soko_log( 'Reconciled ' . $r['name'] . ' to ' . $r['to'], 'accounts', $code );
			$req['to'] = '';
			return self::recon( $req );
		}
		$keys = isset( $b['keys'] ) ? array_map( 'strval', (array) $b['keys'] ) : array();
		if ( empty( $b['on'] ) ) {
			$hist = (array) get_option( 'soko_recon_hist', array() );
			$last = ! empty( $hist[ $code ] ) ? end( $hist[ $code ] ) : null;
			if ( $last ) {
				foreach ( Soko_Accounts::postings() as $p ) {
					if ( (string) $p[1] === $code && in_array( Soko_Accounts::recon_key( $p ), $keys, true ) && substr( $p[0], 0, 10 ) <= $last['date'] ) {
						return new WP_Error( 'soko', 'This item is part of the reconciliation finished on ' . date_i18n( 'j M Y', strtotime( $last['date'] ) ) . ', so it stays ticked.', array( 'status' => 400 ) );
					}
				}
			}
		}
		Soko_Accounts::set_reconciled( $code, $keys, ! empty( $b['on'] ) );
		return self::recon( $req );
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
		if ( array_key_exists( 'business', (array) $b ) && '' === trim( (string) $b['business'] ) ) {
			return new WP_Error( 'soko', 'The business name cannot be blank. It prints on every invoice.', array( 'status' => 400 ) );
		}
		foreach ( array( 'email' => 'business email', 'notify_email' => 'alerts email' ) as $ek => $el ) {
			if ( ! empty( $b[ $ek ] ) && ! is_email( $b[ $ek ] ) ) {
				return new WP_Error( 'soko', sprintf( 'The %s does not look right, for example name@gmail.com.', $el ), array( 'status' => 400 ) );
			}
		}
		foreach ( array( 'phone' => 'phone number', 'phone2' => 'second phone number', 'whatsapp' => 'WhatsApp number' ) as $pk => $pl ) {
			if ( ! empty( $b[ $pk ] ) && ! soko_phone_valid( $b[ $pk ] ) ) {
				return new WP_Error( 'soko', sprintf( 'The %s does not look right. Use 10 digits like 0712 345 678.', $pl ), array( 'status' => 400 ) );
			}
		}
		if ( ! empty( $b['kra_pin'] ) && ! preg_match( '/^[AP]\d{9}[A-Z]$/i', trim( $b['kra_pin'] ) ) ) {
			return new WP_Error( 'soko', 'A KRA PIN has 11 characters, like P051234567X.', array( 'status' => 400 ) );
		}
		if ( ! empty( $b['whatsapp'] ) ) {
			$b['whatsapp'] = soko_phone( $b['whatsapp'] );
		}
		if ( ! empty( $b['kra_pin'] ) ) {
			$b['kra_pin'] = strtoupper( trim( $b['kra_pin'] ) );
		}
		if ( isset( $b['vat_rate'] ) && ( ! is_numeric( $b['vat_rate'] ) || $b['vat_rate'] < 0 || $b['vat_rate'] > 30 ) ) {
			return new WP_Error( 'soko', 'Enter a VAT rate between 0 and 30%. Kenya\'s standard rate is 16%.', array( 'status' => 400 ) );
		}
		foreach ( array( 'invoice_due' => 'Invoice payment days', 'quote_valid' => 'Quotation validity' ) as $dk => $dl ) {
			if ( isset( $b[ $dk ] ) && ( ! is_numeric( $b[ $dk ] ) || (int) $b[ $dk ] < 1 || (int) $b[ $dk ] > 365 ) ) {
				return new WP_Error( 'soko', $dl . ' must be between 1 and 365 days.', array( 'status' => 400 ) );
			}
		}
		if ( isset( $b['lock_date'] ) && '' !== $b['lock_date'] ) {
			$ld = self::pick_date( $b['lock_date'] );
			if ( is_wp_error( $ld ) ) {
				return new WP_Error( 'soko', 'The closing date must be today or earlier.', array( 'status' => 400 ) );
			}
			if ( $ld !== soko_setting( 'lock_date' ) ) {
				soko_log( 'Books closed up to ' . $ld, 'accounts', $ld );
			}
		}
		if ( isset( $b['deposit_percent'] ) && ( ! is_numeric( $b['deposit_percent'] ) || $b['deposit_percent'] < 0 || $b['deposit_percent'] > 100 ) ) {
			return new WP_Error( 'soko', 'Enter a deposit between 0 and 100%.', array( 'status' => 400 ) );
		}
		global $wpdb;
		foreach ( array( 'quote' => 'quotation', 'invoice' => 'invoice', 'receipt' => 'receipt' ) as $k => $label ) {
			if ( ! isset( $b[ 'next_' . $k ] ) || (int) $b[ 'next_' . $k ] === (int) $cur[ 'next_' . $k ] ) {
				continue;
			}
			$n      = (int) $b[ 'next_' . $k ];
			$prefix = isset( $b[ 'prefix_' . $k ] ) ? (string) $b[ 'prefix_' . $k ] : (string) $cur[ 'prefix_' . $k ];
			$taken  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . soko_t( 'docs' ) . ' WHERE number = %s', $prefix . $n ) ); // phpcs:ignore
			if ( $n < 1 || $taken ) {
				return new WP_Error( 'soko', sprintf( '%s%d has already been used. Choose a next %s number higher than any already issued.', $prefix, $n, $label ), array( 'status' => 400 ) );
			}
		}
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
				'label'    => 'administrator' === $role ? 'Owner (full access)' : soko_roles()[ $role ][0],
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
		if ( ! empty( $b['phone'] ) && ! soko_phone_valid( $b['phone'] ) ) {
			return new WP_Error( 'soko', 'That phone number does not look right. Use 10 digits like 0712 345 678.', array( 'status' => 400 ) );
		}
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
			wp_update_user( array( 'ID' => $id, 'display_name' => sanitize_text_field( $b['name'] ), 'first_name' => sanitize_text_field( $b['name'] ), 'user_email' => sanitize_email( $b['email'] ) ) );
			// Change the sign-in username (WordPress has no built-in way to rename one).
			$nu = isset( $b['username'] ) ? sanitize_user( strtolower( trim( (string) $b['username'] ) ), true ) : '';
			if ( $nu && $nu !== $u->user_login ) {
				if ( username_exists( $nu ) ) {
					return new WP_Error( 'soko', 'That username is already taken.', array( 'status' => 400 ) );
				}
				global $wpdb;
				$wpdb->update( $wpdb->users, array( 'user_login' => $nu, 'user_nicename' => sanitize_title( $nu ) ), array( 'ID' => $id ) );
				clean_user_cache( $id );
				soko_log( 'Changed username to ' . $nu, 'user', $id );
			}
			$u = new WP_User( $id );
			$u->set_role( $role );
			update_user_meta( $id, 'soko_phone', sanitize_text_field( isset( $b['phone'] ) ? $b['phone'] : '' ) );
			update_user_meta( $id, 'soko_disabled', ! empty( $b['disabled'] ) ? 1 : 0 );
			if ( isset( $b['password'] ) && '' !== $b['password'] ) {
				if ( strlen( (string) $b['password'] ) < 8 ) {
					return new WP_Error( 'soko', 'Use a password of at least 8 characters.', array( 'status' => 400 ) );
				}
				wp_set_password( (string) $b['password'], $id );
				soko_log( 'Set a new password for ' . $b['name'], 'user', $id );
			}
			if ( ! empty( $b['reset'] ) ) {
				retrieve_password( $u->user_login );
			}
			soko_log( 'Updated team member ' . $b['name'], 'user', $id );
			return array( 'message' => 'Saved.' );
		}
		$email = sanitize_email( isset( $b['email'] ) ? $b['email'] : '' );
		$uname = sanitize_user( strtolower( isset( $b['username'] ) ? $b['username'] : '' ), true );
		if ( $email && ( ! is_email( $email ) || email_exists( $email ) ) ) {
			return new WP_Error( 'soko', 'Use a valid email address that is not already in use.', array( 'status' => 400 ) );
		}
		if ( ! $email && ! $uname ) {
			return new WP_Error( 'soko', 'Give an email address, or a username if they have no email yet.', array( 'status' => 400 ) );
		}
		$login = $uname ? $uname : sanitize_user( strtolower( strtok( $email, '@' ) ), true );
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
		$sent = ! $email ? false : soko_mail(
			$email,
			'Your Dynamic Desk account',
			sprintf( "Hello %s,\n\nAn account has been created for you on Dynamic Desk.\n\nSign in: %s\nEmail: %s\nPassword: %s\n\nPlease change your password after signing in.\n\n%s", sanitize_text_field( $b['name'] ), home_url( '/desk/' ), $email, $pass, soko_setting( 'business' ) )
		);
		soko_log( 'Invited ' . $b['name'] . ' as ' . soko_roles()[ $role ][0], 'user', $uid );
		if ( ! $email ) {
			return array( 'id' => $uid, 'login' => $login, 'password' => empty( $b['password'] ) ? $pass : '', 'message' => 'Account created.' );
		}
		return array( 'id' => $uid, 'login' => $login, 'password' => empty( $b['password'] ) ? $pass : '', 'sent' => $sent, 'message' => 'Account created.' );
	}

	/** Remove test records (owner only): their documents, payments and stock movements, then the customers and leads. */
	public static function cleanup( $req ) {
		global $wpdb;
		$b    = $req->get_json_params();
		$cust = array_map( 'intval', (array) ( isset( $b['customers'] ) ? $b['customers'] : array() ) );
		$lead = array_map( 'intval', (array) ( isset( $b['leads'] ) ? $b['leads'] : array() ) );
		$n    = array( 'docs' => 0, 'customers' => 0, 'leads' => 0 );
		foreach ( $cust as $cid ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, number FROM ' . soko_t( 'docs' ) . ' WHERE customer_id = %d', $cid ), ARRAY_A ) as $d ) { // phpcs:ignore
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, product_id, qty FROM ' . soko_t( 'moves' ) . ' WHERE ref = %s', $d['number'] ), ARRAY_A ) as $m ) { // phpcs:ignore
					$wpdb->query( $wpdb->prepare( 'UPDATE ' . soko_t( 'products' ) . ' SET stock = stock - %f WHERE id = %d', (float) $m['qty'], (int) $m['product_id'] ) ); // phpcs:ignore
					$wpdb->delete( soko_t( 'moves' ), array( 'id' => (int) $m['id'] ) );
				}
				$wpdb->delete( soko_t( 'payments' ), array( 'doc_id' => (int) $d['id'] ) );
				$wpdb->delete( soko_t( 'lines' ), array( 'doc_id' => (int) $d['id'] ) );
				$wpdb->delete( soko_t( 'jobs' ), array( 'doc_id' => (int) $d['id'] ) );
				$wpdb->delete( soko_t( 'docs' ), array( 'id' => (int) $d['id'] ) );
				++$n['docs'];
			}
			$n['customers'] += (int) $wpdb->delete( soko_t( 'customers' ), array( 'id' => $cid ) );
		}
		foreach ( $lead as $lid ) {
			$n['leads'] += (int) $wpdb->delete( soko_t( 'leads' ), array( 'id' => $lid ) );
		}
		Soko_Accounts::flush();
		soko_log( 'Removed test records', 'system', wp_json_encode( $n ) );
		return $n;
	}

	/** The signed-in person chooses their own password. */
	public static function my_password( $req ) {
		$b  = $req->get_json_params();
		$pw = isset( $b['password'] ) ? (string) $b['password'] : '';
		if ( strlen( $pw ) < 8 ) {
			return new WP_Error( 'soko', 'Use at least 8 characters.', array( 'status' => 400 ) );
		}
		$uid = get_current_user_id();
		wp_set_password( $pw, $uid );
		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, true, is_ssl() );
		soko_log( 'Changed own password', 'user', $uid );
		return array( 'message' => 'Password saved. Next time, sign in with ' . wp_get_current_user()->user_login . ' and this password.' );
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
