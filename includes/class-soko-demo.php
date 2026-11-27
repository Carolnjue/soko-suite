<?php
/**
 * Sample data so the Desk can be shown working before real records exist.
 * Every sample row is flagged and can be removed in one click.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Demo {

	public static function load() {
		global $wpdb;
		$GLOBALS['soko_no_mail'] = true;
		self::remove();
		update_option( 'soko_demo_numbers', array_intersect_key( Soko_Settings::all(), array_flip( array( 'next_quote', 'next_invoice', 'next_receipt', 'next_po' ) ) ), false );
		$now = strtotime( soko_today() );
		$day = function ( $n ) use ( $now ) {
			return gmdate( 'Y-m-d', $now - $n * DAY_IN_SECONDS );
		};
		$cust = array(
			array( 'Hoho Farm', 'Business', '0722410118', 'Kisumu', 'P051234567X' ),
			array( 'Lukundo Bakery', 'Business', '0733902441', 'Taita Taveta', 'P052119034K' ),
			array( 'Grace Wanjiru', 'Home', '0712555019', 'Kiambu', '' ),
			array( 'Peter Otieno', 'Home', '0701555284', 'Nairobi', '' ),
			array( 'Bunyore Girls High School', 'Institution', '0720555731', 'Vihiga', 'P051998271A' ),
			array( 'Faith Mutua', 'Home', '0791555640', 'Machakos', '' ),
			array( 'Kilimani Dental Clinic', 'Business', '0722555102', 'Nairobi', 'P000611432M' ),
			array( 'Ahmed Hassan', 'Home', '0710555377', 'Mombasa', '' ),
			array( 'Baraka Farm', 'Business', '0724555916', 'Machakos', 'P051776510Q' ),
			array( 'Brian Kiprop', 'Home', '0745555208', 'Uasin Gishu', '' ),
		);
		$cid = array();
		foreach ( $cust as $c ) {
			$wpdb->insert( soko_t( 'customers' ), array( 'name' => $c[0], 'type' => $c[1], 'phone' => soko_phone( $c[2] ), 'county' => $c[3], 'kra' => $c[4], 'demo' => 1, 'created' => soko_now() ) );
			$cid[] = (int) $wpdb->insert_id;
		}
		$P = function ( $sku ) {
			$p = Soko_Ledger::product_by_sku( $sku );
			return $p ? $p['id'] : 0;
		};
		$inv = array(
			array( 0, 2, array( array( 'KIT-10', 1 ), array( 'Installation and commissioning', 1, 70000 ) ), 0 ),
			array( 2, 3, array( array( 'KIT-3', 1 ), array( 'Installation', 1, 21000 ) ), 0 ),
			array( 6, 5, array( array( 'INV-V5', 1 ), array( 'LI-10', 1 ), array( 'Installation', 1, 35000 ) ), 1 ),
			array( 4, 9, array( array( 'PV-585', 12 ), array( 'LI-10', 1 ), array( 'Phase 2 installation', 1, 60000 ) ), 200000 ),
			array( 3, 18, array( array( 'SWH-200', 1 ), array( 'Installation', 1, 9000 ) ), 0 ),
			array( 8, 13, array( array( 'PMP-HY', 1 ), array( 'PV-585', 8 ), array( 'Borehole solarisation works', 1, 48000 ) ), 1 ),
			array( 1, 20, array( array( 'INV-H5', 1 ), array( 'LI-5', 1 ), array( 'PV-585', 6 ) ), 0 ),
			array( 5, 22, array( array( 'PS-1', 1 ) ), 1 ),
			array( 7, 26, array( array( 'FL-200', 4 ), array( 'CCTV-4G', 2 ) ), 1 ),
			array( 9, 33, array( array( 'KIT-5', 1 ), array( 'Installation', 1, 35000 ) ), 1 ),
			array( 2, 41, array( array( 'GEL-200', 2 ), array( 'FL-100', 2 ) ), 1 ),
			array( 6, 47, array( array( 'GTI-10', 1 ), array( 'PV-585', 16 ), array( 'Installation', 1, 40000 ) ), 1 ),
		);
		$names = array( 'TKR4H2LQ9P', 'TKQ8C1MV3B', 'TKP2ZX0NDE', 'TKM3K9PJ5S', 'TKL7D2QW8E', 'TKJ5N1BC4R', 'TKH8V6XM2T', 'TKG3Y9ZA1U' );
		$ni    = 0;
		foreach ( $inv as $i ) {
			$lines = array();
			foreach ( $i[2] as $l ) {
				$lines[] = isset( $l[2] ) ? array( 'description' => $l[0], 'qty' => $l[1], 'price' => $l[2], 'cost' => $l[2] * 0.35 ) : array( 'product_id' => $P( $l[0] ), 'qty' => $l[1] );
			}
			$d = Soko_Ledger::save_doc( array( 'type' => 'invoice', 'customer_id' => $cid[ $i[0] ], 'lines' => $lines, 'date' => $day( $i[1] ), 'demo' => 1 ) );
			$wpdb->update( soko_t( 'docs' ), array( 'etims_status' => 'signed', 'etims_ref' => 'KRACU03000' . substr( $d['number'], -4 ) ), array( 'id' => $d['id'] ) );
			if ( $i[3] ) {
				$amt = 1 === $i[3] ? $d['total'] : $i[3];
				$c   = Soko_Ledger::customer( $cid[ $i[0] ] );
				Soko_Ledger::record_payment( array( 'method' => 'mpesa', 'code' => $names[ $ni++ % 8 ] . $ni, 'amount' => $amt, 'phone' => $c['phone'], 'payer' => strtoupper( $c['name'] ), 'account_ref' => $d['number'], 'received' => $day( max( 0, $i[1] - 2 ) ) . ' 10:' . str_pad( (string) ( 10 + $ni ), 2, '0' ) . ':00', 'demo' => 1 ) );
			}
		}
		Soko_Ledger::record_payment( array( 'method' => 'mpesa', 'code' => 'TKN6T7RW1A', 'amount' => 12000, 'phone' => '0716555904', 'payer' => 'JOHN KAMAU', 'account_ref' => 'SOLAR', 'received' => $day( 1 ) . ' 18:30:00', 'demo' => 1 ) );
		$wpdb->query( 'UPDATE ' . soko_t( 'docs' ) . ' SET demo = 1 WHERE demo = 0 AND id IN (SELECT doc_id FROM ' . soko_t( 'payments' ) . ' WHERE demo = 1)' ); // phpcs:ignore
		$wpdb->query( 'UPDATE ' . soko_t( 'docs' ) . " SET demo = 1 WHERE type = 'receipt' AND parent_id IN (SELECT id FROM (SELECT id FROM " . soko_t( 'docs' ) . ' WHERE demo = 1) x)' ); // phpcs:ignore

		foreach ( array( array( 5, array( array( 'KIT-5', 1 ), array( 'Installation', 1, 35000 ) ), 'sent' ), array( 3, array( array( 'LI-5', 1 ), array( 'INV-H5', 1 ) ), 'draft' ), array( 7, array( array( 'SWH-300', 1 ), array( 'Installation', 1, 12000 ) ), 'sent' ), array( 4, array( array( 'ST-100', 10 ), array( 'Pole supply and fixing', 10, 9500 ) ), 'sent' ) ) as $k => $q ) {
			$lines = array();
			foreach ( $q[1] as $l ) {
				$lines[] = isset( $l[2] ) ? array( 'description' => $l[0], 'qty' => $l[1], 'price' => $l[2] ) : array( 'product_id' => $P( $l[0] ), 'qty' => $l[1] );
			}
			Soko_Ledger::save_doc( array( 'type' => 'quote', 'customer_id' => $cid[ $q[0] ], 'lines' => $lines, 'date' => $day( 1 + $k * 3 ), 'status' => $q[2], 'demo' => 1 ) );
		}

		$leads = array(
			array( 'Mercy Achieng', 'New', 'Solar calculator', 185000, '7.2 kWh a day, 5 kW inverter suggested', '0722555411' ),
			array( 'Kilimani Dental Clinic', 'Contacted', 'Quote form', 420000, 'Backup for X-ray and vaccine fridge', '0722555102' ),
			array( 'Samuel Njoroge', 'New', 'WhatsApp', 98000, '200 L water heater', '0733555876' ),
			array( 'Greenleaf Academy', 'Survey booked', 'Referral', 960000, '16 kW hybrid for three blocks', '0711555320' ),
			array( 'Faith Mutua', 'Quoted', 'Website', 430000, '5 kW hybrid home kit', '0791555640' ),
			array( 'Ahmed Hassan', 'Quoted', 'Walk-in', 147000, '300 L water heater', '0710555377' ),
			array( 'Grace Wanjiru', 'Won', 'Solar calculator', 206000, '3 kW home kit', '0712555019' ),
		);
		foreach ( $leads as $l ) {
			$wpdb->insert( soko_t( 'leads' ), array( 'name' => $l[0], 'stage' => $l[1], 'source' => $l[2], 'value' => $l[3], 'interest' => $l[4], 'phone' => soko_phone( $l[5] ), 'demo' => 1, 'created' => soko_now(), 'updated' => soko_now() ) );
		}
		$jobs = array(
			array( 0, '10 kW commercial hybrid', 'Installing', 'Kevin O. + 2', 1, 'Kisumu' ),
			array( 2, '3 kW home kit', 'Scheduled', 'Dennis M.', -5, 'Ruiru, Kiambu' ),
			array( 6, 'Load audit and site survey', 'Survey', 'Eng. Wairimu', -2, 'Kilimani, Nairobi' ),
			array( 3, '200 L water heater', 'Scheduled', 'Dennis M.', -4, 'Lavington, Nairobi' ),
			array( 4, 'Phase 2, 12 panels', 'Commissioned', 'Kevin O. + 3', 7, 'Vihiga' ),
			array( 8, 'Borehole pump service', 'Maintenance', 'Brian K.', -7, 'Kangundo' ),
		);
		foreach ( $jobs as $j ) {
			$wpdb->insert( soko_t( 'jobs' ), array( 'customer_id' => $cid[ $j[0] ], 'title' => $j[1], 'stage' => $j[2], 'techs' => $j[3], 'date' => $day( $j[4] ), 'location' => $j[5], 'checklist' => 'Commissioned' === $j[2] ? wp_json_encode( soko_setting( 'checklist' ) ) : '[]', 'demo' => 1, 'created' => soko_now(), 'updated' => soko_now() ) );
		}
		$events = array(
			array( 0, '08:30', 'Hoho Farm: inverter and battery install', 'Install', 'Kevin O.', 'Kisumu' ),
			array( 0, '14:00', 'Call Kilimani Dental Clinic about the load audit', 'Call', 'Eng. Wairimu', '' ),
			array( -1, '09:00', 'Hoho Farm: commissioning and handover', 'Install', 'Kevin O.', 'Kisumu' ),
			array( -2, '10:00', 'Greenleaf Academy site survey', 'Survey', 'Eng. Wairimu', 'Ruiru' ),
			array( -4, '09:30', 'Peter Otieno: water heater install', 'Install', 'Dennis M.', 'Lavington' ),
			array( -5, '09:00', 'Grace Wanjiru: 3 kW home kit', 'Install', 'Dennis M.', 'Ruiru' ),
			array( -7, '08:00', 'Baraka Farm: pump service', 'Maintenance', 'Brian K.', 'Kangundo' ),
		);
		foreach ( $events as $e ) {
			$wpdb->insert( soko_t( 'events' ), array( 'title' => $e[2], 'start' => $day( $e[0] ) . ' ' . $e[1] . ':00', 'kind' => $e[3], 'who' => $e[4], 'location' => $e[5], 'demo' => 1, 'created' => soko_now() ) );
		}
		$exp = array(
			array( 1, 'Transport and fuel', 'Fuel, Kisumu trip', 18500 ),
			array( 2, 'Salaries and wages', 'Casual installers, week 47', 42000 ),
			array( 4, 'Rent and utilities', 'Office and store rent', 85000 ),
			array( 6, 'Marketing', 'Facebook and Google ads', 15000 ),
			array( 8, 'Tools and equipment', 'Crimping tools and multimeter', 12600 ),
			array( 9, 'Salaries and wages', 'Staff salaries', 310000 ),
			array( 11, 'Licences and compliance', 'County business permit', 25000 ),
			array( 15, 'Transport and fuel', 'Pickup service', 23400 ),
			array( 30, 'Rent and utilities', 'Office and store rent', 85000 ),
			array( 39, 'Salaries and wages', 'Staff salaries', 310000 ),
		);
		foreach ( $exp as $x ) {
			$wpdb->insert( soko_t( 'expenses' ), array( 'date' => $day( $x[0] ), 'category' => $x[1], 'description' => $x[2], 'amount' => $x[3], 'vat' => in_array( $x[1], array( 'Tools and equipment', 'Marketing' ), true ) ? round( $x[3] * 16 / 116 ) : 0, 'method' => $x[3] > 50000 ? 'Bank' : 'M-Pesa', 'demo' => 1, 'created' => soko_now() ) );
		}
		$sup = array();
		foreach ( array( array( 'Lithium Valley (export desk)', 'sales@lithiumvalley.example' ), array( 'Solar Distributors Nairobi', '' ), array( 'Victron dealer, Nairobi', '' ), array( 'Cable and accessories supplier', '' ) ) as $s ) {
			$wpdb->insert( soko_t( 'suppliers' ), array( 'name' => $s[0], 'email' => $s[1], 'demo' => 1, 'created' => soko_now() ) );
			$sup[] = (int) $wpdb->insert_id;
		}
		$po = array(
			array( 0, array( array( 'LI-5', 20, 97200 ), array( 'LI-10', 6, 176400 ) ), 'ordered', 3 ),
			array( 1, array( array( 'PV-585', 120, 12900 ) ), 'received', 12 ),
			array( 3, array( array( 'ACC-CAB', 10, 15800 ), array( 'ACC-PRO', 30, 9000 ) ), 'ordered', 1 ),
		);
		foreach ( $po as $o ) {
			$lines = array();
			$total = 0;
			foreach ( $o[1] as $l ) {
				$p       = Soko_Ledger::product_by_sku( $l[0] );
				$lines[] = array( 'product_id' => $p['id'], 'name' => $p['name'], 'qty' => $l[1], 'cost' => $l[2] );
				$total  += $l[1] * $l[2];
			}
			$wpdb->insert( soko_t( 'purchases' ), array( 'number' => Soko_Settings::next_number( 'po' ), 'supplier_id' => $sup[ $o[0] ], 'status' => $o[2], 'date' => $day( $o[3] ), 'items' => wp_json_encode( $lines ), 'total' => $total, 'vat' => round( $total * 16 / 116 ), 'paid' => 'received' === $o[2] ? $total : 0, 'received' => 'received' === $o[2] ? $day( $o[3] - 2 ) . ' 12:00:00' : null, 'demo' => 1, 'created' => soko_now() ) );
		}
		$lic = array(
			array( 'EPRA solar PV licence (T2)', 'Kevin O.', 45 ),
			array( 'EPRA solar PV licence (T1)', 'Dennis M.', 270 ),
			array( 'EPRA solar PV licence (T3)', 'Eng. Wairimu', 95 ),
			array( 'Nairobi County single business permit', 'Dynamic Smart Systems', 34 ),
			array( 'NCA contractor registration', 'Dynamic Smart Systems', 215 ),
		);
		foreach ( $lic as $l ) {
			$wpdb->insert( soko_t( 'licences' ), array( 'name' => $l[0], 'holder' => $l[1], 'expires' => $day( -1 * $l[2] ), 'demo' => 1, 'created' => soko_now() ) );
		}
		update_option( 'soko_demo', 1 );
	}

	public static function remove() {
		global $wpdb;
		// Test orders placed while checking the shop go with the sample data.
		$cust = $wpdb->get_col( 'SELECT id FROM ' . soko_t( 'customers' ) . " WHERE name LIKE 'Test %' OR name LIKE 'QA %' OR name LIKE 'Tie %' OR name LIKE 'Run3 %' OR email LIKE '%@example.com' OR phone IN ('254711000555','254711000777')" ); // phpcs:ignore
		if ( $cust ) {
			$cin = implode( ',', array_map( 'intval', $cust ) );
			$wpdb->query( 'UPDATE ' . soko_t( 'customers' ) . " SET demo = 1 WHERE id IN ($cin)" ); // phpcs:ignore
			$wpdb->query( 'UPDATE ' . soko_t( 'docs' ) . " SET demo = 1 WHERE customer_id IN ($cin)" ); // phpcs:ignore
			$wpdb->query( 'UPDATE ' . soko_t( 'jobs' ) . " SET demo = 1 WHERE customer_id IN ($cin)" ); // phpcs:ignore
		}
		$wpdb->query( 'UPDATE ' . soko_t( 'payments' ) . " SET demo = 1 WHERE code LIKE 'TST%' OR doc_id IN (SELECT id FROM " . soko_t( 'docs' ) . ' WHERE demo = 1)' ); // phpcs:ignore
		$ids = $wpdb->get_col( 'SELECT id FROM ' . soko_t( 'docs' ) . ' WHERE demo = 1' ); // phpcs:ignore
		$nums = $wpdb->get_col( 'SELECT number FROM ' . soko_t( 'docs' ) . ' WHERE demo = 1' ); // phpcs:ignore
		if ( $nums ) {
			$in = implode( ',', array_map( function ( $n ) use ( $wpdb ) { return $wpdb->prepare( '%s', $n ); }, $nums ) );
			foreach ( $wpdb->get_results( 'SELECT product_id, SUM(qty) q FROM ' . soko_t( 'moves' ) . " WHERE ref IN ($in) GROUP BY product_id", ARRAY_A ) as $m ) { // phpcs:ignore
					$wpdb->query( $wpdb->prepare( 'UPDATE ' . soko_t( 'products' ) . ' SET stock = stock - %f WHERE id = %d', $m['q'], $m['product_id'] ) ); // phpcs:ignore
			}
			$wpdb->query( 'DELETE FROM ' . soko_t( 'moves' ) . " WHERE ref IN ($in)" ); // phpcs:ignore
		}

		if ( $ids ) {
			$wpdb->query( 'DELETE FROM ' . soko_t( 'lines' ) . ' WHERE doc_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' ); // phpcs:ignore
		}
		foreach ( array( 'docs', 'customers', 'payments', 'leads', 'jobs', 'events', 'expenses', 'suppliers', 'purchases', 'licences' ) as $t ) {
			$wpdb->query( 'DELETE FROM ' . soko_t( $t ) . ' WHERE demo = 1' ); // phpcs:ignore
		}
		delete_option( 'soko_demo' );
		$numbers = get_option( 'soko_demo_numbers' );
		if ( is_array( $numbers ) && ! $wpdb->get_var( 'SELECT COUNT(*) FROM ' . soko_t( 'docs' ) ) ) { // phpcs:ignore
			Soko_Settings::save( $numbers );
		}
		delete_option( 'soko_demo_numbers' );
	}
}
