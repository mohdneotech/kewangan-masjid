<?php
defined( 'ABSPATH' ) || exit;

function pkw_t( $n ) { global $wpdb; return $wpdb->prefix . 'pkw_' . $n; }
function pkw_rm( $n ) { $n = (float) $n; return ( $n < 0 ? '-' : '' ) . 'RM ' . number_format( abs( $n ), 2 ); }
function pkw_money( $v ) { return round( max( 0, (float) str_replace( [ ',', 'RM', 'rm', ' ' ], '', (string) $v ) ), 2 ); }
function pkw_today() { return current_time( 'Y-m-d' ); }
function pkw_now() { return current_time( 'mysql' ); }
function pkw_date_in( $v ) {
	$v = trim( (string) $v );
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) { return ''; }
	return $v;
}
function pkw_ym_in( $v, $def = '' ) { return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $v ) ? $v : $def; }
function pkw_text( $v, $max = 190 ) { return mb_substr( sanitize_text_field( wp_unslash( (string) $v ) ), 0, $max ); }

const PKW_BULAN = [ 1 => 'Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember' ];
const PKW_CARA  = [ 'tunai' => 'Tunai', 'pindahan' => 'Pindahan / DuitNow', 'cek' => 'Cek', 'kad' => 'Kad', 'lain' => 'Lain-lain' ];

function pkw_ym_label( $ym ) { [ $y, $m ] = array_map( 'intval', explode( '-', $ym ) ); return PKW_BULAN[ $m ] . ' ' . $y; }
function pkw_date_label( $d ) { return $d ? date_i18n( 'd/m/Y', strtotime( $d ) ) : '—'; }

function pkw_settings() {
	return wp_parse_args( (array) get_option( 'pkw_settings', [] ), [
		'kariah_sync'  => 1,   // include verified khairat/korban payments from the Kariah & Khairat Masjid plugin
		'kariah_akaun' => 0,   // account those payments are banked into
		'nama_masjid'  => get_bloginfo( 'name' ),
		'fontawesome'  => 'auto', // auto | cdn | off
		'padam_data'   => 0,      // drop tables on plugin delete
	] );
}

/* ------------------------------------------------------------- private files (inherits uploads/pk-private block) */

function pkw_private_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'pk-private';
	if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
	if ( ! file_exists( "$dir/.htaccess" ) ) { @file_put_contents( "$dir/.htaccess", "Require all denied\nDeny from all\n" ); }
	if ( ! file_exists( "$dir/index.php" ) ) { @file_put_contents( "$dir/index.php", "<?php // silence\n" ); }
	$k = "$dir/kewangan";
	if ( ! is_dir( $k ) ) { wp_mkdir_p( $k ); @file_put_contents( "$k/index.php", "<?php // silence\n" ); }
	return $k;
}

/** @return string|WP_Error|null */
function pkw_store_upload( $field ) {
	if ( empty( $_FILES[ $field ] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES[ $field ]['error'] ) { return null; }
	$f = $_FILES[ $field ];
	if ( UPLOAD_ERR_OK !== (int) $f['error'] ) { return new WP_Error( 'upload', 'Muat naik fail gagal.' ); }
	if ( $f['size'] > 10 * MB_IN_BYTES ) { return new WP_Error( 'upload', 'Saiz fail melebihi 10 MB.' ); }
	$allowed = [ 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf' ];
	$chk     = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], $allowed );
	if ( empty( $chk['ext'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) { return new WP_Error( 'upload', 'Jenis fail tidak dibenarkan (JPG/PNG/WEBP/PDF sahaja).' ); }
	$head = (string) @file_get_contents( $f['tmp_name'], false, null, 0, 1024 );
	if ( 'pdf' === $chk['ext'] ) {
		if ( 0 !== strpos( ltrim( $head ), '%PDF-' ) ) { return new WP_Error( 'upload', 'Fail PDF tidak sah.' ); }
	} elseif ( false === @getimagesize( $f['tmp_name'] ) ) {
		return new WP_Error( 'upload', 'Fail gambar tidak sah.' );
	}
	if ( preg_match( '/<\?php|<script|<html|<svg/i', $head ) ) { return new WP_Error( 'upload', 'Fail tidak dibenarkan.' ); }
	$sub = gmdate( 'Y/m' );
	$dir = pkw_private_dir() . '/' . $sub;
	wp_mkdir_p( $dir );
	$name = gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 10, false ) . '.' . $chk['ext'];
	if ( ! @move_uploaded_file( $f['tmp_name'], "$dir/$name" ) ) { return new WP_Error( 'upload', 'Fail tidak dapat disimpan.' ); }
	@chmod( "$dir/$name", 0640 );
	return "$sub/$name";
}

function pkw_stream_private( $rel ) {
	if ( ! is_string( $rel ) || ! preg_match( '#^\d{4}/\d{2}/[A-Za-z0-9\-]+\.(jpe?g|png|webp|pdf)$#', $rel ) ) { wp_die( 'Fail tidak sah.', 400 ); }
	$base = realpath( pkw_private_dir() );
	$path = realpath( $base . '/' . $rel );
	if ( ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) { wp_die( 'Fail tidak ditemui.', 404 ); }
	$ft = wp_check_filetype( $path );
	nocache_headers();
	header( 'Content-Type: ' . ( $ft['type'] ?: 'application/octet-stream' ) );
	header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Cross-Origin-Resource-Policy: same-origin' );
	if ( 'application/pdf' !== $ft['type'] ) { header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox" ); }
	readfile( $path );
	exit;
}

function pkw_file_url( $rel ) {
	return $rel ? wp_nonce_url( admin_url( 'admin-post.php?action=pkw_fail&f=' . rawurlencode( $rel ) ), 'pkw_fail' ) : '';
}

/* ------------------------------------------------------------- flash messages (no reflected text) */

function pkw_flash( $msg, $type = 'ok' ) {
	$tok = wp_generate_password( 12, false );
	set_transient( 'pkw_flash_' . get_current_user_id() . '_' . $tok, [ $msg, $type ], 120 );
	return $tok;
}
function pkw_flash_take( $tok ) {
	if ( ! $tok || ! preg_match( '/^[A-Za-z0-9]{12}$/', $tok ) ) { return null; }
	$k = 'pkw_flash_' . get_current_user_id() . '_' . $tok;
	$v = get_transient( $k );
	delete_transient( $k );
	return $v ?: null;
}

function pkw_csv_cell( $v ) {
	if ( ! is_string( $v ) || '' === $v || is_numeric( $v ) ) { return $v; }
	return preg_match( '/^[\s\x{00A0}]*[=+\-@\t\r|%\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]/u', $v ) ? "'" . $v : $v;
}
function pkw_csv_out( $name, array $head, iterable $rows ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '.csv"' );
	$o = fopen( 'php://output', 'w' );
	fwrite( $o, "\xEF\xBB\xBF" );
	fputcsv( $o, $head );
	foreach ( $rows as $r ) { fputcsv( $o, array_map( 'pkw_csv_cell', array_values( $r ) ) ); }
	fclose( $o );
	exit;
}
