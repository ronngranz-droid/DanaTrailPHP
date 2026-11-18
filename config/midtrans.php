<?php
// ==========================================================================
// DANATRAIL MIDTRANS PAYMENT GATEWAY CONFIGURATION
// ==========================================================================

// Muat konfigurasi lokal rahasia jika tersedia (diabaikan oleh .gitignore)
if (file_exists(__DIR__ . '/midtrans.local.php')) {
    require_once __DIR__ . '/midtrans.local.php';
}

// Set ke true jika sudah live production, false untuk sandbox/testing
if (!defined('MIDTRANS_IS_PRODUCTION')) {
    $env_prod = getenv('MIDTRANS_IS_PRODUCTION');
    define('MIDTRANS_IS_PRODUCTION', $env_prod !== false ? filter_var($env_prod, FILTER_VALIDATE_BOOLEAN) : false);
}

// Ambil Midtrans Keys dari midtrans.local.php / Environment Variables (fallback placeholder simulator)
if (!defined('MIDTRANS_SERVER_KEY')) {
    define('MIDTRANS_SERVER_KEY', getenv('MIDTRANS_SERVER_KEY') ?: 'SB-Mid-server-YOUR_SERVER_KEY_HERE');
}
if (!defined('MIDTRANS_CLIENT_KEY')) {
    define('MIDTRANS_CLIENT_KEY', getenv('MIDTRANS_CLIENT_KEY') ?: 'SB-Mid-client-YOUR_CLIENT_KEY_HERE');
}

// Midtrans Snap & Core API Endpoints
if (MIDTRANS_IS_PRODUCTION) {
    define('MIDTRANS_SNAP_URL', 'https://app.midtrans.com/snap/v1/transactions');
    define('MIDTRANS_SNAP_JS', 'https://app.midtrans.com/snap/snap.js');
    define('MIDTRANS_API_URL', 'https://api.midtrans.com/v2');
} else {
    define('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions');
    define('MIDTRANS_SNAP_JS', 'https://app.sandbox.midtrans.com/snap/snap.js');
    define('MIDTRANS_API_URL', 'https://api.sandbox.midtrans.com/v2');
}

/**
 * Cek apakah konfigurasi Midtrans masih menggunakan placeholder simulator
 * @return bool
 */
function midtrans_is_simulator() {
    return strpos(MIDTRANS_SERVER_KEY, 'YOUR_SERVER_KEY_HERE') !== false
        || strpos(MIDTRANS_CLIENT_KEY, 'YOUR_CLIENT_KEY_HERE') !== false;
}

/**
 * Bangun Base URL aplikasi secara dinamis (mendukung localhost maupun hosting/reverse-proxy)
 * @return string
 */
function midtrans_base_url() {
    $forwarded_proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (stripos($forwarded_proto, 'https') !== false);

    $scheme = $is_https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $app_root = str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
    $doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');

    $base_path = '';
    if ($doc_root !== '' && stripos($app_root, $doc_root) === 0) {
        $base_path = substr($app_root, strlen($doc_root));
    } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $base_path = preg_replace('#/(payment|user|admin|config)/[^/]*$#', '', $script);
    }

    $base_path = '/' . trim((string)$base_path, '/');
    if ($base_path === '/') {
        $base_path = '';
    }

    return $scheme . '://' . $host . $base_path;
}

/**
 * Shared cURL helper untuk berkomunikasi dengan Midtrans REST API
 * @param string $url
 * @param string $method 'GET'|'POST'
 * @param array|null $payload
 * @return array ['http_code' => int, 'body' => array|null, 'error' => string|null]
 */
function midtrans_curl_request($url, $method = 'GET', $payload = null) {
    $auth_header = 'Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: ' . $auth_header
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload ?? []));
    }

    $response   = curl_exec($ch);
    $http_code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['http_code' => 0, 'body' => null, 'error' => 'cURL Error: ' . $curl_error];
    }

    return [
        'http_code' => $http_code,
        'body'      => json_decode((string)$response, true),
        'error'     => null
    ];
}

/**
 * Helper function untuk request Snap Token ke API Midtrans
 * @param array $params Parameter transaksi sesuai spesifikasi Midtrans Snap
 * @return array ['success' => bool, 'token' => string|null, 'redirect_url' => string|null, 'error' => string|null]
 */
