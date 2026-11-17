<?php

declare(strict_types=1);

/*
 * Fungsi bantu formulir Service Report.
 *
 * Berkas ini ada di dalam folder yang dilayani web server, jadi dijaga supaya
 * tidak bisa dibuka langsung lewat browser.
 */
defined('SR_APP') || exit;

/** Escape untuk HTML. */
function e(mixed $nilai): string
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function muatKonfigurasi(): array
{
    $berkas = __DIR__ . '/config.php';

    if (! is_file($berkas)) {
        http_response_code(500);
        exit('config.php belum ada. Salin config.example.php menjadi config.php lalu isi api_url.');
    }

    return require $berkas;
}

/**
 * Alamat IP pelapor yang diteruskan ke API.
 *
 * API memakai IP ini untuk membatasi 5 kiriman per menit per perangkat dan
 * mencatatnya di kolom ip_pelapor. Tanpa diteruskan, semua pelapor terlihat
 * sebagai IP server ini dan berbagi SATU jatah kiriman.
 *
 * Header dari proxy (Cloudflare) hanya dipercaya bila diizinkan di
 * config.php: kalau situs ini tidak berada di belakang proxy, siapa pun bisa
 * mengisi header itu sendiri untuk menghindari pembatasan.
 */
function ipPelapor(array $konfig): string
{
    if (! empty($konfig['percaya_header_proxy'])) {
        $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if (filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }

        $xff = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')[0]);
        if (filter_var($xff, FILTER_VALIDATE_IP)) {
            return $xff;
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Kirim laporan ke mapsonapi: POST /api/service-report/publik.
 *
 * Penyimpanan, penomoran (NNN/SR/MM/YYYY), dan semua aturan validasi ada di
 * sana, bukan di sini — supaya tim teknis yang membuka menu Service Report
 * di aplikasi mobile melihat data yang lolos aturan yang sama.
 *
 * @return array{status: int, body: array}
 */
function kirimKeApi(array $konfig, array $data): array
{
    $url = rtrim((string) $konfig['api_url'], '/') . '/service-report/publik';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Forwarded-For: ' . ipPelapor($konfig),
        ],
    ]);

    $isi = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $galat = curl_error($ch);
    curl_close($ch);

    if ($isi === false) {
        error_log('Service report: gagal menghubungi API: ' . $galat);

        return ['status' => 0, 'body' => []];
    }

    $body = json_decode((string) $isi, true);

    return ['status' => $status, 'body' => is_array($body) ? $body : []];
}

/**
 * Ambil baris tabel dari POST. Selalu mengembalikan minimal satu baris
 * kosong supaya formulir tetap punya kotak isian.
 */
function barisDariPost(string $nama, array $kunci): array
{
    $hasil = [];

    foreach ((array) ($_POST[$nama] ?? []) as $b) {
        if (! is_array($b)) {
            continue;
        }

        $rapi = [];
        foreach ($kunci as $k) {
            $rapi[$k] = trim((string) ($b[$k] ?? ''));
        }
        $hasil[] = $rapi;
    }

    return $hasil ?: [array_fill_keys($kunci, '')];
}
