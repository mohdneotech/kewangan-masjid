<?php
/**
 * Runs when the plugin is DELETED from wp-admin (not on deactivate).
 * Financial records are only removed if "Padam semua rekod kewangan…" was ticked in Tetapan.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$s = get_option( 'pkw_settings' );
delete_site_transient( 'pkw_gh_release' );
delete_transient( 'pkw_activated' );

if ( empty( $s['padam_data'] ) ) {
	return; // keep everything — reinstalling picks up where it left off
}
foreach ( [ 'transaksi', 'kategori', 'akaun', 'tutup', 'log' ] as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pkw_' . $t ); // phpcs:ignore WordPress.DB.PreparedSQL
}
$page = (int) get_option( 'pkw_page_id' );
if ( $page ) { wp_delete_post( $page, true ); }
foreach ( [ 'pkw_settings', 'pkw_page_id', 'pkw_db_ver' ] as $o ) { delete_option( $o ); }
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_pkw\_%' OR option_name LIKE '\_transient\_timeout\_pkw\_%'" );

// Supporting documents.
$up  = wp_upload_dir();
$dir = trailingslashit( $up['basedir'] ) . 'pk-private/kewangan';
if ( is_dir( $dir ) ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) { $f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); }
	@rmdir( $dir );
}

// Role and capabilities.
remove_role( 'pkw_bendahari' );
foreach ( get_users( [ 'capability__in' => [ 'pkw_urus', 'pkw_lihat' ], 'fields' => 'all' ] ) as $u ) { $u->remove_cap( 'pkw_urus' ); $u->remove_cap( 'pkw_lihat' ); }
if ( $r = get_role( 'administrator' ) ) { $r->remove_cap( 'pkw_urus' ); $r->remove_cap( 'pkw_lihat' ); }
