<?php
defined( 'ABSPATH' ) || exit;

function pkw_back( array $args, $msg = '', $type = 'ok' ) {
	if ( $msg ) { $args['pkw_m'] = pkw_flash( $msg, $type ); }
	wp_safe_redirect( pkw_page_url( $args ) );
	exit;
}
function pkw_guard( $nonce, $edit = true ) {
	if ( ! ( $edit ? pkw_can_edit() : pkw_can_view() ) ) { wp_die( 'Akses ditolak.', 403 ); }
	check_admin_referer( $nonce );
}

/* save (add/edit) */
add_action( 'admin_post_pkw_simpan', function () {
	pkw_guard( 'pkw_simpan' );
	$id = absint( $_POST['id'] ?? 0 );
	$d  = [
		'jenis' => sanitize_key( $_POST['jenis'] ?? '' ), 'tarikh' => sanitize_text_field( wp_unslash( $_POST['tarikh'] ?? '' ) ),
		'kategori_id' => absint( $_POST['kategori_id'] ?? 0 ), 'akaun_id' => absint( $_POST['akaun_id'] ?? 0 ), 'akaun_ke' => absint( $_POST['akaun_ke'] ?? 0 ),
		'jumlah' => sanitize_text_field( wp_unslash( $_POST['jumlah'] ?? '' ) ), 'cara' => sanitize_key( $_POST['cara'] ?? '' ),
		'rujukan' => pkw_text( $_POST['rujukan'] ?? '', 80 ), 'pihak' => pkw_text( $_POST['pihak'] ?? '', 190 ),
		'butiran' => mb_substr( sanitize_textarea_field( wp_unslash( $_POST['butiran'] ?? '' ) ), 0, 1000 ),
	];
	$back = [ 'tab' => 'transaksi' ];
	$f = pkw_store_upload( 'bukti' );
	if ( is_wp_error( $f ) ) { pkw_back( $back + ( $id ? [ 'ubah' => $id ] : [ 'baru' => 1 ] ), $f->get_error_message(), 'err' ); }
	if ( $f ) { $d['bukti'] = $f; }
	$r = pkw_tx_save( $d, $id );
	if ( is_wp_error( $r ) ) { pkw_back( $back + ( $id ? [ 'ubah' => $id ] : [ 'baru' => 1 ] ), $r->get_error_message(), 'err' ); }
	if ( ! $id && ! empty( $_POST['lagi'] ) ) { pkw_back( [ 'tab' => 'transaksi', 'baru' => 1, 'j' => $d['jenis'] ], 'Transaksi #' . $r . ' disimpan. Masukkan transaksi seterusnya.' ); }
	pkw_back( $back, $id ? 'Transaksi #' . $r . ' dikemas kini.' : 'Transaksi #' . $r . ' disimpan.' );
} );

/* void */
add_action( 'admin_post_pkw_batal', function () {
	pkw_guard( 'pkw_batal' );
	$r = pkw_tx_batal( absint( $_POST['id'] ?? 0 ), sanitize_text_field( wp_unslash( $_POST['sebab'] ?? '' ) ) );
	pkw_back( [ 'tab' => 'transaksi' ], is_wp_error( $r ) ? $r->get_error_message() : 'Transaksi dibatalkan (direkod dalam jejak audit).', is_wp_error( $r ) ? 'err' : 'ok' );
} );

