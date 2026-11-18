<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek autentikasi user
if (!isset($_SESSION['role'])) {
    header("location:../login.php?pesan=" . urlencode("Silakan login terlebih dahulu!"));
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
global $conn;
/** @var mysqli $conn */

// Ambil Data Profil User yang Sedang Login
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id && isset($_SESSION['nama']) && $conn) {
    $nama_s = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $u_res = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama_s' LIMIT 1");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $user_id = mysqli_fetch_assoc($u_res)['id'];
        $_SESSION['user_id'] = $user_id;
    }
}
$user_profile_data = null;
if ($user_id && $conn) {
    $u_chk = mysqli_query($conn, "SELECT id, name, email, phone, address FROM users WHERE id='$user_id'");
    if ($u_chk && mysqli_num_rows($u_chk) > 0) {
        $user_profile_data = mysqli_fetch_assoc($u_chk);
    }
}
$is_profile_complete = !empty($user_profile_data['phone']) && !empty($user_profile_data['address']);

// Filter Kategori, Pencarian, Tipe Tab, Harga, Stok, & Urutan
$tab_filter     = isset($_GET['tab']) ? strtolower(trim($_GET['tab'])) : '';
$cat_filter     = isset($_GET['kategori']) ? mysqli_real_escape_string($conn, trim($_GET['kategori'])) : '';
$search_query   = isset($_GET['q']) ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';
$sort_by        = isset($_GET['sort']) ? mysqli_real_escape_string($conn, trim($_GET['sort'])) : 'newest';
$min_price      = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price      = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$stock_status   = isset($_GET['stock_status']) ? trim($_GET['stock_status']) : '';
$rental_date    = isset($_GET['rental_date']) ? trim($_GET['rental_date']) : '';

$sql = "SELECT * FROM products WHERE 1=1";

if ($tab_filter === 'rental') {
    $sql .= " AND (product_type = 'rental' OR product_type = 'both')";
} elseif ($tab_filter === 'toko' || $tab_filter === 'sale') {
    $sql .= " AND (product_type = 'sale' OR product_type = 'both')";
}

if (!empty($cat_filter) && $cat_filter !== 'Semua') {
    // Normalisasi pencocokan kategori fleksibel
    $safe_cat = mysqli_real_escape_string($conn, $cat_filter);
    $sql .= " AND (category = '$safe_cat' OR category LIKE '%$safe_cat%')";
}

if (!empty($search_query)) {
    $sql .= " AND (name LIKE '%$search_query%' OR description LIKE '%$search_query%' OR category LIKE '%$search_query%')";
}

if ($min_price > 0) {
    if ($tab_filter === 'toko' || $tab_filter === 'sale') {
        $sql .= " AND price_sale >= $min_price";
    } else {
        $sql .= " AND price >= $min_price";
    }
}

if ($max_price > 0) {
    if ($tab_filter === 'toko' || $tab_filter === 'sale') {
        $sql .= " AND price_sale <= $max_price";
    } else {
        $sql .= " AND price <= $max_price";
    }
}

if ($stock_status === 'ready') {
    $sql .= " AND (stock > 3 OR stock_sale > 3)";
} elseif ($stock_status === 'low') {
    $sql .= " AND ((stock > 0 AND stock <= 3) OR (stock_sale > 0 AND stock_sale <= 3))";
} elseif ($stock_status === 'available') {
    $sql .= " AND (stock > 0 OR stock_sale > 0)";
}

// Sorting logic
switch ($sort_by) {
    case 'price_low':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY price DESC";
        break;
    case 'stock_high':
        $sql .= " ORDER BY stock DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY name ASC";
        break;
    case 'newest':
    case 'recommend':
    default:
        $sql .= " ORDER BY id DESC";
        break;
}

$products = mysqli_query($conn, $sql);
$jumlah_produk_tampil = $products ? mysqli_num_rows($products) : 0;

// Ambil data kategori resmi
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
$cat_list = [];
if ($categories) {
    while ($c = mysqli_fetch_assoc($categories)) {
        $cat_list[] = $c;
    }
}

// Hitung total produk dalam katalog
$stats_query = mysqli_query($conn, "SELECT COUNT(*) as total_items, SUM(stock) as total_stock FROM products");
$stats_data  = $stats_query ? mysqli_fetch_assoc($stats_query) : [];
$total_products_count = (int)($stats_data['total_items'] ?? 0);

