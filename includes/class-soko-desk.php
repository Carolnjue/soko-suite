<?php
/**
 * Dynamic Desk: the front-end back office at /desk with its own sign-in.
 * Staff never need wp-admin.
 *
 * @package Soko_Suite
 */

defined( 'ABSPATH' ) || exit;

final class Soko_Desk {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'keep_out_of_admin' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_filter( 'authenticate', array( __CLASS__, 'block_disabled' ), 99 );
	}

	private static function only_desk( $u = null ) {
		$u = $u ? $u : wp_get_current_user();
		return $u && $u->exists() && ! user_can( $u, 'edit_posts' ) && soko_is_staff( $u );
	}

	public static function keep_out_of_admin() {
		if ( wp_doing_ajax() || ! self::only_desk() ) {
			return;
		}
		$page = isset( $_SERVER['PHP_SELF'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) ) : ''; // phpcs:ignore
		if ( in_array( $page, array( 'async-upload.php', 'media-upload.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( home_url( '/desk/' ) );
		exit;
	}

	public static function admin_bar( $show ) {
		return self::only_desk() ? false : $show;
	}

	public static function login_redirect( $to, $requested, $user ) {
		return ( $user instanceof WP_User && self::only_desk( $user ) ) ? home_url( '/desk/' ) : $to;
	}

	public static function block_disabled( $user ) {
		if ( $user instanceof WP_User && get_user_meta( $user->ID, 'soko_disabled', true ) ) {
			return new WP_Error( 'soko_disabled', 'This account has been switched off. Ask the owner to turn it back on.' );
		}
		return $user;
	}

	public static function admin_menu() {
		add_menu_page( 'Dynamic Desk', 'Dynamic Desk', 'read', 'soko-desk', function () {
			echo '<div class="wrap"><h1>Dynamic Desk</h1><p>The business desk runs on the front end so staff never need wp-admin.</p><p><a class="button button-primary button-hero" href="' . esc_url( home_url( '/desk/' ) ) . '">Open Dynamic Desk</a></p><p>Version ' . esc_html( SOKO_VERSION ) . '. Updates install automatically from GitHub.</p></div>';
		}, 'dashicons-store', 3 );
	}

	public static function render( $arg ) {
		if ( 'manifest' === $arg ) {
			self::manifest();
		}
		if ( 'sw' === $arg ) {
			self::service_worker();
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		if ( 'logout' === $arg ) {
			wp_logout();
			wp_safe_redirect( home_url( '/desk/login/' ) );
			exit;
		}
		if ( ! is_user_logged_in() || ! soko_is_staff() ) {
			self::login();
			return;
		}
		update_user_meta( get_current_user_id(), 'soko_last_seen', time() );
		self::shell();
	}

	/** Installable app: the Desk can be added to a phone home screen or installed on a computer. */
	private static function manifest() {
		$biz = soko_setting( 'business' );
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		echo wp_json_encode(
			array(
				'name'             => 'Dynamic Desk · ' . $biz,
				'short_name'       => 'Dynamic Desk',
				'description'      => 'Sales, payments, installations and the website for ' . $biz . '.',
				'start_url'        => home_url( '/desk/?app=1' ),
				'scope'            => home_url( '/desk/' ),
				'id'               => home_url( '/desk/' ),
				'display'          => 'standalone',
				'background_color' => '#f4f6fb',
				'theme_color'      => '#1d2156',
				'icons'            => array(
					array( 'src' => SOKO_URL . 'assets/app/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png' ),
					array( 'src' => SOKO_URL . 'assets/app/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png' ),
					array( 'src' => SOKO_URL . 'assets/app/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
				),
				'shortcuts'        => array(
					array( 'name' => 'New invoice', 'url' => home_url( '/desk/#sales' ) ),
					array( 'name' => 'Payments', 'url' => home_url( '/desk/#payments' ) ),
					array( 'name' => 'Schedule', 'url' => home_url( '/desk/#schedule' ) ),
				),
			),
			JSON_UNESCAPED_SLASHES
		);
		exit;
	}

	/**
	 * Service worker. The Desk always loads fresh from the website, so the installed app
	 * updates itself; files are kept only so it opens quickly and shows a notice when offline.
	 */
	private static function service_worker() {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		header( 'Service-Worker-Allowed: ' . wp_parse_url( home_url( '/desk/' ), PHP_URL_PATH ) );
		$v = SOKO_VERSION;
		echo "const V='desk-" . esc_js( $v ) . "';\n";
		echo <<<'JS'
self.addEventListener('install',e=>{self.skipWaiting();});
self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k!==V).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));});
self.addEventListener('fetch',e=>{
  const r=e.request; if(r.method!=='GET')return;
  const u=new URL(r.url);
  if(u.pathname.indexOf('/wp-json/')>-1)return;
  if(r.mode==='navigate'){
    e.respondWith(fetch(r).catch(()=>new Response('<!doctype html><meta name=viewport content="width=device-width,initial-scale=1"><body style="font:16px system-ui;display:grid;place-items:center;height:100vh;margin:0;background:#f4f6fb;color:#1d2156;text-align:center"><div><h2>You are offline</h2><p>Dynamic Desk needs the internet. It will reload when you are back online.</p></div><script>addEventListener("online",()=>location.reload())</script>',{headers:{'Content-Type':'text/html'}})));
    return;
  }
  if(/\.(css|js|png|jpg|jpeg|svg|woff2?)(\?|$)/.test(u.pathname+u.search)&&u.search.indexOf('v=')>-1){
    e.respondWith(caches.open(V).then(c=>c.match(r).then(hit=>hit||fetch(r).then(res=>{if(res.ok)c.put(r,res.clone());return res;}))));
  }
});
JS;
		exit;
	}

	private static function brand() {
		$site = Soko_Sections::site();
		return $site['brand'];
	}

	private static function login() {
		$error = '';
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['soko_login'] ) ) { // phpcs:ignore
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['soko_login'] ) ), 'soko_login' ) ) { // phpcs:ignore
				$error = 'The page expired. Please try again.';
			} else {
				$user = wp_signon(
					array(
						'user_login'    => sanitize_text_field( wp_unslash( isset( $_POST['log'] ) ? $_POST['log'] : '' ) ), // phpcs:ignore
						'user_password' => isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : '', // phpcs:ignore
						'remember'      => ! empty( $_POST['rememberme'] ), // phpcs:ignore
					),
					is_ssl()
				);
				if ( is_wp_error( $user ) ) {
					$error = 'soko_disabled' === $user->get_error_code() ? $user->get_error_message() : 'That email or password is not right.';
				} elseif ( ! soko_is_staff( $user ) ) {
					wp_logout();
					$error = 'This account does not have Desk access.';
				} else {
					wp_safe_redirect( home_url( '/desk/' ) );
					exit;
				}
			}
		} elseif ( is_user_logged_in() && ! soko_is_staff() ) {
			$error = 'You are signed in with an account that does not have Desk access.';
		}
		$b    = self::brand();
		$s    = Soko_Settings::all();
		$logo = $b['logo'] ? $b['logo'] : SOKO_URL . 'assets/img/dss-logo.png';
		?><!DOCTYPE html><html lang="en-GB"><head><meta charset="utf-8">
