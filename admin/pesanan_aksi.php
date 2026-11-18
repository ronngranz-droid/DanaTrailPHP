<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek akses admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=Akses ditolak!");
    exit;
}

require_once __DIR__ . '/../config/config.php';
global $conn;
/** @var mysqli $conn */

// Helper redirect
function get_admin_order_redirect($default = 'pesanan.php') {
    $target = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? $default;
    $target = trim($target);
    if (empty($target) || preg_match('/^(https?:|\/\/)/i', $target)) {
        return $default;
    }
    return $target;
}

$redirect_url = get_admin_order_redirect('pesanan.php');
$glue = (strpos($redirect_url, '?') !== false) ? '&' : '?';

// 1. UPDATE STATUS PEMBAYARAN & PESANAN
if (isset($_GET['update_status'])) {
    $order_id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $raw_status = trim($_GET['status'] ?? $_POST['status'] ?? '');
    $new_status = mysqli_real_escape_string($conn, $raw_status);

    $extra_update = "";
    if ($new_status === 'Diproses') {
        $extra_update = ", payment_status='Lunas'";
    } elseif ($new_status === 'Selesai') {
        $extra_update = ", payment_status='Lunas'";
    }

    $q = "UPDATE orders SET status='$new_status' $extra_update WHERE id='$order_id'";
    if (mysqli_query($conn, $q)) {
        header("location:" . $redirect_url . $glue . "sukses=" . urlencode("Status pesanan berhasil diperbarui menjadi: " . $new_status));
    } else {
        header("location:" . $redirect_url . $glue . "pesan=" . urlencode("Gagal memperbarui status: " . mysqli_error($conn)));
    }
    exit;
}

// 2. VERIFIKASI PEMBAYARAN LUNAS
if (isset($_GET['verifikasi_bayar'])) {
    $order_id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $raw_bayar    = trim($_GET['status_bayar'] ?? $_POST['status_bayar'] ?? 'Lunas');
    $status_bayar = mysqli_real_escape_string($conn, $raw_bayar);

    $status_pesanan = ($status_bayar === 'Lunas') ? 'Diproses' : 'Menunggu Pembayaran';

    $q = "UPDATE orders SET payment_status='$status_bayar', status='$status_pesanan' WHERE id='$order_id'";
    if (mysqli_query($conn, $q)) {
        header("location:" . $redirect_url . $glue . "sukses=" . urlencode("Pembayaran pesanan berhasil diverifikasi sebagai: " . $status_bayar));
    } else {
        header("location:" . $redirect_url . $glue . "pesan=" . urlencode("Gagal verifikasi pembayaran: " . mysqli_error($conn)));
    }
    exit;
}

// 3. INPUT NOMOR RESI & KIRIM PESANAN
if (isset($_POST['kirim_pesanan'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $tracking_number = isset($_POST['tracking_number']) ? mysqli_real_escape_string($conn, trim($_POST['tracking_number'])) : '';
    $courier = isset($_POST['courier']) ? mysqli_real_escape_string($conn, trim($_POST['courier'])) : '';

    $extra = "";
    if (!empty($courier)) {
        $extra .= ", courier='$courier'";
    }

    $q = "UPDATE orders SET status='Dikirim', tracking_number='$tracking_number' $extra WHERE id='$order_id'";
    if (mysqli_query($conn, $q)) {
        header("location:" . $redirect_url . $glue . "sukses=" . urlencode("Pesanan telah ditandai Dikirim dengan No. Resi: " . $tracking_number));
    } else {
        header("location:" . $redirect_url . $glue . "pesan=" . urlencode("Gagal input resi: " . mysqli_error($conn)));
    }
    exit;
}

// 4. BATALKAN PESANAN OLEH ADMIN & RESTORE STOK
if (isset($_GET['batalkan_pesanan'])) {
    $order_id = (int)($_GET['id'] ?? 0);

    // Kembalikan stok barang
    $q_items = mysqli_query($conn, "SELECT product_id, quantity FROM order_items WHERE order_id='$order_id'");
    if ($q_items) {
        while ($it = mysqli_fetch_assoc($q_items)) {
            $pid = (int)$it['product_id'];
            $qty = (int)$it['quantity'];
            mysqli_query($conn, "UPDATE products SET stock_sale = stock_sale + $qty WHERE id='$pid'");
        }
    }

    mysqli_query($conn, "UPDATE orders SET status='Dibatalkan', payment_status='Pembayaran Dibatalkan' WHERE id='$order_id'");
    header("location:" . $redirect_url . $glue . "sukses=" . urlencode("Pesanan telah dibatalkan dan stok produk telah dikembalikan!"));
    exit;
}

// 5. HAPUS DATA PESANAN
if (isset($_GET['hapus'])) {
    $order_id = (int)($_GET['hapus'] ?? 0);
    mysqli_query($conn, "DELETE FROM order_items WHERE order_id='$order_id'");
    mysqli_query($conn, "DELETE FROM orders WHERE id='$order_id'");
    header("location:" . $redirect_url . $glue . "sukses=" . urlencode("Data pesanan berhasil dihapus dari sistem."));
    exit;
}

header("location:pesanan.php");
exit;
