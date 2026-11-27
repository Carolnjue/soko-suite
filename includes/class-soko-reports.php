<?php
/**
 * Reports: profit and loss, cash flow, sales, receivables, VAT, stock, payments, expenses.
 * Every report returns one structure that renders in the Desk and exports to Excel, PDF or CSV.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Reports {

	public static function catalog() {
		return array(
			'pl'          => 'Profit and loss',
			'cashflow'    => 'Cash flow',
			'sales'       => 'Sales by product and category',
			'customers'   => 'Sales by customer',
			'receivables' => 'Money owed (aged receivables)',
			'vat'         => 'VAT summary',
			'stock'       => 'Stock valuation',
			'payments'    => 'Payments received',
			'expenses'    => 'Expenses',
			'jobs'        => 'Installations',
			'bs'          => 'Balance sheet',
			'tb'          => 'Trial balance',
			'gl'          => 'General ledger',
			'payables'    => 'Bills owed to suppliers (aged payables)',
		);
	}

	private static function m( $n ) {
		return round( (float) $n, 2 );
	}

	/** Invoice lines in a period with cost and category. */
	private static function lines( $from, $to ) {
		global $wpdb;
		$d = soko_t( 'docs' );
		$l = soko_t( 'lines' );
		$p = soko_t( 'products' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT l.*, d.number, d.date, d.customer_id, p.cat, p.name AS pname FROM $l l JOIN $d d ON d.id = l.doc_id LEFT JOIN $p p ON p.id = l.product_id WHERE d.type = 'invoice' AND d.status <> 'void' AND d.date BETWEEN %s AND %s", $from, $to ), ARRAY_A ); // phpcs:ignore
	}

	/** Profit and loss figures, read from the ledger so every report agrees. */
	public static function pl_numbers( $from, $to ) {
		$m     = Soko_Accounts::movements( $from, $to );
		$chart = Soko_Accounts::chart();
		$g     = function ( $c ) use ( $m ) { return isset( $m[ $c ] ) ? (float) $m[ $c ] : 0; };
		$sales   = -$g( '4000' );
		$returns = $g( '4900' );
		$other   = -$g( '4950' );
		$cogs    = $g( '5000' );
		$exps    = array();
		foreach ( $m as $code => $v ) {
			if ( isset( $chart[ $code ] ) && 'expense' === $chart[ $code ]['type'] && '5000' !== (string) $code && abs( $v ) > 0.005 ) {
				$exps[] = array( 'category' => $chart[ $code ]['name'], 'net' => round( $v, 2 ), 'code' => (string) $code );
			}
		}
		usort( $exps, function ( $a, $b ) { return $b['net'] <=> $a['net']; } );
		$ex  = array_sum( wp_list_pluck( $exps, 'net' ) );
		$net_sales = $sales - $returns;
		$vat = -Soko_Accounts::sum( '2100', $from, $to, array( 'invoice', 'credit' ) );
		return array(
			'sales'    => self::m( $net_sales ),
			'gross_sales' => self::m( $sales ),
			'returns'  => self::m( $returns ),
			'other'    => self::m( $other ),
			'cogs'     => self::m( $cogs ),
			'gross'    => self::m( $net_sales - $cogs ),
			'expenses' => $exps,
			'opex'     => self::m( $ex ),
			'net'      => self::m( $net_sales + $other - $cogs - $ex ),
			'vat'      => self::m( $vat ),
			'margin'   => $net_sales ? round( ( $net_sales - $cogs ) / $net_sales * 100 ) : 0,
		);
	}

	public static function run( $type, $from, $to ) {
		global $wpdb;
		if ( $from && $to && $from > $to ) {
			list( $from, $to ) = array( $to, $from ); // dates entered the wrong way round
		}
		$period = date_i18n( 'j M Y', strtotime( $from ) ) . ' to ' . date_i18n( 'j M Y', strtotime( $to ) );
		$title  = isset( self::catalog()[ $type ] ) ? self::catalog()[ $type ] : 'Report';
		$out    = array( 'type' => $type, 'title' => $title, 'period' => $period, 'kpis' => array(), 'sections' => array(), 'chart' => null );
		$D      = soko_t( 'docs' );
		$C      = soko_t( 'customers' );
		$P      = soko_t( 'payments' );
		$E      = soko_t( 'expenses' );
		switch ( $type ) {
			case 'pl':
				$n = self::pl_numbers( $from, $to );
				$rows = array( array( 'Sales (excluding VAT)', $n['gross_sales'] ) );
				if ( $n['returns'] ) {
					$rows[] = array( 'Less credit notes and returns', -$n['returns'] );
					$rows[] = array( 'Net sales', $n['sales'], '_b' => 1 );
				}
				$rows[] = array( 'Cost of goods sold', -$n['cogs'] );
				$rows[] = array( 'Gross profit', $n['gross'], '_b' => 1 );
				if ( $n['other'] ) {
					$rows[] = array( 'Other income', $n['other'] );
				}
				foreach ( $n['expenses'] as $x ) {
					$rows[] = array( $x['category'], -1 * self::m( $x['net'] ) );
				}
				$rows[] = array( 'Total operating expenses', -$n['opex'], '_b' => 1 );
				$rows[] = array( 'Net profit before tax', $n['net'], '_b' => 1 );
				$out['kpis'] = array( array( 'Sales excl. VAT', soko_money( $n['sales'] ) ), array( 'Gross profit', soko_money( $n['gross'] ) ), array( 'Gross margin', $n['margin'] . '%' ), array( 'Net profit', soko_money( $n['net'] ) ) );
				$out['sections'][] = array( 'title' => 'Profit and loss', 'head' => array( 'Line', 'KES' ), 'rows' => $rows, 'widths' => array( 130, 50 ) );
				break;

			case 'cashflow':
				$in  = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(received) d, SUM(amount) a FROM $P WHERE status = 'matched' AND received BETWEEN %s AND %s GROUP BY DATE(received)", $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
				$un  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $P WHERE status <> 'matched' AND received BETWEEN %s AND %s", $from . ' 00:00:00', $to . ' 23:59:59' ) ); // phpcs:ignore
				$out_e = $wpdb->get_results( $wpdb->prepare( "SELECT date d, SUM(amount) a FROM $E WHERE date BETWEEN %s AND %s GROUP BY date", $from, $to ), ARRAY_A ); // phpcs:ignore
				$PU  = soko_t( 'purchases' );
				$out_p = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(received) d, SUM(paid) a FROM $PU WHERE paid > 0 AND received BETWEEN %s AND %s GROUP BY DATE(received)", $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
				$weeks = array();
				$add = function ( $rows, $k ) use ( &$weeks ) {
					foreach ( $rows as $r ) {
						$w = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $r['d'] ) ) );
						if ( ! isset( $weeks[ $w ] ) ) {
							$weeks[ $w ] = array( 'in' => 0, 'out' => 0 );
						}
						$weeks[ $w ][ $k ] += (float) $r['a'];
					}
				};
				$add( $in, 'in' );
				$add( $out_e, 'out' );
				$add( $out_p, 'out' );
				ksort( $weeks );
				$rows = array();
				$ti = 0;
				$to_ = 0;
				foreach ( $weeks as $w => $v ) {
					$rows[] = array( 'Week of ' . date_i18n( 'j M', strtotime( $w ) ), self::m( $v['in'] ), self::m( $v['out'] ), self::m( $v['in'] - $v['out'] ) );
					$ti += $v['in'];
					$to_ += $v['out'];
				}
				$rows[] = array( 'Total', self::m( $ti ), self::m( $to_ ), self::m( $ti - $to_ ), '_b' => 1 );
				$out['kpis'] = array( array( 'Money in', soko_money( $ti ) ), array( 'Money out', soko_money( $to_ ) ), array( 'Net cash', soko_money( $ti - $to_ ) ), array( 'Unmatched receipts', soko_money( $un ) ) );
				$out['sections'][] = array( 'title' => 'Cash in and out by week', 'head' => array( 'Week', 'Money in', 'Money out', 'Net' ), 'rows' => $rows, 'widths' => array( 60, 40, 40, 40 ) );
				$out['chart'] = array_map( function ( $w, $v ) { return array( 'label' => date_i18n( 'j M', strtotime( $w ) ), 'in' => $v['in'], 'out' => $v['out'] ); }, array_keys( $weeks ), $weeks );
				break;

			case 'sales':
				$bycat = array();
				$byp   = array();
				foreach ( self::lines( $from, $to ) as $l ) {
					$cat = $l['cat'] ? soko_category( $l['cat'] )['name'] : 'Other items and services';
					$amt = $l['qty'] * $l['price'];
					$bycat[ $cat ] = ( isset( $bycat[ $cat ] ) ? $bycat[ $cat ] : 0 ) + $amt;
					$k = $l['description'];
					if ( ! isset( $byp[ $k ] ) ) {
						$byp[ $k ] = array( 0, 0, 0 );
					}
					$byp[ $k ][0] += $l['qty'];
					$byp[ $k ][1] += $amt;
					$byp[ $k ][2] += $amt - $l['qty'] * $l['cost'] * ( 1 + ( $l['vatable'] ? soko_setting( 'vat_rate' ) / 100 : 0 ) );
				}
				arsort( $bycat );
				uasort( $byp, function ( $a, $b ) { return $b[1] <=> $a[1]; } );
				$out['kpis'] = array( array( 'Sales incl. VAT', soko_money( array_sum( $bycat ) ) ), array( 'Categories sold', count( $bycat ) ), array( 'Products sold', count( $byp ) ) );
				$out['sections'][] = array( 'title' => 'By category', 'head' => array( 'Category', 'Sales' ), 'rows' => array_map( function ( $k, $v ) { return array( $k, round( $v ) ); }, array_keys( $bycat ), $bycat ), 'widths' => array( 130, 50 ) );
				$out['sections'][] = array( 'title' => 'By product or service', 'head' => array( 'Item', 'Qty', 'Sales', 'Margin' ), 'rows' => array_map( function ( $k, $v ) { return array( $k, $v[0] + 0, round( $v[1] ), round( $v[2] ) ); }, array_keys( $byp ), $byp ), 'widths' => array( 100, 20, 30, 30 ) );
				$out['chart'] = array_map( function ( $k, $v ) { return array( 'label' => $k, 'value' => $v ); }, array_keys( $bycat ), $bycat );
				break;

			case 'customers':
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.name, COUNT(d.id) n, SUM(d.total) t, SUM(d.paid) p FROM $D d JOIN $C c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.status <> 'void' AND d.date BETWEEN %s AND %s GROUP BY c.id ORDER BY t DESC", $from, $to ), ARRAY_A ); // phpcs:ignore
				$out['sections'][] = array( 'title' => 'Sales by customer', 'head' => array( 'Customer', 'Invoices', 'Invoiced', 'Paid', 'Owing' ), 'rows' => array_map( function ( $r ) { return array( $r['name'], (int) $r['n'], round( $r['t'] ), round( $r['p'] ), round( $r['t'] - $r['p'] ) ); }, $rows ), 'widths' => array( 70, 20, 30, 30, 30 ) );
				$out['kpis'] = array( array( 'Customers', count( $rows ) ), array( 'Invoiced', soko_money( array_sum( wp_list_pluck( $rows, 't' ) ) ) ) );
				break;

			case 'receivables':
				$open = $wpdb->get_results( "SELECT d.number, d.date, d.due, d.total, d.paid, c.name, c.phone FROM $D d LEFT JOIN $C c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.status <> 'void' AND d.paid < d.total - 0.5 ORDER BY d.due", ARRAY_A ); // phpcs:ignore
				$b = array( 'Not yet due' => 0, '1 to 30 days late' => 0, '31 to 60 days late' => 0, '61 to 90 days late' => 0, 'Over 90 days late' => 0 );
				$rows = array();
				foreach ( $open as $o ) {
					$bal  = $o['total'] - $o['paid'];
					$late = $o['due'] ? floor( ( strtotime( soko_today() ) - strtotime( $o['due'] ) ) / DAY_IN_SECONDS ) : 0;
					$k    = $late <= 0 ? 'Not yet due' : ( $late <= 30 ? '1 to 30 days late' : ( $late <= 60 ? '31 to 60 days late' : ( $late <= 90 ? '61 to 90 days late' : 'Over 90 days late' ) ) );
					$b[ $k ] += $bal;
					$rows[] = array( $o['number'], $o['name'], soko_phone_display( $o['phone'] ), $o['due'], max( 0, (int) $late ), round( $bal ) );
				}
				$out['kpis'] = array( array( 'Total owed', soko_money( array_sum( $b ) ) ), array( 'Overdue', soko_money( array_sum( $b ) - $b['Not yet due'] ) ), array( 'Open invoices', count( $open ) ) );
				$out['sections'][] = array( 'title' => 'Ageing', 'head' => array( 'Bucket', 'KES' ), 'rows' => array_map( function ( $k, $v ) { return array( $k, round( $v ) ); }, array_keys( $b ), $b ), 'widths' => array( 130, 50 ) );
				$out['sections'][] = array( 'title' => 'Open invoices', 'head' => array( 'Invoice', 'Customer', 'Phone', 'Due', 'Days late', 'Balance' ), 'rows' => $rows, 'widths' => array( 25, 55, 30, 25, 20, 25 ) );
				break;

			case 'vat':
				$n   = self::pl_numbers( $from, $to );
				$ein = Soko_Accounts::sum( '1300', $from, $to, array( 'expense' ) );
				$pin = Soko_Accounts::sum( '1300', $from, $to, array( 'bill' ) );
				$net = $n['vat'] - $ein - $pin;
				$out['kpis'] = array( array( 'Output VAT', soko_money( $n['vat'] ) ), array( 'Input VAT', soko_money( $ein + $pin ) ), array( $net >= 0 ? 'VAT payable' : 'VAT credit', soko_money( abs( $net ) ) ) );
				$out['sections'][] = array( 'title' => 'VAT position', 'head' => array( 'Line', 'KES' ), 'rows' => array( array( 'Output VAT on sales', $n['vat'] ), array( 'Input VAT on purchases', -round( $pin, 2 ) ), array( 'Input VAT on expenses', -round( $ein, 2 ) ), array( $net >= 0 ? 'Payable to KRA' : 'Credit carried forward', round( $net, 2 ), '_b' => 1 ) ), 'widths' => array( 130, 50 ) );
				$inv = $wpdb->get_results( $wpdb->prepare( "SELECT d.number, d.date, c.name, c.kra, d.subtotal, d.vat, d.total, d.etims_ref FROM $D d LEFT JOIN $C c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.status <> 'void' AND d.date BETWEEN %s AND %s ORDER BY d.date", $from, $to ), ARRAY_A ); // phpcs:ignore
				$out['sections'][] = array( 'title' => 'Sales invoices', 'head' => array( 'Invoice', 'Date', 'Customer', 'KRA PIN', 'Net', 'VAT', 'eTIMS' ), 'rows' => array_map( function ( $r ) { return array( $r['number'], $r['date'], $r['name'], $r['kra'], round( $r['subtotal'] ), round( $r['vat'] ), $r['etims_ref'] ); }, $inv ), 'widths' => array( 22, 22, 45, 26, 22, 18, 25 ) );
				break;

			case 'stock':
				$ps   = Soko_Ledger::products();
				$rows = array();
				$tv   = 0;
				foreach ( $ps as $p ) {
					if ( 'product' !== $p['kind'] ) {
						continue;
					}
					$v   = $p['stock'] * $p['cost'];
					$tv += $v;
					$rows[] = array( $p['sku'], $p['name'], $p['stock'] + 0, round( $p['cost'] ), round( $v ), $p['stock'] <= $p['reorder'] ? 'Reorder' : '' );
				}
				$out['kpis'] = array( array( 'Stock value at cost', soko_money( $tv ) ), array( 'Products', count( $rows ) ), array( 'To reorder', count( array_filter( $rows, function ( $r ) { return 'Reorder' === $r[5]; } ) ) ) );
				$out['sections'][] = array( 'title' => 'Stock on hand', 'head' => array( 'Code', 'Product', 'Qty', 'Unit cost', 'Value', 'Status' ), 'rows' => $rows, 'widths' => array( 24, 70, 16, 25, 27, 18 ) );
				break;

			case 'payments':
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.*, d.number FROM $P p LEFT JOIN $D d ON d.id = p.doc_id WHERE p.received BETWEEN %s AND %s ORDER BY p.received DESC", $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
				$out['kpis'] = array( array( 'Received', soko_money( array_sum( wp_list_pluck( $rows, 'amount' ) ) ) ), array( 'Payments', count( $rows ) ), array( 'Unmatched', count( array_filter( $rows, function ( $r ) { return 'matched' !== $r['status']; } ) ) ) );
				$out['sections'][] = array( 'title' => 'Payments', 'head' => array( 'Date', 'Code', 'Method', 'From', 'Invoice', 'Amount' ), 'rows' => array_map( function ( $r ) { return array( substr( $r['received'], 0, 16 ), $r['code'], 'mpesa' === $r['method'] ? 'M-Pesa' : ucfirst( $r['method'] ), $r['payer'], $r['number'] ? $r['number'] : 'Unmatched', round( $r['amount'] ) ); }, $rows ), 'widths' => array( 30, 28, 20, 50, 25, 27 ) );
				break;

			case 'expenses':
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $E WHERE date BETWEEN %s AND %s ORDER BY date DESC", $from, $to ), ARRAY_A ); // phpcs:ignore
				$by = array();
				foreach ( $rows as $r ) {
					$by[ $r['category'] ] = ( isset( $by[ $r['category'] ] ) ? $by[ $r['category'] ] : 0 ) + $r['amount'];
				}
				arsort( $by );
				$out['kpis'] = array( array( 'Spent', soko_money( array_sum( $by ) ) ), array( 'Entries', count( $rows ) ) );
				$out['sections'][] = array( 'title' => 'By category', 'head' => array( 'Category', 'KES' ), 'rows' => array_map( function ( $k, $v ) { return array( $k, round( $v ) ); }, array_keys( $by ), $by ), 'widths' => array( 130, 50 ) );
				$out['sections'][] = array( 'title' => 'All expenses', 'head' => array( 'Date', 'Category', 'Description', 'Paid by', 'Amount' ), 'rows' => array_map( function ( $r ) { return array( $r['date'], $r['category'], $r['description'], $r['method'], round( $r['amount'] ) ); }, $rows ), 'widths' => array( 22, 40, 70, 22, 26 ) );
				$out['chart'] = array_map( function ( $k, $v ) { return array( 'label' => $k, 'value' => $v ); }, array_keys( $by ), $by );
				break;

			case 'jobs':
				$J    = soko_t( 'jobs' );
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT j.*, c.name FROM $J j LEFT JOIN $C c ON c.id = j.customer_id WHERE j.date BETWEEN %s AND %s ORDER BY j.date", $from, $to ), ARRAY_A ); // phpcs:ignore
				$undated = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $J WHERE date IS NULL OR date = ''" ); // phpcs:ignore
				$out['kpis'] = array( array( 'Jobs', count( $rows ) ), array( 'Commissioned', count( array_filter( $rows, function ( $r ) { return 'Commissioned' === $r['stage']; } ) ) ), array( 'Not yet dated', $undated ) );
				$out['sections'][] = array( 'title' => 'Installations', 'head' => array( 'Date', 'Customer', 'Work', 'Stage', 'Team' ), 'rows' => array_map( function ( $r ) { return array( $r['date'], $r['name'], $r['title'], $r['stage'], $r['techs'] ); }, $rows ), 'align' => array( 'L', 'L', 'L', 'L', 'L' ), 'widths' => array( 24, 45, 55, 26, 30 ) );
				break;

			case 'bs':
				$period = 'As at ' . date_i18n( 'j M Y', strtotime( $to ) );
				$out['period'] = $period;
				$b      = Soko_Accounts::balances( $to );
				$chart  = Soko_Accounts::chart();
				$assets = array();
				$liabs  = array();
				$equity = array();
				$ta = 0; $tl = 0; $te = 0;
				foreach ( $chart as $code => $a ) {
					$v = isset( $b[ $code ] ) ? round( $b[ $code ], 2 ) : 0;
					if ( abs( $v ) < 0.005 ) {
						continue;
					}
					if ( 'asset' === $a['type'] ) {
						$assets[] = array( $code . '  ' . $a['name'], $v );
						$ta      += $v;
					} elseif ( 'liability' === $a['type'] ) {
						$liabs[] = array( $code . '  ' . $a['name'], -$v );
						$tl     -= $v;
					} elseif ( 'equity' === $a['type'] ) {
						$equity[] = array( $code . '  ' . $a['name'], -$v );
						$te      -= $v;
					}
				}
				$fy    = substr( $to, 0, 4 ) . '-01-01';
				$ret   = Soko_Accounts::profit( null, gmdate( 'Y-m-d', strtotime( $fy . ' -1 day' ) ) );
				$cur   = Soko_Accounts::profit( $fy, $to );
				if ( abs( $ret ) > 0.005 ) {
					$equity[] = array( 'Retained profit from earlier years', $ret );
				}
				$equity[] = array( 'Profit this year (since 1 Jan)', $cur );
				$te      += $ret + $cur;
				$assets[]  = array( 'Total assets', round( $ta, 2 ), '_b' => 1 );
				$liabs[]   = array( 'Total liabilities', round( $tl, 2 ), '_b' => 1 );
				$equity[]  = array( 'Total equity', round( $te, 2 ), '_b' => 1 );
				$diff      = round( $ta - $tl - $te, 2 );
				$out['kpis'] = array( array( 'Total assets', soko_money( $ta ) ), array( 'Total liabilities', soko_money( $tl ) ), array( 'Net worth (equity)', soko_money( $te ) ) );
				$out['sections'][] = array( 'title' => 'What the business owns (assets)', 'head' => array( 'Account', 'KES' ), 'rows' => $assets, 'widths' => array( 130, 50 ) );
				$out['sections'][] = array( 'title' => 'What the business owes (liabilities)', 'head' => array( 'Account', 'KES' ), 'rows' => $liabs, 'widths' => array( 130, 50 ) );
				$out['sections'][] = array( 'title' => "Owner's equity", 'head' => array( 'Account', 'KES' ), 'rows' => $equity, 'widths' => array( 130, 50 ) );
				$out['check'] = abs( $diff ) < 0.01 ? 'balanced' : 'out by ' . $diff;
				if ( isset( $b['1200'] ) && $b['1200'] < -0.5 ) {
					$out['note'] = 'Stock shows below zero because stock was sold that was never entered at cost. Add your stock in Accounts → Opening balances.';
				}
				break;

			case 'tb':
				$out['period'] = 'As at ' . date_i18n( 'j M Y', strtotime( $to ) );
				$b    = Soko_Accounts::balances( $to );
				$rows = array();
				$td = 0; $tc = 0;
				foreach ( Soko_Accounts::chart() as $code => $a ) {
					$v = isset( $b[ $code ] ) ? round( $b[ $code ], 2 ) : 0;
					if ( abs( $v ) < 0.005 ) {
						continue;
					}
					$rows[] = array( $code, $a['name'], $v > 0 ? $v : '', $v < 0 ? -$v : '' );
					$td    += max( 0, $v );
					$tc    += max( 0, -$v );
				}
				$rows[] = array( '', 'Totals', round( $td, 2 ), round( $tc, 2 ), '_b' => 1 );
				$out['kpis'] = array( array( 'Total debits', soko_money( $td ) ), array( 'Total credits', soko_money( $tc ) ), array( 'Difference', soko_money( abs( $td - $tc ) ) ) );
				$out['sections'][] = array( 'title' => 'Trial balance', 'head' => array( 'Code', 'Account', 'Debit', 'Credit' ), 'rows' => $rows, 'widths' => array( 20, 90, 35, 35 ), 'align' => array( 'L', 'L', 'R', 'R' ) );
				break;

			case 'gl':
				$chart = Soko_Accounts::chart();
				$open  = Soko_Accounts::balances( gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) ) );
				$by    = array();
				foreach ( Soko_Accounts::postings( $to ) as $pp ) {
					if ( $pp[0] >= $from ) {
						$by[ $pp[1] ][] = $pp;
					}
				}
				ksort( $by, SORT_STRING );
				$n = 0;
				foreach ( $by as $code => $ps ) {
					$bal  = isset( $open[ $code ] ) ? round( $open[ $code ], 2 ) : 0;
					$rows = array( array( '', 'Opening balance', '', '', $bal, '_b' => 1 ) );
					foreach ( $ps as $pp ) {
						$bal   += $pp[2] - $pp[3];
						$rows[] = array( $pp[0], $pp[4], $pp[2] ? $pp[2] : '', $pp[3] ? $pp[3] : '', round( $bal, 2 ) );
						++$n;
					}
					$rows[] = array( '', 'Closing balance', '', '', round( $bal, 2 ), '_b' => 1 );
					$out['sections'][] = array( 'title' => $code . '  ' . ( isset( $chart[ $code ] ) ? $chart[ $code ]['name'] : '' ), 'head' => array( 'Date', 'Details', 'Debit', 'Credit', 'Balance' ), 'rows' => $rows, 'widths' => array( 24, 86, 23, 23, 24 ), 'align' => array( 'L', 'L', 'R', 'R', 'R' ), 'code' => (string) $code );
				}
				$out['kpis'] = array( array( 'Accounts', count( $by ) ), array( 'Entries', $n ) );
				break;

			case 'payables':
				$out['period'] = 'As at ' . date_i18n( 'j M Y', strtotime( $to ) );
				$PU   = soko_t( 'purchases' );
				$rows = array();
				$tot  = array( 0, 0, 0, 0 );
				foreach ( $wpdb->get_results( 'SELECT p.number, p.date, p.received, p.total, p.paid, s.name FROM ' . $PU . ' p LEFT JOIN ' . soko_t( 'suppliers' ) . " s ON s.id = p.supplier_id WHERE p.status = 'received' AND p.total - p.paid > 0.5 ORDER BY p.received", ARRAY_A ) as $r ) { // phpcs:ignore
					$d    = substr( $r['received'] ? $r['received'] : $r['date'], 0, 10 );
					$days = max( 0, (int) floor( ( strtotime( $to ) - strtotime( $d ) ) / DAY_IN_SECONDS ) );
					$owe  = round( $r['total'] - $r['paid'], 2 );
					$bk   = $days <= 30 ? 0 : ( $days <= 60 ? 1 : ( $days <= 90 ? 2 : 3 ) );
					$tot[ $bk ] += $owe;
					$cells = array( '', '', '', '' );
					$cells[ $bk ] = $owe;
					$rows[] = array_merge( array( $r['name'] ? $r['name'] : '—', $r['number'], $d ), $cells );
				}
				$rows[] = array_merge( array( 'Total', '', '' ), array_map( function ( $x ) { return round( $x, 2 ); }, $tot ), array( '_b' => 1 ) );
				$out['kpis'] = array( array( 'Owed to suppliers', soko_money( array_sum( $tot ) ) ), array( 'Over 60 days', soko_money( $tot[2] + $tot[3] ) ) );
				$out['sections'][] = array( 'title' => 'Unpaid supplier bills', 'head' => array( 'Supplier', 'Order', 'Received', '0–30 days', '31–60', '61–90', 'Over 90' ), 'rows' => $rows );
				break;
		}
		return $out;
	}

	/** Export a report. Returns array( body, mime, filename ). */
	public static function export( $type, $from, $to, $format ) {
		$r    = self::run( $type, $from, $to );
		$base = sanitize_file_name( strtolower( str_replace( ' ', '-', $r['title'] ) ) . '-' . $from . '-to-' . $to );
		if ( 'pdf' === $format ) {
			return array( Soko_PDF::report( $r['title'], $r['period'], $r['sections'], $r['kpis'] ), 'application/pdf', $base . '.pdf' );
		}
		$clean = function ( $rows ) {
			return array_map( function ( $row ) { unset( $row['_b'] ); return array_values( $row ); }, $rows );
		};
		if ( 'xlsx' === $format ) {
			$sheets = array();
			foreach ( $r['sections'] as $s ) {
				$sheets[ $s['title'] ] = array( 'head' => $s['head'], 'rows' => $clean( $s['rows'] ) );
			}
			$bin = Soko_XLSX::build( $sheets );
			if ( $bin ) {
				return array( $bin, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $base . '.xlsx' );
			}
		}
		$fh = fopen( 'php://temp', 'w+' ); // phpcs:ignore
		foreach ( $r['sections'] as $s ) {
			fputcsv( $fh, array( $s['title'] ) );
			fputcsv( $fh, $s['head'] );
			foreach ( $clean( $s['rows'] ) as $row ) {
				fputcsv( $fh, $row );
			}
			fputcsv( $fh, array() );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore
		return array( "\xEF\xBB\xBF" . $csv, 'text/csv', $base . '.csv' );
	}

	/** Figures for the Desk dashboard. */
	public static function dashboard() {
		global $wpdb;
		$D   = soko_t( 'docs' );
		$P   = soko_t( 'payments' );
		$m0  = current_time( 'Y-m-01' );
		$m1  = current_time( 'Y-m-t' );
		$pm0 = gmdate( 'Y-m-01', strtotime( $m0 . ' -1 month' ) );
		$pm1 = gmdate( 'Y-m-t', strtotime( $pm0 ) );
		$this_m = self::pl_numbers( $m0, $m1 );
		$last_m = self::pl_numbers( $pm0, $pm1 );
		$sales  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total) FROM $D WHERE type='invoice' AND status <> 'void' AND date BETWEEN %s AND %s", $m0, $m1 ) ); // phpcs:ignore
		$psales = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total) FROM $D WHERE type='invoice' AND status <> 'void' AND date BETWEEN %s AND %s", $pm0, $pm1 ) ); // phpcs:ignore
		$coll   = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(amount) a, COUNT(*) n FROM $P WHERE status = 'matched' AND received BETWEEN %s AND %s", $m0 . ' 00:00:00', $m1 . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
		$owed   = (float) $wpdb->get_var( "SELECT SUM(total - paid) FROM $D WHERE type='invoice' AND status <> 'void' AND paid < total" ); // phpcs:ignore
		$over   = $wpdb->get_results( $wpdb->prepare( "SELECT d.id, d.number, d.total - d.paid bal, d.due, c.name FROM $D d LEFT JOIN " . soko_t( 'customers' ) . " c ON c.id = d.customer_id WHERE d.type='invoice' AND d.status <> 'void' AND d.paid < d.total - 0.5 AND d.due < %s ORDER BY d.due LIMIT 8", soko_today() ), ARRAY_A ); // phpcs:ignore
		$unm    = $wpdb->get_results( "SELECT id, code, amount, payer FROM $P WHERE status <> 'matched' ORDER BY received DESC LIMIT 5", ARRAY_A ); // phpcs:ignore
		$held   = Soko_Ledger::held();
		$low    = array();
		foreach ( $wpdb->get_results( 'SELECT id, name, stock, reorder FROM ' . soko_t( 'products' ) . " WHERE kind = 'product'", ARRAY_A ) as $lp ) { // phpcs:ignore
			$free = (float) $lp['stock'] - ( isset( $held[ (int) $lp['id'] ] ) ? $held[ (int) $lp['id'] ] : 0 );
			if ( $free <= (float) $lp['reorder'] ) {
				$low[] = array( 'id' => $lp['id'], 'name' => $lp['name'], 'stock' => $lp['stock'] + 0, 'free' => $free );
			}
		}
		usort( $low, function ( $a, $b ) { return $a['free'] <=> $b['free']; } );
		$low = array_slice( $low, 0, 5 );
		$today  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, title, start, who, location, kind FROM ' . soko_t( 'events' ) . ' WHERE done = 0 AND start BETWEEN %s AND %s ORDER BY start', soko_today() . ' 00:00:00', soko_today() . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
		$lic    = $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, expires FROM ' . soko_t( 'licences' ) . ' WHERE expires <= %s ORDER BY expires LIMIT 4', gmdate( 'Y-m-d', strtotime( soko_today() . ' +60 days' ) ) ), ARRAY_A ); // phpcs:ignore
		$weeks  = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$ws = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( soko_today() . " -$i weeks" ) ) );
			$we = gmdate( 'Y-m-d', strtotime( $ws . ' +6 days' ) );
			$in = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $P WHERE received BETWEEN %s AND %s", $ws . ' 00:00:00', $we . ' 23:59:59' ) ); // phpcs:ignore
			$ou = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(amount) FROM ' . soko_t( 'expenses' ) . ' WHERE date BETWEEN %s AND %s', $ws, $we ) ); // phpcs:ignore
			$ou += (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(paid) FROM ' . soko_t( 'purchases' ) . ' WHERE received BETWEEN %s AND %s', $ws . ' 00:00:00', $we . ' 23:59:59' ) ); // phpcs:ignore
			$weeks[] = array( 'label' => date_i18n( 'j M', strtotime( $ws ) ), 'in' => $in, 'out' => $ou );
		}
		$cats = self::run( 'sales', $m0, $m1 );
		$recent = $wpdb->get_results( "SELECT p.code, p.amount, p.payer, p.received, p.status, d.number FROM $P p LEFT JOIN $D d ON d.id = p.doc_id ORDER BY p.received DESC LIMIT 6", ARRAY_A ); // phpcs:ignore
		$newleads = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . soko_t( 'leads' ) . " WHERE stage = 'New'" ); // phpcs:ignore
		$topack   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $D WHERE type='invoice' AND fulfil = 'to_pack'" ); // phpcs:ignore
		$neworders = $wpdb->get_results( $wpdb->prepare( "SELECT d.id, d.number, d.total, d.paid, c.name FROM $D d LEFT JOIN " . soko_t( 'customers' ) . " c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.source = 'web' AND d.status <> 'void' AND d.paid < d.total AND d.date >= %s ORDER BY d.id DESC LIMIT 5", gmdate( 'Y-m-d', strtotime( soko_today() . ' -14 days' ) ) ), ARRAY_A ); // phpcs:ignore
		$money_view = soko_can( 'soko_reports' );
		if ( ! $money_view ) {
			foreach ( $weeks as &$wk ) {
				$wk['out'] = 0;
			}
			unset( $wk );
		}
		return array(
			'month'        => date_i18n( 'F Y' ),
			'sales'        => $sales,
			'sales_change' => $psales ? round( ( $sales - $psales ) / $psales * 100 ) : null,
			'collected'    => (float) $coll['a'],
			'collected_n'  => (int) $coll['n'],
			'owed'         => $owed,
			'overdue'      => $over,
			'neworders'    => $neworders,
			'margin'       => $money_view ? $this_m['margin'] : null,
			'margin_prev'  => $money_view ? $last_m['margin'] : null,
			'net'          => $money_view ? $this_m['net'] : null,
			'unmatched'    => $unm,
			'low'          => $low,
			'today'        => $today,
			'licences'     => $lic,
			'weeks'        => $weeks,
			'cats'         => isset( $cats['chart'] ) ? array_slice( $cats['chart'], 0, 6 ) : array(),
			'recent'       => $recent,
			'leads_new'    => $newleads,
			'to_pack'      => $topack,
			'etims'        => Soko_Etims::summary(),
		);
	}
}
