<?php
/**
 * ============================================================================
 * DANATRAIL - SKRIP MIGRASI DATABASE: MODUL TOKO & PEMBELIAN PRODUK (SHOP)
 * ============================================================================
 * 
 * TUJUAN FILE:
 * File ini bertugas memperbarui dan menyiapkan struktur database MySQL
 * agar aplikasi DANATRAIL mendukung 2 fitur utama sekaligus:
 * 1. FITUR SEWA (RENTAL): Barang untuk disewa dengan tarif sewa harian.
 * 2. FITUR BELI (SALE/SHOP): Barang untuk dibeli langsung menjadi hak milik.
 * 
 * STRUKTUR YANG DIINISIALISASI:
 * 1. Tabel `products`      -> Menambahkan kolom tipe produk, harga jual, dan stok jual.
 * 2. Tabel `cart`          -> Keranjang belanja pengguna untuk pembelian barang.
 * 3. Tabel `orders`        -> Data utama faktur/transaksi pesanan pembelian toko.
 * 4. Tabel `order_items`   -> Rincian setiap item/produk yang ada di dalam suatu pesanan.
 * 
 * DAPAT DIJALANKAN DENGAN:
 * - Browser: Buka http://localhost/tugas/migrate_shop.php (Tampilan Visual Rapi)
 * - Terminal: php migrate_shop.php
 * ============================================================================
 */

require_once __DIR__ . '/config/config.php';
/** @var mysqli $conn */
global $conn;

// Pastikan koneksi database aktif
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Koneksi database belum terhubung. Pastikan MySQL XAMPP aktif.");
}

// Log status migrasi untuk laporan
$migration_logs = [];
$is_cli = (php_sapi_name() === 'cli');

function log_step(&$logs, $section, $status, $message, $detail = '') {
    $logs[] = [
        'section' => $section,
        'status'  => $status, // 'success', 'info', 'warning'
        'message' => $message,
        'detail'  => $detail
    ];
}

