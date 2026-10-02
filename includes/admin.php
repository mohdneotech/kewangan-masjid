<?php
defined( 'ABSPATH' ) || exit;

/* Per-user access level on the profile screen (site administrators only). */
function pkw_profile_field( $user ) {
	if ( ! current_user_can( 'manage_options' ) || user_can( $user, 'manage_options' ) ) { return; }
	$lvl = $user->has_cap( PKW_CAP_URUS ) ? 'urus' : ( $user->has_cap( PKW_CAP_LIHAT ) ? 'lihat' : '' );
	$viaRole = in_array( 'pkw_bendahari', (array) $user->roles, true );
	echo '<h2>Laporan Kewangan</h2><table class="form-table"><tr><th>Akses</th><td>';
	wp_nonce_field( 'pkw_profile', 'pkw_profile_nonce' );
	foreach ( [ '' => 'Tiada akses', 'lihat' => 'Lihat sahaja (laporan, penyata, eksport)', 'urus' => 'Bendahari — rekod transaksi, tetapan, tutup bulan' ] as $k => $l ) {
		echo '<label style="display:block"><input type="radio" name="pkw_akses" value="' . esc_attr( $k ) . '"' . checked( $lvl, $k, false ) . ( $viaRole ? ' disabled' : '' ) . '> ' . esc_html( $l ) . '</label>';
	}
	if ( $viaRole ) { echo '<p class="description">Pengguna ini mempunyai peranan <strong>Bendahari</strong>; tukar peranan untuk mengubah akses.</p>'; }
	echo '<p class="description">Halaman: <a href="' . esc_url( pkw_page_url() ) . '" target="_blank">' . esc_html( pkw_page_url() ) . '</a></p></td></tr></table>';
}
add_action( 'edit_user_profile', 'pkw_profile_field' );

add_action( 'edit_user_profile_update', function ( $uid ) {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['pkw_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['pkw_profile_nonce'] ), 'pkw_profile' ) ) { return; }
	$u = get_userdata( $uid );
	if ( ! $u || user_can( $u, 'manage_options' ) || in_array( 'pkw_bendahari', (array) $u->roles, true ) || ! isset( $_POST['pkw_akses'] ) ) { return; }
	$lvl = sanitize_key( $_POST['pkw_akses'] );
	$before = $u->has_cap( PKW_CAP_URUS ) ? 'urus' : ( $u->has_cap( PKW_CAP_LIHAT ) ? 'lihat' : '' );
	$u->remove_cap( PKW_CAP_URUS ); $u->remove_cap( PKW_CAP_LIHAT );
	if ( 'lihat' === $lvl ) { $u->add_cap( PKW_CAP_LIHAT ); }
	if ( 'urus' === $lvl ) { $u->add_cap( PKW_CAP_LIHAT ); $u->add_cap( PKW_CAP_URUS ); }
	if ( $before !== $lvl ) { pkw_log( 0, 'akses', $u->user_login . ': ' . ( $before ?: 'tiada' ) . ' → ' . ( $lvl ?: 'tiada' ) ); }
} );

/* Shortcut in wp-admin for anyone with access. */
add_action( 'admin_menu', function () {
	if ( ! pkw_can_view() ) { return; }
	add_menu_page( 'Laporan Kewangan', 'Laporan Kewangan', 'read', 'pkw-pergi', function () {
		echo '<div class="wrap"><h1>Laporan Kewangan</h1><p><a class="button button-primary" href="' . esc_url( pkw_page_url() ) . '">Buka Laporan Kewangan →</a></p></div>';
	}, 'dashicons-money-alt', 27 );
} );
add_action( 'load-toplevel_page_pkw-pergi', function () { if ( pkw_can_view() ) { wp_safe_redirect( pkw_page_url() ); exit; } } );

/* Recreate the report page if it was deleted; one-time welcome notice after activation. */
add_action( 'admin_init', function () {
	if ( current_user_can( 'manage_options' ) ) { pkw_ensure_page(); }
} );
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'pkw_activated' ) ) { return; }
	delete_transient( 'pkw_activated' );
	echo '<div class="notice notice-success is-dismissible"><p><strong>Kewangan Masjid diaktifkan.</strong> Halaman dalaman telah dicipta: <a href="' . esc_url( pkw_page_url() ) . '">' . esc_html( pkw_page_url() ) . '</a>. Langkah seterusnya: <a href="' . esc_url( pkw_page_url( [ 'tab' => 'tetapan' ] ) ) . '">isi nama masjid, akaun bank &amp; baki awal</a>, kemudian beri peranan <strong>Bendahari</strong> kepada pengguna yang berkenaan (Pengguna → Sunting).</p></div>';
} );
