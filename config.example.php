<?php

/*
 * Salin berkas ini menjadi config.php, lalu sesuaikan isinya.
 * config.php tidak ikut ke git karena isinya berbeda di tiap mesin.
 */
return [
    // Alamat API mapsonapi, diakhiri /api, tanpa garis miring di belakang.
    //   Lokal (Herd) : http://mapsonapi.test/api
    //   Production   : https://mobile.mapsonarya.com/api
    'api_url' => 'http://mapsonapi.test/api',

    // true HANYA bila situs ini berada di belakang proxy (mis. Cloudflare).
    // Lihat ipPelapor() di lib.php.
    'percaya_header_proxy' => false,
];
