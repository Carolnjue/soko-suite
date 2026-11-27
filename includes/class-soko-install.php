<?php
/**
 * Tables, roles and starting content.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Install {

	public static function activate() {
		self::tables();
		self::roles();
		Soko_Settings::ensure_defaults();
		if ( ! get_option( 'soko_seeded' ) ) {
			self::seed_catalogue();
			update_option( 'soko_seeded', 1 );
		}
		Soko_Blog::seed();
		update_option( 'soko_flush', 1, false );
		if ( ! get_option( 'soko_site' ) ) {
			update_option( 'soko_site', Soko_Sections::default_site(), false );
		}
		update_option( 'soko_db_version', SOKO_DB_VERSION );
		Soko_Store::rewrites();
		Soko_Cron::schedule();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		Soko_Cron::unschedule();
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'soko_db_version' ) !== SOKO_DB_VERSION ) {
			if ( in_array( get_option( 'soko_db_version' ), array( '1', '2' ), true ) ) {
				// 1.x staging sites: take the redesigned header, pages and category photos.
				update_option( 'soko_site', Soko_Sections::default_site(), false );
				delete_option( 'soko_categories' );
			}
			self::activate();
		}
		if ( get_option( 'soko_flush' ) ) {
			delete_option( 'soko_flush' );
			add_action( 'init', 'flush_rewrite_rules', 99 );
		}
	}

	public static function tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$t = 'soko_t';
		$sql = array();
		$sql[] = 'CREATE TABLE ' . $t( 'products' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			sku varchar(60) NOT NULL DEFAULT '',
			slug varchar(160) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			cat varchar(60) NOT NULL DEFAULT '',
			brand varchar(80) NOT NULL DEFAULT '',
			kind varchar(20) NOT NULL DEFAULT 'product',
			price decimal(14,2) NOT NULL DEFAULT 0,
			was decimal(14,2) NOT NULL DEFAULT 0,
			cost decimal(14,2) NOT NULL DEFAULT 0,
			stock decimal(12,2) NOT NULL DEFAULT 0,
			reorder decimal(12,2) NOT NULL DEFAULT 0,
			unit varchar(20) NOT NULL DEFAULT 'pc',
			runs varchar(255) NOT NULL DEFAULT '',
			description longtext NULL,
			specs longtext NULL,
			images longtext NULL,
			warranty varchar(120) NOT NULL DEFAULT '',
			tag varchar(40) NOT NULL DEFAULT '',
			watts decimal(10,2) NOT NULL DEFAULT 0,
			kwh decimal(10,2) NOT NULL DEFAULT 0,
			install_fee decimal(14,2) NOT NULL DEFAULT 0,
			featured tinyint(1) NOT NULL DEFAULT 0,
			visible tinyint(1) NOT NULL DEFAULT 1,
			vatable tinyint(1) NOT NULL DEFAULT 1,
			sort int(11) NOT NULL DEFAULT 0,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			updated datetime NULL,
			PRIMARY KEY  (id),
			KEY slug (slug),
			KEY cat (cat)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'customers' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			type varchar(30) NOT NULL DEFAULT 'Home',
			phone varchar(30) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			kra varchar(30) NOT NULL DEFAULT '',
			county varchar(60) NOT NULL DEFAULT '',
			address varchar(255) NOT NULL DEFAULT '',
			notes text NULL,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			PRIMARY KEY  (id),
			KEY phone (phone)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'docs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL DEFAULT 'invoice',
			number varchar(40) NOT NULL DEFAULT '',
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'draft',
			source varchar(20) NOT NULL DEFAULT 'desk',
			date date NULL,
			due date NULL,
			subtotal decimal(14,2) NOT NULL DEFAULT 0,
			vat decimal(14,2) NOT NULL DEFAULT 0,
			total decimal(14,2) NOT NULL DEFAULT 0,
			paid decimal(14,2) NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			fulfil varchar(20) NOT NULL DEFAULT '',
			notes text NULL,
			meta longtext NULL,
			token varchar(40) NOT NULL DEFAULT '',
			etims_status varchar(20) NOT NULL DEFAULT '',
			etims_ref varchar(80) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			updated datetime NULL,
			PRIMARY KEY  (id),
			KEY number (number),
			KEY type (type),
			KEY customer_id (customer_id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'lines' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			description varchar(255) NOT NULL DEFAULT '',
			qty decimal(12,2) NOT NULL DEFAULT 1,
			price decimal(14,2) NOT NULL DEFAULT 0,
			cost decimal(14,2) NOT NULL DEFAULT 0,
			vatable tinyint(1) NOT NULL DEFAULT 1,
			sort int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY doc_id (doc_id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'payments' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			method varchar(20) NOT NULL DEFAULT 'mpesa',
			code varchar(40) NOT NULL DEFAULT '',
			amount decimal(14,2) NOT NULL DEFAULT 0,
			phone varchar(30) NOT NULL DEFAULT '',
			payer varchar(190) NOT NULL DEFAULT '',
			account_ref varchar(60) NOT NULL DEFAULT '',
			doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
			receipt_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'unmatched',
			checkout_id varchar(80) NOT NULL DEFAULT '',
			raw longtext NULL,
			demo tinyint(1) NOT NULL DEFAULT 0,
			received datetime NULL,
			PRIMARY KEY  (id),
			KEY code (code),
			KEY checkout_id (checkout_id),
			KEY doc_id (doc_id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'stk' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
			phone varchar(30) NOT NULL DEFAULT '',
			amount decimal(14,2) NOT NULL DEFAULT 0,
			checkout_id varchar(80) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'sent',
			message varchar(255) NOT NULL DEFAULT '',
			created datetime NULL,
			PRIMARY KEY  (id),
			KEY checkout_id (checkout_id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'moves' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			qty decimal(12,2) NOT NULL DEFAULT 0,
			reason varchar(40) NOT NULL DEFAULT '',
			ref varchar(60) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'suppliers' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			contact varchar(120) NOT NULL DEFAULT '',
			phone varchar(30) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			kra varchar(30) NOT NULL DEFAULT '',
			notes text NULL,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			PRIMARY KEY  (id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'purchases' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			number varchar(40) NOT NULL DEFAULT '',
			supplier_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'ordered',
			date date NULL,
			expected date NULL,
			items longtext NULL,
			total decimal(14,2) NOT NULL DEFAULT 0,
			vat decimal(14,2) NOT NULL DEFAULT 0,
			paid decimal(14,2) NOT NULL DEFAULT 0,
			notes text NULL,
			received datetime NULL,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			PRIMARY KEY  (id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'expenses' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			date date NULL,
			category varchar(80) NOT NULL DEFAULT '',
			description varchar(255) NOT NULL DEFAULT '',
			amount decimal(14,2) NOT NULL DEFAULT 0,
			vat decimal(14,2) NOT NULL DEFAULT 0,
			method varchar(20) NOT NULL DEFAULT 'M-Pesa',
			reference varchar(60) NOT NULL DEFAULT '',
			receipt varchar(255) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			PRIMARY KEY  (id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'leads' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			phone varchar(30) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			source varchar(40) NOT NULL DEFAULT '',
			stage varchar(30) NOT NULL DEFAULT 'New',
			value decimal(14,2) NOT NULL DEFAULT 0,
			interest varchar(190) NOT NULL DEFAULT '',
			county varchar(60) NOT NULL DEFAULT '',
			notes text NULL,
			data longtext NULL,
			owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			next_action date NULL,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			updated datetime NULL,
			PRIMARY KEY  (id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'jobs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL DEFAULT '',
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			doc_id bigint(20) unsigned NOT NULL DEFAULT 0,
			stage varchar(30) NOT NULL DEFAULT 'Survey',
			techs varchar(190) NOT NULL DEFAULT '',
			date date NULL,
			location varchar(190) NOT NULL DEFAULT '',
			size varchar(60) NOT NULL DEFAULT '',
			checklist longtext NULL,
			notes text NULL,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			updated datetime NULL,
			PRIMARY KEY  (id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'events' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL DEFAULT '',
			kind varchar(30) NOT NULL DEFAULT 'Task',
			start datetime NULL,
			who varchar(190) NOT NULL DEFAULT '',
			location varchar(190) NOT NULL DEFAULT '',
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			remind tinyint(1) NOT NULL DEFAULT 1,
			reminded tinyint(1) NOT NULL DEFAULT 0,
			done tinyint(1) NOT NULL DEFAULT 0,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			PRIMARY KEY  (id),
			KEY start (start)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'licences' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			holder varchar(120) NOT NULL DEFAULT '',
			number varchar(80) NOT NULL DEFAULT '',
			expires date NULL,
			notes text NULL,
			reminded tinyint(1) NOT NULL DEFAULT 0,
			demo tinyint(1) NOT NULL DEFAULT 0,
			created datetime NULL,
			PRIMARY KEY  (id)
		) $c;";
		$sql[] = 'CREATE TABLE ' . $t( 'activity' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(190) NOT NULL DEFAULT '',
			object varchar(60) NOT NULL DEFAULT '',
			ref varchar(60) NOT NULL DEFAULT '',
			created datetime NULL,
			PRIMARY KEY  (id)
		) $c;";
		foreach ( $sql as $q ) {
			dbDelta( $q );
		}
	}

	public static function roles() {
		foreach ( soko_roles() as $role => $def ) {
			remove_role( $role );
			$caps = array( 'read' => true, 'upload_files' => true );
			foreach ( $def[1] as $cap ) {
				$caps[ $cap ] = true;
			}
			add_role( $role, $def[0], $caps );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( soko_modules() as $m ) {
				$admin->add_cap( $m[1] );
			}
		}
	}

	/** Default categories used by the storefront mega menu and the Desk. */
	public static function default_categories() {
		return array(
			array( 'id' => 'kits', 'image' => 'soko:seed/proj-hoho.jpg', 'name' => 'Solar kits', 'icon' => 'kit', 'group' => 'Complete systems' ),
			array( 'id' => 'panels', 'image' => 'soko:stock/cat-panels.jpg', 'name' => 'Solar panels', 'icon' => 'panel', 'group' => 'Generate' ),
			array( 'id' => 'hybrid', 'image' => 'soko:seed/prod-inverters.jpg', 'name' => 'Hybrid inverters', 'icon' => 'inverter', 'group' => 'Convert' ),
			array( 'id' => 'gridtie', 'image' => 'soko:seed/proj-karen.jpg', 'name' => 'Grid-tie inverters', 'icon' => 'inverter', 'group' => 'Convert' ),
			array( 'id' => 'lithium', 'image' => 'soko:seed/prod-lithium.jpg', 'name' => 'Lithium batteries', 'icon' => 'battery', 'group' => 'Store energy' ),
			array( 'id' => 'gel', 'image' => 'soko:stock/cat-storage.jpg', 'name' => 'Gel batteries', 'icon' => 'battery', 'group' => 'Store energy' ),
			array( 'id' => 'stations', 'image' => 'soko:seed/prod-station.jpg', 'name' => 'Portable power stations', 'icon' => 'station', 'group' => 'Store energy' ),
			array( 'id' => 'heaters', 'image' => 'soko:seed/prod-heaters.jpg', 'name' => 'Solar water heaters', 'icon' => 'heater', 'group' => 'Water' ),
			array( 'id' => 'pumps', 'image' => 'soko:seed/prod-pump.jpg', 'name' => 'Solar water pumps', 'icon' => 'pump', 'group' => 'Water' ),
			array( 'id' => 'flood', 'image' => 'soko:stock/cat-flood.jpg', 'name' => 'Solar floodlights', 'icon' => 'bulb', 'group' => 'Light and security' ),
			array( 'id' => 'street', 'image' => 'soko:stock/cat-street.jpg', 'name' => 'Solar street lights', 'icon' => 'street', 'group' => 'Light and security' ),
			array( 'id' => 'cctv', 'image' => 'soko:stock/cat-cctv.jpg', 'name' => 'Solar CCTV', 'icon' => 'cctv', 'group' => 'Light and security' ),
			array( 'id' => 'car', 'image' => 'soko:stock/cat-car.jpg', 'name' => 'Car batteries', 'icon' => 'carbat', 'group' => 'More' ),
			array( 'id' => 'acc', 'image' => 'soko:seed/prod-accessories.jpg', 'name' => 'Accessories', 'icon' => 'cable', 'group' => 'More' ),
			array( 'id' => 'services', 'image' => 'soko:seed/team-roof.jpg', 'name' => 'Services', 'icon' => 'tools', 'group' => 'More' ),
		);
	}

	/** The catalogue from the company profile. Prices are starting values to confirm in the Desk. */
	public static function seed_catalogue() {
		global $wpdb;
		$seed = 'soko:seed/';
		$p    = function ( $sku, $name, $cat, $price, $stock, $specs, $runs, $extra = array() ) use ( $seed ) {
			$row = array_merge(
				array(
					'sku'         => $sku,
					'slug'        => sanitize_title( $name ),
					'name'        => $name,
					'cat'         => $cat,
					'price'       => $price,
					'cost'        => round( $price * 0.64 ),
					'stock'       => $stock,
					'reorder'     => $price > 100000 ? 3 : ( $price > 30000 ? 6 : 15 ),
					'runs'        => $runs,
					'specs'       => wp_json_encode( $specs ),
					'images'      => '[]',
					'warranty'    => '1 year',
					'visible'      => 1,
					'description' => '',
					'created'     => soko_now(),
					'updated'     => soko_now(),
				),
				$extra
			);
			if ( isset( $row['img'] ) ) {
				$row['images'] = wp_json_encode( array( $seed . $row['img'] ) );
				unset( $row['img'] );
			}
			return $row;
		};
		$rows = array(
			$p( 'KIT-3', '3 kW off-grid home kit', 'kits', 185000, 9, array( 'Panels' => '4 × 585 W mono', 'Inverter' => '3 kW off-grid, pure sine', 'Battery' => '5 kWh lithium', 'Includes' => 'Mounting, cables, breakers, surge protection' ), 'Lights, TV, fridge, Wi-Fi and laptops, day and night', array( 'tag' => 'Most popular', 'was' => 205000, 'featured' => 1, 'watts' => 3000, 'kwh' => 5, 'install_fee' => 21000, 'warranty' => '5 yr inverter · 6 yr battery' ) ),
			$p( 'KIT-5', '5 kW hybrid home kit', 'kits', 395000, 6, array( 'Panels' => '8 × 585 W mono', 'Inverter' => '5 kW hybrid', 'Battery' => '10 kWh lithium', 'Includes' => 'Mounting, protection, Wi-Fi monitoring' ), 'A full family home, including a water pump and an iron', array( 'watts' => 5000, 'kwh' => 10, 'install_fee' => 35000, 'warranty' => '5 yr inverter · 6 yr battery' ) ),
			$p( 'KIT-10', '10 kW commercial hybrid kit', 'kits', 890000, 3, array( 'Panels' => '18 × 585 W mono', 'Inverter' => '10 kW three-phase hybrid', 'Battery' => '15 kWh lithium', 'Includes' => 'Mounting, protection, monitoring' ), 'Bakeries, clinics, schools and small hotels', array( 'watts' => 10000, 'kwh' => 15, 'install_fee' => 70000, 'warranty' => '5 yr inverter · 6 yr battery' ) ),
			$p( 'PV-440', 'Mono solar panel 440 W', 'panels', 13800, 64, array( 'Power' => '440 W', 'Cells' => 'Mono PERC, half-cut', 'Efficiency' => '21.0%', 'Size' => '1722 × 1134 mm' ), 'About 1.8 kWh a day in Nairobi sun', array( 'watts' => 440, 'brand' => 'JA Solar', 'warranty' => '12 yr product · 25 yr output' ) ),
			$p( 'PV-585', 'Mono solar panel 585 W', 'panels', 17900, 120, array( 'Power' => '585 W', 'Cells' => 'Mono PERC, half-cut', 'Efficiency' => '22.6%', 'Size' => '2278 × 1134 mm' ), 'About 2.4 kWh a day in Nairobi sun', array( 'watts' => 585, 'brand' => 'JA Solar', 'tag' => 'Best seller', 'featured' => 1, 'warranty' => '12 yr product · 25 yr output' ) ),
			$p( 'PV-700', 'Bifacial solar panel 700 W', 'panels', 23500, 22, array( 'Power' => '700 W', 'Cells' => 'N-type bifacial', 'Efficiency' => '22.5%', 'Size' => '2384 × 1303 mm' ), 'Best for ground mounts and carports', array( 'watts' => 700, 'brand' => 'JA Solar', 'warranty' => '12 yr product · 30 yr output' ) ),
			$p( 'INV-H5', 'Hybrid inverter 5 kW, 48 V', 'hybrid', 78000, 18, array( 'Output' => '5 kW pure sine', 'Solar input' => '6.5 kW, 2 MPPT', 'Battery' => '48 V lithium or lead-acid', 'Monitoring' => 'Wi-Fi app' ), 'Runs a home on solar, battery and KPLC together', array( 'img' => 'prod-inverters.jpg', 'watts' => 5000, 'featured' => 1, 'install_fee' => 9000, 'warranty' => '3 years' ) ),
			$p( 'INV-V5', 'Victron MultiPlus-II 5 kVA', 'hybrid', 265000, 4, array( 'Output' => '4 kW continuous', 'Charger' => '70 A', 'Transfer' => 'Under 20 ms', 'Monitoring' => 'VRM portal' ), 'Premium backup for offices, clinics and data rooms', array( 'brand' => 'Victron Energy', 'watts' => 4000, 'install_fee' => 15000, 'warranty' => '5 years' ) ),
			$p( 'GTI-10', 'Grid-tie inverter 10 kW', 'gridtie', 145000, 7, array( 'Output' => '10 kW three-phase', 'MPPT' => '2 trackers', 'Efficiency' => '98.3%', 'Monitoring' => 'Wi-Fi app' ), 'Cuts daytime KPLC bills for businesses', array( 'watts' => 10000, 'install_fee' => 20000, 'warranty' => '5 years' ) ),
			$p( 'LI-5', 'Lithium battery 5 kWh', 'lithium', 135000, 26, array( 'Capacity' => '5.12 kWh', 'Voltage' => '51.2 V', 'Cycles' => '6,000 at 80% depth', 'Mount' => 'Wall or rack' ), 'About 8 hours of lights, TV, fridge and Wi-Fi', array( 'img' => 'prod-lithium.jpg', 'brand' => 'Lithium Valley', 'kwh' => 5.12, 'tag' => 'In demand', 'featured' => 1, 'install_fee' => 5000, 'warranty' => '6 years' ) ),
			$p( 'LI-10', 'Lithium battery 10 kWh', 'lithium', 245000, 11, array( 'Capacity' => '10.24 kWh', 'Voltage' => '51.2 V', 'Cycles' => '6,000 at 80% depth', 'Mount' => 'Floor stand' ), 'A full night for a family home', array( 'img' => 'prod-lithium.jpg', 'brand' => 'Lithium Valley', 'kwh' => 10.24, 'install_fee' => 7000, 'warranty' => '6 years' ) ),
			$p( 'GEL-200', 'Gel battery 200 Ah, 12 V', 'gel', 32000, 40, array( 'Capacity' => '200 Ah', 'Voltage' => '12 V', 'Cycles' => '1,200 at 50% depth', 'Type' => 'Deep-cycle gel' ), 'Budget backup for small systems', array( 'kwh' => 2.4 ) ),
			$p( 'PS-1', 'Portable power station 1 kWh', 'stations', 89000, 15, array( 'Battery' => '1 kWh built in', 'Inverter' => '200 W AC', 'Charging' => 'Solar, AC or car', 'Uses' => 'Home backup, outdoor work, events' ), 'Router, lights, laptop and POS through a blackout', array( 'img' => 'prod-station.jpg', 'kwh' => 1, 'watts' => 200, 'tag' => 'Entry level', 'featured' => 1, 'warranty' => '2 years' ) ),
			$p( 'SWH-150', 'Solar water heater 150 L', 'heaters', 78000, 9, array( 'Capacity' => '150 L', 'Type' => 'Pressurised, flat plate', 'Tank' => 'Stainless steel', 'Backup' => 'Electric element' ), 'Hot showers for a family of 3', array( 'img' => 'prod-heaters.jpg', 'install_fee' => 9000, 'warranty' => '5 years' ) ),
			$p( 'SWH-200', 'Solar water heater 200 L', 'heaters', 98000, 12, array( 'Capacity' => '200 L', 'Type' => 'Pressurised, flat plate', 'Tank' => 'Stainless steel', 'Backup' => 'Electric element' ), 'Hot showers for a family of 4 to 5', array( 'img' => 'prod-heaters.jpg', 'featured' => 1, 'install_fee' => 9000, 'warranty' => '5 years' ) ),
			$p( 'SWH-300', 'Solar water heater 300 L', 'heaters', 135000, 8, array( 'Capacity' => '300 L', 'Type' => 'Pressurised, flat plate', 'Tank' => 'Stainless steel', 'Backup' => 'Electric element' ), 'Large homes, guest houses and staff quarters', array( 'img' => 'prod-heaters.jpg', 'install_fee' => 12000, 'warranty' => '5 years' ) ),
			$p( 'PMP-DC', 'DC brushless solar pump 1.5 HP', 'pumps', 68000, 10, array( 'Power' => '1.1 kW', 'Head' => 'Up to 60 m', 'Flow' => 'About 4 m³ an hour', 'Panels' => '4 × 585 W' ), 'Irrigation and tank filling without diesel', array( 'img' => 'prod-pump.jpg', 'featured' => 1, 'install_fee' => 12000, 'warranty' => '2 years' ) ),
			$p( 'PMP-HY', 'AC/DC hybrid borehole pump 3 HP', 'pumps', 145000, 5, array( 'Power' => '2.2 kW', 'Head' => 'Up to 120 m', 'Flow' => 'About 6 m³ an hour', 'Input' => 'Solar and KPLC' ), 'Borehole solarisation for farms and institutions', array( 'install_fee' => 25000, 'warranty' => '2 years' ) ),
			$p( 'PMP-INV', 'Solar pumping inverter 4 kW', 'pumps', 62000, 8, array( 'Output' => '4 kW', 'Input' => 'Solar DC or AC', 'Control' => 'Dry-run and tank-full protection' ), 'Runs an existing AC borehole pump on solar', array( 'warranty' => '2 years' ) ),
			$p( 'FL-200', 'Solar floodlight 200 W', 'flood', 9500, 80, array( 'Power' => '200 W', 'Battery' => 'Lithium', 'Runtime' => '10 to 12 hours', 'Control' => 'Remote and dusk sensor' ), 'Compounds, parking and farm gates', array( 'was' => 11000, 'featured' => 1 ) ),
			$p( 'FL-100', 'Solar floodlight 100 W', 'flood', 6500, 60, array( 'Power' => '100 W', 'Battery' => 'Lithium', 'Runtime' => '10 hours', 'Control' => 'Remote and dusk sensor' ), 'Gates, verandas and small compounds' ),
			$p( 'ST-100', 'All-in-one solar street light 100 W', 'street', 14500, 46, array( 'Power' => '100 W', 'Battery' => 'Lithium', 'Pole' => '6 m recommended', 'Sensor' => 'Motion and dusk' ), 'Estate roads, schools and hotels', array( 'img' => 'prod-streetlight.jpg', 'install_fee' => 4500, 'warranty' => '2 years' ) ),
			$p( 'CCTV-4G', 'Solar 4G CCTV camera', 'cctv', 18500, 30, array( 'Video' => '4 MP', 'Link' => '4G SIM or Wi-Fi', 'Power' => 'Built-in panel and battery', 'App' => 'View on your phone' ), 'Gates, farms and sites with no power line', array( 'install_fee' => 3000 ) ),
			$p( 'CAR-N70', 'Chloride Exide N70 car battery', 'car', 14800, 34, array( 'Capacity' => '70 Ah', 'Voltage' => '12 V', 'Fits' => 'Saloons and SUVs', 'Type' => 'Maintenance-free' ), 'Same-day fitting in Nairobi', array( 'brand' => 'Chloride Exide' ) ),
			$p( 'ACC-PRO', 'PV protection kit', 'acc', 12500, 55, array( 'Includes' => 'MC4 set, DC and AC surge protection, breakers', 'Rating' => '1000 V DC', 'Box' => 'IP65 combiner box' ), 'Protects every system against surges', array( 'img' => 'prod-accessories.jpg' ) ),
			$p( 'ACC-CAB', 'Solar cable 6 mm², 100 m', 'acc', 22000, 25, array( 'Size' => '6 mm²', 'Rating' => '1500 V DC', 'Colour' => 'Red and black', 'Length' => '100 m drum' ), 'Panel strings and battery runs', array( 'unit' => 'drum' ) ),
			$p( 'SRV-SURVEY', 'Site survey and system design', 'services', 3500, 0, array( 'Includes' => 'Roof check, load audit, written proposal', 'Area' => 'Nairobi and Kiambu' ), 'Deducted from your system if you go ahead', array( 'kind' => 'service', 'visible' => 1 ) ),
			$p( 'SRV-INSTALL', 'Installation and commissioning', 'services', 21000, 0, array( 'By' => 'EPRA-licensed technicians' ), 'Priced per system after survey', array( 'kind' => 'service', 'visible' => 0 ) ),
			$p( 'SRV-MAINT', 'Annual maintenance visit', 'services', 6500, 0, array( 'Includes' => 'Panel cleaning, connection check, battery health report' ), 'Keeps your system at full output', array( 'kind' => 'service' ) ),
		);
		$i = 0;
		foreach ( $rows as $r ) {
			$r['sort'] = $i++;
			$wpdb->insert( soko_t( 'products' ), $r );
		}
		update_option( 'soko_categories', self::default_categories(), false );
	}
}
