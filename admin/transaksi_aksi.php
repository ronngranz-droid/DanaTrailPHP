<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/config.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
/** @var mysqli $conn */

// Cek apakah admin sudah login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=Akses ditolak!");
    exit;
}

// 1. VERIFIKASI STATUS PEMBAYARAN OLEH ADMIN
if (isset($_POST['verifikasi_bayar']) || isset($_GET['verifikasi_bayar'])) {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $status_bayar = mysqli_real_escape_string($conn, trim($_POST['status_bayar'] ?? $_GET['status_bayar'] ?? 'Lunas'));

    $cek = mysqli_query($conn, "SELECT id FROM rentals WHERE id='$id'");
    if ($cek && mysqli_num_rows($cek) > 0) {
        $update = mysqli_query($conn, "UPDATE rentals SET payment_status='$status_bayar', payment_date=NOW() WHERE id='$id'");
        if ($update) {
            header("location:transaksi.php?sukses=Status pembayaran berhasil diubah menjadi: " . urlencode($status_bayar));
        } else {
            header("location:transaksi.php?pesan=Gagal memperbarui status pembayaran: " . mysqli_error($conn));
        }
    } else {
        header("location:transaksi.php?pesan=Data transaksi tidak ditemukan.");
    }
    exit;
}

// 2. SETUJUI / KONFIRMASI SEWA (Ubah status ke 'Disewa' & Kurangi Stok)
if (isset($_GET['konfirmasi']) || isset($_POST['konfirmasi_sewa'])) {
    $id = (int)($_POST['id'] ?? $_GET['konfirmasi'] ?? 0);

    // Ambil data produk, status, dan jumlah sewa
    $ambil = mysqli_query($conn, "SELECT product_id, quantity, status FROM rentals WHERE id='$id'");
    if ($ambil && mysqli_num_rows($ambil) > 0) {
        $data = mysqli_fetch_assoc($ambil);
        $product_id = (int)$data['product_id'];
        $quantity   = (int)$data['quantity'];
        $status_cur = $data['status'];

        // Cek stok produk saat ini
        $p_query = mysqli_query($conn, "SELECT stock FROM products WHERE id='$product_id'");
        $p_data  = mysqli_fetch_assoc($p_query);
        $stok_saat_ini = (int)($p_data['stock'] ?? 0);

        if ($stok_saat_ini < $quantity) {
            header("location:transaksi.php?pesan=Stok produk tidak mencukupi untuk disetujui (sisa $stok_saat_ini unit)!");
            exit;
        }

        // Kurangi stok produk jika belum berstatus Disewa
        if ($status_cur !== 'Disewa') {
            mysqli_query($conn, "UPDATE products SET stock = stock - $quantity WHERE id='$product_id'");
        }

        // Update status transaksi menjadi Disewa
        $update = mysqli_query($conn, "UPDATE rentals SET status='Disewa' WHERE id='$id'");

        if ($update) {
            header("location:transaksi.php?sukses=Pesanan sewa berhasil disetujui dan stok telah dipotong!");
        } else {
            header("location:transaksi.php?pesan=Gagal mengonfirmasi pesanan.");
        }
    } else {
        header("location:transaksi.php?pesan=Data transaksi tidak ditemukan.");
    }
    exit;
}

// 3. TANDAI SELESAI / PENGEMBALIAN ALAT (Ubah status ke 'Selesai', Catat Denda, & Kembalikan Stok)
if (isset($_GET['selesai']) || isset($_POST['selesai_sewa'])) {
    $id = (int)($_POST['id'] ?? $_GET['selesai'] ?? 0);
    $actual_return = isset($_POST['actual_return_date']) ? mysqli_real_escape_string($conn, trim($_POST['actual_return_date'])) : date('Y-m-d');
    $status_denda = isset($_POST['fine_payment_status']) ? mysqli_real_escape_string($conn, trim($_POST['fine_payment_status'])) : 'Belum Dibayar';
    $catatan_denda = isset($_POST['fine_notes']) ? mysqli_real_escape_string($conn, trim($_POST['fine_notes'])) : '';
    $admin_name = $_SESSION['nama'] ?? 'Admin Basecamp';

    // Ambil data produk dan jumlah sewa
    $ambil = mysqli_query($conn, "SELECT product_id, quantity, status FROM rentals WHERE id='$id'");
    if ($ambil && mysqli_num_rows($ambil) > 0) {
        $data = mysqli_fetch_assoc($ambil);
        $product_id = (int)$data['product_id'];
        $quantity   = (int)$data['quantity'];
        $status_cur = $data['status'];

        // Kembalikan stok produk jika status sebelumnya Disewa
        if ($status_cur === 'Disewa') {
            mysqli_query($conn, "UPDATE products SET stock = stock + $quantity WHERE id='$product_id'");
        }

        // Catat pengembalian dan hitung denda otomatis serta rekam ke rental_fines
        sync_rental_fine_record($id, $actual_return, $status_denda, $catatan_denda, $admin_name);

        // Update status rental menjadi Selesai
        $update = mysqli_query($conn, "UPDATE rentals SET 
                    status='Selesai', 
                    return_status='Selesai',
                    actual_return_date='$actual_return' 
                  WHERE id='$id'");

        if ($update) {
            header("location:transaksi.php?sukses=Pengembalian alat berhasil dicatat dan stok telah dikembalikan ke inventaris!");
        } else {
            header("location:transaksi.php?pesan=Gagal menyelesaikan transaksi: " . mysqli_error($conn));
        }
    } else {
        header("location:transaksi.php?pesan=Data transaksi tidak ditemukan.");
    }
    exit;
}

// 4. UPDATE STATUS PEMBAYARAN DENDA OLEH ADMIN
if (isset($_POST['update_status_denda'])) {
    $rental_id = (int)($_POST['rental_id'] ?? 0);
    $status_denda = mysqli_real_escape_string($conn, trim($_POST['fine_payment_status'] ?? 'Sudah Dibayar'));
    $notes = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));
    $admin_name = $_SESSION['nama'] ?? 'Admin Basecamp';
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'denda.php';

    $res = sync_rental_fine_record($rental_id, null, $status_denda, $notes, $admin_name);

    if ($res) {
        header("location:$redirect_to?sukses=Status pembayaran denda berhasil diubah menjadi: " . urlencode($status_denda));
    } else {
        header("location:$redirect_to?pesan=Gagal memperbarui status denda.");
    }
    exit;
}

