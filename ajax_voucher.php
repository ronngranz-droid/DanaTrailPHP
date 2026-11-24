<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/config.php';
/** @var mysqli $conn */

// Pastikan user sudah login
if (!isset($_SESSION['role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu untuk menggunakan voucher diskon.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id && isset($_SESSION['nama'])) {
    $nama_s = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $u_res = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama_s' LIMIT 1");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $user_id = (int)mysqli_fetch_assoc($u_res)['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

$type           = trim($_POST['type'] ?? 'both'); // 'rental' or 'sale'
$subtotal       = (float)($_POST['subtotal'] ?? 0);
$rental_days    = (int)($_POST['rental_days'] ?? 1);
$product_ids    = isset($_POST['product_ids']) ? (array)$_POST['product_ids'] : [];
$category_names = isset($_POST['category_names']) ? (array)$_POST['category_names'] : [];

// Jika product_ids berupa string berpemisah koma
if (is_string($_POST['product_ids'] ?? null)) {
    $product_ids = array_filter(array_map('trim', explode(',', $_POST['product_ids'])));
}
if (is_string($_POST['category_names'] ?? null)) {
    $category_names = array_filter(array_map('trim', explode(',', $_POST['category_names'])));
}

$res = validate_and_calculate_discount([
    'user_id' => $user_id,
    'type' => $type,
    'subtotal' => $subtotal,
    'rental_days' => $rental_days,
    'product_ids' => $product_ids,
    'category_names' => $category_names
]);

if ($res['discount_amount'] <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Belum ada diskon otomatis yang memenuhi syarat pesanan ini.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'discount_id' => $res['discount_id'],
    'discount_code' => $res['discount_code'],
    'discount_name' => $res['discount_name'],
    'discount_amount' => $res['discount_amount'],
    'discount_amount_formatted' => format_rupiah($res['discount_amount']),
    'subtotal' => $res['subtotal'],
    'subtotal_formatted' => format_rupiah($res['subtotal']),
    'total_after_discount' => $res['total_after_discount'],
    'total_after_discount_formatted' => format_rupiah($res['total_after_discount']),
    'message' => $res['message']
]);
exit;
