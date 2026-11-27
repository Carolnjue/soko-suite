<?php
/**
 * Double-entry accounting for Dynamic Desk.
 *
 * Every invoice, payment, expense, purchase and stock adjustment is posted to the
 * ledger automatically when the books are read, so the accounts always agree with
 * the source documents. Manual journals, opening balances and supplier payments are
 * stored in their own table.
 *
 * @package Soko
 */

defined( 'ABSPATH' ) || exit;

class Soko_Accounts {

	/** Fixed accounts. Expense accounts are added from the expense categories. */
	public static function base() {
		return array(
			array( '1000', 'Cash on hand', 'asset', 'cash' ),
			array( '1010', 'M-Pesa', 'asset', 'mpesa' ),
			array( '1020', 'Bank', 'asset', 'bank' ),
			array( '1100', 'Accounts receivable (customers)', 'asset', 'ar' ),
			array( '1200', 'Stock (inventory)', 'asset', 'stock' ),
			array( '1300', 'VAT on purchases (input VAT)', 'asset', 'vat_in' ),
			array( '1500', 'Equipment and vehicles', 'asset', 'fixed' ),
			array( '2000', 'Accounts payable (suppliers)', 'liability', 'ap' ),
			array( '2100', 'VAT on sales (output VAT)', 'liability', 'vat_out' ),
			array( '2200', 'Customer payments not yet matched', 'liability', 'unalloc' ),
			array( '2300', 'Loans', 'liability', 'loan' ),
			array( '3000', "Owner's capital", 'equity', 'capital' ),
			array( '3050', 'Opening balances', 'equity', 'opening' ),
			array( '3100', "Owner's drawings", 'equity', 'drawings' ),
			array( '4000', 'Sales', 'income', 'sales' ),
			array( '4900', 'Sales returns and credit notes', 'income', 'returns' ),
			array( '4950', 'Other income', 'income', 'other_income' ),
			array( '5000', 'Cost of goods sold', 'expense', 'cogs' ),
			array( '5100', 'Stock adjustments and losses', 'expense', 'stock_adj' ),
		);
	}

	/** All accounts, keyed by code: [code, name, type, key]. */
	public static function chart() {
		static $chart = null;
		if ( null !== $chart ) {
			return $chart;
		}
		$chart = array();
		foreach ( self::base() as $a ) {
			$chart[ $a[0] ] = array( 'code' => $a[0], 'name' => $a[1], 'type' => $a[2], 'key' => $a[3] );
		}
		foreach ( self::expense_accounts() as $cat => $code ) {
			$chart[ $code ] = array( 'code' => $code, 'name' => $cat, 'type' => 'expense', 'key' => 'exp' );
		}
		ksort( $chart, SORT_STRING );
		return $chart;
	}

	/** Expense category => account code (6000, 6010, …), stable by first use. */
	public static function expense_accounts() {
		global $wpdb;
		$map  = get_option( 'soko_expense_accounts', array() );
		$map  = is_array( $map ) ? $map : array();
		$cats = array_merge( (array) soko_setting( 'expense_cats', array() ), (array) $wpdb->get_col( 'SELECT DISTINCT category FROM ' . soko_t( 'expenses' ) ) ); // phpcs:ignore
		$next = $map ? max( array_map( 'intval', $map ) ) + 10 : 6000;
		$new  = false;
		foreach ( $cats as $c ) {
			$c = trim( (string) $c );
			if ( '' === $c || isset( $map[ $c ] ) ) {
				continue;
			}
			$map[ $c ] = (string) $next;
			$next     += 10;
			$new       = true;
		}
		if ( $new ) {
			update_option( 'soko_expense_accounts', $map, false );
		}
		return $map;
	}

	public static function code( $key ) {
		foreach ( self::base() as $a ) {
			if ( $a[3] === $key ) {
				return $a[0];
			}
		}
		return '';
	}

