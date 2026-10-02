<?php
defined( 'ABSPATH' ) || exit;

function pkw_install_schema() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	$kat = pkw_t( 'kategori' ); $ak = pkw_t( 'akaun' ); $tx = pkw_t( 'transaksi' ); $tt = pkw_t( 'tutup' ); $lg = pkw_t( 'log' );

	dbDelta( "CREATE TABLE $kat (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  jenis varchar(6) NOT NULL,
  nama varchar(120) NOT NULL,
  susunan smallint(5) NOT NULL DEFAULT 0,
  aktif tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY jenis (jenis)
) $c;" );

	dbDelta( "CREATE TABLE $ak (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  nama varchar(120) NOT NULL,
  butiran varchar(190) NOT NULL DEFAULT '',
  baki_awal decimal(12,2) NOT NULL DEFAULT 0,
  tarikh_mula date NOT NULL,
  aktif tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id)
) $c;" );

	dbDelta( "CREATE TABLE $tx (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  tarikh date NOT NULL,
  jenis varchar(6) NOT NULL,
  kategori_id bigint(20) unsigned NOT NULL DEFAULT 0,
  akaun_id bigint(20) unsigned NOT NULL DEFAULT 0,
  akaun_ke bigint(20) unsigned NOT NULL DEFAULT 0,
  jumlah decimal(12,2) NOT NULL DEFAULT 0,
  cara varchar(10) NOT NULL DEFAULT 'tunai',
  rujukan varchar(80) NOT NULL DEFAULT '',
  pihak varchar(190) NOT NULL DEFAULT '',
  butiran text NULL,
  bukti varchar(255) NOT NULL DEFAULT '',
  status varchar(6) NOT NULL DEFAULT 'aktif',
  sebab_batal varchar(255) NOT NULL DEFAULT '',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
  updated_at datetime NULL,
  PRIMARY KEY  (id),
  KEY tarikh (tarikh),
  KEY jenis (jenis),
  KEY kategori_id (kategori_id),
  KEY akaun_id (akaun_id),
  KEY status (status)
) $c;" );

	dbDelta( "CREATE TABLE $tt (
  bulan char(7) NOT NULL,
  ditutup_oleh bigint(20) unsigned NOT NULL DEFAULT 0,
  ditutup_pada datetime NOT NULL,
  PRIMARY KEY  (bulan)
) $c;" );

	dbDelta( "CREATE TABLE $lg (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  transaksi_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  tindakan varchar(30) NOT NULL,
  butiran text NULL,
  ip varchar(45) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY transaksi_id (transaksi_id),
  KEY created_at (created_at)
) $c;" );

	update_option( 'pkw_db_ver', PKW_DB_VER, false );
}

function pkw_seed() {
	global $wpdb;
	if ( ! (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . pkw_t( 'kategori' ) ) ) {
		$masuk = [ 'Kutipan Tabung Jumaat', 'Kutipan Tabung Harian / Peti Infaq', 'Infaq & Sumbangan Am', 'Sumbangan Program / Majlis', 'Sumbangan Pembangunan Masjid', 'Kutipan Ramadan & Hari Raya', 'Sewa Dewan / Fasiliti', 'Geran / Peruntukan (MAIS / Kerajaan)', 'Hibah / Keuntungan Bank', 'Penerimaan Lain-lain' ];
		$keluar = [ 'Elaun Imam, Bilal & Siak', 'Bil Elektrik', 'Bil Air', 'Internet & Telefon', 'Penyelenggaraan & Pembaikan', 'Kebersihan & Landskap', 'Program, Kuliah & Honorarium Penceramah', 'Jamuan & Moreh', 'Manfaat Khairat Kematian', 'Pembelian Ternakan Korban', 'Bantuan Asnaf & Kebajikan', 'Pentadbiran & Alat Tulis', 'Perabot & Peralatan', 'Bayaran Bank', 'Pembayaran Lain-lain' ];
		foreach ( $masuk as $i => $n ) { $wpdb->insert( pkw_t( 'kategori' ), [ 'jenis' => 'masuk', 'nama' => $n, 'susunan' => $i ] ); }
		foreach ( $keluar as $i => $n ) { $wpdb->insert( pkw_t( 'kategori' ), [ 'jenis' => 'keluar', 'nama' => $n, 'susunan' => $i ] ); }
	}
	if ( ! (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . pkw_t( 'akaun' ) ) ) {
		$start = current_time( 'Y' ) . '-01-01';
		$wpdb->insert( pkw_t( 'akaun' ), [ 'nama' => 'Akaun Bank Masjid', 'butiran' => 'Kemas kini nama bank & no. akaun di Tetapan', 'baki_awal' => 0, 'tarikh_mula' => $start ] );
		$bank = (int) $wpdb->insert_id;
		$wpdb->insert( pkw_t( 'akaun' ), [ 'nama' => 'Tunai (Wang Runcit)', 'butiran' => 'Dipegang Bendahari', 'baki_awal' => 0, 'tarikh_mula' => $start ] );
		$s = pkw_settings(); $s['kariah_akaun'] = $bank; update_option( 'pkw_settings', $s, false );
	}
}

