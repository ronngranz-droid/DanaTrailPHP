<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/config.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
/** @var mysqli $conn */

// Pastikan user sudah login
if (!isset($_SESSION['role'])) {
    header("location:../login.php?pesan=Silakan login terlebih dahulu!");
    exit;
}

// Ambil User ID dari session atau database
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $nama = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $user_check = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama' LIMIT 1");
    if ($user_check && mysqli_num_rows($user_check) > 0) {
        $u = mysqli_fetch_assoc($user_check);
        $user_id = $u['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

// 1. PROSES BOOKING / SEWA ALAT
if (isset($_POST['sewa_produk'])) {
    $product_id     = (int)($_POST['product_id'] ?? 0);
    $rental_date    = mysqli_real_escape_string($conn, trim($_POST['rental_date'] ?? ''));
    $return_date    = mysqli_real_escape_string($conn, trim($_POST['return_date'] ?? ''));
    $quantity       = (int)($_POST['quantity'] ?? 1);
    $guarantee_type = mysqli_real_escape_string($conn, trim($_POST['guarantee_type'] ?? 'KTP Asli'));
    $payment_method = mysqli_real_escape_string($conn, trim($_POST['payment_method'] ?? 'Transfer Bank BCA'));
    $notes          = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));

    // Cek dan validasi data kontak user (No WhatsApp dan Alamat)
    $input_phone    = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $input_address  = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));

    $user_q = mysqli_query($conn, "SELECT phone, address FROM users WHERE id='$user_id'");
    $user_curr = ($user_q && mysqli_num_rows($user_q) > 0) ? mysqli_fetch_assoc($user_q) : [];

    $final_phone   = !empty($input_phone) ? $input_phone : ($user_curr['phone'] ?? '');
    $final_address = !empty($input_address) ? $input_address : ($user_curr['address'] ?? '');

    // Jika Nomor WhatsApp atau Alamat masih kosong, wajibkan ke profile
    if (empty($final_phone) || empty($final_address)) {
        $from_page = !empty($_SERVER['HTTP_REFERER']) ? basename($_SERVER['HTTP_REFERER']) : 'dashboard.php';
        header("location:profile.php?pesan=" . urlencode("Wajib melengkapi No. WhatsApp dan Alamat sebelum melakukan pemesanan!") . "&wajib_isi=1&redirect_to=" . urlencode($from_page));
        exit;
    }

    // Jika user mengisi/memperbarui phone atau address di form pemesanan, perbarui ke database user
    if (!empty($input_phone) || !empty($input_address)) {
        $update_p = !empty($input_phone) ? $input_phone : ($user_curr['phone'] ?? '');
        $update_a = !empty($input_address) ? $input_address : ($user_curr['address'] ?? '');
        mysqli_query($conn, "UPDATE users SET phone='$update_p', address='$update_a' WHERE id='$user_id'");
    }

    if ($product_id <= 0 || empty($rental_date) || empty($return_date) || $quantity <= 0) {
        header("location:dashboard.php?pesan=Mohon lengkapi formulir sewa dengan benar!");
        exit;
    }

    // Validasi Tanggal
    $time_start = strtotime($rental_date);
    $time_end   = strtotime($return_date);

    if ($time_end < $time_start) {
        header("location:dashboard.php?pesan=Tanggal kembali tidak boleh sebelum tanggal sewa!");
        exit;
    }

    $diff_seconds  = $time_end - $time_start;
    $duration_days = ceil($diff_seconds / (60 * 60 * 24));
    if ($duration_days <= 0) {
        $duration_days = 1; // Minimal sewa 1 hari
    }

    // Handle Upload Foto Dokumen Jaminan
    $guarantee_photo_filename = null;
    if (isset($_FILES['guarantee_photo']) && $_FILES['guarantee_photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['guarantee_photo']['tmp_name'];
        $file_name = $_FILES['guarantee_photo']['name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (in_array($file_ext, $allowed)) {
            $upload_dir = '../uploads/guarantees/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_name = 'jaminan_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $guarantee_photo_filename = $new_name;
            }
        }
    }

    // Ambil detail produk
    $p_query = mysqli_query($conn, "SELECT name, price, stock, category, discount_percent FROM products WHERE id='$product_id'");
    if (!$p_query || mysqli_num_rows($p_query) == 0) {
        header("location:dashboard.php?pesan=Produk tidak ditemukan!");
        exit;
    }

    $product = mysqli_fetch_assoc($p_query);

    // Validasi Stok Fisik & Ketersediaan untuk Periode Tanggal yang Dipilih
    $total_stock = (int)$product['stock'];
    if ($quantity > $total_stock) {
        header("location:dashboard.php?pesan=" . urlencode("Jumlah sewa melebihi total unit yang dimiliki basecamp!"));
        exit;
    }

    $q_active_rentals = mysqli_query($conn, "
        SELECT SUM(quantity) AS booked_qty 
        FROM rentals 
        WHERE product_id = '$product_id' 
          AND status NOT IN ('Dibatalkan')
          AND return_status != 'Selesai'
          AND rental_date <= '$return_date' 
          AND return_date >= '$rental_date'
    ");
    $booked_qty = 0;
    if ($q_active_rentals && $row_b = mysqli_fetch_assoc($q_active_rentals)) {
        $booked_qty = (int)($row_b['booked_qty'] ?? 0);
    }
    $available_stock_period = max(0, $total_stock - $booked_qty);
    if ($quantity > $available_stock_period) {
        header("location:dashboard.php?pesan=" . urlencode("Maaf, untuk periode " . date('d/m/Y', $time_start) . " s.d. " . date('d/m/Y', $time_end) . ", sisa alat yang tersedia hanya " . $available_stock_period . " unit (sudah terbooking " . $booked_qty . " unit)."));
        exit;
    }

    $disc_pct = floatval($product['discount_percent'] ?? 0);
    $unit_price_effective = get_discounted_price((float)$product['price'], $disc_pct);

    // Hitung subtotal harga sewa
    $subtotal_price = $unit_price_effective * $duration_days * $quantity;

    // REKALKULASI DISKON OTOMATIS DI SERVER (KEAMANAN TINGGI)
    $discount_calc = validate_and_calculate_discount([
        'user_id' => $user_id,
        'type' => 'rental',
        'subtotal' => $subtotal_price,
        'rental_days' => $duration_days,
        'product_ids' => [$product_id],
        'category_names' => [$product['category'] ?? '']
    ]);

    $discount_amount = (float)$discount_calc['discount_amount'];
    $final_total = max(0, $subtotal_price - $discount_amount);
    $discount_id_sql = $discount_calc['discount_id'] ? "'" . (int)$discount_calc['discount_id'] . "'" : "NULL";
    $discount_code_sql = !empty($discount_calc['discount_code']) ? "'" . mysqli_real_escape_string($conn, $discount_calc['discount_code']) . "'" : "NULL";

    // Generate kode transaksi unik
    $rental_code = 'DNT-' . date('Ymd') . '-' . rand(100, 999);
    $safe_photo = $guarantee_photo_filename ? "'$guarantee_photo_filename'" : "NULL";

    // Mulai Database Transaction
    mysqli_begin_transaction($conn);

    try {
        // Simpan data transaksi ke tabel rentals
        $query = "INSERT INTO rentals (user_id, product_id, rental_code, rental_date, return_date, duration_days, quantity, subtotal_price, discount_id, discount_code, discount_amount, total_price, payment_method, payment_status, status, return_status, guarantee_type, guarantee_photo, notes)
                  VALUES ('$user_id', '$product_id', '$rental_code', '$rental_date', '$return_date', '$duration_days', '$quantity', '$subtotal_price', $discount_id_sql, $discount_code_sql, '$discount_amount', '$final_total', '$payment_method', 'Menunggu Pembayaran', 'Menunggu Konfirmasi', 'Sedang Disewa', '$guarantee_type', $safe_photo, '$notes')";

        if (!mysqli_query($conn, $query)) {
            throw new Exception("Gagal membuat pesanan sewa: " . mysqli_error($conn));
        }

        $rental_id = mysqli_insert_id($conn);

        // Catat pemakaian diskon jika ada potongan
        if ($discount_amount > 0 && $discount_calc['discount_id']) {
            record_discount_usage($discount_calc['discount_id'], $user_id, 'rental', $rental_id, $discount_amount);
        }

        // Commit transaksi
        mysqli_commit($conn);

        $sukses_msg = "Pemesanan sewa berhasil dikirim! Menunggu konfirmasi admin.";
        if ($discount_amount > 0) {
            $sukses_msg .= " Anda hemat " . format_rupiah($discount_amount) . "!";
        }
        header("location:riwayat.php?sukses=" . urlencode($sukses_msg));
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("location:dashboard.php?pesan=" . urlencode($e->getMessage()));
        exit;
    }
}