	/** Where money moved for a payment method. */
	public static function money_account( $method ) {
		$m = strtolower( str_replace( array( '-', ' ' ), '', (string) $method ) );
		if ( 'mpesa' === $m ) {
			return '1010';
		}
		if ( 'cash' === $m ) {
			return '1000';
		}
		return '1020';
	}

	/* ------------------------------------------------------------ postings */

	private static $cache = null;

	/**
	 * Every posting in the books up to $to (inclusive). Each posting is
	 * [ date, code, debit, credit, memo, ref, source, sid ].
	 */
	public static function postings( $to = null ) {
		if ( null !== self::$cache ) {
			$all = self::$cache;
		} else {
			$all         = self::build();
			self::$cache = $all;
		}
		if ( ! $to ) {
			return $all;
		}
		return array_values( array_filter( $all, function ( $p ) use ( $to ) { return $p[0] <= $to; } ) );
	}

	public static function flush() {
		self::$cache = null;
	}

	private static function add( &$out, $date, $memo, $ref, $source, $sid, $lines ) {
		$date = substr( (string) $date, 0, 10 );
		if ( ! $date ) {
			return;
		}
		foreach ( $lines as $l ) {
			$amt = round( (float) $l[1], 2 );
			if ( abs( $amt ) < 0.005 ) {
				continue;
			}
			// Positive = debit, negative = credit.
			$out[] = array( $date, $l[0], $amt > 0 ? $amt : 0, $amt < 0 ? -$amt : 0, $memo, $ref, $source, $sid );
		}
	}

