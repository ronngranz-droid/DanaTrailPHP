<?php
/**
 * DANATRAIL — Sistem Transaksi Langsung
 * Fitur keranjang dinonaktifkan sesuai konsep transaksi langsung DANATRAIL.
 * Pembelian dilakukan langsung melalui tombol 'Beli Sekarang' ke halaman konfirmasi checkout.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Location: dashboard.php?pesan=" . urlencode("DANATRAIL menggunakan sistem transaksi langsung. Silakan pilih perlengkapan dan klik 'Beli Sekarang' atau 'Sewa Sekarang'."));
exit;
