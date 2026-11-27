<?php
/**
 * Shared helpers.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

/** Full table name. */
function soko_t( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'soko_' . $name;
}

/** KES money string. */
/** Money for line items: keeps cents when a price has them, so the sums add up. */
function soko_money_exact( $n, $symbol = true ) {
	$n = round( (float) $n, 2 );
	$s = number_format( $n, abs( $n - round( $n ) ) > 0.004 ? 2 : 0, '.', ',' );
	return $symbol ? soko_setting( 'currency', 'KES' ) . ' ' . $s : $s;
}

function soko_money( $n, $symbol = true ) {
	$s = number_format( (float) $n, 0, '.', ',' );
	return $symbol ? soko_setting( 'currency', 'KES' ) . ' ' . $s : $s;
}

/** Normalise a Kenyan phone number to 2547XXXXXXXX / 2541XXXXXXXX. */
/** LIKE pattern that finds a phone however it was typed (0712 345…, +254 712…, 712…). */
function soko_phone_like( $q ) {
	global $wpdb;
	$raw = trim( (string) $q );
	if ( ! preg_match( '/^[\d\s+()-]{4,}$/', $raw ) ) {
		return '%' . $wpdb->esc_like( $raw ) . '%';
	}
	$d = preg_replace( '/\D+/', '', $raw );
	if ( 0 === strpos( $d, '254' ) ) {
		$d = substr( $d, 3 );
	} elseif ( 0 === strpos( $d, '0' ) ) {
		$d = substr( $d, 1 );
	}
	return '%' . $wpdb->esc_like( $d ) . '%';
}

/** A real phone number: Kenyan (07…/01…, +254…) or another country in international form (+…). */
function soko_phone_valid( $p ) {
	$raw = trim( (string) $p );
	$d   = preg_replace( '/\D+/', '', $raw );
	if ( 0 === strpos( $raw, '+' ) && 0 !== strpos( $d, '254' ) ) {
		return strlen( $d ) >= 8 && strlen( $d ) <= 15;
	}
	return (bool) preg_match( '/^254[17]\d{8}$/', soko_phone( $raw ) );
}

function soko_phone( $p ) {
	$d = preg_replace( '/\D+/', '', (string) $p );
	if ( 0 === strpos( $d, '0' ) && 10 === strlen( $d ) ) {
		$d = '254' . substr( $d, 1 );
	} elseif ( 9 === strlen( $d ) && in_array( $d[0], array( '7', '1' ), true ) ) {
		$d = '254' . $d;
	}
	return $d;
}

/** Display a phone number as 07XX XXX XXX. */
function soko_phone_display( $p ) {
	$d = soko_phone( $p );
	if ( 12 === strlen( $d ) && 0 === strpos( $d, '254' ) ) {
		$l = '0' . substr( $d, 3 );
		return substr( $l, 0, 4 ) . ' ' . substr( $l, 4, 3 ) . ' ' . substr( $l, 7 );
	}
	return (string) $p;
}

function soko_json( $v, $default = array() ) {
	if ( is_array( $v ) ) {
		return $v;
	}
	$d = json_decode( (string) $v, true );
	return is_array( $d ) ? $d : $default;
}

function soko_now() {
	return current_time( 'mysql' );
}

function soko_today() {
	return current_time( 'Y-m-d' );
}

/** Desk modules and the capability each needs. */
function soko_modules() {
	return array(
		'home'       => array( 'Dashboard', 'soko_dashboard', 'Overview' ),
		'sales'      => array( 'Sales and invoices', 'soko_sales', 'Sell' ),
		'payments'   => array( 'Payments', 'soko_payments', 'Sell' ),
		'crm'        => array( 'Customers and leads', 'soko_crm', 'Sell' ),
		'jobs'       => array( 'Installations', 'soko_jobs', 'Operate' ),
		'schedule'   => array( 'Schedule', 'soko_schedule', 'Operate' ),
		'stock'      => array( 'Products and stock', 'soko_stock', 'Operate' ),
		'purchases'  => array( 'Purchases', 'soko_purchases', 'Operate' ),
		'expenses'   => array( 'Expenses', 'soko_expenses', 'Money' ),
		'accounts'   => array( 'Accounts', 'soko_reports', 'Money' ),
		'reports'    => array( 'Reports', 'soko_reports', 'Money' ),
		'compliance' => array( 'Tax and compliance', 'soko_compliance', 'Money' ),
		'studio'     => array( 'Site Studio', 'soko_site', 'Website' ),
		'blog'       => array( 'Blog', 'soko_site', 'Website' ),
		'users'      => array( 'Team and access', 'soko_users', 'Admin' ),
		'settings'   => array( 'Settings', 'soko_settings', 'Admin' ),
	);
}