// 4. TOLAK / BATALKAN PESANAN (Ubah status ke 'Dibatalkan')
if (isset($_GET['tolak']) || isset($_POST['tolak_sewa'])) {
    $id = (int)($_POST['id'] ?? $_GET['tolak'] ?? 0);

    $ambil = mysqli_query($conn, "SELECT product_id, quantity, status FROM rentals WHERE id='$id'");
    if ($ambil && mysqli_num_rows($ambil) > 0) {
        $data = mysqli_fetch_assoc($ambil);
        $product_id = (int)$data['product_id'];
        $quantity   = (int)$data['quantity'];
        $status_cur = $data['status'];

        // Jika sebelumnya sudah 'Disewa' lalu dibatalkan, kembalikan stoknya
        if ($status_cur === 'Disewa') {
            mysqli_query($conn, "UPDATE products SET stock = stock + $quantity WHERE id='$product_id'");
        }

        $update = mysqli_query($conn, "UPDATE rentals SET status='Dibatalkan' WHERE id='$id'");
        revert_discount_usage('rental', $id);

        if ($update) {
            header("location:transaksi.php?sukses=Pesanan sewa berhasil ditolak/dibatalkan dan kuota voucher dipulihkan.");
        } else {
            header("location:transaksi.php?pesan=Gagal membatalkan pesanan.");
        }
    } else {
        header("location:transaksi.php?pesan=Data transaksi tidak ditemukan.");
    }
    exit;
}

// 5. HAPUS DATA TRANSAKSI
if (isset($_GET['hapus']) || isset($_POST['hapus_sewa'])) {
    $id = (int)($_POST['id'] ?? $_GET['hapus'] ?? 0);

    $ambil = mysqli_query($conn, "SELECT product_id, quantity, status, guarantee_photo, payment_proof FROM rentals WHERE id='$id'");
    if ($ambil && mysqli_num_rows($ambil) > 0) {
        $data = mysqli_fetch_assoc($ambil);
        $product_id = (int)$data['product_id'];
        $quantity   = (int)$data['quantity'];
        $status_cur = $data['status'];

        // Jika transaksi yang dihapus sedang berstatus 'Disewa', kembalikan stok terlebih dahulu!
        if ($status_cur === 'Disewa') {
            mysqli_query($conn, "UPDATE products SET stock = stock + $quantity WHERE id='$product_id'");
        }

        // Hapus file dokumen jaminan & bukti bayar dari server jika ada
        if (!empty($data['guarantee_photo']) && file_exists('../uploads/guarantees/' . $data['guarantee_photo'])) {
            unlink('../uploads/guarantees/' . $data['guarantee_photo']);
        }
        if (!empty($data['payment_proof']) && file_exists('../uploads/payments/' . $data['payment_proof'])) {
            unlink('../uploads/payments/' . $data['payment_proof']);
        }

        revert_discount_usage('rental', $id);
        $hapus = mysqli_query($conn, "DELETE FROM rentals WHERE id='$id'");

        if ($hapus) {
            header("location:transaksi.php?sukses=Data transaksi berhasil dihapus dari sistem!");
        } else {
            header("location:transaksi.php?pesan=Gagal menghapus data: " . mysqli_error($conn));
        }
    } else {
        header("location:transaksi.php?pesan=Data transaksi tidak ditemukan.");
    }
    exit;
}

// Kembali ke halaman transaksi jika file dibuka langsung
header("location:transaksi.php");
exit;
