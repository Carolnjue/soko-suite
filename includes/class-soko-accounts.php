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
		if ( null !== $chart && ! self::$chart_reset ) {
			return $chart;
		}
		self::$chart_reset = false;
		$chart = array();
		foreach ( self::base() as $a ) {
			$chart[ $a[0] ] = array( 'code' => $a[0], 'name' => $a[1], 'type' => $a[2], 'key' => $a[3] );
		}
		foreach ( self::expense_accounts() as $cat => $code ) {
			$chart[ $code ] = array( 'code' => $code, 'name' => $cat, 'type' => 'expense', 'key' => 'exp' );
		}
		foreach ( (array) get_option( 'soko_custom_accounts', array() ) as $code => $a ) {
			if ( ! isset( $chart[ $code ] ) ) {
				$chart[ (string) $code ] = array( 'code' => (string) $code, 'name' => $a['name'], 'type' => $a['type'], 'key' => 'custom' );
			}
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
			if ( '' === $c || isset( $map[ $c ] ) || preg_match( '/\(kept as an asset\)/i', $c ) ) {
				continue;
			}
			$map[ $c ] = (string) $next;
			$next     += 10;
			$new       = true;
		}
		foreach ( array_keys( $map ) as $k ) {
			if ( preg_match( '/\(kept as an asset\)/i', $k ) ) {
				unset( $map[ $k ] ); // asset purchases go to 1500, never an expense account
				$new = true;
			}
		}
		if ( $new ) {
			update_option( 'soko_expense_accounts', $map, false );
		}
		return $map;
	}

	public static function add_account( $code, $name, $type ) {
		$code = preg_replace( '/\D/', '', (string) $code );
		$name = sanitize_text_field( $name );
		$ranges = array( 'asset' => '1', 'liability' => '2', 'equity' => '3', 'income' => '4', 'expense' => '5' );
		if ( ! isset( $ranges[ $type ] ) ) {
			return new WP_Error( 'soko', 'Choose the account type.', array( 'status' => 400 ) );
		}
		if ( 4 !== strlen( $code ) ) {
			return new WP_Error( 'soko', 'Account codes have four digits.', array( 'status' => 400 ) );
		}
		$first = $code[0];
		if ( 'expense' === $type ? ! in_array( $first, array( '5', '6', '7' ), true ) : $first !== $ranges[ $type ] ) {
			return new WP_Error( 'soko', 'expense' === $type ? 'Expense codes start with 5, 6 or 7.' : sprintf( '%s codes start with %s.', ucfirst( $type ), $ranges[ $type ] ), array( 'status' => 400 ) );
		}
		if ( ! $name ) {
			return new WP_Error( 'soko', 'Give the account a name.', array( 'status' => 400 ) );
		}
		self::$chart_reset = true;
		if ( isset( self::chart()[ $code ] ) ) {
			return new WP_Error( 'soko', 'Code ' . $code . ' is already used by ' . self::chart()[ $code ]['name'] . '.', array( 'status' => 400 ) );
		}
		$c = (array) get_option( 'soko_custom_accounts', array() );
		$c[ $code ] = array( 'name' => $name, 'type' => $type );
		update_option( 'soko_custom_accounts', $c, false );
		self::$chart_reset = true;
		return $code;
	}

	public static function remove_account( $code ) {
		$c = (array) get_option( 'soko_custom_accounts', array() );
		if ( ! isset( $c[ $code ] ) ) {
			return new WP_Error( 'soko', 'Only accounts you added can be removed.', array( 'status' => 400 ) );
		}
		foreach ( self::postings() as $p ) {
			if ( (string) $p[1] === (string) $code ) {
				return new WP_Error( 'soko', 'This account has entries, so it cannot be removed.', array( 'status' => 400 ) );
			}
		}
		unset( $c[ $code ] );
		update_option( 'soko_custom_accounts', $c, false );
		self::$chart_reset = true;
		return true;
	}

	public static $chart_reset = false;

	public static function code( $key ) {
		foreach ( self::base() as $a ) {
			if ( $a[3] === $key ) {
				return $a[0];
			}
		}
		return '';
	}

	/** Where money moved for a payment method. */
	/** Books are closed up to and including the lock date (Settings, Documents). Returns an error, or null. */
	public static function locked( $date ) {
		$lock = (string) soko_setting( 'lock_date', '' );
		if ( $lock && $date && substr( (string) $date, 0, 10 ) <= $lock ) {
			return new WP_Error( 'soko_locked', 'The books are closed up to ' . date_i18n( 'j M Y', strtotime( $lock ) ) . '. Use a later date, or ask the owner to change the closing date in Settings.', array( 'status' => 400 ) );
		}
		return null;
	}

	/** An expense recorded before it is paid is a bill owed to the supplier. */
	public static function is_unpaid_method( $method ) {
		return (bool) preg_match( '/^(not paid|unpaid|pay later)/i', trim( (string) $method ) );
	}

	/** Cash, M-Pesa, the main bank, and any extra bank or till accounts added with codes 1021 to 1099. */
	public static function money_codes() {
		$out = array( '1000', '1010', '1020' );
		foreach ( self::chart() as $c => $a ) {
			if ( 'asset' === $a['type'] && (int) $c > 1020 && (int) $c < 1100 ) {
				$out[] = (string) $c;
			}
		}
		return $out;
	}

	public static function money_account( $method ) {
		if ( preg_match( '/^\d{4}$/', trim( (string) $method ) ) && in_array( trim( (string) $method ), self::money_codes(), true ) ) {
			return trim( (string) $method );
		}
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

	/**
	 * Website orders are booked in the accounts once confirmed: any payment received,
	 * or staff have started processing them. Unconfirmed orders are not yet sales.
	 */
	public static function confirmed_sql( $a = 'd' ) {
		return "( $a.source <> 'web' OR $a.paid > 0 OR $a.fulfil IN ('to_pack','packed','dispatched','delivered') )";
	}

	private static function build() {
		global $wpdb;
		$out  = array();
		$rate = soko_setting( 'vat_registered' ) ? (float) soko_setting( 'vat_rate', 16 ) : 0;
		$incl = (int) soko_setting( 'prices_incl_vat', 1 );
		$D    = soko_t( 'docs' );
		$L    = soko_t( 'lines' );

		// 1. Invoices: customer owes us; sales and VAT on sales; cost of goods sold.
		$C     = soko_t( 'customers' );
		$lines = $wpdb->get_results( "SELECT l.doc_id, l.qty, l.price, l.vatable, d.number, d.date, c.name AS cname FROM $L l JOIN $D d ON d.id = l.doc_id LEFT JOIN $C c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.status <> 'void' AND " . self::confirmed_sql( 'd' ), ARRAY_A ); // phpcs:ignore
		$inv   = array();
		foreach ( $lines as $l ) {
			$k = $l['doc_id'];
			if ( ! isset( $inv[ $k ] ) ) {
				$inv[ $k ] = array( 'n' => $l['number'], 'd' => $l['date'], 'c' => $l['cname'], 'net' => 0, 'vat' => 0 );
			}
			$amt = $l['qty'] * $l['price'];
			$v   = $l['vatable'] ? ( $incl ? $amt * $rate / ( 100 + $rate ) : $amt * $rate / 100 ) : 0;
			$inv[ $k ]['net']  += $incl ? $amt - $v : $amt;
			$inv[ $k ]['vat']  += $v;
		}
		// Cost of goods sold is matched to the sale: posted on the invoice date, at the cost on the invoice line.
		$P      = soko_t( 'products' );
		$costed = array();
		foreach ( $wpdb->get_results( "SELECT d.id AS did, d.number, d.date, l.qty, l.cost AS lcost, p.cost AS pcost, p.name, (SELECT MAX(m.cost) FROM " . soko_t( 'moves' ) . " m WHERE m.ref = d.number AND m.product_id = l.product_id AND m.reason = 'Sold') AS mcost FROM $L l JOIN $D d ON d.id = l.doc_id JOIN $P p ON p.id = l.product_id WHERE d.type = 'invoice' AND d.status <> 'void' AND p.kind = 'product' AND " . self::confirmed_sql( 'd' ), ARRAY_A ) as $l ) { // phpcs:ignore
			$unit = (float) $l['lcost'] > 0 ? (float) $l['lcost'] : ( (float) $l['mcost'] > 0 ? (float) $l['mcost'] : (float) $l['pcost'] );
			$val  = round( $l['qty'] * $unit, 2 );
			$costed[ $l['number'] ] = true;
			if ( $val > 0 ) {
				self::add( $out, $l['date'], 'Cost of goods sold, ' . $l['name'] . ' (' . $l['number'] . ')', $l['number'], 'cogs', $l['did'], array( array( '5000', $val ), array( '1200', -$val ) ) );
			}
		}
		foreach ( $inv as $id => $x ) {
			$tot = $x['net'] + $x['vat'];
			self::add( $out, $x['d'], 'Invoice ' . $x['n'] . ( $x['c'] ? ', ' . $x['c'] : '' ), $x['n'], 'invoice', $id, array( array( '1100', $tot ), array( '4000', -$x['net'] ), array( '2100', -$x['vat'] ) ) );
		}

		// 2. Credit notes: reduce sales and VAT, and what the customer owes.
		foreach ( $wpdb->get_results( "SELECT id, number, date, subtotal, vat, total FROM $D WHERE type = 'credit' AND status <> 'void'", ARRAY_A ) as $c ) { // phpcs:ignore
			self::add( $out, $c['date'], 'Credit note ' . $c['number'], $c['number'], 'credit', $c['id'], array( array( '4900', $c['subtotal'] ), array( '2100', $c['vat'] ), array( '1100', -$c['total'] ) ) );
		}

		// 3. Customer payments.
		foreach ( $wpdb->get_results( 'SELECT p.id, p.method, p.code, p.amount, p.status, p.received, p.payer, d.number FROM ' . soko_t( 'payments' ) . " p LEFT JOIN $D d ON d.id = p.doc_id WHERE p.method <> 'credit' AND p.status <> 'void'", ARRAY_A ) as $p ) { // phpcs:ignore
			$against = 'matched' === $p['status'] ? '1100' : '2200';
			$memo    = ( 'matched' === $p['status'] ? 'Payment from ' : 'Payment not yet matched, from ' ) . ( $p['payer'] ? $p['payer'] : 'customer' ) . ( 'matched' === $p['status'] && $p['number'] ? ' for ' . $p['number'] : '' );
			self::add( $out, $p['received'], $memo, $p['code'], 'payment', $p['id'], array( array( self::money_account( $p['method'] ), $p['amount'] ), array( $against, -$p['amount'] ) ) );
		}

		// 4. Expenses.
		$ex = self::expense_accounts();
		foreach ( $wpdb->get_results( 'SELECT id, date, category, description, amount, vat, method, reference FROM ' . soko_t( 'expenses' ), ARRAY_A ) as $e ) { // phpcs:ignore
			$acc = preg_match( '/\(kept as an asset\)/i', $e['category'] ) ? '1500' : ( isset( $ex[ trim( $e['category'] ) ] ) ? $ex[ trim( $e['category'] ) ] : '6000' );
			self::add( $out, $e['date'], trim( $e['category'] . ': ' . $e['description'], ': ' ), $e['reference'], 'expense', $e['id'], array( array( $acc, $e['amount'] - $e['vat'] ), array( '1300', $e['vat'] ), array( self::is_unpaid_method( $e['method'] ) ? '2000' : self::money_account( $e['method'] ), -$e['amount'] ) ) );
		}

		// 5. Supplier purchases: a received order is a bill we owe.
		$J      = soko_t( 'journal' );
		$paidby = array();
		foreach ( $wpdb->get_results( "SELECT ref, entries FROM $J WHERE source IN ('supplier_pay','supplier_return')", ARRAY_A ) as $j ) { // phpcs:ignore
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

		// 6. Stock, valued at the cost recorded when each movement happened.
		//    Goods received on supplier bills are posted with the bill (section 5).
		//    Stock sold on an invoice is costed with the invoice (section 1).
		foreach ( $wpdb->get_results( 'SELECT m.id, m.qty, m.reason, m.ref, m.created, m.cost AS mcost, p.cost, p.name FROM ' . soko_t( 'moves' ) . " m JOIN $P p ON p.id = m.product_id WHERE m.reason <> 'Received'", ARRAY_A ) as $m ) { // phpcs:ignore
			$unit = (float) $m['mcost'] > 0 ? (float) $m['mcost'] : (float) $m['cost'];
			$val  = round( $m['qty'] * $unit, 2 );
			if ( 'Returned to supplier' === $m['reason'] ) {
				continue; // posted with its supplier credit journal
			}
			if ( 'Opening stock' === $m['reason'] ) {
				self::add( $out, $m['created'], 'Opening stock, ' . $m['name'], '', 'stock', $m['id'], array( array( '1200', $val ), array( '3050', -$val ) ) );
			} elseif ( 'Sold' === $m['reason'] ) {
				if ( isset( $costed[ $m['ref'] ] ) ) {
					continue; // already costed with its invoice
				}
				self::add( $out, $m['created'], 'Cost of goods sold, ' . $m['name'] . ( $m['ref'] ? ' (' . $m['ref'] . ')' : '' ), $m['ref'], 'cogs', $m['id'], array( array( '5000', -$val ), array( '1200', $val ) ) );
			} elseif ( 'Returned' === $m['reason'] ) {
				self::add( $out, $m['created'], 'Returned to stock, ' . $m['name'] . ( $m['ref'] ? ' (' . $m['ref'] . ')' : '' ), $m['ref'], 'cogs', $m['id'], array( array( '1200', $val ), array( '5000', -$val ) ) );
			} else {
				self::add( $out, $m['created'], $m['reason'] . ', ' . $m['name'], $m['ref'], 'stock', $m['id'], array( array( '1200', $val ), array( '5100', -$val ) ) );
			}
		}

		// Stock on hand that has no movement history (loaded before movements were recorded) is opening stock.
		$MV = soko_t( 'moves' );
		foreach ( $wpdb->get_results( "SELECT p.id, p.name, p.stock, p.cost, p.created, (SELECT COALESCE(SUM(m.qty), 0) FROM $MV m WHERE m.product_id = p.id) AS moved, (SELECT MIN(m2.created) FROM $MV m2 WHERE m2.product_id = p.id) AS first FROM $P p WHERE p.kind = 'product'", ARRAY_A ) as $u ) { // phpcs:ignore
			$gap = round( (float) $u['stock'] - (float) $u['moved'], 2 );
			if ( abs( $gap ) < 0.005 || (float) $u['cost'] <= 0 ) {
				continue;
			}
			$when = $u['first'] ? $u['first'] : ( $u['created'] ? $u['created'] : soko_now() );
			$val  = round( $gap * (float) $u['cost'], 2 );
			self::add( $out, substr( $when, 0, 10 ) < '2000-01-01' ? soko_today() : $when, 'Opening stock (no earlier record), ' . $u['name'], '', 'stock_open', $u['id'], array( array( '1200', $val ), array( '3050', -$val ) ) );
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

	/**
	 * What had been paid or credited on each invoice by the end of $to:
	 * matched payments + credit notes + credit moved in − credit moved out − refunds.
	 */
	public static function paid_asat( $to ) {
		global $wpdb;
		$D   = soko_t( 'docs' );
		$out = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT doc_id, SUM(amount) a FROM ' . soko_t( 'payments' ) . " WHERE status = 'matched' AND doc_id > 0 AND received <= %s GROUP BY doc_id", $to . ' 23:59:59' ), ARRAY_A ) as $r ) { // phpcs:ignore
			$out[ $r['doc_id'] ] = (float) $r['a'];
		}
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT parent_id, SUM(total) a FROM $D WHERE type = 'credit' AND status <> 'void' AND date <= %s GROUP BY parent_id", $to ), ARRAY_A ) as $r ) { // phpcs:ignore
			$out[ $r['parent_id'] ] = ( isset( $out[ $r['parent_id'] ] ) ? $out[ $r['parent_id'] ] : 0 ) + (float) $r['a'];
		}
		foreach ( $wpdb->get_results( "SELECT id, meta FROM $D WHERE type = 'invoice' AND ( meta LIKE '%credit%' OR meta LIKE '%refunds%' )", ARRAY_A ) as $r ) { // phpcs:ignore
			$m = soko_json( $r['meta'] );
			if ( ! is_array( $m ) ) {
				continue;
			}
			$adj = 0;
			foreach ( array( 'credits_applied' => 1, 'credit_moved' => -1, 'refunds' => -1 ) as $k => $sign ) {
				foreach ( isset( $m[ $k ] ) ? (array) $m[ $k ] : array() as $e ) {
					if ( ! empty( $e['date'] ) && substr( $e['date'], 0, 10 ) <= $to ) {
						$adj += $sign * (float) $e['amount'];
					}
				}
			}
			if ( $adj ) {
				$out[ $r['id'] ] = ( isset( $out[ $r['id'] ] ) ? $out[ $r['id'] ] : 0 ) + $adj;
			}
		}
		return $out;
	}

	/** What had been paid to each supplier order (by number) by the end of $to. */
	public static function po_paid_asat( $to ) {
		global $wpdb;
		$out = array();
		$jt  = array();
		foreach ( $wpdb->get_results( "SELECT ref, date, entries FROM " . soko_t( 'journal' ) . " WHERE source IN ('supplier_pay','supplier_return')", ARRAY_A ) as $j ) { // phpcs:ignore
			foreach ( (array) json_decode( $j['entries'], true ) as $jl ) {
				if ( '2000' === (string) $jl['code'] ) {
					$jt[ $j['ref'] ] = ( isset( $jt[ $j['ref'] ] ) ? $jt[ $j['ref'] ] : 0 ) + (float) $jl['debit'];
					if ( $j['date'] <= $to ) {
						$out[ $j['ref'] ] = ( isset( $out[ $j['ref'] ] ) ? $out[ $j['ref'] ] : 0 ) + (float) $jl['debit'];
					}
				}
			}
		}
		// Older payments with no dated record count from the date the goods arrived.
		foreach ( $wpdb->get_results( 'SELECT number, date, received, paid FROM ' . soko_t( 'purchases' ), ARRAY_A ) as $po ) { // phpcs:ignore
			$rest = (float) $po['paid'] - ( isset( $jt[ $po['number'] ] ) ? $jt[ $po['number'] ] : 0 );
			$when = substr( $po['received'] ? $po['received'] : $po['date'], 0, 10 );
			if ( $rest > 0.005 && $when <= $to ) {
				$out[ $po['number'] ] = ( isset( $out[ $po['number'] ] ) ? $out[ $po['number'] ] : 0 ) + $rest;
			} elseif ( $rest < -0.005 && isset( $out[ $po['number'] ] ) ) {
				// Part of this payment was moved onto the order holding the rest of the goods.
				$out[ $po['number'] ] = max( 0, $out[ $po['number'] ] + $rest );
			}
		}
		return $out;
	}

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
		$lk = self::locked( $date );
		if ( $lk ) {
			return $lk;
		}
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
		$net = array();
		foreach ( $clean as $l ) {
			$net[ $l['code'] ] = ( isset( $net[ $l['code'] ] ) ? $net[ $l['code'] ] : 0 ) + $l['debit'] - $l['credit'];
		}
		if ( count( array_filter( $net, function ( $v ) { return abs( $v ) > 0.005; } ) ) < 2 ) {
			return new WP_Error( 'soko', 'This entry moves money from an account back into the same account, so it changes nothing. Use two different accounts.', array( 'status' => 400 ) );
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
		$was   = self::opening();
		foreach ( (array) $amounts as $code => $amt ) {
			$amt = round( (float) $amt, 2 );
			if ( ! $amt || ! isset( $chart[ $code ] ) || '3050' === (string) $code ) {
				continue;
			}
			if ( '1200' === (string) $code && abs( $amt - ( isset( $was['amounts']['1200'] ) ? (float) $was['amounts']['1200'] : 0 ) ) > 0.005 ) {
				return new WP_Error( 'soko', 'Enter stock on each product (Products, Adjust stock, reason "Stock count") so quantities and value stay together. Leave Stock blank here.', array( 'status' => 400 ) );
			}
			if ( $amt < 0 && in_array( (string) $code, array( '1000', '1010' ), true ) ) {
				return new WP_Error( 'soko', $chart[ $code ]['name'] . ' cannot start below zero. Check the amount.', array( 'status' => 400 ) );
			}
			// Assets carry debit balances; liabilities and equity carry credit balances.
			$debit_side = 'asset' === $chart[ $code ]['type'] || 'expense' === $chart[ $code ]['type'] || 'drawings' === $chart[ $code ]['key'];
			$dr         = $debit_side ? $amt : -$amt;
			$lines[]    = array( 'code' => (string) $code, 'debit' => max( 0, $dr ), 'credit' => max( 0, -$dr ) );
			$net       += $dr;
		}
		if ( abs( $net ) > 0.005 ) {
			$lines[] = array( 'code' => '3050', 'debit' => max( 0, -$net ), 'credit' => max( 0, $net ) );
		}
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $mm ) || ! checkdate( (int) $mm[2], (int) $mm[3], (int) $mm[1] ) || $date > soko_today() ) {
			return new WP_Error( 'soko', 'Pick the date your figures start from: a real date, today or earlier.', array( 'status' => 400 ) );
		}
		$lk = self::locked( $date );
		if ( ! $lk && ! empty( $was['date'] ) ) {
			$lk = self::locked( $was['date'] );
		}
		if ( $lk ) {
			return $lk;
		}
		$old = (int) $wpdb->get_var( "SELECT MIN(id) FROM $J WHERE source = 'opening'" ); // phpcs:ignore
		$wpdb->query( "DELETE FROM $J WHERE source = 'opening'" ); // phpcs:ignore
		self::flush();
		if ( count( $lines ) < 2 ) {
			return 0;
		}
		$id = self::journal( $date, 'Opening balances', $lines, 'opening' );
		if ( $old && ! is_wp_error( $id ) && (int) $id !== $old ) {
			// Keep the same entry number so reconciliation ticks on it are not lost.
			$wpdb->update( $J, array( 'id' => $old ), array( 'id' => (int) $id ) );
			self::flush();
			$id = $old;
		}
		return $id;
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
			$debit_side       = isset( $chart[ $l['code'] ] ) && ( in_array( $chart[ $l['code'] ]['type'], array( 'asset', 'expense' ), true ) || 'drawings' === $chart[ $l['code'] ]['key'] );
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

	/** Is any posting of this record ticked in a bank reconciliation? */
	public static function is_reconciled( $source, $id ) {
		$r = get_option( 'soko_recon', array() );
		foreach ( (array) $r as $code => $keys ) {
			foreach ( (array) $keys as $k => $when ) {
				if ( 0 === strpos( (string) $k, $source . ':' . $id . ':' ) ) {
					return true;
				}
			}
		}
		return false;
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
