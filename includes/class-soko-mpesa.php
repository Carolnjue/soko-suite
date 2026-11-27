<?php
/**
 * M-Pesa through Safaricom Daraja: STK push (M-Pesa Express) and C2B Paybill/Till confirmations.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Mpesa {

	public static function cfg() {
		return soko_setting( 'mpesa' );
	}

	public static function base() {
		return 'live' === self::cfg()['env'] ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
	}

	/** Is STK push configured with real credentials? */
	public static function ready() {
		$c = self::cfg();
		return ! empty( $c['key'] ) && ! empty( $c['secret'] ) && ! empty( $c['passkey'] ) && ! empty( $c['shortcode'] );
	}

	/** Payment instructions shown to customers. */
	public static function instructions( $account ) {
		$c = self::cfg();
		if ( 'till' === $c['type'] && ! empty( $c['till'] ) ) {
			return array( 'kind' => 'Buy Goods', 'number' => $c['till'], 'account' => '' );
		}
		return array( 'kind' => 'Paybill', 'number' => $c['shortcode'], 'account' => $account );
	}

	public static function callback_url( $what ) {
		return rest_url( 'soko/v1/mpesa/' . self::cfg()['token'] . '/' . $what );
	}

	/** Check the keys with Safaricom without charging anyone. */
	public static function test() {
		$c = self::cfg();
		if ( empty( $c['key'] ) || empty( $c['secret'] ) ) {
			return array( 'ok' => false, 'message' => 'Paste the Consumer Key and Consumer Secret first, then save.' );
		}
		delete_transient( 'soko_mpesa_token' );
		$t = self::token();
		if ( is_wp_error( $t ) ) {
			return array( 'ok' => false, 'message' => $t->get_error_message() );
		}
		$miss = array();
		if ( empty( $c['shortcode'] ) ) {
			$miss[] = 'shortcode';
		}
		if ( empty( $c['passkey'] ) ) {
			$miss[] = 'passkey';
		}
		return array( 'ok' => ! $miss, 'message' => $miss ? 'Safaricom accepted the keys. Still missing: ' . implode( ' and ', $miss ) . '.' : 'Connected to Safaricom (' . ( 'live' === $c['env'] ? 'live' : 'sandbox' ) . '). Prompts and automatic matching are on.' );
	}

	private static function token() {
		$cached = get_transient( 'soko_mpesa_token' );
		if ( $cached ) {
			return $cached;
		}
		$c = self::cfg();
		$r = wp_remote_get(
			self::base() . '/oauth/v1/generate?grant_type=client_credentials',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $c['key'] . ':' . $c['secret'] ) ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$b = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( empty( $b['access_token'] ) ) {
			return new WP_Error( 'soko_mpesa', 'M-Pesa sign-in failed. Check the consumer key and secret.' );
		}
		set_transient( 'soko_mpesa_token', $b['access_token'], 50 * MINUTE_IN_SECONDS );
		return $b['access_token'];
	}

	private static function post( $path, $body ) {
		$tok = self::token();
		if ( is_wp_error( $tok ) ) {
			return $tok;
		}
		$r = wp_remote_post(
			self::base() . $path,
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return json_decode( wp_remote_retrieve_body( $r ), true );
	}

	/**
	 * Send an STK push for an invoice.
	 * Returns array( ok, message, checkout_id ).
	 */
	public static function stk( $doc, $phone, $amount = null ) {
		global $wpdb;
		$c      = self::cfg();
		$phone  = soko_phone( $phone );
		$amount = (int) ceil( null === $amount ? $doc['balance'] : $amount );
		if ( strlen( $phone ) !== 12 ) {
			return array( 'ok' => false, 'message' => 'Enter a valid Safaricom number, for example 0712 345 678.' );
		}
		if ( $amount < 1 ) {
			return array( 'ok' => false, 'message' => 'Nothing to pay on this invoice.' );
		}
		if ( ! self::ready() ) {
			return array( 'ok' => false, 'message' => 'M-Pesa prompts are not switched on yet. Pay by ' . self::instructions( $doc['number'] )['kind'] . ' instead.', 'setup' => true );
		}
		$ts   = current_time( 'YmdHis' );
		$till = 'till' === $c['type'] && ! empty( $c['till'] );
		$res  = self::post(
			'/mpesa/stkpush/v1/processrequest',
			array(
				'BusinessShortCode' => $c['shortcode'],
				'Password'          => base64_encode( $c['shortcode'] . $c['passkey'] . $ts ),
				'Timestamp'         => $ts,
				'TransactionType'   => $till ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
				'Amount'            => $amount,
				'PartyA'            => $phone,
				'PartyB'            => $till ? $c['till'] : $c['shortcode'],
				'PhoneNumber'       => $phone,
				'CallBackURL'       => self::callback_url( 'stk' ),
				'AccountReference'  => substr( $doc['number'], 0, 12 ),
				'TransactionDesc'   => 'Payment ' . $doc['number'],
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		if ( isset( $res['ResponseCode'] ) && '0' === (string) $res['ResponseCode'] ) {
			$wpdb->insert(
				soko_t( 'stk' ),
				array(
					'doc_id'      => $doc['id'],
					'phone'       => $phone,
					'amount'      => $amount,
					'checkout_id' => $res['CheckoutRequestID'],
					'status'      => 'sent',
					'message'     => 'Waiting for PIN',
					'created'     => soko_now(),
				)
			);
			soko_log( 'M-Pesa prompt sent for ' . $doc['number'], 'invoice', $doc['number'] );
			return array( 'ok' => true, 'message' => 'Check your phone and enter your M-Pesa PIN.', 'checkout_id' => $res['CheckoutRequestID'] );
		}
		$msg = isset( $res['errorMessage'] ) ? $res['errorMessage'] : ( isset( $res['ResponseDescription'] ) ? $res['ResponseDescription'] : 'M-Pesa did not accept the request.' );
		return array( 'ok' => false, 'message' => $msg );
	}

	/** Status of a prompt, for the checkout page to poll. */
	public static function stk_status( $checkout_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'stk' ) . ' WHERE checkout_id = %s', $checkout_id ), ARRAY_A ); // phpcs:ignore
		return $row ? $row : array( 'status' => 'unknown' );
	}

	/** STK result callback from Safaricom. */
	public static function on_stk( $body ) {
		global $wpdb;
		$cb  = isset( $body['Body']['stkCallback'] ) ? $body['Body']['stkCallback'] : null;
		if ( ! $cb ) {
			return;
		}
		$cid = $cb['CheckoutRequestID'];
		$row = self::stk_status( $cid );
		if ( 0 !== (int) $cb['ResultCode'] ) {
			$wpdb->update( soko_t( 'stk' ), array( 'status' => 'failed', 'message' => substr( $cb['ResultDesc'], 0, 250 ) ), array( 'checkout_id' => $cid ) );
			return;
		}
		$items = array();
		foreach ( $cb['CallbackMetadata']['Item'] as $it ) {
			$items[ $it['Name'] ] = isset( $it['Value'] ) ? $it['Value'] : '';
		}
		$doc = ! empty( $row['doc_id'] ) ? Soko_Ledger::doc( $row['doc_id'] ) : null;
		Soko_Ledger::record_payment(
			array(
				'method'      => 'mpesa',
				'code'        => $items['MpesaReceiptNumber'],
				'amount'      => $items['Amount'],
				'phone'       => (string) $items['PhoneNumber'],
				'payer'       => $doc && $doc['customer'] ? $doc['customer']['name'] : '',
				'account_ref' => $doc ? $doc['number'] : '',
				'doc_id'      => $doc ? $doc['id'] : 0,
				'checkout_id' => $cid,
				'raw'         => $cb,
			)
		);
		$wpdb->update( soko_t( 'stk' ), array( 'status' => 'paid', 'message' => $items['MpesaReceiptNumber'] ), array( 'checkout_id' => $cid ) );
	}

	/** C2B confirmation: customer paid to the Paybill or Till from their phone menu. */
	public static function on_c2b( $b ) {
		if ( empty( $b['TransID'] ) ) {
			return;
		}
		$name = trim( ( isset( $b['FirstName'] ) ? $b['FirstName'] : '' ) . ' ' . ( isset( $b['MiddleName'] ) ? $b['MiddleName'] : '' ) . ' ' . ( isset( $b['LastName'] ) ? $b['LastName'] : '' ) );
		$time = isset( $b['TransTime'] ) && 14 === strlen( $b['TransTime'] ) ? DateTime::createFromFormat( 'YmdHis', $b['TransTime'] )->format( 'Y-m-d H:i:s' ) : soko_now();
		Soko_Ledger::record_payment(
			array(
				'method'      => 'mpesa',
				'code'        => $b['TransID'],
				'amount'      => $b['TransAmount'],
				'phone'       => isset( $b['MSISDN'] ) ? $b['MSISDN'] : '',
				'payer'       => $name,
				'account_ref' => isset( $b['BillRefNumber'] ) ? $b['BillRefNumber'] : '',
				'received'    => $time,
				'raw'         => $b,
			)
		);
	}

	/** Register confirmation and validation URLs for C2B with Safaricom. */
	public static function register_c2b() {
		$c = self::cfg();
		if ( empty( $c['key'] ) || empty( $c['secret'] ) || empty( $c['shortcode'] ) ) {
			return array( 'ok' => false, 'message' => 'Add the shortcode, consumer key and secret first.' );
		}
		$res = self::post(
			'/mpesa/c2b/v2/registerurl',
			array(
				'ShortCode'       => 'till' === $c['type'] && ! empty( $c['till'] ) ? $c['till'] : $c['shortcode'],
				'ResponseType'    => 'Completed',
				'ConfirmationURL' => self::callback_url( 'confirm' ),
				'ValidationURL'   => self::callback_url( 'validate' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		$ok = isset( $res['ResponseCode'] ) && in_array( (string) $res['ResponseCode'], array( '0', '00000000' ), true );
		return array( 'ok' => $ok, 'message' => $ok ? 'Payment notifications registered with Safaricom.' : ( isset( $res['errorMessage'] ) ? $res['errorMessage'] : wp_json_encode( $res ) ) );
	}
}