function pkw_ensure_page() {
	$id = (int) get_option( 'pkw_page_id' );
	if ( $id && ( $p = get_post( $id ) ) && 'trash' !== $p->post_status ) { return $id; }
	$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Laporan Kewangan', 'post_name' => 'laporan-kewangan', 'post_content' => '<!-- wp:shortcode -->[pkw_laporan]<!-- /wp:shortcode -->', 'comment_status' => 'closed' ] );
	if ( $id && ! is_wp_error( $id ) ) {
		update_option( 'pkw_page_id', $id, false );
		update_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', '1' );
	}
	return (int) $id;
}
function pkw_page_url( array $args = [] ) {
	$u = get_permalink( (int) get_option( 'pkw_page_id' ) ) ?: home_url( '/laporan-kewangan/' );
	return $args ? add_query_arg( $args, $u ) : $u;
}

/* ------------------------------------------------------------- lookups */

function pkw_kategori( $jenis = '', $aktif_only = false ) {
	global $wpdb;
	$w = [ '1=1' ];
	if ( $jenis ) { $w[] = $wpdb->prepare( 'jenis=%s', $jenis ); }
	if ( $aktif_only ) { $w[] = 'aktif=1'; }
	return $wpdb->get_results( 'SELECT * FROM ' . pkw_t( 'kategori' ) . ' WHERE ' . implode( ' AND ', $w ) . ' ORDER BY jenis, susunan, nama', ARRAY_A );
}
function pkw_kategori_map() { $m = []; foreach ( pkw_kategori() as $k ) { $m[ (int) $k['id'] ] = $k; } return $m; }
function pkw_akaun( $aktif_only = false ) {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . pkw_t( 'akaun' ) . ( $aktif_only ? ' WHERE aktif=1' : '' ) . ' ORDER BY id', ARRAY_A );
}
function pkw_akaun_map() { $m = []; foreach ( pkw_akaun() as $a ) { $m[ (int) $a['id'] ] = $a; } return $m; }

function pkw_get_tx( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . pkw_t( 'transaksi' ) . ' WHERE id=%d', $id ), ARRAY_A );
}

function pkw_bulan_ditutup( $date_or_ym ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . pkw_t( 'tutup' ) . ' WHERE bulan=%s', substr( (string) $date_or_ym, 0, 7 ) ) );
}
function pkw_tutup_list() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . pkw_t( 'tutup' ) . ' ORDER BY bulan DESC', ARRAY_A );
}