// 2. PROSES UPLOAD BUKTI PEMBAYARAN OLEH USER
if (isset($_POST['upload_bukti_bayar'])) {
    $rental_id = (int)($_POST['rental_id'] ?? 0);

    // Cek kepemilikan rental
    $cek = mysqli_query($conn, "SELECT id, payment_proof FROM rentals WHERE id='$rental_id' AND user_id='$user_id'");
    if (!$cek || mysqli_num_rows($cek) == 0) {
        header("location:riwayat.php?pesan=Data pesanan tidak ditemukan atau akses ditolak!");
        exit;
    }

    $rental_data = mysqli_fetch_assoc($cek);

    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['payment_proof']['tmp_name'];
        $file_name = $_FILES['payment_proof']['name'];
        $file_size = $_FILES['payment_proof']['size'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (!in_array($file_ext, $allowed)) {
            header("location:riwayat.php?pesan=Format file bukti bayar tidak didukung (gunakan JPG, PNG, WEBP, PDF)!");
            exit;
        }

        if ($file_size > 5 * 1024 * 1024) {
            header("location:riwayat.php?pesan=Ukuran file bukti bayar maksimal 5 MB!");
            exit;
        }

        $upload_dir = '../uploads/payments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Hapus file bukti pembayaran lama jika ada
        if (!empty($rental_data['payment_proof']) && file_exists($upload_dir . $rental_data['payment_proof'])) {
            unlink($upload_dir . $rental_data['payment_proof']);
        }

        $new_filename = 'transfer_' . $rental_id . '_' . time() . '_' . rand(100, 999) . '.' . $file_ext;

        if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
            $update = mysqli_query($conn, "UPDATE rentals SET 
                        payment_proof='$new_filename', 
                        payment_status='Menunggu Verifikasi Admin', 
                        payment_date=NOW() 
                      WHERE id='$rental_id'");

            if ($update) {
                header("location:riwayat.php?sukses=Bukti pembayaran berhasil diunggah! Tim DANATRAIL akan segera memverifikasi pesanan Anda.");
            } else {
                header("location:riwayat.php?pesan=Gagal menyimpan data pembayaran: " . mysqli_error($conn));
            }
            exit;
        } else {
            header("location:riwayat.php?pesan=Gagal mengunggah file ke server!");
            exit;
        }
    } else {
        header("location:riwayat.php?pesan=Silakan pilih file bukti transfer yang valid!");
        exit;
    }
}