function midtrans_get_snap_token($params) {
    $res = midtrans_curl_request(MIDTRANS_SNAP_URL, 'POST', $params);
    if ($res['error']) {
        return ['success' => false, 'error' => $res['error']];
    }

    $result = $res['body'];
    if ($res['http_code'] === 201 && isset($result['token'])) {
        return [
            'success'      => true,
            'token'        => $result['token'],
            'redirect_url' => $result['redirect_url'] ?? null
        ];
    }

    $msg = $result['error_messages'][0] ?? 'Gagal membuat snap token Midtrans';
    return ['success' => false, 'error' => $msg, 'raw' => $result, 'http_code' => $res['http_code']];
}

/**
 * Verifikasi status transaksi langsung ke API Midtrans berdasarkan order_id
 * @param string $order_id
 * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
 */
function midtrans_get_transaction_status($order_id) {
    if (empty($order_id)) {
        return ['success' => false, 'data' => null, 'error' => 'Order ID kosong'];
    }

    $url = MIDTRANS_API_URL . '/' . rawurlencode($order_id) . '/status';
    $res = midtrans_curl_request($url, 'GET');

    if ($res['error']) {
        return ['success' => false, 'data' => null, 'error' => $res['error']];
    }

    $body = $res['body'];
    if ($res['http_code'] === 200 && is_array($body) && isset($body['transaction_status'])) {
        return ['success' => true, 'data' => $body, 'error' => null];
    }

    $msg = $body['status_message'] ?? 'Transaksi belum ditemukan di Midtrans';
    return ['success' => false, 'data' => $body, 'error' => $msg];
}

/**
 * Petakan status transaksi Midtrans menjadi status pembayaran aplikasi
 * @param string $transaction_status
 * @param string $fraud_status
 * @return string|null
 */
function midtrans_map_transaction_status($transaction_status, $fraud_status = '') {
    if ($transaction_status === 'capture') {
        if ($fraud_status === 'challenge') {
            return 'Menunggu Verifikasi Admin';
        }
        if ($fraud_status === 'accept' || $fraud_status === '') {
            return 'Lunas';
        }
        return null;
    }

    if ($transaction_status === 'settlement') {
        return 'Lunas';
    }
    if ($transaction_status === 'pending') {
        return 'Menunggu Pembayaran';
    }
    if (in_array($transaction_status, ['deny', 'expire', 'cancel'], true)) {
        return 'Pembayaran Ditolak';
    }

    return null;
}

/**
 * Terapkan pembaruan status pembayaran rental dan potong stok secara idempoten
 * @param mysqli $conn
 * @param int $rental_id
 * @param string $new_payment_status
 * @param string $method_label
 * @return bool
 */
function midtrans_apply_rental_payment_status($conn, $rental_id, $new_payment_status, $method_label) {
    $rid = (int)$rental_id;
    if ($rid <= 0 || empty($new_payment_status)) {
        return false;
    }

    $safe_status = mysqli_real_escape_string($conn, $new_payment_status);
    $safe_method = mysqli_real_escape_string($conn, $method_label);

    if ($new_payment_status === 'Lunas') {
        $find = mysqli_query($conn, "SELECT id, product_id, quantity, status, payment_status FROM rentals WHERE id = '$rid' LIMIT 1");
        if (!$find || mysqli_num_rows($find) === 0) {
            return false;
        }
        $row = mysqli_fetch_assoc($find);
        $pid = (int)$row['product_id'];
        $qty = (int)$row['quantity'];

        if ($row['status'] !== 'Disewa' && $row['status'] !== 'Selesai') {
            mysqli_query($conn, "UPDATE products SET stock = GREATEST(0, stock - $qty) WHERE id='$pid'");
        }

        return (bool)mysqli_query($conn, "UPDATE rentals SET 
            payment_status = 'Lunas',
            payment_method = '$safe_method',
            payment_date = NOW(),
            status = IF(status = 'Selesai', 'Selesai', 'Disewa')
        WHERE id = '$rid'");
    }

    return (bool)mysqli_query($conn, "UPDATE rentals SET 
        payment_status = '$safe_status',
        payment_method = '$safe_method',
        payment_date = NOW()
    WHERE id = '$rid'");
}
?>
