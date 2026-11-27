<?php
/**
 * Website content model: pages built from sections, plus header, footer and brand.
 * Site Studio in the Desk edits this; the storefront renders it.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Sections {

	/* ============================================================ schema */

	public static function schema() {
		$cta  = array(
			array( 'k' => 'cta_label', 'label' => 'Button text', 'type' => 'text' ),
			array( 'k' => 'cta_link', 'label' => 'Button link', 'type' => 'link' ),
		);
		$head = array(
			array( 'k' => 'title', 'label' => 'Heading', 'type' => 'text' ),
			array( 'k' => 'text', 'label' => 'Intro text', 'type' => 'textarea' ),
		);
		$icons = soko_icon_names();
		return array(
			'hero_carousel' => array(
				'label'  => 'Hero carousel',
				'desc'   => 'Full-width sliding banners at the top of a page.',
				'fields' => array(
					array( 'k' => 'slides', 'label' => 'Slides', 'type' => 'items', 'fields' => array(
						array( 'k' => 'tab', 'label' => 'Tab label', 'type' => 'text' ),
						array( 'k' => 'kicker', 'label' => 'Small line above headline', 'type' => 'text' ),
						array( 'k' => 'title', 'label' => 'Headline', 'type' => 'text' ),
						array( 'k' => 'text', 'label' => 'Text', 'type' => 'textarea' ),
						array( 'k' => 'cta_label', 'label' => 'Main button', 'type' => 'text' ),
						array( 'k' => 'cta_link', 'label' => 'Main button link', 'type' => 'link' ),
						array( 'k' => 'cta2_label', 'label' => 'Second button', 'type' => 'text' ),
						array( 'k' => 'cta2_link', 'label' => 'Second button link', 'type' => 'link' ),
						array( 'k' => 'scene', 'label' => 'Illustration', 'type' => 'select', 'options' => array( 'home' => 'Home with rooftop solar', 'business' => 'Business and carport', 'farm' => 'Farm and borehole', 'water' => 'Solar water heating', 'light' => 'Street lights at dusk' ) ),
						array( 'k' => 'image', 'label' => 'Photo', 'type' => 'image' ),
						array( 'k' => 'fact', 'label' => 'Badge figure (e.g. 70 kW)', 'type' => 'text' ),
						array( 'k' => 'fact_text', 'label' => 'Badge text', 'type' => 'text' ),
					) ),
					array( 'k' => 'autoplay', 'label' => 'Slide automatically', 'type' => 'toggle' ),
				),
			),
			'page_hero'     => array(
				'label'  => 'Page banner',
				'desc'   => 'A heading band for inner pages.',
				'fields' => array_merge( $head, $cta, array(
					array( 'k' => 'style', 'label' => 'Style', 'type' => 'select', 'options' => array( 'sky' => 'Light sky', 'blue' => 'Soft gradient', 'photo' => 'Photo', 'split' => 'Text beside photo', 'mosaic' => 'Project photo mosaic with figures', 'actions' => 'Quick contact buttons' ) ),
					array( 'k' => 'image', 'label' => 'Photo', 'type' => 'image' ),
					array( 'k' => 'scene', 'label' => 'Illustration (when no photo)', 'type' => 'select', 'options' => array( '' => 'None', 'home' => 'Home', 'business' => 'Business', 'farm' => 'Farm', 'water' => 'Water heating', 'light' => 'Street lights' ) ),
				) ),
			),
			'trust_strip'   => array(
				'label'  => 'Trust strip',
				'desc'   => 'A coloured band of short promises.',
				'fields' => array(
					array( 'k' => 'items', 'label' => 'Points', 'type' => 'items', 'fields' => array(
						array( 'k' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => array_combine( $icons, $icons ) ),
						array( 'k' => 'title', 'label' => 'Bold line', 'type' => 'text' ),
						array( 'k' => 'text', 'label' => 'Second line', 'type' => 'text' ),
					) ),
				),
			),
			'categories'    => array( 'label' => 'Shop by category', 'desc' => 'Tiles for every product category.', 'fields' => $head ),
			'planner'       => array( 'label' => 'Solar calculator', 'desc' => 'Visitors tap appliances and get a sized system with a price.', 'fields' => $head ),
			'products'      => array(
				'label'  => 'Product grid',
				'desc'   => 'Featured, on offer or a category.',
				'fields' => array_merge( $head, array(
					array( 'k' => 'source', 'label' => 'Show', 'type' => 'select', 'options' => array( 'featured' => 'Featured products', 'offer' => 'Products on offer', 'category' => 'One category', 'new' => 'Newest products' ) ),
					array( 'k' => 'category', 'label' => 'Category', 'type' => 'category' ),
					array( 'k' => 'tabs', 'label' => 'Show category tabs', 'type' => 'toggle' ),
					array( 'k' => 'limit', 'label' => 'How many', 'type' => 'number' ),
				) ),
			),
			'services'      => array(
				'label'  => 'Services',
				'desc'   => 'Cards for what the business does.',
				'fields' => array_merge( $head, array(
					array( 'k' => 'items', 'label' => 'Services', 'type' => 'items', 'fields' => array(
						array( 'k' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => array_combine( $icons, $icons ) ),
						array( 'k' => 'title', 'label' => 'Title', 'type' => 'text' ),
						array( 'k' => 'text', 'label' => 'Text', 'type' => 'textarea' ),
						array( 'k' => 'link', 'label' => 'Link', 'type' => 'link' ),
						array( 'k' => 'image', 'label' => 'Photo', 'type' => 'image' ),
					) ),
				) ),
			),
			'projects'      => array(
				'label'  => 'Projects',
				'desc'   => 'Photos of completed installations, edited under Site Studio → Projects.',
				'fields' => array_merge( $head, array(
					array( 'k' => 'limit', 'label' => 'How many (0 = all)', 'type' => 'number' ),
					array( 'k' => 'filters', 'label' => 'Show sector filters', 'type' => 'toggle' ),
				) ),
			),
			'steps'         => array(
				'label'  => 'How it works',
				'desc'   => 'Steps joined by a line.',
				'fields' => array_merge( $head, array(
					array( 'k' => 'items', 'label' => 'Steps', 'type' => 'items', 'fields' => array(
						array( 'k' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => array_combine( $icons, $icons ) ),
						array( 'k' => 'title', 'label' => 'Title', 'type' => 'text' ),
						array( 'k' => 'text', 'label' => 'Text', 'type' => 'textarea' ),
					) ),
				) ),
			),
			'logos'         => array(
				'label'  => 'Clients and partners',
				'desc'   => 'Names or logos of clients and brands.',
				'fields' => array(
					array( 'k' => 'title', 'label' => 'Left heading', 'type' => 'text' ),
					array( 'k' => 'left', 'label' => 'Left items', 'type' => 'items', 'fields' => array( array( 'k' => 'name', 'label' => 'Name', 'type' => 'text' ), array( 'k' => 'logo', 'label' => 'Logo (optional)', 'type' => 'image' ) ) ),
					array( 'k' => 'title2', 'label' => 'Right heading', 'type' => 'text' ),
					array( 'k' => 'right', 'label' => 'Right items', 'type' => 'items', 'fields' => array( array( 'k' => 'name', 'label' => 'Name', 'type' => 'text' ), array( 'k' => 'logo', 'label' => 'Logo (optional)', 'type' => 'image' ) ) ),
				),
			),
			'text_image'    => array(
				'label'  => 'Text and photo',
				'desc'   => 'A story block beside a picture.',
				'fields' => array_merge(
					array( array( 'k' => 'kicker', 'label' => 'Small line above heading', 'type' => 'text' ), array( 'k' => 'title', 'label' => 'Heading', 'type' => 'text' ), array( 'k' => 'text', 'label' => 'Text', 'type' => 'richtext' ) ),
					$cta,
					array(
						array( 'k' => 'image', 'label' => 'Photo', 'type' => 'image' ),
						array( 'k' => 'image2', 'label' => 'Second photo (optional)', 'type' => 'image' ),
						array( 'k' => 'side', 'label' => 'Photo side', 'type' => 'select', 'options' => array( 'right' => 'Right', 'left' => 'Left' ) ),
					)
				),
			),
			'columns'       => array(
				'label'  => 'Columns',
				'desc'   => 'Two to four short blocks, such as mission and vision.',
				'fields' => array_merge( $head, array(
					array( 'k' => 'items', 'label' => 'Columns', 'type' => 'items', 'fields' => array( array( 'k' => 'title', 'label' => 'Title', 'type' => 'text' ), array( 'k' => 'text', 'label' => 'Text', 'type' => 'textarea' ) ) ),
				) ),
			),
			'stats'         => array(
				'label'  => 'Key figures',
				'desc'   => 'Large numbers with labels. Use real figures only.',
				'fields' => array( array( 'k' => 'items', 'label' => 'Figures', 'type' => 'items', 'fields' => array( array( 'k' => 'value', 'label' => 'Figure', 'type' => 'text' ), array( 'k' => 'label', 'label' => 'Label', 'type' => 'text' ) ) ) ),
			),
			'gallery'       => array(
				'label'  => 'Photo gallery',
				'desc'   => 'A grid of photos.',
				'fields' => array_merge( $head, array( array( 'k' => 'images', 'label' => 'Photos', 'type' => 'items', 'fields' => array( array( 'k' => 'image', 'label' => 'Photo', 'type' => 'image' ), array( 'k' => 'caption', 'label' => 'Caption', 'type' => 'text' ) ) ) ) ),
			),
			'testimonials'  => array(
				'label'  => 'Customer reviews',
				'desc'   => 'Real quotes from real customers, with their permission.',
				'fields' => array_merge( $head, array( array( 'k' => 'items', 'label' => 'Reviews', 'type' => 'items', 'fields' => array( array( 'k' => 'quote', 'label' => 'Quote', 'type' => 'textarea' ), array( 'k' => 'name', 'label' => 'Name', 'type' => 'text' ), array( 'k' => 'role', 'label' => 'Place or business', 'type' => 'text' ) ) ) ) ),
			),
			'faq'           => array(
				'label'  => 'Questions and answers',
				'desc'   => 'Foldable FAQs.',
				'fields' => array_merge( $head, array( array( 'k' => 'items', 'label' => 'Questions', 'type' => 'items', 'fields' => array( array( 'k' => 'q', 'label' => 'Question', 'type' => 'text' ), array( 'k' => 'a', 'label' => 'Answer', 'type' => 'textarea' ) ) ) ) ),
			),
			'cta_band'      => array(
				'label'  => 'Call to action band',
				'desc'   => 'A bold band with buttons.',
				'fields' => array_merge( $head, $cta, array(
					array( 'k' => 'cta2_label', 'label' => 'Second button', 'type' => 'text' ),
					array( 'k' => 'cta2_link', 'label' => 'Second button link', 'type' => 'link' ),
					array( 'k' => 'color', 'label' => 'Colour', 'type' => 'select', 'options' => array( 'sun' => 'Sun orange', 'blue' => 'Brand blue' ) ),
					array( 'k' => 'image', 'label' => 'Photo', 'type' => 'image' ),
					array( 'k' => 'callback', 'label' => 'Show a call-me-back form', 'type' => 'toggle' ),
				) ),
			),
			'savings'       => array(
				'label'  => 'Savings calculator',
				'desc'   => 'Visitors slide their monthly power bill and see the system size, cost and payback.',
				'fields' => array_merge( $head, array(
					array( 'k' => 'tariff', 'label' => 'Average power price, KES per kWh', 'type' => 'number' ),
					array( 'k' => 'kw_cost', 'label' => 'Installed cost per kW of panels and inverter, KES', 'type' => 'number' ),
					array( 'k' => 'kwh_cost', 'label' => 'Installed cost per kWh of lithium battery, KES', 'type' => 'number' ),
					array( 'k' => 'image', 'label' => 'Photo', 'type' => 'image' ),
				) ),
			),
			'contact'       => array(
				'label'  => 'Contact and quote form',
				'desc'   => 'Contact details beside the quote request form. Requests arrive in the Desk as leads.',
				'fields' => array_merge( $head, array( array( 'k' => 'map', 'label' => 'Google Maps embed link (optional)', 'type' => 'link' ) ) ),
			),
			'video'         => array(
				'label'  => 'Video',
				'desc'   => 'A YouTube video.',
				'fields' => array_merge( $head, array( array( 'k' => 'url', 'label' => 'YouTube link', 'type' => 'link' ) ) ),
			),
			'rich_text'     => array(
				'label'  => 'Text',
				'desc'   => 'Formatted paragraphs, lists and links.',
				'fields' => array( array( 'k' => 'title', 'label' => 'Heading', 'type' => 'text' ), array( 'k' => 'text', 'label' => 'Text', 'type' => 'richtext' ) ),
			),
		);
	}

	/* ========================================================== defaults */

	public static function default_site() {
		$img = 'soko:seed/';
		$sec = function ( $type, $props ) {
			return array( 'id' => substr( md5( $type . wp_json_encode( $props ) . wp_rand() ), 0, 8 ), 'type' => $type, 'on' => 1, 'props' => $props );
		};
		$services = array(
			array( 'icon' => 'panel', 'image' => 'soko:seed/team-roof.jpg', 'title' => 'Solar PV systems', 'text' => 'On-grid, off-grid and hybrid systems for homes, businesses and institutions, sized to how you use power.', 'link' => '/shop/kits/' ),
			array( 'icon' => 'heater', 'image' => 'soko:seed/team-heater.jpg', 'title' => 'Solar water heating', 'text' => 'Pressurised heaters from 150 to 350 litres, supplied, installed and tested.', 'link' => '/shop/heaters/' ),
			array( 'icon' => 'pump', 'image' => 'soko:seed/proj-baraka.jpg', 'title' => 'Borehole solarisation', 'text' => 'Pumps and pumping inverters that run boreholes and farm machinery on sunshine.', 'link' => '/shop/pumps/' ),
			array( 'icon' => 'street', 'image' => 'soko:seed/team-street.jpg', 'title' => 'Solar lighting', 'text' => 'Floodlights and street lights for compounds, estates, schools and farms.', 'link' => '/shop/street/' ),
			array( 'icon' => 'battery', 'image' => 'soko:seed/proj-bunyore.jpg', 'title' => 'Power backup', 'text' => 'Lithium storage and inverters that keep you running through blackouts.', 'link' => '/shop/lithium/' ),
			array( 'icon' => 'cctv', 'image' => 'soko:stock/smart-panel.jpg', 'title' => 'IT and security', 'text' => 'Solar CCTV, networking and IT consultancy for offices and remote sites.', 'link' => '/shop/cctv/' ),
		);
		$steps = array(
			array( 'icon' => 'bulb', 'title' => 'Consultation', 'text' => 'Tell us what you want to power. We advise for free, by phone or on site.' ),
			array( 'icon' => 'tools', 'title' => 'Survey and design', 'text' => 'Our engineers check your roof, wiring and usage, then size the system.' ),
			array( 'icon' => 'panel', 'title' => 'Installation', 'text' => 'EPRA-licensed technicians install, test and hand over, usually in one to two days.' ),
			array( 'icon' => 'shield', 'title' => 'Aftercare', 'text' => 'Monitoring, cleaning and maintenance keep your system at full output.' ),
		);
		$cta = array( 'title' => 'Not sure which system you need?', 'text' => 'Talk to an engineer at no cost. If a site visit is needed, the survey fee is deducted from your system when you go ahead, and you get a fixed quote with installation.', 'cta_label' => 'Book a site survey', 'cta_link' => '/contact/', 'cta2_label' => 'Calculate my system', 'cta2_link' => '/planner/', 'color' => 'sun' );
		return array(
			'brand'    => array( 'primary' => '#1b4f9e', 'accent' => '#f7941d', 'deep' => '#1d2156', 'head_font' => 'Poppins', 'body_font' => 'Inter', 'logo' => 'soko:img/dss-logo.png', 'radius' => 18 ),
			'header'   => array(
				'announce'    => 'Free sizing advice by phone or WhatsApp',
				'announce_on' => 1,
				'menu'        => array(
					array( 'label' => 'Solar kits', 'link' => '/shop/kits/', 'cats' => 'kits', 'promo' => 'planner' ),
					array( 'label' => 'Solar panels', 'link' => '/shop/panels/', 'cats' => 'panels', 'promo' => 'planner' ),
					array( 'label' => 'Inverters', 'link' => '/shop/hybrid/', 'cats' => 'hybrid,gridtie', 'promo' => 'planner' ),
					array( 'label' => 'Batteries', 'link' => '/shop/lithium/', 'cats' => 'lithium,gel,stations,car', 'promo' => 'planner' ),
					array( 'label' => 'Water heating & pumps', 'link' => '/shop/heaters/', 'cats' => 'heaters,pumps', 'promo' => 'install' ),
					array( 'label' => 'Lighting & CCTV', 'link' => '/shop/flood/', 'cats' => 'flood,street,cctv', 'promo' => 'install' ),
					array( 'label' => 'Accessories', 'link' => '/shop/acc/', 'cats' => 'acc', 'promo' => 'install' ),
					array( 'label' => 'Services', 'link' => '/services/' ),
				),
				'utility'     => array(
					array( 'label' => 'About us', 'link' => '/about/' ),
					array( 'label' => 'Projects', 'link' => '/projects/' ),
					array( 'label' => 'Blog', 'link' => '/blog/' ),
					array( 'label' => 'Contact', 'link' => '/contact/' ),
				),
				'cta_label'   => 'Request a quote',
				'cta_link'    => '/contact/',
			),
			'footer'   => array(
				'about' => 'Solar solutions and IT consultancy. Designed, supplied, installed and maintained by EPRA-licensed technicians and engineers.',
				'note'  => 'Think solar, think Dynamic.',
			),
			'projects' => array(
				array( 'name' => 'The Hydeout Hotel', 'place' => 'Kisumu', 'size' => '70 kW', 'type' => 'Grid-tied with backup', 'sector' => 'Hospitality', 'img' => $img . 'proj-hydeout.jpg' ),
				array( 'name' => 'Karen Oasis Hotel', 'place' => 'Nairobi', 'size' => '60 kW', 'type' => 'Solar PV', 'sector' => 'Hospitality', 'img' => $img . 'proj-karen.jpg' ),
				array( 'name' => 'Bunyore Girls High School', 'place' => 'Vihiga', 'size' => '16 kW · 23 kWh', 'type' => 'Hybrid, phase 2', 'sector' => 'Education', 'img' => $img . 'proj-bunyore.jpg' ),
				array( 'name' => 'Baraka Farm', 'place' => 'Kangundo', 'size' => '15 kW', 'type' => 'Borehole and farm machinery solarisation', 'sector' => 'Agriculture', 'img' => $img . 'proj-baraka.jpg' ),
				array( 'name' => 'Nairobi International School', 'place' => 'Nairobi', 'size' => '12 kW', 'type' => 'Hybrid, kindergarten', 'sector' => 'Education', 'img' => $img . 'proj-nis.jpg' ),
				array( 'name' => 'Private residence', 'place' => 'Nairobi', 'size' => '15 kW', 'type' => 'Hybrid with solar water heating', 'sector' => 'Homes', 'img' => $img . 'proj-domestic.jpg' ),
				array( 'name' => 'Hoho Farm', 'place' => 'Kisumu', 'size' => '10 kW', 'type' => 'Hybrid', 'sector' => 'Agriculture', 'img' => $img . 'proj-hoho.jpg' ),
				array( 'name' => 'Lukundo Bakery', 'place' => 'Voi', 'size' => '10 kW', 'type' => 'Hybrid', 'sector' => 'Business', 'img' => $img . 'proj-lukundo.jpg' ),
			),
			'pages'    => array(
				'home'     => array(
					'title'    => 'Home',
					'seo'      => 'Solar systems, batteries, water heaters and pumps in Kenya | Dynamic Smart Systems',
					'desc'     => 'Buy solar kits, panels, lithium batteries, inverters, water heaters and pumps online with easy payment. Installed by EPRA-licensed engineers.',
					'sections' => array(
						$sec( 'hero_carousel', array( 'autoplay' => 1, 'slides' => array(
							array( 'tab' => 'Homes', 'kicker' => 'Home solar kits', 'title' => 'Stay powered through every blackout', 'text' => 'Panels, inverter and lithium battery in one kit, priced with installation.', 'cta_label' => 'Shop home kits', 'cta_link' => '/shop/kits/', 'cta2_label' => 'Calculate my system', 'cta2_link' => '/planner/', 'image' => 'soko:stock/home-family.jpg', 'fact' => '3–10 kW', 'fact_text' => 'complete home kits' ),
							array( 'tab' => 'Businesses', 'kicker' => 'Commercial and institutional', 'title' => 'Cut your power bill, keep your business running', 'text' => 'Hybrid and grid-tied systems for hotels, schools, farms and offices, from a 10 kW bakery to a 70 kW hotel.', 'cta_label' => 'Request a quote', 'cta_link' => '/contact/', 'cta2_label' => 'See our projects', 'cta2_link' => '/projects/', 'image' => 'soko:stock/business-team.jpg', 'fact' => '70 kW', 'fact_text' => 'largest hotel install' ),
							array( 'tab' => 'Installation', 'kicker' => 'Design, supply and install', 'title' => 'One team, from site survey to switch-on', 'text' => 'We design, supply, install and commission every system, then stay with you through warranty and aftercare.', 'cta_label' => 'Book a site survey', 'cta_link' => '/contact/', 'cta2_label' => 'Our services', 'cta2_link' => '/services/', 'image' => 'soko:stock/installer-roof.jpg', 'fact' => 'EPRA', 'fact_text' => 'licensed technicians' ),
							array( 'tab' => 'Smart systems', 'kicker' => 'Power backup, CCTV and IT', 'title' => 'Backup power, security and IT support', 'text' => 'Solar CCTV, street and flood lighting, power backup and IT consultancy for homes, offices and institutions.', 'cta_label' => 'Shop CCTV and lighting', 'cta_link' => '/shop/cctv/', 'cta2_label' => 'Talk to an engineer', 'cta2_link' => '/contact/', 'image' => 'soko:stock/smart-panel.jpg' ),
						) ) ),
						$sec( 'trust_strip', array( 'items' => array(
							array( 'icon' => 'shield', 'title' => 'EPRA-licensed', 'text' => 'technicians and engineers' ),
							array( 'icon' => 'battery', 'title' => 'Easy payment', 'text' => 'receipt sent instantly' ),
							array( 'icon' => 'kit', 'title' => 'Countrywide delivery', 'text' => 'and installation' ),
							array( 'icon' => 'tools', 'title' => 'Aftercare', 'text' => 'maintenance visits' ),
						) ) ),
						$sec( 'categories', array( 'title' => 'Shop by category', 'text' => 'Panels, inverters, batteries, water heating, lighting and more, with prices shown.' ) ),
						$sec( 'products', array( 'title' => 'Popular products', 'text' => 'Every product shows its specifications, what it can power, warranty and installation cost.', 'source' => 'featured', 'limit' => 8, 'tabs' => 1 ) ),
						$sec( 'savings', array( 'title' => 'What could solar save you?', 'text' => 'Slide to your monthly electricity bill and see the system you need, what it costs and how soon it pays for itself.', 'image' => $img . 'proj-lukundo.jpg' ) ),
						$sec( 'services', array( 'title' => 'Solar solutions and IT consultancy', 'text' => 'Designed, supplied, installed and maintained by one team of EPRA-licensed technicians and engineers.', 'items' => $services ) ),
						$sec( 'projects', array( 'title' => 'Recent projects', 'text' => 'From a 10 kW bakery in Voi to a 70 kW hotel in Kisumu.', 'limit' => 4 ) ),
						$sec( 'steps', array( 'title' => 'How we work', 'text' => 'Clear steps, one point of contact, no surprises on price.', 'items' => $steps ) ),
						$sec( 'logos', array( 'title' => 'Trusted by', 'left' => array( array( 'name' => 'Radbone Clark' ), array( 'name' => 'Printpak' ), array( 'name' => 'Lindberg Holidays' ), array( 'name' => 'The Hydeout Hotel' ), array( 'name' => 'Karen Oasis Hotel' ), array( 'name' => 'Bunyore Girls High School' ) ), 'title2' => 'Brands we install', 'right' => array( array( 'name' => 'JA Solar' ), array( 'name' => 'Victron Energy' ), array( 'name' => 'Lithium Valley' ), array( 'name' => 'Chloride Exide' ) ) ) ),
						$sec( 'cta_band', array_merge( $cta, array( 'image' => $img . 'team-street.jpg', 'callback' => 1 ) ) ),
					),
				),
				'services' => array(
					'title'    => 'Services',
					'seo'      => 'Solar installation, water heating, borehole solarisation and IT services | Dynamic Smart Systems',
					'desc'     => 'Solar PV design and installation, solar water heating, borehole solarisation, solar lighting, power backup and IT consultancy across Kenya.',
					'sections' => array(
						$sec( 'page_hero', array( 'title' => 'Everything solar, from one team', 'text' => 'We design, supply, install and maintain. One point of contact from the first survey to the final switch-on.', 'style' => 'photo', 'image' => $img . 'team-roof.jpg', 'cta_label' => 'Book a site survey', 'cta_link' => '/contact/' ) ),
						$sec( 'services', array( 'title' => 'Our services', 'text' => '', 'items' => $services ) ),
						$sec( 'text_image', array( 'kicker' => 'Water for farms and homes', 'title' => 'Borehole and farm solarisation', 'text' => '<p>Diesel pumps are expensive to run and break down when you need water most. We size a solar pump or a pumping inverter for your borehole depth and daily water need, so your tank fills every sunny day.</p><p>We also solarise farm machinery and cold rooms.</p>', 'image' => $img . 'proj-baraka.jpg', 'side' => 'right', 'cta_label' => 'See pumps', 'cta_link' => '/shop/pumps/' ) ),
						$sec( 'text_image', array( 'kicker' => 'Hot water', 'title' => 'Solar water heating', 'text' => '<p>Pressurised flat-plate heaters with stainless steel tanks, from 150 to 350 litres. We install, pressure-test and hand over, with an electric backup for cloudy days.</p>', 'image' => $img . 'team-heater.jpg', 'side' => 'left', 'cta_label' => 'See water heaters', 'cta_link' => '/shop/heaters/' ) ),
						$sec( 'steps', array( 'title' => 'How we work', 'text' => '', 'items' => $steps ) ),
						$sec( 'cta_band', $cta ),
					),
				),
				'projects' => array(
					'title'    => 'Projects',
					'seo'      => 'Solar projects: hotels, schools, farms and homes | Dynamic Smart Systems',
					'desc'     => 'Solar installations delivered by Dynamic Smart Systems across Kenya.',
					'sections' => array(
						$sec( 'page_hero', array( 'title' => 'Projects we have delivered', 'text' => 'Hotels, schools, farms, bakeries and homes across Kenya, designed and installed by our own engineers.', 'style' => 'mosaic' ) ),
						$sec( 'projects', array( 'title' => '', 'text' => '', 'limit' => 0, 'filters' => 1 ) ),
						$sec( 'cta_band', array_merge( $cta, array( 'title' => 'Planning a project like these?', 'color' => 'blue' ) ) ),
					),
				),
				'about'    => array(
					'title'    => 'About',
					'seo'      => 'About Dynamic Smart Systems | Solar solutions and IT consultancy, Nairobi',
					'desc'     => 'Dynamic Smart Systems designs, supplies, installs and maintains solar systems across East Africa.',
					'sections' => array(
						$sec( 'page_hero', array( 'title' => 'Think solar, think Dynamic', 'text' => 'Solar solutions and IT consultancy from Nairobi, for homes, businesses, farms and institutions across East Africa.', 'style' => 'split', 'image' => $img . 'team-crew.jpg' ) ),
						$sec( 'text_image', array( 'kicker' => 'Our story', 'title' => 'One team from first survey to final switch-on', 'text' => '<p>We design, install and maintain solar and IT systems for homes, businesses, farms and institutions, sized to how each client actually uses power.</p><p>Our technicians and engineers are licensed by EPRA. We install proven brands such as Victron, JA Solar and Lithium Valley, and quote a fixed price before any work starts.</p>', 'image' => $img . 'team-roof.jpg', 'image2' => $img . 'team-street.jpg', 'side' => 'right', 'cta_label' => 'See our projects', 'cta_link' => '/projects/' ) ),
						$sec( 'columns', array( 'title' => 'What drives us', 'text' => '', 'items' => array(
							array( 'title' => 'Our mission', 'text' => 'To offer an all-in-one, customised solution: solar PV on and off grid, solar water heating, solar lighting, borehole solarisation and power backup.' ),
							array( 'title' => 'Our vision', 'text' => 'To provide solar for productive use and become East Africa\'s favourite solar solution provider, customer-centred and cost-effective.' ),
							array( 'title' => 'Our approach', 'text' => 'Customised, high-quality solar solutions with advanced technology and expert support for sustainable energy.' ),
						) ) ),
						$sec( 'gallery', array( 'title' => 'Our team in action', 'text' => '', 'images' => array(
							array( 'image' => $img . 'team-heater.jpg', 'caption' => 'Rooftop solar water heater testing' ),
							array( 'image' => $img . 'team-roof.jpg', 'caption' => 'Arranging panels on a roof' ),
							array( 'image' => $img . 'team-street.jpg', 'caption' => 'Installing solar street lights' ),
							array( 'image' => $img . 'team-crew.jpg', 'caption' => 'Our EPRA-licensed crew' ),
						) ) ),
						$sec( 'cta_band', $cta ),
					),
				),
				'contact'  => array(
					'title'    => 'Contact',
					'seo'      => 'Contact Dynamic Smart Systems | Get a solar quote',
					'desc'     => 'Request a solar quote or book a site survey. Call or WhatsApp 0712 936 685.',
					'sections' => array(
						$sec( 'page_hero', array( 'title' => 'Get a quote or book a site survey', 'text' => 'Tell us what you need powered or installed, and an engineer will get back to you with advice and a quote.', 'style' => 'actions' ) ),
						$sec( 'contact', array( 'title' => '', 'text' => '' ) ),
						$sec( 'faq', array( 'title' => 'Common questions', 'text' => '', 'items' => array(
							array( 'q' => 'Do you install outside Nairobi?', 'a' => 'Yes. We have installed in Kisumu, Vihiga, Voi, Machakos and beyond. Delivery and installation costs depend on distance.' ),
							array( 'q' => 'How do I pay?', 'a' => 'By mobile money, bank transfer, card or cash, at checkout or against your invoice number. You get a receipt immediately. For larger projects we take a deposit to start and the balance on handover.' ),
							array( 'q' => 'Are your technicians licensed?', 'a' => 'Yes. Our technicians and engineers are licensed by EPRA.' ),
						) ) ),
					),
				),
			),
		);
	}

	public static function site() {
		$s = get_option( 'soko_site' );
		$d = self::default_site();
		if ( ! is_array( $s ) ) {
			return soko_abs( $d );
		}
		foreach ( $d as $k => $v ) {
			if ( ! isset( $s[ $k ] ) ) {
				$s[ $k ] = $v;
			}
		}
		return soko_abs( $s );
	}

	/** Draft preview from Site Studio, keyed by user. */
	public static function current() {
		if ( isset( $_GET['soko_preview'] ) && soko_can( 'soko_site' ) ) { // phpcs:ignore
			$d = get_transient( 'soko_site_draft_' . get_current_user_id() );
			if ( is_array( $d ) ) {
				return soko_abs( $d );
			}
		}
		return self::site();
	}

	private static function clean( $v, $key = '' ) {
		if ( is_array( $v ) ) {
			$o = array();
			foreach ( $v as $k => $x ) {
				$o[ $k ] = self::clean( $x, is_string( $k ) ? $k : $key );
			}
			return $o;
		}
		if ( is_bool( $v ) || is_int( $v ) || is_float( $v ) ) {
			return $v;
		}
		if ( 'text' === $key && false !== strpos( (string) $v, '<' ) ) {
			return wp_kses_post( $v );
		}
		if ( in_array( $key, array( 'html' ), true ) ) {
			return current_user_can( 'unfiltered_html' ) ? $v : wp_kses_post( $v );
		}
		return sanitize_textarea_field( (string) $v );
	}

	/** Keep the last ten published versions so a change can be undone. */
	public static function remember( $site ) {
		$h = get_option( 'soko_site_history', array() );
		$h = is_array( $h ) ? $h : array();
		$u = wp_get_current_user();
		array_unshift( $h, array( 'time' => soko_now(), 'user' => $u && $u->ID ? $u->display_name : '', 'site' => $site ) );
		update_option( 'soko_site_history', array_slice( $h, 0, 10 ), false );
	}

	public static function history() {
		$h = get_option( 'soko_site_history', array() );
		$out = array();
		foreach ( (array) $h as $i => $v ) {
			$out[] = array( 'i' => $i, 'time' => $v['time'], 'user' => $v['user'] );
		}
		return $out;
	}

	public static function restore( $i ) {
		$h = get_option( 'soko_site_history', array() );
		if ( empty( $h[ $i ]['site'] ) ) {
			return new WP_Error( 'soko', 'That version is no longer available.', array( 'status' => 404 ) );
		}
		$ver = $h[ $i ]['site'];
		self::remember( self::site() );
		update_option( 'soko_site', $ver, false );
		update_option( 'soko_flush', 1, false );
		return $ver;
	}

	public static function save( $data ) {
		if ( isset( $data['site'] ) && is_array( $data['site'] ) && isset( $data['site']['pages'] ) ) {
			$data = $data['site'];
		}
		$draft = ! empty( $data['_draft'] );
		unset( $data['_draft'] );
		$site = soko_rel( self::clean( $data ) );
		if ( $draft ) {
			set_transient( 'soko_site_draft_' . get_current_user_id(), $site, DAY_IN_SECONDS );
			return array( 'draft' => true );
		}
		$old = self::site();
		self::remember( $old );
		update_option( 'soko_site', $site, false );
		delete_transient( 'soko_site_draft_' . get_current_user_id() );
		update_option( 'soko_flush', 1, false );
		return $site;
	}

	/* ========================================================== rendering */

	public static function e( $s ) {
		return esc_html( (string) $s );
	}

	public static function link( $l ) {
		$l = (string) $l;
		if ( '' === $l ) {
			return '#';
		}
		if ( 0 === strpos( $l, '/' ) ) {
			return esc_url( home_url( $l ) );
		}
		return esc_url( $l );
	}

	public static function btn( $label, $link, $cls = 'b-sun' ) {
		return $label ? '<a class="btn ' . esc_attr( $cls ) . '" href="' . self::link( $link ) . '">' . self::e( $label ) . '</a>' : '';
	}

	public static function head( $p, $right = '' ) {
		if ( empty( $p['title'] ) && empty( $p['text'] ) ) {
			return '';
		}
		$kicks = array( 'categories' => 'Shop by need', 'products' => 'Best sellers', 'savings' => 'Savings calculator', 'services' => 'What we do', 'projects' => 'Our work', 'steps' => 'How it works', 'contact' => 'Talk to us', 'faq' => 'Good to know', 'testimonials' => 'Client stories', 'stats' => 'In numbers', 'features' => 'Why choose us', 'team' => 'Our people', 'gallery' => 'On site', 'columns' => 'Who we are', 'video' => 'Watch' );
		$type  = isset( $p['_type'] ) ? $p['_type'] : '';
		$kick  = isset( $p['kicker'] ) ? $p['kicker'] : ( isset( $kicks[ $type ] ) ? $kicks[ $type ] : '' );
		return '<div class="sec-head"><div>' . ( $kick ? '<span class="kick">' . self::e( $kick ) . '</span>' : '' ) . ( ! empty( $p['title'] ) ? '<h2>' . self::e( $p['title'] ) . '</h2>' : '' ) . ( ! empty( $p['text'] ) ? '<p>' . self::e( $p['text'] ) . '</p>' : '' ) . '</div>' . $right . '</div>';
	}

	public static function render( $s ) {
		$p = isset( $s['props'] ) ? (array) $s['props'] : array();
		$p['_type'] = isset( $s['type'] ) ? $s['type'] : '';
		$m = 'r_' . $s['type'];
		if ( ! method_exists( __CLASS__, $m ) ) {
			return '';
		}
		return '<div class="sk-sec sk-' . esc_attr( $s['type'] ) . '" data-sec="' . esc_attr( $s['id'] ) . '">' . self::$m( $p ) . '</div>';
	}

	/** Illustrated scenes: a sunlit sky with one subject and energy flowing. */
	public static function scene( $kind, $id = 'a' ) {
		$id   = preg_replace( '/[^a-z0-9]/', '', $id . $kind );
		$defs = '<defs><linearGradient id="sk' . $id . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#cfe4ff"/><stop offset="1" stop-color="#f4f9ff"/></linearGradient><linearGradient id="pv' . $id . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f6fd0"/><stop offset="1" stop-color="#123c84"/></linearGradient><pattern id="ce' . $id . '" width="16" height="12" patternUnits="userSpaceOnUse"><rect width="16" height="12" fill="url(#pv' . $id . ')"/><path d="M0 0H16M0 0V12" stroke="#9cc3ff" stroke-width=".8" opacity=".55"/></pattern></defs>';
		$night = 'light' === $kind;
		$sky   = $night ? '<rect width="1600" height="700" fill="#22396b"/><rect width="1600" height="700" fill="url(#sk' . $id . ')" opacity=".18"/>' : '<rect width="1600" height="700" fill="url(#sk' . $id . ')"/>';
		$sun   = $night ? '<g transform="translate(1240 130)"><circle r="46" fill="#fbe7b0"/><circle cx="18" cy="-10" r="44" fill="#22396b"/></g>' : '<g transform="translate(1180 140)"><circle r="120" fill="#ffd28a" opacity=".35"/><circle r="78" fill="#ffb347"/><circle r="62" fill="#f7941d"/></g>';
		$hills = $night
			? '<path d="M0 540 Q400 470 800 530 T1600 510 V700 H0z" fill="#1a3a2a"/><path d="M0 620 Q500 590 1000 620 T1600 610 V700 H0z" fill="#14301f"/>'
			: '<path d="M0 520 Q300 430 620 500 T1250 470 T1600 480 V700 H0z" fill="#b9dba0"/><path d="M0 580 Q400 520 800 575 T1600 560 V700 H0z" fill="#8fc46f"/><path d="M0 640 Q500 610 1000 640 T1600 630 V700 H0z" fill="#6aa94f"/>';
		$cells = 'url(#ce' . $id . ')';
		$flow  = function ( $d, $c = '#f7941d' ) {
			return '<path class="flow" d="' . $d . '" stroke="' . $c . '" stroke-width="6" fill="none" stroke-linecap="round"/>';
		};
		$s = '';
		switch ( $kind ) {
			case 'business':
				$rows = '';
				for ( $r = 0; $r < 3; $r++ ) {
					for ( $c = 0; $c < 9; $c++ ) {
						$x     = 40 + $r * 12 + $c * 64;
						$y     = 38 + $r * 14;
						$rows .= '<g transform="translate(' . $x . ' ' . $y . ') skewX(-30)"><rect width="58" height="12" fill="' . $cells . '" stroke="#0d2f6b" stroke-width="2"/></g>';
					}
				}
				$win = '';
				for ( $i = 0; $i < 5; $i++ ) {
					$win .= '<rect x="' . ( 40 + $i * 120 ) . '" y="140" width="80" height="60" rx="3" fill="#bcd6f7" stroke="#0f3474" stroke-width="4"/>';
				}
				$car = '';
				for ( $i = 0; $i < 4; $i++ ) {
					$car .= '<g transform="translate(' . ( 6 + $i * 70 ) . ' -34) skewX(-8)"><rect width="64" height="30" fill="' . $cells . '" stroke="#0d2f6b" stroke-width="2"/></g>';
				}
				$s = '<g transform="translate(760 330)"><rect x="0" y="80" width="640" height="220" fill="#eef2f8"/><rect x="0" y="80" width="640" height="26" fill="#1b4f9e"/><path d="M-10 84 L320 30 L650 84Z" fill="#c9d3e3"/>' . $rows . $win . '<rect x="270" y="215" width="100" height="85" fill="#0f3474"/></g><g transform="translate(420 520)"><rect x="0" y="0" width="300" height="10" fill="#0d2f6b"/>' . $car . '<rect x="20" y="10" width="8" height="90" fill="#7c8aa3"/><rect x="270" y="10" width="8" height="90" fill="#7c8aa3"/><rect x="60" y="60" width="120" height="44" rx="12" fill="#f7941d"/><circle cx="85" cy="104" r="12" fill="#0f1d36"/><circle cx="155" cy="104" r="12" fill="#0f1d36"/></g>' . $flow( 'M1120 210 C1080 260 1060 320 1080 370' );
				break;
			case 'farm':
				$crops = '';
				for ( $i = 0; $i < 7; $i++ ) {
					$crops .= '<path d="M' . ( 520 + $i * 60 ) . ' 640 q10 -40 22 -50 q8 20 -2 50z" fill="#3f8a32"/><path d="M' . ( 535 + $i * 60 ) . ' 640 q-14 -30 -24 -34 q2 20 14 34z" fill="#55a43f"/>';
				}
				$pan = '';
				for ( $i = 0; $i < 3; $i++ ) {
					$pan .= '<rect x="' . ( 30 + $i * 58 ) . '" y="-10" width="54" height="56" fill="' . $cells . '" stroke="#0d2f6b" stroke-width="2"/>';
				}
				$s = '<g transform="translate(1020 210)"><rect x="40" y="0" width="16" height="300" fill="#7c8aa3"/><rect x="140" y="0" width="16" height="300" fill="#7c8aa3"/><rect x="30" y="140" width="136" height="12" fill="#7c8aa3"/><ellipse cx="98" cy="-6" rx="88" ry="16" fill="#0f3474"/><rect x="10" y="-80" width="176" height="74" fill="#1b4f9e"/><ellipse cx="98" cy="-80" rx="88" ry="16" fill="#2f6fd0"/><path d="M10 -60h176M10 -40h176M10 -20h176" stroke="#0f3474" stroke-width="3"/></g><g transform="translate(700 420)"><rect x="70" y="40" width="10" height="120" fill="#7c8aa3"/><g transform="skewX(-20)">' . $pan . '</g><rect x="160" y="120" width="54" height="40" rx="6" fill="#f4f7fc" stroke="#0f3474" stroke-width="4"/><circle cx="187" cy="140" r="9" fill="#2fd27a"/></g>' . $crops . $flow( 'M915 520 C980 520 1040 480 1080 520', '#2f9bf0' ) . $flow( 'M1120 210 C1000 300 900 360 860 420' );
				break;
			case 'water':
				$s = '<g transform="translate(820 300)"><rect x="0" y="140" width="460" height="230" fill="#fff"/><rect x="0" y="140" width="40" height="230" fill="#e4ebf6"/><path d="M-30 150 L60 40 L400 40 L490 150Z" fill="#8a4424"/><g transform="translate(110 -10)"><rect x="0" y="0" width="230" height="40" rx="20" fill="#e9eef6" stroke="#0f3474" stroke-width="5"/><rect x="18" y="12" width="40" height="16" rx="8" fill="#f7941d"/></g><g transform="translate(100 46) skewX(-28)"><rect width="260" height="74" fill="' . $cells . '" stroke="#0d2f6b" stroke-width="3"/><path d="M65 0v74M130 0v74M195 0v74" stroke="#0d2f6b" stroke-width="3"/></g><rect x="70" y="200" width="90" height="80" rx="4" fill="#ffe2a8" stroke="#0f3474" stroke-width="5"/><rect x="300" y="200" width="90" height="80" rx="4" fill="#ffe2a8" stroke="#0f3474" stroke-width="5"/><rect x="190" y="260" width="76" height="110" rx="4" fill="#1b4f9e"/></g>' . $flow( 'M1120 210 C1100 250 1060 290 1030 330' ) . '<path class="flow" d="M960 470 q20 -20 0 -40 q-20 -20 0 -40" stroke="#ff7a59" stroke-width="5" fill="none" stroke-linecap="round"/>';
				break;
			case 'light':
				$poles = '';
				for ( $i = 0; $i < 4; $i++ ) {
					$x      = 700 + $i * 220;
					$poles .= '<path d="M' . ( $x - 60 ) . ' 560 L' . ( $x + 60 ) . ' 560 L' . ( $x + 10 ) . ' 330 L' . ( $x - 10 ) . ' 330Z" fill="#ffe7a3" opacity=".22"/><rect x="' . ( $x - 4 ) . '" y="250" width="8" height="330" fill="#9aa7bf"/><rect x="' . ( $x - 2 ) . '" y="250" width="70" height="8" fill="#9aa7bf"/><rect x="' . ( $x + 40 ) . '" y="240" width="56" height="16" rx="3" fill="' . $cells . '" stroke="#0d2f6b" stroke-width="2"/><rect x="' . ( $x + 38 ) . '" y="258" width="40" height="10" rx="4" fill="#fff4c9"/>';
				}
				$s = '<path d="M520 700 L1600 560 V700Z" fill="#2b3547"/>' . $poles;
				break;
			default:
				$panels = '';
				for ( $r = 0; $r < 2; $r++ ) {
					for ( $c = 0; $c < 6; $c++ ) {
						$y  = 28 + $r * 46;
						$x0 = 60 - $r * 14;
						$w  = 50 + $r * 2.4;
						$x  = $x0 + $c * ( $w + 4 );
						$panels .= '<polygon points="' . $x . ',' . $y . ' ' . ( $x + $w ) . ',' . $y . ' ' . ( $x + $w + 2 ) . ',' . ( $y + 40 ) . ' ' . ( $x - 6 ) . ',' . ( $y + 40 ) . '" fill="' . $cells . '" stroke="#0d2f6b" stroke-width="2"/>';
					}
				}
				$s = '<g transform="translate(860 300)"><rect x="0" y="120" width="420" height="230" fill="#fff"/><rect x="0" y="120" width="40" height="230" fill="#e4ebf6"/><path d="M-34 132 L40 14 L380 14 L454 132 Z" fill="#8a4424"/><path d="M-34 132 L40 14 L56 14 L-14 132Z" fill="#6b3218"/><path d="M-40 128 h500 v10 h-500z" fill="#5c2b14"/>' . $panels . '<rect x="60" y="180" width="80" height="70" rx="4" fill="#ffe2a8" stroke="#0f3474" stroke-width="5"/><rect x="280" y="180" width="80" height="70" rx="4" fill="#ffe2a8" stroke="#0f3474" stroke-width="5"/><rect x="175" y="240" width="70" height="110" rx="4" fill="#1b4f9e"/><circle cx="230" cy="298" r="5" fill="#f7941d"/><g transform="translate(450 250)"><rect width="70" height="100" rx="8" fill="#f4f7fc" stroke="#0f3474" stroke-width="4"/><rect x="14" y="16" width="42" height="20" rx="3" fill="#2fd27a"/><path d="M38 52l-10 16h10l-4 14 12-19h-10l3-11z" fill="#f7941d"/></g></g>' . $flow( 'M1110 220 C1080 260 1060 290 1040 318' ) . $flow( 'M1200 470 C1250 480 1290 520 1310 550' );
		}
		return '<svg class="scene" viewBox="0 0 1600 700" preserveAspectRatio="xMidYMax slice" aria-hidden="true">' . $defs . $sky . $sun . $hills . $s . '</svg>';
	}

	private static function r_hero_carousel( $p ) {
		$slides = isset( $p['slides'] ) ? (array) $p['slides'] : array();
		if ( ! $slides ) {
			return '';
		}
		$n = count( $slides );
		$h = '<section class="hero" aria-roledescription="carousel" data-autoplay="' . ( ! empty( $p['autoplay'] ) ? 1 : 0 ) . '"><div class="wrap hero-in"><div class="hstage">';
		foreach ( $slides as $i => $s ) {
			$img  = ! empty( $s['image'] ) ? '<img src="' . esc_url( $s['image'] ) . '" alt="" ' . ( $i ? 'loading="lazy"' : 'fetchpriority="high"' ) . '>' : self::scene( isset( $s['scene'] ) ? $s['scene'] : 'home', 'h' . $i );
			$kick = ! empty( $s['kicker'] ) ? '<span class="kick">' . self::e( $s['kicker'] ) . '</span>' : '';
			$fact = ! empty( $s['fact'] ) ? '<div class="hfact"><b>' . self::e( $s['fact'] ) . '</b><span>' . self::e( isset( $s['fact_text'] ) ? $s['fact_text'] : '' ) . '</span></div>' : '';
			$h   .= '<div class="hslide' . ( 0 === $i ? ' on' : '' ) . '" role="group" aria-roledescription="slide" aria-label="' . ( $i + 1 ) . ' of ' . $n . '"' . ( $i ? ' aria-hidden="true"' : '' ) . '>';
			$h   .= '<div class="hcopy">' . $kick . ( 0 === $i ? '<h1>' : '<h2 class="h1">' ) . self::e( $s['title'] ) . ( 0 === $i ? '</h1>' : '</h2>' ) . '<p>' . self::e( $s['text'] ) . '</p><div class="row">' . self::btn( $s['cta_label'], $s['cta_link'] ) . self::btn( isset( $s['cta2_label'] ) ? $s['cta2_label'] : '', isset( $s['cta2_link'] ) ? $s['cta2_link'] : '', 'b-line' ) . '</div></div>';
			$h   .= '<div class="hpic">' . $img . $fact . '</div></div>';
		}
		$h .= '</div>';
		if ( $n > 1 ) {
			$h .= '<div class="htabs" role="tablist">';
			foreach ( $slides as $i => $s ) {
				$label = ! empty( $s['tab'] ) ? $s['tab'] : ( 'Slide ' . ( $i + 1 ) );
				$h    .= '<button role="tab" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '"' . ( 0 === $i ? ' class="on"' : '' ) . '><span>0' . ( $i + 1 ) . '</span>' . self::e( $label ) . '<i></i></button>';
			}
			$h .= '</div>';
		}
		return $h . '</div></section>';
	}

	private static function r_page_hero( $p ) {
		$style = isset( $p['style'] ) ? $p['style'] : 'sky';
		$img   = ! empty( $p['image'] ) ? esc_url( $p['image'] ) : '';
		$btn   = self::btn( isset( $p['cta_label'] ) ? $p['cta_label'] : '', isset( $p['cta_link'] ) ? $p['cta_link'] : '' );
		$path  = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
		$pk    = array( 'services' => 'Our services', 'projects' => 'Our work', 'about' => 'About us', 'contact' => 'Contact us' );
		$kick  = ! empty( $p['kicker'] ) ? $p['kicker'] : ( isset( $pk[ $path ] ) ? $pk[ $path ] : '' );
		$copy  = ( $kick ? '<span class="kick">' . self::e( $kick ) . '</span>' : '' ) . '<h1>' . self::e( $p['title'] ) . '</h1>' . ( ! empty( $p['text'] ) ? '<p>' . self::e( $p['text'] ) . '</p>' : '' ) . ( $btn ? '<div class="row">' . $btn . '</div>' : '' );
		if ( 'split' === $style && $img ) {
			return '<section class="phero split"><div class="wrap"><div class="pcopy">' . $copy . '</div><div class="pimg"><img src="' . $img . '" alt=""></div></div></section>';
		}
		if ( 'photo' === $style && $img ) {
			return '<section class="phero photo" style="--img:url(' . $img . ')"><div class="wrap"><div class="pcopy">' . $copy . '</div></div></section>';
		}
		if ( 'mosaic' === $style ) {
			$site  = self::current();
			$pj    = (array) $site['projects'];
			$kw    = 0;
			foreach ( $pj as $x ) {
				if ( preg_match( '/([\d.]+)\s*kW/i', $x['size'], $mm ) ) {
					$kw += (float) $mm[1];
				}
			}
			$sect  = count( array_unique( wp_list_pluck( $pj, 'sector' ) ) );
			$tiles = '';
			foreach ( array_slice( $pj, 0, 3 ) as $k => $x ) {
				$tiles .= '<figure class="mz mz' . $k . '"><img src="' . esc_url( $x['img'] ) . '" alt="" ' . ( $k ? 'loading="lazy"' : '' ) . '><figcaption>' . self::e( $x['name'] ) . '</figcaption></figure>';
			}
			return '<section class="phero mosaic"><div class="wrap"><div class="pcopy">' . $copy . '<div class="mzstats"><div><b>' . count( $pj ) . '</b><span>featured projects</span></div><div><b>' . esc_html( rtrim( rtrim( number_format( $kw, 1 ), '0' ), '.' ) ) . ' kW</b><span>of solar in these projects</span></div><div><b>' . (int) $sect . '</b><span>sectors served</span></div></div></div><div class="mzgrid">' . $tiles . '</div></div></section>';
		}
		if ( 'actions' === $style ) {
			$st  = Soko_Settings::all();
			$wa  = 'https://wa.me/' . soko_phone( $st['whatsapp'] );
			$ico = function ( $d ) {
				return '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' . $d . '"/></svg>';
			};
			return '<section class="phero actions"><div class="wrap"><div class="pcopy">' . $copy . '</div><div class="qa">'
				. '<a href="tel:+' . esc_attr( soko_phone( $st['phone'] ) ) . '"><i>' . $ico( 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2' ) . '</i><b>Call us</b><span>' . self::e( $st['phone'] ) . '</span></a>'
				. '<a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener" class="wa2"><i>' . $ico( 'M3 21l1.6-4.6A8.5 8.5 0 1 1 8 20z' ) . '</i><b>WhatsApp</b><span>Chat with an engineer</span></a>'
				. '<a href="mailto:' . esc_attr( $st['email'] ) . '"><i>' . $ico( 'M3 5h18v14H3zM3 7l9 6 9-6' ) . '</i><b>Email</b><span>' . self::e( $st['email'] ) . '</span></a>'
				. '<a href="#quote-form"><i>' . $ico( 'M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11' ) . '</i><b>Quote form</b><span>Reply with advice and a price</span></a>'
				. '</div></div></section>';
		}
		$scene = ! empty( $p['scene'] ) && 'sky' === $style ? self::scene( $p['scene'], 'p' ) : '';
		return '<section class="phero ' . esc_attr( $style ) . ( $scene ? ' has-scene' : '' ) . '">' . $scene . '<div class="wrap"><div class="pcopy">' . $copy . '</div></div></section>';
	}

	private static function r_trust_strip( $p ) {
		$h = '<section class="strip"><div class="wrap">';
		foreach ( (array) ( isset( $p['items'] ) ? $p['items'] : array() ) as $i ) {
			$h .= '<div>' . soko_icon( isset( $i['icon'] ) ? $i['icon'] : 'shield', 34 ) . '<span><b>' . self::e( $i['title'] ) . '</b>' . self::e( $i['text'] ) . '</span></div>';
		}
		return $h . '</div></section>';
	}

	private static function r_categories( $p ) {
		$h = '<section class="band"><div class="wrap">' . self::head( $p, '<a class="btn b-line" href="' . esc_url( home_url( '/shop/' ) ) . '">View all products</a>' ) . '<div class="cats">';
		$count = array();
		foreach ( Soko_Ledger::products( array( 'visible' => 1 ) ) as $pr ) {
			$count[ $pr['cat'] ] = isset( $count[ $pr['cat'] ] ) ? $count[ $pr['cat'] ] + 1 : 1;
		}
		foreach ( soko_categories() as $c ) {
			if ( 'services' === $c['id'] ) {
				continue;
			}
			$n  = isset( $count[ $c['id'] ] ) ? $count[ $c['id'] ] : 0;
			$h .= '<a class="cat" href="' . esc_url( home_url( '/shop/' . $c['id'] . '/' ) ) . '"><div class="cimg">' . ( ! empty( $c['image'] ) ? '<img src="' . esc_url( $c['image'] ) . '" alt="" loading="lazy">' : '' ) . soko_icon( $c['icon'], 24 ) . '</div><span>' . self::e( $c['name'] ) . ( $n ? '<small>' . $n . '</small>' : '' ) . '</span></a>';
		}
		return $h . '</div></div></section>';
	}

	private static function r_planner( $p ) {
		return '<section class="planner band" id="planner"><div class="wrap pl-grid"><div><h2>' . self::e( $p['title'] ) . '</h2><p class="lead">' . self::e( $p['text'] ) . '</p><div class="apps" data-planner-apps></div></div><div class="result" data-planner-result aria-live="polite"></div></div></section>';
	}

	private static function r_products( $p ) {
		$limit = ! empty( $p['limit'] ) ? (int) $p['limit'] : 8;
		$all   = Soko_Ledger::products( array( 'visible' => 1 ) );
		$src   = isset( $p['source'] ) ? $p['source'] : 'featured';
		if ( 'featured' === $src ) {
			$all = array_filter( $all, function ( $x ) { return $x['featured']; } );
		} elseif ( 'offer' === $src ) {
			$all = array_filter( $all, function ( $x ) { return $x['was'] > $x['price']; } );
		} elseif ( 'category' === $src && ! empty( $p['category'] ) ) {
			$cat = $p['category'];
			$all = array_filter( $all, function ( $x ) use ( $cat ) { return $x['cat'] === $cat; } );
		} elseif ( 'new' === $src ) {
			usort( $all, function ( $a, $b ) { return $b['id'] - $a['id']; } );
		}
		$all = empty( $p['tabs'] ) ? array_slice( array_values( $all ), 0, $limit ) : array_values( $all );
		if ( ! $all ) {
			return '';
		}
		$tabs = '';
		if ( ! empty( $p['tabs'] ) ) {
			$site  = self::current();
			$every = Soko_Ledger::products( array( 'visible' => 1 ) );
			usort( $every, function ( $a, $b ) { return $b['featured'] - $a['featured']; } );
			$all   = $every;
			$tabs  = '<div class="ptabs" role="tablist" data-ptabs><button class="on" data-c="" role="tab">All</button>';
			foreach ( (array) $site['header']['menu'] as $m ) {
				if ( ! empty( $m['cats'] ) ) {
					$tabs .= '<button data-c="' . esc_attr( $m['cats'] ) . '" role="tab">' . self::e( $m['label'] ) . '</button>';
				}
			}
			$tabs .= '</div>';
		}
		$h = '<section class="band"><div class="wrap">' . self::head( $p, '<a class="btn b-line" href="' . esc_url( home_url( '/shop/' . ( 'category' === $src && ! empty( $p['category'] ) ? $p['category'] . '/' : '' ) ) ) . '">Shop all</a>' ) . $tabs . '<div class="grid" data-limit="' . (int) $limit . '"' . ( $tabs ? ' data-tabgrid' : '' ) . '>';
		foreach ( $all as $k => $x ) {
			$c  = Soko_Store::card( $x );
			$c  = preg_replace( '/^<article class="card"/', '<article class="card" data-cat="' . esc_attr( $x['cat'] ) . '"' . ( $k >= $limit ? ' hidden' : '' ), $c );
			$h .= $c;
		}
		return $h . '</div></div></section>';
	}

	private static function r_services( $p ) {
		$h = '<section class="band"><div class="wrap">' . self::head( $p ) . '<div class="svc">';
		foreach ( (array) ( isset( $p['items'] ) ? $p['items'] : array() ) as $i ) {
			$img = ! empty( $i['image'] ) ? '<span class="simg"><img src="' . esc_url( $i['image'] ) . '" alt="" loading="lazy"></span>' : '';
			$h  .= '<a class="' . ( $img ? 'has-img' : '' ) . '" href="' . self::link( isset( $i['link'] ) ? $i['link'] : '' ) . '">' . $img . '<span class="sbody">' . soko_icon( isset( $i['icon'] ) ? $i['icon'] : 'panel', 26 ) . '<h3>' . self::e( $i['title'] ) . '</h3><p>' . self::e( $i['text'] ) . '</p><em>Learn more <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></em></span></a>';
		}
		return $h . '</div></div></section>';
	}

	private static function r_projects( $p ) {
		$site  = self::current();
		$items = (array) $site['projects'];
		$limit = isset( $p['limit'] ) ? (int) $p['limit'] : 4;
		if ( $limit ) {
			$items = array_slice( $items, 0, $limit );
		}
		$filters = '';
		if ( ! empty( $p['filters'] ) ) {
			$secs    = array_unique( wp_list_pluck( $items, 'sector' ) );
			$filters = '<div class="filters" data-pj-filters><button class="chip on" data-f="">All</button>';
			foreach ( $secs as $s ) {
				$filters .= '<button class="chip" data-f="' . esc_attr( $s ) . '">' . self::e( $s ) . '</button>';
			}
			$filters .= '</div>';
		}
		$right = $limit ? '<a class="btn b-line" href="' . esc_url( home_url( '/projects/' ) ) . '">All projects</a>' : '';
		$h     = '<section class="band tint"><div class="wrap">' . self::head( $p, $right ) . $filters . '<div class="proj">';
		foreach ( $items as $x ) {
			$h .= '<figure class="pj" data-sector="' . esc_attr( $x['sector'] ) . '"><img src="' . esc_url( $x['img'] ) . '" alt="' . esc_attr( $x['name'] . ' solar installation' ) . '" loading="lazy"><figcaption><span class="size">' . self::e( $x['size'] ) . '</span><h3>' . self::e( $x['name'] ) . '</h3><p>' . self::e( $x['type'] ) . ' · ' . self::e( $x['place'] ) . '</p></figcaption></figure>';
		}
		return $h . '</div></div></section>';
	}

	private static function r_steps( $p ) {
		$items = (array) ( isset( $p['items'] ) ? $p['items'] : array() );
		$h     = '<section class="band steps-band"><div class="wrap">' . self::head( $p ) . '<ol class="steps tl" style="--n:' . max( 1, count( $items ) ) . '"><span class="tl-line"><i></i></span>';
		foreach ( $items as $k => $i ) {
			$h .= '<li style="--i:' . (int) $k . '"><span class="dot">' . soko_icon( isset( $i['icon'] ) ? $i['icon'] : 'bulb', 30 ) . '<em>' . ( $k + 1 ) . '</em></span><h3>' . self::e( $i['title'] ) . '</h3><p>' . self::e( $i['text'] ) . '</p></li>';
		}
		return $h . '</ol></div></section>';
	}

	private static function r_logos( $p ) {
		$row = function ( $title, $items, $rev ) {
			if ( ! $items ) {
				return '';
			}
			$one = '';
			foreach ( $items as $i ) {
				$one .= ! empty( $i['logo'] ) ? '<span class="lg"><img src="' . esc_url( $i['logo'] ) . '" alt="' . esc_attr( $i['name'] ) . '"></span>' : '<span class="lg">' . self::e( $i['name'] ) . '</span>';
			}
			return '<div class="mq-row"><h3>' . self::e( $title ) . '</h3><div class="mq' . ( $rev ? ' rev' : '' ) . '"><div class="mq-track">' . $one . $one . '</div></div></div>';
		};
		return '<section class="band logos-band"><div class="wrap">' . $row( isset( $p['title'] ) ? $p['title'] : '', isset( $p['left'] ) ? (array) $p['left'] : array(), false ) . $row( isset( $p['title2'] ) ? $p['title2'] : '', isset( $p['right'] ) ? (array) $p['right'] : array(), true ) . '</div></section>';
	}

	private static function r_savings( $p ) {
		$cfg = array(
			'tariff'  => ! empty( $p['tariff'] ) ? (float) $p['tariff'] : 30,
			'kwCost'  => ! empty( $p['kw_cost'] ) ? (float) $p['kw_cost'] : 75000,
			'kwhCost' => ! empty( $p['kwh_cost'] ) ? (float) $p['kwh_cost'] : 28000,
		);
		$img = ! empty( $p['image'] ) ? '<img src="' . esc_url( $p['image'] ) . '" alt="" loading="lazy">' : '';
		return '<section class="band savings" data-savings="' . esc_attr( wp_json_encode( $cfg ) ) . '"><div class="wrap sv-grid"><div class="sv-in">' . self::head( $p )
			. '<div class="sv-mode" role="radiogroup" aria-label="Where will you use solar?"><button class="on" data-m="home" role="radio" aria-checked="true">Home</button><button data-m="biz" role="radio" aria-checked="false">Business or institution</button></div>'
			. '<label class="sv-lab" for="svBill">Your monthly electricity bill</label><div class="sv-bill"><b data-sv-bill>KES 8,000</b><span>a month</span></div>'
			. '<input type="range" id="svBill" min="2000" max="150000" step="500" value="8000" data-sv-range>'
			. '<div class="sv-ticks"><span>KES 2,000</span><span>KES 150,000</span></div>'
			. '<p class="fine">Estimates from typical Kenyan sun hours and tariffs. An engineer confirms the exact size and price on a site survey.</p></div>'
			. '<div class="sv-out"><div class="sv-pic">' . $img . '</div><div class="sv-card"><div class="sv-kpis"><div><small>Suggested system</small><b data-sv-size>3 kW</b><em data-sv-batt>5 kWh battery</em></div><div><small>You could save</small><b data-sv-save>KES 5,000</b><em>a month</em></div><div><small>Pays for itself in</small><b data-sv-pay>4 years</b><em>then the savings are yours</em></div></div>'
			. '<div class="sv-bars"><div><span>Bill today</span><i style="--w:100%"><b data-sv-now>KES 8,000</b></i></div><div><span>With solar</span><i class="g out" data-sv-barafter style="--w:40%"><b data-sv-after>KES 3,000</b></i></div></div>'
			. '<div class="row"><a class="btn b-sun" href="' . esc_url( home_url( '/shop/kits/' ) ) . '" data-sv-kit>See matching kits</a><a class="btn b-line" href="' . esc_url( home_url( '/planner/' ) ) . '">Size it appliance by appliance</a></div></div></div></div></section>';
	}

	private static function r_text_image( $p ) {
		$side = isset( $p['side'] ) && 'left' === $p['side'] ? ' left' : '';
		$imgs = '';
		if ( ! empty( $p['image'] ) ) {
			$imgs .= '<img src="' . esc_url( $p['image'] ) . '" alt="" loading="lazy">';
		}
		if ( ! empty( $p['image2'] ) ) {
			$imgs .= '<img src="' . esc_url( $p['image2'] ) . '" alt="" loading="lazy">';
		}
		static $ti_n = 0;
		$ti_n++;
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore
		$svc  = 'services' === $path;
		$kick = ! empty( $p['kicker'] ) ? $p['kicker'] : ( $svc ? 'Service focus' : 'Our story' );
		return '<section class="band"><div class="wrap ti' . $side . '"><div class="ttext sec-head"><div><span class="kick' . ( $svc ? ' numbered' : '' ) . '">' . ( $svc ? '<em class="tnum">' . sprintf( '%02d', $ti_n ) . '</em>' : '' ) . self::e( $kick ) . '</span><h2>' . self::e( $p['title'] ) . '</h2><div class="rich">' . wp_kses_post( wpautop( isset( $p['text'] ) ? $p['text'] : '' ) ) . '</div>' . ( ! empty( $p['cta_label'] ) ? '<div class="row">' . self::btn( $p['cta_label'], $p['cta_link'], 'b-blue' ) . '</div>' : '' ) . '</div></div><div class="timg' . ( ! empty( $p['image2'] ) ? ' two' : '' ) . '">' . $imgs . '</div></div></section>';
	}

	private static function r_columns( $p ) {
		$items = (array) ( isset( $p['items'] ) ? $p['items'] : array() );
		$h     = '<section class="band"><div class="wrap">' . self::head( $p ) . '<div class="cols c' . max( 2, min( 4, count( $items ) ) ) . '">';
		foreach ( $items as $i ) {
			$h .= '<div><h3>' . self::e( $i['title'] ) . '</h3><p>' . self::e( $i['text'] ) . '</p></div>';
		}
		return $h . '</div></div></section>';
	}

	private static function r_stats( $p ) {
		$items = (array) ( isset( $p['items'] ) ? $p['items'] : array() );
		if ( ! $items ) {
			return '';
		}
		$h = '<section class="stats"><div class="wrap">';
		foreach ( $items as $i ) {
			$h .= '<div><b>' . self::e( $i['value'] ) . '</b><span>' . self::e( $i['label'] ) . '</span></div>';
		}
		return $h . '</div></section>';
	}

	private static function r_gallery( $p ) {
		$h = '<section class="band"><div class="wrap">' . self::head( $p ) . '<div class="gal">';
		foreach ( (array) ( isset( $p['images'] ) ? $p['images'] : array() ) as $i ) {
			if ( empty( $i['image'] ) ) {
				continue;
			}
			$h .= '<figure><img src="' . esc_url( $i['image'] ) . '" alt="' . esc_attr( isset( $i['caption'] ) ? $i['caption'] : '' ) . '" loading="lazy">' . ( ! empty( $i['caption'] ) ? '<figcaption>' . self::e( $i['caption'] ) . '</figcaption>' : '' ) . '</figure>';
		}
		return $h . '</div></div></section>';
	}

	private static function r_testimonials( $p ) {
		$items = array_filter( (array) ( isset( $p['items'] ) ? $p['items'] : array() ), function ( $i ) { return ! empty( $i['quote'] ); } );
		if ( ! $items ) {
			return '';
		}
		$h = '<section class="band tint"><div class="wrap">' . self::head( $p ) . '<div class="quotes">';
		foreach ( $items as $i ) {
			$h .= '<blockquote><p>' . self::e( $i['quote'] ) . '</p><footer><b>' . self::e( $i['name'] ) . '</b>' . ( ! empty( $i['role'] ) ? ' · ' . self::e( $i['role'] ) : '' ) . '</footer></blockquote>';
		}
		return $h . '</div></div></section>';
	}

	private static function r_faq( $p ) {
		if ( empty( $p['title'] ) ) {
			$p['title'] = 'Common questions';
		}
		$h = '<section class="band"><div class="wrap narrow">' . self::head( $p ) . '<div class="faq">';
		foreach ( (array) ( isset( $p['items'] ) ? $p['items'] : array() ) as $i ) {
			$h .= '<details><summary>' . self::e( $i['q'] ) . '</summary><p>' . self::e( $i['a'] ) . '</p></details>';
		}
		return $h . '</div></div></section>';
	}

	private static function r_cta_band( $p ) {
		$img  = ! empty( $p['image'] ) ? $p['image'] : '';
		$form = ! empty( $p['callback'] ) ? '<form class="cb" data-lead-form data-source="Call-me-back request" novalidate><input class="hp" name="website" tabindex="-1" autocomplete="off"><div class="f"><input name="name" placeholder="Your name" autocomplete="name" required aria-label="Your name"></div><div class="f"><input name="phone" placeholder="Phone number" inputmode="tel" autocomplete="tel" required aria-label="Phone"></div><input type="hidden" name="interest" value="Call me back"><button class="btn b-sun" type="submit">Call me back</button><p class="formmsg" role="status"></p></form>' : '';
		return '<section class="cta band' . ( $img ? ' has-img' : '' ) . '"><div class="wrap"><div class="cta-copy hd"><span class="kick">' . self::e( ! empty( $p['kicker'] ) ? $p['kicker'] : 'Free consultation' ) . '</span><h2>' . self::e( $p['title'] ) . '</h2><p>' . self::e( $p['text'] ) . '</p>' . $form . '<div class="row">' . self::btn( $p['cta_label'], $p['cta_link'], $form ? 'b-line' : 'b-sun' ) . self::btn( isset( $p['cta2_label'] ) ? $p['cta2_label'] : '', isset( $p['cta2_link'] ) ? $p['cta2_link'] : '', 'b-line' ) . '</div></div>' . ( $img ? '<div class="cta-pic"><img src="' . esc_url( $img ) . '" alt="" loading="lazy"><span class="cta-badge"><b>EPRA</b>licensed engineers</span></div>' : '' ) . '</div></section>';
	}

	private static function r_contact( $p ) {
		$s   = Soko_Settings::all();
		$ico = function ( $d ) {
			return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' . $d . '"/></svg>';
		};
		$wa  = 'https://wa.me/' . soko_phone( $s['whatsapp'] );
		$map = ! empty( $p['map'] ) && false !== strpos( $p['map'], 'google.com/maps' ) ? '<iframe class="map" src="' . esc_url( $p['map'] ) . '" loading="lazy" title="Map"></iframe>' : '';
		return '<section class="band" id="quote"><div class="wrap contact">' . self::head( $p ) . '<div class="cinfo">'
			. '<div class="nexts"><h3>What happens next</h3><ol><li><b>We call you back</b><span>An engineer calls within working hours to understand what you want to power.</span></li><li><b>Site survey</b><span>We check your roof, wiring and usage, in person or by video call. The fee comes off your system price.</span></li><li><b>Fixed quote</b><span>You get an itemised quote with installation, valid for 14 days.</span></li></ol></div>'
			. '<div>' . $ico( 'M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12zM12 11.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5' ) . '<span><b>Address</b>' . self::e( $s['address'] ) . '</span></div>'
			. '<div>' . $ico( 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7v5l3 2' ) . '<span><b>Hours</b>' . self::e( $s['hours'] ) . '</span></div>' . $map
			. '</div>' . Soko_Store::quote_form() . '</div></section>';
	}

	private static function r_video( $p ) {
		if ( empty( $p['url'] ) || ! preg_match( '~(?:youtu\.be/|v=|embed/)([A-Za-z0-9_-]{11})~', $p['url'], $m ) ) {
			return '';
		}
		return '<section class="band"><div class="wrap">' . self::head( $p ) . '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $m[1] ) . '" title="' . esc_attr( isset( $p['title'] ) ? $p['title'] : 'Video' ) . '" allowfullscreen loading="lazy"></iframe></div></div></section>';
	}

	private static function r_rich_text( $p ) {
		return '<section class="band"><div class="wrap narrow">' . ( ! empty( $p['title'] ) ? '<h2>' . self::e( $p['title'] ) . '</h2>' : '' ) . '<div class="rich">' . wp_kses_post( wpautop( isset( $p['text'] ) ? $p['text'] : '' ) ) . '</div></div></section>';
	}
}
