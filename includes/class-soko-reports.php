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

	public static function pl_numbers( $from, $to ) {
		global $wpdb;
		$rate  = soko_setting( 'vat_registered' ) ? (float) soko_setting( 'vat_rate', 16 ) : 0;
		$incl  = (int) soko_setting( 'prices_incl_vat', 1 );
		$sales = 0;
		$cogs  = 0;
		$vat   = 0;
		foreach ( self::lines( $from, $to ) as $l ) {
			$amt = $l['qty'] * $l['price'];
			$v   = $l['vatable'] ? ( $incl ? $amt * $rate / ( 100 + $rate ) : $amt * $rate / 100 ) : 0;
			$sales += $incl ? $amt - $v : $amt;
			$vat   += $v;
			$cogs  += $l['qty'] * $l['cost'];
		}
		$e    = soko_t( 'expenses' );
		$exps = $wpdb->get_results( $wpdb->prepare( "SELECT category, SUM(amount - vat) AS net, SUM(vat) AS vat FROM $e WHERE date BETWEEN %s AND %s GROUP BY category ORDER BY net DESC", $from, $to ), ARRAY_A ); // phpcs:ignore
		$ex   = array_sum( wp_list_pluck( $exps, 'net' ) );
		return array(
			'sales'    => self::m( $sales ),
			'cogs'     => self::m( $cogs ),
			'gross'    => self::m( $sales - $cogs ),
			'expenses' => $exps,
			'opex'     => self::m( $ex ),
			'net'      => self::m( $sales - $cogs - $ex ),
			'vat'      => self::m( $vat ),
			'margin'   => $sales ? round( ( $sales - $cogs ) / $sales * 100 ) : 0,
		);
	}

	public static function run( $type, $from, $to ) {
		global $wpdb;
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
				$rows = array(
					array( 'Sales (excluding VAT)', $n['sales'] ),
					array( 'Cost of goods sold', -$n['cogs'] ),
					array( 'Gross profit', $n['gross'], '_b' => 1 ),
				);
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
				$ein = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(vat) FROM $E WHERE date BETWEEN %s AND %s", $from, $to ) ); // phpcs:ignore
				$PU  = soko_t( 'purchases' );
				$pin = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(vat) FROM $PU WHERE status = 'received' AND date BETWEEN %s AND %s", $from, $to ) ); // phpcs:ignore
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
				$out['sections'][] = array( 'title' => 'Stock on hand', 'head' => array( 'SKU', 'Product', 'Qty', 'Unit cost', 'Value', 'Status' ), 'rows' => $rows, 'widths' => array( 24, 70, 16, 25, 27, 18 ) );
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
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT j.*, c.name FROM $J j LEFT JOIN $C c ON c.id = j.customer_id WHERE (j.date BETWEEN %s AND %s) OR j.date IS NULL ORDER BY j.date", $from, $to ), ARRAY_A ); // phpcs:ignore
				$out['kpis'] = array( array( 'Jobs', count( $rows ) ), array( 'Commissioned', count( array_filter( $rows, function ( $r ) { return 'Commissioned' === $r['stage']; } ) ) ) );
				$out['sections'][] = array( 'title' => 'Installations', 'head' => array( 'Date', 'Customer', 'Work', 'Stage', 'Team' ), 'rows' => array_map( function ( $r ) { return array( $r['date'], $r['name'], $r['title'], $r['stage'], $r['techs'] ); }, $rows ), 'align' => array( 'L', 'L', 'L', 'L', 'L' ), 'widths' => array( 24, 45, 55, 26, 30 ) );
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
		$low    = $wpdb->get_results( 'SELECT id, name, stock FROM ' . soko_t( 'products' ) . " WHERE kind = 'product' AND stock <= reorder ORDER BY stock LIMIT 5", ARRAY_A ); // phpcs:ignore
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
		return array(
			'month'        => date_i18n( 'F Y' ),
			'sales'        => $sales,
			'sales_change' => $psales ? round( ( $sales - $psales ) / $psales * 100 ) : null,
			'collected'    => (float) $coll['a'],
			'collected_n'  => (int) $coll['n'],
			'owed'         => $owed,
			'overdue'      => $over,
			'margin'       => $this_m['margin'],
			'margin_prev'  => $last_m['margin'],
			'net'          => $this_m['net'],
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
