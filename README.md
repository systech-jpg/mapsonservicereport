# mapsonservicereport

Formulir **Service Report / Maintenance Alat** (Form No. 007/01/TKS-MAP/F-03)
untuk pelapor tanpa akun. Dibuka dari kode QR, PHP 8.3 native, tanpa
framework dan tanpa database.

## Cara kerjanya

1. Pelapor memindai kode QR, lalu membuka halaman ini.
2. Pelapor mengisi formulir dan menekan **Kirim laporan**.
3. `index.php` meneruskan isiannya (server ke server, lewat cURL) ke
   `POST <api_url>/service-report/publik` milik **mapsonapi**.
4. mapsonapi memeriksa isian, menyimpannya ke tabel `service_reports`, lalu
   memberi nomor `NNN/SR/MM/YYYY`.
5. Tim teknis menindaklanjutinya dari menu **Service Report** di aplikasi
   mobile (mapsonapi).

Project ini sengaja tidak menyimpan apa pun dan tidak memeriksa aturan isian
sendiri. Aturan validasi dan pesan galatnya ada di
`mapsonapi/app/Http/Controllers/Api/ServiceReportController.php`, dan
ditampilkan di sini apa adanya. Kalau nama isian di API berubah, ubah juga
atribut `name` di `index.php`.

## Isi folder

| Berkas | Guna |
|---|---|
| `index.php` | halaman formulir sekaligus penerima kiriman |
| `lib.php` | fungsi bantu: kirim ke API, IP pelapor, escape HTML |
| `config.example.php` | contoh pengaturan; salin menjadi `config.php` |
| `assets/` | gaya, skrip tambah/hapus baris, logo |

## Penjagaan

- **Token CSRF** di session, supaya situs lain tidak bisa mengirim atas nama
  pengunjung.
- **Isian jebakan `situs`**, yang hanya diisi robot. API menolaknya.
- **Batas 5 kiriman per menit per IP**, dijalankan di API. IP pelapor
  diteruskan lewat header `X-Forwarded-For`. Kalau situs ini berada di
  belakang Cloudflare, isi `percaya_header_proxy` dengan `true`; kalau tidak,
  semua pelapor terlihat sebagai satu IP milik Cloudflare.
- Sesudah berhasil, halaman dialihkan ke `?selesai=1`, sehingga menekan muat
  ulang tidak mengirim laporan yang sama dua kali.

## Pasang di lokal (Herd)

1. Salin pengaturan:
   ```
   copy config.example.php config.php
   ```
2. Pastikan `api_url` di `config.php` berisi `http://mapsonapi.test/api`.
3. Pastikan tabel sudah ada di mapsonapi:
   ```
   cd C:\Users\USER\Herd\mapsonapi
   php artisan migrate
   ```
4. Buka `http://mapsonservicereport.test/`.

## Pasang di server

1. Arahkan subdomain baru ke folder ini. Document root-nya folder ini sendiri,
   tidak ada folder `public/`.
2. Ambil kodenya:
   ```
   git clone git@github.com:systech-jpg/mapsonservicereport.git
   ```
3. Buat pengaturan:
   ```
   cp config.example.php config.php
   ```
4. Ubah `api_url` di `config.php` menjadi alamat API production, misalnya
   `https://mobile.mapsonarya.com/api`.
5. Pastikan ekstensi PHP `curl` aktif:
   ```
   php -m | grep curl
   ```
6. Pastikan mapsonapi di server sudah menjalankan `php artisan migrate`
   (tabel `service_reports`).
7. Buat kode QR dari alamat subdomain ini.
