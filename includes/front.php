<?php
defined( 'ABSPATH' ) || exit;

function pkw_is_page() { $id = (int) get_option( 'pkw_page_id' ); return $id && is_page( $id ); }

add_action( 'template_redirect', function () {
	if ( ! pkw_is_page() ) { return; }
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'Referrer-Policy: same-origin', true );
} );
add_filter( 'wp_robots', function ( $r ) { if ( pkw_is_page() ) { $r['noindex'] = true; $r['nofollow'] = true; } return $r; } );
// keep the page out of search, sitemaps and menus-by-default
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $ids ) { $ids[] = (int) get_option( 'pkw_page_id' ); return $ids; } );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && $q->is_search() ) { $q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), [ (int) get_option( 'pkw_page_id' ) ] ) ); }
} );

const PKW_FA_URL = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css';
const PKW_FA_SRI = 'sha384-PPIZEGYM1v8zp5Py7UjFb79S58UeqCL9pYVnVPURKEqvioPROaVAJKKLzvH2rDnI';

/** Enqueue assets. Also called from the shortcode, because block themes render content before wp_enqueue_scripts. */
function pkw_enqueue() {
	wp_enqueue_style( 'pkw', PKW_URL . 'assets/pkw.css', [], PKW_VER );
	wp_enqueue_script( 'pkw', PKW_URL . 'assets/pkw.js', [], PKW_VER, true );
	$fa = pkw_settings()['fontawesome'];
	if ( 'cdn' === $fa || ( 'auto' === $fa && ! pkw_theme_has_fa() ) ) { wp_enqueue_style( 'pkw-fontawesome', PKW_FA_URL, [], null ); }
}
add_action( 'wp_enqueue_scripts', function () { if ( pkw_is_page() ) { pkw_enqueue(); } }, 20 );
add_filter( 'style_loader_tag', function ( $tag, $handle ) {
	return 'pkw-fontawesome' === $handle ? str_replace( ' href=', ' integrity="' . PKW_FA_SRI . '" crossorigin="anonymous" href=', $tag ) : $tag;
}, 10, 2 );
function pkw_theme_has_fa() {
	$st = wp_styles();
	foreach ( array_merge( $st->queue, $st->done ) as $h ) {
		if ( 'pkw-fontawesome' === $h || empty( $st->registered[ $h ] ) ) { continue; }
		if ( preg_match( '/font-?awesome/i', $h . ' ' . (string) $st->registered[ $h ]->src ) ) { return true; }
	}
	return false;
}

add_shortcode( 'pkw_laporan', 'pkw_render' );

/* Keep the internal page out of automatic page menus (classic wp_list_pages and the block Page List). */
add_filter( 'wp_list_pages_excludes', function ( $ex ) { $ex[] = (int) get_option( 'pkw_page_id' ); return $ex; } );
add_filter( 'get_pages', function ( $pages ) {
	if ( is_admin() ) { return $pages; }
	$id = (int) get_option( 'pkw_page_id' );
	return array_values( array_filter( (array) $pages, fn( $p ) => (int) $p->ID !== $id ) );
} );