function pkw_log( $tx_id, $tindakan, $butiran = '' ) {
	global $wpdb;
	$ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$wpdb->insert( pkw_t( 'log' ), [ 'transaksi_id' => (int) $tx_id, 'user_id' => get_current_user_id(), 'tindakan' => $tindakan, 'butiran' => mb_substr( (string) $butiran, 0, 2000 ), 'ip' => mb_substr( $ip, 0, 45 ), 'created_at' => pkw_now() ] );
}
function pkw_log_list( $limit = 50, $tx_id = 0 ) {
	global $wpdb;
	$w = $tx_id ? $wpdb->prepare( 'WHERE transaksi_id=%d', $tx_id ) : '';
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . pkw_t( 'log' ) . " $w ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
}

/* ------------------------------------------------------------- transactions */

/** @return int|WP_Error */
function pkw_tx_save( array $d, $id = 0 ) {
	global $wpdb;
	$jenis = in_array( $d['jenis'] ?? '', [ 'masuk', 'keluar', 'pindah' ], true ) ? $d['jenis'] : '';
	$tarikh = pkw_date_in( $d['tarikh'] ?? '' );
	$jumlah = pkw_money( $d['jumlah'] ?? 0 );
	$akaun  = (int) ( $d['akaun_id'] ?? 0 );
	$akmap  = pkw_akaun_map();
	if ( ! $jenis ) { return new WP_Error( 'v', 'Jenis transaksi tidak sah.' ); }
	if ( ! $tarikh ) { return new WP_Error( 'v', 'Tarikh tidak sah.' ); }
	if ( $tarikh > pkw_today() ) { return new WP_Error( 'v', 'Tarikh tidak boleh melebihi hari ini.' ); }
	if ( $jumlah <= 0 || $jumlah > 9999999 ) { return new WP_Error( 'v', 'Jumlah mesti lebih daripada RM 0.00.' ); }
	if ( ! isset( $akmap[ $akaun ] ) ) { return new WP_Error( 'v', 'Sila pilih akaun.' ); }
	if ( pkw_bulan_ditutup( $tarikh ) ) { return new WP_Error( 'v', 'Bulan ' . pkw_ym_label( substr( $tarikh, 0, 7 ) ) . ' telah ditutup. Transaksi tidak boleh ditambah/diubah.' ); }
	$row = [
		'tarikh' => $tarikh, 'jenis' => $jenis, 'akaun_id' => $akaun, 'jumlah' => $jumlah,
		'cara' => array_key_exists( $d['cara'] ?? '', PKW_CARA ) ? $d['cara'] : 'lain',
		'rujukan' => mb_substr( (string) ( $d['rujukan'] ?? '' ), 0, 80 ),
		'pihak' => mb_substr( (string) ( $d['pihak'] ?? '' ), 0, 190 ),
		'butiran' => mb_substr( (string) ( $d['butiran'] ?? '' ), 0, 1000 ),
		'kategori_id' => 0, 'akaun_ke' => 0,
	];
	if ( 'pindah' === $jenis ) {
		$ke = (int) ( $d['akaun_ke'] ?? 0 );
		if ( ! isset( $akmap[ $ke ] ) || $ke === $akaun ) { return new WP_Error( 'v', 'Sila pilih akaun destinasi yang berbeza.' ); }
		$row['akaun_ke'] = $ke;
	} else {
		$kat = pkw_kategori_map()[ (int) ( $d['kategori_id'] ?? 0 ) ] ?? null;
		if ( ! $kat || $kat['jenis'] !== $jenis ) { return new WP_Error( 'v', 'Sila pilih kategori yang sepadan.' ); }
		$row['kategori_id'] = (int) $kat['id'];
	}
	if ( ! empty( $d['bukti'] ) ) { $row['bukti'] = $d['bukti']; }

	if ( $id ) {
		$old = pkw_get_tx( $id );
		if ( ! $old || 'aktif' !== $old['status'] ) { return new WP_Error( 'v', 'Transaksi tidak ditemui atau telah dibatalkan.' ); }
		if ( pkw_bulan_ditutup( $old['tarikh'] ) ) { return new WP_Error( 'v', 'Transaksi asal berada dalam bulan yang telah ditutup.' ); }
		$row['updated_by'] = get_current_user_id(); $row['updated_at'] = pkw_now();
		$wpdb->update( pkw_t( 'transaksi' ), $row, [ 'id' => $id ] );
		$diff = [];
		foreach ( [ 'tarikh', 'jenis', 'kategori_id', 'akaun_id', 'akaun_ke', 'jumlah', 'cara', 'rujukan', 'pihak', 'butiran', 'bukti' ] as $f ) {
			if ( isset( $row[ $f ] ) && (string) $row[ $f ] !== (string) $old[ $f ] && ! ( 'jumlah' === $f && (float) $row[ $f ] === (float) $old[ $f ] ) ) { $diff[] = "$f: {$old[$f]} → {$row[$f]}"; }
		}
		pkw_log( $id, 'ubah', implode( '; ', $diff ) ?: 'tiada perubahan' );
		return (int) $id;
	}
	$row['created_by'] = get_current_user_id(); $row['created_at'] = pkw_now(); $row['status'] = 'aktif';
	$wpdb->insert( pkw_t( 'transaksi' ), $row );
	$nid = (int) $wpdb->insert_id;
	pkw_log( $nid, 'tambah', "$jenis " . pkw_rm( $jumlah ) . " ($tarikh)" );
	return $nid;
}

