<?php
/**
 * Plugin Name: Kewangan Masjid — Lejar & Penyata Kewangan
 * Plugin URI: https://github.com/mohdneotech/kewangan-masjid
 * Description: Lejar penerimaan & pembayaran masjid/surau (tabung Jumaat, infaq, bil, elaun, program…) dengan Penyata Penerimaan & Pembayaran bulanan/tahunan, baki akaun, tutup bulan dan jejak audit — di halaman dalaman untuk Bendahari & AJK yang diberi kebenaran. Boleh digabung dengan plugin Kariah & Khairat Masjid.
 * Version: 1.0.1
 * Author: Mohd Nordin Hussain
 * Author URI: https://mohdneotech.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: kewangan-masjid
 */

defined( 'ABSPATH' ) || exit;

// Same internals as the original site-specific build: refuse to load twice.
if ( defined( 'PKW_VER' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p><strong>Kewangan Masjid</strong> tidak dimuatkan kerana versi lain plugin kewangan (cth. "Perepat Kewangan") masih aktif. Nyahaktifkan plugin lama dahulu — data sedia ada akan digunakan semula.</p></div>';
	} );
	return;
}

define( 'PKW_VER', '1.0.1' );
define( 'PKW_DB_VER', '1' );
define( 'PKW_FILE', __FILE__ );
define( 'PKW_DIR', plugin_dir_path( __FILE__ ) );
define( 'PKW_URL', plugin_dir_url( __FILE__ ) );
define( 'PKW_REPO', 'mohdneotech/kewangan-masjid' );
define( 'PKW_CAP_URUS', 'pkw_urus' );  // record / edit transactions, settings, close months
define( 'PKW_CAP_LIHAT', 'pkw_lihat' ); // view reports only

require_once PKW_DIR . 'includes/helpers.php';
require_once PKW_DIR . 'includes/db.php';
require_once PKW_DIR . 'includes/actions.php';
require_once PKW_DIR . 'includes/front.php';
require_once PKW_DIR . 'includes/class-github-updater.php';
if ( is_admin() ) { require_once PKW_DIR . 'includes/admin.php'; }
if ( is_admin() || wp_doing_cron() ) { new PKW_GitHub_Updater( __FILE__, PKW_REPO, PKW_VER ); }

register_activation_hook( __FILE__, 'pkw_activate' );
function pkw_activate() {
	if ( is_plugin_active( 'perepat-kewangan/perepat-kewangan.php' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'Sila nyahaktifkan plugin "Perepat Kewangan" dahulu. Kewangan Masjid akan menggunakan data yang sama.', 'Kewangan Masjid', [ 'back_link' => true ] );
	}
	pkw_install_schema();
	pkw_install_roles();
	pkw_seed();
	pkw_ensure_page();
	pkw_private_dir();
	set_transient( 'pkw_activated', 1, 300 );
}

add_action( 'plugins_loaded', function () {
	if ( get_option( 'pkw_db_ver' ) !== PKW_DB_VER ) { pkw_install_schema(); pkw_install_roles(); pkw_seed(); }
} );

function pkw_install_roles() {
	if ( $r = get_role( 'administrator' ) ) { $r->add_cap( PKW_CAP_URUS ); $r->add_cap( PKW_CAP_LIHAT ); }
	if ( ! get_role( 'pkw_bendahari' ) ) {
		add_role( 'pkw_bendahari', 'Bendahari', [ 'read' => true, PKW_CAP_URUS => true, PKW_CAP_LIHAT => true ] );
	}
}

function pkw_can_view() { return is_user_logged_in() && ( current_user_can( PKW_CAP_LIHAT ) || current_user_can( PKW_CAP_URUS ) ); }
function pkw_can_edit() { return is_user_logged_in() && current_user_can( PKW_CAP_URUS ); }

/* Bendahari-only accounts land on the report page, not the wp-admin profile. */
add_filter( 'login_redirect', function ( $to, $req, $user ) {
	if ( $user instanceof WP_User && [ 'pkw_bendahari' ] === array_values( (array) $user->roles ) && ( ! $req || false !== strpos( $req, 'wp-admin' ) ) ) {
		return pkw_page_url();
	}
	return $to;
}, 20, 3 );

/* Admin-bar shortcut. */
add_action( 'admin_bar_menu', function ( $bar ) {
	if ( pkw_can_view() ) { $bar->add_node( [ 'id' => 'pkw', 'title' => 'Laporan Kewangan', 'href' => pkw_page_url() ] ); }
}, 80 );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $l ) {
	array_unshift( $l, '<a href="' . esc_url( pkw_page_url( [ 'tab' => 'tetapan' ] ) ) . '">Tetapan</a>' );
	return $l;
} );
