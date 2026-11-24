<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../config/config.php';
require_once __DIR__ . '/../config/stock_cash_helper.php';
/** @var mysqli $conn */

// Cek akses admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=Akses ditolak!");
    exit;
}

// Folder upload foto
$folder_upload = '../uploads/products/';
if (!is_dir($folder_upload)) {
    mkdir($folder_upload, 0777, true);
}

// Helper untuk validasi target redirect aman
function get_safe_redirect($default = 'perlengkapan.php') {
    $target = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? $default;
    $target = trim($target);
    if (empty($target) || preg_match('/^(https?:|\/\/)/i', $target)) {
        return $default;
    }
    return $target;
}

// Helper untuk memproses upload foto
function process_uploaded_product_image($file_array, $folder_upload) {
    if (!isset($file_array) || $file_array['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $nama_file = $file_array['name'];
    $tmp_file  = $file_array['tmp_name'];
    $ekstensi  = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));

    if (empty($ekstensi) && !empty($tmp_file) && file_exists($tmp_file)) {
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmp_file);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmp_file);
        }

        $mime_map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/bmp'  => 'bmp',
            'image/x-ms-bmp' => 'bmp'
        ];

        if (!empty($mime) && isset($mime_map[$mime])) {
            $ekstensi = $mime_map[$mime];
        }
    }

    $ekstensi_valid = ['jpg', 'jpeg', 'jpe', 'jfif', 'png', 'webp', 'gif', 'bmp'];
    if (in_array($ekstensi, $ekstensi_valid)) {
        $image_name = 'gear_' . time() . '_' . rand(100, 999) . '.' . $ekstensi;
        if (move_uploaded_file($tmp_file, $folder_upload . $image_name)) {
            return $image_name;
        }
    }

    return null;
}

