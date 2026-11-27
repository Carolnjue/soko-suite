<?php
/**
 * Storefront: routing, layout and shop pages. Independent of the active theme.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Store {

	private static $route = '';
	private static $title = '';
	private static $desc  = '';
	private static $image = '';
	private static $jsonld = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_filter( 'query_vars', function ( $v ) {
			return array_merge( $v, array( 'soko_route', 'soko_arg' ) );
		} );
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ), 1 );
		add_filter( 'redirect_canonical', function ( $r ) {
			return get_query_var( 'soko_route' ) ? false : $r;
		} );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'noop' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'sitemap' ), 20 );
	}

	public static function noop( $p ) {
		return $p;
	}

	public static function enabled() {
		return (int) get_option( 'soko_storefront', 1 );
	}

	public static function rewrites() {
		$q = 'index.php?soko_route=';
		add_rewrite_rule( '^shop/?$', $q . 'shop', 'top' );
		add_rewrite_rule( '^shop/([^/]+)/?$', $q . 'shop&soko_arg=$matches[1]', 'top' );
		add_rewrite_rule( '^product/([^/]+)/?$', $q . 'product&soko_arg=$matches[1]', 'top' );
		foreach ( array( 'planner', 'cart', 'checkout', 'track' ) as $r ) {
			add_rewrite_rule( '^' . $r . '/?$', $q . $r, 'top' );
		}
		add_rewrite_rule( '^blog/?$', $q . 'blog', 'top' );
		add_rewrite_rule( '^blog/([^/]+)/?$', $q . 'blog&soko_arg=$matches[1]', 'top' );
		add_rewrite_rule( '^desk/?$', $q . 'desk', 'top' );
		add_rewrite_rule( '^desk/login/?$', $q . 'desk&soko_arg=login', 'top' );
		add_rewrite_rule( '^desk/logout/?$', $q . 'desk&soko_arg=logout', 'top' );
		$site = get_option( 'soko_site' );
		if ( is_array( $site ) && ! empty( $site['pages'] ) ) {
			foreach ( array_keys( $site['pages'] ) as $slug ) {
				if ( 'home' !== $slug && preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
					add_rewrite_rule( '^' . $slug . '/?$', $q . 'page&soko_arg=' . $slug, 'top' );
				}
			}
		}
	}

	public static function sitemap() {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		require_once SOKO_DIR . 'includes/class-soko-sitemap.php';
		wp_register_sitemap_provider( 'soko', new Soko_Sitemap() );
	}

	public static function dispatch() {
		$route = get_query_var( 'soko_route' );
		if ( 'desk' === $route ) {
			Soko_Desk::render( get_query_var( 'soko_arg' ) );
			exit;
		}
		if ( ! self::enabled() ) {
			return;
		}
		if ( ! $route && ( is_front_page() || is_home() ) && ! is_paged() && empty( $_GET['s'] ) ) { // phpcs:ignore
			$route = 'page';
			set_query_var( 'soko_arg', 'home' );
		}
		if ( ! $route ) {
			return;
		}
		self::$route = $route;
		if ( isset( $_GET['soko_preview'] ) ) { // phpcs:ignore
			add_filter( 'show_admin_bar', '__return_false' );
		}
		status_header( 200 );
		global $wp_query;
		$wp_query->is_404 = false;
		$arg  = get_query_var( 'soko_arg' );
		$site = Soko_Sections::current();
		ob_start();
		switch ( $route ) {
			case 'page':
				if ( empty( $site['pages'][ $arg ] ) ) {
					self::not_found();
					break;
				}
				$pg          = $site['pages'][ $arg ];
				self::$title = ! empty( $pg['seo'] ) ? $pg['seo'] : $pg['title'] . ' | ' . soko_setting( 'business' );
				self::$desc  = isset( $pg['desc'] ) ? $pg['desc'] : '';
				foreach ( (array) $pg['sections'] as $s ) {
					if ( ! empty( $s['on'] ) ) {
						echo Soko_Sections::render( $s ); // phpcs:ignore
					}
				}
				break;
			case 'shop':
				self::shop( $arg );
				break;
			case 'product':
				self::product( $arg );
				break;
			case 'planner':
				self::$title = 'Solar calculator: size your system | ' . soko_setting( 'business' );
				self::$desc  = 'Choose the appliances you want to run and see the solar system you need, with panels, inverter, battery and price.';
				echo '<section class="phero sky"><div class="wrap"><div class="pcopy"><h1>Solar calculator</h1><p>Choose the appliances you want to run and see the system you need, with a price.</p></div></div></section>'; // phpcs:ignore
				echo Soko_Sections::render( array( 'id' => 'pl', 'type' => 'planner', 'props' => array( 'title' => 'Choose your appliances', 'text' => 'Add each appliance and how many you have, then adjust the hours to match how you use them.' ) ) ); // phpcs:ignore
				echo '<section class="band"><div class="wrap narrow"><div class="faq"><details><summary>How is my system sized?</summary><p>We add up the energy your appliances use in a day, allow for losses, and size the battery to cover the evening and night. Panels are sized for about five useful sun hours a day, which is typical for most of Kenya. The inverter covers everything you might run at once, with headroom for motors such as pumps and fridges.</p></details><details><summary>Is the price final?</summary><p>It is an estimate. An engineer confirms the final size and price after a site survey, because roofs, cable runs and existing wiring differ.</p></details></div></div></section>'; // phpcs:ignore
				break;
			case 'cart':
				self::$title = 'Your cart | ' . soko_setting( 'business' );
				echo '<section class="phero sky slim"><div class="wrap"><div class="pcopy"><h1>Your cart</h1></div></div></section><section class="band"><div class="wrap cartpage" data-cart-page></div></section>'; // phpcs:ignore
				break;
			case 'checkout':
				self::$title = 'Checkout | ' . soko_setting( 'business' );
				self::checkout();
				break;
			case 'blog':
				if ( $arg ) {
					$r = Soko_Blog::render_post( $arg );
					if ( ! $r ) {
						self::not_found();
						break;
					}
					self::$title = $r['post']['title'] . ' | ' . soko_setting( 'business' );
					self::$desc  = $r['post']['excerpt'];
					self::$image = $r['post']['image'];
					echo $r['html']; // phpcs:ignore
				} else {
					self::$title = 'Solar guides and news | ' . soko_setting( 'business' );
					self::$desc  = 'Guides on sizing solar systems, choosing batteries, water heating and maintenance from Dynamic Smart Systems engineers.';
					echo Soko_Blog::render_list(); // phpcs:ignore
				}
				break;
			case 'track':
				self::$title = 'Track your order | ' . soko_setting( 'business' );
				self::track();
				break;
			default:
				self::not_found();
		}
		$body = ob_get_clean();
		self::layout( $body );
		exit;
	}

	private static function not_found() {
		status_header( 404 );
		self::$title = 'Page not found | ' . soko_setting( 'business' );
		echo '<section class="phero sky"><div class="wrap"><div class="pcopy"><h1>We could not find that page</h1><p>It may have moved. Try the shop or call us.</p><div class="row"><a class="btn b-sun" href="' . esc_url( home_url( '/shop/' ) ) . '">Go to the shop</a><a class="btn b-line" href="' . esc_url( home_url( '/' ) ) . '">Home</a></div></div></div></section>'; // phpcs:ignore
	}

	/* ============================================================= cards */

	public static function art( $p, $size = 104, $lazy = true ) {
		if ( ! empty( $p['images'] ) ) {
			return '<img src="' . esc_url( $p['images'][0] ) . '" alt="' . esc_attr( $p['name'] ) . '"' . ( $lazy ? ' loading="lazy"' : '' ) . '>';
		}
		return '<span class="ill">' . soko_icon( $p['icon'], $size ) . '</span>';
	}

	public static function card( $p ) {
		$low  = 'product' === $p['kind'] && $p['stock'] > 0 && $p['stock'] <= $p['reorder'];
		$out  = 'product' === $p['kind'] && $p['stock'] <= 0;
		$spec = $p['specs'] ? reset( $p['specs'] ) : '';
		$data = array(
			'id'      => $p['id'],
			'name'    => $p['name'],
			'price'   => $p['price'],
			'was'     => $p['was'],
			'img'     => $p['images'] ? $p['images'][0] : '',
			'icon'    => $p['icon'],
			'cat'     => $p['cat_name'],
			'runs'    => $p['runs'],
			'specs'   => $p['specs'],
			'warranty' => $p['warranty'],
			'stock'   => $out ? 0 : $p['stock'],
			'kind'    => $p['kind'],
			'install' => $p['install_fee'],
			'url'     => $p['url'],
			'tag'     => $p['tag'],
			'brand'   => $p['brand'],
		);
		$chips = '';
		foreach ( array_slice( (array) $p['specs'], 0, 3 ) as $k => $v ) {
			$chips .= '<li title="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</li>';
		}
		$h  = '<article class="card" data-p="' . esc_attr( wp_json_encode( $data ) ) . '">';
		$h .= '<div class="media"><a class="art" href="' . esc_url( $p['url'] ) . '" tabindex="-1" aria-hidden="true">' . self::art( $p ) . '</a>' . ( $p['tag'] ? '<span class="tag">' . esc_html( $p['tag'] ) . '</span>' : '' ) . ( $p['was'] > $p['price'] ? '<span class="off">−' . (int) round( 100 - $p['price'] / $p['was'] * 100 ) . '%</span>' : '' ) . '<button class="qv" data-quick aria-label="Quick view: ' . esc_attr( $p['name'] ) . '"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>Quick view</button></div>';
		$h .= '<div class="body"><span class="cat-name">' . esc_html( $p['brand'] ? $p['brand'] . ' · ' . $p['cat_name'] : $p['cat_name'] ) . '</span><h3><a href="' . esc_url( $p['url'] ) . '">' . esc_html( $p['name'] ) . '</a></h3>' . ( $chips ? '<ul class="chips">' . $chips . '</ul>' : '' ) . '<p class="runs">' . esc_html( $p['runs'] ) . '</p>';
		$h .= '<div class="pfoot"><div><div class="price"><b>' . esc_html( soko_money( $p['price'] ) ) . '</b>' . ( $p['was'] > $p['price'] ? '<s>' . esc_html( soko_money( $p['was'] ) ) . '</s>' : '' ) . '</div>';
		if ( 'product' === $p['kind'] ) {
			$h .= '<span class="stock' . ( $low ? ' low' : '' ) . ( $out ? ' out' : '' ) . '"><i></i>' . ( $out ? 'Order in, about a week' : ( $low ? 'Only ' . (int) $p['stock'] . ' left' : 'In stock' ) ) . '</span>';
		}
		$h .= '</div><button class="addbtn" data-add="' . (int) $p['id'] . '" aria-label="Add ' . esc_attr( $p['name'] ) . ' to cart"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/><path d="M14 4v6M11 7h6"/></svg></button></div></div></article>';
		return $h;
	}

	public static function quote_form( $context = '' ) {
		$types = array( 'Home solar system', 'Business or institution system', 'Solar water heater', 'Borehole or farm solarisation', 'Solar lighting or CCTV', 'Batteries or backup', 'Maintenance or repair' );
		$o     = '';
		foreach ( $types as $t ) {
			$o .= '<option>' . esc_html( $t ) . '</option>';
		}
		return '<form class="panel qform" data-lead-form novalidate><h3>Request a quote</h3>
			<div class="f2"><div class="f"><label for="qName">Full name</label><input id="qName" name="name" autocomplete="name" required></div><div class="f"><label for="qPhone">Phone</label><input id="qPhone" name="phone" inputmode="tel" autocomplete="tel" placeholder="07XX XXX XXX" required></div></div>
			<div class="f2"><div class="f"><label for="qType">I need</label><select id="qType" name="interest">' . $o . '</select></div><div class="f"><label for="qCounty">County</label><input id="qCounty" name="county" placeholder="e.g. Kiambu"></div></div>
			<div class="f"><label for="qEmail">Email (optional)</label><input id="qEmail" name="email" type="email" autocomplete="email"></div>
			<div class="f"><label for="qMsg">What do you want to power?</label><textarea id="qMsg" name="message" placeholder="e.g. 3-bedroom house, fridge, TV, borehole pump, frequent blackouts"></textarea></div>
			<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<button class="btn b-sun" type="submit">Send request</button><p class="formmsg" role="status"></p></form>';
	}

	/* ============================================================== shop */

	private static function shop( $cat ) {
		$cats = soko_categories();
		$c    = $cat ? soko_category( $cat ) : null;
		$q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore
		$all  = Soko_Ledger::products( array( 'visible' => 1 ) );
		$list = Soko_Ledger::products( array( 'visible' => 1, 'cat' => $cat, 'q' => $q ) );
		self::$title = ( $q ? 'Search: ' . $q : ( $c ? $c['name'] . ' in Kenya' : 'Shop solar products' ) ) . ' | ' . soko_setting( 'business' );
		self::$desc  = $c ? 'Buy ' . strtolower( $c['name'] ) . ' online. Pay by M-Pesa, delivery countrywide and installation by EPRA-licensed technicians.' : 'Solar kits, panels, inverters, lithium batteries, water heaters, pumps and lighting. Pay by M-Pesa.';
		$brands = array_unique( array_filter( wp_list_pluck( $list, 'brand' ) ) );
		echo '<section class="phero sky slim"><div class="wrap"><div class="pcopy"><nav class="crumbs"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a><span>/</span><a href="' . esc_url( home_url( '/shop/' ) ) . '">Shop</a>' . ( $c ? '<span>/</span>' . esc_html( $c['name'] ) : '' ) . '</nav><h1>' . esc_html( $q ? 'Results for "' . $q . '"' : ( $c ? $c['name'] : 'Shop all products' ) ) . '</h1><p>' . ( soko_setting( 'vat_registered' ) && soko_setting( 'prices_incl_vat' ) ? 'Prices include VAT. ' : '' ) . 'Every product shows its specifications, what it can power, warranty and installation cost.</p></div></div></section>'; // phpcs:ignore
		echo '<section class="band shopband"><div class="wrap shop"><aside class="side" aria-label="Categories"><a class="' . ( ! $cat ? 'on' : '' ) . '" href="' . esc_url( home_url( '/shop/' ) ) . '">' . soko_icon( 'kit', 22 ) . 'All products<em>' . count( $all ) . '</em></a>'; // phpcs:ignore
		foreach ( $cats as $x ) {
			$n = count( array_filter( $all, function ( $p ) use ( $x ) { return $p['cat'] === $x['id']; } ) );
			if ( ! $n ) {
				continue;
			}
			echo '<a class="' . ( $cat === $x['id'] ? 'on' : '' ) . '" href="' . esc_url( home_url( '/shop/' . $x['id'] . '/' ) ) . '">' . soko_icon( $x['icon'], 22 ) . esc_html( $x['name'] ) . '<em>' . (int) $n . '</em></a>'; // phpcs:ignore
		}
		echo '</aside><div><div class="shopbar"><span class="muted" data-count>' . count( $list ) . ' products</span><div class="shopf">';
		if ( count( $brands ) > 1 ) {
			echo '<label>Brand <select data-f-brand><option value="">All</option>';
			foreach ( $brands as $b ) {
				echo '<option>' . esc_html( $b ) . '</option>';
			}
			echo '</select></label>';
		}
		echo '<label><input type="checkbox" data-f-stock> In stock</label><label>Sort <select data-f-sort><option value="">Most popular</option><option value="lo">Price: low to high</option><option value="hi">Price: high to low</option><option value="az">Name A to Z</option></select></label></div></div>';
		echo '<div class="grid g3" data-shop-grid>';
		foreach ( $list as $p ) {
			echo self::card( $p ); // phpcs:ignore
		}
		echo '</div>';
		if ( ! $list ) {
			echo '<div class="panel empty"><h3>Nothing matched</h3><p>We stock more than we list. Tell us what you need and we will find it.</p><a class="btn b-sun" href="' . esc_url( home_url( '/contact/' ) ) . '">Ask for a product</a></div>';
		}
		echo '</div></div></section>';
	}

	private static function product( $slug ) {
		$p = Soko_Ledger::product( sanitize_title( $slug ) );
		if ( ! $p || ! $p['visible'] ) {
			self::not_found();
			return;
		}
		$s           = Soko_Settings::all();
		self::$title = $p['name'] . ' price in Kenya | ' . $s['business'];
		self::$desc  = $p['name'] . '. ' . $p['runs'] . '. ' . soko_money( $p['price'] ) . '. Pay by M-Pesa, delivery and installation available.';
		self::$image = $p['images'] ? $p['images'][0] : '';
		self::$jsonld = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => $p['name'],
			'sku'      => $p['sku'],
			'description' => wp_strip_all_tags( $p['runs'] . ' ' . $p['description'] ),
			'brand'    => $p['brand'] ? array( '@type' => 'Brand', 'name' => $p['brand'] ) : null,
			'image'    => $p['images'],
			'offers'   => array( '@type' => 'Offer', 'priceCurrency' => 'KES', 'price' => $p['price'], 'availability' => 'https://schema.org/' . ( 'product' !== $p['kind'] || $p['stock'] > 0 ? 'InStock' : 'BackOrder' ), 'url' => $p['url'] ),
		);
		$wa   = 'https://wa.me/' . preg_replace( '/\D/', '', $s['whatsapp'] ) . '?text=' . rawurlencode( 'Hello, I would like to ask about the ' . $p['name'] . ' (' . soko_money( $p['price'] ) . '): ' . $p['url'] );
		$low  = 'product' === $p['kind'] && $p['stock'] > 0 && $p['stock'] <= $p['reorder'];
		$out  = 'product' === $p['kind'] && $p['stock'] <= 0;
		$data = array( 'id' => $p['id'], 'name' => $p['name'], 'price' => $p['price'], 'kwh' => $p['kwh'], 'watts' => $p['watts'], 'install' => $p['install_fee'], 'cat' => $p['cat'] );
		echo '<section class="band pdp" data-product="' . esc_attr( wp_json_encode( $data ) ) . '"><div class="wrap">';
		echo '<nav class="crumbs"><a href="' . esc_url( home_url( '/shop/' ) ) . '">Shop</a><span>/</span><a href="' . esc_url( home_url( '/shop/' . $p['cat'] . '/' ) ) . '">' . esc_html( $p['cat_name'] ) . '</a><span>/</span>' . esc_html( $p['name'] ) . '</nav>';
		echo '<div class="pdp-grid"><div class="gallery"><div class="gmain art">' . self::art( $p, 200, false ) . ( $p['tag'] ? '<span class="tag">' . esc_html( $p['tag'] ) . '</span>' : '' ) . '</div>'; // phpcs:ignore
		if ( count( $p['images'] ) > 1 ) {
			echo '<div class="thumbs">';
			foreach ( $p['images'] as $i => $im ) {
				echo '<button data-img="' . esc_url( $im ) . '" class="' . ( $i ? '' : 'on' ) . '"><img src="' . esc_url( $im ) . '" alt=""></button>';
			}
			echo '</div>';
		}
		echo '</div><div class="buy"><span class="cat-name">' . esc_html( $p['cat_name'] ) . ( $p['brand'] ? ' · ' . esc_html( $p['brand'] ) : '' ) . '</span><h1>' . esc_html( $p['name'] ) . '</h1>';
		echo '<div class="price big"><b>' . esc_html( soko_money( $p['price'] ) ) . '</b>' . ( $p['was'] > $p['price'] ? '<s>' . esc_html( soko_money( $p['was'] ) ) . '</s><em class="save">Save ' . esc_html( soko_money( $p['was'] - $p['price'] ) ) . '</em>' : '' ) . '</div>';
		if ( 'product' === $p['kind'] ) {
			echo '<span class="stock' . ( $low ? ' low' : '' ) . ( $out ? ' out' : '' ) . '">' . ( $out ? 'Out of stock: order in, about a week' : ( $low ? 'Only ' . (int) $p['stock'] . ' left in stock' : 'In stock, ready to deliver' ) ) . '</span>';
		}
		echo '<div class="runsbox">' . soko_icon( 'bulb', 34 ) . '<div><b>What it powers</b><p>' . esc_html( $p['runs'] ) . '</p></div></div>'; // phpcs:ignore
		if ( $p['install_fee'] > 0 ) {
			echo '<div class="opts"><label><input type="radio" name="inst" value="0" checked><span>Supply only</span><b>Included</b></label><label><input type="radio" name="inst" value="1"><span>Supply and install by our technicians</span><b>+ ' . esc_html( soko_money( $p['install_fee'] ) ) . '</b></label></div>';
		}
		echo '<div class="buyrow"><div class="qty" data-qty><button type="button" data-d="-1" aria-label="One less">−</button><input type="number" min="1" value="1" aria-label="Quantity"><button type="button" data-d="1" aria-label="One more">+</button></div><button class="btn b-blue" data-add-pdp>Add to cart</button><button class="btn b-sun" data-buy-pdp>Buy now</button></div>';
		echo '<a class="wa-ask" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z"/></svg>Ask an engineer about this on WhatsApp</a>';
		echo '<ul class="perks"><li>' . soko_icon( 'battery', 22 ) . 'Pay by M-Pesa, receipt sent instantly</li><li>' . soko_icon( 'kit', 22 ) . 'Nairobi delivery same or next day</li><li>' . soko_icon( 'shield', 22 ) . 'Warranty: ' . esc_html( $p['warranty'] ? $p['warranty'] : 'manufacturer warranty' ) . '</li></ul></div></div>'; // phpcs:ignore
		echo '<div class="pdp-more"><div><h2>Specifications</h2><table class="specs">';
		foreach ( $p['specs'] as $k => $v ) {
			echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '<tr><th scope="row">SKU</th><td>' . esc_html( $p['sku'] ) . '</td></tr><tr><th scope="row">Warranty</th><td>' . esc_html( $p['warranty'] ) . '</td></tr></table>';
		if ( $p['description'] ) {
			echo '<div class="rich">' . wp_kses_post( wpautop( $p['description'] ) ) . '</div>';
		}
		echo '</div>';
		if ( $p['kwh'] > 0 ) {
			echo '<div class="runtime panel" data-runtime="' . esc_attr( $p['kwh'] ) . '"><h2>How long will it last?</h2><p class="muted">Tick what you would run during a blackout.</p><div class="rt-list"></div><div class="rt-out"></div></div>';
		} else {
			echo '<div class="panel helpbox"><h2>Not sure it fits?</h2><p>Use the solar calculator to size a full system, or ask an engineer.</p><div class="row"><a class="btn b-blue" href="' . esc_url( home_url( '/planner/' ) ) . '">Open Power Planner</a><a class="btn b-line" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">WhatsApp us</a></div></div>';
		}
		echo '</div>';
		$rel = array_slice( array_values( array_filter( Soko_Ledger::products( array( 'visible' => 1, 'cat' => $p['cat'] ) ), function ( $x ) use ( $p ) { return $x['id'] !== $p['id']; } ) ), 0, 4 );
		if ( count( $rel ) < 4 ) {
			$rel = array_merge( $rel, array_slice( array_values( array_filter( Soko_Ledger::products( array( 'visible' => 1 ) ), function ( $x ) use ( $p ) { return $x['featured'] && $x['id'] !== $p['id'] && $x['cat'] !== $p['cat']; } ) ), 0, 4 - count( $rel ) ) );
		}
		if ( $rel ) {
			echo '<h2 class="rel-h">You may also need</h2><div class="grid">';
			foreach ( $rel as $x ) {
				echo self::card( $x ); // phpcs:ignore
			}
			echo '</div>';
		}
		echo '</div></section>';
	}

	private static function checkout() {
		$s   = Soko_Settings::all();
		$del = '';
		foreach ( (array) $s['delivery'] as $i => $d ) {
			$del .= '<label><input type="radio" name="delivery" value="' . (int) $i . '" data-fee="' . esc_attr( $d['fee'] ) . '"' . ( 0 === $i ? ' checked' : '' ) . '><span><b>' . esc_html( $d['label'] ) . '</b><small>' . esc_html( $d['note'] ) . '</small></span><b>' . ( (float) $d['fee'] ? esc_html( soko_money( $d['fee'] ) ) : 'Free' ) . '</b></label>';
		}
		echo '<section class="phero sky slim"><div class="wrap"><div class="pcopy"><h1>Checkout</h1><p>Pay securely with M-Pesa. You get a receipt straight away and can track your order online.</p><ol class="costeps"><li class="on"><i>1</i>Cart</li><li class="on"><i>2</i>Your details</li><li><i>3</i>Pay with M-Pesa</li><li><i>4</i>Receipt</li></ol></div></div></section>';
		echo '<section class="band"><div class="wrap co" data-checkout><form class="panel" data-co-form novalidate><h3>Your details</h3>
			<div class="f2"><div class="f"><label for="cName">Full name</label><input id="cName" name="name" autocomplete="name" required></div><div class="f"><label for="cPhone">Phone (M-Pesa)</label><input id="cPhone" name="phone" inputmode="tel" autocomplete="tel" placeholder="07XX XXX XXX" required></div></div>
			<div class="f2"><div class="f"><label for="cEmail">Email for your receipt</label><input id="cEmail" name="email" type="email" autocomplete="email"></div><div class="f"><label for="cCounty">County or town</label><input id="cCounty" name="county" autocomplete="address-level2"></div></div>
			<div class="f"><label for="cAddr">Delivery or installation address</label><input id="cAddr" name="address" autocomplete="street-address" placeholder="Estate, road, building or landmark"></div>
			<details class="biz"><summary>Buying for a business? Add your KRA PIN for a tax invoice</summary><div class="f"><label for="cKra">KRA PIN</label><input id="cKra" name="kra" placeholder="P051234567X"></div></details>
			<h3>Delivery</h3><div class="opts">' . $del . '</div>
			<div class="f inst-date" hidden><label for="cDate">Preferred installation date</label><input id="cDate" name="install_date" type="date" min="' . esc_attr( gmdate( 'Y-m-d', strtotime( soko_today() . ' +2 days' ) ) ) . '"></div>
			<div class="f"><label for="cNotes">Notes (optional)</label><textarea id="cNotes" name="notes" placeholder="Anything we should know"></textarea></div>
			<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<h3>Payment</h3><div class="paybox"><span class="mpesa">M-Pesa</span><p>When you tap pay, a prompt appears on your phone. Enter your M-Pesa PIN to complete.</p></div>
			<button class="btn b-sun wide" type="submit" data-pay>Pay with M-Pesa</button><p class="formmsg" role="status"></p></form>
			<aside class="panel summary"><h3>Order summary</h3><div data-co-lines></div><div class="sum" data-co-sum></div><ul class="cotrust"><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12l5 5 9-10"/></svg><span>You only pay when the M-Pesa prompt arrives on your own phone.</span></li><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12l5 5 9-10"/></svg><span>Your receipt and invoice are issued the moment payment is confirmed.</span></li><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 12l5 5 9-10"/></svg><span>Installed by EPRA-licensed technicians, with warranty and aftercare.</span></li></ul></aside></div></section>'; // phpcs:ignore
	}

	private static function track() {
		$num = isset( $_GET['doc'] ) ? sanitize_text_field( wp_unslash( $_GET['doc'] ) ) : ''; // phpcs:ignore
		$tok = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore
		$d   = $num ? Soko_Ledger::doc( $num ) : null;
		if ( ! $d || ! $tok || ! hash_equals( (string) $d['token'], $tok ) || 'invoice' !== $d['type'] ) {
			echo '<section class="phero sky slim"><div class="wrap"><div class="pcopy"><h1>Track your order</h1><p>Use the link in your email or SMS, or enter your order number and phone.</p></div></div></section><section class="band"><div class="wrap narrow"><form class="panel" data-find-form><div class="f2"><div class="f"><label for="tNum">Order or invoice number</label><input id="tNum" name="number" placeholder="INV-1001" required></div><div class="f"><label for="tPhone">Phone used</label><input id="tPhone" name="phone" inputmode="tel" required></div></div><button class="btn b-sun">Find my order</button><p class="formmsg" role="status"></p></form></div></section>';
			return;
		}
		$steps = array( 'awaiting_payment' => 'Placed', 'to_pack' => 'Paid', 'packed' => 'Packed', 'dispatched' => 'On the way', 'delivered' => 'Delivered' );
		$cur   = $d['fulfil'] ? $d['fulfil'] : ( 'paid' === $d['state'] ? 'to_pack' : 'awaiting_payment' );
		$keys  = array_keys( $steps );
		$idx   = array_search( $cur, $keys, true );
		echo '<section class="phero sky slim"><div class="wrap"><div class="pcopy"><h1>Order ' . esc_html( $d['number'] ) . '</h1><p>' . esc_html( $d['customer'] ? 'For ' . $d['customer']['name'] : '' ) . ' · ' . esc_html( date_i18n( 'j M Y', strtotime( $d['date'] ) ) ) . '</p></div></div></section>';
		echo '<section class="band"><div class="wrap co" data-track="' . esc_attr( wp_json_encode( array( 'doc' => $d['number'], 't' => $d['token'], 'balance' => $d['balance'], 'phone' => $d['customer'] ? soko_phone_display( $d['customer']['phone'] ) : '' ) ) ) . '"><div class="panel">';
		if ( 'web' === $d['source'] ) {
			echo '<ol class="track">';
			foreach ( $keys as $i => $k ) {
				echo '<li class="' . ( false !== $idx && $i <= $idx ? 'done' : '' ) . '"><i></i>' . esc_html( $steps[ $k ] ) . '</li>';
			}
			echo '</ol>';
		}
		echo '<h3>Items</h3><table class="lines">';
		foreach ( $d['lines'] as $l ) {
			echo '<tr><td>' . esc_html( $l['description'] ) . ' <span class="muted">× ' . esc_html( $l['qty'] + 0 ) . '</span></td><td class="n">' . esc_html( soko_money( $l['qty'] * $l['price'] ) ) . '</td></tr>';
		}
		echo '</table><div class="sum"><div><span>Total</span><span>' . esc_html( soko_money( $d['total'] ) ) . '</span></div><div><span>Paid</span><span>' . esc_html( soko_money( $d['paid'] ) ) . '</span></div><div class="grand"><span>Balance</span><span data-bal>' . esc_html( soko_money( $d['balance'] ) ) . '</span></div></div>';
		echo '<div class="row"><a class="btn b-line" href="' . esc_url( rest_url( 'soko/v1/doc-pdf?doc=' . rawurlencode( $d['number'] ) . '&t=' . $d['token'] ) ) . '" target="_blank" rel="noopener">Download invoice (PDF)</a></div><div data-receipts></div></div>';
		echo '<aside class="panel summary" data-paypanel></aside></div></section>';
	}

	/* ============================================================ layout */

	private static function fonts( $site ) {
		$h = rawurlencode( $site['brand']['head_font'] ? $site['brand']['head_font'] : 'Outfit' );
		$b = rawurlencode( $site['brand']['body_font'] ? $site['brand']['body_font'] : 'Inter' );
		return 'https://fonts.googleapis.com/css2?family=' . $h . ':wght@500;600;700;800&family=' . $b . ':wght@400;500;600&display=swap';
	}

	public static function catalogue_js() {
		$out = array();
		foreach ( Soko_Ledger::products( array( 'visible' => 1 ) ) as $p ) {
			$out[] = array( 'id' => $p['id'], 'sku' => $p['sku'], 'name' => $p['name'], 'price' => $p['price'], 'cat' => $p['cat'], 'watts' => $p['watts'], 'kwh' => $p['kwh'], 'img' => $p['images'] ? $p['images'][0] : '', 'icon' => $p['icon'], 'install' => $p['install_fee'], 'url' => $p['url'], 'kind' => $p['kind'], 'stock' => $p['stock'] );
		}
		return $out;
	}

	private static function layout( $body ) {
		$site  = Soko_Sections::current();
		$s     = Soko_Settings::all();
		$b     = $site['brand'];
		$h     = $site['header'];
		$cats  = soko_categories();
		$title = self::$title ? self::$title : $s['business'];
		$desc  = self::$desc;
		$canon = esc_url( home_url( add_query_arg( array(), wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) ) ); // phpcs:ignore
		$logo  = $b['logo'] ? $b['logo'] : SOKO_URL . 'assets/img/dss-logo.png';
		$wa    = 'https://wa.me/' . preg_replace( '/\D/', '', $s['whatsapp'] );
		add_filter( 'pre_get_document_title', function () use ( $title ) { return $title; }, 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 999 );
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<script>try{if(localStorage.getItem('soko_theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?><title><?php echo esc_html( $title ); ?></title><?php endif; ?>
<?php if ( $desc ) : ?><meta name="description" content="<?php echo esc_attr( $desc ); ?>"><?php endif; ?>
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $desc ); ?>">
<meta property="og:type" content="<?php echo 'product' === self::$route ? 'product' : 'website'; ?>">
<meta property="og:url" content="<?php echo $canon; // phpcs:ignore ?>">
<meta property="og:image" content="<?php echo esc_url( self::$image ? self::$image : $logo ); ?>">
<meta name="theme-color" content="<?php echo esc_attr( $b['deep'] ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?php echo esc_url( self::fonts( $site ) ); ?>">
<style>:root{--blue:<?php echo esc_html( $b['primary'] ); ?>;--sun:<?php echo esc_html( $b['accent'] ); ?>;--deep:<?php echo esc_html( $b['deep'] ); ?>;--hf:"<?php echo esc_html( $b['head_font'] ); ?>";--bf:"<?php echo esc_html( $b['body_font'] ); ?>";--r:<?php echo (int) $b['radius']; ?>px}</style>
<?php if ( self::$jsonld ) : ?><script type="application/ld+json"><?php echo wp_json_encode( array_filter( self::$jsonld ) ); ?></script><?php endif; ?>
<script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $s['business'], 'telephone' => $s['phone'], 'email' => $s['email'], 'address' => $s['address'], 'url' => home_url( '/' ), 'logo' => $logo ) ); ?></script>
<?php wp_head(); ?>
</head>
<body class="soko soko-<?php echo esc_attr( self::$route ); ?>">
<a class="skip" href="#main">Skip to content</a>
<?php
$all   = Soko_Ledger::products( array( 'visible' => 1 ) );
$bycat = array();
foreach ( $all as $pr ) {
	$bycat[ $pr['cat'] ][] = $pr;
}
$chev = '<svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
?>
<div class="announce"><div class="wrap"><span class="ann-l"><a href="tel:<?php echo esc_attr( soko_phone( $s['phone'] ) ); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg><?php echo esc_html( $s['phone'] ); ?></a><?php if ( $s['phone2'] ) : ?><a href="tel:<?php echo esc_attr( soko_phone( $s['phone2'] ) ); ?>"><?php echo esc_html( $s['phone2'] ); ?></a><?php endif; ?><a href="mailto:<?php echo esc_attr( $s['email'] ); ?>" class="ann-mail"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg><?php echo esc_html( $s['email'] ); ?></a></span><span class="ann-r"><?php foreach ( (array) ( isset( $h['utility'] ) ? $h['utility'] : array() ) as $u ) : ?><a href="<?php echo Soko_Sections::link( $u['link'] ); // phpcs:ignore ?>"><?php echo esc_html( $u['label'] ); ?></a><?php endforeach; ?></span></div></div>
<header class="main"><div class="wrap nav">
<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $s['business'] ); ?>" width="180" height="60"></a>
<form class="search" action="<?php echo esc_url( home_url( '/shop/' ) ); ?>" role="search"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" placeholder="Search panels, inverters, batteries, water heaters…" aria-label="Search products" autocomplete="off" data-search><div class="sugg" data-sugg hidden></div></form>
<div class="tools">
<button class="theme" data-theme-toggle aria-label="Switch light or dark mode" title="Light or dark mode"><svg class="sun" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg class="moon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 13A9 9 0 1 1 11 3a7 7 0 0 0 10 10z"/></svg></button>
<a class="ico lbl" href="<?php echo esc_url( home_url( '/track/' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><span>My orders</span></a>
<button class="ico lbl" data-open-cart aria-label="Cart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 4h2l2.4 11h11l2-8H6.2"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg><span>Cart</span><b class="count" data-cart-count>0</b></button>
<?php if ( ! empty( $h['cta_label'] ) ) : ?><a class="btn b-sun hcta" href="<?php echo Soko_Sections::link( $h['cta_link'] ); // phpcs:ignore ?>"><?php echo esc_html( $h['cta_label'] ); ?></a><?php endif; ?>
<button class="ico burger" data-burger aria-label="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
</div></div>
<nav class="catbar" aria-label="Shop by category"><div class="wrap"><ul class="menu">
<?php foreach ( (array) $h['menu'] as $m ) :
	$ids = ! empty( $m['cats'] ) ? array_values( array_filter( array_map( 'trim', explode( ',', $m['cats'] ) ) ) ) : array();
	$top = array();
	foreach ( $ids as $cid ) {
		foreach ( isset( $bycat[ $cid ] ) ? $bycat[ $cid ] : array() as $pr ) {
			$top[] = $pr;
		}
	}
	usort( $top, function ( $x, $y ) { return $y['featured'] - $x['featured']; } );
	$top = array_slice( $top, 0, 3 );
	?>