/* settings: categories, accounts, kariah link */
add_action( 'admin_post_pkw_tetapan', function () {
	global $wpdb;
	pkw_guard( 'pkw_tetapan' );
	$op = sanitize_key( $_POST['op'] ?? '' );
	$back = [ 'tab' => 'tetapan' ];
	switch ( $op ) {
		case 'kat_tambah':
			$j = in_array( $_POST['jenis'] ?? '', [ 'masuk', 'keluar' ], true ) ? $_POST['jenis'] : '';
			$n = pkw_text( $_POST['nama'] ?? '', 120 );
			if ( ! $j || mb_strlen( $n ) < 2 ) { pkw_back( $back, 'Nama kategori diperlukan.', 'err' ); }
			$wpdb->insert( pkw_t( 'kategori' ), [ 'jenis' => $j, 'nama' => $n, 'susunan' => 100 ] );
			pkw_log( 0, 'kategori', "tambah $j: $n" );
			pkw_back( $back, 'Kategori ditambah.' );
		case 'kat_simpan':
			foreach ( (array) ( $_POST['kat'] ?? [] ) as $kid => $k ) {
				$n = pkw_text( $k['nama'] ?? '', 120 );
				if ( mb_strlen( $n ) < 2 ) { continue; }
				$wpdb->update( pkw_t( 'kategori' ), [ 'nama' => $n, 'susunan' => (int) ( $k['susunan'] ?? 0 ), 'aktif' => empty( $k['aktif'] ) ? 0 : 1 ], [ 'id' => absint( $kid ) ] );
			}
			pkw_log( 0, 'kategori', 'kemas kini senarai kategori' );
			pkw_back( $back, 'Kategori dikemas kini.' );
		case 'akaun_simpan':
			$aid  = absint( $_POST['akaun_id'] ?? 0 );
			$row  = [ 'nama' => pkw_text( $_POST['nama'] ?? '', 120 ), 'butiran' => pkw_text( $_POST['butiran'] ?? '', 190 ), 'baki_awal' => round( (float) str_replace( [ ',', 'RM', ' ' ], '', (string) wp_unslash( $_POST['baki_awal'] ?? '0' ) ), 2 ), 'tarikh_mula' => pkw_date_in( $_POST['tarikh_mula'] ?? '' ), 'aktif' => empty( $_POST['aktif'] ) && $aid ? 0 : 1 ];
			if ( mb_strlen( $row['nama'] ) < 2 || ! $row['tarikh_mula'] ) { pkw_back( $back, 'Nama akaun dan tarikh baki awal diperlukan.', 'err' ); }
			if ( $aid ) {
				$old = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . pkw_t( 'akaun' ) . ' WHERE id=%d', $aid ), ARRAY_A );
				if ( ! $old ) { pkw_back( $back, 'Akaun tidak ditemui.', 'err' ); }
				$touch = ( (float) $old['baki_awal'] !== (float) $row['baki_awal'] || $old['tarikh_mula'] !== $row['tarikh_mula'] );
				if ( $touch && ( pkw_bulan_ditutup( $old['tarikh_mula'] ) || pkw_bulan_ditutup( $row['tarikh_mula'] ) ) ) { pkw_back( $back, 'Baki awal tidak boleh diubah kerana bulan tersebut telah ditutup.', 'err' ); }
				$wpdb->update( pkw_t( 'akaun' ), $row, [ 'id' => $aid ] );
				pkw_log( 0, 'akaun', "ubah #$aid {$row['nama']}: baki awal {$old['baki_awal']}@{$old['tarikh_mula']} → {$row['baki_awal']}@{$row['tarikh_mula']}" );
			} else {
				$wpdb->insert( pkw_t( 'akaun' ), $row );
				pkw_log( 0, 'akaun', "tambah {$row['nama']} baki awal {$row['baki_awal']}@{$row['tarikh_mula']}" );
			}
			pkw_back( $back, 'Akaun disimpan.' );
		case 'umum':
			$s = pkw_settings();
			$n = pkw_text( $_POST['nama_masjid'] ?? '', 150 );
			if ( mb_strlen( $n ) >= 2 ) { $s['nama_masjid'] = $n; }
			$s['fontawesome'] = in_array( $_POST['fontawesome'] ?? '', [ 'auto', 'cdn', 'off' ], true ) ? $_POST['fontawesome'] : 'auto';
			if ( current_user_can( 'manage_options' ) ) { $s['padam_data'] = empty( $_POST['padam_data'] ) ? 0 : 1; }
			update_option( 'pkw_settings', $s, false );
			pkw_log( 0, 'tetapan', 'umum: ' . $s['nama_masjid'] . ', fa=' . $s['fontawesome'] . ', padam_data=' . $s['padam_data'] );
			pkw_back( $back, 'Tetapan disimpan.' );
		case 'kariah':
			$s = pkw_settings();
			$s['kariah_sync']  = empty( $_POST['kariah_sync'] ) ? 0 : 1;
			$s['kariah_akaun'] = absint( $_POST['kariah_akaun'] ?? 0 );
			update_option( 'pkw_settings', $s, false );
			pkw_log( 0, 'tetapan', 'kariah_sync=' . $s['kariah_sync'] . ' akaun=' . $s['kariah_akaun'] );
			pkw_back( $back, 'Tetapan disimpan.' );
	}
	pkw_back( $back );
} );

/* close / reopen month */
add_action( 'admin_post_pkw_tutup', function () {
	global $wpdb;
	pkw_guard( 'pkw_tutup' );
	$ym = pkw_ym_in( sanitize_text_field( wp_unslash( $_POST['bulan'] ?? '' ) ) );
	$back = [ 'tab' => 'tetapan' ];
	if ( ! $ym ) { pkw_back( $back, 'Bulan tidak sah.', 'err' ); }
	if ( 'buka' === ( $_POST['op'] ?? '' ) ) {
		if ( ! current_user_can( 'manage_options' ) ) { pkw_back( $back, 'Hanya pentadbir laman boleh membuka semula bulan yang ditutup.', 'err' ); }
		$wpdb->delete( pkw_t( 'tutup' ), [ 'bulan' => $ym ] );
		pkw_log( 0, 'buka_bulan', $ym );
		pkw_back( $back, pkw_ym_label( $ym ) . ' dibuka semula.' );
	}
	if ( $ym >= current_time( 'Y-m' ) ) { pkw_back( $back, 'Hanya bulan yang telah berlalu boleh ditutup.', 'err' ); }
	$wpdb->replace( pkw_t( 'tutup' ), [ 'bulan' => $ym, 'ditutup_oleh' => get_current_user_id(), 'ditutup_pada' => pkw_now() ] );
	pkw_log( 0, 'tutup_bulan', $ym );
	pkw_back( $back, pkw_ym_label( $ym ) . ' ditutup. Transaksi bulan ini kini dikunci.' );
} );

