<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'error' => 'Silakan login terlebih dahulu!']);
    exit;
}

include '../config/config.php';
include '../config/midtrans.php';
/** @var mysqli $conn */

$rental_id = isset($_POST['rental_id']) ? (int)$_POST['rental_id'] : (isset($_GET['rental_id']) ? (int)$_GET['rental_id'] : 0);
$user_id   = $_SESSION['user_id'] ?? 0;

if ($rental_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID Transaksi tidak valid!']);
    exit;
}

// Ambil data transaksi
$sql = "SELECT r.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, p.name AS product_name, p.price AS product_price
        FROM rentals r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN products p ON r.product_id = p.id
        WHERE r.id = '$rental_id'";

if ($_SESSION['role'] !== 'admin') {
    $sql .= " AND r.user_id = '$user_id'";
}

$query = mysqli_query($conn, $sql);
if (!$query || mysqli_num_rows($query) == 0) {
    echo json_encode(['success' => false, 'error' => 'Data transaksi tidak ditemukan!']);
    exit;
}

$rental = mysqli_fetch_assoc($query);

// Cek apakah sudah lunas
if ($rental['payment_status'] === 'Lunas') {
    echo json_encode(['success' => false, 'error' => 'Pesanan ini sudah lunas!']);
    exit;
}

$is_simulator = midtrans_is_simulator();

// Cek jika sudah memiliki snap_token yang valid sesuai mode aktif
$existing_token = $rental['snap_token'] ?? '';
$is_mock_token  = (strpos($existing_token, 'MOCK-SNAP-') === 0);
if (!empty($existing_token) && (($is_simulator && $is_mock_token) || (!$is_simulator && !$is_mock_token))) {
    echo json_encode([
        'success'      => true,
        'token'        => $existing_token,
        'order_id'     => $rental['midtrans_order_id'] ?? $rental['rental_code'],
        'client_key'   => MIDTRANS_CLIENT_KEY,
        'snap_js'      => MIDTRANS_SNAP_JS,
        'gross_amount' => (int)$rental['total_price'],
        'simulator'    => $is_simulator
    ]);
    exit;
}

// Generate Order ID unik untuk Midtrans
$order_id = $rental['rental_code'] . '-' . time();
$gross_amount = (int)$rental['total_price'];

// Customer details
$name_parts = explode(' ', trim($rental['user_name'] ?? 'Pelanggan'), 2);
$first_name = $name_parts[0] ?? 'Pelanggan';
$last_name  = $name_parts[1] ?? 'Danatrail';

$params = [
    'transaction_details' => [
        'order_id'     => $order_id,
        'gross_amount' => $gross_amount,
    ],
    'item_details' => [
        [
            'id'       => (string)$rental['product_id'],
            'price'    => (int)$rental['total_price'],
            'quantity' => 1,
            'name'     => substr($rental['product_name'] ?? 'Sewa Alat Outdoor', 0, 50),
        ]
    ],
    'customer_details' => [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $rental['user_email'] ?? 'user@danatrail.com',
        'phone'      => $rental['user_phone'] ?? '081234567890',
    ],
    'callbacks' => [
        'finish' => midtrans_base_url() . '/payment/finish_payment.php?id=' . $rental_id
    ]
];

// Cek apakah server key masih default placeholder
if ($is_simulator) {
    // Mode Simulator Siap Pakai (Direct Instant Payment Simulator)
    $mock_token = 'MOCK-SNAP-' . time() . '-' . rand(1000, 9999);
    $safe_mock  = mysqli_real_escape_string($conn, $mock_token);
    $safe_oid   = mysqli_real_escape_string($conn, $order_id);
    mysqli_query($conn, "UPDATE rentals SET snap_token='$safe_mock', midtrans_order_id='$safe_oid' WHERE id='$rental_id'");

    echo json_encode([
        'success'      => true,
        'token'        => $mock_token,
        'order_id'     => $order_id,
        'client_key'   => MIDTRANS_CLIENT_KEY,
        'snap_js'      => MIDTRANS_SNAP_JS,
        'gross_amount' => $gross_amount,
        'simulator'    => true
    ]);
    exit;
}

// Request token ke Midtrans API
$res = midtrans_get_snap_token($params);

if ($res['success']) {
    $token      = $res['token'];
    $safe_token = mysqli_real_escape_string($conn, $token);
    $safe_oid   = mysqli_real_escape_string($conn, $order_id);
    mysqli_query($conn, "UPDATE rentals SET snap_token='$safe_token', midtrans_order_id='$safe_oid' WHERE id='$rental_id'");

    echo json_encode([
        'success'      => true,
        'token'        => $token,
        'order_id'     => $order_id,
        'client_key'   => MIDTRANS_CLIENT_KEY,
        'snap_js'      => MIDTRANS_SNAP_JS,
        'gross_amount' => $gross_amount,
        'redirect_url' => $res['redirect_url'] ?? null
    ]);
} else {
    // Fallback jika API Midtrans gagal terkoneksi
    echo json_encode([
        'success' => false,
        'error'   => $res['error'] ?? 'Gagal membuat sesi pembayaran Midtrans',
        'raw'     => $res
    ]);
}
?>
