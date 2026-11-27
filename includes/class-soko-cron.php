<?php
/**
 * Scheduled reports, reminders and notifications.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Cron {

	public static function init() {
		add_action( 'soko_daily', array( __CLASS__, 'daily' ) );
		add_action( 'soko_hourly', array( __CLASS__, 'hourly' ) );
		if ( ! wp_next_scheduled( 'soko_daily' ) ) {
			self::schedule();
		}
	}

	public static function schedule() {
		self::unschedule();
		$tz    = wp_timezone();
		$first = new DateTime( 'tomorrow 07:00', $tz );
		wp_schedule_event( $first->getTimestamp(), 'daily', 'soko_daily' );
		wp_schedule_event( time() + 300, 'hourly', 'soko_hourly' );
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( 'soko_daily' );
		wp_clear_scheduled_hook( 'soko_hourly' );
	}

	private static function to( $key ) {
		$r  = soko_setting( 'reports' );
		$to = isset( $r[ $key ]['to'] ) && $r[ $key ]['to'] ? $r[ $key ]['to'] : soko_setting( 'notify_email' );
		return array_filter( array_map( 'trim', explode( ',', $to ) ) );
	}

	private static function on( $key ) {
		$r = soko_setting( 'reports' );
		return ! empty( $r[ $key ]['on'] );
	}

	public static function notify( $subject, $body ) {
		$to = soko_setting( 'notify_email' );
		if ( $to ) {
			soko_mail( $to, '[' . soko_setting( 'business' ) . '] ' . $subject, $body );
		}
	}

	private static function attach( $body, $name ) {
		$f = trailingslashit( get_temp_dir() ) . $name;
		file_put_contents( $f, $body ); // phpcs:ignore
		return $f;
	}

	public static function daily() {
		global $wpdb;
		$today = soko_today();
		$yday  = gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) );
		$biz   = soko_setting( 'business' );

		if ( self::on( 'daily' ) ) {
			$d = Soko_Reports::dashboard();
			$p = Soko_Reports::run( 'payments', $yday, $yday );
			$s = Soko_Reports::pl_numbers( $yday, $yday );
			$body = sprintf(
				"Summary for %s\n\nSales (excl. VAT): %s\nPayments received: %s\nOwed by customers: %s\nOverdue invoices: %d\nUnmatched payments: %d\nLow stock items: %d\n\nOpen Dynamic Desk: %s",
				date_i18n( 'l j F', strtotime( $yday ) ),
				soko_money( $s['sales'] ),
				$p['kpis'][0][1],
				soko_money( $d['owed'] ),
				count( $d['overdue'] ),
				count( $d['unmatched'] ),
				count( $d['low'] ),
				home_url( '/desk/' )
			);
			soko_mail( self::to( 'daily' ), $biz . ': daily summary', $body );
		}

		if ( self::on( 'weekly' ) && '1' === current_time( 'N' ) ) {
			$from = gmdate( 'Y-m-d', strtotime( $today . ' -7 days' ) );
			list( $bin, , $name ) = Soko_Reports::export( 'sales', $from, $yday, 'xlsx' );
			$f = self::attach( $bin, $name );
			$n = Soko_Reports::pl_numbers( $from, $yday );
			soko_mail( self::to( 'weekly' ), $biz . ': weekly sales report', sprintf( "Last week's sales excl. VAT: %s\nGross profit: %s (%d%%)\n\nThe full report is attached as an Excel file.", soko_money( $n['sales'] ), soko_money( $n['gross'] ), $n['margin'] ), array(), array( $f ) );
			wp_delete_file( $f );
		}

		if ( self::on( 'monthly' ) && '01' === current_time( 'd' ) ) {
			$from = gmdate( 'Y-m-01', strtotime( $today . ' -1 month' ) );
			$to   = gmdate( 'Y-m-t', strtotime( $from ) );
			$files = array();
			foreach ( array( 'pl' => 'pdf', 'vat' => 'xlsx', 'receivables' => 'pdf' ) as $type => $fmt ) {
				list( $bin, , $name ) = Soko_Reports::export( $type, $from, $to, $fmt );
				$files[] = self::attach( $bin, $name );
			}
			soko_mail( self::to( 'monthly' ), $biz . ': month-end reports for ' . date_i18n( 'F Y', strtotime( $from ) ), "Attached: profit and loss, VAT summary and money owed for last month.\n\nThe VAT return is due by the 20th.", array(), $files );
			foreach ( $files as $f ) {
				wp_delete_file( $f );
			}
		}

		if ( self::on( 'lowstock' ) ) {
			$low = $wpdb->get_results( 'SELECT name, stock, reorder FROM ' . soko_t( 'products' ) . " WHERE kind = 'product' AND visible = 1 AND stock <= reorder ORDER BY stock", ARRAY_A ); // phpcs:ignore
			if ( $low ) {
				soko_mail( self::to( 'lowstock' ), $biz . ': ' . count( $low ) . ' items to reorder', "These items are at or below their reorder level:\n\n" . implode( "\n", array_map( function ( $l ) { return '• ' . $l['name'] . ': ' . ( $l['stock'] + 0 ) . ' left (reorder at ' . ( $l['reorder'] + 0 ) . ')'; }, $low ) ) );
			}
		}

		if ( self::on( 'overdue' ) ) {
			$due = gmdate( 'Y-m-d', strtotime( $today . ' -3 days' ) );
			$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . soko_t( 'docs' ) . " WHERE type = 'invoice' AND status <> 'void' AND paid < total - 0.5 AND due = %s AND demo = 0", $due ) ); // phpcs:ignore
			foreach ( $ids as $id ) {
				$d = Soko_Ledger::doc( $id );
				if ( $d['customer'] && $d['customer']['email'] ) {
					Soko_Ledger::email_doc( $d, $d['customer']['email'], sprintf( "Hello %s,\n\nThis is a friendly reminder that invoice %s has a balance of %s, which was due on %s.\n\nPay by M-Pesa using account number %s, or online: %s\n\nThank you,\n%s", $d['customer']['name'], $d['number'], soko_money( $d['balance'] ), date_i18n( 'j M Y', strtotime( $d['due'] ) ), $d['number'], $d['pay_url'], $biz ) );
				}
			}
		}

		if ( self::on( 'licences' ) ) {
			$L = soko_t( 'licences' );
			foreach ( array( 60, 30, 7 ) as $days ) {
				$on = gmdate( 'Y-m-d', strtotime( $today . " +$days days" ) );
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $L WHERE expires = %s", $on ), ARRAY_A ) as $l ) { // phpcs:ignore
					soko_mail( self::to( 'licences' ), $biz . ': ' . $l['name'] . ' expires in ' . $days . ' days', sprintf( "%s%s expires on %s.\n\nRenew it, then update the date in Dynamic Desk → Tax and compliance.", $l['name'], $l['holder'] ? ' (' . $l['holder'] . ')' : '', date_i18n( 'j F Y', strtotime( $l['expires'] ) ) ) );
				}
			}
		}
	}

	/** Evening-before reminders for scheduled work. */
	public static function hourly() {
		global $wpdb;
		if ( ! self::on( 'schedule' ) || (int) current_time( 'G' ) !== 18 ) {
			return;
		}
		$tom = gmdate( 'Y-m-d', strtotime( soko_today() . ' +1 day' ) );
		$E   = soko_t( 'events' );
		$evs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $E WHERE remind = 1 AND reminded = 0 AND done = 0 AND start BETWEEN %s AND %s ORDER BY start", $tom . ' 00:00:00', $tom . ' 23:59:59' ), ARRAY_A ); // phpcs:ignore
		if ( ! $evs ) {
			return;
		}
		$team = get_users( array( 'role__in' => array_keys( soko_roles() ) ) );
		$sent = array();
		foreach ( $evs as $e ) {
			$line = substr( $e['start'], 11, 5 ) . '  ' . $e['title'] . ( $e['location'] ? ' · ' . $e['location'] : '' ) . ( $e['who'] ? ' · ' . $e['who'] : '' );
			foreach ( $team as $u ) {
				if ( $e['who'] && false !== stripos( $e['who'], strtok( $u->display_name, ' ' ) ) ) {
					$sent[ $u->user_email ][] = $line;
				}
			}
			$sent[ soko_setting( 'notify_email' ) ][] = $line;
			$wpdb->update( $E, array( 'reminded' => 1 ), array( 'id' => $e['id'] ) );
		}
		foreach ( $sent as $to => $lines ) {
			soko_mail( $to, 'Tomorrow: ' . count( $lines ) . ' scheduled ' . _n( 'item', 'items', count( $lines ) ), "Tomorrow's schedule:\n\n" . implode( "\n", $lines ) . "\n\n" . home_url( '/desk/#schedule' ) );
		}
	}
}
