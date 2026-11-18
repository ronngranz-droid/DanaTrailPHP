<?php
header('Content-Type: application/json');
include '../config/config.php';
include '../config/midtrans.php';
/** @var mysqli $conn */

// Ambil raw JSON body dari notifikasi Midtrans
$raw_json = file_get_contents('php://input');
$notif = json_decode($raw_json, true);

if (!$notif || !isset($notif['order_id']) || !isset($notif['transaction_status'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

$order_id           = mysqli_real_escape_string($conn, $notif['order_id']);
$transaction_status = $notif['transaction_status'];
$fraud_status       = $notif['fraud_status'] ?? '';
$status_code        = $notif['status_code'] ?? '';
$gross_amount       = $notif['gross_amount'] ?? '';
$signature_key      = $notif['signature_key'] ?? '';
$payment_type       = trim((string)($notif['payment_type'] ?? 'Midtrans Gateway'));

// Validasi Keamanan Signature Hash (jika server key bukan placeholder)
if (strpos(MIDTRANS_SERVER_KEY, 'YOUR_SERVER_KEY_HERE') === false) {
    $my_signature = hash('sha512', $order_id . $status_code . $gross_amount . MIDTRANS_SERVER_KEY);
    if ($my_signature !== $signature_key) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid signature key']);
        exit;
    }
}

// Logika update status berdasarkan notifikasi Midtrans
$new_payment_status = midtrans_map_transaction_status($transaction_status, $fraud_status);

if ($new_payment_status !== null) {
    $parts = explode('-', $order_id);
    $base_code = $parts[0] . '-' . ($parts[1] ?? '') . '-' . ($parts[2] ?? '');
    $safe_bc = mysqli_real_escape_string($conn, $base_code);

    // 1. Cek di tabel rentals (sewa)
    $find_rent = mysqli_query($conn, "SELECT id FROM rentals WHERE midtrans_order_id = '$order_id' OR rental_code = '$order_id' OR rental_code = '$safe_bc' LIMIT 1");
    if ($find_rent && $row = mysqli_fetch_assoc($find_rent)) {
        midtrans_apply_rental_payment_status($conn, (int)$row['id'], $new_payment_status, 'Midtrans (' . $payment_type . ')');
    } else {
        // 2. Cek di tabel orders (pembelian toko)
        $find_ord = mysqli_query($conn, "SELECT id FROM orders WHERE midtrans_order_id = '$order_id' OR order_code = '$order_id' OR order_code = '$safe_bc' LIMIT 1");
        if ($find_ord && $row_o = mysqli_fetch_assoc($find_ord)) {
            midtrans_apply_order_payment_status($conn, (int)$row_o['id'], $new_payment_status, 'Midtrans (' . $payment_type . ')');
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Notification processed']);
?>