function pkw_tx_batal( $id, $sebab ) {
	global $wpdb;
	$t = pkw_get_tx( $id );
	if ( ! $t || 'aktif' !== $t['status'] ) { return new WP_Error( 'v', 'Transaksi tidak ditemui atau telah dibatalkan.' ); }
	if ( pkw_bulan_ditutup( $t['tarikh'] ) ) { return new WP_Error( 'v', 'Bulan transaksi telah ditutup.' ); }
	if ( mb_strlen( trim( $sebab ) ) < 5 ) { return new WP_Error( 'v', 'Sila nyatakan sebab pembatalan (sekurang-kurangnya 5 aksara).' ); }
	$wpdb->update( pkw_t( 'transaksi' ), [ 'status' => 'batal', 'sebab_batal' => mb_substr( $sebab, 0, 255 ), 'updated_by' => get_current_user_id(), 'updated_at' => pkw_now() ], [ 'id' => $id ] );
	pkw_log( $id, 'batal', $sebab );
	return true;
}

function pkw_tx_list( array $f, $limit = 200, $offset = 0, &$total = null ) {
	global $wpdb;
	$w = [ "t.status='aktif'" ];
	if ( ! empty( $f['dari'] ) ) { $w[] = $wpdb->prepare( 't.tarikh>=%s', $f['dari'] ); }
	if ( ! empty( $f['hingga'] ) ) { $w[] = $wpdb->prepare( 't.tarikh<=%s', $f['hingga'] ); }
	if ( ! empty( $f['jenis'] ) ) { $w[] = $wpdb->prepare( 't.jenis=%s', $f['jenis'] ); }
	if ( ! empty( $f['kategori_id'] ) ) { $w[] = $wpdb->prepare( 't.kategori_id=%d', $f['kategori_id'] ); }
	if ( ! empty( $f['akaun_id'] ) ) { $w[] = $wpdb->prepare( '(t.akaun_id=%d OR t.akaun_ke=%d)', $f['akaun_id'], $f['akaun_id'] ); }
	if ( ! empty( $f['batal'] ) ) { $w[0] = "t.status='batal'"; }
	if ( ! empty( $f['q'] ) ) { $like = '%' . $wpdb->esc_like( $f['q'] ) . '%'; $w[] = $wpdb->prepare( '(t.rujukan LIKE %s OR t.pihak LIKE %s OR t.butiran LIKE %s)', $like, $like, $like ); }
	$where = implode( ' AND ', $w );
	$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . pkw_t( 'transaksi' ) . " t WHERE $where" );
	return $wpdb->get_results( $wpdb->prepare( 'SELECT t.* FROM ' . pkw_t( 'transaksi' ) . " t WHERE $where ORDER BY t.tarikh DESC, t.id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
}

/* ------------------------------------------------------------- Kariah & Khairat Masjid integration (read-only) */

function pkw_kariah_available() {
	global $wpdb;
	static $ok = null;
	if ( null === $ok ) { $ok = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'pk_bayaran' ) ); }
	return $ok;
}
function pkw_kariah_on() { return pkw_kariah_available() && ! empty( pkw_settings()['kariah_sync'] ); }