// Visual category icon mappings (Sesuai 6 Kategori Database Resmi DANATRAIL)
$visual_categories = [
    [
        'name' => 'Tenda & Shelter',
        'db_match' => 'Tenda & Shelter',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21l7-14 7 14M9 21l3-6 3 6M12 7V3" /></svg>'
    ],
    [
        'name' => 'Carrier & Tas',
        'db_match' => 'Carrier & Tas',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>'
    ],
    [
        'name' => 'Sleeping Gear',
        'db_match' => 'Sleeping Gear',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" /></svg>'
    ],
    [
        'name' => 'Cooking Set',
        'db_match' => 'Cooking Set',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a7 7 0 01-14 0m14 0V9a2 2 0 00-2-2H7a2 2 0 00-2 2v2m7-8v2" /></svg>'
    ],
    [
        'name' => 'Penerangan & Elektronik',
        'db_match' => 'Penerangan & Elektronik',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>'
    ],
    [
        'name' => 'Footwear & Apparel',
        'db_match' => 'Footwear & Apparel',
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 4v12l-4-2-4 2V4M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>'
    ]
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Perlengkapan Outdoor — DANATRAIL</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ==========================================================================
           DANATRAIL WARM OUTDOOR DESIGN SYSTEM (MATCHING VERCEL SPEC)
           ========================================================================== */
        :root {
            --forest-950: #041f14;
            --forest-900: #0a2618;
            --forest-850: #103824;
            --forest-800: #164e32;
            --forest-700: #1e6b45;
            
            --gold-500: #f5b800;
            --gold-600: #d99b00;
            --earth-brown: #8c6d48;
            
            --emerald-600: #059669;
            --emerald-500: #10b981;
            
            --bg-canvas: #fbf9f4;
            --surface-card: #ffffff;
            --surface-subtle: #f3efe6;
            --border-color: #e8e3d8;
            --border-light: #f0ebe0;
            
            --text-main: #141f19;
            --text-body: #3b4c42;
            --text-muted: #6e7e74;
            --text-subtle: #98a79e;

            --radius-xl: 18px;
            --radius-lg: 14px;
            --radius-md: 10px;
            --radius-sm: 6px;
            --radius-pill: 9999px;

            --shadow-subtle: 0 1px 3px rgba(10, 38, 24, 0.04);
            --shadow-card: 0 4px 16px rgba(10, 38, 24, 0.06);
            --shadow-modal: 0 20px 45px rgba(4, 31, 20, 0.35);

            --transition-fast: 0.15s ease;
            --transition-normal: 0.2s ease;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            display: flex;
            height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        /* SIDEBAR (260px COMPACT) */
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
            font-size: 10px;
            font-weight: 600;
            color: #86efac;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .nav-group-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7b8f84;
            font-weight: 800;
            margin: 8px 0 4px 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: var(--radius-md);
            color: #b7c7be;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: var(--transition-fast);
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .nav-icon svg {
            width: 100%;
            height: 100%;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
        }

        .nav-item.active {
            background: rgba(245, 184, 0, 0.12);
            color: #ffffff;
            font-weight: 700;
            border-left: 3px solid var(--gold-500);
        }

        .nav-item.active .nav-icon {
            color: var(--gold-500);
        }

        .sidebar-notice-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-md);
            padding: 10px 12px;
            margin: 12px 0;
        }

        .notice-header {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--gold-500);
            margin-bottom: 3px;
        }

        .sidebar-notice-box p {
            font-size: 10.5px;
            color: #92a499;
            line-height: 1.4;
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
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 28px 36px 120px 36px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        .main-wrapper::-webkit-scrollbar {
            width: 7px;
        }

        .main-wrapper::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.02);
        }

        .main-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .main-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
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

        /* ==========================================================================
           PRO-MAX UPGRADE: HEADER & HERO SECTION (DANATRAIL SPEC)
           ========================================================================== */
        .catalog-hero-card {
            position: relative;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 244, 235, 0.85) 100%);
            border: 1px solid rgba(22, 78, 50, 0.12);
            border-radius: 24px;
            padding: 28px 32px;
            margin-bottom: 26px;
            box-shadow: 0 10px 30px -10px rgba(10, 38, 24, 0.05), 0 1px 3px rgba(10, 38, 24, 0.03);
            overflow: hidden;
            backdrop-filter: blur(8px);
        }

        .catalog-hero-card::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(245, 184, 0, 0.15) 0%, rgba(16, 185, 129, 0.08) 50%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        .hero-inner-content {
            position: relative;
            z-index: 1;
        }

        .page-breadcrumb {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid var(--border-color);
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            margin-bottom: 14px;
        }

        .page-breadcrumb a {
            color: var(--forest-800);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: color var(--transition-fast);
        }

        .page-breadcrumb a:hover {
            color: var(--forest-950);
            text-decoration: underline;
        }

        .page-breadcrumb span.sep {
            color: #b5c2ba;
            font-weight: 400;
        }

        .page-breadcrumb strong {
            color: var(--text-main);
            font-weight: 700;
        }

        .catalog-eyebrow-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: rgba(245, 184, 0, 0.14);
            color: #92400e;
            border: 1px solid rgba(245, 184, 0, 0.35);
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 10px;
        }

        .catalog-eyebrow-badge .badge-pulse-dot {
            width: 7px;
            height: 7px;
            background: #d97706;
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.2);
            animation: pulse-dot 2s infinite ease-in-out;
        }

        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
        }

        .catalog-hero-title {
            font-size: 38px;
            font-weight: 800;
            color: #0b1c13;
            letter-spacing: -0.03em;
            line-height: 1.18;
            margin-bottom: 8px;
        }

        .catalog-hero-title .title-accent {
            background: linear-gradient(135deg, #0e3d23 0%, #166534 50%, #d97706 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            display: inline-block;
        }

        .catalog-hero-subtitle {
            font-size: 14.5px;
            color: #4b5d52;
            max-width: 700px;
            line-height: 1.55;
            margin-bottom: 18px;
        }

        .catalog-feature-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding-top: 6px;
        }

        .feat-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: var(--forest-900);
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(22, 78, 50, 0.12);
            padding: 5px 12px;
            border-radius: var(--radius-pill);
            box-shadow: 0 1px 2px rgba(10, 38, 24, 0.03);
        }

        .feat-pill svg {
            color: #059669;
            flex-shrink: 0;
        }

        /* ==========================================================================
           PRO-MAX UPGRADE: VISUAL CATEGORY ICONS CAROUSEL
           ========================================================================== */
        .category-carousel-section {
            position: relative;
            margin-bottom: 28px;
        }

        .category-carousel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .category-section-title {
            font-size: 13.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--forest-900);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .category-section-title svg {
            color: var(--gold-600);
        }

        .carousel-nav-arrows {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .carousel-arrow-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid var(--border-color);
            color: var(--forest-900);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-subtle);
            transition: all var(--transition-fast);
        }

        .carousel-arrow-btn:hover {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
            transform: scale(1.06);
        }

        .category-tiles-carousel {
            display: flex;
            align-items: stretch;
            gap: 14px;
            overflow-x: auto;
            padding: 4px 4px 14px 4px;
            scrollbar-width: thin;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        .category-tiles-carousel::-webkit-scrollbar {
            height: 5px;
        }
        .category-tiles-carousel::-webkit-scrollbar-thumb {
            background: #d5ccba;
            border-radius: 6px;
        }
        .category-tiles-carousel::-webkit-scrollbar-thumb:hover {
            background: #b8ac96;
        }

        .cat-tile-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text-body);
            background: #ffffff;
            border: 1.5px solid #e7ded0;
            border-radius: 18px;
            padding: 14px 18px 12px 18px;
            min-width: 100px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(10, 38, 24, 0.04);
            position: relative;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .cat-tile-btn:hover {
            transform: translateY(-4px);
            border-color: #cbd8cf;
            box-shadow: 0 8px 20px -4px rgba(10, 38, 24, 0.1);
        }

        .cat-icon-circle {
            width: 52px;
            height: 52px;
            border-radius: 15px;
            background: #f4efe6;
            border: 1px solid rgba(22, 78, 50, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--forest-900);
            transition: all 0.2s ease;
        }

        .cat-tile-btn:hover .cat-icon-circle {
            background: #ede6d8;
            transform: scale(1.05);
        }

        .cat-tile-label {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
            letter-spacing: -0.01em;
        }

        /* Active Category Tile State */
        .cat-tile-btn.active {
            background: linear-gradient(145deg, #072618 0%, #12442c 100%);
            border-color: #f5b800;
            box-shadow: 0 10px 24px -4px rgba(7, 38, 24, 0.35), 0 0 0 2px rgba(245, 184, 0, 0.6);
            transform: translateY(-3px);
        }

        .cat-tile-btn.active .cat-icon-circle {
            background: rgba(245, 184, 0, 0.18);
            color: #f5b800;
            border-color: rgba(245, 184, 0, 0.4);
        }

        .cat-tile-btn.active .cat-tile-label {
            color: #ffffff;
            font-weight: 800;
        }

        .cat-tile-btn.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 14px;
            height: 4px;
            background: #f5b800;
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(245, 184, 0, 0.6);
        }

        /* ==========================================================================
           PRO-MAX UPGRADE: SEARCH & SORT TOOLBAR & QUICK CHIPS
           ========================================================================== */
        .search-sort-bar-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
            flex-wrap: wrap;
            background: #ffffff;
            padding: 8px 12px;
            border-radius: 18px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(10, 38, 24, 0.04);
        }

        .search-field-box {
            position: relative;
            flex: 1;
            min-width: 260px;
        }

        .search-field-box .search-lead-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8b9c92;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-field-box input {
            width: 100%;
            padding: 12px 65px 12px 42px;
            background: #faf8f5;
            border: 1.5px solid transparent;
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--text-main);
            transition: all var(--transition-fast);
        }

        .search-field-box input:focus {
            outline: none;
            background: #ffffff;
            border-color: var(--forest-800);
            box-shadow: 0 0 0 3.5px rgba(16, 56, 36, 0.1);
        }

        .search-shortcut-badge {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            font-weight: 700;
            color: #8b9c92;
            background: #ede7da;
            padding: 2px 6px;
            border-radius: 4px;
            pointer-events: none;
        }

        .search-icon-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--forest-900);
            border: none;
            color: #ffffff;
            width: 30px;
            height: 30px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-fast);
        }

        .search-icon-btn:hover {
            background: var(--forest-800);
            transform: translateY(-50%) scale(1.05);
        }

        .sort-picker-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #faf8f5;
            padding: 4px 10px 4px 12px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }

        .sort-label-txt {
            font-size: 12.5px;
            color: var(--text-muted);
            white-space: nowrap;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .sort-select-btn {
            padding: 8px 10px;
            background: transparent;
            border: none;
            font-family: inherit;
            font-size: 13px;
            font-weight: 800;
            color: var(--forest-950);
            cursor: pointer;
            outline: none;
        }

        /* Quick Filter Tags Row */
        .quick-tags-container {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            margin-bottom: 22px;
            padding-bottom: 4px;
            scrollbar-width: none;
        }
        .quick-tags-container::-webkit-scrollbar {
            display: none;
        }

        .quick-tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            background: #ffffff;
            border: 1px solid var(--border-color);
            color: var(--text-body);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            transition: all var(--transition-fast);
        }

        .quick-tag-pill:hover {
            background: #f4efe6;
            border-color: #cbd8cf;
            color: var(--forest-950);
            transform: translateY(-1px);
        }

        .quick-tag-pill.active {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
            box-shadow: 0 2px 8px rgba(10, 38, 24, 0.15);
        }

        /* Active Filter Feedback Banner */
        .active-filter-status-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            padding: 10px 16px;
            background: rgba(16, 56, 36, 0.04);
            border: 1px dashed rgba(16, 56, 36, 0.15);
            border-radius: var(--radius-md);
        }

        .active-count-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--forest-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .live-count-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        .active-filter-chips {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .active-chip-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            font-weight: 700;
            color: var(--forest-950);
        }

        .active-chip-remove {
            color: #dc2626;
            text-decoration: none;
            font-weight: 800;
            font-size: 13px;
            line-height: 1;
        }

        .btn-clear-all-filter {
            font-size: 12px;
            font-weight: 800;
            color: #dc2626;
            text-decoration: none;
            margin-left: 4px;
        }
        .btn-clear-all-filter:hover {
            text-decoration: underline;
        }

        /* ==========================================================================
           2-COLUMN LAYOUT: FILTER SIDEBAR & PRODUCT GRID
           ========================================================================== */
        .catalog-main-grid-layout {
            display: grid;
            grid-template-columns: 270px 1fr;
            gap: 28px;
            align-items: start;
        }

        /* FILTER PANEL SIDEBAR */
        .filter-panel-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px 18px;
            box-shadow: var(--shadow-subtle);
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .filter-head-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-light);
        }

        .filter-head-flex h3 {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-reset-link {
            font-size: 12px;
            font-weight: 700;
            color: var(--forest-800);
            text-decoration: none;
        }

        .filter-reset-link:hover {
            text-decoration: underline;
        }

        .filter-group-block {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group-block label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
        }

        .filter-group-block select,
        .filter-group-block input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 12.5px;
            background: #ffffff;
            color: var(--text-main);
            outline: none;
            transition: border-color var(--transition-fast);
        }

        .filter-group-block select:focus,
        .filter-group-block input:focus {
            border-color: var(--forest-800);
        }

        .price-range-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .btn-apply-filters {
            background: var(--forest-900);
            color: #ffffff;
            border: none;
            padding: 11px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: background var(--transition-fast);
            margin-top: 4px;
        }

        .btn-apply-filters:hover {
            background: var(--forest-800);
        }

        /* RIGHT: PRODUCTS CATALOG */
        .catalog-content-area {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .catalog-status-header {
            font-size: 13.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .catalog-status-header strong {
            color: var(--text-main);
            font-weight: 800;
        }

        .products-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        /* VERCEL/DANATRAIL SPEC PRODUCT CARD */
        .gear-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform var(--transition-normal), box-shadow var(--transition-normal), border-color var(--transition-normal);
            position: relative;
        }

        .gear-card:hover {
            transform: translateY(-3px);
            border-color: #cbd8cf;
            box-shadow: var(--shadow-card);
        }

        .gear-img-wrap {
            height: 180px;
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .gear-photo {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .gear-card:hover .gear-photo {
            transform: scale(1.05);
        }

        .gear-img-fallback {
            width: 50px;
            height: 50px;
            color: var(--gold-500);
            opacity: 0.8;
        }

        .badge-siap-sewa {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(10, 38, 24, 0.85);
            color: #ffffff;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: var(--radius-pill);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 2;
        }

        .badge-dot-green {
            width: 6px;
            height: 6px;
            background: #10b981;
            border-radius: 50%;
        }

        .btn-wishlist-heart {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            z-index: 2;
            transition: all var(--transition-fast);
        }

        .btn-wishlist-heart:hover {
            color: #dc2626;
            background: #ffffff;
            transform: scale(1.08);
        }

        .gear-card-body {
            padding: 14px 16px 16px 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .gear-category-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--earth-brown);
            margin-bottom: 4px;
        }

        .gear-brand-tag {
            color: var(--text-muted);
            font-weight: 700;
        }

        .gear-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.3;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .gear-location-row {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .gear-location-row svg {
            color: var(--forest-700);
            flex-shrink: 0;
        }

        /* ==========================================================================
           ENHANCED MODERN GEAR PRICING & ACTION BUTTONS
           ========================================================================== */
        .gear-pricing-container {
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid var(--border-light);
        }

        .gear-pricing-container.single {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .price-sub-label {
            display: block;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: var(--forest-800);
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .price-sub-label.gold {
            color: #b45309;
        }

        .pricing-dual-grid {
            display: flex;
            align-items: center;
            background: #f8faf7;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 7px 9px;
            gap: 8px;
        }

        .pricing-slot {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .pricing-slot.rental-slot {
            padding-right: 4px;
        }

        .pricing-slot.sale-slot {
            background: #fffdf5;
            border-radius: 6px;
            padding: 4px 6px;
            border: 1px solid #fef3c7;
        }

        .pricing-slot-divider {
            width: 1px;
            height: 32px;
            background: var(--border-color);
            flex-shrink: 0;
        }

        .slot-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .slot-badge-label {
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: 0.4px;
            color: var(--forest-800);
        }

        .slot-badge-label.gold {
            color: #b45309;
        }

        .slot-stock-label {
            font-size: 9px;
            font-weight: 700;
            color: var(--text-muted);
        }

        .slot-stock-label.gold {
            color: #b45309;
        }

        .slot-price {
            font-family: 'Outfit', sans-serif;
            font-size: 13.5px;
            font-weight: 800;
            color: var(--forest-950);
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .slot-price small {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 9.5px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .slot-price.gold {
            color: #b45309;
        }

        .gear-price-box {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: var(--forest-950);
        }

        .gear-price-box.gold {
            color: #b45309;
        }

        .gear-price-box span {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .stock-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: var(--radius-pill);
            background: var(--surface-subtle);
            border: 1px solid var(--border-color);
            color: var(--forest-800);
        }

        .stock-pill-badge.ready {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .stock-pill-badge.gold {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .dot-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
        }

        .dot-indicator.gold {
            background: #f59e0b;
        }

        /* Action Buttons Row */
        .card-action-btns-row {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
            width: 100%;
        }

        .card-action-btns-row .btn-card-detail {
            background: #ffffff;
            color: var(--forest-900);
            border: 1.5px solid var(--border-color);
            padding: 8px 10px;
            border-radius: var(--radius-md);
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all var(--transition-fast);
            flex: 0 0 auto;
        }

        .card-action-btns-row .btn-card-detail:hover {
            background: var(--surface-subtle);
            border-color: #cbd8cf;
            color: var(--forest-950);
        }

        .card-action-btns-row .btn-rent-action-cta {
            background: var(--forest-900);
            color: var(--gold-500);
            border: none;
            padding: 8px 10px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all var(--transition-fast);
            flex: 1;
        }

        .card-action-btns-row .btn-rent-action-cta:hover {
            background: var(--forest-800);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .card-action-btns-row .btn-buy-action-cta {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
            color: #ffffff;
            border: none;
            padding: 8px 10px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all var(--transition-fast);
            flex: 1;
        }

        .card-action-btns-row .btn-buy-action-cta:hover {
            background: linear-gradient(135deg, #b45309 0%, #92400e 100%);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .card-action-btns-row.single-action .btn-card-detail {
            flex: 1;
        }

        .card-action-btns-row.single-action .btn-rent-action-cta,
        .card-action-btns-row.single-action .btn-buy-action-cta {
            flex: 1.4;
        }

        /* Service Tabs Switcher */
        .service-tabs-nav {
            display: inline-flex;
            align-items: center;
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-pill);
            padding: 4px;
            gap: 4px;
            margin-top: 14px;
            box-shadow: var(--shadow-subtle);
        }

        .service-tab-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .service-tab-item:hover {
            color: var(--forest-900);
            background: var(--surface-subtle);
        }

        .service-tab-item.active {
            background: var(--forest-900);
            color: var(--gold-500);
            box-shadow: 0 2px 6px rgba(4, 31, 20, 0.15);
        }

        .service-tab-item.active svg {
            color: var(--gold-500);
        }

        /* Type Badges on Cards */
        .badge-type-rental {
            background: rgba(10, 38, 24, 0.88);
            color: #ffffff;
        }

        .badge-type-sale {
            background: rgba(180, 83, 9, 0.92);
            color: #ffffff;
        }

        .badge-type-both {
            background: rgba(15, 118, 110, 0.92);
            color: #ffffff;
        }

        .badge-dot-gold {
            width: 6px;
            height: 6px;
            background: #f59e0b;
            border-radius: 50%;
        }

        .badge-dot-teal {
            width: 6px;
            height: 6px;
            background: #14b8a6;
            border-radius: 50%;
        }

        /* ==========================================================================
           MODAL BOOKING SEWA & BELI PRODUK (ROCK-SOLID OVERLAY & CONSTRAINTS)
           ========================================================================== */
        .modal-overlay {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(4, 31, 20, 0.78) !important;
            -webkit-backdrop-filter: blur(6px) !important;
            backdrop-filter: blur(6px) !important;
            z-index: 99999 !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 16px !important;
            box-sizing: border-box !important;
            overflow-y: auto !important;
        }

        .modal-overlay.active {
            display: flex !important;
        }

        .modal-dialog {
            background: #ffffff !important;
            border-radius: 16px !important;
            width: 100% !important;
            max-width: 520px !important;
            max-height: calc(100vh - 32px) !important;
            box-shadow: 0 25px 50px -12px rgba(4, 31, 20, 0.45) !important;
            overflow: hidden !important;
            border: 1px solid var(--border-color) !important;
            animation: modalFadeIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
            display: flex !important;
            flex-direction: column !important;
            margin: auto !important;
            position: relative !important;
        }

        .modal-dialog form {
            display: flex !important;
            flex-direction: column !important;
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow: hidden !important;
            height: 100% !important;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header-bar {
            background: var(--forest-950);
            color: #ffffff;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .modal-header-bar h3 {
            font-size: 15.5px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .btn-modal-close-icon {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #d1fae5;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all var(--transition-fast);
        }

        .btn-modal-close-icon:hover {
            background: rgba(239, 68, 68, 0.85);
            color: #ffffff;
        }

        .modal-body-scroll {
            padding: 16px 18px !important;
            overflow-y: auto !important;
            flex: 1 1 auto !important;
            min-height: 0 !important;
            -webkit-overflow-scrolling: touch !important;
        }

        .modal-body-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .modal-body-scroll::-webkit-scrollbar-track {
            background: #f1f5f3;
        }
        .modal-body-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .modal-body-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .modal-prod-highlight {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 10px 12px !important;
            background: var(--bg-canvas) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: var(--radius-md) !important;
            margin-bottom: 14px !important;
        }

        .modal-prod-img {
            width: 56px !important;
            min-width: 56px !important;
            max-width: 56px !important;
            height: 56px !important;
            min-height: 56px !important;
            max-height: 56px !important;
            border-radius: 8px !important;
            object-fit: cover !important;
            background: #ffffff !important;
            border: 1px solid var(--border-color) !important;
            flex-shrink: 0 !important;
            display: block !important;
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
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
        }

        .form-field-group input,
        .form-field-group select,
        .form-field-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13px;
            color: var(--text-main);
            background: #ffffff;
            transition: border-color var(--transition-fast);
            box-sizing: border-box;
        }

        .form-field-group input:focus,
        .form-field-group select:focus,
        .form-field-group textarea:focus {
            outline: none;
            border-color: var(--forest-800);
            box-shadow: 0 0 0 3px rgba(14, 86, 56, 0.12);
        }

        .price-calc-summary-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-md);
            padding: 12px 14px;
            margin-top: 10px;
            font-size: 12.5px;
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            color: var(--text-body);
        }

        .calc-row.total-row {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #bbf7d0;
            font-weight: 800;
            font-size: 14px;
            color: var(--forest-950);
        }

        .modal-footer-bar {
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-modal-cancel {
            background: #ffffff;
            color: var(--text-body);
            border: 1.5px solid var(--border-color);
            padding: 9px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-modal-cancel:hover {
            background: var(--surface-subtle);
            color: var(--forest-950);
        }

        .btn-modal-submit {
            background: var(--forest-900);
            color: var(--gold-500);
            border: none;
            padding: 9px 20px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-modal-submit:hover {
            background: var(--forest-800);
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* RESPONSIVE */
        @media (max-width: 980px) {
            .catalog-main-grid-layout {
                grid-template-columns: 1fr;
            }
            .filter-panel-card {
                margin-bottom: 12px;
            }
        }

        @media (max-width: 860px) {
            html, body {
                height: auto;
                overflow-y: auto;
            }
            body {
                height: auto;
                min-height: 100vh;
                flex-direction: column;
            }
            .sidebar {
                position: fixed !important;
                top: 0;
                left: 0;
                bottom: 0;
                height: 100vh;
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
                height: auto;
                overflow-y: visible;
                padding: 16px 14px 32px 14px;
            }
            .catalog-hero-title {
                font-size: 28px;
            }
            .products-card-grid {
                grid-template-columns: 1fr 1fr;
            }
            .form-grid-2col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .products-card-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</head>

<body>

    <!-- Mobile Top Bar -->
    <header class="mobile-top-bar">
        <div class="mobile-brand">
            <img src="../assets/logo.png" alt="DANATRAIL" onerror="this.src='https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=80&auto=format&fit=crop&q=60'">
            <span>DANATRAIL Outdoor</span>
        </div>
        <button type="button" class="btn-toggle-menu" onclick="toggleMobileSidebar()" aria-label="Buka Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7" />
            </svg>
            <span>Menu</span>
        </button>
    </header>

    <!-- Sidebar Navigation -->
    <?php include 'menu.php'; ?>

    <!-- Main Content -->
    <main class="main-wrapper">

        <?php if (isset($_GET['sukses'])): ?>
            <div style="background: #ecfdf5; color: #065f46; border: 1.5px solid #a7f3d0; border-radius: 12px; padding: 12px 18px; margin-bottom: 18px; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.15);">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span><?php echo htmlspecialchars($_GET['sukses']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['pesan'])): ?>
            <div style="background: #fef2f2; color: #991b1b; border: 1.5px solid #fecaca; border-radius: 12px; padding: 12px 18px; margin-bottom: 18px; font-weight: 700; font-size: 13.5px; display: flex; align-items: center; gap: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span><?php echo htmlspecialchars($_GET['pesan']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Pro-Max Hero Banner Card -->
        <section class="catalog-hero-card">
            <div class="hero-inner-content">
                <!-- Breadcrumb -->
                <div class="page-breadcrumb">
                    <a href="about.php">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Beranda</span>
                    </a>
                    <span class="sep">/</span>
                    <strong>Perlengkapan</strong>
                </div>

                <!-- Eyebrow Badge -->
                <div class="catalog-eyebrow-badge">
                    <span class="badge-pulse-dot"></span>
                    <span>Katalog Resmi DANATRAIL • Expedition Grade</span>
                </div>

                <!-- Hero Title with Gradient Accent -->
                <h1 class="catalog-hero-title">
                    Perlengkapan untuk <span class="title-accent">medanmu.</span>
                </h1>
                
                <p class="catalog-hero-subtitle">
                    Dari penjelajahan akhir pekan hingga ekspedisi puncak gunung tertinggi. Temukan perlengkapan tangguh, teruji, dan siap pakai tepat saat kamu butuhkan.
                </p>

                <!-- Value Proposition Feature Pills -->
                <div class="catalog-feature-pills">
                    <div class="feat-pill">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>100% Higienis & Siap Pakai</span>
                    </div>
                    <div class="feat-pill">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Booking Instan & Cepat</span>
                    </div>
                    <div class="feat-pill">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span><?php echo $total_products_count > 0 ? $total_products_count . '+ Alat Pilihan' : 'Koleksi Terlengkap'; ?></span>
                    </div>
                    <div class="feat-pill">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <span>Rating 4.9+ Pendaki</span>
                    </div>
                </div>

                <!-- Service Selector Tabs -->
                <div style="margin-top: 14px;">
                    <div class="service-tabs-nav">
                        <a href="dashboard.php<?php echo !empty($cat_filter) ? '?kategori='.urlencode($cat_filter) : ''; ?>" class="service-tab-item <?php echo empty($tab_filter) ? 'active' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span>Semua Layanan</span>
                        </a>
                        <a href="dashboard.php?tab=rental<?php echo !empty($cat_filter) ? '&kategori='.urlencode($cat_filter) : ''; ?>" class="service-tab-item <?php echo ($tab_filter === 'rental') ? 'active' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Rental Alat</span>
                        </a>
                        <a href="dashboard.php?tab=toko<?php echo !empty($cat_filter) ? '&kategori='.urlencode($cat_filter) : ''; ?>" class="service-tab-item <?php echo ($tab_filter === 'toko' || $tab_filter === 'sale') ? 'active' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span>Toko (Beli Baru)</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pro-Max Category Carousel Section -->
        <section class="category-carousel-section">
            <div class="category-carousel-header">
                <div class="category-section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>Kategori Perlengkapan</span>
                </div>
                <div class="carousel-nav-arrows">
                    <button type="button" class="carousel-arrow-btn" onclick="scrollCategoryCarousel(-220)" aria-label="Geser Kiri">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button type="button" class="carousel-arrow-btn" onclick="scrollCategoryCarousel(220)" aria-label="Geser Kanan">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="category-tiles-carousel" id="catCarouselContainer">
                <a href="dashboard.php<?php echo !empty($tab_filter) ? '?tab='.urlencode($tab_filter) : ''; ?>" class="cat-tile-btn <?php echo empty($cat_filter) ? 'active' : ''; ?>">
                    <div class="cat-icon-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                    </div>
                    <span class="cat-tile-label">Semua</span>
                </a>

                <?php foreach ($visual_categories as $vc): 
                    $is_active_tile = (strtolower($cat_filter) === strtolower($vc['db_match']) || strtolower($cat_filter) === strtolower($vc['name']));
                    $cat_url = 'dashboard.php?kategori=' . urlencode($vc['db_match']);
                    if (!empty($tab_filter)) {
                        $cat_url .= '&tab=' . urlencode($tab_filter);
                    }
                ?>
                    <a href="<?php echo $cat_url; ?>" class="cat-tile-btn <?php echo $is_active_tile ? 'active' : ''; ?>">
                        <div class="cat-icon-circle">
                            <?php echo $vc['icon']; ?>
                        </div>
                        <span class="cat-tile-label"><?php echo htmlspecialchars($vc['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Pro-Max Search & Sort Row with Instant Live Search -->
        <div class="search-sort-bar-row" id="searchFilterBar">
            <div class="search-field-box">
                <span class="search-lead-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text" id="mainSearchInput" placeholder="Ketik untuk live search tenda, carrier, headlamp, cooking set..." value="<?php echo htmlspecialchars($search_query); ?>" oninput="liveFilterCatalog(this.value)" autocomplete="off">
                <span class="search-shortcut-badge" title="Tekan '/' untuk mencari">/</span>
                <button type="button" class="search-icon-btn" id="btnSearchClear" style="display: none;" onclick="clearCatalogSearch()" aria-label="Hapus Pencarian">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form action="dashboard.php" method="GET" class="sort-picker-wrap" id="sortForm">
                <?php if (!empty($cat_filter)): ?>
                    <input type="hidden" name="kategori" value="<?php echo htmlspecialchars($cat_filter); ?>">
                <?php endif; ?>
                <?php if (!empty($tab_filter)): ?>
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab_filter); ?>">
                <?php endif; ?>
                <span class="sort-label-txt">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                    </svg>
                    <span>Urutkan:</span>
                </span>
                <select name="sort" class="sort-select-btn" onchange="document.getElementById('sortForm').submit()">
                    <option value="newest" <?php echo ($sort_by == 'newest' || $sort_by == 'recommend') ? 'selected' : ''; ?>>Rekomendasi</option>
                    <option value="price_low" <?php echo ($sort_by == 'price_low') ? 'selected' : ''; ?>>Harga Terendah</option>
                    <option value="price_high" <?php echo ($sort_by == 'price_high') ? 'selected' : ''; ?>>Harga Tertinggi</option>
                    <option value="stock_high" <?php echo ($sort_by == 'stock_high') ? 'selected' : ''; ?>>Stok Terbanyak</option>
                    <option value="name_asc" <?php echo ($sort_by == 'name_asc') ? 'selected' : ''; ?>>Nama (A - Z)</option>
                </select>
            </form>
        </div>

        <!-- Active Filter Indicator Banner -->
        <?php 
        $has_active_filters = !empty($cat_filter) || !empty($search_query) || !empty($stock_status) || !empty($tab_filter) || ($sort_by !== 'newest' && $sort_by !== 'recommend');
        if ($has_active_filters):
        ?>
        <div class="active-filter-status-bar">
            <div class="active-count-text">
                <span class="live-count-indicator"></span>
                <span id="activeCountLabel">Menampilkan <strong><?php echo $jumlah_produk_tampil; ?></strong> perlengkapan yang cocok</span>
            </div>
            <div class="active-filter-chips">
                <?php if (!empty($tab_filter)): ?>
                    <span class="active-chip-item">
                        <span>Layanan: <?php echo ($tab_filter === 'rental') ? 'Rental Alat' : 'Toko (Beli)'; ?></span>
                        <a href="dashboard.php?<?php echo http_build_query(array_merge($_GET, ['tab' => ''])); ?>" class="active-chip-remove" title="Hapus Filter">×</a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($cat_filter)): ?>
                    <span class="active-chip-item">
                        <span>Kategori: <?php echo htmlspecialchars($cat_filter); ?></span>
                        <a href="dashboard.php?<?php echo http_build_query(array_merge($_GET, ['kategori' => ''])); ?>" class="active-chip-remove" title="Hapus Filter">×</a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($search_query)): ?>
                    <span class="active-chip-item">
                        <span>Cari: "<?php echo htmlspecialchars($search_query); ?>"</span>
                        <a href="dashboard.php?<?php echo http_build_query(array_merge($_GET, ['q' => ''])); ?>" class="active-chip-remove" title="Hapus Filter">×</a>
                    </span>
                <?php endif; ?>

                <?php if (!empty($stock_status)): ?>
                    <span class="active-chip-item">
                        <span>Stok: <?php echo htmlspecialchars($stock_status); ?></span>
                        <a href="dashboard.php?<?php echo http_build_query(array_merge($_GET, ['stock_status' => ''])); ?>" class="active-chip-remove" title="Hapus Filter">×</a>
                    </span>
                <?php endif; ?>

                <a href="dashboard.php" class="btn-clear-all-filter">Reset Semua Filter</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 2-Column Catalog Layout -->
        <div class="catalog-main-grid-layout">

            <!-- Left Filter Sidebar -->
            <aside class="filter-panel-card">
                <div class="filter-head-flex">
                    <h3>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                        <span>Filter perlengkapan</span>
                    </h3>
                    <a href="dashboard.php" class="filter-reset-link">Reset</a>
                </div>

                <form action="dashboard.php" method="GET" style="display: flex; flex-direction: column; gap: 14px;">
                    <?php if (!empty($tab_filter)): ?>
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab_filter); ?>">
                    <?php endif; ?>
                    <?php if (!empty($search_query)): ?>
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>
                    <?php if (!empty($sort_by)): ?>
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <?php endif; ?>

                    <!-- Filter Kategori -->
                    <div class="filter-group-block">
                        <label for="filter_kategori">Kategori</label>
                        <select name="kategori" id="filter_kategori">
                            <option value="">Semua kategori</option>
                            <?php foreach ($cat_list as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['name']); ?>" <?php echo ($cat_filter === $c['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Tanggal Sewa -->
                    <?php if ($tab_filter !== 'toko' && $tab_filter !== 'sale'): ?>
                    <div class="filter-group-block">
                        <label for="filter_tanggal">Tanggal sewa</label>
                        <input type="date" name="rental_date" id="filter_tanggal" value="<?php echo htmlspecialchars($rental_date); ?>" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <?php endif; ?>

                    <!-- Filter Ketersediaan -->
                    <div class="filter-group-block">
                        <label for="filter_stok">Ketersediaan</label>
                        <select name="stock_status" id="filter_stok">
                            <option value="">Semua status stok</option>
                            <option value="available" <?php echo ($stock_status === 'available') ? 'selected' : ''; ?>>Tersedia & Ready</option>
                            <option value="ready" <?php echo ($stock_status === 'ready') ? 'selected' : ''; ?>>Stok Melimpah (> 3)</option>
                            <option value="low" <?php echo ($stock_status === 'low') ? 'selected' : ''; ?>>Stok Terbatas (&le; 3)</option>
                        </select>
                    </div>

                    <!-- Filter Rentang Harga -->
                    <div class="filter-group-block">
                        <label><?php echo ($tab_filter === 'toko' || $tab_filter === 'sale') ? 'Harga Pembelian' : 'Harga sewa / hari'; ?></label>
                        <div class="price-range-inputs">
                            <input type="number" name="min_price" placeholder="Min Rp" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" min="0">
                            <input type="number" name="max_price" placeholder="Maks Rp" value="<?php echo $max_price > 0 ? $max_price : ''; ?>" min="0">
                        </div>
                    </div>

                    <!-- Filter Lokasi / Basecamp -->
                    <div class="filter-group-block">
                        <label for="filter_lokasi">Lokasi / basecamp</label>
                        <input type="text" id="filter_lokasi" placeholder="Basecamp DANATRAIL" value="Basecamp DANATRAIL" readonly style="background: var(--bg-canvas); color: var(--text-muted);">
                    </div>

                    <button type="submit" class="btn-apply-filters">Terapkan filter</button>
                </form>
            </aside>

            <!-- Right Products Grid Area -->
            <div class="catalog-content-area">
                <div class="catalog-status-header">
                    <strong id="catalogCountHeader"><?php echo $jumlah_produk_tampil; ?></strong> perlengkapan dalam katalog
                </div>

                <div class="products-card-grid" id="productsCardGrid">
                    <?php if ($products && mysqli_num_rows($products) > 0): ?>
                        <?php while ($prod = mysqli_fetch_assoc($products)): 
                            $p_type = $prod['product_type'] ?? 'rental';
                            $stk_rental = (int)($prod['stock'] ?? 0);
                            $stk_sale = (int)($prod['stock_sale'] ?? 0);
                            $price_rental = (float)($prod['price'] ?? 0);
                            $price_sale = (float)($prod['price_sale'] ?? 0);
                            $discount_pct = floatval($prod['discount_percent'] ?? 0);
                            $eff_price_rental = get_discounted_price($price_rental, $discount_pct);
                            $eff_price_sale   = get_discounted_price($price_sale, $discount_pct);
                            $cat_upper = strtoupper($prod['category'] ?? 'OUTDOOR GEAR');
                        ?>
                            <div class="gear-card" data-title="<?php echo htmlspecialchars(strtolower($prod['name'])); ?>" data-cat="<?php echo htmlspecialchars(strtolower($prod['category'])); ?>" data-desc="<?php echo htmlspecialchars(strtolower($prod['description'] ?? '')); ?>">
                                <a href="detail_produk.php?id=<?php echo $prod['id']; ?>" class="gear-img-wrap" title="Lihat detail <?php echo htmlspecialchars($prod['name']); ?>">
                                    <?php if (!empty($prod['image']) && file_exists('../uploads/products/' . $prod['image'])): ?>
                                        <img src="../uploads/products/<?php echo htmlspecialchars($prod['image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="gear-photo" loading="lazy">
                                    <?php else: ?>
                                        <svg class="gear-img-fallback" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                    <?php endif; ?>

                                    <!-- Top Left Badge: Type Indicator -->
                                    <?php if ($p_type === 'sale'): ?>
                                        <div class="badge-siap-sewa badge-type-sale">
                                            <span class="badge-dot-gold"></span>
                                            <span>Dijual</span>
                                        </div>
                                    <?php elseif ($p_type === 'both'): ?>
                                        <div class="badge-siap-sewa badge-type-both">
                                            <span class="badge-dot-teal"></span>
                                            <span>Rental & Jual</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="badge-siap-sewa badge-type-rental">
                                            <span class="badge-dot-green"></span>
                                            <span>Rental</span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($discount_pct > 0): ?>
                                        <div style="position: absolute; bottom: 8px; left: 8px; background: #dc2626; color: #fff; font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 999px; box-shadow: 0 2px 6px rgba(220,38,38,0.4); z-index: 3;">
                                            -<?php echo rtrim(rtrim(number_format($discount_pct, 2), '0'), '.'); ?>%
                                        </div>
                                    <?php endif; ?>

                                    <!-- Top Right Wishlist Heart -->
                                    <button type="button" class="btn-wishlist-heart" aria-label="Favorit" onclick="event.preventDefault(); event.stopPropagation(); this.classList.toggle('active');">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                    </button>
                                </a>

                                <div class="gear-card-body">
                                    <div class="gear-category-row">
                                        <span><?php echo htmlspecialchars($cat_upper); ?></span>
                                        <span class="gear-brand-tag">DANATRAIL</span>
                                    </div>

                                    <h3 class="gear-title">
                                        <a href="detail_produk.php?id=<?php echo $prod['id']; ?>" style="color: inherit; text-decoration: none;" title="<?php echo htmlspecialchars($prod['name']); ?>">
                                            <?php echo htmlspecialchars($prod['name']); ?>
                                        </a>
                                    </h3>

                                    <div class="gear-location-row">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Basecamp DANATRAIL</span>
                                    </div>

                                    <div class="gear-pricing-container <?php echo ($p_type === 'both' && empty($tab_filter)) ? 'dual' : 'single'; ?>">
                                        <?php if ($p_type === 'sale' || ($p_type === 'both' && ($tab_filter === 'toko' || $tab_filter === 'sale'))): ?>
                                            <div class="single-price-left">
                                                <span class="price-sub-label gold">HARGA BELI BARU</span>
                                                <div class="gear-price-box gold">
                                                    <?php if ($discount_pct > 0): ?>
                                                        <span style="font-size: 11.5px; text-decoration: line-through; color: #94a3b8; font-weight: 500; display: block; line-height: 1.1;">Rp <?php echo number_format($price_sale, 0, ',', '.'); ?></span>
                                                    <?php endif; ?>
                                                    Rp <?php echo number_format($eff_price_sale, 0, ',', '.'); ?>
                                                </div>
                                            </div>
                                            <div class="stock-pill-badge <?php echo ($stk_sale > 0) ? 'gold' : ''; ?>">
                                                <span class="dot-indicator <?php echo ($stk_sale > 0) ? 'gold' : ''; ?>"></span>
                                                <span>Stok <?php echo $stk_sale; ?></span>
                                            </div>
                                        <?php elseif ($p_type === 'both' && empty($tab_filter)): ?>
                                            <div class="pricing-dual-grid">
                                                <div class="pricing-slot rental-slot">
                                                    <div class="slot-head">
                                                        <span class="slot-badge-label">SEWA</span>
                                                        <span class="slot-stock-label">Stok <?php echo $stk_rental; ?></span>
                                                    </div>
                                                    <div class="slot-price">
                                                        <?php if ($discount_pct > 0): ?>
                                                            <div style="font-size: 10px; text-decoration: line-through; color: #94a3b8; font-weight: 500;">Rp <?php echo number_format($price_rental, 0, ',', '.'); ?></div>
                                                        <?php endif; ?>
                                                        Rp <?php echo number_format($eff_price_rental, 0, ',', '.'); ?><small>/hari</small>
                                                    </div>
                                                </div>
                                                <div class="pricing-slot-divider"></div>
                                                <div class="pricing-slot sale-slot">
                                                    <div class="slot-head">
                                                        <span class="slot-badge-label gold">BELI BARU</span>
                                                        <span class="slot-stock-label gold">Stok <?php echo $stk_sale; ?></span>
                                                    </div>
                                                    <div class="slot-price gold">
                                                        <?php if ($discount_pct > 0): ?>
                                                            <div style="font-size: 10px; text-decoration: line-through; color: #94a3b8; font-weight: 500;">Rp <?php echo number_format($price_sale, 0, ',', '.'); ?></div>
                                                        <?php endif; ?>
                                                        Rp <?php echo number_format($eff_price_sale, 0, ',', '.'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="single-price-left">
                                                <span class="price-sub-label">TARIF SEWA</span>
                                                <div class="gear-price-box">
                                                    <?php if ($discount_pct > 0): ?>
                                                        <span style="font-size: 11.5px; text-decoration: line-through; color: #94a3b8; font-weight: 500; display: block; line-height: 1.1;">Rp <?php echo number_format($price_rental, 0, ',', '.'); ?></span>
                                                    <?php endif; ?>
                                                    Rp <?php echo number_format($eff_price_rental, 0, ',', '.'); ?> <span>/hari</span>
                                                </div>
                                            </div>
                                            <div class="stock-pill-badge <?php echo ($stk_rental > 0) ? 'ready' : ''; ?>">
                                                <span class="dot-indicator"></span>
                                                <span>Stok <?php echo $stk_rental; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-action-btns-row <?php echo ($p_type === 'both' && empty($tab_filter)) ? 'dual-action' : 'single-action'; ?>">
                                        <a href="detail_produk.php?id=<?php echo $prod['id']; ?>" class="btn-card-detail" title="Lihat detail spesifikasi">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Detail</span>
                                        </a>

                                        <?php if ($p_type === 'sale'): ?>
                                            <?php if ($stk_sale > 0): ?>
                                                <a href="checkout.php?product_id=<?php echo $prod['id']; ?>" class="btn-buy-action-cta" style="text-decoration: none;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                    </svg>
                                                    <span>Beli Sekarang</span>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn-buy-action-cta" style="background: #e2e8f0; color: #94a3b8; cursor: not-allowed;" disabled>
                                                    <span>Habis</span>
                                                </button>
                                            <?php endif; ?>
                                        <?php elseif ($p_type === 'both'): ?>
                                            <?php if ($tab_filter === 'toko' || $tab_filter === 'sale'): ?>
                                                <?php if ($stk_sale > 0): ?>
                                                    <a href="checkout.php?product_id=<?php echo $prod['id']; ?>" class="btn-buy-action-cta" style="text-decoration: none;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                        </svg>
                                                        <span>Beli Sekarang</span>
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="btn-buy-action-cta" style="background: #e2e8f0; color: #94a3b8; cursor: not-allowed;" disabled>
                                                        <span>Habis</span>
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif ($tab_filter === 'rental'): ?>
                                                <?php if ($stk_rental > 0): ?>
                                                    <button type="button" class="btn-rent-action-cta" onclick="openSewaModal(<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>)">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span>Sewa Sekarang</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-rent-action-cta" style="background: #e2e8f0; color: #94a3b8; cursor: not-allowed;" disabled>
                                                        <span>Habis</span>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Default Semua Layanan: 3 Buttons in 1 balanced horizontal row -->
                                                <?php if ($stk_rental > 0): ?>
                                                    <button type="button" class="btn-rent-action-cta" onclick="openSewaModal(<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>)">
                                                        <span>Sewa Sekarang</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-rent-action-cta" style="background: #e2e8f0; color: #94a3b8; cursor: not-allowed;" disabled>
                                                        <span>Sewa Habis</span>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($stk_sale > 0): ?>
                                                    <a href="checkout.php?product_id=<?php echo $prod['id']; ?>" class="btn-buy-action-cta" style="text-decoration: none;">
                                                        <span>Beli Sekarang</span>
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="btn-buy-action-cta" style="background: #e2e8f0; color: #94a3b8; cursor: not-allowed;" disabled>
                                                        <span>Beli Habis</span>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($stk_rental > 0): ?>
                                                <button type="button" class="btn-rent-action-cta" onclick="openSewaModal(<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <span>Sewa Sekarang</span>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn-rent-action-cta" style="background: #e2e8f0; color: #94a3b8; cursor: not-allowed;" disabled>
                                                    <span>Habis</span>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; padding: 48px 20px; text-align: center; color: var(--text-muted); background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" style="margin: 0 auto 10px auto; color: var(--text-subtle);">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h4 style="font-size: 16px; font-weight: 800; color: var(--text-main);">Tidak ada perlengkapan ditemukan</h4>
                            <p style="font-size: 13px; margin-top: 4px;">Coba gunakan kata kunci lain atau klik tombol Reset Filter.</p>
                        </div>
                    <?php endif; ?>

                    <div id="liveSearchNoResults" style="display: none; grid-column: 1 / -1; padding: 48px 20px; text-align: center; color: var(--text-muted); background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" style="margin: 0 auto 10px auto; color: var(--text-subtle);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <h4 style="font-size: 16px; font-weight: 800; color: var(--text-main);">Tidak ada perlengkapan yang cocok</h4>
                        <p style="font-size: 13px; margin-top: 4px;">Kata kunci tidak ditemukan dalam katalog yang sedang ditampilkan.</p>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <!-- ==========================================================================
         MODAL BOOKING SEWA ALAT
         ========================================================================== -->
    <div class="modal-overlay" id="modalSewaOverlay" role="dialog" aria-modal="true" style="display: none;">
        <div class="modal-dialog">
            <div class="modal-header-bar">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
                    <h3 style="margin: 0; font-size: 15.5px; font-weight: 800;">Formulir Pemesanan Sewa</h3>
                </div>
                <button type="button" class="btn-modal-close-icon" onclick="closeSewaModal()" aria-label="Tutup">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form action="sewa_aksi.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" id="modal_product_id" value="">
                <input type="hidden" id="modal_price_per_day" value="0">

                <div class="modal-body-scroll">
                    <!-- Preview Card Info -->
                    <div class="modal-prod-highlight">
                        <img src="../assets/logo.png" alt="Preview" id="modal_img_preview" class="modal-prod-img" width="56" height="56" style="width: 56px; min-width: 56px; max-width: 56px; height: 56px; object-fit: cover; border-radius: 8px; flex-shrink: 0;" onerror="this.src='https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=80&auto=format&fit=crop&q=60'">
                        <div style="min-width: 0; flex: 1;">
                            <div style="font-weight: 800; color: var(--text-main); font-size: 14.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="modal_product_name">Nama Produk</div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 3px;">
                                <span style="font-size: 12.5px; color: var(--forest-800); font-weight: 800;" id="modal_price_display">Rp 0 / hari</span>
                                <span style="font-size: 11px; background: #ecfdf5; color: #065f46; padding: 2px 6px; border-radius: 4px; font-weight: 700; border: 1px solid #a7f3d0;" id="modal_sewa_stock_badge">Stok Siap</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-2col">
                        <div class="form-field-group">
                            <label for="modal_rental_date">Tanggal Ambil Sewa *</label>
                            <input type="date" name="rental_date" id="modal_rental_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" onchange="recalculateTotal()">
                        </div>
                        <div class="form-field-group">
                            <label for="modal_return_date">Tanggal Kembali *</label>
                            <input type="date" name="return_date" id="modal_return_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" onchange="recalculateTotal()">
                        </div>
                    </div>

                    <div class="form-grid-2col">
                        <div class="form-field-group">
                            <label for="modal_quantity">Jumlah Unit *</label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <button type="button" onclick="adjustSewaQty(-1)" style="width: 36px; height: 36px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #f8fafc; font-weight: 800; font-size: 16px; cursor: pointer;">−</button>
                                <input type="number" name="quantity" id="modal_quantity" value="1" min="1" max="10" required style="text-align: center; font-weight: 700; padding: 6px;" onchange="recalculateTotal()" onkeyup="recalculateTotal()">
                                <button type="button" onclick="adjustSewaQty(1)" style="width: 36px; height: 36px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #f8fafc; font-weight: 800; font-size: 16px; cursor: pointer;">+</button>
                            </div>
                        </div>
                        <div class="form-field-group">
                            <label for="modal_guarantee">Jenis Dokumen Jaminan *</label>
                            <select name="guarantee_type" id="modal_guarantee" required>
                                <option value="KTP Asli">KTP Asli</option>
                                <option value="SIM A / C Asli">SIM A / C Asli</option>
                                <option value="KTM (Kartu Mahasiswa)">KTM (Kartu Mahasiswa)</option>
                                <option value="Paspor Asli">Paspor Asli</option>
                            </select>
                        </div>
                    </div>

                    <!-- Data Informasi Pribadi Penyewa (Teleport / Auto-fill) -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 14px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="font-size: 12.5px; font-weight: 700; color: var(--forest-950);">
                                Data Kontak & Alamat Penyewa
                            </div>
                            <a href="profile.php?wajib_isi=1&redirect_to=dashboard.php" style="font-size: 11px; color: var(--forest-700); font-weight: 700; text-decoration: none;">
                                Ubah di Profil →
                            </a>
                        </div>

                        <div class="form-grid-2col" style="margin-bottom: 8px;">
                            <div class="form-field-group" style="margin-bottom: 0;">
                                <label for="modal_phone" style="font-size: 11px;">No. WhatsApp *</label>
                                <input type="tel" name="phone" id="modal_phone" required placeholder="081234567890" value="<?php echo htmlspecialchars($user_profile_data['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-field-group" style="margin-bottom: 0;">
                                <label for="modal_name_static" style="font-size: 11px;">Nama Akun</label>
                                <input type="text" id="modal_name_static" readonly disabled value="<?php echo htmlspecialchars($user_profile_data['name'] ?? $_SESSION['nama']); ?>" style="background: #f1f5f9; color: #64748b;">
                            </div>
                        </div>

                        <div class="form-field-group" style="margin-bottom: 0; margin-top: 8px;">
                            <label for="modal_address" style="font-size: 11px;">Alamat Domisili *</label>
                            <textarea name="address" id="modal_address" rows="2" required placeholder="Alamat tinggal sesuai KTP / domisili..."><?php echo htmlspecialchars($user_profile_data['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Upload Foto Dokumen Jaminan Identitas (KTP/SIM/KTM) -->
                    <div class="form-field-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label for="modal_guarantee_photo" style="margin-bottom: 0;">Foto Identitas Jaminan (KTP / SIM / KTM)</label>
                            <span style="font-size: 10px; font-weight: 700; color: #059669; background: #ecfdf5; padding: 1px 7px; border-radius: 4px;">Opsional</span>
                        </div>
                        <div style="border: 1.5px dashed #cbd5e1; border-radius: var(--radius-md); padding: 10px 14px; text-align: center; background: #fafdfb; position: relative; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('modal_guarantee_photo').click()">
                            <input type="file" name="guarantee_photo" id="modal_guarantee_photo" accept="image/*,application/pdf" style="display: none;" onchange="previewGuaranteePhoto(this)">
                            
                            <div id="guarantee_prompt_view">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color: var(--forest-700); margin: 0 auto 3px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <div style="font-size: 11.5px; font-weight: 700; color: var(--forest-950);">Klik jika ingin unggah foto KTP/SIM sekarang</div>
                                <div style="font-size: 10px; color: var(--text-muted); margin-top: 1px;">Bisa juga bawa & tunjukkan kartu fisik asli langsung saat ambil alat di basecamp.</div>
                            </div>

                            <div id="guarantee_selected_view" style="display: none; align-items: center; justify-content: center; gap: 10px;">
                                <img id="guarantee_img_thumb" src="" alt="Thumbnail" style="width: 44px; height: 32px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                <div style="text-align: left;">
                                    <div id="guarantee_file_title" style="font-size: 12px; font-weight: 700; color: var(--forest-950); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Dokumen Terpilih</div>
                                    <div style="font-size: 10.5px; color: var(--emerald-600); font-weight: 600;">Foto siap dilampirkan</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Metode Pembayaran & Panduan Santai -->
                    <div class="form-field-group">
                        <label for="modal_payment_method">Metode Pembayaran *</label>
                        <select name="payment_method" id="modal_payment_method" required onchange="updateRentalPaymentInstruction(this.value)">
                            <option value="Transfer Bank BCA">Transfer Bank BCA (Virtual Account / Rekening)</option>
                            <option value="Transfer Bank Mandiri">Transfer Bank Mandiri</option>
                            <option value="Transfer Bank BRI">Transfer Bank BRI</option>
                            <option value="QRIS / GoPay / OVO">QRIS Instan (GoPay, OVO, ShopeePay, DANA, BCA)</option>
                            <option value="Bayar di Basecamp (Cash / Debit)" selected>Bayar di Basecamp (Cash / Debit saat Ambil Alat)</option>
                        </select>

                        <!-- Box Panduan Pembayaran Dinamis & Santai -->
                        <div id="modal_pay_instruction_box" style="margin-top: 8px; border-radius: var(--radius-md); padding: 10px 12px; font-size: 11.5px; line-height: 1.4; transition: all 0.2s;">
                            <!-- Konten dinamis diatur oleh JS -->
                        </div>
                    </div>

                    <div class="form-field-group">
                        <label for="modal_notes">Catatan Tambahan (Opsional)</label>
                        <textarea name="notes" id="modal_notes" rows="2" placeholder="Contoh: Estimasi ambil jam 09.00 pagi, tujuan Gunung Merbabu..."></textarea>
                    </div>

                    <!-- Diskon Otomatis Banner -->
                    <div id="modalVoucherFeedback" style="display: none; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 10px 14px; margin-bottom: 14px; font-size: 11.5px; color: #065f46;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <strong id="modalVoucherFeedbackText">Diskon otomatis diterapkan!</strong>
                        </div>
                    </div>

                    <!-- Price Summary Card -->
                    <div class="price-calc-summary-card">
                        <div class="calc-row">
                            <span>Durasi Sewa:</span>
                            <span id="modal_duration_text" style="font-weight: 700;">1 Hari (24 Jam)</span>
                        </div>
                        <div class="calc-row">
                            <span>Tarif / Unit:</span>
                            <span id="modal_unit_rate_text" style="font-weight: 700;">Rp 0</span>
                        </div>
                        <div class="calc-row">
                            <span>Subtotal Sewa:</span>
                            <span id="modal_subtotal_text" style="font-weight: 700;">Rp 0</span>
                        </div>
                        <div class="calc-row" id="modal_discount_row" style="display: none; color: #059669; font-weight: 700;">
                            <span id="modal_discount_label">Potongan Diskon:</span>
                            <span id="modal_discount_text">-Rp 0</span>
                        </div>
                        <div class="calc-row total-row">
                            <span>Estimasi Total Biaya:</span>
                            <span id="modal_total_text" style="color: var(--forest-900);">Rp 0</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer-bar">
                    <button type="button" class="btn-modal-cancel" onclick="closeSewaModal()">Batal</button>
                    <button type="submit" name="sewa_produk" class="btn-modal-submit">Sewa Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- (Sistem Pembelian Langsung: Beli Sekarang langsung mengarah ke checkout.php) -->

    <!-- ==========================================================================
         MODAL PROMPT LENGKAPI PROFIL (JIKA DATA BELUM LENGKAP)
         ========================================================================== -->
    <div class="modal-overlay" id="modalTeleportProfileOverlay" role="dialog" aria-modal="true" style="display: none;">
        <div class="modal-dialog" style="max-width: 460px; text-align: center; padding: 28px 24px;">
            <div style="width: 54px; height: 54px; margin: 0 auto 16px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #a7f3d0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--forest-800);">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 19px; font-weight: 800; color: var(--forest-950); margin-bottom: 8px;">
                Lengkapi Profil Anda Terlebih Dahulu
            </h3>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 22px;">
                Sebelum melakukan pemesanan sewa perlengkapan outdoor, Anda diwajibkan melengkapi <strong>Nomor WhatsApp</strong> dan <strong>Alamat Lengkap Domisili</strong> untuk verifikasi jaminan & serah terima alat.
            </p>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="profile.php?wajib_isi=1&redirect_to=dashboard.php" class="btn-modal-submit" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px; font-size: 13.5px; background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-900) 100%);">
                    <span>Lengkapi di Profil Sekarang →</span>
                </a>
                <button type="button" class="btn-modal-cancel" onclick="proceedToModalSewaDirectly()" style="width: 100%; border: 1.5px solid var(--border-color); font-size: 12.5px; padding: 10px;">
                    Isi Langsung di Formulir Pemesanan
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Interactivity -->
    <script>
        let currentPricePerDay = 0;
        let maxStockAvailable = 1;
        let pendingProductToRent = null;
        const isUserProfileComplete = <?php echo ($is_profile_complete) ? 'true' : 'false'; ?>;

        // Live Search Filter for User Catalog
        function liveFilterCatalog(query) {
            const q = query.trim().toLowerCase();
            const cards = document.querySelectorAll('.products-card-grid .gear-card');
            const clearBtn = document.getElementById('btnSearchClear');
            const noResults = document.getElementById('liveSearchNoResults');
            const countHeader = document.getElementById('catalogCountHeader');

            if (clearBtn) {
                clearBtn.style.display = q !== '' ? 'inline-flex' : 'none';
            }

            let visibleCount = 0;
            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const cat = card.getAttribute('data-cat') || '';
                const desc = card.getAttribute('data-desc') || '';

                if (q === '' || title.includes(q) || cat.includes(q) || desc.includes(q)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (countHeader) {
                countHeader.innerText = visibleCount;
            }

            if (noResults) {
                noResults.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
            }
        }

        function clearCatalogSearch() {
            const searchInput = document.getElementById('mainSearchInput');
            if (searchInput) {
                searchInput.value = '';
                liveFilterCatalog('');
                searchInput.focus();
            }
        }

        // Preview Guarantee Photo in Modal
        function previewGuaranteePhoto(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const promptView = document.getElementById('guarantee_prompt_view');
                    const selectedView = document.getElementById('guarantee_selected_view');
                    const imgThumb = document.getElementById('guarantee_img_thumb');
                    const titleLbl = document.getElementById('guarantee_file_title');

                    if (imgThumb) imgThumb.src = e.target.result;
                    if (titleLbl) titleLbl.innerText = file.name;
                    if (promptView) promptView.style.display = 'none';
                    if (selectedView) selectedView.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        }

        function openSewaModal(product) {
            pendingProductToRent = product;
            if (!isUserProfileComplete) {
                document.getElementById('modalTeleportProfileOverlay').classList.add('active');
                document.body.style.overflow = 'hidden';
                return;
            }
            showActualSewaModal(product);
        }

        function proceedToModalSewaDirectly() {
            document.getElementById('modalTeleportProfileOverlay').classList.remove('active');
            if (pendingProductToRent) {
                showActualSewaModal(pendingProductToRent);
            }
        }

        let currentProductId = 0;
        let currentProductCategory = '';

        function showActualSewaModal(product) {
            const rawPrice = parseFloat(product.price) || 0;
            const discountPercent = parseFloat(product.discount_percent) || 0;
            const discountedPrice = discountPercent > 0 ? Math.round(rawPrice * (1 - (discountPercent / 100))) : rawPrice;
            currentPricePerDay = discountedPrice;
            maxStockAvailable = parseInt(product.stock) || 1;
            currentProductId = product.id;
            currentProductCategory = product.category || '';

            document.getElementById('modal_product_id').value = product.id;
            document.getElementById('modal_product_name').innerText = product.name;
            document.getElementById('modal_price_per_day').value = currentPricePerDay;
            if (discountPercent > 0) {
                document.getElementById('modal_price_display').innerHTML = '<span style="text-decoration:line-through; color:#94a3b8; font-size:12px; margin-right:4px;">Rp ' + Number(rawPrice).toLocaleString('id-ID') + '</span> Rp ' + Number(currentPricePerDay).toLocaleString('id-ID') + ' / hari <span style="background:#fee2e2; color:#b91c1c; font-size:11px; font-weight:700; padding:1px 6px; border-radius:4px;">-' + discountPercent + '%</span>';
            } else {
                document.getElementById('modal_price_display').innerText = 'Rp ' + Number(currentPricePerDay).toLocaleString('id-ID') + ' / hari';
            }
            document.getElementById('modal_unit_rate_text').innerText = 'Rp ' + Number(currentPricePerDay).toLocaleString('id-ID');

            // Reset discount feedback
            document.getElementById('modalVoucherFeedback').style.display = 'none';
            document.getElementById('modal_discount_row').style.display = 'none';

            const qtyInput = document.getElementById('modal_quantity');
            qtyInput.max = maxStockAvailable;
            qtyInput.value = 1;

            const imgPreview = document.getElementById('modal_img_preview');
            if (product.image && product.image.trim() !== '') {
                imgPreview.src = '../uploads/products/' + product.image;
            } else {
                imgPreview.src = '../assets/logo.png';
            }

            recalculateTotal();
            if (typeof updateRentalPaymentInstruction === 'function') {
                updateRentalPaymentInstruction(document.getElementById('modal_payment_method').value);
            }

            document.getElementById('modalSewaOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSewaModal() {
            document.getElementById('modalSewaOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function adjustSewaQty(delta) {
            const input = document.getElementById('modal_quantity');
            let val = parseInt(input.value) || 1;
            val += delta;
            if (val < 1) val = 1;
            if (val > maxStockAvailable) val = maxStockAvailable;
            input.value = val;
            recalculateTotal();
        }

        function recalculateTotal() {
            const startInput = document.getElementById('modal_rental_date').value;
            const returnInput = document.getElementById('modal_return_date').value;
            const input = document.getElementById('modal_quantity');
            let qtyInput = parseInt(input.value) || 1;
            if (qtyInput < 1) qtyInput = 1;
            if (qtyInput > maxStockAvailable) {
                qtyInput = maxStockAvailable;
                input.value = qtyInput;
            }

            if (!startInput || !returnInput) return;

            const startDate = new Date(startInput);
            const returnDate = new Date(returnInput);

            let diffDays = Math.ceil((returnDate - startDate) / (1000 * 60 * 60 * 24));
            if (diffDays <= 0) diffDays = 1;

            const subtotal = currentPricePerDay * diffDays * qtyInput;

            document.getElementById('modal_duration_text').innerText = diffDays + ' Hari (' + (diffDays * 24) + ' Jam)';
            document.getElementById('modal_subtotal_text').innerText = 'Rp ' + Number(subtotal).toLocaleString('id-ID');

            // Cek AJAX diskon otomatis
            const formData = new FormData();
            formData.append('type', 'rental');
            formData.append('subtotal', subtotal);
            formData.append('rental_days', diffDays);
            formData.append('product_ids', currentProductId);
            formData.append('category_names', currentProductCategory);

            fetch('../ajax_voucher.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const feedbackBox = document.getElementById('modalVoucherFeedback');
                const feedbackText = document.getElementById('modalVoucherFeedbackText');
                const discountRow = document.getElementById('modal_discount_row');
                const discountLabel = document.getElementById('modal_discount_label');
                const discountText = document.getElementById('modal_discount_text');
                const totalText = document.getElementById('modal_total_text');

                if (data.success && data.discount_amount > 0) {
                    discountRow.style.display = 'flex';
                    discountLabel.innerText = 'Diskon Otomatis:';
                    discountText.innerText = '-' + data.discount_amount_formatted;
                    totalText.innerText = data.total_after_discount_formatted;

                    if (feedbackBox) {
                        feedbackBox.style.display = 'block';
                        feedbackBox.style.background = '#ecfdf5';
                        feedbackBox.style.color = '#065f46';
                        feedbackBox.style.border = '1px solid #a7f3d0';
                    }
                    if (feedbackText) feedbackText.innerText = data.message;
                } else {
                    discountRow.style.display = 'none';
                    totalText.innerText = 'Rp ' + Number(subtotal).toLocaleString('id-ID');
                    if (feedbackBox) feedbackBox.style.display = 'none';
                }
            })
            .catch(err => {
                document.getElementById('modal_total_text').innerText = 'Rp ' + Number(subtotal).toLocaleString('id-ID');
            });
        }

        // BUY / SHOP DIRECT HANDLERS
        // BUY / SHOP DIRECT HANDLERS
        function openBeliModal(product) {
            const pId = (typeof product === 'object') ? (product.id || 0) : product;
            if (pId > 0) {
                window.location.href = 'checkout.php?product_id=' + pId;
            }
        }

        // Carousel Scroll Control
        function scrollCategoryCarousel(offset) {
            const container = document.getElementById('catCarouselContainer');
            if (container) {
                container.scrollBy({ left: offset, behavior: 'smooth' });
            }
        }

        // Modal backdrop click
        window.addEventListener('click', function(event) {
            const modalSewa = document.getElementById('modalSewaOverlay');
            if (event.target === modalSewa) closeSewaModal();
        });

        // Keyboard Shortcuts (Escape to close, '/' to focus search)
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSewaModal();
                const sidebar = document.getElementById('appSidebar');
                const backdrop = document.getElementById('sidebarBackdrop');
                if (sidebar && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    backdrop.classList.remove('active');
                }
            }

            const searchInput = document.getElementById('mainSearchInput');
            if (e.key === '/' && document.activeElement !== searchInput && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });

        // Global Copy Helper with Gentle Toast
        function copyTextToClipboard(text, label) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopyToast(label || 'Nomor berhasil disalin!');
                }).catch(() => {
                    fallbackCopyText(text, label);
                });
            } else {
                fallbackCopyText(text, label);
            }
        }

        function fallbackCopyText(text, label) {
            const temp = document.createElement('textarea');
            temp.value = text;
            temp.style.position = 'fixed';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.focus();
            temp.select();
            try {
                document.execCommand('copy');
                showCopyToast(label || 'Nomor berhasil disalin!');
            } catch (err) {
                alert('Tersalin: ' + text);
            }
            document.body.removeChild(temp);
        }

        function showCopyToast(msg) {
            let t = document.getElementById('global_copy_toast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'global_copy_toast';
                t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#041f14;color:#f5b800;padding:10px 18px;border-radius:9999px;font-size:12.5px;font-weight:700;box-shadow:0 10px 25px rgba(0,0,0,0.35);z-index:999999;transition:all 0.25s ease;display:none;align-items:center;gap:8px;border:1px solid rgba(245,184,0,0.3);';
                document.body.appendChild(t);
            }
            t.innerHTML = '<span>✓</span> ' + msg;
            t.style.display = 'inline-flex';
            t.style.opacity = '1';
            clearTimeout(window._copyToastTimer);
            window._copyToastTimer = setTimeout(() => {
                t.style.opacity = '0';
                setTimeout(() => { t.style.display = 'none'; }, 250);
            }, 2200);
        }

        // Petunjuk Pembayaran Real-Time saat Pemesanan Sewa
        function updateRentalPaymentInstruction(method) {
            const box = document.getElementById('modal_pay_instruction_box');
            if (!box) return;

            if (method.includes('BCA')) {
                box.style.background = '#f0fdf4';
                box.style.border = '1px solid #bbf7d0';
                box.style.color = '#14532d';
                box.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong>BCA: 7310-0987-6543</strong><br>
                            <span style="font-size:10px; color:#15803d;">a/n PT DANATRAIL INDONESIA</span>
                        </div>
                        <button type="button" onclick="copyTextToClipboard('731009876543', 'No. Rekening BCA disalin!')" style="display:inline-flex; align-items:center; gap:4px; padding:4px 9px; background:#10b981; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <span>Salin</span>
                        </button>
                    </div>
                    <div style="font-size:10.5px; color:#166534; margin-top:5px;"><strong>Petunjuk:</strong> Anda dapat transfer setelah pesanan dikonfirmasi & struk bisa diunggah santai di riwayat sewa.</div>
                `;
            } else if (method.includes('Mandiri')) {
                box.style.background = '#f0fdf4';
                box.style.border = '1px solid #bbf7d0';
                box.style.color = '#14532d';
                box.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong>Mandiri: 137-00-1928374-1</strong><br>
                            <span style="font-size:10px; color:#15803d;">a/n PT DANATRAIL INDONESIA</span>
                        </div>
                        <button type="button" onclick="copyTextToClipboard('1370019283741', 'No. Rekening Mandiri disalin!')" style="display:inline-flex; align-items:center; gap:4px; padding:4px 9px; background:#10b981; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <span>Salin</span>
                        </button>
                    </div>
                    <div style="font-size:10.5px; color:#166534; margin-top:5px;"><strong>Petunjuk:</strong> Transfer via Livin' by Mandiri atau ATM, bukti bayar bisa diunggah kapan saja.</div>
                `;
            } else if (method.includes('BRI')) {
                box.style.background = '#f0fdf4';
                box.style.border = '1px solid #bbf7d0';
                box.style.color = '#14532d';
                box.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong>BRI: 0123-01-098765-50-8</strong><br>
                            <span style="font-size:10px; color:#15803d;">a/n PT DANATRAIL INDONESIA</span>
                        </div>
                        <button type="button" onclick="copyTextToClipboard('012301098765508', 'No. Rekening BRI disalin!')" style="display:inline-flex; align-items:center; gap:4px; padding:4px 9px; background:#10b981; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                            <span>Salin</span>
                        </button>
                    </div>
                    <div style="font-size:10.5px; color:#166534; margin-top:5px;"><strong>Petunjuk:</strong> Transfer via BRImo atau ATM BRI dengan aman dan nyaman.</div>
                `;
            } else if (method.includes('QRIS')) {
                box.style.background = '#eff6ff';
                box.style.border = '1px solid #bfdbfe';
                box.style.color = '#1e3a8a';
                box.innerHTML = `
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="background:#2563eb; color:#fff; font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px;">QRIS</span>
                        <strong>Scan QRIS Bebas Biaya Admin</strong>
                    </div>
                    <div style="font-size:10.5px; color:#1d4ed8; margin-top:4px;">Bisa di-scan dari aplikasi BCA, Mandiri, BRI, GoPay, OVO, Dana, atau ShopeePay. QR Code akan otomatis tampil setelah pemesanan.</div>
                `;
            } else if (method.includes('Basecamp')) {
                box.style.background = '#f0fdf4';
                box.style.border = '1.5px solid #86efac';
                box.style.color = '#065f46';
                box.innerHTML = `
                    <div style="display:flex; align-items:center; gap:6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:#047857;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21l7-14 7 14M9 21l3-6 3 6M12 7V3" /></svg>
                        <strong style="color:#047857;">Bayar Tunai / Debit di Loket Basecamp</strong>
                    </div>
                    <div style="font-size:10.5px; color:#065f46; margin-top:3px;">
                        Anda <strong>tidak perlu transfer saat ini</strong>. Silakan datang langsung ke Basecamp DANATRAIL saat tanggal sewa tiba dan selesaikan di kasir loket saat serah terima barang.
                    </div>
                `;
            }
        }
    </script>
</body>

</html>