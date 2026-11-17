// Tambah/hapus baris tabel (C. Product Survey dan D. Problem Identification),
// dan cegah formulir terkirim dua kali karena tombol ditekan berulang.
(function () {
  'use strict';

  var MAKS_BARIS = 20; // sama dengan batas di API

  // Nomor indeks baris baru harus unik. Indeksnya tidak harus berurutan —
  // server merapikannya — tapi dua baris berindeks sama akan saling menimpa.
  var berikutnya = 1000;

  function nomori(daftar) {
    daftar.querySelectorAll('[data-baris]').forEach(function (baris, i) {
      var n = baris.querySelector('[data-nomor]');
      if (n) n.textContent = i + 1;
    });
  }

  document.querySelectorAll('[data-tambah]').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
      var nama = tombol.getAttribute('data-tambah');
      var daftar = document.querySelector('[data-daftar="' + nama + '"]');
      var cetak = document.getElementById('cetak-' + nama);

      if (daftar.querySelectorAll('[data-baris]').length >= MAKS_BARIS) return;

      var html = cetak.innerHTML.replace(/__i__/g, String(berikutnya++));
      daftar.insertAdjacentHTML('beforeend', html);
      nomori(daftar);

      var isian = daftar.lastElementChild.querySelector('input, textarea');
      if (isian) isian.focus();
    });
  });

  document.addEventListener('click', function (e) {
    var hapus = e.target.closest('[data-hapus]');
    if (!hapus) return;

    var baris = hapus.closest('[data-baris]');
    var daftar = baris.parentElement;

    if (daftar.querySelectorAll('[data-baris]').length <= 1) return;

    baris.remove();
    nomori(daftar);
  });

  var form = document.getElementById('form-sr');
  if (form) {
    form.addEventListener('submit', function () {
      var tombol = document.getElementById('tombol-kirim');
      tombol.disabled = true;
      document.getElementById('putar-kirim').classList.remove('d-none');
    });

    // Kembali dengan tombol Back bisa memulihkan halaman dari cache browser
    // dalam keadaan tombol masih terkunci.
    window.addEventListener('pageshow', function () {
      document.getElementById('tombol-kirim').disabled = false;
      document.getElementById('putar-kirim').classList.add('d-none');
    });
  }
})();