<script>try{if(localStorage.getItem('soko_desk_theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · Dynamic Desk</title><meta name="robots" content="noindex"><link rel="manifest" href="<?php echo esc_url( home_url( '/desk/manifest.webmanifest' ) ); ?>"><link rel="icon" type="image/png" href="<?php echo esc_url( SOKO_URL . 'assets/app/icon-192.png' ); ?>"><meta name="theme-color" content="#1d2156"><link rel="apple-touch-icon" href="<?php echo esc_url( SOKO_URL . 'assets/app/apple-touch.png' ); ?>"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Dynamic Desk">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);font:16px/1.5 Inter,system-ui,sans-serif;color:#0f1d36;background:#fff}
.art{background:<?php echo esc_html( $b['deep'] ); ?>;color:#fff;padding:48px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
.art h1{font:700 clamp(34px,4vw,52px)/1.05 Outfit,sans-serif;letter-spacing:-.02em;margin:0;max-width:12ch}.art p{color:#c9d7f2;max-width:40ch;font-size:18px}
.art svg{position:absolute;right:-60px;bottom:-40px;width:70%;opacity:.95}
.brand{display:flex;align-items:center;gap:12px;font:700 20px Outfit;position:relative}.brand img{height:44px;background:#fff;border-radius:10px;padding:4px 8px}
form{align-self:center;justify-self:center;width:min(400px,calc(100% - 32px));padding:24px 0}
h2{font:700 30px Outfit;letter-spacing:-.02em;margin:0 0 6px}.sub{color:#5a6780;margin:0 0 24px}
label{display:block;font-weight:600;font-size:14px;margin:14px 0 6px}input[type=text],input[type=password],input[type=email]{width:100%;height:48px;border:1px solid #d6deea;border-radius:12px;padding:0 14px;font:16px Inter}
input:focus{outline:3px solid <?php echo esc_html( $b['accent'] ); ?>;outline-offset:1px;border-color:transparent}
.rem{display:flex;gap:8px;align-items:center;font-weight:500;margin:16px 0}
button{width:100%;height:50px;border:0;border-radius:12px;background:<?php echo esc_html( $b['primary'] ); ?>;color:#fff;font:600 16px Inter;cursor:pointer}button:hover{filter:brightness(1.08)}
.err{background:#fde9e7;color:#a12b22;padding:12px 14px;border-radius:12px;margin-bottom:8px}.links{display:flex;justify-content:space-between;margin-top:18px;font-size:14px}.links a{color:<?php echo esc_html( $b['primary'] ); ?>}
@media(max-width:800px){body{grid-template-columns:1fr}.art{min-height:220px;padding:28px}.art svg{display:none}.art p{display:none}}
</style></head><body>
<div class="art"><div class="brand"><img src="<?php echo esc_url( $logo ); ?>" alt=""><span>Dynamic Desk</span></div>
<div><h1>Your whole business, one desk.</h1><p>Sales, payments, invoices, stock, installations and reports for <?php echo esc_html( $s['business'] ); ?>.</p></div>
<svg viewBox="0 0 400 300" aria-hidden="true"><circle cx="300" cy="70" r="46" fill="<?php echo esc_attr( $b['accent'] ); ?>"/><circle cx="300" cy="70" r="70" fill="<?php echo esc_attr( $b['accent'] ); ?>" opacity=".18"/><g transform="translate(60 150) skewX(-24)"><?php for ( $r = 0; $r < 3; $r++ ) { for ( $c = 0; $c < 5; $c++ ) { echo '<rect x="' . ( $c * 52 ) . '" y="' . ( $r * 38 ) . '" width="48" height="34" fill="#2f6fd0" stroke="#9cc3ff" stroke-width="1.5"/>'; } } ?></g></svg>
</div>
<form method="post" action="<?php echo esc_url( home_url( '/desk/login/' ) ); ?>">
<h2>Sign in</h2><p class="sub">Use the email and password your manager gave you.</p>
<?php if ( $error ) : ?><div class="err" role="alert"><?php echo esc_html( $error ); ?></div><?php endif; ?>
<label for="log">Email or username</label><input type="text" id="log" name="log" autocomplete="username" required autofocus>
<label for="pwd">Password</label><input type="password" id="pwd" name="pwd" autocomplete="current-password" required>
<label class="rem"><input type="checkbox" name="rememberme" value="1" checked> Keep me signed in on this device</label>
<?php wp_nonce_field( 'soko_login', 'soko_login' ); ?>
<button type="submit">Sign in</button>
<div class="links"><a href="<?php echo esc_url( wp_lostpassword_url( home_url( '/desk/' ) ) ); ?>">Forgot your password?</a><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to the website</a></div>
</form></body></html>
		<?php
	}

	private static function shell() {
		$b = self::brand();
		$css = SOKO_URL . 'assets/desk.css?v=' . SOKO_VERSION;
		?><!DOCTYPE html><html lang="en-GB"><head><meta charset="utf-8">
<script>try{if(localStorage.getItem('soko_desk_theme')==='dark')document.documentElement.setAttribute('data-theme','dark')}catch(e){}</script><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dynamic Desk</title><meta name="robots" content="noindex"><link rel="manifest" href="<?php echo esc_url( home_url( '/desk/manifest.webmanifest' ) ); ?>"><link rel="icon" type="image/png" href="<?php echo esc_url( SOKO_URL . 'assets/app/icon-192.png' ); ?>"><meta name="theme-color" content="#1d2156"><link rel="apple-touch-icon" href="<?php echo esc_url( SOKO_URL . 'assets/app/apple-touch.png' ); ?>"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Dynamic Desk"><meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
<style>:root{--brand:<?php echo esc_html( $b['primary'] ); ?>;--side:<?php echo esc_html( $b['deep'] ); ?>;--sun:<?php echo esc_html( $b['accent'] ); ?>}</style>
</head><body>
<div id="app" class="app"><div class="boot"><div class="spin"></div>Opening Dynamic Desk…</div></div>
<script>window.DESK=<?php echo wp_json_encode( array( 'api' => esc_url_raw( rest_url( 'soko/v1/' ) ), 'wpapi' => esc_url_raw( rest_url( 'wp/v2/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'vat_incl' => (int) soko_setting( 'prices_incl_vat', 1 ), 'site' => home_url( '/' ), 'logout' => home_url( '/desk/logout/' ), 'logo' => $b['logo'], 'plugin' => SOKO_URL, 'icons' => array_combine( soko_icon_names(), array_map( function ( $k ) { return soko_icon( $k, 24 ); }, soko_icon_names() ) ), 'schema' => Soko_Sections::schema() ) ); ?>;</script>
<?php foreach ( array( 'desk-core', 'desk-modules', 'desk-studio', 'desk-accounts' ) as $f ) : ?><script src="<?php echo esc_url( SOKO_URL . 'assets/' . $f . '.js?v=' . SOKO_VERSION ); ?>"></script><?php endforeach; ?>
</body></html>
		<?php
	}
}
