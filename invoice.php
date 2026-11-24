<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/config/config.php';
global $conn;
/** @var mysqli $conn */

// Pastikan user atau admin sudah login
if (!isset($_SESSION['role'])) {
    header("location:login.php?pesan=Silakan login terlebih dahulu untuk melihat invoice!");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$code = isset($_GET['code']) ? mysqli_real_escape_string($conn, trim($_GET['code'])) : '';
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$order_code = isset($_GET['order_code']) ? mysqli_real_escape_string($conn, trim($_GET['order_code'])) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

$is_store_order = ($order_id > 0 || !empty($order_code) || $type === 'order' || (strpos($code, 'ORD-') === 0));

$wa_admin_num = "6288996016686";

// Inisialisasi variabel template default untuk static analysis IDE
$order = [];
$items = [];
$data = [];
$rental_code = '';
$tgl_sewa = '';
$tgl_kembali = '';
$durasi = 0;
$qty = 0;
$harga_satuan = 0;
$subtotal_rental = 0;
$subtotal = 0;
$diskon = 0;
$discount_code = '';
$late_days = 0;
$denda = 0;
$fine_pay_status = 'Bebas Denda';
$biaya_tambahan = 0;
$total_akhir = 0;
$payment_status = 'Menunggu Pembayaran';
$status_sewa = 'Menunggu Konfirmasi';
$jaminan = 'KTP Asli';
$inv_code = '';
$wa_text = '';

if ($is_store_order) {
    $search_code = !empty($order_code) ? $order_code : $code;
    $where_order = ($order_id > 0) ? "o.id = '$order_id'" : "o.order_code = '$search_code'";
    $q_order = mysqli_query($conn, "SELECT o.*, u.name as user_name, u.email as user_email, u.phone as user_phone, u.address as user_address 
                                   FROM orders o 
                                   LEFT JOIN users u ON o.user_id = u.id 
                                   WHERE $where_order LIMIT 1");
    if (!$q_order || mysqli_num_rows($q_order) == 0) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Faktur Pesanan Toko Tidak Ditemukan</h2><p><a href='index.php'>Kembali ke Beranda</a></p></div>");
    }
    $order = mysqli_fetch_assoc($q_order);
    if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $order['user_id']) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Akses Ditolak</h2><p>Anda tidak memiliki izin untuk melihat invoice ini.</p></div>");
    }
    $order_items_q = mysqli_query($conn, "SELECT oi.*, p.category as product_category FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = '{$order['id']}'");
    $items = [];
    if ($order_items_q) {
        while ($it = mysqli_fetch_assoc($order_items_q)) {
            $items[] = $it;
        }
    }
    $inv_code = $order['order_code'];
    $wa_text = urlencode("Halo Admin DANATRAIL, saya ingin konfirmasi pesanan toko dengan No Faktur: " . $inv_code);
} else {
    // Rental Query
    if ($id <= 0 && empty($code)) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Faktur / Invoice Tidak Ditemukan</h2><p><a href='index.php'>Kembali ke Beranda</a></p></div>");
    }
    $where = ($id > 0) ? "r.id = '$id'" : "r.rental_code = '$code'";
    $sql = "SELECT r.*, 
                   u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.address AS user_address,
                   p.name AS product_name, p.category AS product_category, p.price AS product_price, p.image AS product_image,
                   rf.fine_amount AS recorded_fine_amount, rf.late_days AS recorded_late_days, rf.payment_status AS fine_payment_status_db
            FROM rentals r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            LEFT JOIN rental_fines rf ON r.id = rf.rental_id
            WHERE $where LIMIT 1";
    $query = mysqli_query($conn, $sql);
    if (!$query || mysqli_num_rows($query) == 0) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Data Transaksi Tidak Ditemukan</h2><p><a href='index.php'>Kembali ke Beranda</a></p></div>");
    }
    $data = mysqli_fetch_assoc($query);
    if ($_SESSION['role'] !== 'admin' && $_SESSION['user_id'] != $data['user_id']) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Akses Ditolak</h2><p>Anda tidak memiliki izin untuk melihat invoice ini.</p></div>");
    }
    $rental_code = $data['rental_code'];
    $tgl_sewa = date('d F Y', strtotime($data['rental_date']));
    $tgl_kembali = date('d F Y', strtotime($data['return_date']));
    $durasi = (int)$data['duration_days'];
    $qty = (int)$data['quantity'];
    $harga_satuan = (float)$data['product_price'];

    // Perhitungan backend aman & terpisah:
    $subtotal_rental = (float)($data['subtotal_price'] > 0 ? $data['subtotal_price'] : ($harga_satuan * $qty * $durasi));
    $subtotal = $subtotal_rental;
    $diskon = (float)($data['discount_amount'] ?? 0);
    $discount_code = $data['discount_code'] ?? '';

    // Hitung denda keterlambatan
    $fine_calc = calculate_rental_fine($data);
    $late_days = (isset($data['recorded_late_days']) && $data['recorded_late_days'] !== null) ? (int)$data['recorded_late_days'] : (int)($fine_calc['late_days'] ?? 0);
    $denda = (isset($data['recorded_fine_amount']) && $data['recorded_fine_amount'] !== null) ? (float)$data['recorded_fine_amount'] : (float)($fine_calc['fine_amount'] ?? 0);
    $fine_pay_status = $data['fine_payment_status_db'] ?? $data['fine_payment_status'] ?? ($late_days > 0 ? 'Belum Dibayar' : 'Bebas Denda');

    $biaya_tambahan = 0; // Biaya Tambahan

    // Formula: Total Akhir = Subtotal - Diskon + Denda + Biaya Tambahan
    $total_akhir = $subtotal_rental - $diskon + $denda + $biaya_tambahan;

    $payment_status = $data['payment_status'] ?? 'Menunggu Pembayaran';
    $status_sewa = $data['status'];
    $jaminan = $data['guarantee_type'] ?? 'KTP Asli';
    $inv_code = $rental_code;
    $wa_text = urlencode("Halo Admin DANATRAIL, saya ingin konfirmasi sewa untuk Kode Faktur: " . $rental_code);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($inv_code); ?> — DANATRAIL Outdoor</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest-950: #041f14;
            --forest-900: #073522;
            --forest-800: #0e5638;
            --forest-700: #166e4a;
            --forest-600: #23895e;
            --gold-500: #f5b800;
            --gold-600: #d99b00;
            --emerald-600: #059669;
            --emerald-500: #10b981;
            --emerald-50: #ecfdf5;
            --text-main: #0f172a;
            --text-muted: #475569;
            --border: #e2e8f0;
            --bg-canvas: #f8fafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-main);
            padding: 24px 16px;
            font-size: 13.5px;
            line-height: 1.5;
        }

        /* Top Action Bar (Hide when printing) */
        .action-bar {
            max-width: 820px;
            margin: 0 auto 16px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #ffffff;
            padding: 12px 18px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }

        .btn-outline {
            background: #ffffff;
            border: 1.5px solid var(--border);
            color: var(--text-muted);
        }

        .btn-outline:hover {
            border-color: var(--forest-800);
            color: var(--forest-900);
            background: #f8fafc;
        }

        .btn-primary {
            background: var(--forest-900);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: var(--forest-800);
        }

        .btn-wa {
            background: #25d366;
            color: #ffffff;
        }

        .btn-wa:hover {
            background: #1da851;
        }

        /* Invoice Container Sheet */
        .invoice-paper {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 36px 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            position: relative;
        }

        /* Header */
        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 24px;
            border-bottom: 2px solid var(--forest-900);
            margin-bottom: 28px;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo-wrap {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: var(--forest-900);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .brand-logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .brand-info h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--forest-900);
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .brand-info p {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .inv-meta {
            text-align: right;
        }

        .inv-title {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--forest-900);
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        .inv-code {
            font-family: monospace;
            font-size: 15px;
            font-weight: 800;
            color: var(--emerald-600);
            background: var(--emerald-50);
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 4px;
        }

        .inv-date {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* 2-Column Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
        }

        .info-card h3 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--forest-800);
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-card table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-card td {
            padding: 3px 0;
            font-size: 12.5px;
            vertical-align: top;
        }

        .info-card td.label {
            color: var(--text-muted);
            width: 110px;
            font-weight: 500;
        }

        .info-card td.val {
            font-weight: 700;
            color: var(--text-main);
        }

        /* Items Table */
        .table-wrap {
            margin-bottom: 24px;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .item-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .item-table th {
            background: var(--forest-900);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
        }

        .item-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }

        .item-table tr:last-child td {
            border-bottom: none;
        }

        .item-title {
            font-weight: 700;
            color: var(--text-main);
        }

        .item-cat {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        /* Totals & Status Section */
        .summary-section {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .status-box {
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            background: #ffffff;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .status-lunas {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .status-pending {
            background: #fef9c3;
            color: #a16207;
            border: 1px solid #fde047;
        }
        .status-verif {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #7dd3fc;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px 0;
            font-size: 13px;
        }

        .totals-table td.total-label {
            color: var(--text-muted);
            font-weight: 500;
        }

        .totals-table td.total-val {
            text-align: right;
            font-weight: 700;
        }

        .totals-table tr.grand-row td {
            border-top: 2px solid var(--forest-900);
            padding-top: 10px;
            font-size: 16px;
            font-weight: 800;
            color: var(--forest-900);
            font-family: 'Outfit', sans-serif;
        }

        /* Terms & Conditions */
        .terms-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 30px;
            border-left: 3px solid var(--forest-700);
        }

        .terms-box h4 {
            font-size: 12px;
            font-weight: 700;
            color: var(--forest-900);
            margin-bottom: 4px;
        }

        .terms-box ol {
            padding-left: 18px;
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.45;
        }

        /* Print Specific Styling */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                font-size: 12px !important;
            }
            .action-bar {
                display: none !important;
            }
            .invoice-paper {
                border: none !important;
                box-shadow: none !important;
                padding: 15px !important;
                max-width: 100% !important;
            }
            .btn-action {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Action Bar (Hide in Print) -->
    <div class="action-bar">
        <div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="<?php echo $is_store_order ? 'admin/pesanan.php' : 'admin/transaksi.php'; ?>" class="btn-action btn-outline">
                    ← Kembali ke <?php echo $is_store_order ? 'Pesanan Toko' : 'Data Sewa'; ?>
                </a>
            <?php else: ?>
                <a href="<?php echo $is_store_order ? 'user/pesanan.php' : 'user/riwayat.php'; ?>" class="btn-action btn-outline">
                    ← Kembali ke <?php echo $is_store_order ? 'Pesanan Belanja' : 'Riwayat Sewa'; ?>
                </a>
            <?php endif; ?>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="https://wa.me/<?php echo $wa_admin_num; ?>?text=<?php echo $wa_text; ?>" target="_blank" class="btn-action btn-wa">
                WhatsApp Admin
            </a>
            <button onclick="window.print()" class="btn-action btn-primary">
                Cetak / Simpan PDF
            </button>
        </div>
    </div>

    <!-- Official Invoice Document -->
    <div class="invoice-paper">
        <!-- Header -->
        <div class="inv-header">
            <div class="brand-block">
                <div class="brand-logo-wrap">
                    <img src="assets/logo.png" alt="DANATRAIL" onerror="this.src='https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=80&auto=format&fit=crop&q=60'">
                </div>
                <div class="brand-info">
                    <h1>DANATRAIL</h1>
                    <p>Platform Rental & Toko Perlengkapan Outdoor Gunung</p>
                    <p>Jl. Raya Basecamp No. 07 • WhatsApp: +<?php echo $wa_admin_num; ?></p>
                </div>
            </div>
            <div class="inv-meta">
                <div class="inv-title"><?php echo $is_store_order ? 'Faktur Pembelian' : 'Faktur Sewa'; ?></div>
                <div class="inv-code"><?php echo htmlspecialchars($inv_code); ?></div>
                <div class="inv-date">Tgl: <?php echo date('d/m/Y H:i', strtotime($is_store_order ? ($order['created_at'] ?? 'now') : ($data['created_at'] ?? 'now'))); ?></div>
            </div>
        </div>

        <?php if ($is_store_order): ?>
            <!-- STORE ORDER INVOICE BODY -->
            <div class="info-grid">
                <div class="info-card">
                    <h3>Penerima & Alamat Pengiriman</h3>
                    <table>
                        <tr>
                            <td class="label">Nama Penerima</td>
                            <td class="val">: <?php echo htmlspecialchars($order['recipient_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">No. WhatsApp</td>
                            <td class="val">: <?php echo htmlspecialchars($order['recipient_phone']); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Alamat Lengkap</td>
                            <td class="val">: <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="info-card">
                    <h3>Ekspedisi & Status Pesanan</h3>
                    <table>
                        <tr>
                            <td class="label">Kurir Pengiriman</td>
                            <td class="val">: <?php echo htmlspecialchars($order['courier'] ?? 'Kurir Reguler'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">No. Resi / Tracking</td>
                            <td class="val">: 
                                <?php if (!empty($order['tracking_number'])): ?>
                                    <strong style="font-family: monospace; color: var(--forest-800);"><?php echo htmlspecialchars($order['tracking_number']); ?></strong>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">Sedang Diproses</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Status Pesanan</td>
                            <td class="val">: <strong><?php echo htmlspecialchars($order['status']); ?></strong></td>
                        </tr>
                        <tr>
                            <td class="label">Metode Bayar</td>
                            <td class="val">: <?php echo htmlspecialchars($order['payment_method']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-wrap">
                <table class="item-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align:center;">No</th>
                            <th>Produk Perlengkapan</th>
                            <th style="text-align:right;">Harga Satuan</th>
                            <th style="text-align:center;">Jumlah</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($items as $item): 
                        ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $no++; ?></td>
                                <td>
                                    <div class="item-title"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="item-cat">Produk Toko DANATRAIL (Baru)</div>
                                </td>
                                <td style="text-align:right;">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                <td style="text-align:center; font-weight: 700;"><?php echo (int)$item['quantity']; ?> Unit</td>
                                <td style="text-align:right; font-weight: 700;">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary & Totals -->
            <div class="summary-section">
                <div class="status-box">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Status Pembayaran:</div>
                    <div>
                        <?php if ($order['payment_status'] === 'Lunas'): ?>
                            <span class="status-badge status-lunas">✓ Pembayaran Lunas</span>
                        <?php elseif ($order['payment_status'] === 'Menunggu Verifikasi'): ?>
                            <span class="status-badge status-verif">Menunggu Verifikasi</span>
                        <?php else: ?>
                            <span class="status-badge status-pending">Menunggu Pembayaran</span>
                        <?php endif; ?>

                        <span class="status-badge" style="background:#f1f5f9; color:var(--text-main); border:1px solid #cbd5e1;">
                            Status: <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </div>
                    <?php if (!empty($order['notes'])): ?>
                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px; font-style: italic;">
                            Catatan: "<?php echo htmlspecialchars($order['notes']); ?>"
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <table class="totals-table">
                        <tr>
                            <td class="total-label">Subtotal Produk</td>
                            <td class="total-val">Rp <?php echo number_format($order['subtotal_price'] > 0 ? $order['subtotal_price'] : ($order['total_price'] + ($order['discount_amount'] ?? 0)), 0, ',', '.'); ?></td>
                        </tr>
                        <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>
                        <tr>
                            <td class="total-label" style="color: #166e4a;">
                                Diskon Belanja <?php echo !empty($order['discount_code']) ? '(' . htmlspecialchars($order['discount_code']) . ')' : ''; ?>
                            </td>
                            <td class="total-val" style="color: #166e4a;">- Rp <?php echo number_format($order['discount_amount'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="total-label">Biaya Pengiriman (<?php echo htmlspecialchars($order['courier'] ?? 'Kurir'); ?>)</td>
                            <td class="total-val">
                                <?php if ((float)($order['shipping_cost'] ?? 0) > 0): ?>
                                    Rp <?php echo number_format((float)$order['shipping_cost'], 0, ',', '.'); ?>
                                <?php else: ?>
                                    <span style="color: var(--emerald-600); font-weight: 700;">Gratis Ongkir</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="grand-row">
                            <td>TOTAL AKHIR</td>
                            <td style="text-align: right;">Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Terms & Conditions for Sale -->
            <div class="terms-box" style="margin-bottom: 0;">
                <h4>Syarat & Ketentuan Pembelian Toko DANATRAIL:</h4>
                <ol>
                    <li>Produk yang dibeli merupakan barang baru 100% original bergaransi resmi.</li>
                    <li>Pengiriman dilakukan pada hari kerja setelah pembayaran terverifikasi lunas.</li>
                    <li>Harap mendokumentasikan video unboxing saat paket diterima untuk klaim garansi pengiriman.</li>
                </ol>
            </div>

        <?php else: ?>
            <!-- RENTAL INVOICE BODY -->
            <div class="info-grid">
                <div class="info-card">
                    <h3>Data Penyewa</h3>
                    <table>
                        <tr>
                            <td class="label">Nama Lengkap</td>
                            <td class="val">: <?php echo htmlspecialchars($data['user_name'] ?? 'Pelanggan'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Email</td>
                            <td class="val">: <?php echo htmlspecialchars($data['user_email'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">No. WhatsApp</td>
                            <td class="val">: <?php echo htmlspecialchars($data['user_phone'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <td class="label">Alamat / Asal</td>
                            <td class="val">: <?php echo htmlspecialchars($data['user_address'] ?? 'Jawa Barat'); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="info-card">
                    <h3>Rincian Jadwal & Jaminan</h3>
                    <table>
                        <tr>
                            <td class="label">Tgl Mulai Sewa</td>
                            <td class="val">: <?php echo $tgl_sewa; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Tgl Pengembalian</td>
                            <td class="val">: <?php echo $tgl_kembali; ?></td>
                        </tr>
                        <tr>
                            <td class="label">Durasi Sewa</td>
                            <td class="val">: <?php echo $durasi; ?> Hari</td>
                        </tr>
                        <tr>
                            <td class="label">Dokumen Jaminan</td>
                            <td class="val">: <strong style="color: var(--forest-800);"><?php echo htmlspecialchars($jaminan); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-wrap">
                <table class="item-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align:center;">No</th>
                            <th>Perlengkapan Outdoor</th>
                            <th style="text-align:right;">Tarif / Hari</th>
                            <th style="text-align:center;">Durasi</th>
                            <th style="text-align:center;">Jumlah</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:center;">1</td>
                            <td>
                                <div class="item-title"><?php echo htmlspecialchars($data['product_name'] ?? 'Perlengkapan Outdoor'); ?></div>
                                <div class="item-cat">Kategori: <?php echo htmlspecialchars($data['product_category'] ?? 'Outdoor Gear'); ?></div>
                            </td>
                            <td style="text-align:right;">Rp <?php echo number_format($harga_satuan, 0, ',', '.'); ?></td>
                            <td style="text-align:center;"><?php echo $durasi; ?> Hari</td>
                            <td style="text-align:center; font-weight: 700;"><?php echo $qty; ?> Unit</td>
                            <td style="text-align:right; font-weight: 700;">Rp <?php echo number_format($subtotal_rental, 0, ',', '.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Summary & Totals -->
            <div class="summary-section">
                <div class="status-box">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px;">Status Pembayaran & Sewa:</div>
                    <div>
                        <?php if ($payment_status === 'Lunas'): ?>
                            <span class="status-badge status-lunas">✓ Pembayaran Lunas</span>
                        <?php elseif ($payment_status === 'Menunggu Verifikasi Admin'): ?>
                            <span class="status-badge status-verif">Menunggu Verifikasi</span>
                        <?php else: ?>
                            <span class="status-badge status-pending">Menunggu Pembayaran</span>
                        <?php endif; ?>

                        <span class="status-badge" style="background:#f1f5f9; color:var(--text-main); border:1px solid #cbd5e1;">
                            Status Sewa: <?php echo htmlspecialchars($status_sewa); ?>
                        </span>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                        Metode Pembayaran: <strong><?php echo htmlspecialchars($data['payment_method'] ?? 'Transfer Bank'); ?></strong>
                    </div>
                    <?php if (!empty($data['notes'])): ?>
                        <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 6px; font-style: italic;">
                            Catatan: "<?php echo htmlspecialchars($data['notes']); ?>"
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <table class="totals-table">
                        <tr>
                            <td class="total-label">Subtotal Rental</td>
                            <td class="total-val">Rp <?php echo number_format($subtotal_rental, 0, ',', '.'); ?></td>
                        </tr>
                        <?php if ($diskon > 0): ?>
                        <tr>
                            <td class="total-label" style="color: #166e4a;">
                                Diskon Rental <?php echo !empty($discount_code) ? '(' . htmlspecialchars($discount_code) . ')' : ''; ?>
                            </td>
                            <td class="total-val" style="color: #166e4a;">- Rp <?php echo number_format($diskon, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($denda > 0): ?>
                        <tr>
                            <td class="total-label" style="color: #dc2626;">
                                Denda Keterlambatan (<?php echo $late_days; ?> Hari)
                                <div style="font-size: 11px; font-weight: normal; color: var(--text-muted); margin-top: 1px;">
                                    Status Denda: <strong><?php echo htmlspecialchars($fine_pay_status); ?></strong>
                                </div>
                            </td>
                            <td class="total-val" style="color: #dc2626;">+ Rp <?php echo number_format($denda, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="total-label">Biaya Tambahan</td>
                            <td class="total-val">Rp <?php echo number_format($biaya_tambahan, 0, ',', '.'); ?></td>
                        </tr>
                        <tr class="grand-row">
                            <td>TOTAL AKHIR</td>
                            <td style="text-align: right;">Rp <?php echo number_format($total_akhir, 0, ',', '.'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Terms & Conditions -->
            <div class="terms-box" style="margin-bottom: 0;">
                <h4>Syarat & Ketentuan Pengambilan & Pengembalian:</h4>
                <ol>
                    <li>Penyewa wajib membawa kartu identitas asli (<?php echo htmlspecialchars($jaminan); ?>) saat serah terima barang di Basecamp DANATRAIL.</li>
                    <li>Barang wajib dicek bersama saat pengambilan dan pengembalian dalam kondisi bersih dan lengkap.</li>
                    <li>Keterlambatan pengembalian tanpa konfirmasi akan dikenakan denda keterlambatan per hari sesuai tarif normal.</li>
                    <li>Kerusakan atau kehilangan unit menjadi tanggung jawab penyewa untuk mengganti biaya perbaikan/unit baru.</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>
</html>