/** Roles shipped with the suite: label => capabilities. */
function soko_roles() {
	$all = wp_list_pluck( soko_modules(), 1 );
	return array(
		'soko_owner'      => array( 'Owner', $all ),
		'soko_manager'    => array( 'Manager', array_diff( $all, array( 'soko_users', 'soko_settings' ) ) ),
		'soko_accountant' => array( 'Accountant', array( 'soko_dashboard', 'soko_sales', 'soko_payments', 'soko_purchases', 'soko_expenses', 'soko_reports', 'soko_compliance' ) ),
		'soko_sales'      => array( 'Sales', array( 'soko_dashboard', 'soko_sales', 'soko_payments', 'soko_crm', 'soko_schedule', 'soko_stock' ) ),
		'soko_technician' => array( 'Technician', array( 'soko_jobs', 'soko_schedule', 'soko_stock' ) ),
		'soko_store'      => array( 'Store keeper', array( 'soko_stock', 'soko_purchases', 'soko_schedule', 'soko_sales' ) ),
		'soko_web'        => array( 'Website editor', array( 'soko_site', 'soko_stock' ) ),
	);
}

/** Can the current user open the Desk at all? */
function soko_is_staff( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	if ( ! $user || ! $user->exists() || get_user_meta( $user->ID, 'soko_disabled', true ) ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return true;
	}
	foreach ( soko_modules() as $m ) {
		if ( user_can( $user, $m[1] ) ) {
			return true;
		}
	}
	return false;
}

function soko_can( $cap ) {
	return current_user_can( 'manage_options' ) || current_user_can( $cap );
}

/** Log an action to the activity table. */
function soko_log( $action, $object = '', $ref = '' ) {
	global $wpdb;
	$wpdb->insert(
		soko_t( 'activity' ),
		array(
			'user_id' => get_current_user_id(),
			'action'  => substr( (string) $action, 0, 190 ),
			'object'  => substr( (string) $object, 0, 60 ),
			'ref'     => substr( (string) $ref, 0, 60 ),
			'created' => soko_now(),
		)
	);
}

