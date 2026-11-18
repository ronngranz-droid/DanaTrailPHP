<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek apakah yang login adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=" . urlencode("Akses ditolak! Anda bukan Administrator."));
    exit;
}

include '../config/config.php';
/** @var mysqli $conn */

// 1. Hitung jumlah data user
$data_user   = mysqli_query($conn, "SELECT id FROM users");
$jumlah_user = $data_user ? mysqli_num_rows($data_user) : 0;

// 2. Hitung jumlah data kategori & list kategori
$data_kategori   = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
$jumlah_kategori = $data_kategori ? mysqli_num_rows($data_kategori) : 0;
$list_kategori   = [];
if ($data_kategori && mysqli_num_rows($data_kategori) > 0) {
    while ($cat = mysqli_fetch_assoc($data_kategori)) {
        $list_kategori[] = $cat;
    }
}

// 3. Hitung total produk & agregat stok
$q_product_stats = mysqli_query($conn, "SELECT 
    COUNT(*) as total_items, 
    SUM(stock) as total_units,
    SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock,
    SUM(CASE WHEN stock > 0 AND stock <= 3 THEN 1 ELSE 0 END) as low_stock,
    SUM(CASE WHEN stock > 3 THEN 1 ELSE 0 END) as ready_stock
    FROM products");
$prod_stats     = $q_product_stats ? mysqli_fetch_assoc($q_product_stats) : [];
$jumlah_produk  = (int)($prod_stats['total_items'] ?? 0);
$total_units    = (int)($prod_stats['total_units'] ?? 0);
$stok_habis     = (int)($prod_stats['out_of_stock'] ?? 0);
$stok_rendah    = (int)($prod_stats['low_stock'] ?? 0);
$stok_aman      = (int)($prod_stats['ready_stock'] ?? 0);

// Hitung breakdown produk per kategori
$cat_product_counts = [];
$q_cpc = mysqli_query($conn, "SELECT category, COUNT(*) as cnt FROM products GROUP BY category");
if ($q_cpc) {
    while ($rc = mysqli_fetch_assoc($q_cpc)) {
        $cat_product_counts[$rc['category']] = (int)$rc['cnt'];
    }
}

// 4. Hitung pesanan sewa yang menunggu konfirmasi
$data_pending   = mysqli_query($conn, "SELECT COUNT(*) as total FROM rentals WHERE status='Menunggu Konfirmasi'");
$jumlah_pending = $data_pending ? (int)mysqli_fetch_assoc($data_pending)['total'] : 0;

// 5. Hitung total pendapatan sewa yang disetujui (Disewa / Selesai)
$data_omset     = mysqli_query($conn, "SELECT SUM(total_price) as total FROM rentals WHERE status IN ('Disewa', 'Selesai')");
$total_omset    = $data_omset ? (float)(mysqli_fetch_assoc($data_omset)['total'] ?? 0) : 0;

// 6. Ambil 6 transaksi sewa terbaru untuk ringkasan operasional
$q_recent_rentals = mysqli_query($conn, "SELECT rentals.*, users.name as user_name, users.email as user_email, users.phone as user_phone, products.name as product_name, products.image as product_image
    FROM rentals
    LEFT JOIN users ON rentals.user_id = users.id
    LEFT JOIN products ON rentals.product_id = products.id
    ORDER BY rentals.id DESC
    LIMIT 6");

// 7. Hitung metrik penjualan toko
$data_omset_toko = mysqli_query($conn, "SELECT SUM(total_price) as total FROM orders WHERE payment_status = 'Lunas'");
$total_omset_toko = $data_omset_toko ? (float)(mysqli_fetch_assoc($data_omset_toko)['total'] ?? 0) : 0;

// 8. Hitung Grand Total Pendapatan Gabungan (Sewa Alat + Penjualan Toko)
$total_omset_gabungan = $total_omset + $total_omset_toko;

$data_pending_toko = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status = 'Menunggu Konfirmasi'");
$jumlah_pending_toko = $data_pending_toko ? (int)mysqli_fetch_assoc($data_pending_toko)['total'] : 0;

// Pemetaan slug kategori untuk shortcut card
$category_slug_map = [
    'Tenda & Shelter'         => 'tenda',
    'Carrier & Tas'           => 'carrier',
    'Sleeping Gear'           => 'sleeping-gear',
    'Cooking Set'             => 'cooking',
    'Penerangan & Elektronik' => 'elektronik',
    'Footwear & Apparel'      => 'footwear'
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Ringkasan Operasional — Admin DANATRAIL</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ==========================================================================
           DANATRAIL ADMIN DESIGN SYSTEM (DASHBOARD EXECUTIVE SUMMARY)
           ========================================================================== */
        :root {
            --forest-950: #041f14;
            --forest-900: #073522;
            --forest-800: #0e5638;
            --forest-700: #166e4a;
            --forest-600: #23895e;
            
            --gold-500: #f5b800;
            --gold-600: #d99b00;
            --gold-100: #fef8e6;
            
            --emerald-600: #059669;
            --emerald-500: #10b981;
            --emerald-50: #ecfdf5;
            --emerald-border: #a7f3d0;
            
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

            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
            --shadow-card: 0 2px 10px rgba(7, 53, 34, 0.05);

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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            letter-spacing: -0.015em;
        }

        /* SIDEBAR UNIFIED STYLES */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--forest-950) 0%, var(--forest-900) 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            padding: 22px 16px;
            position: sticky;
            top: 0;
            height: 100vh;
            border-right: 1px solid rgba(255, 255, 255, 0.07);
            flex-shrink: 0;
            z-index: 90;
            transition: transform 0.25s ease;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-brand-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #ffffff;
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid var(--gold-500);
            overflow: hidden;
            flex-shrink: 0;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .sidebar-brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.8px;
            color: #ffffff;
            line-height: 1.1;
        }

        .brand-tagline {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--gold-500);
            margin-top: 2px;
        }

        .sidebar-mobile-close {
            display: none;
            background: rgba(255, 255, 255, 0.08);
            border: none;
            color: #ffffff;
            padding: 6px;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }

        .user-profile-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            padding: 10px 12px;
            border-radius: var(--radius-md);
            margin: 16px 0 14px 0;
        }

        .user-avatar-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .user-avatar-initial {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--forest-800);
            border: 1px solid var(--gold-500);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 13px;
            color: #ffffff;
        }

        .status-indicator-dot {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: #10b981;
            border: 1.5px solid var(--forest-950);
            border-radius: var(--radius-pill);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-display-name {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role-chip {
            font-size: 10.5px;
            font-weight: 600;
            color: #86efac;
            display: flex;
            align-items: center;
        }

        .nav-group-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7b8f84;
            font-weight: 800;
            margin: 8px 0 4px 6px;
        }

        .nav-pending-badge {
            background: var(--gold-500);
            color: var(--forest-950);
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: var(--radius-pill);
        }

        .sidebar-footer {
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: auto;
        }

        .btn-sidebar-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 8px 12px;
            border-radius: var(--radius-md);
            background: rgba(239, 68, 68, 0.08);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.15);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition: var(--transition-fast);
        }

        .btn-sidebar-logout:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(4, 31, 20, 0.6);
            -webkit-backdrop-filter: blur(3px);
            backdrop-filter: blur(3px);
            z-index: 89;
        }

        /* MAIN CONTENT WRAPPER */
        .main-wrapper {
            flex: 1;
            padding: 24px 32px 40px 32px;
            overflow-y: auto;
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
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
        }

        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mobile-brand img {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: #fff;
            padding: 1px;
        }

        .mobile-brand span {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        .btn-toggle-menu {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #ffffff;
            padding: 6px 10px;
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        /* PAGE HEADER */
        .dashboard-hero-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .dashboard-hero-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--forest-950);
        }

        .dashboard-hero-header p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .quick-nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-quick-manage {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            color: var(--forest-900);
            border-radius: var(--radius-md);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: all var(--transition-fast);
            box-shadow: var(--shadow-subtle);
        }

        .btn-quick-manage:hover {
            border-color: var(--forest-800);
            background: #f0f6f2;
            transform: translateY(-1px);
        }

        .btn-quick-manage.primary-btn {
            background: var(--forest-900);
            color: var(--gold-500);
            border-color: var(--forest-900);
        }

        .btn-quick-manage.primary-btn:hover {
            background: var(--forest-800);
            color: #ffffff;
        }

        /* BALANCED EXECUTIVE METRICS GRID */
        .stats-finance-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 14px;
        }

        .stats-ops-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }

        .metric-card-box {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 18px 20px;
            box-shadow: var(--shadow-subtle);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 110px;
            transition: transform var(--transition-fast), border-color var(--transition-fast);
        }

        .metric-card-box:hover {
            transform: translateY(-2px);
            border-color: #cbd5ce;
        }

        .metric-card-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3.5px;
            background: #d1dcd4;
        }

        .metric-card-box.card-omset::before {
            background: var(--emerald-500);
        }

        .metric-card-box.card-pending::before {
            background: var(--gold-500);
        }

        .metric-card-box.card-gear::before {
            background: var(--forest-700);
        }

        .metric-card-box.card-cat::before {
            background: #3b82f6;
        }

        .metric-card-box.card-users::before {
            background: #8b5cf6;
        }

        .metric-head-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .metric-num-val {
            font-family: 'Outfit', sans-serif;
            font-size: 25px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
        }

        .metric-num-val.val-omset {
            font-size: 20px;
            color: var(--forest-800);
        }

        .metric-num-val.val-pending {
            color: #b45309;
        }

        .metric-sub-note {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* 2 COLUMN DASHBOARD BODY */
        .dashboard-split-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        /* INVENTORY HEALTH CARD */
        .inventory-health-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 18px;
            box-shadow: var(--shadow-subtle);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .health-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-light);
        }

        .health-card-head h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .inventory-status-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .status-health-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: var(--radius-md);
            background: var(--bg-canvas);
            border: 1px solid var(--border-light);
        }

        .status-health-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-body);
        }

        .status-health-val {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: var(--text-main);
        }

        .health-cta-block {
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid var(--border-light);
        }

        .btn-full-inventory {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px;
            border-radius: var(--radius-md);
            background: var(--forest-900);
            color: var(--gold-500);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 700;
            transition: all var(--transition-fast);
        }

        .btn-full-inventory:hover {
            background: var(--forest-800);
            color: #ffffff;
        }

        /* RECENT ORDERS TABLE CARD */
        .recent-orders-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-header-with-link {
            padding: 14px 18px;
            background: #fafbfa;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header-with-link h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        .link-view-all {
            color: var(--forest-700);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .link-view-all:hover {
            color: var(--forest-900);
            text-decoration: underline;
        }

        .table-responsive-box {
            overflow-x: auto;
            width: 100%;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .orders-table th {
            background: #f4f6f4;
            color: var(--text-muted);
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .orders-table td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .orders-table tr:hover td {
            background-color: #fbfdfc;
        }

        .order-code-badge {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            color: var(--forest-900);
            background: #eef4f0;
            padding: 2px 7px;
            border-radius: 4px;
            border: 1px solid #d5e3da;
        }

        .status-badge-chip {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: var(--radius-pill);
            display: inline-block;
            white-space: nowrap;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .badge-disewa {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .badge-selesai {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .badge-batal {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* CATEGORIES SHORTCUT GRID */
        .category-shortcuts-section {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 18px;
            box-shadow: var(--shadow-subtle);
        }

        .cat-shortcuts-head {
            margin-bottom: 14px;
        }

        .cat-shortcuts-head h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        .cat-shortcuts-head p {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .category-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .cat-shortcut-card {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            text-decoration: none;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all var(--transition-fast);
        }

        .cat-shortcut-card:hover {
            background: #ffffff;
            border-color: var(--forest-700);
            box-shadow: var(--shadow-subtle);
            transform: translateY(-1px);
        }

        .cat-card-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .cat-card-title {
            font-weight: 700;
            font-size: 13px;
            color: var(--forest-950);
        }

        .cat-card-sub {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .cat-card-arrow {
            color: var(--text-subtle);
            transition: transform var(--transition-fast), color var(--transition-fast);
        }

        .cat-shortcut-card:hover .cat-card-arrow {
            color: var(--forest-800);
            transform: translateX(3px);
        }

        /* RESPONSIVE BREAKPOINTS */
        @media (max-width: 992px) {
            .dashboard-split-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                transform: translateX(-100%);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .sidebar-backdrop.active {
                display: block;
            }
            .sidebar-mobile-close {
                display: block;
            }
            .mobile-top-bar {
                display: flex;
            }
            .main-wrapper {
                padding: 16px 14px 32px 14px;
            }
            .stats-executive-grid {
                grid-template-columns: 1fr 1fr;
            }
            .dashboard-hero-header {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</head>

<body>

    <!-- Mobile Top Header Bar -->
    <header class="mobile-top-bar">
        <div class="mobile-brand">
            <img src="../assets/logo.png" alt="DANATRAIL" onerror="this.src='https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=80&auto=format&fit=crop&q=60'">
            <span>DANATRAIL Admin</span>
        </div>
        <button type="button" class="btn-toggle-menu" onclick="toggleAdminSidebar()" aria-label="Buka Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7" />
            </svg>
            <span>Menu</span>
        </button>
    </header>

    <!-- Unified Sidebar Component -->
    <?php include 'menu.php'; ?>

    <!-- Main Content -->
    <main class="main-wrapper">

        <!-- Page Header -->
        <div class="dashboard-hero-header">
            <div>
                <h1>Dashboard Ringkasan Operasional</h1>
                <p>Pantau metrik pendapatan rental, pesanan aktif, ketersediaan inventaris, dan navigasi cepat.</p>
            </div>
            <div class="quick-nav-actions">
                <a href="perlengkapan.php" class="btn-quick-manage primary-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <span>Kelola Perlengkapan</span>
                </a>
                <a href="transaksi.php" class="btn-quick-manage">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <span>Data Penyewaan</span>
                </a>
                <a href="pesanan.php" class="btn-quick-manage" style="border-color: #fde68a; background: #fffbeb; color: #92400e;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <span>Pesanan Toko</span>
                    <?php if ($jumlah_pending_toko > 0): ?>
                        <span style="background: #ef4444; color: #ffffff; font-size: 10px; font-weight: 800; padding: 1px 6px; border-radius: 999px;"><?php echo $jumlah_pending_toko; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Executive Metric Cards -->
                <!-- Row 1: Pilar Keuangan & Total Omset (3 Kolom Simetris) -->
        <div class="stats-finance-grid">
            <!-- Grand Total Pendapatan Gabungan -->
            <div class="metric-card-box" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1.5px solid #86efac; box-shadow: 0 4px 14px rgba(22, 163, 74, 0.08);">
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 3.5px; background: #16a34a;"></div>
                <div class="metric-head-row">
                    <span style="color: #14532d; font-weight: 800; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                        <span>Total Omset Gabungan</span>
                        <span style="font-size: 9px; background: #16a34a; color: #fff; padding: 2px 7px; border-radius: 99px; text-transform: uppercase; font-weight: 800;">Sewa + Jual</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color: #15803d;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="metric-num-val" style="color: #14532d; font-size: 24px; font-weight: 900; margin: 6px 0 3px 0;">
                    Rp <?php echo number_format($total_omset_gabungan, 0, ',', '.'); ?>
                </div>
                <div class="metric-sub-note" style="color: #166534; font-weight: 600; font-size: 11.5px;">
                    Akumulasi bersih dari sewa alat & penjualan toko
                </div>
            </div>

            <!-- Total Pendapatan Sewa -->
            <div class="metric-card-box card-omset" style="background: #ffffff; border-color: #d1fae5;">
                <div class="metric-head-row">
                    <span style="color: #065f46; font-weight: 700;">Omset Sewa Alat</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--emerald-600);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <div class="metric-num-val val-omset" style="font-size: 22px; margin: 6px 0 3px 0;">
                    Rp <?php echo number_format($total_omset, 0, ',', '.'); ?>
                </div>
                <div class="metric-sub-note">Penyewaan gear disetujui & selesai</div>
            </div>

            <!-- Total Pendapatan Penjualan Toko -->
            <div class="metric-card-box card-omset" style="background: #ffffff; border-color: #fed7aa;">
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 3.5px; background: #ea580c;"></div>
                <div class="metric-head-row">
                    <span style="color: #9a3412; font-weight: 700;">Omset Toko (Jual)</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #ea580c;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <div class="metric-num-val val-omset" style="color: #c2410c; font-size: 22px; margin: 6px 0 3px 0;">
                    Rp <?php echo number_format($total_omset_toko, 0, ',', '.'); ?>
                </div>
                <div class="metric-sub-note">Penjualan lunas terverifikasi</div>
            </div>
        </div>

        <!-- Row 2: Metrik Aktivitas & Inventaris (4 Kolom Simetris) -->
        <div class="stats-ops-grid">
            <!-- Pesanan Sewa Pending (Klik untuk buka Transaksi Sewa) -->
            <a href="transaksi.php?status=Menunggu+Konfirmasi" class="metric-card-box card-pending" style="text-decoration: none; color: inherit; cursor: pointer;" title="Klik untuk verifikasi transaksi sewa pending">
                <div class="metric-head-row">
                    <span>Sewa Masuk</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--gold-500);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="metric-num-val val-pending" style="margin: 4px 0 2px 0;">
                    <?php echo $jumlah_pending; ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Sewa</span>
                </div>
                <div class="metric-sub-note" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Menunggu verifikasi sewa</span>
                    <span style="font-size: 11px; font-weight: 700; color: #b45309;">Buka &rarr;</span>
                </div>
            </a>

            <!-- Pesanan Toko Pending (Klik untuk buka Pesanan Toko) -->
            <a href="pesanan.php" class="metric-card-box card-pending" style="background: #ffffff; border-color: #fef08a; text-decoration: none; color: inherit; cursor: pointer;" title="Klik untuk mengelola Pesanan Toko">
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 3.5px; background: #eab308;"></div>
                <div class="metric-head-row">
                    <span style="color: #854d0e; font-weight: 700;">Pesanan Toko Baru</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #ca8a04;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <div class="metric-num-val val-pending" style="color: #a16207; margin: 4px 0 2px 0;">
                    <?php echo $jumlah_pending_toko; ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Pesanan</span>
                </div>
                <div class="metric-sub-note" style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Belanja baru menunggu diproses</span>
                    <span style="font-size: 11px; font-weight: 700; color: #a16207;">Buka &rarr;</span>
                </div>
            </a>

            <!-- Total Perlengkapan (Klik untuk kelola Produk) -->
            <a href="perlengkapan.php" class="metric-card-box card-gear" style="text-decoration: none; color: inherit; cursor: pointer;" title="Klik untuk mengelola katalog produk">
                <div class="metric-head-row">
                    <span>Total Produk</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--forest-700);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="metric-num-val" style="margin: 4px 0 2px 0;">
                    <?php echo $jumlah_produk; ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Item</span>
                </div>
                <div class="metric-sub-note" style="display: flex; justify-content: space-between; align-items: center;">
                    <span><?php echo $total_units; ?> total unit fisik</span>
                    <span style="font-size: 11px; font-weight: 700; color: var(--forest-700);">Kelola &rarr;</span>
                </div>
            </a>

            <!-- Total Pengguna -->
            <div class="metric-card-box card-users">
                <div class="metric-head-row">
                    <span>Total Member</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #8b5cf6;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <div class="metric-num-val" style="margin: 4px 0 2px 0;">
                    <?php echo $jumlah_user; ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Member</span>
                </div>
                <div class="metric-sub-note">Akun terdaftar di sistem</div>
            </div>
        </div>

        <!-- Split Layout: Inventory Health & Recent Rentals -->
        <div class="dashboard-split-layout">
            <!-- Left: Ringkasan Inventory -->
            <div class="inventory-health-card">
                <div class="health-card-head">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--forest-700);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span>Ringkasan Inventory</span>
                    </h3>
                </div>

                <div class="inventory-status-list">
                    <div class="status-health-row">
                        <div class="status-health-label">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--emerald-500);"></span>
                            <span>Stok Aman (> 3 unit)</span>
                        </div>
                        <div class="status-health-val" style="color: var(--emerald-600);"><?php echo $stok_aman; ?> item</div>
                    </div>

                    <div class="status-health-row">
                        <div class="status-health-label">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
                            <span>Stok Rendah (&le; 3 unit)</span>
                        </div>
                        <div class="status-health-val" style="color: #b45309;"><?php echo $stok_rendah; ?> item</div>
                    </div>

                    <div class="status-health-row">
                        <div class="status-health-label">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444;"></span>
                            <span>Produk Habis (0 unit)</span>
                        </div>
                        <div class="status-health-val" style="color: #dc2626;"><?php echo $stok_habis; ?> item</div>
                    </div>

                    <div class="status-health-row">
                        <div class="status-health-label">
                            <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--forest-800);"></span>
                            <span>Total Unit Fisik</span>
                        </div>
                        <div class="status-health-val"><?php echo $total_units; ?> unit</div>
                    </div>
                </div>

                <div class="health-cta-block">
                    <a href="perlengkapan.php" class="btn-full-inventory">
                        <span>Buka Pusat Perlengkapan</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Right: Pesanan Terbaru -->
            <div class="recent-orders-card">
                <div class="card-header-with-link">
                    <h3>Pesanan Masuk & Transaksi Terbaru</h3>
                    <a href="transaksi.php" class="link-view-all">
                        <span>Lihat Semua Transaksi</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>

                <div class="table-responsive-box">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Penyewa</th>
                                <th>Perlengkapan</th>
                                <th>Jaminan / KTP</th>
                                <th>Tgl Sewa</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($q_recent_rentals && mysqli_num_rows($q_recent_rentals) > 0): ?>
                                <?php while ($r = mysqli_fetch_assoc($q_recent_rentals)): 
                                    $st = $r['status'] ?? 'Menunggu Konfirmasi';
                                    $badge_class = 'badge-pending';
                                    if ($st === 'Disewa') $badge_class = 'badge-disewa';
                                    elseif ($st === 'Selesai') $badge_class = 'badge-selesai';
                                    elseif ($st === 'Dibatalkan') $badge_class = 'badge-batal';

                                    $guarantee_photo = $r['guarantee_photo'] ?? '';
                                    $guarantee_type  = $r['guarantee_type'] ?? 'KTP Asli';
                                ?>
                                    <tr>
                                        <td>
                                            <span class="order-code-badge"><?php echo htmlspecialchars($r['rental_code']); ?></span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main); font-size: 13px;">
                                                <?php echo htmlspecialchars($r['user_name'] ?? 'User #'.$r['user_id']); ?>
                                            </div>
                                            <?php if (!empty($r['user_phone'])): ?>
                                                <div style="font-size: 11px; color: var(--text-muted);">
                                                    WA: <?php echo htmlspecialchars($r['user_phone']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: var(--text-body); max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo htmlspecialchars($r['product_name'] ?? 'Produk #'.$r['product_id']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                                <span style="font-size: 11px; font-weight: 700; color: var(--forest-900); background: #f0fdf4; padding: 2px 7px; border-radius: 4px; border: 1px solid #bbf7d0;">
                                                    <?php echo htmlspecialchars($guarantee_type); ?>
                                                </span>
                                                <?php if (!empty($guarantee_photo) && file_exists('../uploads/guarantees/' . $guarantee_photo)): ?>
                                                    <button type="button" onclick="openAdminPhotoModal('../uploads/guarantees/<?php echo htmlspecialchars($guarantee_photo); ?>', 'Foto Dokumen Jaminan: <?php echo htmlspecialchars(addslashes($r['user_name'] ?? 'User')); ?> (<?php echo htmlspecialchars(addslashes($guarantee_type)); ?>)')" style="background: none; border: none; font-size: 11px; color: var(--forest-700); font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0; display: inline-flex; align-items: center; gap: 3px;">
                                                        <span>Lihat Foto KTP</span>
                                                    </button>
                                                <?php else: ?>
                                                    <span style="font-size: 10.5px; color: var(--text-muted); font-style: italic;">Titip Fisik</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 12px; color: var(--text-muted);">
                                                <?php echo date('d M Y', strtotime($r['rental_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--forest-900);">
                                                Rp <?php echo number_format($r['total_price'], 0, ',', '.'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge-chip <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($st); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="transaksi.php" style="font-size: 11px; font-weight: 700; color: var(--forest-800); text-decoration: none; padding: 3px 8px; border: 1px solid var(--border-color); border-radius: 6px; background: #ffffff;">
                                                Kelola →
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 32px; color: var(--text-muted);">
                                        Belum ada data transaksi penyewaan.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Category Shortcuts Section -->
        <section class="category-shortcuts-section">
            <div class="cat-shortcuts-head">
                <h3>Pusat Kategori Perlengkapan Outdoor</h3>
                <p>Klik kategori untuk mengelola stok, tarif sewa, dan katalog perlengkapan secara spesifik.</p>
            </div>

            <div class="category-cards-grid">
                <?php foreach ($list_kategori as $cat_item): 
                    $c_name = $cat_item['name'];
                    $c_slug = $category_slug_map[$c_name] ?? strtolower(str_replace(' ', '-', $c_name));
                    $c_count = $cat_product_counts[$c_name] ?? 0;
                ?>
                    <a href="perlengkapan.php?kategori=<?php echo urlencode($c_slug); ?>" class="cat-shortcut-card">
                        <div class="cat-card-left">
                            <span class="cat-card-title"><?php echo htmlspecialchars($c_name); ?></span>
                            <span class="cat-card-sub"><?php echo $c_count; ?> perlengkapan</span>
                        </div>
                        <div class="cat-card-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <!-- Modal Preview Foto Dokumen Jaminan / KTP di Dashboard Admin -->
    <div id="adminPhotoModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #ffffff; border-radius: 14px; max-width: 540px; width: 100%; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); background: var(--bg-canvas);">
                <strong id="adminPhotoModalTitle" style="font-size: 14px; color: var(--forest-950);">Verifikasi Foto KTP / Jaminan</strong>
                <button type="button" onclick="closeAdminPhotoModal()" style="background: none; border: none; font-size: 22px; line-height: 1; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
            <div style="padding: 16px; text-align: center; background: #081a10;">
                <img id="adminPhotoModalImg" src="" alt="Foto Jaminan" style="max-height: 420px; max-width: 100%; object-fit: contain; border-radius: 6px;">
            </div>
            <div style="padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; background: #ffffff; border-top: 1px solid var(--border-color);">
                <a id="adminPhotoDownloadBtn" href="" target="_blank" download style="font-size: 12px; font-weight: 700; color: var(--forest-800); text-decoration: none;">⬇ Buka Ukuran Penuh</a>
                <button type="button" onclick="closeAdminPhotoModal()" style="padding: 6px 16px; border-radius: 6px; background: var(--forest-900); color: #ffffff; font-weight: 700; border: none; font-size: 12.5px; cursor: pointer;">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        function openAdminPhotoModal(imgSrc, title) {
            const modal = document.getElementById('adminPhotoModal');
            const img = document.getElementById('adminPhotoModalImg');
            const titleEl = document.getElementById('adminPhotoModalTitle');
            const downloadBtn = document.getElementById('adminPhotoDownloadBtn');

            if (img && modal) {
                img.src = imgSrc;
                if (titleEl && title) titleEl.innerText = title;
                if (downloadBtn) downloadBtn.href = imgSrc;
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeAdminPhotoModal() {
            const modal = document.getElementById('adminPhotoModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        window.addEventListener('click', function(e) {
            const modal = document.getElementById('adminPhotoModal');
            if (e.target === modal) {
                closeAdminPhotoModal();
            }
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAdminPhotoModal();
            }
        });
    </script>
</body>
</html>