/** Verified khairat/korban payments grouped by type between dates. */
function pkw_kariah_totals( $dari, $hingga ) {
	global $wpdb;
	if ( ! pkw_kariah_on() ) { return []; }
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.jenis, COALESCE(SUM(b.jumlah),0) AS jumlah, COUNT(*) AS bil FROM {$wpdb->prefix}pk_bayaran b JOIN {$wpdb->prefix}pk_pelan p ON p.id=b.pelan_id
		 WHERE b.status='sah' AND b.tarikh BETWEEN %s AND %s GROUP BY p.jenis", $dari, $hingga ), ARRAY_A );
	$out = [];
	foreach ( $rows as $r ) { $out[ $r['jenis'] ] = [ 'jumlah' => (float) $r['jumlah'], 'bil' => (int) $r['bil'] ]; }
	return $out;
}
function pkw_kariah_label( $jenis ) { return 'khairat' === $jenis ? 'Yuran Khairat Kematian (sistem kariah)' : ( 'korban' === $jenis ? 'Bayaran Korban & Aqiqah (sistem kariah)' : 'Kutipan ' . $jenis . ' (sistem kariah)' ); }
function pkw_kariah_akaun() {
	$id = (int) pkw_settings()['kariah_akaun'];
	$m  = pkw_akaun_map();
	return isset( $m[ $id ] ) ? $id : (int) array_key_first( $m );
}

/* ------------------------------------------------------------- balances & statements */

/** Balance of one account at END of $date (inclusive). */
function pkw_baki_akaun( array $ak, $date ) {
	global $wpdb;
	if ( $date < $ak['tarikh_mula'] ) { return 0.0; }
	$tx  = pkw_t( 'transaksi' );
	$id  = (int) $ak['id'];
	$net = (float) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(CASE WHEN jenis='masuk' AND akaun_id=%d THEN jumlah
		                      WHEN jenis='keluar' AND akaun_id=%d THEN -jumlah
		                      WHEN jenis='pindah' AND akaun_ke=%d THEN jumlah
		                      WHEN jenis='pindah' AND akaun_id=%d THEN -jumlah ELSE 0 END),0)
		 FROM $tx WHERE status='aktif' AND tarikh BETWEEN %s AND %s", $id, $id, $id, $id, $ak['tarikh_mula'], $date ) );
	if ( pkw_kariah_on() && pkw_kariah_akaun() === $id ) {
		$net += array_sum( array_column( pkw_kariah_totals( $ak['tarikh_mula'], $date ), 'jumlah' ) );
	}
	return round( (float) $ak['baki_awal'] + $net, 2 );
}

function pkw_baki_semua( $date ) {
	$out = [];
	foreach ( pkw_akaun() as $a ) { $out[ (int) $a['id'] ] = pkw_baki_akaun( $a, $date ); }
	return $out;
}

/**
 * Penyata Penerimaan & Pembayaran for [dari, hingga].
 * baki_awal + baki_awal_baru + masuk - keluar = baki_akhir (transfers net to zero).
 */
