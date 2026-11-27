<?php
/**
 * Branded PDFs for quotations, invoices, receipts and reports.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

require_once SOKO_DIR . 'lib/fpdf/fpdf.php';

final class Soko_PDF_Doc extends FPDF {
	public $foot = '';
	public function Footer() {
		$this->SetY( -14 );
		$this->SetFont( 'Helvetica', '', 8 );
		$this->SetTextColor( 120, 130, 150 );
		$this->Cell( 0, 5, Soko_PDF::t( $this->foot ), 0, 0, 'L' );
		$this->Cell( 0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R' );
	}
}

final class Soko_PDF {

	const BLUE = array( 27, 79, 158 );
	const INK  = array( 15, 29, 54 );
	const SOFT = array( 90, 103, 128 );
	const SUN  = array( 247, 148, 29 );

	public static function t( $s ) {
		$s = str_replace( array( '–', '—', '’', '‘', '“', '”', '•', '×', '²', '³' ), array( '-', '-', "'", "'", '"', '"', '-', 'x', '2', '3' ), (string) $s );
		$c = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'windows-1252//TRANSLIT', $s ) : $s; // phpcs:ignore
		return false === $c ? $s : $c;
	}

	private static function logo() {
		$custom = get_option( 'soko_logo_pdf' );
		if ( $custom && file_exists( $custom ) ) {
			return $custom;
		}
		return SOKO_DIR . 'assets/img/dss-logo.jpg';
	}

	private static function color( $pdf, $c, $what = 'text' ) {
		if ( 'text' === $what ) {
			$pdf->SetTextColor( $c[0], $c[1], $c[2] );
		} elseif ( 'fill' === $what ) {
			$pdf->SetFillColor( $c[0], $c[1], $c[2] );
		} else {
			$pdf->SetDrawColor( $c[0], $c[1], $c[2] );
		}
	}

	private static function head( $pdf, $title, $right ) {
		$pdf->Image( self::logo(), 15, 12, 52 );
		self::color( $pdf, self::BLUE );
		$pdf->SetFont( 'Helvetica', 'B', 20 );
		$pdf->SetXY( 110, 12 );
		$pdf->Cell( 85, 9, self::t( $title ), 0, 2, 'R' );
		$pdf->SetFont( 'Helvetica', '', 9.5 );
		self::color( $pdf, self::INK );
		foreach ( $right as $line ) {
			$pdf->Cell( 85, 5, self::t( $line ), 0, 2, 'R' );
		}
		$pdf->SetXY( 15, 32 );
		$pdf->SetFont( 'Helvetica', '', 8.5 );
		self::color( $pdf, self::SOFT );
		$s = Soko_Settings::all();
		$pdf->MultiCell( 90, 4.2, self::t( $s['address'] . "\n" . $s['phone'] . ( $s['phone2'] ? ' / ' . $s['phone2'] : '' ) . "\n" . $s['email'] . ( $s['kra_pin'] ? "\nKRA PIN " . $s['kra_pin'] : '' ) ) );
		$y = max( $pdf->GetY(), 52 ) + 3;
		self::color( $pdf, self::BLUE, 'draw' );
		$pdf->SetLineWidth( 0.8 );
		$pdf->Line( 15, $y, 195, $y );
		$pdf->SetLineWidth( 0.2 );
		$pdf->SetY( $y + 5 );
	}

	/** Build the PDF for a document; returns the binary string. */
	public static function doc( $d ) {
		$s     = Soko_Settings::all();
		$pdf   = new Soko_PDF_Doc( 'P', 'mm', 'A4' );
		$pdf->foot = $s['business'] . ' · ' . $s['tagline'];
		$pdf->AliasNbPages();
		$pdf->SetMargins( 15, 12, 15 );
		$pdf->SetAutoPageBreak( true, 20 );
		$pdf->AddPage();
		$titles = array( 'quote' => 'Quotation', 'invoice' => $s['vat_registered'] ? 'Tax invoice' : 'Invoice', 'receipt' => 'Receipt' );
		$right  = array( $d['number'], 'Date: ' . date_i18n( 'j M Y', strtotime( $d['date'] ) ) );
		if ( 'quote' === $d['type'] && $d['due'] ) {
			$right[] = 'Valid until: ' . date_i18n( 'j M Y', strtotime( $d['due'] ) );
		}
		if ( 'invoice' === $d['type'] && $d['due'] ) {
			$right[] = 'Due: ' . date_i18n( 'j M Y', strtotime( $d['due'] ) );
		}
		if ( 'receipt' === $d['type'] && ! empty( $d['meta']['invoice'] ) ) {
			$right[] = 'For invoice: ' . $d['meta']['invoice'];
		}
		self::head( $pdf, ( 'void' === ( isset( $d['state'] ) ? $d['state'] : '' ) ? 'CANCELLED · ' : '' ) . $titles[ $d['type'] ], $right );
		if ( 'void' === ( isset( $d['state'] ) ? $d['state'] : '' ) ) {
			$pdf->SetFont( 'Helvetica', 'B', 11 );
			$pdf->SetTextColor( 194, 54, 43 );
			$pdf->Cell( 0, 8, 'This invoice has been cancelled. No payment is due.', 0, 1 );
			$pdf->SetTextColor( 0, 0, 0 );
		}

		$c = $d['customer'] ? $d['customer'] : array( 'name' => 'Customer' );
		$pdf->SetFont( 'Helvetica', 'B', 9 );
		self::color( $pdf, self::SOFT );
		$pdf->Cell( 90, 5, 'receipt' === $d['type'] ? 'RECEIVED FROM' : 'BILL TO', 0, 1 );
		self::color( $pdf, self::INK );
		$pdf->SetFont( 'Helvetica', 'B', 11 );
		$pdf->Cell( 90, 6, self::t( $c['name'] ), 0, 1 );
		$pdf->SetFont( 'Helvetica', '', 9.5 );
		$info = array();
		if ( ! empty( $c['phone'] ) ) {
			$info[] = soko_phone_display( $c['phone'] );
		}
		if ( ! empty( $c['email'] ) ) {
			$info[] = $c['email'];
		}
		if ( ! empty( $c['address'] ) || ! empty( $c['county'] ) ) {
			$info[] = trim( ( isset( $c['address'] ) ? $c['address'] : '' ) . ' ' . ( isset( $c['county'] ) ? $c['county'] : '' ) );
		}
		if ( ! empty( $c['kra'] ) ) {
			$info[] = 'KRA PIN ' . $c['kra'];
		}
		foreach ( $info as $i ) {
			$pdf->Cell( 90, 5, self::t( $i ), 0, 1 );
		}
		$pdf->Ln( 5 );

		if ( 'receipt' === $d['type'] ) {
			$meta = $d['meta'];
			self::color( $pdf, array( 238, 245, 255 ), 'fill' );
			$pdf->SetFont( 'Helvetica', '', 10 );
			$rows = array(
				array( 'Amount received', soko_money( $d['total'] ) ),
				array( 'Paid by', ( 'mpesa' === ( isset( $meta['method'] ) ? $meta['method'] : '' ) ? 'M-Pesa' : ucfirst( isset( $meta['method'] ) ? $meta['method'] : '' ) ) . ( ! empty( $meta['code'] ) ? '  ·  ' . $meta['code'] : '' ) ),
				array( 'Against invoice', isset( $meta['invoice'] ) ? $meta['invoice'] : '' ),
				array( 'Balance remaining', soko_money( isset( $meta['balance'] ) ? $meta['balance'] : 0 ) ),
			);
			foreach ( $rows as $i => $r ) {
				$pdf->SetFont( 'Helvetica', 0 === $i ? 'B' : '', 0 === $i ? 13 : 10 );
				$pdf->Cell( 70, 10, self::t( $r[0] ), 'B', 0, 'L', 0 === $i );
				$pdf->Cell( 110, 10, self::t( $r[1] ), 'B', 1, 'R', 0 === $i );
			}
			$pdf->Ln( 8 );
			$pdf->SetFont( 'Helvetica', 'B', 22 );
			self::color( $pdf, array( 17, 128, 79 ) );
			self::color( $pdf, array( 17, 128, 79 ), 'draw' );
			$pdf->SetLineWidth( 0.9 );
			$pdf->Cell( 40, 14, 'PAID', 1, 1, 'C' );
			$pdf->SetLineWidth( 0.2 );
			self::color( $pdf, self::INK );
		} else {
			self::color( $pdf, self::BLUE, 'fill' );
			$pdf->SetTextColor( 255, 255, 255 );
			$pdf->SetFont( 'Helvetica', 'B', 9 );
			$pdf->Cell( 98, 8, '  Description', 0, 0, 'L', true );
			$pdf->Cell( 16, 8, 'Qty', 0, 0, 'R', true );
			$pdf->Cell( 32, 8, 'Unit price', 0, 0, 'R', true );
			$pdf->Cell( 34, 8, 'Amount  ', 0, 1, 'R', true );
			self::color( $pdf, self::INK );
			$pdf->SetFont( 'Helvetica', '', 9.5 );
			self::color( $pdf, array( 227, 232, 241 ), 'draw' );
			$fill = false;
			foreach ( $d['lines'] as $l ) {
				self::color( $pdf, array( 246, 248, 252 ), 'fill' );
				$y0 = $pdf->GetY();
				$pdf->MultiCell( 98, 7, self::t( '  ' . $l['description'] ), 0, 'L', $fill );
				$h = $pdf->GetY() - $y0;
				$pdf->SetXY( 113, $y0 );
				$pdf->Cell( 16, $h, rtrim( rtrim( number_format( $l['qty'], 2 ), '0' ), '.' ), 0, 0, 'R', $fill );
				$pdf->Cell( 32, $h, soko_money( $l['price'], false ), 0, 0, 'R', $fill );
				$pdf->Cell( 34, $h, soko_money( $l['qty'] * $l['price'], false ) . '  ', 0, 1, 'R', $fill );
				$fill = ! $fill;
			}
			$pdf->Ln( 3 );
			$tot = array();
			if ( $s['vat_registered'] ) {
				$tot[] = array( 'Subtotal excl. VAT', soko_money( $d['subtotal'] ) );
				$tot[] = array( 'VAT ' . (float) $s['vat_rate'] . '%', soko_money( $d['vat'] ) );
			}
			$tot[] = array( 'Total', soko_money( $d['total'] ) );
			if ( 'invoice' === $d['type'] && $d['paid'] > 0 ) {
				$tot[] = array( 'Paid', '(' . soko_money( $d['paid'] ) . ')' );
				$tot[] = array( 'Balance due', soko_money( $d['balance'] ) );
			}
			if ( ! empty( $d['meta']['deposit'] ) && 'invoice' === $d['type'] && $d['paid'] <= 0 ) {
				$tot[] = array( 'Deposit to start (' . (int) $d['meta']['deposit'] . '%)', soko_money( round( $d['total'] * $d['meta']['deposit'] / 100 ) ) );
			}
			foreach ( $tot as $i => $t ) {
				$last = $i === count( $tot ) - 1;
				$pdf->SetX( 110 );
				$pdf->SetFont( 'Helvetica', $last ? 'B' : '', $last ? 12 : 9.5 );
				$pdf->Cell( 50, $last ? 9 : 6, self::t( $t[0] ), $last ? 'T' : 0, 0, 'L' );
				$pdf->Cell( 35, $last ? 9 : 6, self::t( $t[1] ), $last ? 'T' : 0, 1, 'R' );
			}
		}

		$pdf->Ln( 6 );
		if ( 'invoice' === $d['type'] && $d['balance'] > 0 || 'quote' === $d['type'] ) {
			$ins = Soko_Mpesa::instructions( $d['number'] );
			self::color( $pdf, array( 238, 245, 255 ), 'fill' );
			$y = $pdf->GetY();
			$pdf->Rect( 15, $y, 180, 24, 'F' );
			$pdf->SetXY( 20, $y + 3 );
			$pdf->SetFont( 'Helvetica', 'B', 10 );
			self::color( $pdf, array( 10, 143, 60 ) );
			$pdf->Cell( 80, 5, 'Pay with M-Pesa', 0, 2 );
			self::color( $pdf, self::INK );
			$pdf->SetFont( 'Helvetica', '', 9.5 );
			if ( $ins['number'] ) {
				$pdf->Cell( 80, 5, self::t( $ins['kind'] . ': ' . $ins['number'] ), 0, 2 );
				if ( $ins['account'] ) {
					$pdf->Cell( 80, 5, self::t( 'Account number: ' . $ins['account'] ), 0, 2 );
				}
			} else {
				$pdf->Cell( 80, 5, self::t( 'Use the payment link below or call ' . $s['phone'] ), 0, 2 );
			}
			$pdf->SetXY( 105, $y + 3 );
			$pdf->SetFont( 'Helvetica', 'B', 9.5 );
			$pdf->Cell( 85, 5, 'Pay or view online', 0, 2 );
			$pdf->SetFont( 'Helvetica', '', 8 );
			self::color( $pdf, self::BLUE );
			$pdf->MultiCell( 85, 4, self::t( $d['pay_url'] ) );
			self::color( $pdf, self::INK );
			$pdf->SetY( $y + 28 );
		}
		if ( $s['bank_details'] && 'receipt' !== $d['type'] ) {
			$pdf->SetFont( 'Helvetica', 'B', 9 );
			$pdf->Cell( 0, 5, 'Bank details', 0, 1 );
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->MultiCell( 0, 4.5, self::t( $s['bank_details'] ) );
			$pdf->Ln( 2 );
		}
		if ( 'invoice' === $d['type'] && 'signed' === $d['etims_status'] ) {
			$pdf->SetFont( 'Helvetica', '', 8.5 );
			self::color( $pdf, self::SOFT );
			$pdf->Cell( 0, 5, self::t( 'KRA eTIMS invoice: ' . $d['etims_ref'] ), 0, 1 );
		}
		$note = trim( $d['notes'] . ( 'receipt' !== $d['type'] ? "\n" . $s['invoice_note'] : '' ) );
		if ( $note ) {
			$pdf->Ln( 2 );
			$pdf->SetFont( 'Helvetica', '', 8.5 );
			self::color( $pdf, self::SOFT );
			$pdf->MultiCell( 0, 4.3, self::t( $note ) );
		}
		return $pdf->Output( 'S' );
	}

	/** Write a document PDF to a temp file (for email attachments). */
	public static function doc_file( $d ) {
		$dir = get_temp_dir();
		$f   = trailingslashit( $dir ) . sanitize_file_name( $d['number'] ) . '.pdf';
		file_put_contents( $f, self::doc( $d ) ); // phpcs:ignore
		return file_exists( $f ) ? $f : '';
	}

	/**
	 * Report PDF. $sections: array of array( 'title' => '', 'head' => array(), 'rows' => array(), 'align' => array() ).
	 */
	public static function report( $title, $period, $sections, $kpis = array() ) {
		$s   = Soko_Settings::all();
		$pdf = new Soko_PDF_Doc( 'P', 'mm', 'A4' );
		$pdf->foot = $s['business'] . ' · Generated ' . date_i18n( 'j M Y H:i' );
		$pdf->AliasNbPages();
		$pdf->SetMargins( 15, 12, 15 );
		$pdf->SetAutoPageBreak( true, 20 );
		$pdf->AddPage();
		self::head( $pdf, $title, array( $period ) );
		if ( $kpis ) {
			$w = 180 / count( $kpis );
			$y = $pdf->GetY();
			foreach ( $kpis as $i => $k ) {
				self::color( $pdf, array( 238, 245, 255 ), 'fill' );
				$pdf->Rect( 15 + $i * $w + 1, $y, $w - 2, 18, 'F' );
				$pdf->SetXY( 18 + $i * $w, $y + 2 );
				$pdf->SetFont( 'Helvetica', '', 8 );
				self::color( $pdf, self::SOFT );
				$pdf->Cell( $w - 6, 5, self::t( $k[0] ), 0, 2 );
				$pdf->SetFont( 'Helvetica', 'B', 11.5 );
				self::color( $pdf, self::INK );
				$pdf->Cell( $w - 6, 7, self::t( $k[1] ), 0, 2 );
			}
			$pdf->SetY( $y + 24 );
		}
		foreach ( $sections as $sec ) {
			if ( ! empty( $sec['title'] ) ) {
				$pdf->SetFont( 'Helvetica', 'B', 11 );
				self::color( $pdf, self::BLUE );
				$pdf->Cell( 0, 8, self::t( $sec['title'] ), 0, 1 );
				self::color( $pdf, self::INK );
			}
			$n      = count( $sec['head'] );
			$widths = isset( $sec['widths'] ) ? $sec['widths'] : array_merge( array( 180 - ( $n - 1 ) * 30 ), array_fill( 0, $n - 1, 30 ) );
			$align  = isset( $sec['align'] ) ? $sec['align'] : array_merge( array( 'L' ), array_fill( 0, $n - 1, 'R' ) );
			$pdf->SetFont( 'Helvetica', 'B', 8.5 );
			self::color( $pdf, self::SOFT );
			self::color( $pdf, array( 210, 218, 232 ), 'draw' );
			foreach ( $sec['head'] as $i => $h ) {
				$pdf->Cell( $widths[ $i ], 7, self::t( $h ), 'B', 0, $align[ $i ] );
			}
			$pdf->Ln();
			self::color( $pdf, self::INK );
			foreach ( $sec['rows'] as $r ) {
				$bold = ! empty( $r['_b'] );
				unset( $r['_b'] );
				$pdf->SetFont( 'Helvetica', $bold ? 'B' : '', 9 );
				foreach ( array_values( $r ) as $i => $v ) {
					if ( $i > 0 && is_numeric( $v ) && ! preg_match( '/^0\d/', (string) $v ) ) {
						$v = $v < 0 ? '(' . number_format( abs( $v ) ) . ')' : number_format( (float) $v );
					}
					$pdf->Cell( $widths[ $i ], 6.5, self::t( mb_strimwidth( (string) $v, 0, (int) ( $widths[ $i ] * 0.55 ), '…' ) ), $bold ? 'T' : 0, 0, $align[ $i ] );
				}
				$pdf->Ln();
			}
			$pdf->Ln( 4 );
		}
		return $pdf->Output( 'S' );
	}
}
