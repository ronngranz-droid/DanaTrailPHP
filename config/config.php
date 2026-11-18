<?php
// Koneksi ke database MySQL menggunakan 127.0.0.1 untuk menghindari latency DNS/IPv6 'localhost' (2000ms -> 15ms)
mysqli_report(MYSQLI_REPORT_OFF);

global $conn;

if (!isset($conn) || !($conn instanceof mysqli) || !@mysqli_ping($conn)) {
    // Port 3307 adalah port aktif XAMPP MariaDB/MySQL
    $conn = @mysqli_connect("127.0.0.1", "root", "", "danatrail", 3307);

    if (!$conn) {
        // Fallback jika MySQL berjalan di port default 3306
        $conn = @mysqli_connect("127.0.0.1", "root", "", "danatrail", 3306);
    }

    if (!$conn) {
        // Fallback terakhir dengan socket default
        $conn = @mysqli_connect("localhost", "root", "", "danatrail");
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

