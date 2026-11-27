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
		return $wpdb->get_results( $wpdb->prepare( "SELECT l.*, d.number, d.date, d.customer_id, p.cat, p.name AS pname FROM $l l JOIN $d d ON d.id = l.doc_id LEFT JOIN $p p ON p.id = l.product_id WHERE d.type = 'invoice' AND d.status <> 'void' AND " . Soko_Accounts::confirmed_sql( 'd' ) . " AND d.date BETWEEN %s AND %s", $from, $to ), ARRAY_A ); // phpcs:ignore
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
				$n    = self::pl_numbers( $from, $to );
				// Same length period immediately before, for comparison.
				$days = (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS );
				$pt   = gmdate( 'Y-m-d', strtotime( $from . ' -1 day' ) );
				$pf   = gmdate( 'Y-m-d', strtotime( $pt . ' -' . $days . ' days' ) );
				if ( '01-01' === substr( $from, 5 ) ) {
					// Year to date or a full year: compare with the same dates last year.
					$pf = ( (int) substr( $from, 0, 4 ) - 1 ) . substr( $from, 4 );
					$pt = ( (int) substr( $to, 0, 4 ) - 1 ) . ( '02-29' === substr( $to, 5 ) ? '-02-28' : substr( $to, 4 ) );
				} elseif ( gmdate( 'd', strtotime( $from ) ) === '01' && gmdate( 'Y-m-t', strtotime( $to ) ) === $to ) {
					$months = ( (int) gmdate( 'Y', strtotime( $to ) ) - (int) gmdate( 'Y', strtotime( $from ) ) ) * 12 + (int) gmdate( 'n', strtotime( $to ) ) - (int) gmdate( 'n', strtotime( $from ) ) + 1;
					$pf     = gmdate( 'Y-m-01', strtotime( $from . ' -' . $months . ' months' ) );
				}
				$q   = self::pl_numbers( $pf, $pt );
				$qx  = array();
				foreach ( $q['expenses'] as $x ) {
					$qx[ $x['category'] ] = $x['net'];
				}
				$rows = array( array( 'Sales (excluding VAT)', $n['gross_sales'], $q['gross_sales'] ) );
				if ( $n['returns'] || $q['returns'] ) {
					$rows[] = array( 'Less credit notes and returns', -$n['returns'], -$q['returns'] );
					$rows[] = array( 'Net sales', $n['sales'], $q['sales'], '_b' => 1 );
				}
				$rows[] = array( 'Cost of goods sold', -$n['cogs'], -$q['cogs'] );
				$rows[] = array( 'Gross profit', $n['gross'], $q['gross'], '_b' => 1 );
				if ( $n['other'] || $q['other'] ) {
					$rows[] = array( 'Other income', $n['other'], $q['other'] );
				}
				$seen = array();
				foreach ( $n['expenses'] as $x ) {
					$seen[ $x['category'] ] = 1;
					$rows[] = array( $x['category'], -1 * self::m( $x['net'] ), -1 * self::m( isset( $qx[ $x['category'] ] ) ? $qx[ $x['category'] ] : 0 ) );
				}
				foreach ( $qx as $c => $v ) {
					if ( ! isset( $seen[ $c ] ) ) {
						$rows[] = array( $c, 0, -1 * self::m( $v ) );
					}
				}
				$rows[] = array( 'Total operating expenses', -$n['opex'], -$q['opex'], '_b' => 1 );
				$rows[] = array( 'Net profit before tax', $n['net'], $q['net'], '_b' => 1 );
				$out['kpis'] = array( array( 'Sales excl. VAT', soko_money( $n['sales'] ) ), array( 'Gross profit', soko_money( $n['gross'] ) ), array( 'Gross margin', $n['margin'] . '%' ), array( 'Net profit', soko_money( $n['net'] ) ) );
				$out['sections'][] = array( 'title' => 'Profit and loss, compared with ' . date_i18n( 'j M', strtotime( $pf ) ) . ' to ' . date_i18n( 'j M Y', strtotime( $pt ) ), 'head' => array( 'Line', 'This period', 'Compared' ), 'rows' => $rows, 'widths' => array( 100, 40, 40 ) );
				break;

			case 'cashflow':
				// Built from every posting to Cash, M-Pesa and Bank, so it reconciles to the books.
				$money = Soko_Accounts::money_codes();
				$label = array(
					'payment'       => 'Received from customers',
					'expense'       => 'Paid for expenses',
					'supplier_pay'  => 'Paid to suppliers',
					'supplier_paid' => 'Paid to suppliers',
					'manual'        => 'Other entries',
					'opening'       => 'Opening balances',
				);
				$open_bal = 0;
				$weeks    = array();
				$cats     = array();
				$all      = Soko_Accounts::postings( $to );
				$grp      = array();
				foreach ( $all as $pp ) {
					$grp[ $pp[6] . ':' . $pp[7] ][ (string) $pp[1] ] = 1;
				}
				$section = function ( $pp ) use ( $grp ) {
					$codes = isset( $grp[ $pp[6] . ':' . $pp[7] ] ) ? $grp[ $pp[6] . ':' . $pp[7] ] : array();
					$in = $pp[2] - $pp[3] >= 0;
					if ( ! array_diff( array_map( 'strval', array_keys( $codes ) ), Soko_Accounts::money_codes() ) ) {
						return 'skip'; // moving money between your own accounts is not cash in or out
					}
					if ( isset( $codes['1500'] ) ) {
						return array( 'Investing', $in ? 'Equipment and vehicles sold' : 'Equipment and vehicles bought' );
					}
					if ( isset( $codes['2300'] ) ) {
						return array( 'Financing', $in ? 'Loans received' : 'Loans repaid' );
					}
					if ( isset( $codes['3000'] ) ) {
						return array( 'Financing', $in ? 'Capital put in by the owner' : 'Capital taken out' );
					}
					if ( isset( $codes['3100'] ) ) {
						return array( 'Financing', $in ? 'Drawings returned' : 'Owner drawings' );
					}
					if ( in_array( $pp[6], array( 'manual' ), true ) ) {
						return array( 'Operating', $in ? 'Other money in' : 'Charges and other payments' );
					}
					return null;
				};
				foreach ( $all as $pp ) {
					if ( ! in_array( (string) $pp[1], $money, true ) ) {
						continue;
					}
					$amt = $pp[2] - $pp[3];
					if ( $pp[0] < $from || 'opening' === $pp[6] && $pp[0] <= $from ) {
						$open_bal += $amt;
						continue;
					}
					$sx = $section( $pp );
					if ( 'skip' === $sx ) {
						continue;
					}
					$c  = $sx ? $sx[0] . '|' . $sx[1] : 'Operating|' . ( isset( $label[ $pp[6] ] ) ? $label[ $pp[6] ] : ( 'refund' === $pp[6] ? 'Refunds to customers' : 'Other' ) );
					if ( ! isset( $cats[ $c ] ) ) {
						$cats[ $c ] = array( 'in' => 0, 'out' => 0 );
					}
					$cats[ $c ][ $amt >= 0 ? 'in' : 'out' ] += abs( $amt );
					$w = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $pp[0] ) ) );
					if ( ! isset( $weeks[ $w ] ) ) {
						$weeks[ $w ] = array( 'in' => 0, 'out' => 0 );
					}
					$weeks[ $w ][ $amt >= 0 ? 'in' : 'out' ] += abs( $amt );
				}
				ksort( $weeks );
				$ti = array_sum( wp_list_pluck( $cats, 'in' ) );
				$to_ = array_sum( wp_list_pluck( $cats, 'out' ) );
				$sum = array( array( 'Cash, M-Pesa and bank at the start', self::m( $open_bal ), '_b' => 1 ) );
				foreach ( array( 'Operating' => 'From running the business', 'Investing' => 'From buying equipment and assets', 'Financing' => 'From loans and the owner' ) as $g => $gl ) {
					$gin = 0;
					$any = false;
					foreach ( $cats as $c => $v ) {
						list( $cg, $cl ) = explode( '|', $c, 2 );
						if ( $cg !== $g ) {
							continue;
						}
						$any = true;
						if ( $v['in'] ) {
							$sum[] = array( $cl, self::m( $v['in'] ) );
						}
						if ( $v['out'] ) {
							$sum[] = array( $cl, -self::m( $v['out'] ) );
						}
						$gin += $v['in'] - $v['out'];
					}
					if ( $any ) {
						$sum[] = array( 'Net cash ' . strtolower( $gl ), self::m( $gin ), '_b' => 1 );
					}
				}
				$sum[] = array( 'Net change', self::m( $ti - $to_ ), '_b' => 1 );
				$sum[] = array( 'Cash, M-Pesa and bank at the end', self::m( $open_bal + $ti - $to_ ), '_b' => 1 );
				$rows = array();
				foreach ( $weeks as $w => $v ) {
					$rows[] = array( 'Week of ' . date_i18n( 'j M', strtotime( $w ) ), self::m( $v['in'] ), self::m( $v['out'] ), self::m( $v['in'] - $v['out'] ) );
				}
				$rows[] = array( 'Total', self::m( $ti ), self::m( $to_ ), self::m( $ti - $to_ ), '_b' => 1 );
				$out['kpis'] = array( array( 'Money in', soko_money( $ti ) ), array( 'Money out', soko_money( $to_ ) ), array( 'Net cash', soko_money( $ti - $to_ ) ), array( 'Cash at the end', soko_money( $open_bal + $ti - $to_ ) ) );
				$out['sections'][] = array( 'title' => 'Where cash came from and went', 'head' => array( 'Line', 'KES' ), 'rows' => $sum, 'widths' => array( 130, 50 ) );
				$out['sections'][] = array( 'title' => 'Cash in and out by week', 'head' => array( 'Week', 'Money in', 'Money out', 'Net' ), 'rows' => $rows, 'widths' => array( 60, 40, 40, 40 ) );
				$out['chart'] = array_map( function ( $w, $v ) { return array( 'label' => date_i18n( 'j M', strtotime( $w ) ), 'in' => $v['in'], 'out' => $v['out'] ); }, array_keys( $weeks ), $weeks );
				break;

			case 'sales':
				$bycat = array();
				$byp   = array();
				$pidk  = array();
				$rate  = soko_setting( 'vat_registered' ) ? (float) soko_setting( 'vat_rate', 16 ) : 0;
				$incl  = (int) soko_setting( 'prices_incl_vat', 1 );
				foreach ( self::lines( $from, $to ) as $l ) {
					$cat = $l['cat'] ? soko_category( $l['cat'] )['name'] : 'Other items and services';
					$amt = $l['qty'] * $l['price'];
					$net = $l['vatable'] && $incl ? $amt * 100 / ( 100 + $rate ) : $amt; // before VAT
					$bycat[ $cat ] = ( isset( $bycat[ $cat ] ) ? $bycat[ $cat ] : 0 ) + $net;
					$k = $l['description'];
					if ( ! isset( $byp[ $k ] ) ) {
						$byp[ $k ] = array( 0, 0, 0 );
					}
					if ( $l['product_id'] ) {
						$pidk[ (int) $l['product_id'] ] = $k;
					}
					$byp[ $k ][0] += $l['qty'];
					$byp[ $k ][1] += $net;
					$byp[ $k ][2] += $net - $l['qty'] * $l['cost'];
				}
				// Items that came back on a credit note are not sold.
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT m.product_id, SUM(m.qty) q FROM ' . soko_t( 'moves' ) . " m JOIN $D c ON c.number = m.ref AND c.type = 'credit' AND c.status <> 'void' WHERE m.reason = 'Returned' AND c.date BETWEEN %s AND %s GROUP BY m.product_id", $from, $to ), ARRAY_A ) as $rq ) { // phpcs:ignore
					if ( isset( $pidk[ (int) $rq['product_id'] ] ) ) {
						$byp[ $pidk[ (int) $rq['product_id'] ] ][0] -= (float) $rq['q'];
					}
				}
				arsort( $bycat );
				uasort( $byp, function ( $a, $b ) { return $b[1] <=> $a[1]; } );
				$cn    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(subtotal) FROM $D WHERE type = 'credit' AND status <> 'void' AND date BETWEEN %s AND %s", $from, $to ) ); // phpcs:ignore
				$gross = array_sum( $bycat );
				$out['kpis'] = array( array( 'Net sales (before VAT)', soko_money( $gross - $cn ) ), array( 'Credit notes', soko_money( $cn ) ), array( 'Products and services sold', count( $byp ) ) );
				$cr = array_map( function ( $k, $v ) { return array( $k, round( $v ) ); }, array_keys( $bycat ), $bycat );
				if ( $cn > 0.005 ) {
					$cr[] = array( 'Less credit notes', -round( $cn ) );
				}
				$cr[] = array( 'Net sales (agrees with profit and loss)', round( $gross - $cn ), '_b' => 1 );
				$out['sections'][] = array( 'title' => 'By category (before VAT)', 'head' => array( 'Category', 'Sales' ), 'rows' => $cr, 'widths' => array( 130, 50 ) );
				$out['sections'][] = array( 'title' => 'By product or service (before VAT)', 'head' => array( 'Item', 'Qty', 'Sales', 'Margin' ), 'rows' => array_map( function ( $k, $v ) { return array( $k, $v[0] + 0, round( $v[1] ), round( $v[2] ) ); }, array_keys( $byp ), $byp ), 'widths' => array( 100, 20, 30, 30 ) );
				if ( ! Soko_API::sees_cost() ) {
					$last = count( $out['sections'] ) - 1;
					$out['sections'][ $last ]['head'] = array( 'Item', 'Qty', 'Sales' );
					$out['sections'][ $last ]['rows'] = array_map( function ( $r ) { return array_slice( $r, 0, 3 ); }, $out['sections'][ $last ]['rows'] );
				}
				$out['chart'] = array_map( function ( $k, $v ) { return array( 'label' => $k, 'value' => $v ); }, array_keys( $bycat ), $bycat );
				break;

			case 'customers':
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT c.name, COUNT(d.id) n, SUM(d.total) t, SUM(d.paid) p FROM $D d JOIN $C c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.status <> 'void' AND " . Soko_Accounts::confirmed_sql( 'd' ) . " AND d.date BETWEEN %s AND %s GROUP BY c.id ORDER BY t DESC", $from, $to ), ARRAY_A ); // phpcs:ignore
				$out['sections'][] = array( 'title' => 'Sales by customer', 'head' => array( 'Customer', 'Invoices', 'Invoiced', 'Paid or credited', 'Owing' ), 'rows' => array_map( function ( $r ) { return array( $r['name'], (int) $r['n'], round( $r['t'] ), round( $r['p'] ), round( $r['t'] - $r['p'] ) ); }, $rows ), 'widths' => array( 70, 20, 30, 30, 30 ) );
				$out['kpis'] = array( array( 'Customers', count( $rows ) ), array( 'Invoiced', soko_money( array_sum( wp_list_pluck( $rows, 't' ) ) ) ) );
				break;

			case 'receivables':
				$out['period'] = 'As at ' . date_i18n( 'j M Y', strtotime( $to ) );
				$pa   = Soko_Accounts::paid_asat( $to );
				$all  = $wpdb->get_results( "SELECT d.id, d.number, d.date, d.due, d.total, c.name, c.phone FROM $D d LEFT JOIN $C c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.status <> 'void' AND " . Soko_Accounts::confirmed_sql( 'd' ) . ' ORDER BY d.due', ARRAY_A ); // phpcs:ignore
				$b    = array( 'Not yet due' => 0, '1 to 30 days late' => 0, '31 to 60 days late' => 0, '61 to 90 days late' => 0, 'Over 90 days late' => 0 );
				$rows = array();
				$cr   = 0;
				foreach ( $all as $o ) {
					if ( $o['date'] > $to ) {
						$cr += isset( $pa[ $o['id'] ] ) ? $pa[ $o['id'] ] : 0; // paid before the invoice date: an advance
						continue;
					}
					$bal = round( (float) $o['total'] - ( isset( $pa[ $o['id'] ] ) ? $pa[ $o['id'] ] : 0 ), 2 );
					if ( $bal < -0.005 ) {
						$cr -= $bal;
						continue;
					}
					if ( $bal <= 0.5 ) {
						continue;
					}
					$late = $o['due'] ? floor( ( strtotime( $to ) - strtotime( $o['due'] ) ) / DAY_IN_SECONDS ) : 0;
					$k    = $late <= 0 ? 'Not yet due' : ( $late <= 30 ? '1 to 30 days late' : ( $late <= 60 ? '31 to 60 days late' : ( $late <= 90 ? '61 to 90 days late' : 'Over 90 days late' ) ) );
					$b[ $k ] += $bal;
					$rows[] = array( $o['number'], $o['name'], soko_phone_display( $o['phone'] ), $o['due'], max( 0, (int) $late ), round( $bal ) );
				}
				$out['kpis'] = array( array( 'Total owed', soko_money( array_sum( $b ) ) ), array( 'Overdue', soko_money( array_sum( $b ) - $b['Not yet due'] ) ), array( 'Open invoices', count( $rows ) ) );
				$out['sections'][] = array( 'title' => 'Ageing', 'head' => array( 'Bucket', 'KES' ), 'rows' => array_map( function ( $k, $v ) { return array( $k, round( $v ) ); }, array_keys( $b ), $b ), 'widths' => array( 130, 50 ) );
				$out['sections'][] = array( 'title' => 'Open invoices', 'head' => array( 'Invoice', 'Customer', 'Phone', 'Due', 'Days late', 'Balance' ), 'rows' => $rows, 'widths' => array( 25, 55, 30, 25, 20, 25 ) );
				$book = Soko_Accounts::balances( $to );
				$book = isset( $book['1100'] ) ? round( $book['1100'], 2 ) : 0;
				$owed = round( array_sum( $b ), 2 );
				$rec  = array( array( 'Owed on open invoices', $owed ) );
				if ( $cr > 0.005 ) {
					$rec[] = array( 'Less customer credit balances (overpayments)', -round( $cr, 2 ) );
				}
				$diff = round( $book - ( $owed - $cr ), 2 );
				if ( abs( $diff ) > 0.5 ) {
					$rec[] = array( 'Opening balances and other entries', $diff );
				}
				$rec[] = array( 'Accounts receivable in the books', $book, '_b' => 1 );
				$out['sections'][] = array( 'title' => 'Agrees with the books', 'head' => array( 'Line', 'KES' ), 'rows' => $rec, 'widths' => array( 130, 50 ) );
				$pend = (float) $wpdb->get_var( "SELECT SUM(d.total - d.paid) FROM $D d WHERE d.type = 'invoice' AND d.status <> 'void' AND d.paid < d.total AND NOT " . Soko_Accounts::confirmed_sql( 'd' ) ); // phpcs:ignore
				if ( $pend > 0.5 ) {
					$out['sections'][] = array( 'title' => 'Not included', 'head' => array( 'Item', 'KES' ), 'rows' => array( array( 'Website orders waiting for confirmation (they count once paid or packed)', round( $pend, 2 ) ) ), 'widths' => array( 130, 50 ) );
				}
				break;

			case 'vat':
				$n   = self::pl_numbers( $from, $to );
				$ein = Soko_Accounts::sum( '1300', $from, $to, array( 'expense' ) );
				$pin = Soko_Accounts::sum( '1300', $from, $to, array( 'bill' ) );
				$net = $n['vat'] - $ein - $pin;
				$out['kpis'] = array( array( 'Output VAT', soko_money( $n['vat'] ) ), array( 'Input VAT', soko_money( $ein + $pin ) ), array( $net >= 0 ? 'VAT payable' : 'VAT credit', soko_money( abs( $net ) ) ) );
				$out['sections'][] = array( 'title' => 'VAT position', 'head' => array( 'Line', 'KES' ), 'rows' => array( array( 'Output VAT on sales', $n['vat'] ), array( 'Input VAT on purchases', -round( $pin, 2 ) ), array( 'Input VAT on expenses', -round( $ein, 2 ) ), array( $net >= 0 ? 'Payable to KRA' : 'Credit carried forward', round( $net, 2 ), '_b' => 1 ) ), 'widths' => array( 130, 50 ) );
				$inv = $wpdb->get_results( $wpdb->prepare( "SELECT d.number, d.date, c.name, c.kra, d.subtotal, d.vat, d.total, d.etims_ref FROM $D d LEFT JOIN $C c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.status <> 'void' AND " . Soko_Accounts::confirmed_sql( 'd' ) . " AND d.date BETWEEN %s AND %s ORDER BY d.date", $from, $to ), ARRAY_A ); // phpcs:ignore
				$out['sections'][] = array( 'title' => 'Sales invoices', 'head' => array( 'Invoice', 'Date', 'Customer', 'KRA PIN', 'Net', 'VAT', 'eTIMS' ), 'rows' => array_merge( array_map( function ( $r ) { return array( $r['number'], $r['date'], $r['name'], $r['kra'], round( $r['subtotal'], 2 ), round( $r['vat'], 2 ), $r['etims_ref'] ); }, $inv ), array( array( 'Total', '', '', '', round( array_sum( wp_list_pluck( $inv, 'subtotal' ) ), 2 ), round( array_sum( wp_list_pluck( $inv, 'vat' ) ), 2 ), '', '_b' => 1 ) ) ), 'widths' => array( 22, 22, 45, 26, 22, 18, 25 ) );
				$cn = $wpdb->get_results( $wpdb->prepare( "SELECT d.number, d.date, c.name, d.subtotal, d.vat FROM $D d LEFT JOIN $C c ON c.id = d.customer_id WHERE d.type = 'credit' AND d.status <> 'void' AND d.date BETWEEN %s AND %s ORDER BY d.date", $from, $to ), ARRAY_A ); // phpcs:ignore
				if ( $cn ) {
					$out['sections'][] = array( 'title' => 'Credit notes (reduce output VAT)', 'head' => array( 'Credit note', 'Date', 'Customer', 'Net', 'VAT' ), 'rows' => array_map( function ( $r ) { return array( $r['number'], $r['date'], $r['name'], -round( $r['subtotal'], 2 ), -round( $r['vat'], 2 ) ); }, $cn ) );
				}
				$iv  = array();
				$pos = array();
				foreach ( $wpdb->get_results( 'SELECT p.id, p.supplier_inv, s.name, s.kra FROM ' . soko_t( 'purchases' ) . ' p LEFT JOIN ' . soko_t( 'suppliers' ) . ' s ON s.id = p.supplier_id', ARRAY_A ) as $r ) { // phpcs:ignore
					$pos[ $r['id'] ] = $r;
				}
				$exs = array();
				foreach ( $wpdb->get_results( 'SELECT id, supplier, supplier_pin, reference FROM ' . soko_t( 'expenses' ), ARRAY_A ) as $r ) { // phpcs:ignore
					$exs[ $r['id'] ] = $r;
				}
				foreach ( Soko_Accounts::postings( $to ) as $pp ) {
					if ( '1300' === (string) $pp[1] && $pp[0] >= $from && in_array( $pp[6], array( 'expense', 'bill' ), true ) && $pp[2] > 0 ) {
						if ( 'bill' === $pp[6] ) {
							$x = isset( $pos[ $pp[7] ] ) ? $pos[ $pp[7] ] : array();
							$iv[] = array( $pp[0], isset( $x['name'] ) ? $x['name'] : '', isset( $x['kra'] ) ? $x['kra'] : '', ! empty( $x['supplier_inv'] ) ? $x['supplier_inv'] : $pp[5], round( $pp[2], 2 ) );
						} else {
							$x = isset( $exs[ $pp[7] ] ) ? $exs[ $pp[7] ] : array();
							$iv[] = array( $pp[0], ! empty( $x['supplier'] ) ? $x['supplier'] : $pp[4], isset( $x['supplier_pin'] ) ? $x['supplier_pin'] : '', $pp[5], round( $pp[2], 2 ) );
						}
					}
				}
				if ( $iv ) {
					$miss = count( array_filter( $iv, function ( $r ) { return '' === (string) $r[2]; } ) );
					$iv[] = array( 'Total', '', '', '', round( array_sum( array_column( $iv, 4 ) ), 2 ), '_b' => 1 );
					$out['sections'][] = array( 'title' => 'Input VAT claimed', 'head' => array( 'Date', 'Supplier', 'Supplier KRA PIN', 'Supplier invoice', 'VAT' ), 'rows' => $iv );
					if ( $miss ) {
						$out['kpis'][] = array( 'Missing supplier PIN', $miss . ' item' . ( 1 === $miss ? '' : 's' ) );
					}
				}
				break;

			case 'stock':
				$out['period'] = 'As at ' . date_i18n( 'j M Y', strtotime( $to ) );
				// Quantity at the date = movements up to then, plus stock loaded with no movement record once it is in the books.
				$upto = array();
				$tot  = array();
				$MV   = soko_t( 'moves' );
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT product_id, SUM(CASE WHEN created <= %s THEN qty ELSE 0 END) q, SUM(qty) t, MIN(created) f FROM $MV GROUP BY product_id", $to . ' 23:59:59' ), ARRAY_A ) as $r ) { // phpcs:ignore
					$upto[ $r['product_id'] ] = array( (float) $r['q'], (float) $r['t'], $r['f'] );
				}
				$rows = array();
				$tv   = 0;
				foreach ( Soko_Ledger::products() as $p ) {
					if ( 'product' !== $p['kind'] ) {
						continue;
					}
					$u   = isset( $upto[ $p['id'] ] ) ? $upto[ $p['id'] ] : array( 0, 0, null );
					$gap = (float) $p['stock'] - $u[1];
					$gd  = substr( $u[2] ? $u[2] : ( ! empty( $p['created'] ) ? $p['created'] : soko_today() ), 0, 10 );
					$q   = $u[0] + ( $gd <= $to ? $gap : 0 );
					$v   = $q * $p['cost'];
					$tv += $v;
					$rows[] = array( $p['sku'], $p['name'], $q + 0, round( $p['cost'] ), round( $v ), $q <= $p['reorder'] ? 'Reorder' : '' );
				}
				$bk = Soko_Accounts::balances( $to );
				$bk = isset( $bk['1200'] ) ? round( $bk['1200'], 2 ) : 0;
				$out['kpis'] = array( array( 'Stock value at cost', soko_money( $tv ) ), array( 'Products', count( $rows ) ), array( 'To reorder', count( array_filter( $rows, function ( $r ) { return 'Reorder' === $r[5]; } ) ) ) );
				$out['sections'][] = array( 'title' => 'Stock on hand', 'head' => array( 'Code', 'Product', 'Qty', 'Unit cost', 'Value', 'Status' ), 'rows' => $rows, 'widths' => array( 24, 70, 16, 25, 27, 18 ) );
				$sr = array( array( 'Stock on hand at average cost', round( $tv, 2 ) ) );
				$sd = round( $bk - $tv, 2 );
				if ( abs( $sd ) > 0.5 ) {
					$sr[] = array( 'Sold but not yet delivered, and earlier cost changes', $sd );
				}
				$sr[] = array( 'Stock in the books', $bk, '_b' => 1 );
				$out['sections'][] = array( 'title' => 'Agrees with the books', 'head' => array( 'Line', 'KES' ), 'rows' => $sr, 'widths' => array( 130, 50 ) );
				break;

			case 'payments':
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.*, d.number FROM $P p LEFT JOIN $D d ON d.id = p.doc_id WHERE p.received BETWEEN %s AND %s ORDER BY p.received DESC", $from . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
				$out['kpis'] = array( array( 'Received', soko_money( array_sum( wp_list_pluck( $rows, 'amount' ) ) ) ), array( 'Payments', count( $rows ) ), array( 'Unmatched', count( array_filter( $rows, function ( $r ) { return 'matched' !== $r['status']; } ) ) ) );
				$out['sections'][] = array( 'title' => 'Payments', 'head' => array( 'Date', 'Code', 'Method', 'From', 'Invoice', 'Amount' ), 'rows' => array_map( function ( $r ) { return array( substr( $r['received'], 0, 16 ), $r['code'], 'mpesa' === $r['method'] ? 'M-Pesa' : ucfirst( $r['method'] ), $r['payer'], $r['number'] ? $r['number'] : 'Unmatched', round( $r['amount'] ) ); }, $rows ), 'widths' => array( 30, 28, 20, 50, 25, 27 ) );
				break;

			case 'expenses':
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $E WHERE date BETWEEN %s AND %s ORDER BY date DESC", $from, $to ), ARRAY_A ); // phpcs:ignore
				$by    = array();
				$asset = 0;
				$vat   = 0;
				foreach ( $rows as $r ) {
					$vat += (float) $r['vat'];
					if ( preg_match( '/\(kept as an asset\)/i', $r['category'] ) ) {
						$asset += $r['amount'] - $r['vat'];
						continue;
					}
					$by[ $r['category'] ] = ( isset( $by[ $r['category'] ] ) ? $by[ $r['category'] ] : 0 ) + $r['amount'] - $r['vat'];
				}
				arsort( $by );
				$run = array_sum( $by );
				$out['kpis'] = array( array( 'Running costs (before VAT)', soko_money( $run ) ), array( 'Equipment bought', soko_money( $asset ) ), array( 'VAT to claim', soko_money( $vat ) ), array( 'Entries', count( $rows ) ) );
				$cat = array_map( function ( $k, $v ) { return array( $k, round( $v, 2 ) ); }, array_keys( $by ), $by );
				$cat[] = array( 'Running costs from expenses', round( $run, 2 ), '_b' => 1 );
				$out['sections'][] = array( 'title' => 'By category (before VAT)', 'head' => array( 'Category', 'KES' ), 'rows' => $cat, 'widths' => array( 130, 50 ) );
				// How this ties to the profit and loss, which also includes journals and stock write-offs.
				$pl  = self::pl_numbers( $from, $to );
				$oth = round( $pl['opex'] - $run, 2 );
				$tie = array( array( 'Running costs from expenses', round( $run, 2 ) ) );
				if ( abs( $oth ) > 0.5 ) {
					$tie[] = array( 'Journals, stock write-offs and other entries', $oth );
				}
				$tie[] = array( 'Operating expenses in the profit and loss', round( $pl['opex'], 2 ), '_b' => 1 );
				$out['sections'][] = array( 'title' => 'Agrees with the profit and loss', 'head' => array( 'Line', 'KES' ), 'rows' => $tie, 'widths' => array( 130, 50 ) );
				$list   = array_map( function ( $r ) { return array( $r['date'], $r['category'], $r['description'] . ( $r['supplier'] ? ' (' . $r['supplier'] . ')' : '' ), $r['method'], round( $r['amount'] - $r['vat'], 2 ), round( $r['vat'], 2 ), round( $r['amount'], 2 ) ); }, $rows );
				$list[] = array( 'Total', '', '', '', round( array_sum( wp_list_pluck( $rows, 'amount' ) ) - $vat, 2 ), round( $vat, 2 ), round( array_sum( wp_list_pluck( $rows, 'amount' ) ), 2 ), '_b' => 1 );
				$out['sections'][] = array( 'title' => 'All expenses', 'head' => array( 'Date', 'Category', 'Description', 'Paid by', 'Before VAT', 'VAT', 'Total' ), 'rows' => $list, 'widths' => array( 20, 32, 56, 20, 20, 14, 20 ) );
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
				// Customers who overpaid: shown as money owed back, not netted inside receivables.
				$cr = 0;
				$pa = Soko_Accounts::paid_asat( $to );
				foreach ( $wpdb->get_results( "SELECT id, date, total FROM $D d WHERE type = 'invoice' AND status <> 'void' AND " . Soko_Accounts::confirmed_sql( 'd' ), ARRAY_A ) as $iv ) { // phpcs:ignore
					$pd = isset( $pa[ $iv['id'] ] ) ? $pa[ $iv['id'] ] : 0;
					$cr += $iv['date'] > $to ? $pd : max( 0, $pd - (float) $iv['total'] );
				}
				// Suppliers paid before their goods arrived: money owed to us in goods, not a smaller bill.
				$pre = 0;
				$pp  = Soko_Accounts::po_paid_asat( $to );
				foreach ( $wpdb->get_results( 'SELECT number, status, received FROM ' . soko_t( 'purchases' ), ARRAY_A ) as $po ) { // phpcs:ignore
					if ( ( 'received' !== $po['status'] || substr( (string) $po['received'], 0, 10 ) > $to ) && ! empty( $pp[ $po['number'] ] ) ) {
						$pre += $pp[ $po['number'] ];
					}
				}
				if ( $pre > 0.005 ) {
					foreach ( $liabs as &$lr ) {
						if ( 0 === strpos( $lr[0], '2000' ) ) {
							$lr[1] = round( $lr[1] + $pre, 2 );
						}
					}
					unset( $lr );
					if ( ! array_filter( $liabs, function ( $r ) { return 0 === strpos( $r[0], '2000' ); } ) ) {
						$liabs[] = array( '2000  Accounts payable (suppliers)', round( $pre, 2 ) );
					}
					$tl      += $pre;
					$assets[] = array( 'Paid to suppliers in advance', round( $pre, 2 ) );
					$ta      += $pre;
				}
				if ( $cr > 0.005 ) {
					$has = false;
					foreach ( $assets as &$ar ) {
						if ( 0 === strpos( $ar[0], '1100' ) ) {
							$ar[1] = round( $ar[1] + $cr, 2 );
							$has   = true;
						}
					}
					unset( $ar );
					if ( ! $has ) {
						$assets[] = array( '1100  ' . $chart['1100']['name'], round( $cr, 2 ) );
					}
					$ta     += $cr;
					$liabs[] = array( 'Customer credit balances (overpayments)', round( $cr, 2 ) );
					$tl     += $cr;
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
				foreach ( $open as $code => $v ) {
					if ( abs( $v ) > 0.005 && ! isset( $by[ $code ] ) ) {
						$by[ $code ] = array(); // no entries this period, but it still carries a balance
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
				$ppaid = Soko_Accounts::po_paid_asat( $to );
				$pre   = 0;
				foreach ( $wpdb->get_results( 'SELECT p.number, p.date, p.received, p.status, p.total, s.name FROM ' . $PU . ' p LEFT JOIN ' . soko_t( 'suppliers' ) . " s ON s.id = p.supplier_id ORDER BY p.received", ARRAY_A ) as $r ) { // phpcs:ignore
					$paid = isset( $ppaid[ $r['number'] ] ) ? $ppaid[ $r['number'] ] : 0;
					$d    = substr( $r['received'] ? $r['received'] : $r['date'], 0, 10 );
					if ( 'received' !== $r['status'] || $d > $to ) {
						$pre += $paid; // paid in advance, goods not yet in by this date
						continue;
					}
					$owe = round( $r['total'] - $paid, 2 );
					if ( $owe <= 0.5 ) {
						continue;
					}
					$days = max( 0, (int) floor( ( strtotime( $to ) - strtotime( $d ) ) / DAY_IN_SECONDS ) );
					$bk   = $days <= 30 ? 0 : ( $days <= 60 ? 1 : ( $days <= 90 ? 2 : 3 ) );
					$tot[ $bk ] += $owe;
					$cells = array( '', '', '', '' );
					$cells[ $bk ] = $owe;
					$rows[] = array_merge( array( $r['name'] ? $r['name'] : '—', $r['number'], $d ), $cells );
				}
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, date, supplier, category, amount, method FROM ' . soko_t( 'expenses' ) . ' WHERE date <= %s', $to ), ARRAY_A ) as $e ) { // phpcs:ignore
					if ( ! Soko_Accounts::is_unpaid_method( $e['method'] ) ) {
						continue;
					}
					$owe = round( (float) $e['amount'] - ( isset( $ppaid[ 'EXP-' . $e['id'] ] ) ? $ppaid[ 'EXP-' . $e['id'] ] : 0 ), 2 );
					if ( $owe <= 0.5 ) {
						continue;
					}
					$days = max( 0, (int) floor( ( strtotime( $to ) - strtotime( $e['date'] ) ) / DAY_IN_SECONDS ) );
					$bk   = $days <= 30 ? 0 : ( $days <= 60 ? 1 : ( $days <= 90 ? 2 : 3 ) );
					$tot[ $bk ] += $owe;
					$cells = array( '', '', '', '' );
					$cells[ $bk ] = $owe;
					$rows[] = array_merge( array( $e['supplier'] ? $e['supplier'] : '—', 'Bill: ' . $e['category'], $e['date'] ), $cells );
				}
				$rows[] = array_merge( array( 'Total', '', '' ), array_map( function ( $x ) { return round( $x, 2 ); }, $tot ), array( '_b' => 1 ) );
				$bk   = Soko_Accounts::balances( $to );
				$bk   = isset( $bk['2000'] ) ? -round( $bk['2000'], 2 ) : 0;
				$bills = round( array_sum( $tot ), 2 );
				$prec = array( array( 'Unpaid supplier bills', $bills ) );
				if ( $pre > 0.005 ) {
					$prec[] = array( 'Less paid in advance on orders not yet received', -round( $pre, 2 ) );
				}
				$pd = round( $bk - ( $bills - $pre ), 2 );
				if ( abs( $pd ) > 0.5 ) {
					$prec[] = array( 'Opening balances and other entries', $pd );
				}
				$prec[] = array( 'Accounts payable in the books', $bk, '_b' => 1 );
				$out['kpis'] = array( array( 'Owed to suppliers', soko_money( array_sum( $tot ) ) ), array( 'Over 60 days', soko_money( $tot[2] + $tot[3] ) ) );
				$out['sections'][] = array( 'title' => 'Unpaid supplier bills', 'head' => array( 'Supplier', 'Order or bill', 'Date', '0–30 days', '31–60', '61–90', 'Over 90' ), 'rows' => $rows );
				$out['sections'][] = array( 'title' => 'Agrees with the books', 'head' => array( 'Line', 'KES' ), 'rows' => $prec, 'widths' => array( 130, 50 ) );
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
		// Net sales before VAT, after credit notes: the same figure as the profit and loss.
		$sales  = (float) self::pl_numbers( $m0, $m1 )['sales'];
		$psales = (float) self::pl_numbers( $pm0, $pm1 )['sales'];
		$coll   = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(amount) a, COUNT(*) n FROM $P WHERE status = 'matched' AND received BETWEEN %s AND %s", $m0 . ' 00:00:00', $m1 . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
		$owed   = (float) $wpdb->get_var( "SELECT SUM(total - paid) FROM $D WHERE type='invoice' AND status <> 'void' AND paid < total AND " . Soko_Accounts::confirmed_sql( $D ) ); // phpcs:ignore
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
		// Money in and out of cash, M-Pesa and bank, from the books (so supplier payments count when paid).
		$money = Soko_Accounts::money_codes();
		$start = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( soko_today() . ' -11 weeks' ) ) );
		$wk    = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$ws        = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( soko_today() . " -$i weeks" ) ) );
			$wk[ $ws ] = array( 'label' => date_i18n( 'j M', strtotime( $ws ) ), 'in' => 0, 'out' => 0 );
		}
		foreach ( Soko_Accounts::postings( soko_today() ) as $pp ) {
			if ( $pp[0] < $start || ! in_array( (string) $pp[1], $money, true ) || 'transfer' === $pp[6] ) {
				continue;
			}
			$ws = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $pp[0] ) ) );
			if ( isset( $wk[ $ws ] ) ) {
				$wk[ $ws ]['in']  += (float) $pp[2];
				$wk[ $ws ]['out'] += (float) $pp[3];
			}
		}
		$weeks = array_values( $wk );
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