function pkw_render() {
	pkw_enqueue();
	if ( ! is_user_logged_in() ) {
		return '<div class="pkw pkw-gate"><div class="pkw-gate-card"><i class="fa-solid fa-lock" aria-hidden="true"></i><h2>Laporan Kewangan</h2><p>Halaman dalaman untuk Bendahari dan AJK yang diberi kebenaran sahaja.</p><a class="btn btn-success" href="' . esc_url( wp_login_url( pkw_page_url() ) ) . '">Log masuk AJK</a></div></div>';
	}
	if ( ! pkw_can_view() ) {
		return '<div class="pkw pkw-gate"><div class="pkw-gate-card"><i class="fa-solid fa-ban" aria-hidden="true"></i><h2>Tiada akses</h2><p>Akaun anda tidak mempunyai kebenaran melihat laporan kewangan. Sila hubungi Bendahari atau pentadbir laman.</p></div></div>';
	}
	$edit = pkw_can_edit();
	$tabs = [ 'ringkasan' => [ 'Ringkasan', 'fa-chart-pie' ], 'transaksi' => [ 'Transaksi', 'fa-list' ], 'penyata' => [ 'Penyata', 'fa-file-invoice' ], 'audit' => [ 'Jejak Audit', 'fa-clock-rotate-left' ] ];
	if ( $edit ) { $tabs['tetapan'] = [ 'Tetapan', 'fa-gear' ]; }
	$tab = isset( $tabs[ $_GET['tab'] ?? '' ] ) ? $_GET['tab'] : 'ringkasan';

	ob_start();
	$u = wp_get_current_user();
	echo '<div class="pkw">';
	echo '<div class="pkw-head"><div><h2 class="pkw-title">' . esc_html( pkw_settings()['nama_masjid'] ) . '</h2><div class="pkw-sub">Laporan kewangan dalaman — sulit</div></div><div class="pkw-user"><i class="fa-solid fa-user-shield" aria-hidden="true"></i> ' . esc_html( $u->display_name ) . ' <span class="pkw-badge">' . ( $edit ? 'Bendahari' : 'Lihat sahaja' ) . '</span> <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Log keluar</a></div></div>';
	if ( $fl = pkw_flash_take( sanitize_text_field( wp_unslash( $_GET['pkw_m'] ?? '' ) ) ) ) {
		echo '<div class="pkw-alert pkw-alert-' . ( 'err' === $fl[1] ? 'err' : 'ok' ) . '" role="status">' . esc_html( $fl[0] ) . '</div>';
	}
	echo '<nav class="pkw-tabs" aria-label="Bahagian laporan">';
	foreach ( $tabs as $k => [ $l, $ic ] ) {
		echo '<a class="' . ( $k === $tab ? 'is-active' : '' ) . '" href="' . esc_url( pkw_page_url( [ 'tab' => $k ] ) ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '><i class="fa-solid ' . esc_attr( $ic ) . '" aria-hidden="true"></i> ' . esc_html( $l ) . '</a>';
	}
	echo '</nav><div class="pkw-body">';
	call_user_func( 'pkw_tab_' . $tab, $edit );
	echo '</div></div>';
	return ob_get_clean();
}

/* ================================================================ RINGKASAN */

function pkw_tab_ringkasan( $edit ) {
	$ym   = current_time( 'Y-m' );
	$y    = (int) current_time( 'Y' );
	$bul  = pkw_penyata( "$ym-01", pkw_today() );
	$thn  = pkw_penyata( "$y-01-01", pkw_today() );
	$amap = pkw_akaun_map();
	$baki = pkw_baki_semua( pkw_today() );

	echo '<div class="pkw-kpis">';
	pkw_kpi( 'Baki semasa (semua akaun)', pkw_rm( array_sum( $baki ) ), 'fa-wallet', 'teal' );
	pkw_kpi( 'Penerimaan ' . pkw_ym_label( $ym ), pkw_rm( $bul['jumlah_masuk'] ), 'fa-arrow-down', 'green' );
	pkw_kpi( 'Pembayaran ' . pkw_ym_label( $ym ), pkw_rm( $bul['jumlah_keluar'] ), 'fa-arrow-up', 'red' );
	pkw_kpi( 'Lebihan / (Kurangan) ' . $y, pkw_rm( $thn['lebihan'] ), 'fa-scale-balanced', $thn['lebihan'] < 0 ? 'red' : 'green' );
	echo '</div>';

	if ( $edit ) {
		echo '<div class="pkw-quick"><a class="btn btn-success" href="' . esc_url( pkw_page_url( [ 'tab' => 'transaksi', 'baru' => 1, 'j' => 'masuk' ] ) ) . '"><i class="fa-solid fa-plus"></i> Rekod penerimaan</a> <a class="btn btn-outline-danger" href="' . esc_url( pkw_page_url( [ 'tab' => 'transaksi', 'baru' => 1, 'j' => 'keluar' ] ) ) . '"><i class="fa-solid fa-minus"></i> Rekod pembayaran</a> <a class="btn btn-outline-secondary" href="' . esc_url( pkw_page_url( [ 'tab' => 'transaksi', 'baru' => 1, 'j' => 'pindah' ] ) ) . '"><i class="fa-solid fa-right-left"></i> Pindahan antara akaun</a></div>';
	}

	echo '<div class="pkw-grid2"><section class="pkw-card"><h3>Penerimaan &amp; pembayaran bulanan ' . (int) $y . '</h3>' . pkw_chart_svg( pkw_bulanan( $y ) ) . '<div class="pkw-legend"><span class="sw sw-in"></span> Penerimaan <span class="sw sw-out"></span> Pembayaran</div></section>';
	echo '<section class="pkw-card"><h3>Baki akaun</h3><table class="pkw-table"><tbody>';
	foreach ( $baki as $id => $b ) {
		echo '<tr><td>' . esc_html( $amap[ $id ]['nama'] ) . '<div class="pkw-muted">' . esc_html( $amap[ $id ]['butiran'] ) . ( $amap[ $id ]['aktif'] ? '' : ' · tidak aktif' ) . '</div></td><td class="num">' . esc_html( pkw_rm( $b ) ) . '</td></tr>';
	}
	echo '</tbody><tfoot><tr><th>Jumlah</th><th class="num">' . esc_html( pkw_rm( array_sum( $baki ) ) ) . '</th></tr></tfoot></table></section></div>';

	echo '<div class="pkw-grid2">';
	pkw_top_cats( 'Penerimaan utama ' . $y, $thn['masuk'], $thn['jumlah_masuk'], 'in' );
	pkw_top_cats( 'Pembayaran utama ' . $y, $thn['keluar'], $thn['jumlah_keluar'], 'out' );
	echo '</div>';

	$recent = pkw_tx_list( [], 8 );
	echo '<section class="pkw-card"><h3>Transaksi terkini</h3>';
	pkw_tx_table( $recent, false );
	echo '<p><a href="' . esc_url( pkw_page_url( [ 'tab' => 'transaksi' ] ) ) . '">Lihat semua transaksi →</a></p></section>';
	if ( pkw_kariah_on() ) {
		echo '<p class="pkw-muted"><i class="fa-solid fa-circle-info"></i> Penerimaan termasuk bayaran khairat &amp; korban yang telah <strong>disahkan</strong> dalam sistem Kariah (tidak perlu direkod semula di sini).</p>';
	}
}

function pkw_kpi( $label, $val, $icon, $tone ) {
	echo '<div class="pkw-kpi pkw-tone-' . esc_attr( $tone ) . '"><i class="fa-solid ' . esc_attr( $icon ) . '" aria-hidden="true"></i><div><div class="pkw-kpi-v">' . esc_html( $val ) . '</div><div class="pkw-kpi-l">' . esc_html( $label ) . '</div></div></div>';
}

function pkw_top_cats( $title, array $lines, $total, $tone ) {
	usort( $lines, fn( $a, $b ) => $b['jumlah'] <=> $a['jumlah'] );
	echo '<section class="pkw-card"><h3>' . esc_html( $title ) . '</h3>';
	if ( ! $lines ) { echo '<p class="pkw-muted">Tiada rekod lagi.</p></section>'; return; }
	echo '<ul class="pkw-bars">';
	foreach ( array_slice( $lines, 0, 6 ) as $l ) {
		$pct = $total > 0 ? $l['jumlah'] / $total * 100 : 0;
		echo '<li><div class="pkw-bar-l"><span>' . esc_html( $l['nama'] ) . '</span><strong>' . esc_html( pkw_rm( $l['jumlah'] ) ) . '</strong></div><svg class="pkw-bar" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true"><rect width="100" height="6" rx="3" class="trk"/><rect width="' . esc_attr( round( max( $pct, 1 ), 1 ) ) . '" height="6" rx="3" class="fill-' . esc_attr( $tone ) . '"/></svg></li>';
	}
	echo '</ul></section>';
}

/** Grouped monthly bar chart as SVG (no inline CSS → CSP-safe). */
function pkw_chart_svg( array $data ) {
	$max = 0;
	foreach ( $data as $d ) { $max = max( $max, $d['masuk'], $d['keluar'] ); }
	$W = 640; $H = 240; $pl = 56; $pb = 26; $pt = 10; $ch = $H - $pb - $pt; $cw = ( $W - $pl - 8 ) / 12;
	$step = $max > 0 ? pow( 10, floor( log10( $max ) ) ) : 100;
	foreach ( [ 1, 2, 2.5, 5, 10 ] as $m ) { if ( $max / ( $step * $m ) <= 5 ) { $step *= $m; break; } }
	$top = max( $step, ceil( $max / $step ) * $step );
	$s   = '<svg class="pkw-chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Carta penerimaan dan pembayaran bulanan">';
	for ( $v = 0; $v <= $top + 0.001; $v += $step ) {
		$yy = $pt + $ch - $v / $top * $ch;
		$s .= '<line x1="' . $pl . '" x2="' . ( $W - 4 ) . '" y1="' . round( $yy, 1 ) . '" y2="' . round( $yy, 1 ) . '" class="grid"/><text x="' . ( $pl - 6 ) . '" y="' . round( $yy + 4, 1 ) . '" class="ax" text-anchor="end">' . esc_html( $v >= 1000 ? round( $v / 1000, 1 ) . 'k' : (int) $v ) . '</text>';
	}
	$ab = [ 1 => 'Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis' ];
	foreach ( $data as $m => $d ) {
		$x0 = $pl + ( $m - 1 ) * $cw; $bw = $cw * 0.34;
		foreach ( [ 'masuk' => [ 0.14, 'in' ], 'keluar' => [ 0.52, 'out' ] ] as $k => [ $off, $cls ] ) {
			$h = $top > 0 ? $d[ $k ] / $top * $ch : 0;
			$s .= '<rect x="' . round( $x0 + $cw * $off, 1 ) . '" y="' . round( $pt + $ch - $h, 1 ) . '" width="' . round( $bw, 1 ) . '" height="' . round( max( $h, 0 ), 1 ) . '" rx="2" class="b-' . $cls . '"><title>' . esc_html( $ab[ $m ] . ': ' . ( 'masuk' === $k ? 'Penerimaan ' : 'Pembayaran ' ) . pkw_rm( $d[ $k ] ) ) . '</title></rect>';
		}
		$s .= '<text x="' . round( $x0 + $cw / 2, 1 ) . '" y="' . ( $H - 8 ) . '" class="ax" text-anchor="middle">' . $ab[ $m ] . '</text>';
	}
	return $s . '</svg>';
}

/* ================================================================ TRANSAKSI */

function pkw_tab_transaksi( $edit ) {
	if ( $edit && ( ! empty( $_GET['baru'] ) || ! empty( $_GET['ubah'] ) ) ) { pkw_tx_form( absint( $_GET['ubah'] ?? 0 ) ); return; }
	$f = [
		'dari' => pkw_date_in( $_GET['dari'] ?? '' ), 'hingga' => pkw_date_in( $_GET['hingga'] ?? '' ),
		'jenis' => in_array( $_GET['jenis'] ?? '', [ 'masuk', 'keluar', 'pindah' ], true ) ? $_GET['jenis'] : '',
		'kategori_id' => absint( $_GET['kategori_id'] ?? 0 ), 'akaun_id' => absint( $_GET['akaun_id'] ?? 0 ),
		'q' => pkw_text( $_GET['q'] ?? '', 60 ), 'batal' => ! empty( $_GET['batal'] ),
	];
	if ( ! $f['dari'] && ! $f['hingga'] && empty( $_GET['semua'] ) ) { $f['dari'] = current_time( 'Y-m' ) . '-01'; }
	$pg  = max( 1, absint( $_GET['pg'] ?? 1 ) ); $per = 50; $total = 0;
	$rows = pkw_tx_list( $f, $per, ( $pg - 1 ) * $per, $total );

	echo '<div class="pkw-toolbar">';
	if ( $edit ) { echo '<a class="btn btn-success" href="' . esc_url( pkw_page_url( [ 'tab' => 'transaksi', 'baru' => 1 ] ) ) . '"><i class="fa-solid fa-plus"></i> Transaksi baharu</a>'; }
	echo '</div><form class="pkw-filter" method="get" action="' . esc_url( pkw_page_url() ) . '"><input type="hidden" name="tab" value="transaksi">';
	echo '<label>Dari <input type="date" name="dari" value="' . esc_attr( $f['dari'] ) . '"></label><label>Hingga <input type="date" name="hingga" value="' . esc_attr( $f['hingga'] ) . '"></label>';
	echo '<label>Jenis <select name="jenis"><option value="">Semua</option>';
	foreach ( [ 'masuk' => 'Penerimaan', 'keluar' => 'Pembayaran', 'pindah' => 'Pindahan' ] as $k => $l ) { echo '<option value="' . $k . '"' . selected( $f['jenis'], $k, false ) . '>' . $l . '</option>'; }
	echo '</select></label><label>Kategori <select name="kategori_id"><option value="0">Semua</option>';
	foreach ( pkw_kategori() as $k ) { echo '<option value="' . (int) $k['id'] . '"' . selected( $f['kategori_id'], (int) $k['id'], false ) . '>' . ( 'masuk' === $k['jenis'] ? '↓ ' : '↑ ' ) . esc_html( $k['nama'] ) . '</option>'; }
	echo '</select></label><label>Akaun <select name="akaun_id"><option value="0">Semua</option>';
	foreach ( pkw_akaun() as $a ) { echo '<option value="' . (int) $a['id'] . '"' . selected( $f['akaun_id'], (int) $a['id'], false ) . '>' . esc_html( $a['nama'] ) . '</option>'; }
	echo '</select></label><label>Cari <input type="search" name="q" value="' . esc_attr( $f['q'] ) . '" placeholder="Rujukan / pihak / butiran"></label>';
	echo '<label class="pkw-chk"><input type="checkbox" name="batal" value="1"' . checked( $f['batal'], true, false ) . '> Dibatalkan</label>';
	echo '<button class="btn btn-primary btn-sm">Tapis</button> <a class="btn btn-link btn-sm" href="' . esc_url( pkw_page_url( [ 'tab' => 'transaksi', 'semua' => 1 ] ) ) . '">Semua tarikh</a></form>';

	$sum_in = 0; $sum_out = 0;
	foreach ( $rows as $r ) { if ( 'masuk' === $r['jenis'] ) { $sum_in += $r['jumlah']; } elseif ( 'keluar' === $r['jenis'] ) { $sum_out += $r['jumlah']; } }
	echo '<p class="pkw-muted">' . (int) $total . ' transaksi' . ( $f['dari'] ? ' dari ' . esc_html( pkw_date_label( $f['dari'] ) ) : '' ) . ( $f['hingga'] ? ' hingga ' . esc_html( pkw_date_label( $f['hingga'] ) ) : '' ) . ' · halaman ini: penerimaan <strong>' . esc_html( pkw_rm( $sum_in ) ) . '</strong>, pembayaran <strong>' . esc_html( pkw_rm( $sum_out ) ) . '</strong></p>';
	pkw_tx_table( $rows, $edit, $f['batal'] );
	if ( $total > $per ) {
		echo '<nav class="pkw-pager">';
		for ( $i = 1; $i <= (int) ceil( $total / $per ); $i++ ) { echo $i === $pg ? '<span>' . $i . '</span>' : '<a href="' . esc_url( add_query_arg( 'pg', $i ) ) . '">' . $i . '</a>'; }
		echo '</nav>';
	}
	if ( pkw_kariah_on() ) { echo '<p class="pkw-muted"><i class="fa-solid fa-circle-info"></i> Bayaran khairat &amp; korban yang disahkan dalam sistem Kariah tidak disenaraikan di sini, tetapi dimasukkan secara automatik dalam Ringkasan dan Penyata.</p>'; }
}

function pkw_tx_table( array $rows, $edit, $batal = false ) {
	if ( ! $rows ) { echo '<p class="pkw-empty">Tiada transaksi.</p>'; return; }
	$kmap = pkw_kategori_map(); $amap = pkw_akaun_map();
	echo '<div class="pkw-scroll"><table class="pkw-table pkw-tx"><thead><tr><th>Tarikh</th><th>Perkara</th><th>Akaun</th><th>Rujukan</th><th class="num">Masuk</th><th class="num">Keluar</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $t ) {
		$isP = 'pindah' === $t['jenis'];
		$nm  = $isP ? 'Pindahan ke ' . ( $amap[ (int) $t['akaun_ke'] ]['nama'] ?? '?' ) : ( $kmap[ (int) $t['kategori_id'] ]['nama'] ?? '—' );
		echo '<tr class="' . esc_attr( 'j-' . $t['jenis'] ) . '"><td>' . esc_html( pkw_date_label( $t['tarikh'] ) ) . '<div class="pkw-muted">#' . (int) $t['id'] . '</div></td><td><strong>' . esc_html( $nm ) . '</strong>';
		if ( $t['pihak'] ) { echo '<div>' . esc_html( $t['pihak'] ) . '</div>'; }
		if ( $t['butiran'] ) { echo '<div class="pkw-muted">' . esc_html( wp_trim_words( $t['butiran'], 20 ) ) . '</div>'; }
		if ( $batal ) { echo '<div class="pkw-void">Dibatalkan: ' . esc_html( $t['sebab_batal'] ) . '</div>'; }
		echo '</td><td>' . esc_html( $amap[ (int) $t['akaun_id'] ]['nama'] ?? '—' ) . '<div class="pkw-muted">' . esc_html( PKW_CARA[ $t['cara'] ] ?? '' ) . '</div></td><td>' . esc_html( $t['rujukan'] ?: '—' );
		if ( $t['bukti'] ) { echo ' <a class="pkw-file" target="_blank" rel="noopener" href="' . esc_url( pkw_file_url( $t['bukti'] ) ) . '" title="Lihat dokumen sokongan"><i class="fa-solid fa-paperclip"></i></a>'; }
		echo '</td><td class="num in">' . ( 'masuk' === $t['jenis'] ? esc_html( pkw_rm( $t['jumlah'] ) ) : ( $isP ? '<span class="pkw-muted">' . esc_html( pkw_rm( $t['jumlah'] ) ) . '</span>' : '' ) ) . '</td><td class="num out">' . ( 'keluar' === $t['jenis'] ? esc_html( pkw_rm( $t['jumlah'] ) ) : '' ) . '</td><td class="act">';
		if ( $edit && ! $batal ) {
			if ( pkw_bulan_ditutup( $t['tarikh'] ) ) { echo '<span class="pkw-muted" title="Bulan ditutup"><i class="fa-solid fa-lock"></i></span>'; }
			else { echo '<a href="' . esc_url( pkw_page_url( [ 'tab' => 'transaksi', 'ubah' => (int) $t['id'] ] ) ) . '" title="Ubah"><i class="fa-solid fa-pen"></i></a>'; }
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
}

function pkw_tx_form( $id ) {
	$t = $id ? pkw_get_tx( $id ) : null;
	if ( $id && ( ! $t || 'aktif' !== $t['status'] ) ) { echo '<p class="pkw-empty">Transaksi tidak ditemui.</p>'; return; }
	if ( $t && pkw_bulan_ditutup( $t['tarikh'] ) ) { echo '<p class="pkw-empty">Transaksi ini berada dalam bulan yang telah ditutup dan tidak boleh diubah.</p>'; return; }
	$j = $t['jenis'] ?? ( in_array( $_GET['j'] ?? '', [ 'masuk', 'keluar', 'pindah' ], true ) ? $_GET['j'] : 'masuk' );
	$v = fn( $k, $d = '' ) => $t[ $k ] ?? $d;
	echo '<section class="pkw-card pkw-form-card"><h3>' . ( $t ? 'Ubah transaksi #' . (int) $id : 'Transaksi baharu' ) . '</h3>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" class="pkw-form" id="pkw-txform">';
	wp_nonce_field( 'pkw_simpan' );
	echo '<input type="hidden" name="action" value="pkw_simpan"><input type="hidden" name="id" value="' . (int) $id . '">';
	echo '<fieldset class="pkw-seg"><legend>Jenis</legend>';
	foreach ( [ 'masuk' => 'Penerimaan', 'keluar' => 'Pembayaran', 'pindah' => 'Pindahan antara akaun' ] as $k => $l ) { echo '<label><input type="radio" name="jenis" value="' . $k . '"' . checked( $j, $k, false ) . '> ' . $l . '</label>'; }
	echo '</fieldset><div class="pkw-row">';
	echo '<label>Tarikh <input type="date" name="tarikh" required max="' . esc_attr( pkw_today() ) . '" value="' . esc_attr( $v( 'tarikh', pkw_today() ) ) . '"></label>';
	echo '<label>Jumlah (RM) <input type="text" name="jumlah" inputmode="decimal" required pattern="[0-9,]*(\.[0-9]{1,2})?" value="' . esc_attr( $t ? number_format( (float) $t['jumlah'], 2, '.', '' ) : '' ) . '" placeholder="0.00"></label></div>';
	echo '<label class="pkw-only-kat">Kategori <select name="kategori_id" id="pkw-kat"><option value="">— pilih —</option>';
	foreach ( [ 'masuk' => 'Penerimaan', 'keluar' => 'Pembayaran' ] as $jj => $gl ) {
		echo '<optgroup label="' . $gl . '" data-jenis="' . $jj . '">';
		foreach ( pkw_kategori( $jj ) as $k ) {
			if ( ! $k['aktif'] && (int) $v( 'kategori_id' ) !== (int) $k['id'] ) { continue; }
			echo '<option value="' . (int) $k['id'] . '"' . selected( (int) $v( 'kategori_id' ), (int) $k['id'], false ) . '>' . esc_html( $k['nama'] ) . '</option>';
		}
		echo '</optgroup>';
	}
	echo '</select></label><div class="pkw-row"><label><span class="pkw-lbl-akaun">Akaun</span> <select name="akaun_id" required>';
	foreach ( pkw_akaun() as $a ) { if ( $a['aktif'] || (int) $v( 'akaun_id' ) === (int) $a['id'] ) { echo '<option value="' . (int) $a['id'] . '"' . selected( (int) $v( 'akaun_id' ), (int) $a['id'], false ) . '>' . esc_html( $a['nama'] ) . '</option>'; } }
	echo '</select></label><label class="pkw-only-pindah">Ke akaun <select name="akaun_ke"><option value="0">— pilih —</option>';
	foreach ( pkw_akaun( true ) as $a ) { echo '<option value="' . (int) $a['id'] . '"' . selected( (int) $v( 'akaun_ke' ), (int) $a['id'], false ) . '>' . esc_html( $a['nama'] ) . '</option>'; }
	echo '</select></label><label>Cara <select name="cara">';
	foreach ( PKW_CARA as $k => $l ) { echo '<option value="' . $k . '"' . selected( $v( 'cara', 'tunai' ), $k, false ) . '>' . $l . '</option>'; }
	echo '</select></label></div><div class="pkw-row"><label>No. rujukan / resit / baucar <input type="text" name="rujukan" maxlength="80" value="' . esc_attr( $v( 'rujukan' ) ) . '"></label>';
	echo '<label><span class="pkw-lbl-pihak">Diterima daripada / Dibayar kepada</span> <input type="text" name="pihak" maxlength="190" value="' . esc_attr( $v( 'pihak' ) ) . '"></label></div>';
	echo '<label>Butiran <textarea name="butiran" rows="2" maxlength="1000">' . esc_textarea( $v( 'butiran' ) ) . '</textarea></label>';
	echo '<label>Dokumen sokongan (resit / bil / baucar — JPG, PNG, PDF, maks 10 MB) <input type="file" name="bukti" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>';
	if ( $t && $t['bukti'] ) { echo '<p class="pkw-muted">Dokumen sedia ada: <a target="_blank" rel="noopener" href="' . esc_url( pkw_file_url( $t['bukti'] ) ) . '">lihat</a> (muat naik baharu akan menggantikannya)</p>'; }
	echo '<div class="pkw-actions"><button class="btn btn-success" type="submit">Simpan</button>';
	if ( ! $t ) { echo ' <button class="btn btn-outline-success" type="submit" name="lagi" value="1">Simpan &amp; tambah lagi</button>'; }
	echo ' <a class="btn btn-link" href="' . esc_url( pkw_page_url( [ 'tab' => 'transaksi' ] ) ) . '">Batal</a></div></form>';
	if ( $t ) {
		echo '<details class="pkw-danger"><summary>Batalkan transaksi ini</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'pkw_batal' );
		echo '<input type="hidden" name="action" value="pkw_batal"><input type="hidden" name="id" value="' . (int) $id . '"><p class="pkw-muted">Transaksi tidak dipadam — ia ditanda "dibatalkan" dan kekal dalam jejak audit.</p><label>Sebab pembatalan <input type="text" name="sebab" required minlength="5" maxlength="255"></label> <button class="btn btn-danger btn-sm">Sahkan pembatalan</button></form></details>';
		$log = pkw_log_list( 20, $id );
		if ( $log ) {
			echo '<h4>Sejarah</h4><ul class="pkw-log">';
			foreach ( $log as $l ) { $u = get_userdata( $l['user_id'] ); echo '<li><span>' . esc_html( pkw_date_label( $l['created_at'] ) . ' ' . substr( $l['created_at'], 11, 5 ) ) . '</span> <strong>' . esc_html( $u ? $u->display_name : '—' ) . '</strong> ' . esc_html( $l['tindakan'] . ' — ' . $l['butiran'] ) . '</li>'; }
			echo '</ul>';
		}
	}
	echo '</section>';
}

/* ================================================================ PENYATA */

function pkw_tab_penyata( $edit ) {
	[ $dari, $hingga, $label ] = pkw_range_from_request( $_GET );
	$tempoh = in_array( $_GET['tempoh'] ?? '', [ 'bulan', 'tahun', 'julat' ], true ) ? $_GET['tempoh'] : 'bulan';
	$p = pkw_penyata( $dari, $hingga );

	echo '<form class="pkw-filter pkw-noprint" method="get" action="' . esc_url( pkw_page_url() ) . '"><input type="hidden" name="tab" value="penyata">';
	echo '<label>Tempoh <select name="tempoh" id="pkw-tempoh">';
	foreach ( [ 'bulan' => 'Bulanan', 'tahun' => 'Tahunan', 'julat' => 'Julat tarikh' ] as $k => $l ) { echo '<option value="' . $k . '"' . selected( $tempoh, $k, false ) . '>' . $l . '</option>'; }
	echo '</select></label><label class="pkw-t pkw-t-bulan">Bulan <input type="month" name="bulan" value="' . esc_attr( substr( $dari, 0, 7 ) ) . '"></label>';
	echo '<label class="pkw-t pkw-t-tahun">Tahun <input type="number" name="tahun" min="2000" max="2100" value="' . esc_attr( substr( $dari, 0, 4 ) ) . '"></label>';
	echo '<label class="pkw-t pkw-t-julat">Dari <input type="date" name="dari" value="' . esc_attr( $dari ) . '"></label><label class="pkw-t pkw-t-julat">Hingga <input type="date" name="hingga" value="' . esc_attr( $hingga ) . '"></label>';
	echo '<button class="btn btn-primary btn-sm">Papar</button></form>';
	$q = [ 'action' => 'pkw_csv', 'tempoh' => $tempoh, 'bulan' => substr( $dari, 0, 7 ), 'tahun' => substr( $dari, 0, 4 ), 'dari' => $dari, 'hingga' => $hingga ];
	echo '<div class="pkw-toolbar pkw-noprint"><button type="button" class="btn btn-outline-secondary btn-sm" id="pkw-print"><i class="fa-solid fa-print"></i> Cetak / PDF</button> <a class="btn btn-outline-secondary btn-sm" href="' . esc_url( wp_nonce_url( add_query_arg( $q + [ 'jenis' => 'penyata' ], admin_url( 'admin-post.php' ) ), 'pkw_csv' ) ) . '"><i class="fa-solid fa-file-csv"></i> Penyata (CSV)</a> <a class="btn btn-outline-secondary btn-sm" href="' . esc_url( wp_nonce_url( add_query_arg( $q + [ 'jenis' => 'transaksi' ], admin_url( 'admin-post.php' ) ), 'pkw_csv' ) ) . '"><i class="fa-solid fa-file-csv"></i> Senarai transaksi (CSV)</a></div>';

	$open = true; $d = $dari;
	while ( $d <= $hingga ) { if ( ! pkw_bulan_ditutup( $d ) ) { $open = false; break; } $d = gmdate( 'Y-m-01', strtotime( substr( $d, 0, 7 ) . '-01 +1 month' ) ); }

	echo '<article class="pkw-statement"><header><h3>' . esc_html( strtoupper( pkw_settings()['nama_masjid'] ) ) . '</h3><div>PENYATA PENERIMAAN DAN PEMBAYARAN</div><div>Bagi tempoh ' . esc_html( pkw_date_label( $dari ) . ' hingga ' . pkw_date_label( $hingga ) ) . ' (' . esc_html( $label ) . ')</div>';
	echo '<div class="pkw-stamp ' . ( $open ? 'is-closed' : 'is-draft' ) . '">' . ( $open ? 'Muktamad — bulan ditutup' : 'Draf — tempoh belum ditutup' ) . '</div></header>';
	echo '<table class="pkw-table pkw-st"><tbody>';
	echo '<tr class="hd"><th>Baki awal tempoh</th><th class="num">' . esc_html( pkw_rm( $p['baki_awal'] ) ) . '</th></tr>';
	if ( $p['baki_awal_baru'] ) { echo '<tr><td>Baki awal akaun baharu dalam tempoh</td><td class="num">' . esc_html( pkw_rm( $p['baki_awal_baru'] ) ) . '</td></tr>'; }
	echo '<tr class="sec"><th colspan="2">PENERIMAAN</th></tr>';
	if ( ! $p['masuk'] ) { echo '<tr><td class="pkw-muted" colspan="2">Tiada</td></tr>'; }
	foreach ( $p['masuk'] as $l ) { echo '<tr><td>' . esc_html( $l['nama'] ) . ' <span class="pkw-muted">(' . (int) $l['bil'] . ')</span></td><td class="num">' . esc_html( pkw_rm( $l['jumlah'] ) ) . '</td></tr>'; }
	echo '<tr class="tot"><th>Jumlah penerimaan</th><th class="num">' . esc_html( pkw_rm( $p['jumlah_masuk'] ) ) . '</th></tr>';
	echo '<tr class="sec"><th colspan="2">PEMBAYARAN</th></tr>';
	if ( ! $p['keluar'] ) { echo '<tr><td class="pkw-muted" colspan="2">Tiada</td></tr>'; }
	foreach ( $p['keluar'] as $l ) { echo '<tr><td>' . esc_html( $l['nama'] ) . ' <span class="pkw-muted">(' . (int) $l['bil'] . ')</span></td><td class="num">' . esc_html( pkw_rm( $l['jumlah'] ) ) . '</td></tr>'; }
	echo '<tr class="tot"><th>Jumlah pembayaran</th><th class="num">' . esc_html( pkw_rm( $p['jumlah_keluar'] ) ) . '</th></tr>';
	echo '<tr class="hd"><th>Lebihan / (Kurangan) tempoh</th><th class="num ' . ( $p['lebihan'] < 0 ? 'neg' : '' ) . '">' . esc_html( pkw_rm( $p['lebihan'] ) ) . '</th></tr>';
	echo '<tr class="sec"><th colspan="2">BAKI AKHIR MENGIKUT AKAUN</th></tr>';
	foreach ( $p['akaun'] as $a ) { echo '<tr><td>' . esc_html( $a['nama'] ) . ( $a['butiran'] ? ' <span class="pkw-muted">' . esc_html( $a['butiran'] ) . '</span>' : '' ) . '</td><td class="num">' . esc_html( pkw_rm( $a['akhir'] ) ) . '</td></tr>'; }
	echo '<tr class="hd grand"><th>Baki akhir tempoh</th><th class="num">' . esc_html( pkw_rm( $p['baki_akhir'] ) ) . '</th></tr></tbody></table>';
	if ( ! $p['selaras'] ) { echo '<p class="pkw-alert pkw-alert-err">Amaran: baki tidak selaras — semak tarikh baki awal akaun.</p>'; }
	echo '<footer class="pkw-sign"><div><span></span>Disediakan oleh<br>Bendahari</div><div><span></span>Disemak oleh<br>Juruaudit Dalaman</div><div><span></span>Disahkan oleh<br>Nazir</div></footer>';
	echo '<p class="pkw-muted pkw-gen">Dijana ' . esc_html( pkw_date_label( pkw_today() ) . ' ' . current_time( 'H:i' ) ) . ' oleh ' . esc_html( wp_get_current_user()->display_name ) . '.' . ( pkw_kariah_on() ? ' Termasuk bayaran khairat/korban yang disahkan dalam sistem Kariah mengikut tarikh bayaran.' : '' ) . '</p></article>';
}

/* ================================================================ AUDIT */

function pkw_tab_audit( $edit ) {
	$rows = pkw_log_list( 200 );
	echo '<p class="pkw-muted">200 tindakan terkini — setiap tambah, ubah, batal, eksport, tutup bulan dan perubahan tetapan direkod.</p>';
	if ( ! $rows ) { echo '<p class="pkw-empty">Tiada rekod.</p>'; return; }
	echo '<div class="pkw-scroll"><table class="pkw-table"><thead><tr><th>Masa</th><th>Pengguna</th><th>Tindakan</th><th>Transaksi</th><th>Butiran</th></tr></thead><tbody>';
	foreach ( $rows as $l ) {
		$u = get_userdata( $l['user_id'] );
		echo '<tr><td>' . esc_html( pkw_date_label( $l['created_at'] ) . ' ' . substr( $l['created_at'], 11, 5 ) ) . '</td><td>' . esc_html( $u ? $u->display_name : '—' ) . '</td><td>' . esc_html( $l['tindakan'] ) . '</td><td>' . ( $l['transaksi_id'] ? '#' . (int) $l['transaksi_id'] : '' ) . '</td><td>' . esc_html( $l['butiran'] ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

/* ================================================================ TETAPAN */

function pkw_tab_tetapan( $edit ) {
	if ( ! $edit ) { return; }
	$post = esc_url( admin_url( 'admin-post.php' ) );
	$s = pkw_settings();
	echo '<section class="pkw-card"><h3>Umum</h3><form method="post" action="' . $post . '" class="pkw-form">';
	wp_nonce_field( 'pkw_tetapan' );
	echo '<input type="hidden" name="action" value="pkw_tetapan"><input type="hidden" name="op" value="umum"><div class="pkw-row"><label>Nama masjid / surau (tajuk penyata) <input type="text" name="nama_masjid" maxlength="150" required value="' . esc_attr( $s['nama_masjid'] ) . '"></label><label>Ikon Font Awesome <select name="fontawesome">';
	foreach ( [ 'auto' => 'Auto (muat jika tema tiada)', 'cdn' => 'Sentiasa muat dari CDN', 'off' => 'Jangan muat' ] as $k => $l ) { echo '<option value="' . $k . '"' . selected( $s['fontawesome'], $k, false ) . '>' . $l . '</option>'; }
	echo '</select></label></div>';
	if ( current_user_can( 'manage_options' ) ) { echo '<label class="pkw-chk"><input type="checkbox" name="padam_data" value="1"' . checked( (int) $s['padam_data'], 1, false ) . '> Padam semua rekod kewangan apabila plugin <em>dipadam</em> (bukan nyahaktif)</label>'; }
	echo '<div><button class="btn btn-sm btn-outline-success">Simpan</button></div></form></section>';
	echo '<div class="pkw-grid2"><section class="pkw-card"><h3>Akaun &amp; baki awal</h3><p class="pkw-muted">Masukkan baki sebenar setiap akaun pada tarikh mula (cth. baki penyata bank pada 1 Januari). Semua laporan dikira dari tarikh ini.</p>';
	foreach ( array_merge( pkw_akaun(), [ [ 'id' => 0, 'nama' => '', 'butiran' => '', 'baki_awal' => '0.00', 'tarikh_mula' => current_time( 'Y' ) . '-01-01', 'aktif' => 1 ] ] ) as $a ) {
		echo '<form method="post" action="' . $post . '" class="pkw-form pkw-akaun">';
		wp_nonce_field( 'pkw_tetapan' );
		echo '<input type="hidden" name="action" value="pkw_tetapan"><input type="hidden" name="op" value="akaun_simpan"><input type="hidden" name="akaun_id" value="' . (int) $a['id'] . '">';
		echo '<div class="pkw-row"><label>' . ( $a['id'] ? 'Nama akaun' : '+ Akaun baharu' ) . ' <input type="text" name="nama" maxlength="120" value="' . esc_attr( $a['nama'] ) . '"' . ( $a['id'] ? ' required' : '' ) . '></label><label>No. akaun / catatan <input type="text" name="butiran" maxlength="190" value="' . esc_attr( $a['butiran'] ) . '"></label></div>';
		echo '<div class="pkw-row"><label>Baki awal (RM) <input type="text" name="baki_awal" inputmode="decimal" value="' . esc_attr( number_format( (float) $a['baki_awal'], 2, '.', '' ) ) . '"></label><label>Pada tarikh <input type="date" name="tarikh_mula" value="' . esc_attr( $a['tarikh_mula'] ) . '"></label>';
		if ( $a['id'] ) { echo '<label class="pkw-chk"><input type="checkbox" name="aktif" value="1"' . checked( (int) $a['aktif'], 1, false ) . '> Aktif</label>'; }
		echo '<button class="btn btn-sm btn-outline-success">Simpan</button></div></form>';
	}
	echo '</section><section class="pkw-card"><h3>Tutup bulan</h3><p class="pkw-muted">Selepas akaun bulan disemak dan diselaraskan dengan penyata bank, tutup bulan tersebut. Transaksinya dikunci dan penyata ditanda "Muktamad". Hanya pentadbir laman boleh membuka semula.</p>';
	echo '<form method="post" action="' . $post . '" class="pkw-row">';
	wp_nonce_field( 'pkw_tutup' );
	echo '<input type="hidden" name="action" value="pkw_tutup"><label>Bulan <input type="month" name="bulan" max="' . esc_attr( gmdate( 'Y-m', strtotime( current_time( 'Y-m' ) . '-01 -1 month' ) ) ) . '" required></label> <button class="btn btn-sm btn-warning">Tutup bulan</button></form>';
	$tt = pkw_tutup_list();
	if ( $tt ) {
		echo '<ul class="pkw-log">';
		foreach ( $tt as $t ) {
			$u = get_userdata( $t['ditutup_oleh'] );
			echo '<li><i class="fa-solid fa-lock"></i> <strong>' . esc_html( pkw_ym_label( $t['bulan'] ) ) . '</strong> — ' . esc_html( ( $u ? $u->display_name : '—' ) . ', ' . pkw_date_label( $t['ditutup_pada'] ) );
			if ( current_user_can( 'manage_options' ) ) {
				echo ' <form method="post" action="' . $post . '" class="pkw-inline">';
				wp_nonce_field( 'pkw_tutup' );
				echo '<input type="hidden" name="action" value="pkw_tutup"><input type="hidden" name="op" value="buka"><input type="hidden" name="bulan" value="' . esc_attr( $t['bulan'] ) . '"><button class="btn btn-link btn-sm">Buka semula</button></form>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
	if ( pkw_kariah_available() ) {
		$s = pkw_settings();
		echo '<h3>Integrasi sistem Kariah</h3><form method="post" action="' . $post . '" class="pkw-form">';
		wp_nonce_field( 'pkw_tetapan' );
		echo '<input type="hidden" name="action" value="pkw_tetapan"><input type="hidden" name="op" value="kariah"><label class="pkw-chk"><input type="checkbox" name="kariah_sync" value="1"' . checked( (int) $s['kariah_sync'], 1, false ) . '> Masukkan bayaran khairat &amp; korban yang disahkan sebagai penerimaan</label><label>Dimasukkan ke akaun <select name="kariah_akaun">';
		foreach ( pkw_akaun() as $a ) { echo '<option value="' . (int) $a['id'] . '"' . selected( pkw_kariah_akaun(), (int) $a['id'], false ) . '>' . esc_html( $a['nama'] ) . '</option>'; }
		echo '</select></label><p class="pkw-muted">Jika dihidupkan, jangan rekod bayaran tersebut sekali lagi di tab Transaksi (elak dikira dua kali).</p><button class="btn btn-sm btn-outline-success">Simpan</button></form>';
	}
	echo '</section></div>';

	echo '<section class="pkw-card"><h3>Kategori</h3><form method="post" action="' . $post . '" class="pkw-form">';
	wp_nonce_field( 'pkw_tetapan' );
	echo '<input type="hidden" name="action" value="pkw_tetapan"><input type="hidden" name="op" value="kat_simpan"><div class="pkw-grid2">';
	foreach ( [ 'masuk' => 'Penerimaan', 'keluar' => 'Pembayaran' ] as $j => $l ) {
		echo '<div><h4>' . $l . '</h4><table class="pkw-table pkw-kat"><thead><tr><th>Nama</th><th>Susunan</th><th>Aktif</th></tr></thead><tbody>';
		foreach ( pkw_kategori( $j ) as $k ) {
			$n = 'kat[' . (int) $k['id'] . ']';
			echo '<tr><td><input type="text" name="' . $n . '[nama]" maxlength="120" value="' . esc_attr( $k['nama'] ) . '"></td><td><input type="number" name="' . $n . '[susunan]" value="' . (int) $k['susunan'] . '"></td><td><input type="checkbox" name="' . $n . '[aktif]" value="1"' . checked( (int) $k['aktif'], 1, false ) . '></td></tr>';
		}
		echo '</tbody></table></div>';
	}
	echo '</div><button class="btn btn-sm btn-outline-success">Simpan kategori</button></form>';
	echo '<form method="post" action="' . $post . '" class="pkw-row pkw-addkat">';
	wp_nonce_field( 'pkw_tetapan' );
	echo '<input type="hidden" name="action" value="pkw_tetapan"><input type="hidden" name="op" value="kat_tambah"><label>Kategori baharu <input type="text" name="nama" maxlength="120" required></label><label>Jenis <select name="jenis"><option value="masuk">Penerimaan</option><option value="keluar">Pembayaran</option></select></label><button class="btn btn-sm btn-success">Tambah</button></form>';
	echo '<p class="pkw-muted">Kategori tidak dipadam supaya laporan lama kekal tepat — nyahaktifkan sahaja yang tidak digunakan lagi.</p></section>';

	echo '<section class="pkw-card"><h3>Siapa boleh akses</h3><ul>';
	foreach ( get_users( [ 'capability__in' => [ PKW_CAP_URUS, PKW_CAP_LIHAT ], 'fields' => [ 'ID', 'display_name' ] ] ) as $u ) {
		echo '<li>' . esc_html( $u->display_name ) . ' — ' . ( user_can( $u->ID, PKW_CAP_URUS ) ? 'Bendahari (rekod &amp; lihat)' : 'Lihat sahaja' ) . '</li>';
	}
	echo '</ul><p class="pkw-muted">Pentadbir laman menambah akses di wp-admin → Pengguna: peranan <strong>Bendahari</strong> untuk merekod, atau tanda <strong>"Akses lihat Laporan Kewangan"</strong> pada profil AJK (cth. Nazir, Setiausaha, Juruaudit) untuk lihat sahaja.</p></section>';
}
