<?php
/**
 * Business settings with defaults.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Settings {

	public static function defaults() {
		return array(
			'business'        => 'Dynamic Smart Systems',
			'tagline'         => 'Solar Solutions & IT Consultancy',
			'email'           => 'dynamicsmartsystems@gmail.com',
			'phone'           => '0712 936 685',
			'phone2'          => '0790 787 533',
			'whatsapp'        => '254712936685',
			'address'         => 'P.O. Box 51225 – 00100, Nairobi',
			'pickup_address'  => '',
			'hours'           => 'Monday to Saturday, 8:00 to 18:00',
			'kra_pin'         => '',
			'currency'        => 'KES',
			'vat_rate'        => 16,
			'vat_registered'  => 1,
			'prices_incl_vat' => 1,
			'prefix_quote'    => 'QUO-',
			'prefix_invoice'  => 'INV-',
			'prefix_receipt'  => 'RCT-',
			'prefix_po'       => 'PO-',
			'prefix_credit'   => 'CN-',
			'next_quote'      => 1001,
			'next_invoice'    => 1001,
			'next_receipt'    => 1001,
			'next_po'         => 101,
			'next_credit'     => 1001,
			'quote_valid'     => 14,
			'invoice_due'     => 14,
			'lock_date'       => '',
			'deposit_percent' => 60,
			'notify_email'    => 'dynamicsmartsystems@gmail.com',
			'invoice_note'    => 'Thank you for choosing Dynamic Smart Systems. Goods remain the property of Dynamic Smart Systems until paid in full.',
			'bank_details'    => '',
			'delivery'        => array(
				array( 'label' => 'Pick up in Nairobi', 'note' => 'Ready in 2 hours', 'fee' => 0 ),
				array( 'label' => 'Nairobi delivery', 'note' => 'Same or next day', 'fee' => 1500 ),
				array( 'label' => 'Countrywide courier', 'note' => '2 to 4 days', 'fee' => 3500 ),
			),
			'install_rate'    => 8,
			'expense_cats'    => array( 'Transport and fuel', 'Salaries and wages', 'Rent and utilities', 'Marketing', 'Tools and equipment', 'Licences and compliance', 'Subcontractors', 'Bank and M-Pesa charges', 'Office and admin', 'Other' ),
			'lead_stages'     => array( 'New', 'Contacted', 'Survey booked', 'Quoted', 'Won', 'Lost' ),
			'job_stages'      => array( 'Survey', 'Scheduled', 'Installing', 'Commissioned', 'Maintenance' ),
			'checklist'       => array( 'Site survey done', 'Materials picked from store', 'Panels mounted', 'Wiring and protection', 'Inverter and battery set up', 'System tested', 'Customer trained', 'Handover signed' ),
			'mpesa'           => array(
				'env'       => 'sandbox',
				'type'      => 'paybill',
				'shortcode' => '',
				'till'      => '',
				'key'       => '',
				'secret'    => '',
				'passkey'   => '',
				'token'     => wp_generate_password( 24, false ),
				'stk'       => 1,
			),
			'smtp'            => array(
				'host'   => '',
				'port'   => 587,
				'secure' => 'tls',
				'user'   => '',
				'pass'   => '',
			),
			'etims'           => array(
				'mode'   => 'off',
				'env'    => 'sandbox',
				'pin'    => '',
				'branch' => '00',
				'serial' => '',
				'cmc'    => '',
			),
			'reports'         => array(
				'daily'    => array( 'on' => 1, 'to' => '' ),
				'weekly'   => array( 'on' => 1, 'to' => '' ),
				'monthly'  => array( 'on' => 1, 'to' => '' ),
				'lowstock' => array( 'on' => 1, 'to' => '' ),
				'overdue'  => array( 'on' => 0, 'to' => '' ),
				'licences' => array( 'on' => 1, 'to' => '' ),
				'schedule' => array( 'on' => 1, 'to' => '' ),
			),
		);
	}

	public static function all() {
		$s = get_option( 'soko_settings', array() );
		$d = self::defaults();
		$s = is_array( $s ) ? $s : array();
		foreach ( $d as $k => $v ) {
			if ( ! isset( $s[ $k ] ) ) {
				$s[ $k ] = $v;
			} elseif ( is_array( $v ) && is_array( $s[ $k ] ) && ! isset( $v[0] ) ) {
				$s[ $k ] = array_merge( $v, $s[ $k ] );
			}
		}
		return $s;
	}

	public static function ensure_defaults() {
		if ( ! get_option( 'soko_settings' ) ) {
			update_option( 'soko_settings', self::defaults(), false );
		}
	}

	public static function save( $data ) {
		$s = self::all();
		foreach ( (array) $data as $k => $v ) {
			if ( array_key_exists( $k, $s ) ) {
				$s[ $k ] = self::clean( $v );
			}
		}
		update_option( 'soko_settings', $s, false );
		return $s;
	}

	private static function clean( $v ) {
		if ( is_array( $v ) ) {
			return array_map( array( __CLASS__, 'clean' ), $v );
		}
		if ( is_numeric( $v ) ) {
			return $v + 0;
		}
		return sanitize_textarea_field( (string) $v );
	}

	/** Settings safe to send to staff without secrets. */
	public static function public_view( $full = false ) {
		$s = self::all();
		unset( $s['mpesa']['token'] ); // the callback secret never leaves the server
		if ( isset( $s['etims'] ) && is_array( $s['etims'] ) ) {
			unset( $s['etims']['cmc'] );
		}
		if ( ! $full ) {
			unset( $s['mpesa']['secret'], $s['mpesa']['passkey'], $s['mpesa']['key'], $s['smtp'] );
		} else {
			foreach ( array( 'secret', 'passkey', 'key' ) as $k ) {
				$s['mpesa'][ $k ] = $s['mpesa'][ $k ] ? '••••••' . substr( $s['mpesa'][ $k ], -4 ) : '';
			}
			$s['smtp']['pass'] = $s['smtp']['pass'] ? '••••••••' : '';
		}
		return $s;
	}

	/** Next document number, incremented atomically enough for a small team. */
	public static function next_number( $type ) {
		$map = array( 'quote' => 'quote', 'invoice' => 'invoice', 'receipt' => 'receipt', 'po' => 'po', 'credit' => 'credit' );
		$k   = $map[ $type ];
		$s   = self::all();
		$n   = (int) $s[ 'next_' . $k ];
		if ( 'po' !== $k ) {
			// Never reissue a number that already exists.
			global $wpdb;
			while ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . soko_t( 'docs' ) . ' WHERE number = %s', $s[ 'prefix_' . $k ] . $n ) ) ) { // phpcs:ignore
				++$n;
			}
		}
		$s[ 'next_' . $k ] = $n + 1;
		update_option( 'soko_settings', $s, false );
		return $s[ 'prefix_' . $k ] . $n;
	}
}

function soko_setting( $key, $default = null ) {
	$all = Soko_Settings::all();
	return isset( $all[ $key ] ) ? $all[ $key ] : $default;
}

function soko_categories() {
	$c = get_option( 'soko_categories' );
	return soko_abs( is_array( $c ) && $c ? $c : Soko_Install::default_categories() );
}

function soko_category( $id ) {
	foreach ( soko_categories() as $c ) {
		if ( $c['id'] === $id ) {
			return $c;
		}
	}
	return array( 'id' => $id, 'name' => ucfirst( $id ), 'icon' => 'panel', 'group' => 'More' );
}
