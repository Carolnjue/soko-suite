<?php
/**
 * Minimal Excel (.xlsx) writer: several sheets, bold header row, real numbers, column widths.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_XLSX {

	private static function col( $i ) {
		$s = '';
		$i++;
		while ( $i > 0 ) {
			$m = ( $i - 1 ) % 26;
			$s = chr( 65 + $m ) . $s;
			$i = (int) ( ( $i - $m ) / 26 );
		}
		return $s;
	}

	private static function x( $v ) {
		return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * @param array $sheets array( 'Sheet name' => array( 'head' => array(), 'rows' => array( array() ) ) )
	 * @return string binary xlsx, or '' when ZipArchive is missing.
	 */
	public static function build( $sheets ) {
		$zip = new Soko_Zip();
		$names = array_keys( $sheets );
		$ct    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		$wb    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
		$rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		foreach ( $names as $i => $name ) {
			$n   = $i + 1;
			$ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$wb .= '<sheet name="' . self::x( substr( preg_replace( '/[\\\\\/\?\*\[\]:]/', ' ', $name ), 0, 31 ) ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
			$zip->addFromString( 'xl/worksheets/sheet' . $n . '.xml', self::sheet( $sheets[ $name ] ) );
		}
		$ct   .= '</Types>';
		$wb   .= '</sheets></workbook>';
		$rels .= '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
		$zip->addFromString( '[Content_Types].xml', $ct );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', $wb );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $rels );
		$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1B4F9E"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="4"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf numFmtId="164" applyNumberFormat="1"/><xf fontId="0" applyFont="1"/></cellXfs></styleSheet>' );
		return $zip->bytes();
	}

	private static function sheet( $s ) {
		$rows  = array_merge( array( $s['head'] ), $s['rows'] );
		$width = array();
		$xml   = '';
		foreach ( $rows as $r => $row ) {
			$xml .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( array_values( (array) $row ) as $c => $v ) {
				$ref         = self::col( $c ) . ( $r + 1 );
				$width[ $c ] = max( isset( $width[ $c ] ) ? $width[ $c ] : 8, min( 60, mb_strlen( (string) $v ) + 2 ) );
				if ( 0 !== $r && is_numeric( $v ) && ! preg_match( '/^0\d/', (string) $v ) ) {
					$xml .= '<c r="' . $ref . '" s="2"><v>' . ( $v + 0 ) . '</v></c>';
				} else {
					$xml .= '<c r="' . $ref . '" t="inlineStr"' . ( 0 === $r ? ' s="1"' : '' ) . '><is><t>' . self::x( $v ) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		$cols = '<cols>';
		foreach ( $width as $c => $w ) {
			$cols .= '<col min="' . ( $c + 1 ) . '" max="' . ( $c + 1 ) . '" width="' . $w . '" customWidth="1"/>';
		}
		$cols .= '</cols>';
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' . $cols . '<sheetData>' . $xml . '</sheetData></worksheet>';
	}
}


/** Minimal in-memory ZIP writer (deflate when available), so exports work on any host. */
class Soko_Zip {
	private $files = array();

	public function addFromString( $name, $data ) {
		$this->files[] = array( $name, (string) $data );
	}

	public function bytes() {
		$out = '';
		$cd  = '';
		foreach ( $this->files as $f ) {
			list( $name, $data ) = $f;
			$crc    = crc32( $data );
			$method = 0;
			$body   = $data;
			if ( function_exists( 'gzdeflate' ) ) {
				$z = gzdeflate( $data, 6 );
				if ( false !== $z ) {
					$method = 8;
					$body   = $z;
				}
			}
			$off  = strlen( $out );
			$head = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0, $method, 0, 0x21, $crc, strlen( $body ), strlen( $data ), strlen( $name ), 0 );
			$out .= $head . $name . $body;
			$cd  .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, 0, 0x21, $crc, strlen( $body ), strlen( $data ), strlen( $name ), 0, 0, 0, 0, 0, $off ) . $name;
		}
		$n = count( $this->files );
		return $out . $cd . pack( 'VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen( $cd ), strlen( $out ), 0 );
	}
}
