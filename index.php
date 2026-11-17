<?php

declare(strict_types=1);

/*
 * Formulir Service Report / Maintenance Alat (007/01/TKS-MAP/F-03).
 *
 * Dibuka pelapor TANPA login dari kode QR. Halaman ini tidak menyimpan apa
 * pun sendiri: kirimannya diteruskan ke mapsonapi (lib.php → kirimKeApi()),
 * lalu tim teknis menindaklanjutinya dari menu Service Report di aplikasi
 * mobile.
 */
const SR_APP = true;

require __DIR__ . '/lib.php';

$konfig = muatKonfigurasi();

const JENIS = [
    'installation' => 'Installation',
    'service' => 'Service',
    'pm' => 'PM',
    'training' => 'Training',
];

const KOLOM_PRODUK = ['description', 'type', 'nie', 'sn', 'sw_version'];
const KOLOM_PERIKSA = ['tahap', 'solusi'];

$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $https,
]);

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$lama = [];
$galat = null;
$galatIsian = [];
$terkirim = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama = [
        'jenis' => array_values(array_intersect((array) ($_POST['jenis'] ?? []), array_keys(JENIS))),
        'customer_nama' => trim((string) ($_POST['customer_nama'] ?? '')),
        'customer_alamat' => trim((string) ($_POST['customer_alamat'] ?? '')),
        'department' => trim((string) ($_POST['department'] ?? '')),
        'position' => trim((string) ($_POST['position'] ?? '')),
        'customer_ref' => trim((string) ($_POST['customer_ref'] ?? '')),
        'telepon' => trim((string) ($_POST['telepon'] ?? '')),
        'contact_person' => trim((string) ($_POST['contact_person'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'tanggal_mulai' => trim((string) ($_POST['tanggal_mulai'] ?? '')) ?: null,
        'tanggal_selesai' => trim((string) ($_POST['tanggal_selesai'] ?? '')) ?: null,
        'produk' => barisDariPost('produk', KOLOM_PRODUK),
        'pemeriksaan' => barisDariPost('pemeriksaan', KOLOM_PERIKSA),
        'nama_engineer' => trim((string) ($_POST['nama_engineer'] ?? '')),
        'nama_customer' => trim((string) ($_POST['nama_customer'] ?? '')),
        // Isian jebakan robot; diteruskan apa adanya, API yang menolak.
        'situs' => (string) ($_POST['situs'] ?? ''),
    ];

    if (! hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $galat = 'Sesi formulir sudah kedaluwarsa. Tekan Kirim sekali lagi.';
    } else {
        $hasil = kirimKeApi($konfig, $lama);
        $body = $hasil['body'];

        if ($hasil['status'] === 201) {
            // Pola kirim → alihkan → tampilkan: tanpa pengalihan, menekan
            // muat ulang di layar terima kasih mengirim laporan yang sama lagi.
            $_SESSION['terkirim'] = (string) ($body['data']['no_report'] ?? '-');
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: ?selesai=1', true, 303);
            exit;
        }

        $galat = match (true) {
            $hasil['status'] === 0 => 'Gagal menghubungi server. Periksa sinyal lalu coba lagi.',
            $hasil['status'] === 429 => 'Terlalu banyak kiriman dari perangkat ini. Tunggu sebentar lalu coba lagi.',
            default => (string) ($body['message'] ?? 'Laporan gagal dikirim. Coba lagi.'),
        };

        if ($hasil['status'] === 422 && is_array($body['errors'] ?? null)) {
            $galatIsian = $body['errors'];
        }
    }
} elseif (isset($_GET['selesai'], $_SESSION['terkirim'])) {
    $terkirim = $_SESSION['terkirim'];
    unset($_SESSION['terkirim']);
}

$produk = $lama['produk'] ?? [array_fill_keys(KOLOM_PRODUK, '')];
$pemeriksaan = $lama['pemeriksaan'] ?? [array_fill_keys(KOLOM_PERIKSA, '')];

/** Nilai lama sebuah isian, sudah di-escape. */
$v = fn (string $k): string => e($lama[$k] ?? '');

/** Pesan galat API untuk satu isian (kuncinya mengikuti Laravel: "pemeriksaan.0.tahap"). */
$salah = fn (string $k): ?string => $galatIsian[$k][0] ?? null;

$kelasSalah = fn (string $k): string => isset($galatIsian[$k]) ? ' is-invalid' : '';

$versi = fn (string $berkas): string => $berkas . '?v=' . (string) @filemtime(__DIR__ . '/' . $berkas);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
  <meta name="theme-color" content="#BC9E68">
  <meta name="robots" content="noindex">
  <title>Service Report — Mapson Arya Parahita</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= e($versi('assets/style.css')) ?>" rel="stylesheet">
</head>
<body>
  <header class="sr-kepala">
    <img src="<?= e($versi('assets/logo.png')) ?>" alt="Mapson Arya Parahita">
    <h1>Formulir Service Report / Maintenance Alat</h1>
    <p>PT. Mapson Arya Parahita · Form No. 007/01/TKS-MAP/F-03</p>
  </header>

  <main class="px-3 mx-auto sr-isi">
  <?php if ($terkirim !== null): ?>
    <div class="sr-kartu text-center py-4">
      <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
      <div class="fw-bold fs-5 mt-2">Laporan terkirim</div>
      <div class="text-secondary">Nomor laporan Anda</div>
      <div class="fw-bold fs-4 my-2"><?= e($terkirim) ?></div>
      <p class="text-secondary small mb-3">
        Tim teknis PT. Mapson Arya Parahita akan menindaklanjuti laporan ini dan
        menghubungi contact person yang Anda cantumkan. Simpan nomor di atas untuk
        menanyakan perkembangannya.
      </p>
      <a href="./" class="btn btn-outline-emas">Buat laporan lain</a>
    </div>
  <?php else: ?>
    <form method="post" action="./" id="form-sr">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

      <?php if ($galat): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-octagon-fill"></i>
          <span class="small"><?= e($galat) ?></span>
        </div>
      <?php endif; ?>

      <div class="sr-kartu">
        <div class="sr-judul sr-wajib">Jenis pekerjaan</div>
        <div class="sr-jenis">
          <?php foreach (JENIS as $kode => $label): ?>
            <label>
              <input type="checkbox" class="form-check-input m-0" name="jenis[]" value="<?= e($kode) ?>"
                     <?= in_array($kode, $lama['jenis'] ?? [], true) ? 'checked' : '' ?>>
              <span><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if ($salah('jenis')): ?><div class="text-danger small mt-2"><?= e($salah('jenis')) ?></div><?php endif; ?>
      </div>

      <div class="sr-kartu">
        <div class="sr-judul">A. Customer</div>

        <label class="form-label small fw-semibold sr-wajib" for="f-nama">Name Customer</label>
        <input type="text" id="f-nama" name="customer_nama" class="form-control<?= $kelasSalah('customer_nama') ?>"
               value="<?= $v('customer_nama') ?>" maxlength="150" placeholder="Nama rumah sakit / instansi" required>
        <?php if ($salah('customer_nama')): ?><div class="text-danger small"><?= e($salah('customer_nama')) ?></div><?php endif; ?>

        <label class="form-label small fw-semibold mt-3" for="f-alamat">Address</label>
        <textarea id="f-alamat" name="customer_alamat" class="form-control" rows="2" maxlength="500"><?= $v('customer_alamat') ?></textarea>
      </div>

      <div class="sr-kartu">
        <div class="sr-judul">B. Room ID</div>

        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-dept">Department</label>
            <input type="text" id="f-dept" name="department" class="form-control" value="<?= $v('department') ?>" maxlength="100">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-pos">Position</label>
            <input type="text" id="f-pos" name="position" class="form-control" value="<?= $v('position') ?>" maxlength="100">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-ref">Customer Ref</label>
            <input type="text" id="f-ref" name="customer_ref" class="form-control" value="<?= $v('customer_ref') ?>" maxlength="100">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold sr-wajib" for="f-telp">Tlp No.</label>
            <input type="tel" id="f-telp" name="telepon" class="form-control<?= $kelasSalah('telepon') ?>"
                   value="<?= $v('telepon') ?>" maxlength="50" inputmode="tel" required>
            <?php if ($salah('telepon')): ?><div class="text-danger small"><?= e($salah('telepon')) ?></div><?php endif; ?>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold sr-wajib" for="f-cp">Contact Person</label>
            <input type="text" id="f-cp" name="contact_person" class="form-control<?= $kelasSalah('contact_person') ?>"
                   value="<?= $v('contact_person') ?>" maxlength="100" required>
            <?php if ($salah('contact_person')): ?><div class="text-danger small"><?= e($salah('contact_person')) ?></div><?php endif; ?>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-email">Email</label>
            <input type="email" id="f-email" name="email" class="form-control<?= $kelasSalah('email') ?>"
                   value="<?= $v('email') ?>" maxlength="150" inputmode="email" autocapitalize="none">
            <?php if ($salah('email')): ?><div class="text-danger small"><?= e($salah('email')) ?></div><?php endif; ?>
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold" for="f-mulai">Date Service Begin</label>
            <input type="date" id="f-mulai" name="tanggal_mulai" class="form-control" value="<?= $v('tanggal_mulai') ?>">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold" for="f-selesai">Date End of Service</label>
            <input type="date" id="f-selesai" name="tanggal_selesai" class="form-control<?= $kelasSalah('tanggal_selesai') ?>"
                   value="<?= $v('tanggal_selesai') ?>">
          </div>
          <?php if ($salah('tanggal_selesai')): ?><div class="col-12 text-danger small"><?= e($salah('tanggal_selesai')) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="sr-kartu">
        <div class="sr-judul">C. Product Survey</div>

        <div data-daftar="produk">
          <?php foreach ($produk as $i => $p): ?>
            <div class="sr-baris" data-baris>
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold">Alat <span data-nomor><?= $i + 1 ?></span></span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-hapus><i class="bi bi-trash"></i> Hapus</button>
              </div>
              <input type="text" class="form-control form-control-sm mb-2" placeholder="Description"
                     name="produk[<?= $i ?>][description]" value="<?= e($p['description']) ?>" maxlength="200">
              <div class="row g-2">
                <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="Type" name="produk[<?= $i ?>][type]" value="<?= e($p['type']) ?>" maxlength="100"></div>
                <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="NIE (Unit)" name="produk[<?= $i ?>][nie]" value="<?= e($p['nie']) ?>" maxlength="100"></div>
                <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="No. SN" name="produk[<?= $i ?>][sn]" value="<?= e($p['sn']) ?>" maxlength="100"></div>
                <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="S.W Version" name="produk[<?= $i ?>][sw_version]" value="<?= e($p['sw_version']) ?>" maxlength="100"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-sm btn-outline-emas" data-tambah="produk">
          <i class="bi bi-plus-lg"></i> Tambah alat
        </button>
      </div>

      <div class="sr-kartu">
        <div class="sr-judul">D. Problem Identification</div>

        <div data-daftar="pemeriksaan">
          <?php foreach ($pemeriksaan as $i => $b): ?>
            <div class="sr-baris" data-baris>
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="small fw-semibold">No. <span data-nomor><?= $i + 1 ?></span></span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-hapus><i class="bi bi-trash"></i> Hapus</button>
              </div>
              <textarea class="form-control form-control-sm mb-2<?= $kelasSalah("pemeriksaan.$i.tahap") ?>" rows="3"
                        placeholder="Tahap pemeriksaan / keluhan yang ditemukan"
                        name="pemeriksaan[<?= $i ?>][tahap]" maxlength="2000"><?= e($b['tahap']) ?></textarea>
              <textarea class="form-control form-control-sm" rows="2" placeholder="Solusi / rekomendasi (boleh dikosongkan)"
                        name="pemeriksaan[<?= $i ?>][solusi]" maxlength="2000"><?= e($b['solusi']) ?></textarea>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($salah('pemeriksaan')): ?><div class="text-danger small mb-2"><?= e($salah('pemeriksaan')) ?></div><?php endif; ?>
        <div class="text-secondary small mb-2">Isi minimal satu baris keluhan / tahap pemeriksaan.</div>

        <button type="button" class="btn btn-sm btn-outline-emas" data-tambah="pemeriksaan">
          <i class="bi bi-plus-lg"></i> Tambah baris
        </button>
      </div>

      <div class="sr-kartu">
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-eng">Engineering PT. Mapson Arya Parahita — Name</label>
            <input type="text" id="f-eng" name="nama_engineer" class="form-control" value="<?= $v('nama_engineer') ?>" maxlength="100">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold" for="f-cust">Customer — Name</label>
            <input type="text" id="f-cust" name="nama_customer" class="form-control" value="<?= $v('nama_customer') ?>" maxlength="100">
          </div>
        </div>
      </div>

      <!-- Isian jebakan: tidak terlihat dan tidak bisa dijangkau Tab, jadi hanya robot yang mengisinya. -->
      <div class="sr-jebakan" aria-hidden="true">
        <label for="f-situs">Situs</label>
        <input type="text" id="f-situs" name="situs" tabindex="-1" autocomplete="off">
      </div>

      <button type="submit" class="btn btn-emas w-100 py-2 mb-4" id="tombol-kirim">
        <span class="spinner-border spinner-border-sm align-middle me-2 d-none" id="putar-kirim" aria-hidden="true"></span>
        Kirim laporan
      </button>
    </form>

    <!-- Cetakan baris baru. __i__ diganti nomor urut oleh assets/form.js. -->
    <template id="cetak-produk">
      <div class="sr-baris" data-baris>
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="small fw-semibold">Alat <span data-nomor></span></span>
          <button type="button" class="btn btn-sm btn-link text-danger p-0" data-hapus><i class="bi bi-trash"></i> Hapus</button>
        </div>
        <input type="text" class="form-control form-control-sm mb-2" placeholder="Description" name="produk[__i__][description]" maxlength="200">
        <div class="row g-2">
          <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="Type" name="produk[__i__][type]" maxlength="100"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="NIE (Unit)" name="produk[__i__][nie]" maxlength="100"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="No. SN" name="produk[__i__][sn]" maxlength="100"></div>
          <div class="col-6"><input type="text" class="form-control form-control-sm" placeholder="S.W Version" name="produk[__i__][sw_version]" maxlength="100"></div>
        </div>
      </div>
    </template>

    <template id="cetak-pemeriksaan">
      <div class="sr-baris" data-baris>
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="small fw-semibold">No. <span data-nomor></span></span>
          <button type="button" class="btn btn-sm btn-link text-danger p-0" data-hapus><i class="bi bi-trash"></i> Hapus</button>
        </div>
        <textarea class="form-control form-control-sm mb-2" rows="3" placeholder="Tahap pemeriksaan / keluhan yang ditemukan" name="pemeriksaan[__i__][tahap]" maxlength="2000"></textarea>
        <textarea class="form-control form-control-sm" rows="2" placeholder="Solusi / rekomendasi (boleh dikosongkan)" name="pemeriksaan[__i__][solusi]" maxlength="2000"></textarea>
      </div>
    </template>

    <script src="<?= e($versi('assets/form.js')) ?>"></script>
  <?php endif; ?>
  </main>
</body>
</html>
