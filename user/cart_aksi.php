<?php
/**
 * DANATRAIL — Sistem Transaksi Langsung
 * Handler cart diarahkan langsung ke halaman konfirmasi pembelian (checkout.php).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$product_id = isset($_REQUEST['product_id']) ? (int)$_REQUEST['product_id'] : 0;
$quantity   = isset($_REQUEST['quantity']) ? (int)$_REQUEST['quantity'] : 1;
if ($quantity < 1) {
    $quantity = 1;
}

if ($product_id > 0) {
    header("Location: checkout.php?product_id=" . $product_id . "&quantity=" . $quantity);
    exit;
}

header("Location: dashboard.php?pesan=" . urlencode("DANATRAIL menggunakan sistem transaksi langsung tanpa keranjang."));
exit;