// 3. PROSES BATALKAN SEWA OLEH USER
if (isset($_GET['batal']) || isset($_POST['batal_sewa'])) {
    $rental_id = (int)($_POST['rental_id'] ?? $_GET['batal'] ?? 0);

    // Hanya bisa membatalkan jika status masih 'Menunggu Konfirmasi' dan milik user yang sedang login
    $cek = mysqli_query($conn, "SELECT id, status FROM rentals WHERE id='$rental_id' AND user_id='$user_id'");
    if ($cek && mysqli_num_rows($cek) > 0) {
        $r = mysqli_fetch_assoc($cek);
        if ($r['status'] === 'Menunggu Konfirmasi') {
            mysqli_query($conn, "UPDATE rentals SET status='Dibatalkan' WHERE id='$rental_id'");
            revert_discount_usage('rental', $rental_id);
            header("location:riwayat.php?sukses=Pesanan sewa berhasil dibatalkan dan kuota voucher Anda dipulihkan.");
            exit;
        } else {
            header("location:riwayat.php?pesan=Pesanan tidak dapat dibatalkan karena sudah diproses!");
            exit;
        }
    } else {
        header("location:riwayat.php?pesan=Data pesanan tidak ditemukan!");
        exit;
    }
}

// Fallback redirect
header("location:dashboard.php");
exit;