// 1. TAMBAH PRODUK BARU
if (isset($_POST['tambah_produk'])) {
    $redirect_base = get_safe_redirect('perlengkapan.php');
    $name          = isset($_POST['name']) ? mysqli_real_escape_string($conn, trim($_POST['name'])) : '';
    $category      = isset($_POST['category']) ? mysqli_real_escape_string($conn, trim($_POST['category'])) : '';
    $product_type  = isset($_POST['product_type']) ? mysqli_real_escape_string($conn, trim($_POST['product_type'])) : 'rental';
    if (!in_array($product_type, ['rental', 'sale', 'both'])) {
        $product_type = 'rental';
    }

    $price         = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $stock         = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $price_sale        = isset($_POST['price_sale']) ? (float)$_POST['price_sale'] : 0;
    $discount_percent  = isset($_POST['discount_percent']) ? max(0, min(100, (float)$_POST['discount_percent'])) : 0;
    $cost_price        = isset($_POST['cost_price']) ? max(0, (float)$_POST['cost_price']) : 0;
    $stock_sale        = isset($_POST['stock_sale']) ? (int)$_POST['stock_sale'] : 0;
    $description       = isset($_POST['description']) ? mysqli_real_escape_string($conn, trim($_POST['description'])) : '';
    $image_name        = '';

    $glue = (strpos($redirect_base, '?') !== false) ? '&' : '?';

    if (empty($name) || empty($category)) {
        header("location:" . $redirect_base . $glue . "pesan=" . urlencode("Harap isi nama dan kategori perlengkapan!"));
        exit;
    }

    if ($product_type === 'rental' && $price <= 0) {
        header("location:" . $redirect_base . $glue . "pesan=" . urlencode("Tarif sewa harus diisi dan lebih dari Rp 0 untuk produk rental!"));
        exit;
    }

    if ($product_type === 'sale' && $price_sale <= 0) {
        header("location:" . $redirect_base . $glue . "pesan=" . urlencode("Harga jual harus diisi dan lebih dari Rp 0 untuk produk yang dijual!"));
        exit;
    }

    if ($product_type === 'both' && ($price <= 0 || $price_sale <= 0)) {
        header("location:" . $redirect_base . $glue . "pesan=" . urlencode("Tarif sewa dan harga jual harus diisi untuk produk tipe Rental & Jual!"));
        exit;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploaded = process_uploaded_product_image($_FILES['image'], $folder_upload);
        if ($uploaded) {
            $image_name = $uploaded;
        }
    }

    $query = "INSERT INTO products (name, category, product_type, price, price_sale, discount_percent, cost_price, stock, stock_sale, description, image) 
              VALUES ('$name', '$category', '$product_type', '$price', '$price_sale', '$discount_percent', '$cost_price', '$stock', '$stock_sale', '$description', '$image_name')";
    
    if (mysqli_query($conn, $query)) {
        $new_id = mysqli_insert_id($conn);
        $total_initial_units = ($product_type === 'rental') ? $stock : (($product_type === 'sale') ? $stock_sale : ($stock + $stock_sale));
        $initial_cost = $total_initial_units * $cost_price;

        // Potong kas toko jika ada modal pengadaan awal
        if ($initial_cost > 0) {
            record_cash_flow($conn, 'out', 'new_product', $initial_cost, "Pengadaan awal {$total_initial_units} unit '{$name}' @ Rp " . number_format($cost_price, 0, ',', '.'), $new_id);
        }

        // Catat stok awal ke stock_logs jika ada stok
        if ($stock > 0 && ($product_type === 'rental' || $product_type === 'both')) {
            $c_rent = $stock * $cost_price;
            mysqli_query($conn, "INSERT INTO stock_logs (product_id, type, action_type, quantity, cost_per_unit, total_cost, notes) 
                VALUES ('$new_id', 'rental', 'new_product', '$stock', '$cost_price', '$c_rent', 'Stok awal perlengkapan baru')");
        }
        if ($stock_sale > 0 && ($product_type === 'sale' || $product_type === 'both')) {
            $c_sale = $stock_sale * $cost_price;
            mysqli_query($conn, "INSERT INTO stock_logs (product_id, type, action_type, quantity, cost_per_unit, total_cost, notes) 
                VALUES ('$new_id', 'sale', 'new_product', '$stock_sale', '$cost_price', '$c_sale', 'Stok awal toko')");
        }

        header("location:" . $redirect_base . $glue . "sukses=" . urlencode("Perlengkapan baru '{$name}' berhasil ditambahkan ke inventaris!"));
    } else {
        header("location:" . $redirect_base . $glue . "pesan=" . urlencode("Gagal menambahkan perlengkapan: " . mysqli_error($conn)));
    }
    exit;
}

// 2. EDIT PRODUK
if (isset($_POST['edit_produk'])) {
    $redirect_base = get_safe_redirect('perlengkapan.php');
    $glue          = (strpos($redirect_base, '?') !== false) ? '&' : '?';

    $id                = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name              = isset($_POST['name']) ? mysqli_real_escape_string($conn, trim($_POST['name'])) : '';
    $category          = isset($_POST['category']) ? mysqli_real_escape_string($conn, trim($_POST['category'])) : '';
    $product_type      = isset($_POST['product_type']) ? mysqli_real_escape_string($conn, trim($_POST['product_type'])) : 'rental';
    if (!in_array($product_type, ['rental', 'sale', 'both'])) {
        $product_type = 'rental';
    }

    $price             = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $stock             = isset($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $price_sale        = isset($_POST['price_sale']) ? (float)$_POST['price_sale'] : 0;
    $discount_percent  = isset($_POST['discount_percent']) ? max(0, min(100, (float)$_POST['discount_percent'])) : 0;
    $stock_sale        = isset($_POST['stock_sale']) ? (int)$_POST['stock_sale'] : 0;
    $description       = isset($_POST['description']) ? mysqli_real_escape_string($conn, trim($_POST['description'])) : '';

    $query_lama = mysqli_query($conn, "SELECT image FROM products WHERE id='$id'");
    $data_lama  = ($query_lama && mysqli_num_rows($query_lama) > 0) ? mysqli_fetch_assoc($query_lama) : null;
    $image_name = $data_lama ? $data_lama['image'] : '';

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $nama_foto_baru = process_uploaded_product_image($_FILES['image'], $folder_upload);
        if ($nama_foto_baru) {
            if (!empty($image_name) && file_exists($folder_upload . $image_name)) {
                unlink($folder_upload . $image_name);
            }
            $image_name = $nama_foto_baru;
        }
    }

    $query = "UPDATE products SET 
                name='$name', 
                category='$category', 
                product_type='$product_type',
                price='$price', 
                price_sale='$price_sale',
                discount_percent='$discount_percent',
                stock='$stock', 
                stock_sale='$stock_sale',
                description='$description', 
                image='$image_name' 
              WHERE id='$id'";

    if (mysqli_query($conn, $query)) {
        header("location:" . $redirect_base . $glue . "sukses=" . urlencode("Data perlengkapan berhasil diperbarui!"));
    } else {
        header("location:" . $redirect_base . $glue . "pesan=" . urlencode("Gagal memperbarui: " . mysqli_error($conn)));
    }
    exit;
}

// 3. HAPUS PRODUK
if (isset($_GET['hapus'])) {
    $redirect_base = get_safe_redirect('perlengkapan.php');
    $glue          = (strpos($redirect_base, '?') !== false) ? '&' : '?';
    $id            = (int)$_GET['hapus'];

    $cek = mysqli_query($conn, "SELECT image FROM products WHERE id='$id'");
    if ($cek && mysqli_num_rows($cek) > 0) {
        $data = mysqli_fetch_assoc($cek);
        if (!empty($data['image']) && file_exists($folder_upload . $data['image'])) {
            unlink($folder_upload . $data['image']);
        }
    }

    $query = "DELETE FROM products WHERE id='$id'";
    if (mysqli_query($conn, $query)) {
        header("location:" . $redirect_base . $glue . "sukses=" . urlencode("Perlengkapan berhasil dihapus dari inventaris!"));
    } else {
        header("location:" . $redirect_base . $glue . "pesan=" . urlencode("Gagal menghapus: " . mysqli_error($conn)));
    }
    exit;
}

// 4. RESTOCK BARANG / TAMBAH STOK CEPAT DENGAN POTONG KAS TOKO
if (isset($_POST['restock_produk'])) {
    $redirect_base = get_safe_redirect('perlengkapan.php');
    $glue          = (strpos($redirect_base, '?') !== false) ? '&' : '?';

    $product_id    = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $restock_target= isset($_POST['restock_target']) ? trim($_POST['restock_target']) : 'rental';
    $qty_rental    = isset($_POST['qty_rental']) ? max(0, (int)$_POST['qty_rental']) : 0;
    $qty_sale      = isset($_POST['qty_sale']) ? max(0, (int)$_POST['qty_sale']) : 0;
    $cost_per_unit = isset($_POST['cost_per_unit']) ? max(0, (float)$_POST['cost_per_unit']) : 0;
    $notes         = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($restock_target === 'rental') {
        $qty_sale = 0;
    } elseif ($restock_target === 'sale') {
        $qty_rental = 0;
    }

    $res = process_stock_addition_and_deduct_cash($conn, $product_id, $restock_target, $qty_rental, $qty_sale, $cost_per_unit, 'restock', $notes);

    if ($res['success']) {
        $msg = "Stok '" . $res['product_name'] . "' berhasil ditambah +" . $res['qty_added'] . " unit!";
        if ($res['total_cost'] > 0) {
            $msg .= " Uang kas toko berkurang Rp " . number_format($res['total_cost'], 0, ',', '.') . " (Sisa Kas: Rp " . number_format($res['cash_after'], 0, ',', '.') . "). Total nilai aset produk kini Rp " . number_format($res['new_total_value'], 0, ',', '.') . ".";
        }
        header("location:" . $redirect_base . $glue . "sukses=" . urlencode($msg));
    } else {
        header("location:" . $redirect_base . $glue . "pesan=" . urlencode($res['message']));
    }
    exit;
}

// Redirect jika file dibuka langsung
header("location:perlengkapan.php");
exit;
?>