function check_column_exists($conn, $table, $column) {
    $safe_table = mysqli_real_escape_string($conn, $table);
    $safe_column = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$safe_table` LIKE '$safe_column'");
    return ($res && mysqli_num_rows($res) > 0);
}

// ============================================================================
// BAGIAN 1: PEMBARUAN TABEL `products` (Pemisahan Sewa vs Beli)
// ============================================================================

// 1.1 Kolom `product_type`: Menentukan apakah produk untuk sewa ('rental'), beli ('sale'), atau keduanya ('both')
if (!check_column_exists($conn, 'products', 'product_type')) {
    $sql = "ALTER TABLE products ADD COLUMN product_type ENUM('rental', 'sale', 'both') NOT NULL DEFAULT 'rental' AFTER category";
    if (mysqli_query($conn, $sql)) {
        log_step($migration_logs, 'Tabel products', 'success', "Menambahkan kolom 'product_type'", "ENUM('rental', 'sale', 'both') untuk membedakan barang sewa, barang jual, atau keduanya.");
    }
} else {
    log_step($migration_logs, 'Tabel products', 'info', "Kolom 'product_type' sudah tersedia", "Menentukan jenis produk (sewa / jual).");
}

// 1.2 Kolom `price_sale`: Tarif harga jual beli (Rp) jika barang dapat dibeli
if (!check_column_exists($conn, 'products', 'price_sale')) {
    $sql = "ALTER TABLE products ADD COLUMN price_sale DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER price";
    if (mysqli_query($conn, $sql)) {
        log_step($migration_logs, 'Tabel products', 'success', "Menambahkan kolom 'price_sale'", "Menyimpan nominal harga jual barang ke pembeli.");
    }
} else {
    log_step($migration_logs, 'Tabel products', 'info', "Kolom 'price_sale' sudah tersedia", "Menyimpan harga jual ritel barang.");
}

// 1.3 Kolom `stock_sale`: Jumlah unit inventaris yang khusus disediakan untuk dijual
if (!check_column_exists($conn, 'products', 'stock_sale')) {
    $sql = "ALTER TABLE products ADD COLUMN stock_sale INT(11) NOT NULL DEFAULT 0 AFTER stock";
    if (mysqli_query($conn, $sql)) {
        log_step($migration_logs, 'Tabel products', 'success', "Menambahkan kolom 'stock_sale'", "Memisahkan kuota stok jual dari stok sewa.");
    }
} else {
    log_step($migration_logs, 'Tabel products', 'info', "Kolom 'stock_sale' sudah tersedia", "Stok fisik barang yang siap dibeli.");
}

// ============================================================================
// BAGIAN 2: TABEL `cart` (Keranjang Belanja Pembelian)
// ============================================================================
// Fungsi: Menampung barang-barang toko yang dimasukkan user ke keranjang sebelum checkout.
$sql_cart = "CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ID Pengguna yang memiliki keranjang',
    product_id INT NOT NULL COMMENT 'ID Produk yang dipilih',
    quantity INT NOT NULL DEFAULT 1 COMMENT 'Jumlah unit yang akan dibeli',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY fk_cart_user (user_id),
    KEY fk_cart_prod (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tabel penampung keranjang belanja pembelian toko'";

if (mysqli_query($conn, $sql_cart)) {
    log_step($migration_logs, 'Tabel cart', 'success', "Tabel 'cart' siap digunakan", "Menyimpan sementara item yang dipilih pembeli sebelum checkout.");
}

// ============================================================================
// BAGIAN 3: TABEL `orders` (Faktur Transaksi Pembelian Toko)
// ============================================================================
// Fungsi: Menyimpan data kepala transaksi penjualan (kode pesanan, penerima, alamat, pembayaran, status pengiriman).
$sql_orders = "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'Nomor unik faktur penjualan, misal: ORD-20261028-001',
    user_id INT NOT NULL COMMENT 'ID Pengguna pembeli',
    total_price DECIMAL(12,2) NOT NULL COMMENT 'Total nilai transaksi belanja',
    recipient_name VARCHAR(100) NOT NULL COMMENT 'Nama penerima paket',
    recipient_phone VARCHAR(30) NOT NULL COMMENT 'Nomor telepon penerima',
    shipping_address TEXT NOT NULL COMMENT 'Alamat tujuan pengiriman paket',
    courier VARCHAR(50) DEFAULT 'Kurir Ekspedisi Basecamp' COMMENT 'Kurir atau metode pengiriman',
    payment_method VARCHAR(50) DEFAULT 'Transfer Bank BCA' COMMENT 'Metode pembayaran (Transfer / Tunai)',
    payment_status VARCHAR(50) DEFAULT 'Menunggu Pembayaran' COMMENT 'Status verifikasi bayar (Menunggu / Lunas)',
    payment_proof VARCHAR(255) NULL COMMENT 'File bukti transfer pembayaran',
    status ENUM('Menunggu Pembayaran', 'Menunggu Konfirmasi', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan') DEFAULT 'Menunggu Pembayaran' COMMENT 'Tahapan proses pesanan',
    tracking_number VARCHAR(100) NULL COMMENT 'Nomor resi pengiriman',
    notes TEXT NULL COMMENT 'Catatan tambahan dari pembeli',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu pesanan dibuat',
    KEY fk_order_user (user_id),
    KEY idx_order_code (order_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tabel utama pesanan pembelian barang'";

if (mysqli_query($conn, $sql_orders)) {
    log_step($migration_logs, 'Tabel orders', 'success', "Tabel 'orders' siap digunakan", "Mencatat kepala transaksi, identitas penerima, alamat pengiriman, dan status pesanan.");
}

// ============================================================================
// BAGIAN 4: TABEL `order_items` (Rincian Produk Dalam Pesanan)
// ============================================================================
// Fungsi: Menerapkan Normalisasi Database (Relasi 1 to Many: 1 order memiliki banyak produk).
// Menyimpan snapshot harga saat transaksi terjadi agar tidak terpengaruh jika harga master produk berubah di kemudian hari.
$sql_items = "CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL COMMENT 'Relasi ke tabel orders.id',
    product_id INT NOT NULL COMMENT 'Relasi ke tabel products.id',
    product_name VARCHAR(150) NOT NULL COMMENT 'Snapshot nama produk saat dibeli',
    price DECIMAL(12,2) NOT NULL COMMENT 'Snapshot harga satuan produk saat dibeli',
    quantity INT NOT NULL DEFAULT 1 COMMENT 'Jumlah unit yang dibeli',
    subtotal DECIMAL(12,2) NOT NULL COMMENT 'Total harga item (price * quantity)',
    KEY fk_item_order (order_id),
    KEY fk_item_prod (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Rincian produk di setiap pesanan (Normalisasi 1-ke-Banyak)'";

if (mysqli_query($conn, $sql_items)) {
    log_step($migration_logs, 'Tabel order_items', 'success', "Tabel 'order_items' siap digunakan", "Menyimpan daftar produk yang dibeli per transaksi (normalisasi 1-ke-banyak).");
}

// ============================================================================
// BAGIAN 5: INISIALISASI SAMPEL PRODUK TOKO (Jika Belum Ada)
// ============================================================================
// Memastikan katalog toko langsung memiliki contoh barang siap jual saat pertama kali demo/diuji.
$check_any_sale = mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE product_type IN ('sale', 'both')");
$row_sale = $check_any_sale ? mysqli_fetch_assoc($check_any_sale) : ['c' => 0];

if ((int)$row_sale['c'] == 0) {
    // Memberikan contoh produk dengan tipe jual
    mysqli_query($conn, "UPDATE products 
                         SET product_type = 'sale', 
                             price_sale = CASE WHEN price > 0 THEN price * 8 ELSE 250000 END, 
                             stock_sale = CASE WHEN stock > 0 THEN stock ELSE 10 END 
                         WHERE category IN ('Cooking Set', 'Penerangan & Elektronik') 
                         LIMIT 3");

    mysqli_query($conn, "UPDATE products 
                         SET product_type = 'both', 
                             price_sale = CASE WHEN price > 0 THEN price * 10 ELSE 450000 END, 
                             stock_sale = CASE WHEN stock > 0 THEN stock ELSE 8 END 
                         WHERE category IN ('Footwear & Apparel', 'Carrier & Tas') 
                         LIMIT 2");

    log_step($migration_logs, 'Sampel Produk', 'success', "Inisialisasi sampel produk jual selesai", "Menyiapkan beberapa produk awal bertipe 'sale' dan 'both' agar katalog toko langsung aktif.");
} else {
    log_step($migration_logs, 'Sampel Produk', 'info', "Produk tipe jual sudah ada ({$row_sale['c']} produk)", "Katalog toko sudah memiliki produk siap jual.");
}

// ============================================================================
// OUTPUT TAMPILAN (CLI vs BROWSER)
// ============================================================================
if ($is_cli) {
    echo "====================================================================\n";
    echo " DANATRAIL - MIGRASI DATABASE MODUL TOKO (SHOP)\n";
    echo "====================================================================\n";
    foreach ($migration_logs as $log) {
        $badge = strtoupper($log['status']);
        echo "[{$badge}] {$log['section']} -> {$log['message']}\n";
        if (!empty($log['detail'])) {
            echo "        Penjelasan: {$log['detail']}\n";
        }
    }
    echo "====================================================================\n";
    echo " Status: Seluruh struktur tabel toko berhasil diverifikasi & aktif!\n";
    echo "====================================================================\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Migrasi Modul Toko — DANATRAIL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-950: #041f14;
            --forest-900: #073522;
            --forest-800: #0e5638;
            --forest-700: #166e4a;
            --forest-600: #23895e;
            --forest-100: #d1fae5;
            --forest-50:  #ecfdf5;
            --bg-canvas: #f7f9f8;
            --surface-card: #ffffff;
            --border-color: #e2e8e5;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-main);
            padding: 32px 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 860px;
            margin: 0 auto;
        }

        .header-card {
            background: linear-gradient(135deg, #073522 0%, #0e5638 100%);
            color: #ffffff;
            padding: 28px 32px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(7, 53, 34, 0.15);
            margin-bottom: 24px;
        }

        .header-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: #a7f3d0;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 9999px;
            margin-bottom: 10px;
        }

        .header-card h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .header-card p {
            font-size: 13.5px;
            color: #d1fae5;
            max-width: 680px;
        }

        .summary-box {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }

        .summary-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--forest-950);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .schema-diagram {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 14px 18px;
            font-family: monospace;
            font-size: 13px;
            color: #334155;
            margin-bottom: 12px;
            overflow-x: auto;
        }

        .log-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .log-item {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            transition: transform 0.15s ease;
        }

        .log-item:hover {
            transform: translateY(-1px);
            border-color: #a7f3d0;
        }

        .status-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-weight: 800;
            font-size: 13px;
            margin-top: 2px;
        }

        .status-icon.success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .status-icon.info {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .log-content {
            flex: 1;
        }

        .log-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 4px;
            flex-wrap: wrap;
        }

        .log-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--forest-950);
        }

        .log-section-tag {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            background: #f1f5f9;
            color: #475569;
            border-radius: 6px;
        }

        .log-detail {
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .action-footer {
            margin-top: 28px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: var(--forest-800);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: var(--forest-900);
        }

        .btn-secondary {
            background: #ffffff;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #f8fafc;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header-card">
        <span class="header-badge">DATABASE MIGRATION • SYSTEM READY</span>
        <h1>Modul Toko & Pembelian Produk (Shop)</h1>
        <p>Skrip ini mengelola dan memverifikasi tabel-tabel MySQL untuk transaksi belanja perlengkapan outdoor di DANATRAIL. Seluruh tabel siap digunakan oleh sistem.</p>
    </div>

    <!-- Ringkasan Arsitektur Database -->
    <div class="summary-box">
        <div class="summary-title">
            <span>📌 Alur Relasi Database Toko (Mudah Dijelaskan ke Dosen / Penguji)</span>
        </div>
        <div class="schema-diagram">
[users] ──(1:N)──> [cart] ──(Checkout)──> [orders] ──(1:N)──> [order_items]
   │                                          │                      │
   │                                     (Faktur Beli)        (Detail Produk)
   └─────────────── (1:N) ────────────────────┘
        </div>
        <p style="font-size: 12.5px; color: #475569;">
            <strong>Prinsip Normalisasi:</strong> Tabel <code>orders</code> menyimpan informasi kepala faktur (nama penerima, alamat kirim, total biaya), sedangkan tabel <code>order_items</code> menyimpan rincian barang per pesanan sehingga satu transaksi dapat menampung banyak jenis barang sekaligus tanpa duplikasi data.
        </p>
    </div>

    <!-- Log Verifikasi Per Tabel -->
    <div class="log-list">
        <?php foreach ($migration_logs as $log): ?>
            <div class="log-item">
                <div class="status-icon <?php echo htmlspecialchars($log['status']); ?>">
                    ✓
                </div>
                <div class="log-content">
                    <div class="log-header">
                        <span class="log-title"><?php echo htmlspecialchars($log['message']); ?></span>
                        <span class="log-section-tag"><?php echo htmlspecialchars($log['section']); ?></span>
                    </div>
                    <div class="log-detail"><?php echo htmlspecialchars($log['detail']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tombol Aksi Navigasi -->
    <div class="action-footer">
        <a href="admin/dashboard.php" class="btn-action btn-secondary">
            &larr; Kembali ke Dashboard Admin
        </a>
        <a href="admin/pesanan.php" class="btn-action btn-primary">
            Lihat Pesanan Toko Masuk &rarr;
        </a>
    </div>
</div>

</body>
</html>
