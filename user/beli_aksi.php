<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include '../config/config.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
/** @var mysqli $conn */

// Pastikan user login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("location:../login.php?pesan=" . urlencode("Akses ditolak! Silakan login terlebih dahulu."));
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id && isset($_SESSION['nama'])) {
    $nama_s = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $u_res = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama_s' LIMIT 1");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $user_id = (int)mysqli_fetch_assoc($u_res)['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

if (!$user_id) {
    header("location:../login.php?pesan=" . urlencode("Sesi login berakhir. Silakan login kembali."));
    exit;
}

// Folder uploads bukti bayar
$folder_payment = '../uploads/payments/';
if (!is_dir($folder_payment)) {
    mkdir($folder_payment, 0777, true);
}

// ============================================================================
// 1. PROSES KONFIRMASI PEMBELIAN LANGSUNG (DIRECT PURCHASE)
// ============================================================================
if (isset($_POST['proses_checkout'])) {
    $product_id       = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity         = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    $recipient_name   = isset($_POST['recipient_name']) ? mysqli_real_escape_string($conn, trim($_POST['recipient_name'])) : '';
    $recipient_phone  = isset($_POST['recipient_phone']) ? mysqli_real_escape_string($conn, trim($_POST['recipient_phone'])) : '';
    $shipping_address = isset($_POST['shipping_address']) ? mysqli_real_escape_string($conn, trim($_POST['shipping_address'])) : '';
    $courier          = isset($_POST['courier']) ? mysqli_real_escape_string($conn, trim($_POST['courier'])) : 'Ambil Langsung di Basecamp DANATRAIL';
    $payment_method   = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, trim($_POST['payment_method'])) : 'Transfer Bank BCA';
    $notes            = isset($_POST['notes']) ? mysqli_real_escape_string($conn, trim($_POST['notes'])) : '';

    if ($quantity < 1) $quantity = 1;

    $is_pickup = (stripos($courier, 'Basecamp') !== false || stripos($courier, 'Ambil') !== false);

    if ($product_id <= 0) {
        header("location:dashboard.php?pesan=" . urlencode("Produk yang dipilih tidak valid!"));
        exit;
    }

    if (empty($recipient_name) || empty($recipient_phone)) {
        header("location:checkout.php?product_id=" . $product_id . "&pesan=" . urlencode("Harap lengkapi nama penerima dan no WhatsApp!"));
        exit;
    }

    if (!$is_pickup && empty($shipping_address)) {
        header("location:checkout.php?product_id=" . $product_id . "&pesan=" . urlencode("Harap masukkan alamat lengkap tujuan pengiriman ekspedisi!"));
        exit;
    }

    if ($is_pickup && empty($shipping_address)) {
        $shipping_address = 'Ambil Langsung di Basecamp DANATRAIL';
    }

    // Update profile user jika ada info kontak baru
    if (!$is_pickup && !empty($shipping_address) && $shipping_address !== 'Ambil Langsung di Basecamp DANATRAIL') {
        mysqli_query($conn, "UPDATE users SET phone='$recipient_phone', address='$shipping_address' WHERE id='$user_id'");
    } else {
        mysqli_query($conn, "UPDATE users SET phone='$recipient_phone' WHERE id='$user_id'");
    }

    // Validasi produk dan stok fisik toko di backend
    $q_prod = mysqli_query($conn, "SELECT id, name, category, product_type, price_sale, stock_sale, discount_percent 
                                   FROM products 
                                   WHERE id='$product_id' LIMIT 1");

    if (!$q_prod || mysqli_num_rows($q_prod) == 0) {
        header("location:dashboard.php?pesan=" . urlencode("Produk tidak ditemukan!"));
        exit;
    }

    $product = mysqli_fetch_assoc($q_prod);

    if ($product['product_type'] !== 'sale' && $product['product_type'] !== 'both') {
        header("location:dashboard.php?pesan=" . urlencode("Produk ini khusus untuk disewakan, tidak dapat dibeli."));
        exit;
    }

    $avail_stock = (int)$product['stock_sale'];
    if ($avail_stock < $quantity) {
        header("location:checkout.php?product_id=" . $product_id . "&pesan=" . urlencode("Maaf, stok produk '" . $product['name'] . "' hanya tersisa " . $avail_stock . " unit!"));
        exit;
    }

    // Hitung harga resmi dari database (keamanan backend tinggi, tidak percaya data client)
    $raw_price = (float)$product['price_sale'];
    $disc_pct  = floatval($product['discount_percent'] ?? 0);
    $eff_price = get_discounted_price($raw_price, $disc_pct);
    $total_order = $eff_price * $quantity;

    // Rekalkulasi diskon otomatis dari aturan database
    $discount_calc = validate_and_calculate_discount([
        'user_id' => $user_id,
        'type' => 'sale',
        'subtotal' => $total_order,
        'rental_days' => 1,
        'product_ids' => [$product_id],
        'category_names' => [$product['category'] ?? '']
    ]);

    $discount_amount = (float)$discount_calc['discount_amount'];
    $final_total = max(0, $total_order - $discount_amount);
    $discount_id_sql = $discount_calc['discount_id'] ? "'" . (int)$discount_calc['discount_id'] . "'" : "NULL";
    $discount_code_sql = !empty($discount_calc['discount_code']) ? "'" . mysqli_real_escape_string($conn, $discount_calc['discount_code']) . "'" : "NULL";

    // Mulai Database Transaction untuk keamanan ACID
    mysqli_begin_transaction($conn);

    try {
        // Generate Order Code Unik
        $order_code = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        $pay_status = 'Menunggu Pembayaran';
        $order_status = 'Menunggu Pembayaran';

        $insert_order = "INSERT INTO orders (order_code, user_id, subtotal_price, discount_id, discount_code, discount_amount, total_price, recipient_name, recipient_phone, shipping_address, courier, payment_method, payment_status, status, notes)
                         VALUES ('$order_code', '$user_id', '$total_order', $discount_id_sql, $discount_code_sql, '$discount_amount', '$final_total', '$recipient_name', '$recipient_phone', '$shipping_address', '$courier', '$payment_method', '$pay_status', '$order_status', '$notes')";

        if (!mysqli_query($conn, $insert_order)) {
            throw new Exception("Gagal menyimpan data pesanan: " . mysqli_error($conn));
        }

        $order_id = mysqli_insert_id($conn);

        // Insert Order Items (Rincian Produk Snapshot)
        $pname = mysqli_real_escape_string($conn, $product['name']);
        $ins_item = mysqli_query($conn, "INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                                         VALUES ('$order_id', '$product_id', '$pname', '$eff_price', '$quantity', '$total_order')");
        if (!$ins_item) {
            throw new Exception("Gagal menyimpan rincian barang: " . mysqli_error($conn));
        }

        // Potong stok barang jual secara aman
        $update_stock = mysqli_query($conn, "UPDATE products SET stock_sale = GREATEST(0, stock_sale - $quantity) WHERE id='$product_id'");
        if (!$update_stock) {
            throw new Exception("Gagal memperbarui stok produk: " . mysqli_error($conn));
        }

        // Catat penggunaan diskon jika ada
        if ($discount_amount > 0 && $discount_calc['discount_id']) {
            record_discount_usage($discount_calc['discount_id'], $user_id, 'order', $order_id, $discount_amount);
        }

        // Hapus sisa data keranjang user jika produk ini pernah dimasukkan
        mysqli_query($conn, "DELETE FROM cart WHERE user_id='$user_id' AND product_id='$product_id'");

        // Commit transaksi database
        mysqli_commit($conn);

        $msg_sukses = "Pembelian langsung berhasil dibuat dengan Kode " . $order_code . ".";
        if ($discount_amount > 0) {
            $msg_sukses .= " Anda menghemat " . format_rupiah($discount_amount) . "!";
        }

        // Arahkan pengguna langsung ke detail transaksi / invoice
        header("location:../invoice.php?order_id=" . $order_id . "&sukses=" . urlencode($msg_sukses));
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("location:checkout.php?product_id=" . $product_id . "&pesan=" . urlencode($e->getMessage()));
        exit;
    }
}

// ============================================================================
// 2. UPLOAD BUKTI BAYAR PESANAN TOKO
// ============================================================================
if (isset($_POST['upload_bukti_bayar'])) {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

    // Verifikasi kepemilikan pesanan
    $q_cek = mysqli_query($conn, "SELECT id, order_code FROM orders WHERE id='$order_id' AND user_id='$user_id'");
    if (!$q_cek || mysqli_num_rows($q_cek) == 0) {
        header("location:pesanan.php?pesan=" . urlencode("Pesanan tidak ditemukan!"));
        exit;
    }

    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['payment_proof']['name'];
        $tmp_file  = $_FILES['payment_proof']['tmp_name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $valid_ext = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (in_array($ext, $valid_ext)) {
            $proof_name = 'pay_order_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($tmp_file, $folder_payment . $proof_name)) {
                mysqli_query($conn, "UPDATE orders SET payment_proof='$proof_name', payment_status='Menunggu Verifikasi Admin', status='Menunggu Konfirmasi' WHERE id='$order_id'");
                header("location:pesanan.php?sukses=" . urlencode("Bukti transfer pembayaran berhasil diunggah! Menunggu konfirmasi admin."));
                exit;
            }
        }
    }

    header("location:pesanan.php?pesan=" . urlencode("Gagal mengunggah bukti pembayaran. Format file harus JPG, PNG, WEBP, atau PDF."));
    exit;
}

