<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek apakah yang login adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=" . urlencode("Akses ditolak! Anda bukan Administrator."));
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
require_once __DIR__ . '/../config/stock_cash_helper.php';
/** @var mysqli $conn */

// Saldo kas toko untuk keperluan pengecekan dan kalkulasi pengadaan
$store_cash = function_exists('get_store_cash_balance') ? get_store_cash_balance($conn) : 0.00;
$inv_summary = function_exists('get_inventory_financial_summary') ? get_inventory_financial_summary($conn) : [];

$grand_total_stock = (int)($inv_summary['total_units'] ?? 0);
$grand_total_rent  = (int)($inv_summary['total_units_rent'] ?? 0);
$grand_total_sale  = (int)($inv_summary['total_units_sale'] ?? 0);
$grand_total_value = (float)($inv_summary['total_value'] ?? 0);

// Pemetaan slug kategori resmi ke nama kategori di database
$category_mapping = [
    'tenda'                  => 'Tenda & Shelter',
    'tenda-shelter'          => 'Tenda & Shelter',
    'carrier'                => 'Carrier & Tas',
    'carrier-tas'            => 'Carrier & Tas',
    'sleeping-gear'          => 'Sleeping Gear',
    'sleeping'               => 'Sleeping Gear',
    'cooking'                => 'Cooking Set',
    'cooking-set'            => 'Cooking Set',
    'elektronik'             => 'Penerangan & Elektronik',
    'penerangan-elektronik'  => 'Penerangan & Elektronik',
    'footwear'               => 'Footwear & Apparel',
    'footwear-apparel'       => 'Footwear & Apparel'
];

$filter_kategori_param = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
$selected_category_db  = '';

if (!empty($filter_kategori_param)) {
    $param_lower = strtolower($filter_kategori_param);
    if (isset($category_mapping[$param_lower])) {
        $selected_category_db = $category_mapping[$param_lower];
    } else {
        // Fallback pencocokan nama langsung
        $selected_category_db = $filter_kategori_param;
    }
}

// Pencarian produk
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Query data kategori untuk dropdown & pill
$data_kategori   = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
$jumlah_kategori = $data_kategori ? mysqli_num_rows($data_kategori) : 0;
$list_kategori   = [];
if ($data_kategori && mysqli_num_rows($data_kategori) > 0) {
    while ($cat = mysqli_fetch_assoc($data_kategori)) {
        $list_kategori[] = $cat;
    }
}

// Filter Tipe Produk (rental, sale, both, all)
$filter_tipe = isset($_GET['tipe']) ? strtolower(trim($_GET['tipe'])) : '';

// Hitung total produk & breakdown per kategori dan tipe
$cat_stats = [];
$total_all_items = 0;
$total_rental_items = 0;
$total_sale_items = 0;

$q_all_count = mysqli_query($conn, "SELECT category, product_type, COUNT(*) as cnt FROM products GROUP BY category, product_type");
if ($q_all_count) {
    while ($r = mysqli_fetch_assoc($q_all_count)) {
        $c_name = $r['category'];
        $cat_stats[$c_name] = ($cat_stats[$c_name] ?? 0) + (int)$r['cnt'];
        $total_all_items += (int)$r['cnt'];
        if ($r['product_type'] === 'rental' || $r['product_type'] === 'both') {
            $total_rental_items += (int)$r['cnt'];
        }
        if ($r['product_type'] === 'sale' || $r['product_type'] === 'both') {
            $total_sale_items += (int)$r['cnt'];
        }
    }
}

// Query produk sesuai filter kategori, tipe, & pencarian
$query_produk = "SELECT * FROM products WHERE 1=1";

if (!empty($selected_category_db)) {
    $safe_cat = mysqli_real_escape_string($conn, $selected_category_db);
    $query_produk .= " AND category = '$safe_cat'";
}

if (!empty($filter_tipe)) {
    if ($filter_tipe === 'rental') {
        $query_produk .= " AND (product_type = 'rental' OR product_type = 'both')";
    } elseif ($filter_tipe === 'sale') {
        $query_produk .= " AND (product_type = 'sale' OR product_type = 'both')";
    } elseif ($filter_tipe === 'both') {
        $query_produk .= " AND product_type = 'both'";
    }
}

if (!empty($search)) {
    $query_produk .= " AND (name LIKE '%$search%' OR description LIKE '%$search%' OR category LIKE '%$search%')";
}

$query_produk .= " ORDER BY id DESC";
$data_produk   = mysqli_query($conn, $query_produk);
$jumlah_produk = $data_produk ? mysqli_num_rows($data_produk) : 0;

// Menentukan judul dan subtitle halaman sesuai spesifikasi
$is_category_view = !empty($selected_category_db);
if ($is_category_view) {
    $page_heading   = htmlspecialchars($selected_category_db);
    $page_subtitle  = "Kelola inventaris perlengkapan kategori " . htmlspecialchars($selected_category_db) . ".";
    $search_placeholder = "Cari dalam kategori " . htmlspecialchars($selected_category_db) . "...";
} else {
    $page_heading   = "Kelola Perlengkapan & Toko";
    $page_subtitle  = "Kelola seluruh katalog perlengkapan rental dan produk toko (dijual) DANATRAIL.";
    $search_placeholder = "Cari perlengkapan (nama, kategori, atau kelengkapan)...";
}