	private static function build() {
		global $wpdb;
		$out  = array();
		$rate = soko_setting( 'vat_registered' ) ? (float) soko_setting( 'vat_rate', 16 ) : 0;
		$incl = (int) soko_setting( 'prices_incl_vat', 1 );
		$D    = soko_t( 'docs' );
		$L    = soko_t( 'lines' );

		// 1. Invoices: customer owes us; sales and VAT on sales; cost of goods sold.
		$lines = $wpdb->get_results( "SELECT l.doc_id, l.qty, l.price, l.cost, l.vatable, l.product_id, d.number, d.date FROM $L l JOIN $D d ON d.id = l.doc_id WHERE d.type = 'invoice' AND d.status <> 'void'", ARRAY_A ); // phpcs:ignore
		$inv   = array();
		foreach ( $lines as $l ) {
			$k = $l['doc_id'];
			if ( ! isset( $inv[ $k ] ) ) {
				$inv[ $k ] = array( 'n' => $l['number'], 'd' => $l['date'], 'net' => 0, 'vat' => 0, 'cogs' => 0 );
			}
			$amt = $l['qty'] * $l['price'];
			$v   = $l['vatable'] ? ( $incl ? $amt * $rate / ( 100 + $rate ) : $amt * $rate / 100 ) : 0;
			$inv[ $k ]['net']  += $incl ? $amt - $v : $amt;
			$inv[ $k ]['vat']  += $v;
			$inv[ $k ]['cogs'] += $l['qty'] * (float) $l['cost'];
		}
		foreach ( $inv as $id => $x ) {
			$tot = $x['net'] + $x['vat'];
			self::add( $out, $x['d'], 'Invoice ' . $x['n'], $x['n'], 'invoice', $id, array( array( '1100', $tot ), array( '4000', -$x['net'] ), array( '2100', -$x['vat'] ) ) );
			if ( $x['cogs'] > 0 ) {
				self::add( $out, $x['d'], 'Cost of goods sold, ' . $x['n'], $x['n'], 'cogs', $id, array( array( '5000', $x['cogs'] ), array( '1200', -$x['cogs'] ) ) );
			}
		}

		// 2. Credit notes: reduce sales and VAT, and what the customer owes.
		foreach ( $wpdb->get_results( "SELECT id, number, date, subtotal, vat, total FROM $D WHERE type = 'credit' AND status <> 'void'", ARRAY_A ) as $c ) { // phpcs:ignore
			self::add( $out, $c['date'], 'Credit note ' . $c['number'], $c['number'], 'credit', $c['id'], array( array( '4900', $c['subtotal'] ), array( '2100', $c['vat'] ), array( '1100', -$c['total'] ) ) );
		}

		// 3. Customer payments.
		foreach ( $wpdb->get_results( 'SELECT id, method, code, amount, status, received, doc_id FROM ' . soko_t( 'payments' ) . " WHERE method <> 'credit'", ARRAY_A ) as $p ) { // phpcs:ignore
			$against = 'matched' === $p['status'] ? '1100' : '2200';
			self::add( $out, $p['received'], ( 'matched' === $p['status'] ? 'Payment received ' : 'Payment not yet matched ' ) . $p['code'], $p['code'], 'payment', $p['id'], array( array( self::money_account( $p['method'] ), $p['amount'] ), array( $against, -$p['amount'] ) ) );
		}

		// 4. Expenses.
		$ex = self::expense_accounts();
		foreach ( $wpdb->get_results( 'SELECT id, date, category, description, amount, vat, method, reference FROM ' . soko_t( 'expenses' ), ARRAY_A ) as $e ) { // phpcs:ignore
			$acc = isset( $ex[ trim( $e['category'] ) ] ) ? $ex[ trim( $e['category'] ) ] : '6000';
			self::add( $out, $e['date'], trim( $e['category'] . ': ' . $e['description'], ': ' ), $e['reference'], 'expense', $e['id'], array( array( $acc, $e['amount'] - $e['vat'] ), array( '1300', $e['vat'] ), array( self::money_account( $e['method'] ), -$e['amount'] ) ) );
		}

		// 5. Supplier purchases: a received order is a bill we owe.
		$J      = soko_t( 'journal' );
		$paidby = array();
		foreach ( $wpdb->get_results( "SELECT ref, entries FROM $J WHERE source = 'supplier_pay'", ARRAY_A ) as $j ) { // phpcs:ignore
			foreach ( (array) json_decode( $j['entries'], true ) as $jl ) {
				if ( '2000' === (string) $jl['code'] ) {
					$paidby[ $j['ref'] ] = ( isset( $paidby[ $j['ref'] ] ) ? $paidby[ $j['ref'] ] : 0 ) + (float) $jl['debit'];
				}
			}
		}
		foreach ( $wpdb->get_results( 'SELECT p.*, s.name AS supplier FROM ' . soko_t( 'purchases' ) . ' p LEFT JOIN ' . soko_t( 'suppliers' ) . ' s ON s.id = p.supplier_id', ARRAY_A ) as $po ) { // phpcs:ignore
			$when = $po['received'] ? $po['received'] : $po['date'];
			if ( 'received' === $po['status'] ) {
				self::add( $out, $when, 'Bill ' . $po['number'] . ( $po['supplier'] ? ', ' . $po['supplier'] : '' ), $po['number'], 'bill', $po['id'], array( array( '1200', $po['total'] - $po['vat'] ), array( '1300', $po['vat'] ), array( '2000', -$po['total'] ) ) );
			}
			// Payments made before the supplier-payment record existed.
			$rest = (float) $po['paid'] - ( isset( $paidby[ $po['number'] ] ) ? $paidby[ $po['number'] ] : 0 );
			if ( $rest > 0.005 ) {
				self::add( $out, $when, 'Paid supplier, ' . $po['number'], $po['number'], 'supplier_paid', $po['id'], array( array( '2000', $rest ), array( '1020', -$rest ) ) );
			}
		}

		// 6. Stock: opening stock and adjustments, valued at cost.
		$P = soko_t( 'products' );
		foreach ( $wpdb->get_results( 'SELECT m.id, m.qty, m.reason, m.ref, m.created, p.cost, p.name FROM ' . soko_t( 'moves' ) . " m JOIN $P p ON p.id = m.product_id WHERE m.reason NOT IN ('Sold', 'Received')", ARRAY_A ) as $m ) { // phpcs:ignore
			$val = round( $m['qty'] * (float) $m['cost'], 2 );
			if ( 'Opening stock' === $m['reason'] ) {
				self::add( $out, $m['created'], 'Opening stock, ' . $m['name'], '', 'stock', $m['id'], array( array( '1200', $val ), array( '3050', -$val ) ) );
			} else {
				self::add( $out, $m['created'], $m['reason'] . ', ' . $m['name'], $m['ref'], 'stock', $m['id'], array( array( '1200', $val ), array( '5100', -$val ) ) );
			}
		}

		// 7. Journals: opening balances, manual entries, supplier payments.
		foreach ( $wpdb->get_results( "SELECT * FROM $J ORDER BY date, id", ARRAY_A ) as $j ) { // phpcs:ignore
			$ls = array();
			foreach ( (array) json_decode( $j['entries'], true ) as $jl ) {
				$ls[] = array( (string) $jl['code'], (float) $jl['debit'] - (float) $jl['credit'] );
			}
			self::add( $out, $j['date'], $j['memo'], $j['ref'], $j['source'], 'j' . $j['id'], $ls );
		}

		usort( $out, function ( $a, $b ) { return strcmp( $a[0], $b[0] ); } );
		return $out;
	}

