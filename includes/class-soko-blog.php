<?php
/**
 * Blog: posts written in the Desk, shown at /blog/ in the storefront design.
 *
 * @package Soko
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Blog {

	public static function row( $p, $full = false ) {
		$img = get_post_meta( $p->ID, '_soko_image', true );
		$out = array(
			'id'      => $p->ID,
			'title'   => get_the_title( $p ),
			'slug'    => $p->post_name,
			'status'  => $p->post_status,
			'date'    => get_the_date( 'Y-m-d', $p ),
			'excerpt' => $p->post_excerpt ? $p->post_excerpt : wp_trim_words( wp_strip_all_tags( $p->post_content ), 28 ),
			'image'   => soko_abs( $img ),
			'topic'   => get_post_meta( $p->ID, '_soko_topic', true ),
			'url'     => home_url( '/blog/' . $p->post_name . '/' ),
			'mins'    => max( 1, (int) ceil( str_word_count( wp_strip_all_tags( $p->post_content ) ) / 200 ) ),
		);
		if ( $full ) {
			$out['content'] = $p->post_content;
		}
		return $out;
	}

	public static function posts( $status = 'publish', $limit = -1 ) {
		$q = get_posts( array( 'post_type' => 'post', 'post_status' => $status, 'numberposts' => $limit, 'orderby' => 'date', 'order' => 'DESC' ) );
		return array_map( array( __CLASS__, 'row' ), $q );
	}

	public static function save( $b ) {
		$id   = isset( $b['id'] ) ? (int) $b['id'] : 0;
		$data = array(
			'post_type'    => 'post',
			'post_title'   => sanitize_text_field( isset( $b['title'] ) ? $b['title'] : '' ),
			'post_content' => wp_kses_post( isset( $b['content'] ) ? $b['content'] : '' ),
			'post_excerpt' => sanitize_textarea_field( isset( $b['excerpt'] ) ? $b['excerpt'] : '' ),
			'post_status'  => ( isset( $b['status'] ) && 'publish' === $b['status'] ) ? 'publish' : 'draft',
		);
		if ( $id ) {
			$data['ID'] = $id;
			wp_update_post( $data );
		} else {
			$id = wp_insert_post( $data );
		}
		update_post_meta( $id, '_soko_image', soko_rel( esc_url_raw( isset( $b['image'] ) ? $b['image'] : '' ) ) );
		update_post_meta( $id, '_soko_topic', sanitize_text_field( isset( $b['topic'] ) ? $b['topic'] : '' ) );
		soko_log( 'Saved blog post', 'blog', $data['post_title'] );
		return self::row( get_post( $id ), true );
	}

	/** Paragraphs from plain text, keeping simple markup the editor adds. */
	public static function body( $html ) {
		return wpautop( $html );
	}

	public static function render_list() {
		$posts = self::posts();
		$h     = '<section class="phero bloghero"><div class="wrap"><div class="pcopy"><span class="kick">Guides and news</span><h1>Solar know-how</h1><p>Plain answers on sizing, batteries, water heating and keeping your system running, from the engineers who install them.</p></div></div></section><section class="band"><div class="wrap">';
		if ( ! $posts ) {
			return $h . '<div class="panel empty"><h3>Articles are on the way</h3><p>In the meantime, our engineers will answer any question on WhatsApp.</p><a class="btn b-sun" href="' . esc_url( home_url( '/contact/' ) ) . '">Talk to us</a></div></div></section>';
		}
		$first = array_shift( $posts );
		$h    .= '<a class="bfeat" href="' . esc_url( $first['url'] ) . '"><span class="bimg">' . ( $first['image'] ? '<img src="' . esc_url( $first['image'] ) . '" alt="">' : '' ) . '</span><span class="bcopy">' . ( $first['topic'] ? '<em>' . esc_html( $first['topic'] ) . '</em>' : '' ) . '<h2>' . esc_html( $first['title'] ) . '</h2><p>' . esc_html( $first['excerpt'] ) . '</p><small>' . esc_html( date_i18n( 'j M Y', strtotime( $first['date'] ) ) ) . ' · ' . (int) $first['mins'] . ' min read</small><b>Read article →</b></span></a>';
		if ( $posts ) {
			$h .= '<div class="bgrid">';
			foreach ( $posts as $p ) {
				$h .= '<a class="bcard" href="' . esc_url( $p['url'] ) . '"><span class="bimg">' . ( $p['image'] ? '<img src="' . esc_url( $p['image'] ) . '" alt="" loading="lazy">' : '' ) . '</span>' . ( $p['topic'] ? '<em>' . esc_html( $p['topic'] ) . '</em>' : '' ) . '<h3>' . esc_html( $p['title'] ) . '</h3><p>' . esc_html( $p['excerpt'] ) . '</p><small>' . esc_html( date_i18n( 'j M Y', strtotime( $p['date'] ) ) ) . ' · ' . (int) $p['mins'] . ' min read</small><b class="more">Read article →</b></a>';
			}
			$h .= '</div>';
		}
		return $h . '</div></section>';
	}

	public static function render_post( $slug ) {
		$q = get_posts( array( 'name' => sanitize_title( $slug ), 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1 ) );
		if ( ! $q ) {
			return '';
		}
		$p    = self::row( $q[0], true );
		$more = array_slice( array_values( array_filter( self::posts(), function ( $x ) use ( $p ) { return $x['id'] !== $p['id']; } ) ), 0, 3 );
		$h    = '<article class="post"><header class="phead"><div class="wrap narrow"><nav class="crumbs"><a href="' . esc_url( home_url( '/blog/' ) ) . '">Blog</a><span>›</span><span>' . esc_html( $p['topic'] ? $p['topic'] : 'Article' ) . '</span></nav><h1>' . esc_html( $p['title'] ) . '</h1><p class="pmeta">' . esc_html( date_i18n( 'j M Y', strtotime( $p['date'] ) ) ) . ' · ' . (int) $p['mins'] . ' min read</p></div></header>';
		if ( $p['image'] ) {
			$h .= '<div class="wrap pimgw"><img class="pimg" src="' . esc_url( $p['image'] ) . '" alt=""></div>';
		}
		$h .= '<div class="wrap narrow rich pbody">' . self::body( $p['content'] ) . '<aside class="pcta"><div><b>Want this done for you?</b><span>Our EPRA-licensed engineers size, supply and install, then look after it.</span></div><a class="btn b-sun" href="' . esc_url( home_url( '/contact/' ) ) . '">Request a quote</a></aside></div></article>';
		// Products this article talks about, so a reader can buy in one tap.
		$text = strtolower( $p['title'] . ' ' . wp_strip_all_tags( $p['content'] ) );
		$hit  = array();
		foreach ( soko_categories() as $c ) {
			$w = strtolower( preg_replace( '/s$/', '', strtok( $c['name'], ' ' ) ) );
			if ( strlen( $w ) > 3 && preg_match( '/\b' . preg_quote( $w, '/' ) . '/', $text ) ) {
				$hit[] = $c['id'];
			}
		}
		if ( $hit ) {
			$prods = array_slice( array_values( array_filter( Soko_Ledger::products( array( 'visible' => 1 ) ), function ( $x ) use ( $hit ) { return in_array( $x['cat'], $hit, true ); } ) ), 0, 4 );
			if ( $prods ) {
				$h .= '<section class="band"><div class="wrap"><h2 style="margin-bottom:20px">Products in this article</h2><div class="grid">';
				foreach ( $prods as $x ) {
					$h .= Soko_Store::card( $x );
				}
				$h .= '</div></div></section>';
			}
		}
		if ( $more ) {
			$h .= '<section class="band tint"><div class="wrap"><h2 style="margin-bottom:20px">Keep reading</h2><div class="bgrid">';
			foreach ( $more as $m ) {
				$h .= '<a class="bcard" href="' . esc_url( $m['url'] ) . '"><span class="bimg">' . ( $m['image'] ? '<img src="' . esc_url( $m['image'] ) . '" alt="" loading="lazy">' : '' ) . '</span><h3>' . esc_html( $m['title'] ) . '</h3><small>' . (int) $m['mins'] . ' min read</small></a>';
			}
			$h .= '</div></div></section>';
		}
		return array( 'html' => $h, 'post' => $p );
	}

	/** Three starter guides, created once so the blog never opens empty. */
	public static function seed() {
		$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
		if ( $hello && false !== strpos( $hello->post_content, 'Welcome to WordPress' ) ) {
			wp_delete_post( $hello->ID, true );
		}
		if ( get_option( 'soko_blog_seeded' ) || get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_soko_image' ) ) ) {
			return;
		}
		update_option( 'soko_blog_seeded', 1, false );
		$posts = array(
			array(
				'title'   => 'How to size a solar system for your home',
				'topic'   => 'Buying guide',
				'image'   => 'soko:seed/team-roof.jpg',
				'excerpt' => 'Work out what you want to run, for how long, and what that means for panels, inverter and battery.',
				'content' => "Start with what you want to keep running, not with panel sizes. A home that only needs lights, a TV, Wi-Fi and phone charging needs a very different system from one that runs a borehole pump or an iron.\n\n<h2>1. List your appliances</h2>\nWrite down each appliance, its wattage (on the label or the manual) and how many hours a day you use it. Multiply watts by hours to get watt-hours a day, then add them up.\n\n<h2>2. Size the battery for the evening and night</h2>\nMost of your power is used after sunset. Size the battery so it covers your evening and night use with some margin. Lithium batteries can be used to about 80% of their capacity every day; gel batteries should stay nearer 50%.\n\n<h2>3. Size the panels to refill the battery</h2>\nMost of Kenya gets around five useful sun hours a day. Divide your daily use by five, then add about a quarter for losses in cables, the inverter and dusty panels.\n\n<h2>4. Pick an inverter for your peak load</h2>\nAdd up everything that could run at the same time. Motors such as fridges and pumps draw several times their rated power for a moment when they start, so allow headroom.\n\nOur solar calculator does these sums for you. An engineer then confirms the size on a site survey before you pay for installation.",
			),
			array(
				'title'   => 'Lithium or gel batteries: which should you buy?',
				'topic'   => 'Batteries',
				'image'   => 'soko:seed/prod-lithium.jpg',
				'excerpt' => 'Lithium costs more upfront but usually costs less over the life of the system. Here is how to compare them.',
				'content' => "Batteries are often the most expensive part of a solar system, so it pays to compare them on cost over their life, not just the price tag.\n\n<h2>Usable capacity</h2>\nA 5 kWh lithium battery gives you about 4 kWh of usable energy every day. A gel battery of the same size should only be drained to about half, so you get around 2.5 kWh.\n\n<h2>Lifespan</h2>\nGood lithium batteries are rated for several thousand cycles, which is many years of daily use. Gel batteries typically last a fraction of that when cycled every day.\n\n<h2>When gel still makes sense</h2>\nGel batteries are a reasonable choice for backup systems that are rarely drained, or where the budget is tight today and the system will be upgraded later.\n\n<h2>Our advice</h2>\nFor a home or business that uses the battery every night, lithium is usually the better buy. Ask us for a side-by-side quote and we will show you the cost per year for both.",
			),
			array(
				'title'   => 'Keeping your solar panels at full output',
				'topic'   => 'Maintenance',
				'image'   => 'soko:seed/proj-hydeout.jpg',
				'excerpt' => 'Dust, shade and loose connections quietly cut how much power you get. A few simple checks keep your system working hard.',
				'content' => "Solar systems need little maintenance, but a few checks make a real difference to how much power you get.\n\n<h2>Clean the panels</h2>\nDust and bird droppings can cut output noticeably, especially in the dry season. Rinse the panels with clean water early in the morning or late in the evening, when the glass is cool. Do not use detergents or abrasive brushes.\n\n<h2>Watch for new shade</h2>\nA growing tree or a new building can shade part of an array. Even partial shade on one panel can pull down the output of the whole string.\n\n<h2>Check the monitoring app</h2>\nIf your inverter has Wi-Fi monitoring, look at the daily production now and then. A sudden drop on a sunny day usually means a fault worth checking.\n\n<h2>Book a service visit</h2>\nOnce a year, have a technician check connections, breakers, surge protection and battery health. Our aftercare visits cover all of this.",
			),
		);
		foreach ( $posts as $p ) {
			$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $p['title'], 'post_content' => $p['content'], 'post_excerpt' => $p['excerpt'] ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_soko_image', $p['image'] );
				update_post_meta( $id, '_soko_topic', $p['topic'] );
			}
		}
	}
}
