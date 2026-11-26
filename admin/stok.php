<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek hak akses admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=" . urlencode("Akses ditolak! Anda bukan Administrator."));
    exit;
}

include '../config/config.php';
require_once __DIR__ . '/../config/stock_cash_helper.php';
/** @var mysqli $conn */

// Saldo kas toko terkini dari hasil penjualan & sewa
$store_cash = function_exists('get_store_cash_balance') ? get_store_cash_balance($conn) : 0.00;

$current_page = 'stok.php';

// Tab aktif: 'inventaris' | 'logs'
$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'inventaris';
if (!in_array($active_tab, ['inventaris', 'logs'])) {
    $active_tab = 'inventaris';
}

// Pencarian
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// 1. Hitung total semua stok dan nilai seluruh stok barang
$q_stats = mysqli_query($conn, "SELECT 
    COUNT(*) as total_items,
    SUM(stock) as total_stock_rent,
    SUM(stock_sale) as total_stock_sale,
    SUM(stock + stock_sale) as total_all_stock,
    SUM(stock * price) as total_val_rent,
    SUM(stock_sale * price_sale) as total_val_sale,
    SUM((stock * price) + (stock_sale * price_sale)) as grand_total_stock_value,
    SUM(CASE WHEN (product_type IN ('rental', 'both') AND stock <= 2) OR (product_type IN ('sale', 'both') AND stock_sale <= 2) THEN 1 ELSE 0 END) as low_stock_items
    FROM products");
$stats = mysqli_fetch_assoc($q_stats);

$total_items             = (int)($stats['total_items'] ?? 0);
$total_stock_rent        = (int)($stats['total_stock_rent'] ?? 0);
$total_stock_sale        = (int)($stats['total_stock_sale'] ?? 0);
$total_all_stock         = (int)($stats['total_all_stock'] ?? 0);
$total_val_rent          = (float)($stats['total_val_rent'] ?? 0);
$total_val_sale          = (float)($stats['total_val_sale'] ?? 0);
$grand_total_stock_value = (float)($stats['grand_total_stock_value'] ?? 0);
$low_stock_items         = (int)($stats['low_stock_items'] ?? 0);

// 2. Query Data Inventaris Stok
$query_inventory = "SELECT * FROM products WHERE 1=1";
if (!empty($search)) {
    $query_inventory .= " AND (name LIKE '%$search%' OR category LIKE '%$search%')";
}
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
if ($filter_status === 'kritis') {
    $query_inventory .= " AND ((product_type IN ('rental', 'both') AND stock <= 2) OR (product_type IN ('sale', 'both') AND stock_sale <= 2))";
} elseif ($filter_status === 'habis') {
    $query_inventory .= " AND ((product_type IN ('rental', 'both') AND stock = 0) OR (product_type IN ('sale', 'both') AND stock_sale = 0))";
}
$filter_tipe = isset($_GET['tipe']) ? trim($_GET['tipe']) : '';
if ($filter_tipe === 'jual') {
    $query_inventory .= " AND product_type IN ('sale', 'both')";
} elseif ($filter_tipe === 'sewa') {
    $query_inventory .= " AND product_type = 'rental'";
}
$query_inventory .= " ORDER BY (stock + stock_sale) ASC, id DESC";
$data_inventory = mysqli_query($conn, $query_inventory);

// 3. Query Riwayat Restock
$query_logs = "SELECT sl.*, p.name as product_name, p.category, p.image 
               FROM stock_logs sl 
               LEFT JOIN products p ON sl.product_id = p.id 
               ORDER BY sl.id DESC LIMIT 100";
$data_logs = mysqli_query($conn, $query_logs);

$current_request_uri = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok Perlengkapan — Admin DANATRAIL</title>
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
            --forest-100: #d1fae5;
            --forest-50:  #ecfdf5;

            --gold-500: #f5b800;
            --gold-600: #d99b00;
            --gold-100: #fef8e6;

            --bg-canvas: #f7f8f5;
            --surface-card: #ffffff;
            --border-color: #e4e9e5;
            --border-light: #edf2ee;

            --text-main: #101914;
            --text-body: #33443b;
            --text-muted: #66746c;
            --text-subtle: #92a098;

            --radius-xl: 16px;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
            --radius-pill: 9999px;

            --shadow-card: 0 2px 10px rgba(7, 53, 34, 0.05);
            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04);
            --shadow-modal: 0 20px 45px rgba(4, 31, 20, 0.35);

            --transition-fast: 0.15s ease;
            --transition-normal: 0.2s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            display: flex;
            min-height: 100vh;
            line-height: 1.5;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            letter-spacing: -0.015em;
        }

        .main-wrapper {
            flex: 1;
            padding: 32px 40px;
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
            overflow-x: hidden;
        }

        /* HEADER */
        .page-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .page-header-title-box h1 {
            font-size: 26px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--forest-950);
        }

        .page-header-title-box p {
            color: var(--text-muted);
            font-size: 13.5px;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* METRIC CARDS GRID */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            position: relative;
            box-shadow: var(--shadow-card);
            transition: transform var(--transition-fast);
        }

        .metric-card:hover {
            transform: translateY(-2px);
        }

        .metric-highlight-card {
            background: linear-gradient(135deg, #073522 0%, #0f5c3c 100%);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .metric-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .metric-title {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .metric-highlight-card .metric-title {
            color: #a7f3d0;
        }

        .metric-val {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
        }

        .metric-highlight-card .metric-val {
            color: #ffffff;
        }

        .metric-desc {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .metric-highlight-card .metric-desc {
            color: #d1fae5;
        }

        /* TABS NAVIGATION */
        .tabs-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-buttons-group {
            display: flex;
            gap: 8px;
        }

        .tab-btn {
            padding: 10px 18px;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-muted);
            border: none;
            background: transparent;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .tab-btn:hover {
            color: var(--forest-900);
        }

        .tab-btn.active {
            color: var(--forest-900);
            border-bottom-color: var(--forest-800);
        }

        .tab-badge {
            background: #e2e8f0;
            color: #334155;
            font-size: 11px;
            padding: 1px 7px;
            border-radius: var(--radius-pill);
            font-weight: 700;
        }

        .tab-btn.active .tab-badge {
            background: var(--forest-100);
            color: var(--forest-900);
        }

        /* TABLE CONTAINER */
        .content-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table.stock-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table.stock-table th {
            background: #f8fafc;
            padding: 12px 16px;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            text-align: left;
            border-bottom: 1.5px solid var(--border-color);
        }

        table.stock-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        table.stock-table tr:hover {
            background: #fcfdfd;
        }

        .thumb-box {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            overflow: hidden;
            background: #f1f5f9;
            flex-shrink: 0;
            border: 1px solid var(--border-color);
        }

        .thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .thumb-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-subtle);
        }

        /* STATUS BADGES */
        .badge-stock {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
        }

        .badge-ready {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .badge-low {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .badge-out {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ACTION BUTTON RESTOCK */
        .btn-restock-row {
            background: linear-gradient(135deg, #073522 0%, #0e5638 100%);
            color: #ffffff;
            border: none;
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all var(--transition-fast);
        }

        .btn-restock-row:hover {
            background: #041f14;
            transform: scale(1.02);
        }

        .badge-asset-rent {
            display: inline-flex;
            align-items: center;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #f1f5f9;
            padding: 5px 10px;
            border-radius: var(--radius-sm);
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .btn-margin-chip {
            padding: 5px 10px;
            border-radius: 6px;
            border: 1.5px solid #cbd5e1;
            background: #ffffff;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-margin-chip:hover {
            border-color: #059669;
            color: #059669;
            background: #ecfdf5;
        }

        .btn-margin-chip.active {
            background: #059669;
            color: #ffffff;
            border-color: #059669;
        }

        /* TOAST NOTIF */
        .toast-notification {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 600;
        }

        .toast-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .toast-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .toast-close-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            color: inherit;
        }

        /* MODAL STYLES */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(4, 31, 20, 0.65);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            overflow-y: auto;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-dialog {
            background: #ffffff;
            width: 100%;
            max-width: 520px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-modal);
            overflow: hidden;
            animation: modalFadeIn 0.2s ease-out;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            margin: auto;
        }

        .modal-dialog form {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header-bar {
            padding: 16px 20px;
            background: linear-gradient(135deg, #073522 0%, #0e5638 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .modal-header-bar h3 {
            font-size: 16px;
            font-weight: 800;
            color: #ffffff;
        }

        .btn-modal-close-icon {
            background: transparent;
            border: none;
            color: #ffffff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.8;
            transition: opacity var(--transition-fast);
        }

        .btn-modal-close-icon:hover {
            opacity: 1;
        }

        .modal-body-scroll {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
            max-height: calc(90vh - 140px);
            -webkit-overflow-scrolling: touch;
        }

        .modal-body-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .modal-body-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .modal-body-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .modal-body-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .form-field-group {
            margin-bottom: 14px;
        }

        .form-field-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
        }

        .form-field-group input, 
        .form-field-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13px;
            color: var(--text-main);
            transition: border-color var(--transition-fast);
        }

        .form-field-group input:focus, 
        .form-field-group select:focus {
            outline: none;
            border-color: var(--forest-700);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .modal-footer-bar {
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-modal-cancel-btn {
            background: #ffffff;
            border: 1px solid var(--border-color);
            color: var(--text-body);
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-modal-confirm-btn {
            background: linear-gradient(135deg, #073522 0%, #0e5638 100%);
            border: none;
            color: #ffffff;
            padding: 8px 18px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(7, 53, 34, 0.2);
        }

        .type-radio-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .type-radio-card {
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            background: #ffffff;
        }

        .type-radio-card.selected {
            border-color: var(--forest-800);
            background: #f0fdf4;
            color: var(--forest-900);
        }

        @media (max-width: 992px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .main-wrapper {
                padding: 20px 16px;
            }
        }

        @media (max-width: 576px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Unified Sidebar -->
    <?php include 'menu.php'; ?>

    <main class="main-wrapper">

        <!-- Page Header -->
        <div class="page-header-flex">
            <div class="page-header-title-box">
                <h1>
                    <span>Manajemen Stok Perlengkapan</span>
                    <span style="font-size: 12px; background: #e0f2fe; color: #0369a1; padding: 3px 10px; border-radius: var(--radius-pill); font-weight: 700;">Admin Basecamp</span>
                </h1>
                <p>Pantau seluruh unit stok, total nilai aset barang, serta lakukan restock perlengkapan secara cepat.</p>
            </div>

            <div class="header-actions">
                <a href="perlengkapan.php" style="background: linear-gradient(135deg, #073522 0%, #0e5638 100%); border: none; color: #ffffff; padding: 9px 16px; border-radius: var(--radius-md); font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Tambah Perlengkapan Baru</span>
                </a>
            </div>
        </div>

        <!-- Feedback Alert -->
        <?php if (isset($_GET['sukses'])): ?>
            <div class="toast-notification toast-success" id="alertToast">
                <span><?php echo htmlspecialchars($_GET['sukses']); ?></span>
                <button type="button" class="toast-close-btn" onclick="document.getElementById('alertToast').remove()">✕</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['pesan'])): ?>
            <div class="toast-notification toast-error" id="alertToast">
                <span><?php echo htmlspecialchars($_GET['pesan']); ?></span>
                <button type="button" class="toast-close-btn" onclick="document.getElementById('alertToast').remove()">✕</button>
            </div>
        <?php endif; ?>

        <!-- 5 Metric Cards: SALDO KAS TOKO, TOTAL STOK & NILAI SELURUH STOK -->
        <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
            <!-- Card 0: SALDO KAS TOKO (DARI PENJUALAN & SEWA) -->
            <div class="metric-card" style="background: linear-gradient(135deg, #14532d 0%, #064e3b 100%); color: #ffffff; border-color: rgba(255,255,255,0.18);">
                <div class="metric-head">
                    <span class="metric-title" style="color: #86efac; font-weight: 700;">Saldo Kas Toko (Penjualan)</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" style="color: #4ade80;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="metric-val" style="color: #ffffff; font-size: 22px;">Rp <?php echo number_format($store_cash, 0, ',', '.'); ?></div>
                <div class="metric-desc" style="color: #a7f3d0;">Uang kas bersih dari penjualan & sewa (dipotong restock)</div>
            </div>

            <!-- Card 1: TOTAL SEMUA STOK UNIT -->
            <div class="metric-card metric-highlight-card">
                <div class="metric-head">
                    <span class="metric-title">Total Semua Stok Unit</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" style="color: #f5b800;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="metric-val"><?php echo number_format($total_all_stock, 0, ',', '.'); ?> <span style="font-size: 15px; font-weight: 700; color: #a7f3d0;">Unit</span></div>
                <div class="metric-desc"><?php echo $total_stock_rent; ?> unit sewa &bull; <?php echo $total_stock_sale; ?> unit toko (jual)</div>
            </div>

            <!-- Card 2: TOTAL NILAI SEMUA STOK BARANG -->
            <div class="metric-card">
                <div class="metric-head">
                    <span class="metric-title">Total Nilai Semua Stok</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #16a34a;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="metric-val" style="color: #15803d; font-size: 24px;">Rp <?php echo number_format($grand_total_stock_value, 0, ',', '.'); ?></div>
                <div class="metric-desc">Estimasi nilai total aset seluruh stok barang</div>
            </div>

            <!-- Card 3: TOTAL JENIS BARANG -->
            <div class="metric-card">
                <div class="metric-head">
                    <span class="metric-title">Total Jenis Perlengkapan</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #3b82f6;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                </div>
                <div class="metric-val"><?php echo $total_items; ?> <span style="font-size: 14px; font-weight: 600; color: var(--text-muted);">Katalog</span></div>
                <div class="metric-desc">Item terdaftar di inventaris Danatrail</div>
            </div>

            <!-- Card 4: STOK KRITIS / MENIPIS -->
            <div class="metric-card">
                <div class="metric-head">
                    <span class="metric-title">Perlu Restock (<=2 Unit)</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #dc2626;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="metric-val" style="color: <?php echo ($low_stock_items > 0) ? '#dc2626' : 'var(--emerald-600)'; ?>;">
                    <?php echo $low_stock_items; ?> <span style="font-size: 14px; font-weight: 600; color: var(--text-muted);">Barang</span>
                </div>
                <div class="metric-desc"><?php echo ($low_stock_items > 0) ? 'Klik Restock untuk menambah unit' : 'Semua unit stok dalam batas aman'; ?></div>
            </div>
        </div>

        <!-- Tabs Header Bar -->
        <div class="tabs-header-bar">
            <div class="tab-buttons-group">
                <a href="stok.php?tab=inventaris" class="tab-btn <?php echo ($active_tab === 'inventaris') ? 'active' : ''; ?>">
                    <span>Daftar Stok Seluruh Barang</span>
                    <span class="tab-badge"><?php echo $total_items; ?> Barang</span>
                </a>
                <a href="stok.php?tab=logs" class="tab-btn <?php echo ($active_tab === 'logs') ? 'active' : ''; ?>">
                    <span>Riwayat Penambahan & Restock</span>
                </a>
            </div>

            <?php if ($active_tab === 'inventaris'): ?>
                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                    <a href="stok.php?tab=inventaris" style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-decoration: none; padding: 4px 10px; border-radius: var(--radius-sm); background: <?php echo (empty($filter_status) && empty($filter_tipe)) ? '#e2e8f0' : 'transparent'; ?>;">Semua (<?php echo $total_items; ?>)</a>
                    <a href="stok.php?tab=inventaris&tipe=jual" style="font-size: 12px; font-weight: 700; color: #1e40af; text-decoration: none; padding: 4px 10px; border-radius: var(--radius-sm); background: <?php echo ($filter_tipe === 'jual') ? '#dbeafe' : 'transparent'; ?>;">Barang Toko / Jual</a>
                    <a href="stok.php?tab=inventaris&tipe=sewa" style="font-size: 12px; font-weight: 700; color: #065f46; text-decoration: none; padding: 4px 10px; border-radius: var(--radius-sm); background: <?php echo ($filter_tipe === 'sewa') ? '#d1fae5' : 'transparent'; ?>;">Aset Sewa</a>
                    <a href="stok.php?tab=inventaris&status=kritis" style="font-size: 12px; font-weight: 700; color: #b45309; text-decoration: none; padding: 4px 10px; border-radius: var(--radius-sm); background: <?php echo ($filter_status === 'kritis') ? '#fef3c7' : 'transparent'; ?>;">Stok Menipis (<=2)</a>
                    <a href="stok.php?tab=inventaris&status=habis" style="font-size: 12px; font-weight: 700; color: #b91c1c; text-decoration: none; padding: 4px 10px; border-radius: var(--radius-sm); background: <?php echo ($filter_status === 'habis') ? '#fee2e2' : 'transparent'; ?>;">Stok Habis (0)</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB CONTENT 1: DAFTAR INVENTARIS & RESTOCK -->
        <?php if ($active_tab === 'inventaris'): ?>
            <div class="content-card">
                <div class="table-responsive">
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Foto</th>
                                <th>Perlengkapan</th>
                                <th>Kategori & Tipe</th>
                                <th>Stok Sewa</th>
                                <th>Stok Toko (Jual)</th>
                                <th>Total Stok Barang</th>
                                <th>Nilai Stok Barang</th>
                                <th>Status</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($data_inventory && mysqli_num_rows($data_inventory) > 0): ?>
                                <?php while ($item = mysqli_fetch_assoc($data_inventory)): 
                                    $ptype = $item['product_type'];
                                    $sRent = (int)$item['stock'];
                                    $sSale = (int)$item['stock_sale'];
                                    $pRent = (float)($item['price'] ?? 0);
                                    $pSale = (float)($item['price_sale'] ?? 0);
                                    
                                    $itemTotalStock = $sRent + $sSale;
                                    $itemTotalValue = ($sRent * $pRent) + ($sSale * $pSale);
                                    
                                    // Status
                                    $minStock = ($ptype === 'rental') ? $sRent : (($ptype === 'sale') ? $sSale : min($sRent, $sSale));
                                    $isOut = ($minStock <= 0);
                                    $isLow = ($minStock > 0 && $minStock <= 2);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="thumb-box">
                                                <?php if (!empty($item['image']) && file_exists('../uploads/products/' . $item['image'])): ?>
                                                    <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="Foto">
                                                <?php else: ?>
                                                    <div class="thumb-fallback">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--text-main); font-size: 13.5px;"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <div style="font-size: 11.5px; color: var(--text-muted);"><?php echo htmlspecialchars($item['category']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($ptype === 'rental'): ?>
                                                <span style="font-size: 11px; font-weight: 700; background: #ecfdf5; color: #065f46; padding: 2px 8px; border-radius: var(--radius-pill); border: 1px solid #a7f3d0;">Rental</span>
                                            <?php elseif ($ptype === 'sale'): ?>
                                                <span style="font-size: 11px; font-weight: 700; background: #eff6ff; color: #1e40af; padding: 2px 8px; border-radius: var(--radius-pill); border: 1px solid #bfdbfe;">Dijual</span>
                                            <?php elseif ($ptype === 'both'): ?>
                                                <span style="font-size: 11px; font-weight: 700; background: #fefce8; color: #854d0e; padding: 2px 8px; border-radius: var(--radius-pill); border: 1px solid #fef08a;">Rental & Jual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($ptype === 'sale'): ?>
                                                <span style="color: var(--text-subtle);">-</span>
                                            <?php else: ?>
                                                <strong style="color: <?php echo ($sRent <= 2) ? '#dc2626' : 'var(--text-main)'; ?>; font-size: 13.5px;"><?php echo $sRent; ?></strong> unit
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($ptype === 'rental'): ?>
                                                <span style="color: var(--text-subtle);">-</span>
                                            <?php else: ?>
                                                <strong style="color: <?php echo ($sSale <= 2) ? '#dc2626' : '#1e40af'; ?>; font-size: 13.5px;"><?php echo $sSale; ?></strong> unit
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="font-size: 14px; color: var(--forest-900);"><?php echo $itemTotalStock; ?></strong> unit
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; color: #15803d; font-size: 13px;">Rp <?php echo number_format($itemTotalValue, 0, ',', '.'); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($isOut): ?>
                                                <span class="badge-stock badge-out">● Stok Habis</span>
                                            <?php elseif ($isLow): ?>
                                                <span class="badge-stock badge-low">● Stok Menipis</span>
                                            <?php else: ?>
                                                <span class="badge-stock badge-ready">● Stok Aman</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php if ($ptype === 'rental'): ?>
                                                <span class="badge-asset-rent">
                                                    Aset Sewa Tetap
                                                </span>
                                            <?php else: ?>
                                                <button type="button" class="btn-restock-row" onclick="openModalRestock(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    <span>+ Restock Toko</span>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        Tidak ada data inventaris yang cocok dengan filter saat ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- TAB CONTENT 2: RIWAYAT RESTOCK -->
        <?php elseif ($active_tab === 'logs'): ?>
            <div class="content-card">
                <div class="table-responsive">
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th>Tanggal & Waktu</th>
                                <th>Perlengkapan</th>
                                <th>Tipe Stok</th>
                                <th>Jenis Aksi</th>
                                <th>Jumlah Tambahan Unit</th>
                                <th>Catatan / Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($data_logs && mysqli_num_rows($data_logs) > 0): ?>
                                <?php while ($log = mysqli_fetch_assoc($data_logs)): ?>
                                    <tr>
                                        <td style="white-space: nowrap; font-size: 12px; color: var(--text-muted);">
                                            <?php echo date('d M Y, H:i', strtotime($log['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--text-main);">
                                                <?php echo htmlspecialchars($log['product_name'] ?? 'ID #' . $log['product_id']); ?>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($log['category'] ?? '-'); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($log['type'] === 'rental'): ?>
                                                <span style="font-size: 11px; font-weight: 700; color: #065f46; background: #ecfdf5; padding: 2px 7px; border-radius: var(--radius-pill);">Sewa</span>
                                            <?php else: ?>
                                                <span style="font-size: 11px; font-weight: 700; color: #1e40af; background: #eff6ff; padding: 2px 7px; border-radius: var(--radius-pill);">Toko (Jual)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['action_type'] === 'new_product'): ?>
                                                <span style="font-size: 11px; font-weight: 700; color: #854d0e; background: #fefce8; padding: 2px 7px; border-radius: var(--radius-pill);">Stok Awal Baru</span>
                                            <?php else: ?>
                                                <span style="font-size: 11px; font-weight: 700; color: #065f46; background: #ecfdf5; padding: 2px 7px; border-radius: var(--radius-pill);">Restock Unit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--forest-900); font-size: 14px;">+<?php echo $log['quantity']; ?></strong> unit
                                        </td>
                                        <td style="color: var(--text-muted); font-size: 12px;">
                                            <?php echo htmlspecialchars($log['notes'] ?: '-'); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        Belum ada riwayat restock unit yang tercatat.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- ==========================================================================
         MODAL: RESTOCK BARANG TOKO & KALKULATOR PROFIT OTOMATIS
         ========================================================================== -->
    <div class="modal-overlay" id="modalRestockOverlay" role="dialog" aria-modal="true">
        <div class="modal-dialog" style="max-width: 580px;">
            <div class="modal-header-bar">
                <div>
                    <h3>Restock Barang Dagangan Toko</h3>
                    <p style="font-size: 12px; color: #a7f3d0; margin-top: 2px;">Otomatisasi kalkulasi harga modal beli, harga jual, dan estimasi keuntungan profit.</p>
                </div>
                <button type="button" class="btn-modal-close-icon" onclick="closeModalRestock()" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form action="produk_aksi.php" method="POST" id="formRestock">
                <input type="hidden" name="product_id" id="restock_product_id" value="">
                <input type="hidden" name="restock_target" value="sale">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($current_request_uri); ?>">

                <div class="modal-body-scroll">
                    <!-- Info Barang Terpilih -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: var(--radius-md); padding: 12px; margin-bottom: 14px; display: flex; align-items: center; gap: 12px;">
                        <div id="restock_thumb_box" style="width: 50px; height: 50px; border-radius: var(--radius-sm); overflow: hidden; background: #e2e8f0; flex-shrink: 0; border: 1px solid #cbd5e1;">
                            <img id="restock_thumb_img" src="" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div style="min-width: 0; flex: 1;">
                            <h4 id="restock_product_name" style="font-size: 14px; font-weight: 800; color: var(--text-main); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Nama Barang</h4>
                            <div style="display: flex; gap: 8px; font-size: 11.5px; color: var(--text-muted); margin-top: 3px;">
                                <span>Kategori: <strong id="restock_product_cat" style="color: var(--forest-900);">-</strong></span>
                                <span>&bull;</span>
                                <span>Stok Toko Saat Ini: <strong id="restock_current_sale" style="color: #1e40af;">0</strong> unit</span>
                            </div>
                        </div>
                    </div>

                    <!-- Jumlah Unit Restock -->
                    <div class="form-field-group">
                        <label for="restock_qty_sale">Jumlah Unit yang Ingin Direstock *</label>
                        <div style="position: relative;">
                            <input type="number" name="qty_sale" id="restock_qty_sale" value="10" min="1" style="font-size: 15px; font-weight: 700; color: var(--forest-950);" oninput="calculateRestockCostSummary()">
                            <span style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b; font-size: 12px;">Unit Barang</span>
                        </div>
                    </div>

                    <!-- Grid 2 Kolom: Harga Modal & Harga Jual -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px;">
                        <div class="form-field-group">
                            <label for="restock_cost_per_unit">Harga Modal Beli / Kulakan (Rp) *</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b; font-size: 12px;">Rp</span>
                                <input type="number" name="cost_per_unit" id="restock_cost_per_unit" min="0" placeholder="0" style="padding-left: 36px; font-weight: 700;" oninput="onCostOrPriceChanged('cost')">
                            </div>
                            <small style="color: #64748b; font-size: 11px; margin-top: 3px; display: block;">Modal kulakan per unit barang.</small>
                        </div>

                        <div class="form-field-group">
                            <label for="restock_selling_price">Harga Jual ke Pelanggan (Rp) *</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #1e40af; font-size: 12px;">Rp</span>
                                <input type="number" name="selling_price" id="restock_selling_price" min="0" placeholder="0" style="padding-left: 36px; font-weight: 700; color: #1e40af;" oninput="onCostOrPriceChanged('sell')">
                            </div>
                            <small style="color: #64748b; font-size: 11px; margin-top: 3px; display: block;">Harga tayang di katalog toko.</small>
                        </div>
                    </div>

                    <!-- Rekomendasi Margin 1-Click Preset -->
                    <div style="margin-top: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 10px 12px;">
                        <span style="font-size: 11.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">
                            Pilih Rekomendasi Margin Keuntungan Otomatis:
                        </span>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                            <button type="button" class="btn-margin-chip" data-margin="0.20" onclick="applyMarginPreset(0.20, this)">+20% Hemat</button>
                            <button type="button" class="btn-margin-chip" data-margin="0.25" onclick="applyMarginPreset(0.25, this)">+25% Standar</button>
                            <button type="button" class="btn-margin-chip active" data-margin="0.30" onclick="applyMarginPreset(0.30, this)">+30% Rekomendasi</button>
                            <button type="button" class="btn-margin-chip" data-margin="0.40" onclick="applyMarginPreset(0.40, this)">+40% Untung Tinggi</button>
                            <button type="button" class="btn-margin-chip" data-margin="0.50" onclick="applyMarginPreset(0.50, this)">+50% Maksimal</button>
                        </div>
                    </div>

                    <!-- Panel Analisis Finansial & Profit Otomatis -->
                    <div style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border: 1.5px solid #86efac; border-radius: var(--radius-md); padding: 14px; margin-top: 12px;">
                        <div style="font-size: 11.5px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <span>Analisis Profit & Dampak Kas Toko</span>
                            <span id="badge_profit_margin" style="background: #16a34a; color: #ffffff; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700;">Margin 30%</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #374151; margin-bottom: 5px;">
                            <span>Keuntungan (Profit) per Unit:</span>
                            <strong id="lbl_profit_per_unit" style="color: #15803d; font-size: 13.5px;">+ Rp 0</strong>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #b91c1c; margin-bottom: 5px;">
                            <span>Total Modal Restock (Potong Kas):</span>
                            <strong id="restock_summary_total_cost">- Rp 0</strong>
                        </div>

                        <div style="display: flex; justify-content: space-between; font-size: 12.5px; color: #1e40af; margin-bottom: 5px;">
                            <span>Proyeksi Total Omset Penjualan:</span>
                            <strong id="restock_summary_total_revenue">Rp 0</strong>
                        </div>

                        <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 12px; margin: 8px 0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 700; color: #14532d; font-size: 12.5px;">Estimasi Total Laba Bersih:</span>
                            <strong id="lbl_total_estimated_profit" style="color: #15803d; font-size: 15px; font-family: 'Outfit', sans-serif;">+ Rp 0</strong>
                        </div>

                        <div style="border-top: 1px dashed #86efac; margin-top: 6px; padding-top: 6px; display: flex; justify-content: space-between; font-size: 12.5px;">
                            <span style="color: #4b5563;">Sisa Saldo Kas Toko:</span>
                            <strong id="restock_summary_remaining_balance" style="color: #15803d; font-family: 'Outfit', sans-serif;">Rp <?php echo number_format($store_cash, 0, ',', '.'); ?></strong>
                        </div>
                        <div id="restock_warning_insufficient" style="display: none; margin-top: 8px; background: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; padding: 7px 10px; border-radius: var(--radius-sm); font-size: 11.5px; font-weight: 700;">
                            ⚠️ Saldo kas toko tidak mencukupi untuk biaya restock ini! Kurangi unit atau sesuaikan harga modal.
                        </div>
                    </div>

                    <!-- Opsi Perbarui Harga Jual di Katalog -->
                    <div style="margin-top: 10px; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-sm);">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: var(--text-main); cursor: pointer; margin: 0;">
                            <input type="checkbox" name="update_price_sale" id="update_price_sale" value="1" checked style="width: 15px; height: 15px;">
                            <span>Perbarui harga jual di katalog toko dengan harga ini</span>
                        </label>
                    </div>

                    <!-- Catatan Restock -->
                    <div class="form-field-group" style="margin-top: 10px;">
                        <label for="restock_notes">Catatan Restock / Keterangan (Opsional)</label>
                        <input type="text" name="notes" id="restock_notes" placeholder="Contoh: Kulakan stok baru untuk liburan akhir tahun">
                    </div>
                </div>

                <div class="modal-footer-bar">
                    <button type="button" class="btn-modal-cancel-btn" onclick="closeModalRestock()">Batal</button>
                    <button type="submit" name="restock_produk" id="btn_submit_restock" class="btn-modal-confirm-btn">
                        Simpan Restock Unit Toko
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const CURRENT_STORE_CASH = <?php echo json_encode((float)$store_cash); ?>;
        let activeRestockProduct = null;
        let selectedMarginPreset = 0.30;

        function applyMarginPreset(marginPct, btn) {
            selectedMarginPreset = marginPct;
            document.querySelectorAll('.btn-margin-chip').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');

            let sellPrice = parseFloat(document.getElementById('restock_selling_price').value) || 0;
            if (sellPrice <= 0 && activeRestockProduct) {
                sellPrice = parseFloat(activeRestockProduct.price_sale) || (parseFloat(activeRestockProduct.price) * 10) || 100000;
                document.getElementById('restock_selling_price').value = Math.round(sellPrice);
            }

            // Hitung harga modal: cost = sell * (1 - margin)
            let costPrice = Math.round(sellPrice * (1 - marginPct));
            costPrice = Math.round(costPrice / 1000) * 1000;
            if (costPrice <= 0) costPrice = Math.round(sellPrice * 0.7);

            document.getElementById('restock_cost_per_unit').value = costPrice;
            calculateRestockCostSummary();
        }

        function onCostOrPriceChanged(source) {
            calculateRestockCostSummary();
        }

        function calculateRestockCostSummary() {
            if (!activeRestockProduct) return;

            const qty = parseInt(document.getElementById('restock_qty_sale')?.value) || 0;
            const costPerUnit = parseFloat(document.getElementById('restock_cost_per_unit')?.value) || 0;
            const sellPrice = parseFloat(document.getElementById('restock_selling_price')?.value) || 0;

            const totalCost = qty * costPerUnit;
            const totalRevenue = qty * sellPrice;
            const profitPerUnit = Math.max(0, sellPrice - costPerUnit);
            const totalProfit = qty * profitPerUnit;

            const marginPct = (sellPrice > 0) ? Math.round((profitPerUnit / sellPrice) * 100) : 0;
            const remainingCash = CURRENT_STORE_CASH - totalCost;

            const badgeMargin = document.getElementById('badge_profit_margin');
            const lblProfitUnit = document.getElementById('lbl_profit_per_unit');
            const costEl = document.getElementById('restock_summary_total_cost');
            const revEl = document.getElementById('restock_summary_total_revenue');
            const profitEl = document.getElementById('lbl_total_estimated_profit');
            const remEl = document.getElementById('restock_summary_remaining_balance');
            const warnEl = document.getElementById('restock_warning_insufficient');
            const btnSubmit = document.getElementById('btn_submit_restock');

            if (badgeMargin) badgeMargin.textContent = 'Margin ' + marginPct + '%';
            if (lblProfitUnit) lblProfitUnit.textContent = '+ Rp ' + profitPerUnit.toLocaleString('id-ID') + ' (' + marginPct + '%)';
            if (costEl) costEl.textContent = '- Rp ' + totalCost.toLocaleString('id-ID');
            if (revEl) revEl.textContent = 'Rp ' + totalRevenue.toLocaleString('id-ID');
            if (profitEl) profitEl.textContent = '+ Rp ' + totalProfit.toLocaleString('id-ID');

            if (remEl) {
                remEl.textContent = 'Rp ' + remainingCash.toLocaleString('id-ID');
                remEl.style.color = (remainingCash < 0) ? '#dc2626' : '#15803d';
            }

            if (totalCost > CURRENT_STORE_CASH) {
                if (warnEl) warnEl.style.display = 'block';
                if (btnSubmit) {
                    btnSubmit.disabled = true;
                    btnSubmit.style.opacity = '0.5';
                    btnSubmit.style.cursor = 'not-allowed';
                }
            } else {
                if (warnEl) warnEl.style.display = 'none';
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.style.opacity = '1';
                    btnSubmit.style.cursor = 'pointer';
                }
            }
        }

        // Modal Restock
        function openModalRestock(product) {
            activeRestockProduct = product;
            document.getElementById('restock_product_id').value = product.id;
            document.getElementById('restock_product_name').textContent = product.name;
            document.getElementById('restock_product_cat').textContent = product.category || 'Outdoor Gear';
            document.getElementById('restock_current_sale').textContent = product.stock_sale || 0;
            document.getElementById('restock_qty_sale').value = 10;

            let sellPrice = parseFloat(product.price_sale) || 0;
            if (sellPrice <= 0) {
                sellPrice = (parseFloat(product.price) * 10) || 100000;
            }

            let costPrice = parseFloat(product.cost_price) || 0;
            if (costPrice <= 0) {
                costPrice = Math.round((sellPrice * 0.70) / 1000) * 1000;
            }

            document.getElementById('restock_selling_price').value = Math.round(sellPrice);
            document.getElementById('restock_cost_per_unit').value = Math.round(costPrice);

            document.querySelectorAll('.btn-margin-chip').forEach(b => {
                if (b.getAttribute('data-margin') === '0.30') b.classList.add('active');
                else b.classList.remove('active');
            });

            const imgEl = document.getElementById('restock_thumb_img');
            if (product.image && product.image.trim() !== '') {
                imgEl.src = '../uploads/products/' + product.image;
            } else {
                imgEl.src = 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=80&auto=format&fit=crop&q=60';
            }

            calculateRestockCostSummary();
            document.getElementById('modalRestockOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModalRestock() {
            document.getElementById('modalRestockOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        window.addEventListener('click', function(e) {
            const mRestock = document.getElementById('modalRestockOverlay');
            if (e.target === mRestock) closeModalRestock();
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModalRestock();
            }
        });
    </script>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>
</html>
