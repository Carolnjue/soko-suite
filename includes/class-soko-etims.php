<?php
/**
 * KRA eTIMS tracking.
 *
 * Every tax invoice is queued for eTIMS. Until the business completes KRA's OSCU
 * certification, staff sign invoices in the eTIMS portal or eTIMS Client and record
 * the control unit invoice number here, so nothing is missed. Once OSCU credentials
 * are issued, the connector settings in Desk → Settings hold them.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Etims {

	public static function mode() {
		$e = soko_setting( 'etims' );
		return $e['mode'];
	}

	public static function queue( $doc ) {
		global $wpdb;
		if ( ! soko_setting( 'vat_registered' ) || $doc['etims_status'] ) {
			return;
		}
		$wpdb->update( soko_t( 'docs' ), array( 'etims_status' => 'pending' ), array( 'id' => $doc['id'] ) );
	}

	/** Record the eTIMS control unit invoice number for an invoice. */
	public static function record( $doc_id, $ref ) {
		global $wpdb;
		$ref = sanitize_text_field( $ref );
		$wpdb->update( soko_t( 'docs' ), array( 'etims_status' => $ref ? 'signed' : 'pending', 'etims_ref' => $ref ), array( 'id' => $doc_id ) );
		$d = Soko_Ledger::doc( $doc_id );
		soko_log( 'eTIMS number recorded for ' . $d['number'], 'invoice', $d['number'] );
		return $d;
	}

	public static function summary() {
		global $wpdb;
		$t = soko_t( 'docs' );
		return array(
			'pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t d WHERE type = 'invoice' AND status <> 'void' AND etims_status = 'pending' AND " . Soko_Accounts::confirmed_sql( 'd' ) ), // phpcs:ignore
			'signed'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE type = 'invoice' AND etims_status = 'signed'" ), // phpcs:ignore
			'mode'    => self::mode(),
		);
	}
}
