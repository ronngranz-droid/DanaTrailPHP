<?php
// Koneksi ke database MySQL menggunakan 127.0.0.1 untuk menghindari latency DNS/IPv6 'localhost' (2000ms -> 15ms)
mysqli_report(MYSQLI_REPORT_OFF);

global $conn;

if (!isset($conn) || !($conn instanceof mysqli) || !@mysqli_ping($conn)) {
    $env_host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '';
    $env_user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: '';
    $env_pass = getenv('DB_PASS');
    if ($env_pass === false) {
        $env_pass = getenv('MYSQLPASSWORD') ?: '';
    }
    $env_name = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'danatrail';
    $env_port = (int)(getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306);
    $env_ssl  = getenv('DB_SSL');

    if ($env_host !== '') {
        // Koneksi Cloud MySQL via Environment Variables (Vercel / Railway / Aiven / TiDB)
        $conn = mysqli_init();
        $flags = 0;
        if ($env_ssl !== false && filter_var($env_ssl, FILTER_VALIDATE_BOOLEAN)) {
            mysqli_ssl_set($conn, null, null, null, null, null);
            $flags = MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
        }
        $connected = @$conn->real_connect($env_host, $env_user, $env_pass, $env_name, $env_port, null, $flags);
        if (!$connected) {
            $conn = false;
        }
    } elseif (getenv('VERCEL')) {
        // Berjalan di Vercel tapi Environment Variable DB_HOST belum diatur
        http_response_code(503);
        die(
            '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Konfigurasi Database — DANATRAIL</title></head>'
            . '<body style="font-family:system-ui,-apple-system,sans-serif;background:#041f14;color:#ecfdf5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px;">'
            . '<div style="max-width:560px;background:#073522;border:1px solid #166e4a;border-radius:16px;padding:28px 32px;box-shadow:0 20px 50px rgba(0,0,0,.4);">'
            . '<h2 style="margin:0 0 10px;color:#f5b800;">DANATRAIL — Database Belum Terhubung</h2>'
            . '<p style="line-height:1.6;color:#d1fae5;margin:0 0 14px;">Aplikasi PHP sudah berhasil berjalan di <strong>Vercel</strong>, namun database Cloud MySQL belum dikonfigurasi.</p>'
            . '<p style="line-height:1.6;color:#d1fae5;margin:0 0 10px;">Tambahkan Environment Variables berikut di <strong>Vercel Project Settings &rarr; Environment Variables</strong> lalu Redeploy:</p>'
            . '<pre style="background:#041f14;padding:12px 14px;border-radius:10px;overflow:auto;font-size:13px;color:#a7f3d0;">DB_HOST=host-mysql-cloud-anda\nDB_PORT=3306\nDB_USER=username-db\nDB_PASS=password-db\nDB_NAME=danatrail\nDB_SSL=true</pre>'
            . '</div></body></html>'
        );
    } else {
        // Mode Lokal XAMPP: Port 3307 adalah port aktif XAMPP MariaDB/MySQL
        $conn = @mysqli_connect("127.0.0.1", "root", "", "danatrail", 3307);

        if (!$conn) {
            // Fallback jika MySQL berjalan di port default 3306
            $conn = @mysqli_connect("127.0.0.1", "root", "", "danatrail", 3306);
        }

        if (!$conn) {
            // Fallback terakhir dengan socket default
            $conn = @mysqli_connect("localhost", "root", "", "danatrail");
        }
    }

    if (!$conn) {
        die("Koneksi database gagal: " . mysqli_connect_error());
    }

    mysqli_set_charset($conn, "utf8mb4");
}

// Muat pustaka pembantu diskon, denda rental, dan manajemen stok & kas
require_once __DIR__ . '/discount_fine_helper.php';
require_once __DIR__ . '/stock_cash_helper.php';
?>

