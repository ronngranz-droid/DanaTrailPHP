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

$tx_type   = (isset($_GET['type']) && strtolower(trim((string)$_GET['type'])) === 'order') ? 'order' : 'rental';
$tx_id     = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['rental_id']) ? (int)$_POST['rental_id'] : 0);
$order_id  = isset($_GET['order_id']) ? trim((string)$_GET['order_id']) : '';
$status    = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$pay_type  = isset($_GET['payment_type']) ? trim((string)$_GET['payment_type']) : 'Midtrans Online';

$redirect_base = ($tx_type === 'order') ? '../user/pesanan.php' : '../user/riwayat.php';

// Jika tx_id tidak disertakan tetapi order_id dari Midtrans ada, cari ID transaksi berdasarkan order_id
if ($tx_id <= 0 && !empty($order_id)) {
    $safe_oid = mysqli_real_escape_string($conn, $order_id);
    $parts = explode('-', $order_id);
    $base_code = $parts[0] . '-' . ($parts[1] ?? '') . '-' . ($parts[2] ?? '');
    $safe_bc = mysqli_real_escape_string($conn, $base_code);

    // Cek di rentals
    $chk_rent = mysqli_query($conn, "SELECT id FROM rentals WHERE midtrans_order_id = '$safe_oid' OR rental_code = '$safe_oid' OR rental_code = '$safe_bc' LIMIT 1");
    if ($chk_rent && $row_r = mysqli_fetch_assoc($chk_rent)) {
        $tx_type = 'rental';
        $tx_id   = (int)$row_r['id'];
        $redirect_base = '../user/riwayat.php';
    } else {
        // Cek di orders
        $chk_ord = mysqli_query($conn, "SELECT id FROM orders WHERE midtrans_order_id = '$safe_oid' OR order_code = '$safe_oid' OR order_code = '$safe_bc' LIMIT 1");
        if ($chk_ord && $row_o = mysqli_fetch_assoc($chk_ord)) {
            $tx_type = 'order';
            $tx_id   = (int)$row_o['id'];
            $redirect_base = '../user/pesanan.php';
        }
    }
}

if ($tx_id <= 0) {
    header("location:$redirect_base?pesan=" . urlencode("ID transaksi tidak valid."));
    exit;
}

$table_name  = ($tx_type === 'order') ? 'orders' : 'rentals';
$where_owner = ($_SESSION['role'] === 'admin') ? "" : " AND user_id = '$user_id'";
$q_tx = mysqli_query($conn, "SELECT id, user_id, status, payment_status, snap_token, midtrans_order_id FROM `$table_name` WHERE id = '$tx_id' $where_owner LIMIT 1");

if (!$q_tx || mysqli_num_rows($q_tx) === 0) {
    header("location:$redirect_base?pesan=" . urlencode("Data transaksi tidak ditemukan atau akses ditolak."));
    exit;
}

$tx = mysqli_fetch_assoc($q_tx);

if (($tx['payment_status'] ?? '') === 'Lunas') {
    header("location:$redirect_base?sukses=" . urlencode("Pembayaran transaksi sudah terkonfirmasi LUNAS."));
    exit;
}

$apply_fn = ($tx_type === 'order') ? 'midtrans_apply_order_payment_status' : 'midtrans_apply_rental_payment_status';

// 1. MODE REAL MIDTRANS (Sandbox/Production): Wajib verifikasi langsung ke API Midtrans
if (!midtrans_is_simulator()) {
    $midtrans_oid = !empty($tx['midtrans_order_id']) ? $tx['midtrans_order_id'] : $order_id;
    $api_res = midtrans_get_transaction_status($midtrans_oid);

    if (!$api_res['success'] || empty($api_res['data'])) {
        header("location:$redirect_base?pesan=" . urlencode("Pembayaran belum terkonfirmasi di Midtrans. Silakan selesaikan pembayaran atau cek kembali beberapa saat lagi."));
        exit;
    }

    $tx_data = $api_res['data'];
    $mapped_status = midtrans_map_transaction_status(
        $tx_data['transaction_status'] ?? '',
        $tx_data['fraud_status'] ?? ''
    );
    $actual_pay_type = $tx_data['payment_type'] ?? $pay_type;

    if ($mapped_status !== null) {
        $apply_fn($conn, $tx_id, $mapped_status, 'Midtrans (' . $actual_pay_type . ')');
    }

    if ($mapped_status === 'Lunas') {
        $msg = ($tx_type === 'order')
            ? "Pembayaran pesanan toko terverifikasi LUNAS oleh Midtrans! Pesanan segera diproses."
            : "Pembayaran sewa online terverifikasi LUNAS oleh Midtrans!";
        header("location:$redirect_base?sukses=" . urlencode($msg));
    } elseif ($mapped_status === 'Pembayaran Ditolak') {
        header("location:$redirect_base?pesan=" . urlencode("Pembayaran dibatalkan, ditolak, atau kedaluwarsa."));
    } else {
        header("location:$redirect_base?pesan=" . urlencode("Pembayaran Anda sedang menunggu verifikasi/penyelesaian."));
    }
    exit;
}

// 2. MODE SIMULATOR (Placeholder Keys): Wajib ada token simulator yang valid dari get_snap_token.php
if (strpos($tx['snap_token'] ?? '', 'MOCK-SNAP-') !== 0) {
    header("location:$redirect_base?pesan=" . urlencode("Sesi pembayaran simulator tidak valid. Silakan klik tombol Bayar terlebih dahulu."));
    exit;
}

if ($status === 'success' || $status === 'settlement') {
    $apply_fn($conn, $tx_id, 'Lunas', 'Online (' . $pay_type . ')');
    header("location:$redirect_base?sukses=" . urlencode("Pembayaran online berhasil! Pesanan Anda telah berstatus LUNAS dan otomatis dikonfirmasi."));
    exit;
}

if ($status === 'pending') {
    $apply_fn($conn, $tx_id, 'Menunggu Pembayaran', 'Online (' . $pay_type . ')');
    header("location:$redirect_base?pesan=" . urlencode("Pembayaran Anda sedang diproses. Silakan selesaikan pembayaran."));
    exit;
}

header("location:$redirect_base");
exit;
?>