// ============================================================================
// 3. BATALKAN PESANAN OLEH USER
// ============================================================================
if (isset($_GET['batal'])) {
    $order_id = (int)$_GET['batal'];

    // Cek pesanan
    $q_cek = mysqli_query($conn, "SELECT id, status FROM orders WHERE id='$order_id' AND user_id='$user_id'");
    if ($q_cek && mysqli_num_rows($q_cek) > 0) {
        $ord = mysqli_fetch_assoc($q_cek);
        if ($ord['status'] === 'Menunggu Pembayaran' || $ord['status'] === 'Menunggu Konfirmasi') {
            // Kembalikan stok produk
            $q_items = mysqli_query($conn, "SELECT product_id, quantity FROM order_items WHERE order_id='$order_id'");
            if ($q_items) {
                while ($it = mysqli_fetch_assoc($q_items)) {
                    $pid = (int)$it['product_id'];
                    $qty = (int)$it['quantity'];
                    mysqli_query($conn, "UPDATE products SET stock_sale = stock_sale + $qty WHERE id='$pid'");
                }
            }
            mysqli_query($conn, "UPDATE orders SET status='Dibatalkan' WHERE id='$order_id'");
            header("location:pesanan.php?sukses=" . urlencode("Pesanan telah dibatalkan dan stok produk telah dikembalikan."));
            exit;
        }
    }

    header("location:pesanan.php?pesan=" . urlencode("Pesanan tidak dapat dibatalkan!"));
    exit;
}

header("location:pesanan.php");
exit;