$current_request_uri = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_heading; ?> — Admin DANATRAIL</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ==========================================================================
           DANATRAIL ADMIN DESIGN SYSTEM
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

        /* MAIN CONTENT LAYOUT */
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

        /* HEADER SECTION */
        .page-header-flex {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .page-header-title-box h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--forest-950);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header-title-box p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .header-count-tag {
            font-size: 12px;
            font-weight: 700;
            color: var(--forest-800);
            background: var(--emerald-50);
            border: 1px solid var(--emerald-border);
            padding: 2px 10px;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
        }

        .btn-add-gear-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--forest-900);
            color: var(--gold-500);
            border: 1px solid var(--gold-500);
            padding: 9px 18px;
            border-radius: var(--radius-md);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-fast);
            box-shadow: 0 2px 6px rgba(4, 31, 20, 0.15);
        }

        .btn-add-gear-cta:hover {
            background: var(--forest-800);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(4, 31, 20, 0.2);
        }

        /* TOAST FEEDBACK */
        .toast-notification {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 18px;
            font-size: 13.5px;
            font-weight: 600;
            box-shadow: var(--shadow-subtle);
        }

        .toast-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .toast-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .toast-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast-close-btn {
            background: transparent;
            border: none;
            color: inherit;
            cursor: pointer;
            opacity: 0.6;
            padding: 2px;
        }

        .toast-close-btn:hover {
            opacity: 1;
        }

        /* TOOLBAR & CATEGORY PILLS */
        .filter-controls-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            margin-bottom: 18px;
            box-shadow: var(--shadow-subtle);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .search-row-box {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-input-wrap {
            position: relative;
            flex: 1;
            min-width: 260px;
        }

        .search-input-wrap svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: var(--text-subtle);
        }

        .search-input-wrap input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13px;
            background: var(--bg-canvas);
            color: var(--text-main);
            transition: border-color var(--transition-fast), background var(--transition-fast);
        }

        .search-input-wrap input:focus {
            outline: none;
            border-color: var(--forest-800);
            background: #ffffff;
            box-shadow: 0 0 0 2px rgba(14, 86, 56, 0.1);
        }

        .btn-search-trigger {
            background: var(--forest-900);
            color: #ffffff;
            border: none;
            padding: 9px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        .btn-search-trigger:hover {
            background: var(--forest-800);
        }

        .btn-reset-filter {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .btn-reset-filter:hover {
            background: #f1f5f2;
            color: var(--text-main);
        }

        /* Category Filter Pills (Only shown on 'Semua' view or as top switcher) */
        .category-pill-tabs {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            padding-top: 4px;
            border-top: 1px solid var(--border-light);
        }

        .cat-tab-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            color: var(--text-body);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            transition: all var(--transition-fast);
        }

        .cat-tab-link:hover {
            border-color: #cbd5ce;
            background: #eef2ef;
            color: var(--text-main);
        }

        .cat-tab-link.active {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
        }

        .cat-tab-link .pill-cnt {
            font-size: 10.5px;
            padding: 1px 6px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.07);
            color: inherit;
        }

        .cat-tab-link.active .pill-cnt {
            background: var(--gold-500);
            color: var(--forest-950);
            font-weight: 800;
        }

        /* INVENTORY TABLE CARD */
        .inventory-table-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
        }

        .table-card-head {
            padding: 14px 18px;
            background: #fafbfa;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-card-head h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        .table-count-badge {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--forest-800);
            background: #eaf3ee;
            padding: 3px 9px;
            border-radius: var(--radius-pill);
        }

        .table-responsive-box {
            overflow-x: auto;
            width: 100%;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .inventory-table th {
            background: #f4f6f4;
            color: var(--text-muted);
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 11px 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .inventory-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .inventory-table tr:hover td {
            background-color: #fbfdfc;
        }

        /* THUMB MEDIA */
        .product-thumb-media {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            object-fit: cover;
            border: 1px solid var(--border-color);
            background: #ffffff;
            display: block;
        }

        .product-thumb-fallback {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: #edf2ee;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-subtle);
        }

        .product-thumb-fallback svg {
            width: 20px;
            height: 20px;
        }

        /* PRODUCT INFO CELL */
        .product-info-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
            max-width: 320px;
        }

        .product-name-txt {
            font-weight: 700;
            color: var(--text-main);
            font-size: 13.5px;
            line-height: 1.25;
        }

        .product-desc-txt {
            font-size: 11.5px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cat-pill-tag {
            display: inline-block;
            font-size: 11.5px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: var(--radius-pill);
            background: #ecf3ef;
            color: var(--forest-800);
            border: 1px solid #d0dfd6;
            white-space: nowrap;
        }

        .price-text-val {
            font-family: 'Outfit', sans-serif;
            font-size: 14.5px;
            font-weight: 700;
            color: var(--forest-900);
            white-space: nowrap;
        }

        .price-sub-label {
            font-size: 10px;
            color: var(--text-muted);
            display: block;
            font-weight: normal;
        }

        /* STOCK STATUS ROW */
        .stock-tag-row {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 12.5px;
            white-space: nowrap;
        }

        .stock-dot-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .stock-dot-ready {
            background: var(--emerald-500);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .stock-dot-low {
            background: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .stock-dot-out {
            background: #ef4444;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        /* ACTIONS CELL */
        .table-actions-cell {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .btn-action-edit {
            background: #ffffff;
            color: var(--forest-800);
            border: 1px solid #c5d8cd;
            padding: 5px 10px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all var(--transition-fast);
        }

        .btn-action-edit:hover {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
        }

        .btn-action-delete {
            background: #ffffff;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 5px 10px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .btn-action-delete:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
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
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 520px;
            box-shadow: var(--shadow-modal);
            overflow: hidden;
            border: 1px solid var(--border-color);
            animation: modalFadeIn 0.2s ease;
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
            background: var(--forest-950);
            color: #ffffff;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .modal-header-bar h3 {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
        }

        .btn-modal-close-icon {
            background: transparent;
            border: none;
            color: #a4b5ab;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .btn-modal-close-icon:hover {
            color: #ffffff;
        }

        .modal-body-scroll {
            padding: 18px 20px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
            max-height: calc(90vh - 130px);
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

        .form-grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-field-group {
            margin-bottom: 12px;
        }

        .form-field-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .form-field-group input,
        .form-field-group select,
        .form-field-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13px;
            color: var(--text-main);
            background: #ffffff;
            transition: border-color var(--transition-fast);
        }

        .form-field-group input:focus,
        .form-field-group select:focus,
        .form-field-group textarea:focus {
            outline: none;
            border-color: var(--forest-800);
            box-shadow: 0 0 0 2px rgba(14, 86, 56, 0.1);
        }

        .modal-footer-bar {
            padding: 12px 20px;
            background: var(--bg-canvas);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-modal-cancel-btn {
            background: #ffffff;
            color: var(--text-body);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-modal-confirm-btn {
            background: var(--forest-900);
            color: var(--gold-500);
            border: none;
            padding: 8px 20px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        .btn-modal-confirm-btn:hover {
            background: var(--forest-800);
            color: #ffffff;
        }

        /* DRAG & DROP PHOTO UPLOAD COMPONENT */
        .dropzone-box {
            position: relative;
            border: 2px dashed #b7d5c3;
            background: #f9fbf9;
            border-radius: var(--radius-lg);
            padding: 20px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 125px;
            overflow: hidden;
        }

        .dropzone-box:hover {
            border-color: var(--forest-700);
            background: #f2f8f4;
            box-shadow: 0 4px 12px rgba(7, 53, 34, 0.06);
        }

        .dropzone-box.is-dragover {
            border-color: var(--emerald-500);
            border-style: solid;
            background: var(--emerald-50);
            transform: scale(1.01);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
        }

        @keyframes pastePulse {
            0% {
                border-color: var(--emerald-500);
                background: #ebfbf2;
                box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.25);
                transform: scale(1.01);
            }
            50% {
                border-color: var(--emerald-600);
                background: #e1f7e9;
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.35);
            }
            100% {
                border-color: #b7d5c3;
                background: #f9fbf9;
                box-shadow: none;
                transform: scale(1);
            }
        }

        .dropzone-box.paste-highlight {
            animation: pastePulse 0.9s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .dropzone-input {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 5;
        }

        .dropzone-prompt {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            pointer-events: none;
            width: 100%;
        }

        .dropzone-icon-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #eaf4ee;
            color: var(--forest-800);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .dropzone-box:hover .dropzone-icon-circle,
        .dropzone-box.is-dragover .dropzone-icon-circle {
            transform: translateY(-2px);
            background: var(--emerald-50);
            color: var(--emerald-600);
        }

        .dropzone-main-text {
            font-size: 13px;
            color: var(--text-body);
            margin: 0;
        }

        .dropzone-browse-btn {
            color: var(--forest-800);
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .dropzone-sub-text {
            font-size: 11.5px;
            color: var(--text-subtle);
            margin: 0;
        }

        /* Dropzone Preview Card */
        .dropzone-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--emerald-border);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            position: relative;
            z-index: 10;
            text-align: left;
            box-shadow: var(--shadow-subtle);
            animation: fadeInScale 0.2s ease;
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }

        .dropzone-preview-thumb {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 1px solid var(--border-color);
            background: #f1f5f3;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropzone-preview-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dropzone-preview-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .dropzone-file-name {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropzone-file-size {
            font-size: 11px;
            color: var(--text-muted);
        }

        .dropzone-btn-remove {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
            padding: 3px 8px;
            border-radius: var(--radius-sm);
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 4px;
            transition: all var(--transition-fast);
        }

        .dropzone-btn-remove:hover {
            background: #be123c;
            color: #ffffff;
            border-color: #be123c;
        }

        /* Current Photo Card in Edit Modal */
        .current-photo-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f7faf8;
            border: 1px solid #dbe6df;
            border-radius: var(--radius-md);
            padding: 10px 12px;
            margin-bottom: 10px;
        }

        .current-photo-thumb {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 1px solid #c8d9cf;
            background: #eef3f0;
            flex-shrink: 0;
        }

        .current-photo-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .current-photo-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .current-photo-badge {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--forest-800);
            background: #e1ede5;
            padding: 1px 7px;
            border-radius: var(--radius-pill);
            align-self: flex-start;
        }

        .current-photo-filename {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-body);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .current-photo-hint {
            font-size: 11px;
            color: var(--text-muted);
        }

        .new-photo-badge {
            font-size: 10px;
            font-weight: 700;
            color: var(--emerald-600);
            background: var(--emerald-50);
            border: 1px solid var(--emerald-border);
            padding: 1px 6px;
            border-radius: var(--radius-pill);
            align-self: flex-start;
        }

        /* Product Type Badges & Radio Cards */
        .badge-type-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            letter-spacing: 0.2px;
        }
        .badge-type-rental {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .badge-type-sale {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .badge-type-both {
            background: #fefce8;
            color: #854d0e;
            border: 1px solid #fef08a;
        }

        .type-radio-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 4px;
        }
        .type-radio-card {
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all var(--transition-fast);
            background: #ffffff;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-body);
        }
        .type-radio-card:hover {
            border-color: var(--forest-700);
            background: #f7faf8;
        }
        .type-radio-card.selected {
            border-color: var(--forest-800);
            background: #ecfdf5;
            color: var(--forest-950);
        }

        /* RESPONSIVE BREAKPOINTS */
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
            .page-header-flex {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-add-gear-cta {
                justify-content: center;
            }
            .form-grid-2col {
                grid-template-columns: 1fr;
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

        <!-- Page Header & Action Bar -->
        <div class="page-header-flex">
            <div class="page-header-title-box">
                <h1>
                    <span><?php echo $page_heading; ?></span>
                    <?php if ($is_category_view): ?>
                        <span class="header-count-tag"><?php echo $jumlah_produk; ?> perlengkapan</span>
                    <?php endif; ?>
                </h1>
                <p><?php echo $page_subtitle; ?></p>
            </div>

            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <!-- Saldo Kas Toko Widget (Uang dari Penjualan & Sewa) -->
                <div style="background: linear-gradient(135deg, #14532d 0%, #064e3b 100%); color: #ffffff; padding: 7px 15px; border-radius: var(--radius-lg); border: 1px solid rgba(255,255,255,0.18); display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(4,31,20,0.12);">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: rgba(255,255,255,0.14); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" style="color: #4ade80;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #86efac;">Saldo Kas Toko (Penjualan)</div>
                        <div style="font-size: 16px; font-weight: 800; font-family: 'Outfit', sans-serif; color: #ffffff;">
                            Rp <?php echo number_format($store_cash, 0, ',', '.'); ?>
                        </div>
                    </div>
                </div>

                <!-- Total Semua Stok & Nilai Stok Widget -->
                <a href="stok.php" style="text-decoration: none; background: linear-gradient(135deg, #073522 0%, #0e5638 100%); color: #ffffff; padding: 7px 15px; border-radius: var(--radius-lg); border: 1px solid rgba(255,255,255,0.15); display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(4,31,20,0.12);">
                    <div style="width: 36px; height: 36px; border-radius: var(--radius-md); background: rgba(255,255,255,0.12); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" style="color: #f5b800;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #a7f3d0;">Total Nilai Semua Produk</div>
                        <div style="font-size: 16px; font-weight: 800; font-family: 'Outfit', sans-serif; color: #ffffff;">
                            Rp <?php echo number_format($grand_total_value, 0, ',', '.'); ?>
                            <span style="font-size: 11px; font-weight: 600; color: #d1fae5; margin-left: 4px;">(<?php echo number_format($grand_total_stock, 0, ',', '.'); ?> Unit)</span>
                        </div>
                    </div>
                    <span style="background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.25); color: #ffffff; border-radius: var(--radius-sm); padding: 4px 8px; font-size: 11px; font-weight: 700; margin-left: 4px;">
                        Lihat Stok ↗
                    </span>
                </a>

                <button type="button" class="btn-add-gear-cta" onclick="openModalTambah()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Tambah Perlengkapan</span>
                </button>
            </div>
        </div>

        <!-- Toast Notification Feedback -->
        <?php if (isset($_GET['sukses'])): ?>
            <div class="toast-notification toast-success" id="alertToast">
                <div class="toast-content">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span><?php echo htmlspecialchars($_GET['sukses']); ?></span>
                </div>
                <button type="button" class="toast-close-btn" onclick="document.getElementById('alertToast').remove()" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['pesan'])): ?>
            <div class="toast-notification toast-error" id="alertToast">
                <div class="toast-content">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo htmlspecialchars($_GET['pesan']); ?></span>
                </div>
                <button type="button" class="toast-close-btn" onclick="document.getElementById('alertToast').remove()" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        <?php endif; ?>        <!-- Toolbar & Filter Controls -->
        <section class="filter-controls-card">
            <!-- Live Search Bar & Type Quick Filter -->
            <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; width: 100%;">
                <div class="search-row-box" style="display: flex; align-items: center; width: 100%; max-width: 420px;">
                    <div class="search-input-wrap" style="flex: 1;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" id="adminInventorySearch" placeholder="Live search perlengkapan (nama, kategori, tipe, harga)..." value="<?php echo htmlspecialchars($search); ?>" oninput="liveFilterInventory(this.value)" autocomplete="off">
                    </div>
                </div>

                <!-- Type Selector Pills -->
                <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                    <span style="font-size: 11.5px; font-weight: 700; color: var(--text-muted); margin-right: 2px;">Tipe:</span>
                    <a href="perlengkapan.php<?php echo !empty($filter_kategori_param) ? '?kategori='.urlencode($filter_kategori_param) : ''; ?>" class="cat-tab-link <?php echo empty($filter_tipe) ? 'active' : ''; ?>" style="padding: 4px 10px; font-size: 11.5px;">
                        <span>Semua Tipe</span>
                    </a>
                    <a href="perlengkapan.php?tipe=rental<?php echo !empty($filter_kategori_param) ? '&kategori='.urlencode($filter_kategori_param) : ''; ?>" class="cat-tab-link <?php echo ($filter_tipe === 'rental') ? 'active' : ''; ?>" style="padding: 4px 10px; font-size: 11.5px;">
                        <span>Rental Saja</span>
                        <span class="pill-cnt"><?php echo $total_rental_items; ?></span>
                    </a>
                    <a href="perlengkapan.php?tipe=sale<?php echo !empty($filter_kategori_param) ? '&kategori='.urlencode($filter_kategori_param) : ''; ?>" class="cat-tab-link <?php echo ($filter_tipe === 'sale') ? 'active' : ''; ?>" style="padding: 4px 10px; font-size: 11.5px;">
                        <span>Dijual (Toko)</span>
                        <span class="pill-cnt"><?php echo $total_sale_items; ?></span>
                    </a>
                </div>
            </div>

            <!-- Category Pills Switcher -->
            <div class="category-pill-tabs" style="margin-top: 10px;">
                <a href="perlengkapan.php<?php echo !empty($filter_tipe) ? '?tipe='.urlencode($filter_tipe) : ''; ?>" class="cat-tab-link <?php echo empty($filter_kategori_param) ? 'active' : ''; ?>">
                    <span>Semua Kategori</span>
                    <span class="pill-cnt"><?php echo $total_all_items; ?></span>
                </a>

                <?php foreach ($list_kategori as $ck): 
                    $c_name = $ck['name'];
                    $c_slug = strtolower(str_replace([' & ', ' '], ['-', '-'], $c_name));
                    if ($c_name === 'Tenda & Shelter') $c_slug = 'tenda';
                    elseif ($c_name === 'Carrier & Tas') $c_slug = 'carrier';
                    elseif ($c_name === 'Sleeping Gear') $c_slug = 'sleeping-gear';
                    elseif ($c_name === 'Cooking Set') $c_slug = 'cooking';
                    elseif ($c_name === 'Penerangan & Elektronik') $c_slug = 'elektronik';
                    elseif ($c_name === 'Footwear & Apparel') $c_slug = 'footwear';

                    $is_pill_active = ($selected_category_db === $c_name);
                    $c_cnt = $cat_stats[$c_name] ?? 0;
                    $url_param = 'kategori=' . urlencode($c_slug);
                    if (!empty($filter_tipe)) {
                        $url_param .= '&tipe=' . urlencode($filter_tipe);
                    }
                ?>
                    <a href="perlengkapan.php?<?php echo $url_param; ?>" class="cat-tab-link <?php echo $is_pill_active ? 'active' : ''; ?>">
                        <span><?php echo htmlspecialchars($c_name); ?></span>
                        <span class="pill-cnt"><?php echo $c_cnt; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Inventory Table Card -->
        <section class="inventory-table-card">
            <div class="table-card-head">
                <h3>
                    <?php if ($is_category_view): ?>
                        Daftar Perlengkapan — <?php echo htmlspecialchars($selected_category_db); ?>
                    <?php else: ?>
                        Seluruh Inventaris Rental & Toko DANATRAIL
                    <?php endif; ?>
                </h3>
                <span class="table-count-badge"><?php echo $jumlah_produk; ?> Perlengkapan</span>
            </div>

            <div class="table-responsive-box">
                <table class="inventory-table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th style="width: 54px;">Foto</th>
                            <th>Nama Perlengkapan</th>
                            <th>Kategori & Tipe</th>
                            <th>Harga / Tarif</th>
                            <th>Stok Unit</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data_produk && mysqli_num_rows($data_produk) > 0): ?>
                            <?php while ($p = mysqli_fetch_assoc($data_produk)): 
                                $ptype      = $p['product_type'] ?? 'rental';
                                $priceRent  = (float)($p['price'] ?? 0);
                                $priceSale  = (float)($p['price_sale'] ?? 0);
                                $stockRent  = (int)($p['stock'] ?? 0);
                                $stockSale  = (int)($p['stock_sale'] ?? 0);
                            ?>
                                <tr class="inventory-row" data-search="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . $p['category'] . ' ' . $ptype . ' ' . ($p['description'] ?? '') . ' ' . $priceRent . ' ' . $priceSale . ' ' . $stockRent . ' ' . $stockSale)); ?>">
                                    <td>
                                        <?php if (!empty($p['image']) && file_exists('../uploads/products/' . $p['image'])): ?>
                                            <img src="../uploads/products/<?php echo htmlspecialchars($p['image']); ?>" alt="Foto" class="product-thumb-media">
                                        <?php else: ?>
                                            <div class="product-thumb-fallback">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="product-info-cell">
                                            <div class="product-name-txt"><?php echo htmlspecialchars($p['name']); ?></div>
                                            <div class="product-desc-txt"><?php echo htmlspecialchars($p['description'] ?? '-'); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                            <span class="cat-pill-tag"><?php echo htmlspecialchars($p['category']); ?></span>
                                            <?php if ($ptype === 'rental'): ?>
                                                <span class="badge-type-pill badge-type-rental">Rental</span>
                                            <?php elseif ($ptype === 'sale'): ?>
                                                <span class="badge-type-pill badge-type-sale">Dijual</span>
                                            <?php elseif ($ptype === 'both'): ?>
                                                <span class="badge-type-pill badge-type-both">Rental & Jual</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="price-text-val">
                                            <?php 
                                            $disc = floatval($p['discount_percent'] ?? 0);
                                            $discountedRent = get_discounted_price($priceRent, $disc);
                                            $discountedSale = get_discounted_price($priceSale, $disc);
                                            if ($disc > 0): ?>
                                                <div style="margin-bottom: 3px;">
                                                    <span style="display: inline-block; background: #fee2e2; color: #b91c1c; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 4px; border: 1px solid #fca5a5;">
                                                        Diskon <?php echo rtrim(rtrim(number_format($disc, 2), '0'), '.'); ?>%
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($ptype === 'rental'): ?>
                                                <?php if ($disc > 0): ?>
                                                    <div style="font-size: 11.5px; text-decoration: line-through; color: #94a3b8;">Rp <?php echo number_format($priceRent, 0, ',', '.'); ?></div>
                                                    Rp <?php echo number_format($discountedRent, 0, ',', '.'); ?>
                                                <?php else: ?>
                                                    Rp <?php echo number_format($priceRent, 0, ',', '.'); ?>
                                                <?php endif; ?>
                                                <span class="price-sub-label">per 24 jam</span>
                                            <?php elseif ($ptype === 'sale'): ?>
                                                <?php if ($disc > 0): ?>
                                                    <div style="font-size: 11.5px; text-decoration: line-through; color: #94a3b8;">Rp <?php echo number_format($priceSale, 0, ',', '.'); ?></div>
                                                    Rp <?php echo number_format($discountedSale, 0, ',', '.'); ?>
                                                <?php else: ?>
                                                    Rp <?php echo number_format($priceSale, 0, ',', '.'); ?>
                                                <?php endif; ?>
                                                <span class="price-sub-label" style="color: #1e40af;">Harga Beli</span>
                                            <?php elseif ($ptype === 'both'): ?>
                                                <div style="font-size: 12.5px; color: var(--forest-900); font-weight: 700;">
                                                    Sewa: <?php if ($disc > 0): ?><span style="text-decoration: line-through; font-weight: 400; color: #94a3b8; font-size: 11px;">Rp <?php echo number_format($priceRent, 0, ',', '.'); ?></span> <?php endif; ?>Rp <?php echo number_format($discountedRent, 0, ',', '.'); ?>/hari
                                                </div>
                                                <div style="font-size: 12px; color: #1e40af; font-weight: 700; margin-top: 1px;">
                                                    Beli: <?php if ($disc > 0): ?><span style="text-decoration: line-through; font-weight: 400; color: #94a3b8; font-size: 11px;">Rp <?php echo number_format($priceSale, 0, ',', '.'); ?></span> <?php endif; ?>Rp <?php echo number_format($discountedSale, 0, ',', '.'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="stock-tag-row">
                                            <?php if ($ptype === 'rental'): ?>
                                                <span class="stock-dot-indicator <?php echo ($stockRent > 0) ? 'stock-dot-ready' : 'stock-dot-out'; ?>"></span>
                                                <span><?php echo $stockRent; ?> unit sewa</span>
                                            <?php elseif ($ptype === 'sale'): ?>
                                                <span class="stock-dot-indicator <?php echo ($stockSale > 0) ? 'stock-dot-ready' : 'stock-dot-out'; ?>"></span>
                                                <span style="color: #1e40af; font-weight: 600;"><?php echo $stockSale; ?> unit toko</span>
                                            <?php elseif ($ptype === 'both'): ?>
                                                <div>
                                                    <div style="font-size: 11.5px;"><?php echo $stockRent; ?> sewa</div>
                                                    <div style="font-size: 11.5px; color: #1e40af; font-weight: 600;"><?php echo $stockSale; ?> jual</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-actions-cell">
                                            <button type="button" class="btn-action-edit" onclick="openModalEdit(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                <span>Edit</span>
                                            </button>
                                            
                                            <a href="produk_aksi.php?hapus=<?php echo $p['id']; ?>&redirect_to=<?php echo urlencode($current_request_uri); ?>" class="btn-action-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus perlengkapan: ' + <?php echo htmlspecialchars(json_encode($p['name']), ENT_QUOTES, 'UTF-8'); ?> + '?');">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                <span>Hapus</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 8px auto; color: var(--text-subtle);">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                    <h4 style="font-size: 15px; font-weight: 700; color: var(--text-main);">Tidak ada perlengkapan ditemukan</h4>
                                    <p style="font-size: 12.5px; margin-top: 2px;">
                                        <?php if (!empty($search)): ?>
                                            Tidak ada hasil untuk kata kunci "<?php echo htmlspecialchars($search); ?>". Silakan coba kata kunci lain atau reset filter.
                                        <?php else: ?>
                                            Belum ada data perlengkapan pada kategori ini. Silakan klik tombol "+ Tambah Perlengkapan".
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- ==========================================================================
         MODAL 1: TAMBAH PERLENGKAPAN BARU
         ========================================================================== -->
    <div class="modal-overlay" id="modalTambahOverlay" role="dialog" aria-modal="true">
        <div class="modal-dialog">
            <div class="modal-header-bar">
                <h3>Tambah Perlengkapan Outdoor</h3>
                <button type="button" class="btn-modal-close-icon" onclick="closeModalTambah()" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form action="produk_aksi.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($current_request_uri); ?>">

                <div class="modal-body-scroll">
                    <div class="form-field-group">
                        <label for="add_name">Nama Perlengkapan *</label>
                        <input type="text" name="name" id="add_name" placeholder="Contoh: Tenda Dome 4P Double Layer" required>
                    </div>

                    <div class="form-field-group">
                        <label for="add_category">Kategori *</label>
                        <input list="cat_list_options" name="category" id="add_category" placeholder="Pilih / ketik kategori" value="<?php echo htmlspecialchars($selected_category_db); ?>" required>
                        <datalist id="cat_list_options">
                            <?php foreach ($list_kategori as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['name']); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <!-- Tipe Produk Selection -->
                    <div class="form-field-group">
                        <label>Tipe Produk / Model Transaksi *</label>
                        <div class="type-radio-group">
                            <label class="type-radio-card" id="card_add_type_rental">
                                <input type="radio" name="product_type" value="rental" checked onchange="handleProductTypeChange('add', this.value)">
                                <span>Rental</span>
                            </label>
                            <label class="type-radio-card" id="card_add_type_sale">
                                <input type="radio" name="product_type" value="sale" onchange="handleProductTypeChange('add', this.value)">
                                <span>Dijual</span>
                            </label>
                            <label class="type-radio-card" id="card_add_type_both">
                                <input type="radio" name="product_type" value="both" onchange="handleProductTypeChange('add', this.value)">
                                <span>Rental & Jual</span>
                            </label>
                        </div>
                    </div>

                    <!-- Rental Fields -->
                    <div id="add_rental_fields_container">
                        <div class="form-grid-2col">
                            <div class="form-field-group">
                                <label for="add_price">Tarif Sewa (Rp / hari) *</label>
                                <input type="number" name="price" id="add_price" placeholder="Contoh: 50000" min="0" value="50000">
                            </div>
                            <div class="form-field-group">
                                <label for="add_stock">Stok Unit Sewa *</label>
                                <input type="number" name="stock" id="add_stock" value="3" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Sale Fields -->
                    <div id="add_sale_fields_container" style="display: none;">
                        <div class="form-grid-2col">
                            <div class="form-field-group">
                                <label for="add_price_sale">Harga Jual Baru (Rp) *</label>
                                <input type="number" name="price_sale" id="add_price_sale" placeholder="Contoh: 450000" min="0" value="0">
                            </div>
                            <div class="form-field-group">
                                <label for="add_stock_sale">Stok Barang Dijual *</label>
                                <input type="number" name="stock_sale" id="add_stock_sale" value="5" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Diskon Produk Tambah -->
                    <div class="form-field-group">
                        <label for="add_discount_percent">Diskon Produk (%) <span style="font-size: 11.5px; font-weight: normal; color: #64748b;">(Opsional, 0 - 100%)</span></label>
                        <div style="position: relative; max-width: 220px;">
                            <input type="number" name="discount_percent" id="add_discount_percent" placeholder="0" min="0" max="100" step="0.5" value="0" style="padding-right: 32px;">
                            <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #64748b; font-weight: 700;">%</span>
                        </div>
                        <small style="color: #64748b; font-size: 11.5px; margin-top: 4px; display: block;">Diskon akan otomatis memotong tarif sewa dan harga beli barang ini.</small>
                    </div>

                    <div class="form-field-group">
                        <label for="add_image">Foto Perlengkapan (Opsional)</label>
                        <div class="dropzone-box" id="add_dropzone">
                            <input type="file" name="image" id="add_image" accept="image/*" class="dropzone-input">
                            
                            <!-- Prompt State -->
                            <div class="dropzone-prompt" id="add_dropzone_prompt">
                                <div class="dropzone-icon-circle">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p class="dropzone-main-text"><strong>Tarik & lepas foto ke sini</strong>, atau <span class="dropzone-browse-btn">Pilih File</span></p>
                                <p class="dropzone-sub-text">Bisa juga tempel gambar (Ctrl+V) langsung</p>
                            </div>

                            <!-- Preview State -->
                            <div class="dropzone-preview" id="add_dropzone_preview" style="display: none;">
                                <div class="dropzone-preview-thumb">
                                    <img id="add_preview_img" src="" alt="Preview Foto">
                                </div>
                                <div class="dropzone-preview-info">
                                    <span class="new-photo-badge">Foto Terpilih</span>
                                    <div class="dropzone-file-name" id="add_file_name"></div>
                                    <div class="dropzone-file-size" id="add_file_size"></div>
                                    <button type="button" class="dropzone-btn-remove" onclick="resetAddImage(event)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Hapus / Ganti
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-field-group">
                        <label for="add_description">Deskripsi & Kelengkapan</label>
                        <textarea name="description" id="add_description" rows="3" placeholder="Kapasitas, berat, bahan, kondisi, kelengkapan alat..."></textarea>
                    </div>
                </div>

                <div class="modal-footer-bar">
                    <button type="button" class="btn-modal-cancel-btn" onclick="closeModalTambah()">Batal</button>
                    <button type="submit" name="tambah_produk" class="btn-modal-confirm-btn">Simpan Perlengkapan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL 2: EDIT DATA PERLENGKAPAN
         ========================================================================== -->
    <div class="modal-overlay" id="modalEditOverlay" role="dialog" aria-modal="true">
        <div class="modal-dialog">
            <div class="modal-header-bar">
                <h3>Edit Data Perlengkapan</h3>
                <button type="button" class="btn-modal-close-icon" onclick="closeModalEdit()" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form action="produk_aksi.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" id="edit_id" value="">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($current_request_uri); ?>">

                <div class="modal-body-scroll">
                    <div class="form-field-group">
                        <label for="edit_name">Nama Perlengkapan *</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>

                    <div class="form-field-group">
                        <label for="edit_category">Kategori *</label>
                        <input list="cat_list_options_edit" name="category" id="edit_category" required>
                        <datalist id="cat_list_options_edit">
                            <?php foreach ($list_kategori as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['name']); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <!-- Tipe Produk Selection Edit -->
                    <div class="form-field-group">
                        <label>Tipe Produk / Model Transaksi *</label>
                        <div class="type-radio-group">
                            <label class="type-radio-card" id="card_edit_type_rental">
                                <input type="radio" name="product_type" id="edit_type_rental" value="rental" onchange="handleProductTypeChange('edit', this.value)">
                                <span>Rental</span>
                            </label>
                            <label class="type-radio-card" id="card_edit_type_sale">
                                <input type="radio" name="product_type" id="edit_type_sale" value="sale" onchange="handleProductTypeChange('edit', this.value)">
                                <span>Dijual</span>
                            </label>
                            <label class="type-radio-card" id="card_edit_type_both">
                                <input type="radio" name="product_type" id="edit_type_both" value="both" onchange="handleProductTypeChange('edit', this.value)">
                                <span>Rental & Jual</span>
                            </label>
                        </div>
                    </div>

                    <!-- Rental Fields Edit -->
                    <div id="edit_rental_fields_container">
                        <div class="form-grid-2col">
                            <div class="form-field-group">
                                <label for="edit_price">Tarif Sewa (Rp / hari)</label>
                                <input type="number" name="price" id="edit_price" min="0">
                            </div>
                            <div class="form-field-group">
                                <label for="edit_stock">Stok Unit Sewa</label>
                                <input type="number" name="stock" id="edit_stock" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Sale Fields Edit -->
                    <div id="edit_sale_fields_container" style="display: none;">
                        <div class="form-grid-2col">
                            <div class="form-field-group">
                                <label for="edit_price_sale">Harga Jual Baru (Rp)</label>
                                <input type="number" name="price_sale" id="edit_price_sale" min="0">
                            </div>
                            <div class="form-field-group">
                                <label for="edit_stock_sale">Stok Barang Dijual</label>
                                <input type="number" name="stock_sale" id="edit_stock_sale" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Diskon Produk Edit -->
                    <div class="form-field-group">
                        <label for="edit_discount_percent">Diskon Produk (%) <span style="font-size: 11.5px; font-weight: normal; color: #64748b;">(0 - 100%)</span></label>
                        <div style="position: relative; max-width: 220px;">
                            <input type="number" name="discount_percent" id="edit_discount_percent" placeholder="0" min="0" max="100" step="0.5" value="0" style="padding-right: 32px;">
                            <span style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #64748b; font-weight: 700;">%</span>
                        </div>
                        <small style="color: #64748b; font-size: 11.5px; margin-top: 4px; display: block;">Isi 0 jika barang ini dijual/disewakan dengan tarif normal (tanpa diskon).</small>
                    </div>

                    <div class="form-field-group">
                        <label for="edit_image">Foto Perlengkapan</label>
                        
                        <!-- Box Foto Saat Ini -->
                        <div id="edit_current_photo_wrapper" class="current-photo-card" style="display: none;">
                            <div class="current-photo-thumb">
                                <img id="edit_current_photo_img" src="" alt="Foto Saat Ini" onerror="this.src='../assets/img/placeholder-gear.svg';">
                            </div>
                            <div class="current-photo-meta">
                                <span class="current-photo-badge">Foto Saat Ini</span>
                                <span class="current-photo-filename" id="edit_current_photo_name"></span>
                                <span class="current-photo-hint">Tarik foto baru di bawah jika ingin mengganti</span>
                            </div>
                        </div>

                        <!-- Dropzone Foto Baru -->
                        <div class="dropzone-box" id="edit_dropzone">
                            <input type="file" name="image" id="edit_image" accept="image/*" class="dropzone-input">
                            
                            <!-- Prompt state -->
                            <div class="dropzone-prompt" id="edit_dropzone_prompt">
                                <div class="dropzone-icon-circle">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p class="dropzone-main-text"><strong>Tarik & lepas foto baru</strong> ke sini, atau <span class="dropzone-browse-btn">Pilih File</span></p>
                                <p class="dropzone-sub-text">Kosongkan jika ingin mempertahankan foto yang sudah ada</p>
                            </div>

                            <!-- Preview state -->
                            <div class="dropzone-preview" id="edit_dropzone_preview" style="display: none;">
                                <div class="dropzone-preview-thumb">
                                    <img id="edit_preview_img" src="" alt="Preview Foto Baru">
                                </div>
                                <div class="dropzone-preview-info">
                                    <span class="new-photo-badge">Foto Baru Dipilih</span>
                                    <div class="dropzone-file-name" id="edit_file_name"></div>
                                    <div class="dropzone-file-size" id="edit_file_size"></div>
                                    <button type="button" class="dropzone-btn-remove" onclick="resetEditImage(event)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Batalkan Foto Baru
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-field-group">
                        <label for="edit_description">Deskripsi & Kelengkapan</label>
                        <textarea name="description" id="edit_description" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer-bar">
                    <button type="button" class="btn-modal-cancel-btn" onclick="closeModalEdit()">Batal</button>
                    <button type="submit" name="edit_produk" class="btn-modal-confirm-btn">Perbarui Perlengkapan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript Interactivity -->
    <script>
        // Format ukuran file (e.g. 240 KB, 1.5 MB)
        function formatBytes(bytes, decimals = 1) {
            if (!bytes || bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        // Helper untuk normalisasi file dari clipboard agar selalu memiliki nama & ekstensi valid
        function normalizeImageFile(file) {
            if (!file) return null;
            let fileName = file.name || '';
            const mimeType = file.type || 'image/png';
            
            let ext = 'png';
            if (mimeType.includes('jpeg') || mimeType.includes('jpg')) ext = 'jpg';
            else if (mimeType.includes('webp')) ext = 'webp';
            else if (mimeType.includes('gif')) ext = 'gif';
            else if (mimeType.includes('bmp')) ext = 'bmp';

            if (!fileName || fileName === 'blob' || fileName === 'image' || !fileName.includes('.')) {
                const timeStamp = new Date().toISOString().replace(/[-:T.]/g, '').slice(0, 14);
                fileName = `pasted_gear_${timeStamp}.${ext}`;
            }

            try {
                return new File([file], fileName, { type: mimeType, lastModified: Date.now() });
            } catch (e) {
                return file;
            }
        }

        // Helper untuk ekstrak image dari clipboard data (screenshot, paste dari web, atau file dari OS)
        function extractImageFromClipboard(clipboardData) {
            if (!clipboardData) return null;

            // 1. Cek clipboardData.files (jika copy file gambar dari Explorer/Desktop)
            if (clipboardData.files && clipboardData.files.length > 0) {
                for (let i = 0; i < clipboardData.files.length; i++) {
                    const f = clipboardData.files[i];
                    if (f && f.type && f.type.startsWith('image/')) {
                        return normalizeImageFile(f);
                    }
                }
            }

            // 2. Cek clipboardData.items (screenshot Snipping Tool, PrintScreen, web copy)
            if (clipboardData.items && clipboardData.items.length > 0) {
                for (let i = 0; i < clipboardData.items.length; i++) {
                    const item = clipboardData.items[i];
                    if (item && item.type && item.type.startsWith('image/')) {
                        const blob = item.getAsFile();
                        if (blob) {
                            return normalizeImageFile(blob);
                        }
                    }
                }
            }

            return null;
        }

        const dropzoneInstances = {};

        // Setup Drag & Drop & File Setting untuk Dropzone
        function setupDropzone(config) {
            const dropzone = document.getElementById(config.zoneId);
            const input = document.getElementById(config.inputId);
            const prompt = document.getElementById(config.promptId);
            const preview = document.getElementById(config.previewId);
            const img = document.getElementById(config.imgId);
            const fileName = document.getElementById(config.nameId);
            const fileSize = document.getElementById(config.sizeId);

            if (!dropzone || !input) return;

            function handleFile(file) {
                if (!file) return;
                if (!file.type.startsWith('image/')) {
                    alert('Hanya file gambar (JPG, PNG, WEBP, GIF, BMP) yang diperbolehkan!');
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran foto terlalu besar! Maksimal 5MB.');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    fileName.textContent = file.name || 'Foto Terpilih';
                    fileSize.textContent = formatBytes(file.size);
                    prompt.style.display = 'none';
                    preview.style.display = 'flex';

                    // Efek visual pulse saat foto masuk via Ctrl+V atau drag-drop
                    dropzone.classList.remove('paste-highlight');
                    void dropzone.offsetWidth;
                    dropzone.classList.add('paste-highlight');
                };
                reader.readAsDataURL(file);
            }

            function setFile(file) {
                const normFile = normalizeImageFile(file);
                if (!normFile) return;

                try {
                    const dt = new DataTransfer();
                    dt.items.add(normFile);
                    input.files = dt.files;
                } catch (err) {
                    console.warn('DataTransfer fallback', err);
                }

                handleFile(normFile);
            }

            // Input change
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    handleFile(this.files[0]);
                }
            });

            // Drag and drop events
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('is-dragover');
                }, false);
            });

            ['dragleave', 'dragend'].forEach(eventName => {
                dropzone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('is-dragover');
                }, false);
            });

            dropzone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('is-dragover');

                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    setFile(dt.files[0]);
                }
            }, false);

            dropzoneInstances[config.zoneId] = {
                setFile,
                handleFile
            };
        }

        // Inisialisasi dropzone Tambah & Edit
        setupDropzone({
            zoneId: 'add_dropzone',
            inputId: 'add_image',
            promptId: 'add_dropzone_prompt',
            previewId: 'add_dropzone_preview',
            imgId: 'add_preview_img',
            nameId: 'add_file_name',
            sizeId: 'add_file_size'
        });

        setupDropzone({
            zoneId: 'edit_dropzone',
            inputId: 'edit_image',
            promptId: 'edit_dropzone_prompt',
            previewId: 'edit_dropzone_preview',
            imgId: 'edit_preview_img',
            nameId: 'edit_file_name',
            sizeId: 'edit_file_size'
        });

        // Reset Add Image Dropzone
        function resetAddImage(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const input = document.getElementById('add_image');
            if (input) input.value = '';
            const prompt = document.getElementById('add_dropzone_prompt');
            const preview = document.getElementById('add_dropzone_preview');
            const img = document.getElementById('add_preview_img');
            const fileName = document.getElementById('add_file_name');
            const fileSize = document.getElementById('add_file_size');
            if (prompt) prompt.style.display = 'flex';
            if (preview) preview.style.display = 'none';
            if (img) img.src = '';
            if (fileName) fileName.textContent = '';
            if (fileSize) fileSize.textContent = '';
        }

        // Reset Edit Image Dropzone
        function resetEditImage(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const input = document.getElementById('edit_image');
            if (input) input.value = '';
            const prompt = document.getElementById('edit_dropzone_prompt');
            const preview = document.getElementById('edit_dropzone_preview');
            const img = document.getElementById('edit_preview_img');
            const fileName = document.getElementById('edit_file_name');
            const fileSize = document.getElementById('edit_file_size');
            if (prompt) prompt.style.display = 'flex';
            if (preview) preview.style.display = 'none';
            if (img) img.src = '';
            if (fileName) fileName.textContent = '';
            if (fileSize) fileSize.textContent = '';
        }

        // Type Selection Handler (Rental vs Sale vs Both)
        function handleProductTypeChange(mode, type) {
            const rentalContainer = document.getElementById(mode + '_rental_fields_container');
            const saleContainer = document.getElementById(mode + '_sale_fields_container');

            // Highlight selected radio card
            ['rental', 'sale', 'both'].forEach(t => {
                const card = document.getElementById('card_' + mode + '_type_' + t);
                if (card) {
                    if (t === type) {
                        card.classList.add('selected');
                    } else {
                        card.classList.remove('selected');
                    }
                }
            });

            if (type === 'rental') {
                if (rentalContainer) rentalContainer.style.display = 'block';
                if (saleContainer) saleContainer.style.display = 'none';
            } else if (type === 'sale') {
                if (rentalContainer) rentalContainer.style.display = 'none';
                if (saleContainer) saleContainer.style.display = 'block';
            } else if (type === 'both') {
                if (rentalContainer) rentalContainer.style.display = 'block';
                if (saleContainer) saleContainer.style.display = 'block';
            }
        }

        const CURRENT_STORE_CASH = <?php echo json_encode((float)($store_cash ?? 0)); ?>;

        // Kalkulasi Modal Pengadaan Awal saat Tambah Barang Baru
        function calculateAddCostSummary() {
            const addCostEl = document.getElementById('add_cost_price');
            if (!addCostEl) return;

            const ptype = document.querySelector('input[name="product_type"]:checked')?.value || 'rental';
            const rentStock = parseInt(document.getElementById('add_stock')?.value) || 0;
            const saleStock = parseInt(document.getElementById('add_stock_sale')?.value) || 0;

            let totalUnits = 0;
            if (ptype === 'rental') totalUnits = rentStock;
            else if (ptype === 'sale') totalUnits = saleStock;
            else if (ptype === 'both') totalUnits = rentStock + saleStock;

            const costPerUnit = parseFloat(addCostEl.value) || 0;
            const totalDeduction = totalUnits * costPerUnit;
            const remaining = CURRENT_STORE_CASH - totalDeduction;

            const unitsEl = document.getElementById('add_summary_units');
            const dedEl = document.getElementById('add_summary_deduction');
            const balEl = document.getElementById('add_summary_balance');
            const warnEl = document.getElementById('add_warning_insufficient');

            if (unitsEl) unitsEl.textContent = totalUnits + ' unit';
            if (dedEl) dedEl.textContent = '- Rp ' + totalDeduction.toLocaleString('id-ID');
            if (balEl) {
                balEl.textContent = 'Rp ' + remaining.toLocaleString('id-ID');
                balEl.style.color = (remaining < 0) ? '#dc2626' : '#14532d';
            }
            if (warnEl) {
                warnEl.style.display = (totalDeduction > CURRENT_STORE_CASH) ? 'block' : 'none';
            }
        }

        // Modal Tambah
        function openModalTambah() {
            resetAddImage();
            const rentalRadio = document.querySelector('input[name="product_type"][value="rental"]');
            if (rentalRadio) rentalRadio.checked = true;
            handleProductTypeChange('add', 'rental');
            calculateAddCostSummary();
            document.getElementById('modalTambahOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModalTambah() {
            resetAddImage();
            document.getElementById('modalTambahOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Modal Edit
        function openModalEdit(product) {
            resetEditImage();
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_category').value = product.category;
            
            const ptype = product.product_type || 'rental';
            const radio = document.querySelector('input[name="product_type"][value="' + ptype + '"]');
            if (radio) radio.checked = true;
            handleProductTypeChange('edit', ptype);

            document.getElementById('edit_price').value = product.price || 0;
            document.getElementById('edit_stock').value = product.stock || 0;
            document.getElementById('edit_price_sale').value = product.price_sale || 0;
            document.getElementById('edit_stock_sale').value = product.stock_sale || 0;
            const elDiscount = document.getElementById('edit_discount_percent');
            if (elDiscount) elDiscount.value = parseFloat(product.discount_percent) || 0;
            const elCostPrice = document.getElementById('edit_cost_price');
            if (elCostPrice) elCostPrice.value = product.cost_price || 0;
            document.getElementById('edit_description').value = product.description || '';

            // Handle current photo preview
            const currentWrapper = document.getElementById('edit_current_photo_wrapper');
            const currentImg = document.getElementById('edit_current_photo_img');
            const currentName = document.getElementById('edit_current_photo_name');

            if (product.image && product.image.trim() !== '') {
                currentImg.src = '../uploads/products/' + product.image;
                currentName.textContent = product.image;
                currentWrapper.style.display = 'flex';
            } else {
                currentImg.src = '';
                currentName.textContent = '';
                currentWrapper.style.display = 'none';
            }

            document.getElementById('modalEditOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModalEdit() {
            resetEditImage();
            document.getElementById('modalEditOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Global Paste Listener (Ctrl+V) untuk modal Tambah & Edit
        window.addEventListener('paste', function(e) {
            const clipboardData = e.clipboardData || window.clipboardData || (e.originalEvent && e.originalEvent.clipboardData);
            if (!clipboardData) return;

            const imageFile = extractImageFromClipboard(clipboardData);
            if (!imageFile) return;

            const addModal = document.getElementById('modalTambahOverlay');
            const editModal = document.getElementById('modalEditOverlay');

            const isAddActive = addModal && addModal.classList.contains('active');
            const isEditActive = editModal && editModal.classList.contains('active');

            if (isAddActive) {
                e.preventDefault();
                if (dropzoneInstances['add_dropzone']) {
                    dropzoneInstances['add_dropzone'].setFile(imageFile);
                }
            } else if (isEditActive) {
                e.preventDefault();
                if (dropzoneInstances['edit_dropzone']) {
                    dropzoneInstances['edit_dropzone'].setFile(imageFile);
                }
            } else {
                const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
                if (activeTag !== 'input' && activeTag !== 'textarea') {
                    e.preventDefault();
                    openModalTambah();
                    setTimeout(() => {
                        if (dropzoneInstances['add_dropzone']) {
                            dropzoneInstances['add_dropzone'].setFile(imageFile);
                        }
                    }, 50);
                }
            }
        });

        // Live Search Filter for Admin Inventory Table
        function liveFilterInventory(query) {
            const q = query.trim().toLowerCase();
            const rows = document.querySelectorAll('#inventoryTable tbody tr.inventory-row');
            let visible = 0;
            rows.forEach(row => {
                const text = row.getAttribute('data-search') || '';
                if (q === '' || text.includes(q)) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });
            const badge = document.querySelector('.table-count-badge');
            if (badge) badge.innerText = visible + ' Perlengkapan';
        }

        // Close on Backdrop Click & ESC
        window.addEventListener('click', function(event) {
            const addModal = document.getElementById('modalTambahOverlay');
            const editModal = document.getElementById('modalEditOverlay');
            if (event.target === addModal) closeModalTambah();
            if (event.target === editModal) closeModalEdit();
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModalTambah();
                closeModalEdit();
                const sidebar = document.getElementById('adminSidebar');
                const backdrop = document.getElementById('adminSidebarBackdrop');
                if (sidebar && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    backdrop.classList.remove('active');
                }
            }
        });
    </script>
</body>

</html>