function pkw_penyata( $dari, $hingga ) {
	global $wpdb;
	$tx   = pkw_t( 'transaksi' );
	$kmap = pkw_kategori_map();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT jenis, kategori_id, SUM(jumlah) AS jumlah, COUNT(*) AS bil FROM $tx WHERE status='aktif' AND jenis IN ('masuk','keluar') AND tarikh BETWEEN %s AND %s GROUP BY jenis, kategori_id", $dari, $hingga ), ARRAY_A );
	$masuk = []; $keluar = [];
	foreach ( $rows as $r ) {
		$line = [ 'nama' => $kmap[ (int) $r['kategori_id'] ]['nama'] ?? '(Kategori dipadam)', 'jumlah' => (float) $r['jumlah'], 'bil' => (int) $r['bil'], 'susunan' => (int) ( $kmap[ (int) $r['kategori_id'] ]['susunan'] ?? 999 ), 'kategori_id' => (int) $r['kategori_id'] ];
		if ( 'masuk' === $r['jenis'] ) { $masuk[] = $line; } else { $keluar[] = $line; }
	}
	foreach ( pkw_kariah_totals( $dari, $hingga ) as $j => $v ) {
		$masuk[] = [ 'nama' => pkw_kariah_label( $j ), 'jumlah' => $v['jumlah'], 'bil' => $v['bil'], 'susunan' => 500, 'kategori_id' => 0, 'auto' => 1 ];
	}
	$sort = fn( $a, $b ) => [ $a['susunan'], $a['nama'] ] <=> [ $b['susunan'], $b['nama'] ];
	usort( $masuk, $sort ); usort( $keluar, $sort );

	$prev = gmdate( 'Y-m-d', strtotime( $dari . ' -1 day' ) );
	$awal = 0.0; $awal_baru = 0.0; $akhir = 0.0; $akaun = [];
	foreach ( pkw_akaun() as $a ) {
		$b0 = pkw_baki_akaun( $a, $prev );
		$b1 = pkw_baki_akaun( $a, $hingga );
		$baru = ( $a['tarikh_mula'] >= $dari && $a['tarikh_mula'] <= $hingga ) ? (float) $a['baki_awal'] : 0.0;
		$awal += $b0; $awal_baru += $baru; $akhir += $b1;
		$akaun[] = [ 'nama' => $a['nama'], 'butiran' => $a['butiran'], 'awal' => $b0 + $baru, 'akhir' => $b1 ];
	}
	$tm = array_sum( array_column( $masuk, 'jumlah' ) );
	$tk = array_sum( array_column( $keluar, 'jumlah' ) );
	return [
		'dari' => $dari, 'hingga' => $hingga, 'masuk' => $masuk, 'keluar' => $keluar,
		'jumlah_masuk' => round( $tm, 2 ), 'jumlah_keluar' => round( $tk, 2 ), 'lebihan' => round( $tm - $tk, 2 ),
		'baki_awal' => round( $awal, 2 ), 'baki_awal_baru' => round( $awal_baru, 2 ), 'baki_akhir' => round( $akhir, 2 ), 'akaun' => $akaun,
		'selaras' => abs( ( $awal + $awal_baru + $tm - $tk ) - $akhir ) < 0.005,
	];
}

/** Monthly income/expense totals for a year (for the chart). */
function pkw_bulanan( $tahun ) {
	global $wpdb;
	$out = [];
	for ( $m = 1; $m <= 12; $m++ ) { $out[ $m ] = [ 'masuk' => 0.0, 'keluar' => 0.0 ]; }
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT MONTH(tarikh) AS m, jenis, SUM(jumlah) AS j FROM ' . pkw_t( 'transaksi' ) . " WHERE status='aktif' AND jenis IN ('masuk','keluar') AND YEAR(tarikh)=%d GROUP BY m, jenis", $tahun ), ARRAY_A );
	foreach ( $rows as $r ) { $out[ (int) $r['m'] ][ $r['jenis'] ] += (float) $r['j']; }
	if ( pkw_kariah_on() ) {
		$k = $wpdb->get_results( $wpdb->prepare( "SELECT MONTH(b.tarikh) AS m, SUM(b.jumlah) AS j FROM {$wpdb->prefix}pk_bayaran b WHERE b.status='sah' AND YEAR(b.tarikh)=%d GROUP BY m", $tahun ), ARRAY_A );
		foreach ( $k as $r ) { $out[ (int) $r['m'] ]['masuk'] += (float) $r['j']; }
	}
	return $out;
}