/* CSV exports (viewers allowed) */
add_action( 'admin_post_pkw_csv', function () {
	if ( ! pkw_can_view() ) { wp_die( 'Akses ditolak.', 403 ); }
	check_admin_referer( 'pkw_csv' );
	[ $dari, $hingga ] = pkw_range_from_request( $_GET );
	pkw_log( 0, 'eksport', sanitize_key( $_GET['jenis'] ?? '' ) . " $dari..$hingga" );
	if ( 'penyata' === ( $_GET['jenis'] ?? '' ) ) {
		$p = pkw_penyata( $dari, $hingga );
		$rows = [ [ 'BAKI AWAL', '', $p['baki_awal'] ] ];
		if ( $p['baki_awal_baru'] ) { $rows[] = [ 'BAKI AWAL AKAUN BAHARU', '', $p['baki_awal_baru'] ]; }
		foreach ( $p['masuk'] as $l ) { $rows[] = [ 'Penerimaan', $l['nama'], $l['jumlah'] ]; }
		$rows[] = [ 'JUMLAH PENERIMAAN', '', $p['jumlah_masuk'] ];
		foreach ( $p['keluar'] as $l ) { $rows[] = [ 'Pembayaran', $l['nama'], $l['jumlah'] ]; }
		$rows[] = [ 'JUMLAH PEMBAYARAN', '', $p['jumlah_keluar'] ];
		$rows[] = [ 'LEBIHAN / (KURANGAN)', '', $p['lebihan'] ];
		foreach ( $p['akaun'] as $a ) { $rows[] = [ 'Baki akhir akaun', $a['nama'], $a['akhir'] ]; }
		$rows[] = [ 'BAKI AKHIR', '', $p['baki_akhir'] ];
		pkw_csv_out( "penyata-kewangan-$dari-$hingga", [ 'Bahagian', 'Perkara', 'Jumlah (RM)' ], $rows );
	}
	$kmap = pkw_kategori_map(); $amap = pkw_akaun_map();
	$rows = [];
	foreach ( pkw_tx_list( [ 'dari' => $dari, 'hingga' => $hingga ], 100000 ) as $t ) {
		$rows[] = [ $t['id'], $t['tarikh'], $t['jenis'], $kmap[ (int) $t['kategori_id'] ]['nama'] ?? '', $amap[ (int) $t['akaun_id'] ]['nama'] ?? '', $amap[ (int) $t['akaun_ke'] ]['nama'] ?? '', $t['jumlah'], PKW_CARA[ $t['cara'] ] ?? $t['cara'], $t['rujukan'], $t['pihak'], $t['butiran'] ];
	}
	pkw_csv_out( "transaksi-kewangan-$dari-$hingga", [ 'ID', 'Tarikh', 'Jenis', 'Kategori', 'Akaun', 'Ke Akaun', 'Jumlah (RM)', 'Cara', 'Rujukan', 'Pihak', 'Butiran' ], $rows );
} );

/* stream proof (viewers allowed) */
add_action( 'admin_post_pkw_fail', function () {
	if ( ! pkw_can_view() ) { wp_die( 'Akses ditolak.', 403 ); }
	check_admin_referer( 'pkw_fail' );
	pkw_stream_private( sanitize_text_field( wp_unslash( $_GET['f'] ?? '' ) ) );
} );

/** Resolve the reporting period from ?tempoh=bulan|tahun|julat. @return array{0:string,1:string,2:string} */
function pkw_range_from_request( array $q ) {
	$tempoh = in_array( $q['tempoh'] ?? '', [ 'bulan', 'tahun', 'julat' ], true ) ? $q['tempoh'] : 'bulan';
	if ( 'tahun' === $tempoh ) {
		$y = (int) ( $q['tahun'] ?? 0 ); if ( $y < 2000 || $y > 2100 ) { $y = (int) current_time( 'Y' ); }
		return [ "$y-01-01", "$y-12-31", "Tahun $y" ];
	}
	if ( 'julat' === $tempoh ) {
		$d = pkw_date_in( $q['dari'] ?? '' ); $h = pkw_date_in( $q['hingga'] ?? '' );
		if ( $d && $h && $d <= $h ) { return [ $d, $h, pkw_date_label( $d ) . ' – ' . pkw_date_label( $h ) ]; }
	}
	$ym = pkw_ym_in( sanitize_text_field( (string) ( $q['bulan'] ?? '' ) ), current_time( 'Y-m' ) );
	return [ "$ym-01", gmdate( 'Y-m-t', strtotime( "$ym-01" ) ), pkw_ym_label( $ym ) ];
}
