<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wajib login sebelum memproses/memverifikasi callback pembayaran
if (!isset($_SESSION['role'])) {
    header("location:../login.php?pesan=" . urlencode("Silakan login terlebih dahulu!"));
    exit;
}

include '../config/config.php';
include '../config/midtrans.php';
/** @var mysqli $conn */

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0 && !empty($_SESSION['nama'])) {
    $nama_s = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $u_res  = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama_s' LIMIT 1");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $user_id = (int)mysqli_fetch_assoc($u_res)['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

$rental_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['rental_id']) ? (int)$_POST['rental_id'] : 0);
$order_id  = isset($_GET['order_id']) ? trim((string)$_GET['order_id']) : '';
$status    = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$pay_type  = isset($_GET['payment_type']) ? trim((string)$_GET['payment_type']) : 'Midtrans Online';

if ($rental_id <= 0) {
    header("location:../user/riwayat.php?pesan=" . urlencode("ID transaksi tidak valid."));
    exit;
}

// Verifikasi kepemilikan transaksi (kecuali admin)
$where_owner = ($_SESSION['role'] === 'admin') ? "" : " AND user_id = '$user_id'";
$q_rental = mysqli_query($conn, "SELECT id, user_id, product_id, quantity, status, payment_status, snap_token, midtrans_order_id FROM rentals WHERE id = '$rental_id' $where_owner LIMIT 1");

if (!$q_rental || mysqli_num_rows($q_rental) === 0) {
    header("location:../user/riwayat.php?pesan=" . urlencode("Data transaksi tidak ditemukan atau akses ditolak."));
    exit;
}

$rental = mysqli_fetch_assoc($q_rental);

if ($rental['payment_status'] === 'Lunas') {
    header("location:../user/riwayat.php?sukses=" . urlencode("Pembayaran transaksi sudah terkonfirmasi LUNAS."));
    exit;
}

// 1. MODE REAL MIDTRANS (Sandbox/Production): Wajib verifikasi langsung ke API Midtrans
if (!midtrans_is_simulator()) {
    $midtrans_oid = !empty($rental['midtrans_order_id']) ? $rental['midtrans_order_id'] : $order_id;
    $api_res = midtrans_get_transaction_status($midtrans_oid);

    if (!$api_res['success'] || empty($api_res['data'])) {
        header("location:../user/riwayat.php?pesan=" . urlencode("Pembayaran belum terkonfirmasi di Midtrans. Silakan selesaikan pembayaran atau cek kembali beberapa saat lagi."));
        exit;
    }

    $tx_data = $api_res['data'];
    $mapped_status = midtrans_map_transaction_status(
        $tx_data['transaction_status'] ?? '',
        $tx_data['fraud_status'] ?? ''
    );
    $actual_pay_type = $tx_data['payment_type'] ?? $pay_type;

    if ($mapped_status !== null) {
        midtrans_apply_rental_payment_status($conn, $rental_id, $mapped_status, 'Midtrans (' . $actual_pay_type . ')');
    }

    if ($mapped_status === 'Lunas') {
        header("location:../user/riwayat.php?sukses=" . urlencode("Pembayaran online terverifikasi LUNAS oleh Midtrans!"));
    } elseif ($mapped_status === 'Pembayaran Ditolak') {
        header("location:../user/riwayat.php?pesan=" . urlencode("Pembayaran dibatalkan, ditolak, atau kedaluwarsa."));
    } else {
        header("location:../user/riwayat.php?pesan=" . urlencode("Pembayaran Anda sedang menunggu verifikasi/penyelesaian."));
    }
    exit;
}

// 2. MODE SIMULATOR (Placeholder Keys): Wajib ada token simulator yang valid dari get_snap_token.php
if (strpos($rental['snap_token'] ?? '', 'MOCK-SNAP-') !== 0) {
    header("location:../user/riwayat.php?pesan=" . urlencode("Sesi pembayaran simulator tidak valid. Silakan klik tombol Bayar terlebih dahulu."));
    exit;
}

if ($status === 'success' || $status === 'settlement') {
    midtrans_apply_rental_payment_status($conn, $rental_id, 'Lunas', 'Online (' . $pay_type . ')');
    header("location:../user/riwayat.php?sukses=" . urlencode("Pembayaran online berhasil! Pesanan Anda telah berstatus LUNAS dan otomatis dikonfirmasi."));
    exit;
}

if ($status === 'pending') {
    midtrans_apply_rental_payment_status($conn, $rental_id, 'Menunggu Pembayaran', 'Online (' . $pay_type . ')');
    header("location:../user/riwayat.php?pesan=" . urlencode("Pembayaran Anda sedang diproses. Silakan selesaikan pembayaran."));
    exit;
}

header("location:../user/riwayat.php");
exit;
?>