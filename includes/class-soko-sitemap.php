<?php
/**
 * Adds shop pages, categories and products to the WordPress sitemap.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Sitemap extends WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'soko';
		$this->object_type = 'soko';
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		$urls = array( array( 'loc' => home_url( '/' ) ), array( 'loc' => home_url( '/shop/' ) ), array( 'loc' => home_url( '/planner/' ) ) );
		$site = Soko_Sections::site();
		foreach ( array_keys( (array) $site['pages'] ) as $slug ) {
			if ( 'home' !== $slug ) {
				$urls[] = array( 'loc' => home_url( '/' . $slug . '/' ) );
			}
		}
		foreach ( soko_categories() as $c ) {
			$urls[] = array( 'loc' => home_url( '/shop/' . $c['id'] . '/' ) );
		}
		foreach ( Soko_Ledger::products( array( 'visible' => 1 ) ) as $p ) {
			$urls[] = array( 'loc' => $p['url'] );
		}
		return $urls;
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
