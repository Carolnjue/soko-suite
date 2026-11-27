<?php
/**
 * Business logic: products, customers, documents, payments and stock.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Ledger {

	/* ------------------------------------------------------------ products */

	/** Storefront mode: stock shown to customers excludes stock held for unpaid web orders. */
	public static $storefront = false;
	private static $held_cache = null;

	/** Units held for unpaid website orders placed in the last 48 hours, by product. */
	public static function held() {
		if ( null !== self::$held_cache ) {
			return self::$held_cache;
		}
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', strtotime( soko_now() . ' -48 hours' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT l.product_id AS pid, SUM(l.qty) AS q FROM ' . soko_t( 'lines' ) . ' l JOIN ' . soko_t( 'docs' ) . " d ON d.id = l.doc_id WHERE d.type = 'invoice' AND d.source = 'web' AND d.status <> 'void' AND d.paid < d.total AND d.fulfil <> 'delivered' AND COALESCE(d.meta,'') NOT LIKE %s AND d.created >= %s AND l.product_id > 0 GROUP BY l.product_id", '%stock_done%', $since ), ARRAY_A ); // phpcs:ignore
		self::$held_cache = array();
		foreach ( (array) $rows as $x ) {
			self::$held_cache[ (int) $x['pid'] ] = (float) $x['q'];
		}
		return self::$held_cache;
	}

	public static function product_row( $r ) {
		if ( ! $r ) {
			return null;
		}
		$r          = (array) $r;
		if ( 'product' === ( isset( $r['kind'] ) ? $r['kind'] : '' ) ) {
			$h           = self::held();
			$r['held']   = isset( $h[ (int) $r['id'] ] ) ? $h[ (int) $r['id'] ] : 0;
			$r['available'] = max( 0, (float) $r['stock'] - $r['held'] );
			if ( self::$storefront ) {
				$r['stock'] = $r['available'];
			}
		}
		$r['specs'] = soko_json( $r['specs'] );
		$r['images'] = soko_abs( soko_json( $r['images'] ) );
		if ( $r['images'] && 1 === count( $r['images'] ) && false !== strpos( $r['images'][0], '/assets/seed/prod-' ) ) {
			$cat0 = soko_category( $r['cat'] );
			if ( ! empty( $cat0['gallery'] ) && count( $cat0['gallery'] ) > 1 ) {
				$r['images'] = array(); // sample photo shared by several products: rotate through the category gallery
			}
		}
		if ( ! $r['images'] ) {
			$cat = soko_category( $r['cat'] );
			if ( ! empty( $cat['gallery'] ) ) {
				$g             = array_values( (array) $cat['gallery'] );
				$r['images']   = array( $g[ (int) $r['id'] % count( $g ) ] );
				$r['cat_image'] = 1;
			} elseif ( ! empty( $cat['image'] ) ) {
				$r['images']   = array( $cat['image'] );
				$r['cat_image'] = 1;
			}
		}
		foreach ( array( 'id', 'featured', 'visible', 'vatable', 'sort' ) as $k ) {
			$r[ $k ] = (int) $r[ $k ];
		}
		foreach ( array( 'price', 'was', 'cost', 'stock', 'reorder', 'watts', 'kwh', 'install_fee' ) as $k ) {
			$r[ $k ] = (float) $r[ $k ];
		}
		$c           = soko_category( $r['cat'] );
		$r['cat_name'] = $c['name'];
		$r['icon']   = $c['icon'];
		$r['url']    = home_url( '/product/' . $r['slug'] . '/' );
		if ( ! $r['install_fee'] && 'product' === $r['kind'] && $r['price'] >= 9000 ) {
			$r['install_fee'] = max( 2500, round( $r['price'] * (float) soko_setting( 'install_rate', 8 ) / 100 / 500 ) * 500 );
		}
		return $r;
	}

	public static function products( $args = array() ) {
		global $wpdb;
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['visible'] ) ) {
			$where[] = 'visible = 1';
		}
		if ( ! empty( $args['cat'] ) ) {
			$where[] = 'cat = %s';
			$vals[]  = $args['cat'];
		}
		if ( ! empty( $args['ids'] ) ) {
			$ids     = array_map( 'intval', (array) $args['ids'] );
			$where[] = 'id IN (' . implode( ',', $ids ?: array( 0 ) ) . ')';
		}
		if ( ! empty( $args['q'] ) ) {
			// Common local spellings: "invertor", "betri", "panal", "charge controler".
			$args['q'] = preg_replace( array( '/invertor/i', '/\bbet(t)?r(i|y)\b/i', '/panal/i', '/controler/i', '/bateri/i', '/camera?s?\b/i' ), array( 'inverter', 'batter', 'panel', 'controller', 'batter', 'cam' ), trim( $args['q'] ) );
			$syn   = preg_match( '/repair|fault|fix|broken|service|not working/i', $args['q'] );
			$cats  = soko_categories();
			$stop  = array( 'and', 'for', 'the', 'with', 'of', 'a', 'an', 'in', 'to', 'solar' );
			$words = array_values( array_filter( preg_split( '/[\s,\/]+/', strtolower( $args['q'] ) ), function ( $w ) { return '' !== $w; } ) );
			if ( count( $words ) > 1 ) {
				$words = array_values( array_diff( $words, $stop ) ) ?: $words; // "solar kit" still finds kits
			}
			$parts = array();
			foreach ( array_slice( $words, 0, 6 ) as $w ) {
				// Plurals: panels, batteries, inverters, heaters, "5kw" and "5 kw".
				$stem = preg_replace( array( '/ies$/', '/(ches|shes|xes|ses)$/', '/s$/' ), array( 'y', '', '' ), $w );
				$stem = preg_replace( '/^(\d+(?:\.\d+)?)\s*(kw|kva|kwh|w|ah|v)$/', '$1 $2', $stem );
				$stem = strlen( $stem ) >= 3 || is_numeric( $stem ) ? $stem : $w;
				if ( 'battery' === $stem ) {
					$stem = 'batter';
				}
				$alts = array_unique( array( $stem, str_replace( ' ', '', $stem ) ) );
				$or   = array();
				foreach ( $alts as $a ) {
					$lk  = '%' . $wpdb->esc_like( $a ) . '%';
					$or[] = 'name LIKE %s OR sku LIKE %s OR brand LIKE %s OR runs LIKE %s OR description LIKE %s OR specs LIKE %s';
					array_push( $vals, $lk, $lk, $lk, $lk, $lk, $lk );
				}
				$cm = array();
				foreach ( $cats as $c ) {
					if ( false !== stripos( $c['name'], $stem ) || false !== stripos( $c['id'], $stem ) ) {
						$cm[] = $c['id'];
					}
				}
				if ( $cm ) {
					$or[] = 'cat IN (' . implode( ',', array_fill( 0, count( $cm ), '%s' ) ) . ')';
					$vals = array_merge( $vals, $cm );
				}
				if ( $syn ) {
					$or[] = 'name LIKE %s OR name LIKE %s';
					array_push( $vals, '%maintenance%', '%survey%' );
				}
				$parts[] = '(' . implode( ' OR ', $or ) . ')';
			}
			if ( $parts ) {
				$where[] = '(' . implode( ' AND ', $parts ) . ')';
			}
		}
		$order = 'sort ASC, id ASC';
		if ( ! empty( $args['sort'] ) ) {
			$map   = array( 'lo' => 'price ASC', 'hi' => 'price DESC', 'az' => 'name ASC', 'new' => 'id DESC' );
			$order = isset( $map[ $args['sort'] ] ) ? $map[ $args['sort'] ] : $order;
		}
		$sql = 'SELECT * FROM ' . soko_t( 'products' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY ' . $order;
		$rows = $vals ? $wpdb->get_results( $wpdb->prepare( $sql, $vals ) ) : $wpdb->get_results( $sql ); // phpcs:ignore
		return array_map( array( __CLASS__, 'product_row' ), $rows );
	}

	public static function product( $id_or_slug ) {
		global $wpdb;
		$t = soko_t( 'products' );
		$r = is_numeric( $id_or_slug )
			? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id_or_slug ) )
			: $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE slug = %s", $id_or_slug ) );
		return self::product_row( $r );
	}

	public static function product_by_sku( $sku ) {
		global $wpdb;
		$t = soko_t( 'products' );
		return self::product_row( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE sku = %s", $sku ) ) );
	}

	public static function save_product( $d, $id = 0 ) {
		global $wpdb;
		$fields = array( 'sku', 'name', 'cat', 'brand', 'kind', 'price', 'was', 'cost', 'reorder', 'unit', 'runs', 'description', 'warranty', 'tag', 'watts', 'kwh', 'install_fee', 'featured', 'visible', 'vatable', 'sort' );
		$row    = array();
		foreach ( $fields as $f ) {
			if ( isset( $d[ $f ] ) ) {
				$row[ $f ] = is_string( $d[ $f ] ) ? ( 'description' === $f ? wp_kses_post( $d[ $f ] ) : sanitize_text_field( $d[ $f ] ) ) : $d[ $f ];
			}
		}
		if ( isset( $d['specs'] ) ) {
			$specs = array();
			foreach ( (array) $d['specs'] as $k => $v ) {
				if ( is_array( $v ) && isset( $v['k'] ) ) {
					$k = $v['k'];
					$v = $v['v'];
				}
				if ( '' !== trim( (string) $k ) ) {
					$specs[ sanitize_text_field( $k ) ] = sanitize_text_field( $v );
				}
			}
			$row['specs'] = wp_json_encode( $specs );
		}
		if ( isset( $d['images'] ) ) {
			$row['images'] = wp_json_encode( soko_rel( array_values( array_filter( array_map( 'esc_url_raw', soko_abs( (array) $d['images'] ) ) ) ) ) );
		}
		$row['updated'] = soko_now();
		if ( isset( $d['name'] ) && ( ! $id || ! empty( $d['reslug'] ) ) ) {
			$row['slug'] = self::unique_slug( sanitize_title( $d['name'] ), $id );
		}
		if ( $id ) {
			$wpdb->update( soko_t( 'products' ), $row, array( 'id' => $id ) );
		} else {
			$row['created'] = soko_now();
			$row['stock']   = isset( $d['stock'] ) ? (float) $d['stock'] : 0;
			$wpdb->insert( soko_t( 'products' ), $row );
			$id = (int) $wpdb->insert_id;
			if ( $row['stock'] ) {
				self::move( $id, $row['stock'], 'Opening stock', '' , false );
			}
		}
		if ( $id && isset( $d['stock'] ) && isset( $d['_adjust_reason'] ) ) {
			$cur = self::product( $id );
			$diff = (float) $d['stock'] - $cur['stock'];
			if ( $diff ) {
				self::move( $id, $diff, sanitize_text_field( $d['_adjust_reason'] ), 'Adjustment' );
			}
		}
		soko_log( 'Saved product', 'product', $id );
		return self::product( $id );
	}

	private static function unique_slug( $slug, $id ) {
		global $wpdb;
		$t    = soko_t( 'products' );
		$base = $slug ? $slug : 'product';
		$i    = 2;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE slug = %s AND id <> %d", $slug, $id ) ) ) {
			$slug = $base . '-' . $i++;
		}
		return $slug;
	}

	/** Record a stock movement and update the product. */
	public static function move( $product_id, $qty, $reason, $ref = '', $apply = true, $cost = null, $date = null ) {
		global $wpdb;
		$wpdb->insert(
			soko_t( 'moves' ),
			array(
				'product_id' => $product_id,
				'qty'        => $qty,
				'reason'     => $reason,
				'ref'        => $ref,
				'cost'       => null !== $cost ? (float) $cost : (float) $wpdb->get_var( $wpdb->prepare( 'SELECT cost FROM ' . soko_t( 'products' ) . ' WHERE id = %d', $product_id ) ), // phpcs:ignore
				'user_id'    => get_current_user_id(),
				'created'    => $date && substr( $date, 0, 10 ) !== soko_today() ? substr( $date, 0, 10 ) . ' 12:00:00' : soko_now(),
			)
		);
		if ( $apply ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . soko_t( 'products' ) . ' SET stock = stock + %f WHERE id = %d', $qty, $product_id ) ); // phpcs:ignore
		}
	}

	/* ----------------------------------------------------------- customers */

	public static function customer( $id ) {
		global $wpdb;
		$t = soko_t( 'customers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A );
	}

	/** Find a customer by phone or create one. */
	public static function customer_for( $d ) {
		global $wpdb;
		$t     = soko_t( 'customers' );
		$phone = soko_phone( isset( $d['phone'] ) ? $d['phone'] : '' );
		if ( $phone ) {
			$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE phone = %s", $phone ) );
			if ( $id ) {
				$upd  = array();
				$acct = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore
				$same = empty( $d['name'] ) || 0 === strcasecmp( trim( $d['name'] ), trim( (string) $acct['name'] ) );
				foreach ( array( 'email', 'kra', 'county', 'address' ) as $k ) {
					// Someone else on a shared phone: fill gaps only, never replace the account holder's details.
					if ( ! $same && ! empty( $acct[ $k ] ) ) {
						continue;
					}
					if ( ! empty( $d[ $k ] ) && ( 'email' !== $k || is_email( $d[ $k ] ) ) ) {
						$upd[ $k ] = sanitize_text_field( $d[ $k ] );
					}
				}
				if ( $upd ) {
					$wpdb->update( $t, $upd, array( 'id' => $id ) );
				}
				return (int) $id;
			}
		}
		return self::save_customer( array_merge( $d, array( 'phone' => $phone ) ) );
	}

	public static function save_customer( $d, $id = 0 ) {
		global $wpdb;
		$row = array();
		foreach ( array( 'name', 'type', 'phone', 'email', 'kra', 'county', 'address', 'notes' ) as $k ) {
			if ( isset( $d[ $k ] ) ) {
				$row[ $k ] = 'notes' === $k ? sanitize_textarea_field( $d[ $k ] ) : sanitize_text_field( $d[ $k ] );
			}
		}
		if ( isset( $row['phone'] ) ) {
			$row['phone'] = soko_phone( $row['phone'] );
		}
		if ( isset( $row['kra'] ) ) {
			$row['kra'] = strtoupper( $row['kra'] );
		}
		if ( $id ) {
			$wpdb->update( soko_t( 'customers' ), $row, array( 'id' => $id ) );
			return (int) $id;
		}
		$row['created'] = soko_now();
		if ( empty( $row['name'] ) ) {
			$row['name'] = 'Walk-in customer';
		}
		$wpdb->insert( soko_t( 'customers' ), $row );
		return (int) $wpdb->insert_id;
	}

	/* ----------------------------------------------------------- documents */

	public static function doc( $id_or_number ) {
		global $wpdb;
		$t = soko_t( 'docs' );
		$d = is_numeric( $id_or_number )
			? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id_or_number ), ARRAY_A )
			: $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE number = %s", $id_or_number ), ARRAY_A );
		return $d ? self::doc_out( $d ) : null;
	}

	public static function doc_out( $d, $with_lines = true ) {
		global $wpdb;
		foreach ( array( 'subtotal', 'vat', 'total', 'paid' ) as $k ) {
			$d[ $k ] = (float) $d[ $k ];
		}
		$d['id']          = (int) $d['id'];
		$d['customer_id'] = (int) $d['customer_id'];
		$d['parent_id']   = (int) $d['parent_id'];
		$d['meta']        = soko_json( $d['meta'] );
		$d['balance']     = 'void' === $d['status'] || 'invoice' !== $d['type'] ? 0 : max( 0, round( $d['total'] - $d['paid'], 2 ) );
		$d['state']       = self::state( $d );
		if ( 'invoice' === $d['type'] ) {
			$d['credited'] = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(total),0) FROM ' . soko_t( 'docs' ) . " WHERE type = 'credit' AND status <> 'void' AND parent_id = %d", $d['id'] ) ); // phpcs:ignore
		}
		if ( $with_lines && 'invoice' === $d['type'] ) {
			$d['job_id'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . soko_t( 'jobs' ) . ' WHERE doc_id = %d ORDER BY id LIMIT 1', $d['id'] ) ); // phpcs:ignore
		}
		if ( $with_lines && 'invoice' === $d['type'] && $d['customer_id'] && $d['balance'] > 0 ) {
			$d['customer_credit'] = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(paid - total) FROM ' . soko_t( 'docs' ) . " WHERE type = 'invoice' AND status <> 'void' AND customer_id = %d AND paid > total", $d['customer_id'] ) ); // phpcs:ignore
		}
		if ( $with_lines ) {
			$d['lines'] = $wpdb->get_results( $wpdb->prepare( 'SELECT l.*, p.kind FROM ' . soko_t( 'lines' ) . ' l LEFT JOIN ' . soko_t( 'products' ) . ' p ON p.id = l.product_id WHERE l.doc_id = %d ORDER BY l.sort, l.id', $d['id'] ), ARRAY_A ); // phpcs:ignore
			foreach ( $d['lines'] as &$l ) {
				foreach ( array( 'qty', 'price', 'cost' ) as $k ) {
					$l[ $k ] = (float) $l[ $k ];
				}
				$l['product_id'] = (int) $l['product_id'];
				$l['vatable']    = (int) $l['vatable'];
			}
			$d['customer'] = $d['customer_id'] ? self::customer( $d['customer_id'] ) : null;
			if ( ! empty( $d['meta']['contact'] ) && ! empty( $d['customer'] ) ) {
				$d['customer']['account_name'] = $d['customer']['name'];
				$d['customer']['name']         = $d['meta']['contact'];
				if ( array_key_exists( 'contact_email', $d['meta'] ) ) {
					$d['customer']['email'] = $d['meta']['contact_email'];
				}
				if ( array_key_exists( 'contact_address', $d['meta'] ) ) {
					$d['customer']['address'] = $d['meta']['contact_address'];
					$d['customer']['county']  = '';
				}
			}
		}
		$d['pay_url'] = home_url( '/track/?doc=' . rawurlencode( $d['number'] ) . '&t=' . $d['token'] );
		return $d;
	}

	/** Human state used for badges and filters. */
	public static function state( $d ) {
		if ( 'invoice' === $d['type'] ) {
			if ( 'void' === $d['status'] ) {
				return 'void';
			}
			if ( $d['total'] > 0 && $d['paid'] >= $d['total'] - 0.5 ) {
				return 'paid';
			}
			if ( $d['due'] && $d['due'] < soko_today() ) {
				return 'overdue';
			}
			return $d['paid'] > 0 ? 'partial' : 'unpaid';
		}
		if ( 'quote' === $d['type'] && in_array( $d['status'], array( 'draft', 'sent' ), true ) && $d['due'] && $d['due'] < soko_today() ) {
			return 'expired';
		}
		return $d['status'];
	}

	/** Totals from lines. Prices are VAT-inclusive by default. */
	public static function totals( $lines ) {
		$rate  = soko_setting( 'vat_registered' ) ? (float) soko_setting( 'vat_rate', 16 ) : 0;
		$incl  = (int) soko_setting( 'prices_incl_vat', 1 );
		$gross = 0;
		$vat   = 0;
		foreach ( $lines as $l ) {
			$amt = (float) $l['qty'] * (float) $l['price'];
			if ( $incl ) {
				$gross += $amt;
				$vat   += ! empty( $l['vatable'] ) ? $amt * $rate / ( 100 + $rate ) : 0;
			} else {
				$v      = ! empty( $l['vatable'] ) ? $amt * $rate / 100 : 0;
				$gross += $amt + $v;
				$vat   += $v;
			}
		}
		return array( 'total' => round( $gross, 2 ), 'vat' => round( $vat, 2 ), 'subtotal' => round( $gross - $vat, 2 ) );
	}

	/**
	 * Create or update a quote or invoice.
	 * $d: type, customer_id or customer{}, lines[{product_id,description,qty,price}], date, due, notes, source, meta
	 */
	public static function save_doc( $d, $id = 0 ) {
		global $wpdb;
		$type = isset( $d['type'] ) ? $d['type'] : 'invoice';
		if ( empty( $d['customer_id'] ) && ! empty( $d['customer'] ) ) {
			$d['customer_id'] = self::customer_for( $d['customer'] );
			// A shared or family phone: keep the name this order was placed under.
			$typed = trim( sanitize_text_field( isset( $d['customer']['name'] ) ? $d['customer']['name'] : '' ) );
			$known = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM ' . soko_t( 'customers' ) . ' WHERE id = %d', $d['customer_id'] ) ); // phpcs:ignore
			if ( $typed && strcasecmp( $typed, $known ) ) {
				$d['meta'] = array_merge(
					isset( $d['meta'] ) ? (array) $d['meta'] : array(),
					array(
						'contact'         => $typed,
						'contact_email'   => isset( $d['customer']['email'] ) && is_email( $d['customer']['email'] ) ? sanitize_email( $d['customer']['email'] ) : '',
						'contact_address' => trim( sanitize_text_field( ( isset( $d['customer']['address'] ) ? $d['customer']['address'] : '' ) . ( ! empty( $d['customer']['county'] ) ? ', ' . $d['customer']['county'] : '' ) ), ', ' ),
					)
				);
			}
		}
		// When editing, keep what was not sent, and keep the cost each item was sold at.
		$prev      = $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . soko_t( 'docs' ) . ' WHERE id = %d', $id ), ARRAY_A ) : null; // phpcs:ignore
		$old_costs = array();
		if ( $prev ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, cost FROM ' . soko_t( 'lines' ) . ' WHERE doc_id = %d', $id ), ARRAY_A ) as $ol ) { // phpcs:ignore
				if ( (float) $ol['cost'] > 0 ) {
					$old_costs[ (int) $ol['product_id'] ] = (float) $ol['cost'];
				}
			}
			foreach ( array( 'customer_id', 'date', 'due' ) as $k ) {
				if ( empty( $d[ $k ] ) ) {
					$d[ $k ] = $prev[ $k ];
				}
			}
		}
		$lines = array();
		$i     = 0;
		foreach ( (array) ( isset( $d['lines'] ) ? $d['lines'] : array() ) as $l ) {
			$pid  = isset( $l['product_id'] ) ? (int) $l['product_id'] : 0;
			$prod = $pid ? self::product( $pid ) : null;
			$qty  = isset( $l['qty'] ) ? (float) $l['qty'] : 1;
			if ( $qty <= 0 ) {
				continue;
			}
			$lines[] = array(
				'product_id'  => $pid,
				'description' => sanitize_text_field( ! empty( $l['description'] ) ? $l['description'] : ( $prod ? $prod['name'] : 'Item' ) ),
				'qty'         => $qty,
				'price'       => isset( $l['price'] ) && '' !== $l['price'] ? (float) $l['price'] : ( $prod ? $prod['price'] : 0 ),
				'cost'        => $pid && isset( $old_costs[ $pid ] ) ? $old_costs[ $pid ] : ( $prod ? $prod['cost'] : ( isset( $l['cost'] ) ? (float) $l['cost'] : 0 ) ),
				'vatable'     => $prod ? $prod['vatable'] : ( isset( $l['vatable'] ) ? (int) $l['vatable'] : 1 ),
				'sort'        => $i++,
			);
		}
		$tot = self::totals( $lines );
		$row = array(
			'type'        => $type,
			'customer_id' => isset( $d['customer_id'] ) ? (int) $d['customer_id'] : 0,
			'date'        => ! empty( $d['date'] ) ? sanitize_text_field( $d['date'] ) : soko_today(),
			'subtotal'    => $tot['subtotal'],
			'vat'         => $tot['vat'],
			'total'       => $tot['total'],
			'notes'       => isset( $d['notes'] ) ? sanitize_textarea_field( $d['notes'] ) : '',
			'updated'     => soko_now(),
		);
		$days       = 'quote' === $type ? (int) soko_setting( 'quote_valid', 14 ) : (int) soko_setting( 'invoice_due', 14 );
		$row['due'] = ! empty( $d['due'] ) && ( ! $prev || $prev['date'] === $row['date'] || $d['due'] !== $prev['due'] ) ? sanitize_text_field( $d['due'] ) : gmdate( 'Y-m-d', strtotime( $row['date'] . ' +' . $days . ' days' ) );
		if ( isset( $d['meta'] ) ) {
			$row['meta'] = wp_json_encode( $d['meta'] );
		}
		if ( $id ) {
			$wpdb->update( soko_t( 'docs' ), $row, array( 'id' => $id ) );
			$wpdb->delete( soko_t( 'lines' ), array( 'doc_id' => $id ) );
		} else {
			$row['number']     = Soko_Settings::next_number( $type );
			$row['status']     = isset( $d['status'] ) ? sanitize_key( $d['status'] ) : ( 'quote' === $type ? 'sent' : 'issued' );
			$row['source']     = isset( $d['source'] ) ? sanitize_key( $d['source'] ) : 'desk';
			$row['parent_id']  = isset( $d['parent_id'] ) ? (int) $d['parent_id'] : 0;
			$row['token']      = wp_generate_password( 20, false );
			$row['created_by'] = get_current_user_id();
			$row['created']    = soko_now();
			$row['demo']       = ! empty( $d['demo'] ) ? 1 : 0;
			if ( ! empty( $d['paid'] ) ) {
				$row['paid'] = (float) $d['paid'];
			}
			$wpdb->insert( soko_t( 'docs' ), $row );
			$id = (int) $wpdb->insert_id;
		}
		foreach ( $lines as $l ) {
			$l['doc_id'] = $id;
			$wpdb->insert( soko_t( 'lines' ), $l );
		}
		$doc = self::doc( $id );
		// A website order is only a request until the team confirms it or it is paid.
		if ( 'invoice' === $type && 'issued' === $doc['status'] && ( 'web' !== $doc['source'] || (float) $doc['paid'] > 0 || in_array( (string) $doc['fulfil'], array( 'to_pack', 'packed', 'dispatched', 'delivered' ), true ) ) ) {
			Soko_Etims::queue( $doc );
		}
		soko_log( ( $id ? 'Saved ' : 'Created ' ) . ( 'quote' === $type ? 'quotation' : $type ) . ' ' . $doc['number'], $type, $doc['number'] );
		return $doc;
	}

	public static function set_status( $id, $status ) {
		global $wpdb;
		$upd = array( 'status' => sanitize_key( $status ), 'updated' => soko_now() );
		if ( 'void' === $status ) {
			$cur = $wpdb->get_var( $wpdb->prepare( 'SELECT etims_status FROM ' . soko_t( 'docs' ) . ' WHERE id = %d', $id ) ); // phpcs:ignore
			if ( 'signed' !== $cur ) {
				$upd['etims_status'] = ''; // a cancelled invoice is not sent to eTIMS
			}
		}
		$wpdb->update( soko_t( 'docs' ), $upd, array( 'id' => $id ) );
		$d = self::doc( $id );
		soko_log( 'Marked ' . $d['number'] . ' ' . $status, $d['type'], $d['number'] );
		return $d;
	}

	/** Turn an accepted quote into an invoice. */
	public static function convert( $quote_id, $deposit = false, $date = '' ) {
		$q = self::doc( $quote_id );
		if ( ! $q || 'quote' !== $q['type'] ) {
			return new WP_Error( 'soko', 'That is not a quotation.' );
		}
		if ( ! empty( $q['meta']['invoice_id'] ) ) {
			return self::doc( $q['meta']['invoice_id'] );
		}
		$inv = self::save_doc(
			array(
				'type'        => 'invoice',
				'customer_id' => $q['customer_id'],
				'date'        => $date ? $date : soko_today(),
				'lines'       => $q['lines'],
				'notes'       => $q['notes'],
				'parent_id'   => $q['id'],
				'meta'        => array_merge( array( 'quote' => $q['number'], 'deposit' => $deposit ? (int) soko_setting( 'deposit_percent', 60 ) : 0 ), self::contact_meta( $q ) ),
			)
		);
		global $wpdb;
		$meta               = $q['meta'];
		$meta['invoice_id'] = $inv['id'];
		$wpdb->update( soko_t( 'docs' ), array( 'status' => 'accepted', 'meta' => wp_json_encode( $meta ) ), array( 'id' => $q['id'] ) );
		return $inv;
	}

	/* ------------------------------------------------------------ payments */

	/** Normalise an account reference for matching: INV 1046, inv1046, 1046 → INV1046. */
	private static function norm( $s ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $s ) );
	}

	/** Find the invoice a payment belongs to. */
	public static function match_invoice( $account_ref, $phone = '', $amount = 0 ) {
		global $wpdb;
		$t   = soko_t( 'docs' );
		$ref = self::norm( $account_ref );
		if ( $ref ) {
			$rows = $wpdb->get_results( "SELECT id, number FROM $t WHERE type = 'invoice' AND status <> 'void' ORDER BY id DESC LIMIT 2000", ARRAY_A ); // phpcs:ignore
			foreach ( $rows as $r ) {
				$n = self::norm( $r['number'] );
				if ( $n === $ref ) {
					return (int) $r['id'];
				}
			}
			// A reference quoting a quotation pays the invoice made from it, never an unrelated invoice with the same digits.
			foreach ( $wpdb->get_results( "SELECT number, meta FROM $t WHERE type = 'quote' ORDER BY id DESC LIMIT 2000", ARRAY_A ) as $q ) { // phpcs:ignore
				if ( self::norm( $q['number'] ) === $ref ) {
					$qm = json_decode( (string) $q['meta'], true );
					return ! empty( $qm['invoice_id'] ) ? (int) $qm['invoice_id'] : 0;
				}
			}
			$digits  = preg_replace( '/\D/', '', $ref );
			$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
			$prefix  = preg_replace( '/[^A-Z]/', '', strtoupper( (string) soko_setting( 'prefix_invoice', 'INV' ) ) );
			if ( strlen( $digits ) >= 3 && ( '' === $letters || $letters === $prefix ) ) {
				foreach ( $rows as $r ) {
					if ( preg_replace( '/\D/', '', $r['number'] ) === $digits ) {
						return (int) $r['id'];
					}
				}
			}
		}
		$phone = soko_phone( $phone );
		if ( $phone && strlen( $phone ) >= 12 ) {
			$c = soko_t( 'customers' );
			$open = $wpdb->get_results( $wpdb->prepare( "SELECT d.id, d.total, d.paid FROM $t d JOIN $c c ON c.id = d.customer_id WHERE d.type = 'invoice' AND d.status <> 'void' AND d.paid < d.total AND c.phone = %s", $phone ), ARRAY_A ); // phpcs:ignore
			if ( 1 === count( $open ) ) {
				return (int) $open[0]['id'];
			}
			foreach ( $open as $o ) {
				if ( abs( ( $o['total'] - $o['paid'] ) - $amount ) < 1 ) {
					return (int) $o['id'];
				}
			}
		}
		return 0;
	}

	/**
	 * Record an incoming payment (M-Pesa confirmation, STK result, bank, cash).
	 * Matches it, applies it, issues a receipt. Idempotent on code.
	 */
	public static function payment_exists( $code ) {
		global $wpdb;
		$t = soko_t( 'payments' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE code = %s", strtoupper( sanitize_text_field( $code ) ) ) );
	}

	public static function record_payment( $p ) {
		global $wpdb;
		$t    = soko_t( 'payments' );
		$code = strtoupper( sanitize_text_field( isset( $p['code'] ) ? $p['code'] : '' ) );
		if ( $code ) {
			$exists = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE code = %s", $code ), ARRAY_A );
			if ( $exists ) {
				return $exists;
			}
		} else {
			$code = strtoupper( substr( $p['method'] ?? 'PAY', 0, 3 ) ) . '-' . strtoupper( wp_generate_password( 8, false ) );
		}
		$row = array(
			'method'      => isset( $p['method'] ) ? sanitize_key( $p['method'] ) : 'mpesa',
			'code'        => $code,
			'amount'      => (float) $p['amount'],
			'phone'       => soko_phone( isset( $p['phone'] ) ? $p['phone'] : '' ),
			'payer'       => sanitize_text_field( isset( $p['payer'] ) ? $p['payer'] : '' ),
			'account_ref' => sanitize_text_field( isset( $p['account_ref'] ) ? $p['account_ref'] : '' ),
			'checkout_id' => sanitize_text_field( isset( $p['checkout_id'] ) ? $p['checkout_id'] : '' ),
			'raw'         => isset( $p['raw'] ) ? wp_json_encode( $p['raw'] ) : '',
			'received'    => ! empty( $p['received'] ) ? $p['received'] : soko_now(),
			'status'      => 'unmatched',
			'demo'        => ! empty( $p['demo'] ) ? 1 : 0,
		);
		$wpdb->insert( $t, $row );
		$pid    = (int) $wpdb->insert_id;
		$doc_id = ! empty( $p['doc_id'] ) ? (int) $p['doc_id'] : self::match_invoice( $row['account_ref'], $row['phone'], $row['amount'] );
		if ( $doc_id ) {
			self::apply_payment( $pid, $doc_id );
		} else {
			soko_log( 'Unmatched payment ' . $code . ' ' . soko_money( $row['amount'] ), 'payment', $code );
			Soko_Cron::notify( 'Unmatched payment ' . $code, sprintf( "A payment of %s from %s (%s), reference \"%s\", could not be matched to an invoice.\n\nOpen Dynamic Desk → Payments to match it.", soko_money( $row['amount'] ), $row['payer'], soko_phone_display( $row['phone'] ), $row['account_ref'] ) );
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $pid ), ARRAY_A );
	}

	/** Apply a stored payment to an invoice and issue a receipt. */
	public static function apply_payment( $payment_id, $doc_id ) {
		global $wpdb;
		$pt  = soko_t( 'payments' );
		$pay = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $pt WHERE id = %d", $payment_id ), ARRAY_A );
		$inv = self::doc( $doc_id );
		if ( ! $pay || ! $inv || 'matched' === $pay['status'] || 'void' === $pay['status'] ) {
			return new WP_Error( 'soko', 'Payment already applied or invoice missing.', array( 'status' => 400 ) );
		}
		if ( 'invoice' !== $inv['type'] || 'void' === $inv['status'] ) {
			return new WP_Error( 'soko', 'Payments can only go on an open invoice. Convert the quotation to an invoice first.', array( 'status' => 400 ) );
		}
		$lk = Soko_Accounts::locked( substr( $pay['received'], 0, 10 ) );
		if ( $lk ) {
			return $lk;
		}
		$was_paid = 'paid' === $inv['state'];
		$paid     = (float) $inv['paid'] + (float) $pay['amount'];
		$wpdb->update( soko_t( 'docs' ), array( 'paid' => $paid, 'updated' => soko_now() ), array( 'id' => $inv['id'] ) );
		$rnum = Soko_Settings::next_number( 'receipt' );
		$wpdb->insert(
			soko_t( 'docs' ),
			array(
				'type'        => 'receipt',
				'number'      => $rnum,
				'customer_id' => $inv['customer_id'],
				'status'      => 'issued',
				'source'      => $pay['method'],
				'date'        => substr( $pay['received'], 0, 10 ),
				'subtotal'    => $pay['amount'],
				'total'       => $pay['amount'],
				'paid'        => $pay['amount'],
				'parent_id'   => $inv['id'],
				'meta'        => wp_json_encode( array_merge( array( 'invoice' => $inv['number'], 'code' => $pay['code'], 'method' => $pay['method'], 'balance' => max( 0, $inv['total'] - $paid ), 'credit' => max( 0, round( $paid - $inv['total'], 2 ) ) ), self::contact_meta( $inv ) ) ),
				'token'       => wp_generate_password( 20, false ),
				'demo'        => $pay['demo'],
				'created'     => soko_now(),
			)
		);
		$rid = (int) $wpdb->insert_id;
		$wpdb->update( $pt, array( 'status' => 'matched', 'doc_id' => $inv['id'], 'receipt_id' => $rid ), array( 'id' => $payment_id ) );
		$inv = self::doc( $inv['id'] );
		if ( 'issued' === $inv['status'] ) {
			Soko_Etims::queue( $inv );
		}
		if ( ! $was_paid && 'paid' === $inv['state'] ) {
			self::fulfil_stock( $inv );
			// Paying in full confirms a waiting order; it never moves a packed or sent order backwards.
			if ( 'web' === $inv['source'] && in_array( (string) $inv['fulfil'], array( '', 'awaiting_payment' ), true ) ) {
				$wpdb->update( soko_t( 'docs' ), array( 'fulfil' => 'to_pack' ), array( 'id' => $inv['id'] ) );
			}
		}
		soko_log( 'Payment ' . $pay['code'] . ' matched to ' . $inv['number'], 'payment', $pay['code'] );
		self::send_receipt( $rid );
		return array( 'invoice' => $inv, 'receipt' => self::doc( $rid ) );
	}

	/** The person an order was placed under on a shared phone, to carry onto its receipts, credit notes and jobs. */
	public static function contact_meta( $doc ) {
		$m = isset( $doc['meta'] ) && is_array( $doc['meta'] ) ? $doc['meta'] : array();
		return array_intersect_key( $m, array_flip( array( 'contact', 'contact_email', 'contact_address' ) ) );
	}

	/** Take sold items out of stock once, when an invoice is fully paid or delivered. */
	public static function fulfil_stock( $inv, $date = null ) {
		global $wpdb;
		if ( ! empty( $inv['meta']['stock_done'] ) ) {
			return;
		}
		foreach ( $inv['lines'] as $l ) {
			if ( $l['product_id'] ) {
				$p = self::product( $l['product_id'] );
				if ( $p && 'product' === $p['kind'] ) {
					// Freeze the cost of the sale: a line sold before its cost was known takes today's cost, once.
					$c = (float) $l['cost'] > 0 ? (float) $l['cost'] : (float) $p['cost'];
					if ( (float) $l['cost'] <= 0 && $c > 0 && ! empty( $l['id'] ) ) {
						$wpdb->update( soko_t( 'lines' ), array( 'cost' => $c ), array( 'id' => (int) $l['id'] ) );
					}
					self::move( $l['product_id'], -1 * $l['qty'], 'Sold', $inv['number'], true, $c, $date );
				}
			}
		}
		$meta               = $inv['meta'];
		$meta['stock_done'] = 1;
		$wpdb->update( soko_t( 'docs' ), array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $inv['id'] ) );
	}

	public static function send_receipt( $rid ) {
		$r = self::doc( $rid );
		if ( ! $r || empty( $r['customer']['email'] ) || ! empty( $r['demo'] ) ) {
			return;
		}
		self::email_doc( $r, $r['customer']['email'] );
	}

	/** A short update to the customer when their order moves on. Only for website orders with an email. */
	public static function notify_customer( $doc, $step ) {
		if ( ! $doc || empty( $doc['customer']['email'] ) || ! empty( $doc['demo'] ) || 'web' !== $doc['source'] ) {
			return false;
		}
		$pickup = isset( $doc['meta']['delivery'] ) && (int) $doc['meta']['delivery'] >= 0 && preg_match( '/pick\s?-?up|collect/i', (string) $doc['notes'] );
		$install = ! empty( $doc['meta']['install'] );
		$lines = array(
			'confirmed'  => 'We have confirmed your order and are getting it ready.',
			'to_pack'    => 'We have confirmed your order and are getting it ready.',
			'packed'     => $pickup ? 'Your order is packed and ready to collect' . ( soko_setting( 'pickup_address' ) ? ' at ' . soko_setting( 'pickup_address' ) : '' ) . '.' : 'Your order is packed and will be on its way soon.',
			'dispatched' => $install ? 'Our team is on the way to install your system.' : 'Your order is on its way to you.',
			'delivered'  => $install ? 'Your system has been installed. Thank you for choosing us.' : ( $pickup ? 'Your order has been collected. Thank you for shopping with us.' : 'Your order has been delivered. Thank you for shopping with us.' ),
			'cancelled'  => 'Your order has been cancelled. If you did not ask for this, please call us.',
		);
		if ( ! isset( $lines[ $step ] ) ) {
			return false;
		}
		$name = soko_setting( 'business' );
		$body = sprintf(
			"Hello %s,\n\nOrder %s: %s\n\nTrack your order: %s\n\n%s\n%s",
			$doc['customer']['name'],
			$doc['number'],
			$lines[ $step ],
			$doc['pay_url'],
			$name,
			soko_setting( 'phone' )
		);
		return soko_mail( $doc['customer']['email'], 'Order ' . $doc['number'] . ' update from ' . $name, $body );
	}

	/** Email a document as a PDF attachment. */
	public static function email_doc( $doc, $to, $message = '' ) {
		$file = Soko_PDF::doc_file( $doc );
		$name = soko_setting( 'business' );
		$kind = array( 'quote' => 'Quotation', 'invoice' => 'Invoice', 'receipt' => 'Receipt', 'credit' => 'Credit note' );
		$subj = $kind[ $doc['type'] ] . ' ' . $doc['number'] . ' from ' . $name;
		if ( ! $message ) {
			$parts = array( 'Hello ' . ( $doc['customer'] ? $doc['customer']['name'] : '' ) . ',', 'Please find your ' . strtolower( $kind[ $doc['type'] ] ) . ' ' . $doc['number'] . ' attached.' );
			if ( 'invoice' === $doc['type'] ) {
				if ( $doc['balance'] > 0 ) {
					$parts[] = 'Amount due: ' . soko_money( $doc['balance'] ) . '. Please use ' . $doc['number'] . ' as the payment reference.';
				}
				$parts[] = 'View or pay online: ' . $doc['pay_url'];
			} elseif ( in_array( $doc['type'], array( 'receipt', 'credit' ), true ) && $doc['parent_id'] ) {
				$par = self::doc( (int) $doc['parent_id'] );
				if ( $par ) {
					$parts[] = 'See your order ' . $par['number'] . ' online: ' . $par['pay_url'];
				}
			} elseif ( 'quote' === $doc['type'] ) {
				$parts[] = 'To go ahead, reply to this email or call us and we will raise the invoice.';
			}
			$parts[] = $name . "\n" . soko_setting( 'phone' );
			$message = implode( "\n\n", $parts );
		}
		$body = $message;
		$ok = soko_mail( $to, $subj, $body, array(), $file ? array( $file ) : array() );
		if ( $file ) {
			wp_delete_file( $file );
		}
		return $ok;
	}
}
