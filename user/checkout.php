<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek autentikasi user
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("location:../login.php?pesan=" . urlencode("Silakan login untuk melakukan pembelian langsung."));
    exit;
}

include '../config/config.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
/** @var mysqli $conn */

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id && isset($_SESSION['nama'])) {
    $nama_s = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $u_res = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama_s' LIMIT 1");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $user_id = (int)mysqli_fetch_assoc($u_res)['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

if (!$user_id) {
    header("location:../login.php?pesan=" . urlencode("Sesi login berakhir. Silakan login kembali."));
    exit;
}

// 1. Ambil Product ID dari parameter request (Sistem Pembelian Langsung)
$product_id = isset($_REQUEST['product_id']) ? (int)$_REQUEST['product_id'] : (isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0);

if ($product_id <= 0) {
    header("location:dashboard.php?pesan=" . urlencode("Silakan pilih produk yang ingin Anda beli langsung dari katalog."));
    exit;
}

// 2. Ambil data produk langsung dari database
$q_prod = mysqli_query($conn, "SELECT id, name, category, product_type, price_sale, stock_sale, image, discount_percent, description 
                               FROM products 
                               WHERE id='$product_id' LIMIT 1");

if (!$q_prod || mysqli_num_rows($q_prod) == 0) {
    header("location:dashboard.php?pesan=" . urlencode("Produk tidak ditemukan!"));
    exit;
}

$product = mysqli_fetch_assoc($q_prod);

// Validasi apakah produk dapat dijual
if ($product['product_type'] !== 'sale' && $product['product_type'] !== 'both') {
    header("location:dashboard.php?pesan=" . urlencode("Produk ini khusus untuk disewakan, tidak tersedia untuk dibeli."));
    exit;
}

$stock_sale = (int)$product['stock_sale'];
if ($stock_sale <= 0) {
    header("location:dashboard.php?pesan=" . urlencode("Maaf, stok produk '" . $product['name'] . "' sedang habis!"));
    exit;
}

// 3. Tentukan jumlah awal yang dipesan (default 1, max = stok tersedia)
$requested_qty = isset($_REQUEST['quantity']) ? (int)$_REQUEST['quantity'] : (isset($_REQUEST['qty']) ? (int)$_REQUEST['qty'] : 1);
$initial_qty = max(1, min($requested_qty, $stock_sale));

// 4. Hitung harga dan diskon otomatis
$raw_price_sale  = (float)$product['price_sale'];
$discount_pct    = floatval($product['discount_percent'] ?? 0);
$effective_price = get_discounted_price($raw_price_sale, $discount_pct);
$initial_subtotal = $effective_price * $initial_qty;

// Evaluasi diskon otomatis awal dari database
$init_discount = validate_and_calculate_discount([
    'user_id' => $user_id,
    'type' => 'sale',
    'subtotal' => $initial_subtotal,
    'rental_days' => 1,
    'product_ids' => [$product_id],
    'category_names' => [$product['category'] ?? '']
]);

$initial_discount_amount = (float)$init_discount['discount_amount'];
$initial_final_total = (float)$init_discount['total_after_discount'];

// 5. Ambil data user untuk kemudahan pengisian form
$q_user = mysqli_query($conn, "SELECT name, email, phone, address FROM users WHERE id='$user_id'");
$user_info = ($q_user && mysqli_num_rows($q_user) > 0) ? mysqli_fetch_assoc($q_user) : [];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembelian Langsung — DANATRAIL</title>
    <!-- Google Fonts -->
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

            --bg-canvas: #f7f8f5;
            --surface-card: #ffffff;
            --border-color: #e4e9e5;
            --text-main: #101914;
            --text-body: #33443b;
            --text-muted: #66746c;

            --radius-xl: 16px;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
            --radius-pill: 9999px;

            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04);
            --shadow-card: 0 2px 10px rgba(7, 53, 34, 0.05);
            --transition-fast: 0.15s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
        }

        .mobile-top-bar {
            display: none;
            align-items: center;
            justify-content: space-between;
            background: var(--forest-950);
            padding: 12px 16px;
            color: #ffffff;
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            width: 100%;
        }

        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-hamburger {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
        }

        .main-content {
            flex: 1;
            padding: 32px 40px 60px 40px;
            overflow-y: auto;
            max-width: 1300px;
            margin: 0 auto;
            width: 100%;
        }

        .header-title-box {
            margin-bottom: 24px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .breadcrumb a {
            color: var(--forest-700);
            text-decoration: none;
            font-weight: 600;
        }

        .page-heading {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--forest-950);
            letter-spacing: -0.02em;
        }

        .page-subheading {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 28px;
            align-items: start;
        }

        .card-box {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-card);
        }

        .card-box-header {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: var(--forest-950);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-field {
            margin-bottom: 16px;
        }

        .form-field label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--forest-900);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-field input,
        .form-field select,
        .form-field textarea {
            width: 100%;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 13.5px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            background: #ffffff;
            color: var(--text-main);
            transition: all var(--transition-fast);
            outline: none;
        }

        .form-field input:focus,
        .form-field select:focus,
        .form-field textarea:focus {
            border-color: var(--forest-700);
            box-shadow: 0 0 0 3px rgba(22, 110, 74, 0.12);
        }

        .radio-option-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            cursor: pointer;
            transition: all var(--transition-fast);
            background: #ffffff;
        }

        .radio-option-card:hover {
            border-color: var(--forest-600);
            background: #fbfdfc;
        }

        .radio-option-card input[type="radio"]:checked ~ .radio-info {
            color: var(--forest-950);
        }

        .radio-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Product Summary Item Card */
        .product-direct-card {
            display: flex;
            gap: 16px;
            background: #f8faf9;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px;
            margin-bottom: 18px;
        }

        .product-direct-img {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-md);
            object-fit: cover;
            background: #ffffff;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .product-direct-info {
            flex: 1;
            min-width: 0;
        }

        .product-direct-title {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--forest-950);
            line-height: 1.3;
            margin-bottom: 4px;
        }

        .product-direct-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            color: var(--forest-800);
            background: #ecfdf5;
            padding: 2px 8px;
            border-radius: 4px;
            margin-bottom: 6px;
        }

        .product-direct-price {
            font-size: 14px;
            font-weight: 800;
            color: #b45309;
        }

        .product-direct-stock {
            font-size: 11.5px;
            color: var(--emerald-600);
            font-weight: 700;
            margin-top: 2px;
        }

        /* Quantity Controls */
        .qty-control-box {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            margin-bottom: 16px;
        }

        .qty-control-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .qty-buttons-group {
            display: inline-flex;
            align-items: center;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            background: #ffffff;
            overflow: hidden;
        }

        .qty-btn {
            width: 36px;
            height: 36px;
            background: #f8fafc;
            border: none;
            color: var(--forest-950);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        .qty-btn:hover:not(:disabled) {
            background: #e2e8f0;
        }

        .qty-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .qty-input {
            width: 52px;
            height: 36px;
            border: none;
            border-left: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            text-align: center;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: var(--forest-950);
            outline: none;
        }

        .summary-total-box {
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
            border: 1.5px solid #a7f3d0;
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 18px;
            text-align: right;
        }

        .btn-submit-order {
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, var(--forest-900) 0%, var(--forest-700) 100%);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(7, 53, 34, 0.25);
            transition: all var(--transition-fast);
        }

        .btn-submit-order:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-600) 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(7, 53, 34, 0.3);
        }

        .btn-submit-order:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        .pickup-info-banner {
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            border-radius: var(--radius-md);
            padding: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #166534;
            font-size: 12.5px;
            line-height: 1.5;
        }

        .expedition-address-box {
            background: #fafaf9;
            border: 1.5px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px;
            transition: all var(--transition-fast);
        }

        .hidden {
            display: none !important;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            margin-top: 14px;
            justify-content: center;
            width: 100%;
        }

        .back-link:hover {
            color: var(--forest-800);
        }

        @media (max-width: 992px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            .main-content {
                padding: 24px 20px;
            }
            .mobile-top-bar {
                display: flex;
            }
        }
    </style>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</head>