	/* ------------------------------------------------------------ balances */

	/** Debit-minus-credit by account for postings between $from and $to (inclusive). */
	public static function movements( $from, $to ) {
		$b = array();
		foreach ( self::postings( $to ) as $p ) {
			if ( $from && $p[0] < $from ) {
				continue;
			}
			$b[ $p[1] ] = ( isset( $b[ $p[1] ] ) ? $b[ $p[1] ] : 0 ) + $p[2] - $p[3];
		}
		return $b;
	}

	/** Debit-minus-credit on one account in a period, optionally only from some sources. */
	public static function sum( $code, $from, $to, $sources = null ) {
		$t = 0;
		foreach ( self::postings( $to ) as $p ) {
			if ( (string) $p[1] !== (string) $code || ( $from && $p[0] < $from ) || ( $sources && ! in_array( $p[6], (array) $sources, true ) ) ) {
				continue;
			}
			$t += $p[2] - $p[3];
		}
		return round( $t, 2 );
	}

	public static function balances( $to ) {
		return self::movements( null, $to );
	}

	/** Profit (income minus expenses) between two dates, from the ledger. */
	public static function profit( $from, $to ) {
		$chart = self::chart();
		$p     = 0;
		foreach ( self::movements( $from, $to ) as $code => $v ) {
			$t = isset( $chart[ $code ] ) ? $chart[ $code ]['type'] : 'expense';
			if ( 'income' === $t || 'expense' === $t ) {
				$p -= $v;
			}
		}
		return round( $p, 2 );
	}

	public static function name( $code ) {
		$c = self::chart();
		return isset( $c[ $code ] ) ? $c[ $code ]['name'] : 'Account ' . $code;
	}

	/* ------------------------------------------------------------ journals */

	/** Save a journal. $lines: [ [code, debit, credit], … ]. */
	public static function journal( $date, $memo, $lines, $source = 'manual', $ref = '' ) {
		global $wpdb;
		$clean = array();
		$dr    = 0;
		$cr    = 0;
		$chart = self::chart();
		foreach ( (array) $lines as $l ) {
			$code = (string) ( isset( $l['code'] ) ? $l['code'] : $l[0] );
			$d    = round( (float) ( isset( $l['debit'] ) ? $l['debit'] : $l[1] ), 2 );
			$c    = round( (float) ( isset( $l['credit'] ) ? $l['credit'] : $l[2] ), 2 );
			if ( ( $d <= 0 && $c <= 0 ) || ! isset( $chart[ $code ] ) ) {
				continue;
			}
			if ( $d < 0 || $c < 0 ) {
				return new WP_Error( 'soko', 'Amounts cannot be negative.', array( 'status' => 400 ) );
			}
			$clean[] = array( 'code' => $code, 'debit' => $d, 'credit' => $c );
			$dr     += $d;
			$cr     += $c;
		}
		if ( count( $clean ) < 2 ) {
			return new WP_Error( 'soko', 'A journal needs at least two lines.', array( 'status' => 400 ) );
		}
		if ( abs( $dr - $cr ) > 0.01 ) {
			return new WP_Error( 'soko', sprintf( 'Debits (%s) and credits (%s) must be equal.', soko_money_exact( $dr ), soko_money_exact( $cr ) ), array( 'status' => 400 ) );
		}
		$wpdb->insert(
			soko_t( 'journal' ),
			array(
				'date'    => sanitize_text_field( $date ),
				'memo'    => sanitize_text_field( $memo ),
				'ref'     => sanitize_text_field( $ref ),
				'source'  => sanitize_key( $source ),
				'entries' => wp_json_encode( $clean ),
				'user_id' => get_current_user_id(),
				'created' => soko_now(),
			)
		);
		self::flush();
		return (int) $wpdb->insert_id;
	}