/** Inline SVG icon set used by the storefront and the Desk. */
function soko_icon( $k, $size = 40 ) {
	static $icons = null;
	if ( null === $icons ) {
		$icons = array(
			'panel'    => '<rect x="8" y="14" width="48" height="30" rx="2" fill="currentColor" opacity=".16"/><path d="M8 14h48v30H8zM20 14v30M32 14v30M44 14v30M8 24h48M8 34h48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="M26 44l-4 10M38 44l4 10M18 54h28" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
			'kit'      => '<rect x="6" y="10" width="30" height="20" rx="2" fill="currentColor" opacity=".16"/><path d="M6 10h30v20H6zM16 10v20M26 10v20M6 20h30" fill="none" stroke="currentColor" stroke-width="2.4"/><rect x="40" y="22" width="18" height="30" rx="3" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M45 30h8M45 36h8" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><path d="M21 30v16h19" fill="none" stroke="currentColor" stroke-width="2.4" stroke-dasharray="3 3"/>',
			'inverter' => '<rect x="14" y="8" width="36" height="48" rx="5" fill="currentColor" opacity=".16"/><rect x="14" y="8" width="36" height="48" rx="5" fill="none" stroke="currentColor" stroke-width="2.4"/><rect x="21" y="16" width="22" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M24 23q3-5 6 0t6 0" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="26" cy="42" r="3" fill="currentColor"/><circle cx="38" cy="42" r="3" fill="currentColor"/>',
			'battery'  => '<rect x="12" y="16" width="40" height="38" rx="4" fill="currentColor" opacity=".16"/><rect x="12" y="16" width="40" height="38" rx="4" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M22 16v-6h6v6M36 16v-6h6v6" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M34 24l-8 13h8l-3 10 9-15h-8l2-8z" fill="currentColor"/>',
			'station'  => '<rect x="10" y="18" width="44" height="34" rx="6" fill="currentColor" opacity=".16"/><rect x="10" y="18" width="44" height="34" rx="6" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M22 18v-6h20v6" fill="none" stroke="currentColor" stroke-width="2.4"/><rect x="17" y="26" width="14" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="40" cy="42" r="3" fill="currentColor"/><circle cx="47" cy="42" r="3" fill="currentColor"/>',
			'heater'   => '<path d="M6 50l16-26h36L42 50z" fill="currentColor" opacity=".16"/><path d="M6 50l16-26h36L42 50zM14 37h38M30 24l-8 26M44 24l-8 26" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><rect x="20" y="8" width="38" height="12" rx="6" fill="none" stroke="currentColor" stroke-width="2.4"/>',
			'pump'     => '<path d="M10 54V30a6 6 0 0 1 6-6h10v30z" fill="currentColor" opacity=".16"/><path d="M10 54V30a6 6 0 0 1 6-6h10v30zM26 34h12a6 6 0 0 1 6 6v14" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M44 8c-5 7-7 10-7 13a7 7 0 0 0 14 0c0-3-2-6-7-13z" fill="currentColor"/>',
			'bulb'     => '<path d="M32 8a16 16 0 0 0-9 29c2 2 3 4 3 7h12c0-3 1-5 3-7A16 16 0 0 0 32 8z" fill="currentColor" opacity=".16"/><path d="M32 8a16 16 0 0 0-9 29c2 2 3 4 3 7h12c0-3 1-5 3-7A16 16 0 0 0 32 8zM26 50h12M28 56h8" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
			'street'   => '<path d="M22 58V12h20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/><rect x="34" y="8" width="20" height="9" rx="2" fill="currentColor"/><path d="M38 20l-4 10M46 20v12M54 20l4 10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" opacity=".6"/><path d="M12 58h20" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>',
			'cctv'     => '<path d="M10 22l34-8 4 14-34 8z" fill="currentColor" opacity=".16"/><path d="M10 22l34-8 4 14-34 8zM48 21l8-2v10l-6 2" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/><path d="M24 35l4 12h-12v8" fill="none" stroke="currentColor" stroke-width="2.4"/><circle cx="18" cy="25" r="2.5" fill="currentColor"/>',
			'carbat'   => '<rect x="8" y="20" width="48" height="32" rx="4" fill="currentColor" opacity=".16"/><rect x="8" y="20" width="48" height="32" rx="4" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M16 20v-6h8v6M40 20v-6h8v6M17 34h6M45 31v6M42 34h6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
			'cable'    => '<path d="M10 46c10 0 10-28 22-28s12 28 22 28" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><rect x="4" y="40" width="10" height="12" rx="2" fill="currentColor"/><rect x="50" y="40" width="10" height="12" rx="2" fill="currentColor" opacity=".5"/>',
			'tools'    => '<path d="M40 8a10 10 0 0 0-9 14L10 43a4 4 0 0 0 6 6l21-21a10 10 0 0 0 14-9l-6 6-6-2-2-6z" fill="currentColor" opacity=".16"/><path d="M40 8a10 10 0 0 0-9 14L10 43a4 4 0 0 0 6 6l21-21a10 10 0 0 0 14-9l-6 6-6-2-2-6z" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"/>',
			'shield'   => '<path d="M32 6l20 8v16c0 13-9 22-20 26C21 52 12 43 12 30V14z" fill="currentColor" opacity=".16"/><path d="M32 6l20 8v16c0 13-9 22-20 26C21 52 12 43 12 30V14z" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="M24 31l6 6 11-12" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>',
		);
	}
	$body = isset( $icons[ $k ] ) ? $icons[ $k ] : $icons['panel'];
	return '<svg viewBox="0 0 64 64" width="' . (int) $size . '" height="' . (int) $size . '" aria-hidden="true">' . $body . '</svg>';
}