<body>
    <!-- Include Menu Sidebar -->
    <?php include 'menu.php'; ?>

    <main class="main-content">
        <!-- Breadcrumb & Heading -->
        <div class="header-title-box">
            <div class="breadcrumb">
                <a href="dashboard.php">Katalog</a>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
                <a href="detail_produk.php?id=<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
                <span>Konfirmasi Pembelian</span>
            </div>
            <h1 class="page-heading">Konfirmasi Pembelian Langsung</h1>
            <p class="page-subheading">Periksa rincian barang, jumlah pesanan, dan alamat pengiriman sebelum menyelesaikan transaksi.</p>
        </div>

        <?php if (isset($_GET['pesan'])): ?>
            <div style="background: #fef2f2; color: #991b1b; border: 1.5px solid #fecaca; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; gap: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><?php echo htmlspecialchars($_GET['pesan']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Transaksi Pembelian Langsung -->
        <form action="beli_aksi.php" method="POST" id="formDirectCheckout" onsubmit="return handleFormSubmit(this)">
            <input type="hidden" name="proses_checkout" value="1">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

            <div class="checkout-grid">
                <!-- Left: Form Kontak, Pengiriman & Pembayaran -->
                <div>
                    <!-- 1. Kontak Penerima -->
                    <div class="card-box">
                        <div class="card-box-header">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 24px; height: 24px; border-radius: 50%; background: var(--forest-800); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">1</span>
                                <span>Identitas Penerima</span>
                            </span>
                        </div>

                        <div class="form-row">
                            <div class="form-field" style="margin-bottom: 0;">
                                <label for="recipient_name">Nama Lengkap Penerima *</label>
                                <input type="text" name="recipient_name" id="recipient_name" value="<?php echo htmlspecialchars($user_info['name'] ?? $_SESSION['nama'] ?? ''); ?>" required placeholder="Contoh: Budi Santoso">
                            </div>
                            <div class="form-field" style="margin-bottom: 0;">
                                <label for="recipient_phone">Nomor WhatsApp / HP Aktif *</label>
                                <input type="tel" name="recipient_phone" id="recipient_phone" value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" required placeholder="Contoh: 081234567890">
                            </div>
                        </div>
                    </div>

                    <!-- 2. Metode Pengiriman -->
                    <div class="card-box">
                        <div class="card-box-header">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 24px; height: 24px; border-radius: 50%; background: var(--forest-800); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">2</span>
                                <span>Metode Pengiriman Paket</span>
                            </span>
                        </div>

                        <div class="form-field">
                            <label for="courier_select">Pilih Opsi Pengiriman *</label>
                            <select name="courier" id="courier_select" onchange="toggleShippingMode()" required>
                                <option value="Ambil Langsung di Basecamp DANATRAIL" data-cost="0" selected>Ambil Langsung di Basecamp DANATRAIL (Bebas Ongkir - Rp 0)</option>
                                <option value="Kurir Ekspedisi JNE Reguler" data-cost="25000">Kurir Ekspedisi JNE Reguler (+Rp 25.000)</option>
                                <option value="Kurir Ekspedisi J&T Express" data-cost="28000">Kurir Ekspedisi J&T Express (+Rp 28.000)</option>
                                <option value="Kurir Ekspedisi SiCepat Cargo" data-cost="35000">Kurir Ekspedisi SiCepat Cargo (+Rp 35.000)</option>
                            </select>
                        </div>

                        <!-- Info saat Ambil di Basecamp -->
                        <div id="pickupInfoView" class="pickup-info-banner">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <div>
                                <strong style="color: #14532d; font-size: 13.5px; display: block; margin-bottom: 2px;">Pengambilan Mandiri di Basecamp:</strong>
                                Pesanan Anda langsung disiapkan di Basecamp DANATRAIL. Anda tidak perlu memasukkan alamat rumah dan bebas biaya ongkos kirim.
                            </div>
                        </div>

                        <!-- Input Alamat Pengiriman (Saat Kurir Ekspedisi) -->
                        <div id="expeditionAddressView" class="address-dynamic-container hidden" style="margin-top: 12px;">
                            <div class="expedition-address-box">
                                <label for="shipping_address" style="font-size: 13px; font-weight: 700; color: #065f46; display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    <span>Alamat Lengkap Pengiriman Paket *</span>
                                </label>
                                <textarea name="shipping_address" id="shipping_address" rows="3" placeholder="Tuliskan nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota/kabupaten, dan kode pos tujuan..."><?php echo htmlspecialchars($user_info['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-field" style="margin-top: 16px; margin-bottom: 0;">
                            <label for="order_notes">Catatan Pesanan untuk Penjual (Opsional)</label>
                            <input type="text" name="notes" id="order_notes" placeholder="Contoh: Mohon packing bubble wrap tebal, warna varian hitam...">
                        </div>
                    </div>

                    <!-- 3. Metode Pembayaran -->
                    <div class="card-box">
                        <div class="card-box-header">
                            <span style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 24px; height: 24px; border-radius: 50%; background: var(--forest-800); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">3</span>
                                <span>Metode Pembayaran</span>
                            </span>
                        </div>

                        <label class="radio-option-card">
                            <div class="radio-info">
                                <input type="radio" name="payment_method" value="Transfer Bank BCA" checked>
                                <div>
                                    <strong style="font-size: 13.5px;">Transfer Bank BCA</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted);">No. Rekening: <strong>7310-0987-6543</strong> (a/n PT DANATRAIL INDONESIA)</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: var(--forest-800); background: #ecfdf5; padding: 2px 7px; border-radius: 4px;">BCA</span>
                        </label>

                        <label class="radio-option-card">
                            <div class="radio-info">
                                <input type="radio" name="payment_method" value="Transfer Bank Mandiri">
                                <div>
                                    <strong style="font-size: 13.5px;">Transfer Bank Mandiri</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted);">No. Rekening: <strong>137-00-1928374-1</strong> (a/n PT DANATRAIL INDONESIA)</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #1e40af; background: #eff6ff; padding: 2px 7px; border-radius: 4px;">MANDIRI</span>
                        </label>

                        <label class="radio-option-card">
                            <div class="radio-info">
                                <input type="radio" name="payment_method" value="Transfer Bank BRI">
                                <div>
                                    <strong style="font-size: 13.5px;">Transfer Bank BRI</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted);">No. Rekening: <strong>0123-01-098765-50-8</strong> (a/n PT DANATRAIL INDONESIA)</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #0284c7; background: #f0f9ff; padding: 2px 7px; border-radius: 4px;">BRI</span>
                        </label>

                        <label class="radio-option-card">
                            <div class="radio-info">
                                <input type="radio" name="payment_method" value="QRIS Instan">
                                <div>
                                    <strong style="font-size: 13.5px;">QRIS Instan (GoPay, OVO, ShopeePay, DANA, BCA Mobile)</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted);">Scan QR Code via aplikasi e-wallet atau m-banking</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #7c3aed; background: #f5f3ff; padding: 2px 7px; border-radius: 4px;">QRIS</span>
                        </label>

                        <label class="radio-option-card">
                            <div class="radio-info">
                                <input type="radio" name="payment_method" value="Bayar di Kasir Basecamp">
                                <div>
                                    <strong style="font-size: 13.5px;">Bayar di Kasir Basecamp (Tunai / EDC Debit)</strong>
                                    <div style="font-size: 11.5px; color: var(--text-muted);">Pembayaran langsung saat mengambil barang di loket toko</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #0f766e; background: #f0fdfa; padding: 2px 7px; border-radius: 4px;">KASIR</span>
                        </label>
                    </div>
                </div>

                <!-- Right: Rincian Produk, Jumlah & Konfirmasi Pembelian -->
                <div>
                    <div class="card-box" style="position: sticky; top: 24px;">
                        <div class="card-box-header">
                            <span>Rincian Pembelian</span>
                            <span style="font-size: 11.5px; color: #b45309; font-weight: 800; background: #fef3c7; padding: 2px 8px; border-radius: 4px;">Transaksi Langsung</span>
                        </div>

                        <!-- Card Preview Produk -->
                        <div class="product-direct-card">
                            <?php if (!empty($product['image']) && file_exists('../uploads/products/' . $product['image'])): ?>
                                <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-direct-img">
                            <?php else: ?>
                                <div class="product-direct-img" style="display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                            <?php endif; ?>

                            <div class="product-direct-info">
                                <span class="product-direct-badge"><?php echo htmlspecialchars(strtoupper($product['category'] ?? 'OUTDOOR')); ?></span>
                                <h3 class="product-direct-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="product-direct-price">
                                    Rp <?php echo number_format($effective_price, 0, ',', '.'); ?> <span style="font-size: 11.5px; color: var(--text-muted); font-weight: 500;">/ unit</span>
                                    <?php if ($discount_pct > 0): ?>
                                        <span style="font-size: 11px; text-decoration: line-through; color: #94a3b8; margin-left: 4px;">Rp <?php echo number_format($raw_price_sale, 0, ',', '.'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-direct-stock">Stok Tersedia: <?php echo $stock_sale; ?> unit</div>
                            </div>
                        </div>

                        <!-- Penentuan Jumlah Barang (Quantity Selector Langsung) -->
                        <div class="qty-control-box">
                            <div class="qty-control-row">
                                <div>
                                    <strong style="font-size: 13px; color: var(--forest-950); display: block;">Jumlah Barang:</strong>
                                    <span style="font-size: 11.5px; color: var(--text-muted);">Maks. <?php echo $stock_sale; ?> unit</span>
                                </div>
                                <div class="qty-buttons-group">
                                    <button type="button" class="qty-btn" id="btnQtyMinus" onclick="adjustQty(-1)" aria-label="Kurangi">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" />
                                        </svg>
                                    </button>
                                    <input type="number" name="quantity" id="direct_quantity" class="qty-input" value="<?php echo $initial_qty; ?>" min="1" max="<?php echo $stock_sale; ?>" required oninput="recalculateDirectTotal()">
                                    <button type="button" class="qty-btn" id="btnQtyPlus" onclick="adjustQty(1)" aria-label="Tambah">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Diskon Otomatis Banner -->
                        <div id="boxDiscountBanner" style="background: <?php echo ($initial_discount_amount > 0) ? '#f0fdf4' : '#fafaf9'; ?>; border: 1.5px <?php echo ($initial_discount_amount > 0) ? 'solid #86efac' : 'dashed var(--border-color)'; ?>; border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                <div style="display: flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 800; text-transform: uppercase; color: <?php echo ($initial_discount_amount > 0) ? '#166534' : 'var(--forest-900)'; ?>;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                    </svg>
                                    <span>Diskon Otomatis</span>
                                </div>
                                <span id="badgeDiscountStatus" style="font-size: 11px; font-weight: 800; background: #22c55e; color: #ffffff; padding: 2px 8px; border-radius: 999px; display: <?php echo ($initial_discount_amount > 0) ? 'inline-block' : 'none'; ?>;">AKTIF</span>
                            </div>
                            <div id="textDiscountDesc" style="margin-top: 6px; font-size: 12px; color: #15803d; line-height: 1.4;">
                                <?php if ($initial_discount_amount > 0): ?>
                                    <strong><?php echo htmlspecialchars($init_discount['discount_name']); ?></strong> diterapkan! Anda hemat <strong>Rp <?php echo number_format($initial_discount_amount, 0, ',', '.'); ?></strong>.
                                <?php else: ?>
                                    Diskon otomatis akan langsung aktif jika total belanja Anda memenuhi syarat promo promo toko.
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Price Breakdown -->
                        <div style="border-top: 1px solid var(--border-color); padding-top: 12px; margin-bottom: 14px; display: flex; flex-direction: column; gap: 6px; font-size: 12.5px;">
                            <div style="display: flex; justify-content: space-between; color: var(--text-muted);">
                                <span>Subtotal (<span id="lbl_qty_count"><?php echo $initial_qty; ?></span> unit):</span>
                                <strong id="lbl_subtotal" style="color: var(--text-main);">Rp <?php echo number_format($initial_subtotal, 0, ',', '.'); ?></strong>
                            </div>
                            <div id="row_discount" style="display: <?php echo ($initial_discount_amount > 0) ? 'flex' : 'none'; ?>; justify-content: space-between; color: #059669; font-weight: 700;">
                                <span id="lbl_discount_name">Potongan Diskon:</span>
                                <span id="lbl_discount_amount">-Rp <?php echo number_format($initial_discount_amount, 0, ',', '.'); ?></span>
                            </div>
                            <div id="row_shipping" style="display: flex; justify-content: space-between; color: var(--text-muted);">
                                <span>Ongkos Kirim (<span id="lbl_courier_name">Basecamp</span>):</span>
                                <strong id="lbl_shipping_cost" style="color: var(--text-main);">Rp 0 (Gratis)</strong>
                            </div>
                        </div>

                        <!-- Total Box -->
                        <div class="summary-total-box">
                            <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #065f46; letter-spacing: 0.5px;">Total Pembayaran:</div>
                            <div id="lbl_final_total" style="font-family: 'Outfit', sans-serif; font-size: 24px; font-weight: 800; color: var(--forest-950); margin-top: 2px;">
                                Rp <?php echo number_format($initial_final_total, 0, ',', '.'); ?>
                            </div>
                            <div style="font-size: 11.5px; color: #15803d; margin-top: 2px;" id="shippingCostLabel">Bebas Biaya Pengambilan di Basecamp</div>
                        </div>

                        <!-- Submit Confirmation Button -->
                        <button type="submit" class="btn-submit-order" id="btnConfirmSubmit">
                            <span>Konfirmasi & Beli Sekarang</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>

                        <a href="dashboard.php" class="back-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            <span>Kembali ke Katalog</span>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script>
        const unitEffectivePrice = <?php echo (float)$effective_price; ?>;
        const maxStock = <?php echo (int)$stock_sale; ?>;
        const productId = <?php echo (int)$product['id']; ?>;
        const productCategory = <?php echo json_encode($product['category'] ?? ''); ?>;

        function formatRupiah(num) {
            return 'Rp ' + Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function adjustQty(delta) {
            const input = document.getElementById('direct_quantity');
            let current = parseInt(input.value) || 1;
            let target = current + delta;
            if (target < 1) target = 1;
            if (target > maxStock) target = maxStock;
            input.value = target;
            recalculateDirectTotal();
        }

        let currentSubtotal = unitEffectivePrice * <?php echo $initial_qty; ?>;
        let currentDiscount = <?php echo $initial_discount_amount; ?>;

        function getSelectedShippingCost() {
            const sel = document.getElementById('courier_select');
            if (!sel) return 0;
            const opt = sel.options[sel.selectedIndex];
            return opt ? parseFloat(opt.getAttribute('data-cost') || '0') : 0;
        }

        function updateTotalBreakdown() {
            const shipping = getSelectedShippingCost();
            const finalTotal = Math.max(0, currentSubtotal - currentDiscount) + shipping;
            const lblTotal = document.getElementById('lbl_final_total');
            if (lblTotal) lblTotal.innerText = formatRupiah(finalTotal);

            const sel = document.getElementById('courier_select');
            if (!sel) return;
            const courier = sel.value;
            const isPickup = courier.includes('Basecamp') || courier.includes('Ambil');
            const costLabel = document.getElementById('shippingCostLabel');
            const lblShippingCost = document.getElementById('lbl_shipping_cost');
            const lblCourierName = document.getElementById('lbl_courier_name');

            if (isPickup) {
                if (costLabel) costLabel.innerText = 'Bebas Biaya Pengambilan di Basecamp';
                if (lblCourierName) lblCourierName.innerText = 'Basecamp';
                if (lblShippingCost) lblShippingCost.innerText = 'Rp 0 (Gratis)';
            } else {
                const shortName = courier.replace('Kurir Ekspedisi ', '');
                if (costLabel) costLabel.innerText = 'Ongkir ' + shortName + ': ' + formatRupiah(shipping);
                if (lblCourierName) lblCourierName.innerText = shortName;
                if (lblShippingCost) lblShippingCost.innerText = formatRupiah(shipping);
            }
        }

        function recalculateDirectTotal() {
            const input = document.getElementById('direct_quantity');
            let qty = parseInt(input.value) || 1;
            if (qty < 1) qty = 1;
            if (qty > maxStock) qty = maxStock;
            input.value = qty;

            // Update status button minus & plus
            document.getElementById('btnQtyMinus').disabled = (qty <= 1);
            document.getElementById('btnQtyPlus').disabled = (qty >= maxStock);

            const subtotal = unitEffectivePrice * qty;
            currentSubtotal = subtotal;
            document.getElementById('lbl_qty_count').innerText = qty;
            document.getElementById('lbl_subtotal').innerText = formatRupiah(subtotal);

            // Cek diskon otomatis via AJAX
            const formData = new FormData();
            formData.append('type', 'sale');
            formData.append('subtotal', subtotal);
            formData.append('rental_days', 1);
            formData.append('product_ids', productId);
            formData.append('category_names', productCategory);

            fetch('../ajax_voucher.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const rowDiscount = document.getElementById('row_discount');
                const badgeStatus = document.getElementById('badgeDiscountStatus');
                const textDesc = document.getElementById('textDiscountDesc');
                const boxBanner = document.getElementById('boxDiscountBanner');

                if (data.success && data.discount_amount > 0) {
                    currentDiscount = parseFloat(data.discount_amount);
                    rowDiscount.style.display = 'flex';
                    document.getElementById('lbl_discount_name').innerText = 'Potongan (' + (data.discount_name || 'Diskon') + '):';
                    document.getElementById('lbl_discount_amount').innerText = '-' + data.discount_amount_formatted;
                    badgeStatus.style.display = 'inline-block';
                    boxBanner.style.background = '#f0fdf4';
                    boxBanner.style.border = '1.5px solid #86efac';
                    textDesc.innerHTML = '<strong>' + (data.discount_name || 'Promo') + '</strong> diterapkan! Anda hemat <strong>' + data.discount_amount_formatted + '</strong>.';
                } else {
                    currentDiscount = 0;
                    rowDiscount.style.display = 'none';
                    badgeStatus.style.display = 'none';
                    boxBanner.style.background = '#fafaf9';
                    boxBanner.style.border = '1.5px dashed var(--border-color)';
                    textDesc.innerText = 'Diskon otomatis akan langsung aktif jika total belanja Anda memenuhi syarat promo toko.';
                }
                updateTotalBreakdown();
            })
            .catch(() => {
                updateTotalBreakdown();
            });
        }

        function toggleShippingMode() {
            const courier = document.getElementById('courier_select').value;
            const isPickup = courier.includes('Basecamp') || courier.includes('Ambil');
            const pickupView = document.getElementById('pickupInfoView');
            const expeditionView = document.getElementById('expeditionAddressView');
            const addressInput = document.getElementById('shipping_address');

            if (isPickup) {
                pickupView.classList.remove('hidden');
                expeditionView.classList.add('hidden');
                addressInput.removeAttribute('required');
            } else {
                pickupView.classList.add('hidden');
                expeditionView.classList.remove('hidden');
                addressInput.setAttribute('required', 'required');
            }
            updateTotalBreakdown();
        }

        // Anti-double-submission: cegah transaksi ganda jika user klik tombol berkali-kali
        let isSubmitting = false;
        function handleFormSubmit(form) {
            if (isSubmitting) {
                return false;
            }

            const recipient = document.getElementById('recipient_name').value.trim();
            const phone = document.getElementById('recipient_phone').value.trim();
            const courier = document.getElementById('courier_select').value;
            const isPickup = courier.includes('Basecamp') || courier.includes('Ambil');
            const address = document.getElementById('shipping_address').value.trim();

            if (!recipient || !phone) {
                alert('Mohon lengkapi Nama Penerima dan Nomor WhatsApp!');
                return false;
            }

            if (!isPickup && !address) {
                alert('Mohon lengkapi Alamat Lengkap Pengiriman untuk kurir ekspedisi!');
                document.getElementById('shipping_address').focus();
                return false;
            }

            isSubmitting = true;
            const btn = document.getElementById('btnConfirmSubmit');
            btn.disabled = true;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" class="spin-icon" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0 1 10 10"></path></svg> Memproses Transaksi...';
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            recalculateDirectTotal();
            toggleShippingMode();
        });
    </script>
</body>
</html>