	/** Opening balances: one journal, replaced each time it is saved; the difference goes to Opening balances equity. */
	public static function set_opening( $date, $amounts ) {
		global $wpdb;
		$J     = soko_t( 'journal' );
		$chart = self::chart();
		$lines = array();
		$net   = 0;
		foreach ( (array) $amounts as $code => $amt ) {
			$amt = round( (float) $amt, 2 );
			if ( ! $amt || ! isset( $chart[ $code ] ) || '3050' === (string) $code ) {
				continue;
			}
			// Assets carry debit balances; liabilities and equity carry credit balances.
			$debit_side = 'asset' === $chart[ $code ]['type'] || 'expense' === $chart[ $code ]['type'];
			$dr         = $debit_side ? $amt : -$amt;
			$lines[]    = array( 'code' => (string) $code, 'debit' => max( 0, $dr ), 'credit' => max( 0, -$dr ) );
			$net       += $dr;
		}
		if ( abs( $net ) > 0.005 ) {
			$lines[] = array( 'code' => '3050', 'debit' => max( 0, -$net ), 'credit' => max( 0, $net ) );
		}
		$wpdb->query( "DELETE FROM $J WHERE source = 'opening'" ); // phpcs:ignore
		self::flush();
		if ( count( $lines ) < 2 ) {
			return 0;
		}
		return self::journal( $date, 'Opening balances', $lines, 'opening' );
	}

	public static function opening() {
		global $wpdb;
		$j = $wpdb->get_row( 'SELECT * FROM ' . soko_t( 'journal' ) . " WHERE source = 'opening' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore
		if ( ! $j ) {
			return array( 'date' => '', 'amounts' => array() );
		}
		$chart = self::chart();
		$a     = array();
		foreach ( (array) json_decode( $j['entries'], true ) as $l ) {
			if ( '3050' === (string) $l['code'] ) {
				continue;
			}
			$debit_side       = isset( $chart[ $l['code'] ] ) && in_array( $chart[ $l['code'] ]['type'], array( 'asset', 'expense' ), true );
			$a[ $l['code'] ] = $debit_side ? $l['debit'] - $l['credit'] : $l['credit'] - $l['debit'];
		}
		return array( 'date' => $j['date'], 'amounts' => $a );
	}

	/* ------------------------------------------------------------ reconciliation */

	public static function recon_key( $p ) {
		return $p[6] . ':' . $p[7] . ':' . $p[1];
	}

	public static function reconciled( $code ) {
		$r = get_option( 'soko_recon', array() );
		return isset( $r[ $code ] ) && is_array( $r[ $code ] ) ? $r[ $code ] : array();
	}

	public static function set_reconciled( $code, $keys, $on ) {
		$r = get_option( 'soko_recon', array() );
		$r = is_array( $r ) ? $r : array();
		$cur = isset( $r[ $code ] ) ? $r[ $code ] : array();
		foreach ( (array) $keys as $k ) {
			if ( $on ) {
				$cur[ $k ] = soko_today();
			} else {
				unset( $cur[ $k ] );
			}
		}
		$r[ $code ] = $cur;
		update_option( 'soko_recon', $r, false );
		return $cur;
	}
}