<li class="<?php echo $ids ? 'has' : ''; ?>"><a href="<?php echo Soko_Sections::link( $m['link'] ); // phpcs:ignore ?>"><?php echo esc_html( $m['label'] ); ?><?php echo $ids ? $chev : ''; // phpcs:ignore ?></a>
<?php if ( $ids ) : ?>
<div class="mega"><div class="wrap dd">
<div class="dd-cats"><h4><?php echo esc_html( $m['label'] ); ?></h4><?php foreach ( $ids as $cid ) : $c = soko_category( $cid ); $n = isset( $bycat[ $cid ] ) ? count( $bycat[ $cid ] ) : 0; ?><a href="<?php echo esc_url( home_url( '/shop/' . $cid . '/' ) ); ?>"><span class="ci"><?php if ( ! empty( $c['image'] ) ) : ?><img src="<?php echo esc_url( $c['image'] ); ?>" alt="" loading="lazy"><?php endif; ?></span><span><b><?php echo esc_html( $c['name'] ); ?></b><small><?php echo (int) $n; ?> <?php echo 1 === $n ? 'product' : 'products'; ?></small></span></a><?php endforeach; ?><a class="dd-all" href="<?php echo Soko_Sections::link( $m['link'] ); // phpcs:ignore ?>">View all <?php echo esc_html( strtolower( $m['label'] ) ); ?> →</a></div>
<div class="dd-top"><h4>Popular</h4><div class="dd-grid"><?php foreach ( $top as $pr ) : ?><a href="<?php echo esc_url( $pr['url'] ); ?>"><span class="pi"><?php if ( $pr['images'] ) : ?><img src="<?php echo esc_url( $pr['images'][0] ); ?>" alt="" loading="lazy"><?php endif; ?></span><b><?php echo esc_html( $pr['name'] ); ?></b><small><?php echo esc_html( $pr['runs'] ); ?></small><em><?php echo esc_html( soko_money( $pr['price'] ) ); ?></em></a><?php endforeach; ?></div></div>
<div class="mega-promo"><?php if ( isset( $m['promo'] ) && 'planner' === $m['promo'] ) : ?><h3>Not sure what size you need?</h3><p>Choose the appliances you want to run and see the right system, priced with installation.</p><a class="btn b-sun" href="<?php echo esc_url( home_url( '/planner/' ) ); ?>">Calculate my system</a><?php else : ?><h3>Need it installed?</h3><p>Our EPRA-licensed technicians supply, install and commission, with aftercare visits.</p><a class="btn b-sun" href="<?php echo Soko_Sections::link( $h['cta_link'] ); // phpcs:ignore ?>">Request a quote</a><?php endif; ?></div>
</div></div>
<?php endif; ?></li>
<?php endforeach; ?>
</ul></div></nav>
</header>
<nav class="mnav" data-mnav aria-label="Mobile"><div class="mtop"><img src="<?php echo esc_url( $logo ); ?>" alt="" height="40"><button class="ico" data-close-mnav aria-label="Close menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
<form action="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="msearch"><input name="q" placeholder="Search products" aria-label="Search products"></form>
<?php foreach ( (array) $h['menu'] as $m ) : ?><a href="<?php echo Soko_Sections::link( $m['link'] ); // phpcs:ignore ?>"><?php echo esc_html( $m['label'] ); ?></a>
<?php if ( ! empty( $m['cats'] ) ) : ?><div class="sub"><?php foreach ( array_filter( array_map( 'trim', explode( ',', $m['cats'] ) ) ) as $cid ) : $c = soko_category( $cid ); ?><a href="<?php echo esc_url( home_url( '/shop/' . $cid . '/' ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a><?php endforeach; ?></div><?php endif; ?>
<?php endforeach; ?><?php foreach ( (array) ( isset( $h['utility'] ) ? $h['utility'] : array() ) as $u ) : ?><a href="<?php echo Soko_Sections::link( $u['link'] ); // phpcs:ignore ?>"><?php echo esc_html( $u['label'] ); ?></a><?php endforeach; ?>
<?php if ( ! empty( $h['cta_label'] ) ) : ?><a class="btn b-sun" style="margin-top:16px" href="<?php echo Soko_Sections::link( $h['cta_link'] ); // phpcs:ignore ?>"><?php echo esc_html( $h['cta_label'] ); ?></a><?php endif; ?></nav>
<main id="main"><?php echo $body; // phpcs:ignore ?></main>
<footer class="foot"><div class="wrap">
<div><span class="logo-w"><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $s['business'] ); ?>" height="44"></span><p><?php echo esc_html( $site['footer']['about'] ); ?></p></div>
<div><h4>Shop</h4><?php foreach ( array_slice( $cats, 0, 6 ) as $c ) : ?><a href="<?php echo esc_url( home_url( '/shop/' . $c['id'] . '/' ) ); ?>"><?php echo esc_html( $c['name'] ); ?></a><?php endforeach; ?></div>
<div><h4>Company</h4><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About us</a><a href="<?php echo esc_url( home_url( '/services/' ) ); ?>">Installation and services</a><a href="<?php echo esc_url( home_url( '/projects/' ) ); ?>">Projects</a><a href="<?php echo esc_url( home_url( '/planner/' ) ); ?>">Calculate my system</a><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a><a href="<?php echo esc_url( home_url( '/track/' ) ); ?>">Track an order</a></div>
<div><h4>Talk to us</h4><a href="tel:<?php echo esc_attr( soko_phone( $s['phone'] ) ); ?>"><?php echo esc_html( $s['phone'] ); ?></a><?php if ( $s['phone2'] ) : ?><a href="tel:<?php echo esc_attr( soko_phone( $s['phone2'] ) ); ?>"><?php echo esc_html( $s['phone2'] ); ?></a><?php endif; ?><a href="mailto:<?php echo esc_attr( $s['email'] ); ?>"><?php echo esc_html( $s['email'] ); ?></a><span><?php echo esc_html( $s['address'] ); ?></span><span><?php echo esc_html( $s['hours'] ); ?></span></div>
</div><div class="wrap legal"><span>© <?php echo esc_html( current_time( 'Y' ) . ' ' . $s['business'] ); ?>. <?php echo esc_html( $site['footer']['note'] ); ?></span><span class="paywith">Pay with <b class="mpesa">M-Pesa</b></span></div></footer>
<a class="wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.6.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.2c0-.1-.2-.2-.5-.3z"/></svg><span>Chat with an engineer</span></a>
<?php if ( soko_is_staff() && ! isset( $_GET['soko_preview'] ) ) : // phpcs:ignore ?><a class="staff-pill" href="<?php echo esc_url( home_url( '/desk/#studio' ) ); ?>"><i></i>Edit in Dynamic Desk</a><?php endif; ?>
<div class="scrim" data-scrim></div><aside class="drawer" data-drawer aria-label="Details" aria-modal="true" role="dialog"></aside>
<div class="toast" data-toast role="status"></div>
<?php wp_footer(); ?>
</body></html>
		<?php
	}

	public static function assets() {
		/* Keep the storefront clean of theme styling. */
		global $wp_styles, $wp_scripts;
		$theme = get_template_directory_uri();
		$child = get_stylesheet_directory_uri();
		foreach ( array( $wp_styles, $wp_scripts ) as $reg ) {
			if ( ! $reg ) {
				continue;
			}
			foreach ( (array) $reg->queue as $h ) {
				$src = isset( $reg->registered[ $h ] ) ? (string) $reg->registered[ $h ]->src : '';
				if ( $src && ( 0 === strpos( $src, $theme ) || 0 === strpos( $src, $child ) || false !== strpos( $src, '/themes/' ) ) ) {
					if ( $reg === $wp_styles ) {
						wp_dequeue_style( $h );
					} else {
						wp_dequeue_script( $h );
					}
				}
			}
		}
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_enqueue_style( 'soko-store', SOKO_URL . 'assets/store.css', array(), SOKO_VERSION );
		wp_enqueue_script( 'soko-store', SOKO_URL . 'assets/store.js', array(), SOKO_VERSION, true );
		$s = Soko_Settings::all();
		wp_localize_script(
			'soko-store',
			'SOKO',
			array(
				'api'      => esc_url_raw( rest_url( 'soko/v1/' ) ),
				'home'     => home_url( '/' ),
				'currency' => $s['currency'],
				'vat'      => $s['vat_registered'] ? (float) $s['vat_rate'] : 0,
				'products' => self::catalogue_js(),
				'delivery' => $s['delivery'],
				'whatsapp' => preg_replace( '/\D/', '', $s['whatsapp'] ),
				'mpesa'    => Soko_Mpesa::ready(),
				'pay'      => Soko_Mpesa::instructions( '' ),
				'route'    => self::$route,
				'icons'    => array_combine( soko_icon_names(), array_map( function ( $k ) { return soko_icon( $k, 100 ); }, soko_icon_names() ) ),
			)
		);
	}
}
