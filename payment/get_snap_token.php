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
$order_id  = isset($_POST['order_id']) ? (int)$_POST['order_id'] : (isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0);

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0 && !empty($_SESSION['nama'])) {
    $nama_s = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $u_res  = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama_s' LIMIT 1");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $user_id = (int)mysqli_fetch_assoc($u_res)['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

$is_order = ($order_id > 0 && $rental_id <= 0);
$tx_id    = $is_order ? $order_id : $rental_id;

if ($tx_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID Transaksi tidak valid!']);
    exit;
}

$owner_filter = ($_SESSION['role'] !== 'admin') ? " AND t.user_id = '$user_id'" : "";

if ($is_order) {
    $sql = "SELECT t.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
            FROM orders t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.id = '$tx_id' $owner_filter
            LIMIT 1";
} else {
    $sql = "SELECT t.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, p.name AS product_name
            FROM rentals t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN products p ON t.product_id = p.id
            WHERE t.id = '$tx_id' $owner_filter
            LIMIT 1";
}

$query = mysqli_query($conn, $sql);
if (!$query || mysqli_num_rows($query) === 0) {
    echo json_encode(['success' => false, 'error' => 'Data transaksi tidak ditemukan!']);
    exit;
}

$tx = mysqli_fetch_assoc($query);

if (($tx['payment_status'] ?? '') === 'Lunas') {
    echo json_encode(['success' => false, 'error' => 'Pesanan ini sudah lunas!']);
    exit;
}

$gross_amount = (int)round((float)$tx['total_price']);
if ($gross_amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Nominal transaksi tidak valid!']);
    exit;
}

$is_simulator   = midtrans_is_simulator();
$tx_code        = $is_order ? ($tx['order_code'] ?? ('ORD-' . $tx_id)) : ($tx['rental_code'] ?? ('DNT-' . $tx_id));
$existing_token = $tx['snap_token'] ?? '';
$is_mock_token  = (strpos($existing_token, 'MOCK-SNAP-') === 0);

// Reuse token hanya jika mode-nya cocok
if (!empty($existing_token) && (($is_simulator && $is_mock_token) || (!$is_simulator && !$is_mock_token))) {
    echo json_encode([
        'success'      => true,
        'token'        => $existing_token,
        'order_id'     => $tx['midtrans_order_id'] ?? $tx_code,
        'client_key'   => MIDTRANS_CLIENT_KEY,
        'snap_js'      => MIDTRANS_SNAP_JS,
        'gross_amount' => $gross_amount,
        'simulator'    => $is_simulator
    ]);
    exit;
}

$midtrans_order_id = $tx_code . '-' . time();
$item_name = $is_order
    ? ('Pembelian Toko ' . $tx_code)
    : ($tx['product_name'] ?? 'Sewa Alat Outdoor');

if ($is_order) {
    $q_first_item = mysqli_query($conn, "SELECT product_name, quantity FROM order_items WHERE order_id = '$tx_id' LIMIT 1");
    if ($q_first_item && $fi = mysqli_fetch_assoc($q_first_item)) {
        $item_name = $fi['product_name'] . ' (x' . (int)$fi['quantity'] . ')';
    }
}

$customer_name = $is_order ? ($tx['recipient_name'] ?: ($tx['user_name'] ?? 'Pelanggan')) : ($tx['user_name'] ?? 'Pelanggan');
$name_parts    = explode(' ', trim($customer_name), 2);
$first_name    = $name_parts[0] ?? 'Pelanggan';
$last_name     = $name_parts[1] ?? 'Danatrail';
$customer_phone = $is_order ? ($tx['recipient_phone'] ?: ($tx['user_phone'] ?? '081234567890')) : ($tx['user_phone'] ?? '081234567890');

$finish_type = $is_order ? 'order' : 'rental';
$params = [
    'transaction_details' => [
        'order_id'     => $midtrans_order_id,
        'gross_amount' => $gross_amount,
    ],
    'item_details' => [
        [
            'id'       => $is_order ? ('ORD-' . $tx_id) : (string)($tx['product_id'] ?? $tx_id),
            'price'    => $gross_amount,
            'quantity' => 1,
            'name'     => substr($item_name, 0, 50),
        ]
    ],
    'customer_details' => [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $tx['user_email'] ?? 'user@danatrail.com',
        'phone'      => $customer_phone,
    ],
    'callbacks' => [
        'finish' => midtrans_base_url() . '/payment/finish_payment.php?type=' . $finish_type . '&id=' . $tx_id
    ]
];

$table_name = $is_order ? 'orders' : 'rentals';

if ($is_simulator) {
    $mock_token = 'MOCK-SNAP-' . time() . '-' . rand(1000, 9999);
    $safe_mock  = mysqli_real_escape_string($conn, $mock_token);
    $safe_oid   = mysqli_real_escape_string($conn, $midtrans_order_id);
    mysqli_query($conn, "UPDATE `$table_name` SET snap_token='$safe_mock', midtrans_order_id='$safe_oid' WHERE id='$tx_id'");

    echo json_encode([
        'success'      => true,
        'token'        => $mock_token,
        'order_id'     => $midtrans_order_id,
        'client_key'   => MIDTRANS_CLIENT_KEY,
        'snap_js'      => MIDTRANS_SNAP_JS,
        'gross_amount' => $gross_amount,
        'simulator'    => true
    ]);
    exit;
}

$res = midtrans_get_snap_token($params);

if ($res['success']) {
    $token      = $res['token'];
    $safe_token = mysqli_real_escape_string($conn, $token);
    $safe_oid   = mysqli_real_escape_string($conn, $midtrans_order_id);
    mysqli_query($conn, "UPDATE `$table_name` SET snap_token='$safe_token', midtrans_order_id='$safe_oid' WHERE id='$tx_id'");

    echo json_encode([
        'success'      => true,
        'token'        => $token,
        'order_id'     => $midtrans_order_id,
        'client_key'   => MIDTRANS_CLIENT_KEY,
        'snap_js'      => MIDTRANS_SNAP_JS,
        'gross_amount' => $gross_amount,
        'redirect_url' => $res['redirect_url'] ?? null,
        'simulator'    => false
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error'   => $res['error'] ?? 'Gagal membuat sesi pembayaran Midtrans',
        'raw'     => $res
    ]);
}
?>