function soko_icon_names() {
	return array( 'panel', 'kit', 'inverter', 'battery', 'station', 'heater', 'pump', 'bulb', 'street', 'cctv', 'carbat', 'cable', 'tools', 'shield' );
}

/** Store links without the domain, so the site survives a move to a new domain. */
function soko_rel( $v ) {
	if ( is_array( $v ) ) {
		return array_map( 'soko_rel', $v );
	}
	if ( ! is_string( $v ) ) {
		return $v;
	}
	$v = str_replace( SOKO_URL . 'assets/', 'soko:', $v );
	foreach ( array( home_url( '/' ), set_url_scheme( home_url( '/' ), 'http' ), set_url_scheme( home_url( '/' ), 'https' ) ) as $h ) {
		$v = str_replace( $h, 'site:/', $v );
	}
	return $v;
}

/** Turn stored relative links back into full URLs for this domain. */
function soko_abs( $v ) {
	if ( is_array( $v ) ) {
		return array_map( 'soko_abs', $v );
	}
	if ( ! is_string( $v ) ) {
		return $v;
	}
	if ( false === strpos( $v, 'soko:' ) && false === strpos( $v, 'site:/' ) ) {
		return $v;
	}
	return str_replace( array( 'soko:', 'site:/' ), array( SOKO_URL . 'assets/', home_url( '/' ) ), $v );
}

/** Send email without ever breaking a sale: hosts that disable mail() throw, so failures are logged, not fatal. */
function soko_mail( $to, $subject, $body, $headers = array(), $files = array() ) {
	if ( ! empty( $GLOBALS['soko_no_mail'] ) || ! $to ) {
		return false;
	}
	$reply = soko_setting( 'email' );
	if ( $reply && is_email( $reply ) && ! preg_grep( '/^reply-to:/i', (array) $headers ) ) {
		$headers   = (array) $headers;
		$headers[] = 'Reply-To: ' . soko_setting( 'business' ) . ' <' . $reply . '>';
	}
	try {
		return wp_mail( $to, $subject, $body, $headers, $files );
	} catch ( \Throwable $e ) {
		update_option( 'soko_mail_error', substr( $e->getMessage(), 0, 300 ), false );
		return false;
	}
}

/** Emails come from the business, not "WordPress". */
add_filter(
	'wp_mail_from_name',
	function ( $n ) {
		$b = soko_setting( 'business' );
		return ( $b && 'WordPress' === $n ) ? $b : $n;
	}
);
add_filter(
	'wp_mail_from',
	function ( $e ) {
		$c = soko_setting( 'smtp' );
		if ( ! empty( $c['user'] ) && is_email( $c['user'] ) ) {
			return $c['user'];
		}
		$b = soko_setting( 'email' );
		if ( $b && is_email( $b ) && 0 === strpos( $e, 'wordpress@' ) ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			// Only use the business address directly when it is on this site's domain, so mail is not rejected as spoofed.
			if ( $host && false !== stripos( $b, '@' . preg_replace( '/^www\./', '', $host ) ) ) {
				return $b;
			}
		}
		return $e;
	}
);

/** Use the SMTP account from Settings, when one is filled in. */
add_action(
	'phpmailer_init',
	function ( $m ) {
		$c = soko_setting( 'smtp' );
		if ( empty( $c['host'] ) ) {
			return;
		}
		$m->isSMTP();
		$m->Host       = $c['host'];
		$m->Port       = (int) ( $c['port'] ? $c['port'] : 587 );
		$m->SMTPAuth   = ! empty( $c['user'] );
		$m->Username   = $c['user'];
		$m->Password   = $c['pass'];
		$m->SMTPSecure = 'ssl' === $c['secure'] ? 'ssl' : ( 'none' === $c['secure'] ? '' : 'tls' );
		if ( ! empty( $c['user'] ) && is_email( $c['user'] ) ) {
			$m->setFrom( $c['user'], soko_setting( 'business' ), false );
		}
	}
);
