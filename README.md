# Kewangan Masjid

Plugin WordPress untuk **Bendahari masjid atau surau** — rekod penerimaan & pembayaran, baki akaun bank/tunai, dan **Penyata Penerimaan & Pembayaran** bulanan/tahunan yang boleh dicetak, di satu halaman dalaman yang hanya boleh dibuka oleh Bendahari dan AJK yang diberi kebenaran.

*A WordPress plugin for Malaysian mosques and suraus: a simple cash-basis ledger (income, expenses, transfers between bank and petty cash), live account balances, a printable monthly/yearly Receipts & Payments statement, month-end close and a full audit trail — on a private front-end page for the treasurer and authorised committee members. UI is in Bahasa Melayu.*

## Ciri-ciri

**Untuk Bendahari**
- Rekod **penerimaan**, **pembayaran** dan **pindahan antara akaun** (cth. masukkan kutipan tabung tunai ke bank)
- Lampirkan **dokumen sokongan** (resit, bil, baucar — JPG/PNG/PDF) pada setiap transaksi
- Kategori sedia ada (tabung Jumaat, infaq, sewa dewan, geran; elaun imam/bilal/siak, bil elektrik & air, program, penyelenggaraan, khairat, korban, asnaf…) — boleh tambah, ubah nama, susun dan nyahaktif
- Berbilang akaun (bank, tunai, akaun khas) dengan baki awal pada tarikh mula

**Laporan**
- **Ringkasan**: baki semasa, penerimaan & pembayaran bulan ini, lebihan/kurangan tahun ini, carta bulanan, kategori utama
- **Penyata Penerimaan & Pembayaran** — bulanan, tahunan atau julat tarikh: baki awal, penerimaan & pembayaran mengikut kategori, lebihan/(kurangan), baki akhir setiap akaun, ruang tandatangan Bendahari / Juruaudit Dalaman / Nazir
- Cetak / simpan PDF terus dari pelayar, eksport **CSV** (penyata dan senarai transaksi)

**Kawalan**
- **Tutup bulan** — selepas diselaraskan dengan penyata bank, bulan dikunci (tiada tambah/ubah/batal) dan penyata ditanda *Muktamad*. Hanya pentadbir laman boleh membuka semula.
- Transaksi **tidak dipadam** — hanya dibatalkan dengan sebab
- **Jejak audit**: setiap tambah, ubah (nilai lama → baharu), batal, eksport, tutup bulan dan perubahan akses direkod bersama pengguna & IP
- Dua tahap akses: **Bendahari** (rekod & urus) dan **Lihat sahaja** (cth. Nazir, Setiausaha, juruaudit)

**Keselamatan**
- Halaman `noindex`, tidak dicache, disembunyikan dari carian, sitemap dan menu automatik
- Dokumen sokongan disimpan di luar Media Library (`uploads/pk-private/kewangan/`) dan hanya dihantar melalui pautan bernonce kepada pengguna yang dibenarkan
- Semakan kandungan fail, nonce & semakan kebenaran pada setiap tindakan, perlindungan formula CSV, mesej status melalui token pelayan

**Integrasi dengan [Kariah & Khairat Masjid](https://github.com/mohdneotech/kariah-khairat)** *(pilihan)*
- Bayaran khairat & korban yang telah **disahkan** dalam plugin Kariah dimasukkan secara automatik sebagai penerimaan (mengikut tarikh bayaran) — tidak perlu direkod dua kali

## Pemasangan

1. Muat turun **`kewangan-masjid.zip`** dari halaman [Releases](../../releases/latest).
2. wp-admin → **Plugins → Add New → Upload Plugin** → pilih zip → **Install Now** → **Activate**.
3. Halaman **Laporan Kewangan** (`/laporan-kewangan/`) dicipta secara automatik.
4. Buka halaman itu → tab **Tetapan**:
   - isi **nama masjid/surau** (tajuk penyata)
   - kemas kini **akaun bank & tunai** dan masukkan **baki awal** sebenar pada tarikh mula (cth. baki penyata bank pada 1 Januari)
5. wp-admin → **Pengguna** → sunting pengguna:
   - beri peranan **Bendahari**, *atau*
   - di bahagian **Laporan Kewangan** pilih *Lihat sahaja* / *Bendahari* untuk AJK sedia ada

> Disyorkan: wajibkan **2FA** untuk peranan Bendahari dan pentadbir (cth. Wordfence Login Security atau Two-Factor).

### Pelayan nginx / Caddy

`.htaccess` di `uploads/pk-private/` hanya berkesan pada Apache. Pada nginx atau Caddy, sekat folder ini:

```nginx
location ^~ /wp-content/uploads/pk-private/ { deny all; }
```

```caddy
@pkprivate path /wp-content/uploads/pk-private/*
respond @pkprivate 403
```

## Cara guna (ringkas)

| Tugas | Di mana |
|---|---|
| Rekod kutipan tabung Jumaat | Ringkasan → **Rekod penerimaan** |
| Bayar bil elektrik dari bank | Ringkasan → **Rekod pembayaran** |
| Masukkan tunai ke bank | **Pindahan antara akaun** (tidak dikira sebagai penerimaan/pembayaran) |
| Penyata bulanan untuk mesyuarat AJK | **Penyata** → Bulanan → **Cetak / PDF** |
| Penyata tahunan untuk mesyuarat agung | **Penyata** → Tahunan |
| Kunci bulan yang telah disemak | **Tetapan** → **Tutup bulan** |

## Kemas kini

Plugin menyemak GitHub Releases dan kemas kini muncul seperti plugin biasa di wp-admin → **Plugins**. Untuk mematikan:

```php
add_filter( 'pkw_github_updates', '__return_false' );
```

## Nyahpasang

Nyahaktif tidak memadam apa-apa. Rekod kewangan hanya dipadam apabila plugin **dipadam** *dan* pilihan "Padam semua rekod kewangan…" ditanda di Tetapan.

## Keperluan

WordPress 6.4+, PHP 8.0+, MySQL/MariaDB. Diuji pada WordPress 7.1 dengan tema blok Twenty Twenty-Five dan tema klasik berasaskan Bootstrap.

## Lesen

GPL-2.0-or-later © Mohd Nordin Hussain — [mohdneotech.com](https://mohdneotech.com)
