<?php
/**
 * Free M-Pesa matching without Daraja: forwarded confirmation SMS and uploaded statements.
 *
 * @package Soko
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Mpesa_Free {

	/** Secret part of the SMS forwarding address. */
	public static function token() {
		$t = get_option( 'soko_sms_token' );
		if ( ! $t ) {
			$t = wp_generate_password( 24, false );
			update_option( 'soko_sms_token', $t, false );
		}
		return $t;
	}

	public static function sms_url() {
		return rest_url( 'soko/v1/sms/' . self::token() );
	}

	/**
	 * Read an M-Pesa "received" confirmation SMS.
	 * Handles Paybill ("...received from NAME 2547XXXXXXXX. Account Number REF...") and Till ("...received from NAME 07XX***XXX on...").
	 */
	public static function parse( $text ) {
		$t = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( (string) $text ) ) );
		if ( ! preg_match( '/\b([A-Z0-9]{10})\b\s*Confirmed/i', $t, $c ) ) {
			return null;
		}
		if ( ! preg_match( '/Ksh\s?([\d,]+(?:\.\d{1,2})?)\s+received from\s+(.+?)(?:\s+(\+?\d[\d*]{7,13}\d))?(?:[.,]|\s+on\s|\s+Account|\s+New\s|$)/i', $t, $m ) ) {
			return null;
		}
		$ref = '';
		if ( preg_match( '/(?:Account Number|Account No\.?|Acc\.)\s*:?\s*([A-Za-z0-9-]+)/i', $t, $a ) ) {
			$ref = $a[1];
		}
		$phone = isset( $m[3] ) ? $m[3] : '';
		return array(
			'method'      => 'mpesa',
			'code'        => strtoupper( $c[1] ),
			'amount'      => (float) str_replace( ',', '', $m[1] ),
			'payer'       => ucwords( strtolower( trim( $m[2], ' .' ) ) ),
			'phone'       => false === strpos( $phone, '*' ) ? $phone : '',
			'account_ref' => $ref,
			'raw'         => array( 'sms' => $t ),
		);
	}

	public static function on_sms( $req ) {
		if ( ! hash_equals( self::token(), (string) $req['token'] ) ) {
			return new WP_Error( 'forbidden', 'Wrong address', array( 'status' => 403 ) );
		}
		$b    = $req->get_json_params();
		$text = '';
		foreach ( array( 'message', 'text', 'body', 'msg', 'content', 'sms' ) as $k ) {
			if ( ! empty( $b[ $k ] ) ) {
				$text = $b[ $k ];
				break;
			}
			if ( $req->get_param( $k ) ) {
				$text = $req->get_param( $k );
				break;
			}
		}
		if ( ! $text ) {
			$text = $req->get_body();
		}
		$p = self::parse( $text );
		if ( ! $p ) {
			return array( 'ok' => true, 'ignored' => true );
		}
		$row = Soko_Ledger::record_payment( $p );
		update_option( 'soko_sms_last', soko_now(), false );
		return array( 'ok' => true, 'code' => $p['code'], 'status' => isset( $row['status'] ) ? $row['status'] : '' );
	}

	/** Import an M-Pesa statement exported as CSV from the business portal. */
	public static function import_csv( $path ) {
		$fh = fopen( $path, 'r' );
		if ( ! $fh ) {
			return array( 'ok' => false, 'message' => 'Could not read the file.' );
		}
		$head = null;
		$cols = array();
		$new  = 0;
		$seen = 0;
		while ( ( $r = fgetcsv( $fh ) ) !== false ) {
			if ( ! $head ) {
				$low = array_map( function ( $x ) { return strtolower( trim( (string) $x ) ); }, $r );
				$rc  = self::find( $low, array( 'receipt no', 'receipt', 'transaction id' ) );
				$pi  = self::find( $low, array( 'paid in', 'credit', 'amount' ) );
				if ( null !== $rc && null !== $pi ) {
					$head = true;
					$cols = array(
						'code'   => $rc,
						'in'     => $pi,
						'time'   => self::find( $low, array( 'completion time', 'date', 'time' ) ),
						'status' => self::find( $low, array( 'transaction status', 'status' ) ),
						'party'  => self::find( $low, array( 'other party info', 'other party', 'details' ) ),
						'ref'    => self::find( $low, array( 'a/c no.', 'account no', 'account', 'reason type' ) ),
					);
				}
				continue;
			}
			$amt = (float) str_replace( array( ',', ' ' ), '', isset( $r[ $cols['in'] ] ) ? $r[ $cols['in'] ] : 0 );
			$st  = null !== $cols['status'] && isset( $r[ $cols['status'] ] ) ? strtolower( $r[ $cols['status'] ] ) : 'completed';
			if ( $amt <= 0 || false === strpos( $st, 'complet' ) ) {
				continue;
			}
			$party = null !== $cols['party'] && isset( $r[ $cols['party'] ] ) ? $r[ $cols['party'] ] : '';
			$phone = preg_match( '/(2547\d{8}|07\d{8}|01\d{8})/', $party, $pm ) ? $pm[1] : '';
			$name  = trim( preg_replace( '/[\d*\s-]{6,}/', ' ', $party ), ' -' );
			$time  = null !== $cols['time'] && ! empty( $r[ $cols['time'] ] ) ? gmdate( 'Y-m-d H:i:s', strtotime( $r[ $cols['time'] ] ) ) : soko_now();
			$seen++;
			$before = Soko_Ledger::payment_exists( $r[ $cols['code'] ] );
			Soko_Ledger::record_payment(
				array(
					'method'      => 'mpesa',
					'code'        => $r[ $cols['code'] ],
					'amount'      => $amt,
					'payer'       => ucwords( strtolower( $name ) ),
					'phone'       => $phone,
					'account_ref' => null !== $cols['ref'] && isset( $r[ $cols['ref'] ] ) ? $r[ $cols['ref'] ] : '',
					'received'    => $time,
					'raw'         => array( 'statement' => $r ),
				)
			);
			if ( ! $before ) {
				$new++;
			}
		}
		fclose( $fh );
		if ( ! $head ) {
			return array( 'ok' => false, 'message' => 'This does not look like an M-Pesa statement. Export it as CSV (Excel) from the M-Pesa business portal and try again.' );
		}
		soko_log( 'Imported M-Pesa statement', 'payments', $new . ' new payments' );
		return array( 'ok' => true, 'message' => $seen . ' payments read, ' . $new . ' new. Paid invoices have been updated.' );
	}

	private static function find( $row, $names ) {
		foreach ( $names as $n ) {
			foreach ( $row as $i => $c ) {
				if ( $c === $n || 0 === strpos( $c, $n ) ) {
					return $i;
				}
			}
		}
		return null;
	}
}
