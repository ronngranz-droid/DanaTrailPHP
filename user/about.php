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

// Ambil data statistik ringkas untuk social proof / brand counters
$q_prod = mysqli_query($conn, "SELECT COUNT(*) as c_prod, SUM(stock) as c_stock FROM products");
$d_prod = $q_prod ? mysqli_fetch_assoc($q_prod) : [];
$total_katalog = (int)($d_prod['c_prod'] ?? 0);
$total_unit    = (int)($d_prod['c_stock'] ?? 0);

$q_rental_done = mysqli_query($conn, "SELECT COUNT(*) as c_done FROM rentals WHERE status IN ('Disewa', 'Selesai')");
$total_sewa    = (int)($q_rental_done ? mysqli_fetch_assoc($q_rental_done)['c_done'] : 0);

// Ambil list kategori untuk showcase
$q_cats = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
$categories_list = [];
if ($q_cats) {
    while ($c = mysqli_fetch_assoc($q_cats)) {
        $categories_list[] = $c;
    }
}

// Ambil 8 produk unggulan / populer untuk showcase ala e-commerce (Shopee-style)
$q_featured = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC LIMIT 8");

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
    <title>Beranda — DANATRAIL Outdoor Rental</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ==========================================================================
           DANATRAIL USER DESIGN SYSTEM (BRAND LANDING & E-COMMERCE SHOWCASE)
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
            --shadow-card: 0 4px 14px rgba(7, 53, 34, 0.07);
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
            letter-spacing: -0.015em;
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
            padding: 24px 32px 120px 32px;
            max-width: 1280px;
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
           HERO SECTION: BRAND INTRODUCTION & VALUE CALLOUT
           ========================================================================== */
        .hero-brand-card {
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 60%, var(--forest-800) 100%);
            border-radius: var(--radius-xl);
            padding: 36px 40px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(4, 31, 20, 0.2);
            margin-bottom: 28px;
        }

        .hero-brand-card::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(245, 184, 0, 0.14) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-pill-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gold-500);
            background: rgba(245, 184, 0, 0.12);
            border: 1px solid rgba(245, 184, 0, 0.3);
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            margin-bottom: 12px;
        }

        .hero-brand-card h1 {
            font-size: 32px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
            max-width: 780px;
        }

        .hero-brand-card h1 span {
            color: var(--gold-500);
        }

        .hero-brand-desc {
            color: #d1e2d7;
            font-size: 14.5px;
            margin-top: 12px;
            max-width: 700px;
            line-height: 1.6;
        }

        .hero-cta-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .btn-hero-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: var(--radius-md);
            background: var(--gold-500);
            color: var(--forest-950);
            font-weight: 800;
            font-size: 13.5px;
            text-decoration: none;
            transition: all var(--transition-fast);
            box-shadow: 0 4px 12px rgba(245, 184, 0, 0.3);
        }

        .btn-hero-primary:hover {
            background: #ffc414;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(245, 184, 0, 0.4);
        }

        .btn-hero-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.18);
            font-weight: 700;
            font-size: 13.5px;
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .btn-hero-secondary:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* BRAND METRICS STRIP */
        .brand-metrics-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .brand-metric-item {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            box-shadow: var(--shadow-subtle);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .metric-icon-box {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: #ecfdf5;
            border: 1px solid var(--emerald-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--emerald-600);
            flex-shrink: 0;
        }

        .metric-icon-box.gold {
            background: #fef8e6;
            border-color: #fde68a;
            color: var(--gold-600);
        }

        .metric-icon-box.forest {
            background: #eef5f1;
            border-color: #c4dcd0;
            color: var(--forest-800);
        }

        .metric-val-title {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
        }

        .metric-val-sub {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 2px;
            font-weight: 600;
        }

        /* ==========================================================================
           SHOPEE / E-COMMERCE STYLE FEATURED PRODUCTS SHOWCASE
           ========================================================================== */
        .featured-products-section {
            margin-bottom: 34px;
        }

        .section-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-header-title h2 {
            font-size: 21px;
            font-weight: 800;
            color: var(--forest-950);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header-title p {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .flash-pill-badge {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #ffffff;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            letter-spacing: 0.5px;
        }

        .link-see-all-catalog {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--forest-800);
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .link-see-all-catalog:hover {
            color: var(--forest-950);
            transform: translateX(3px);
            text-decoration: underline;
        }

        /* PRODUCT CARDS GRID (E-COMMERCE / SHOPEE SPEC) */
        .products-showcase-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .ecom-product-card {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform var(--transition-normal), box-shadow var(--transition-normal), border-color var(--transition-normal);
            position: relative;
        }

        .ecom-product-card:hover {
            transform: translateY(-3px);
            border-color: #cbdad0;
            box-shadow: var(--shadow-card);
        }

        .ecom-media-wrap {
            height: 165px;
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .ecom-real-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .ecom-product-card:hover .ecom-real-img {
            transform: scale(1.05);
        }

        .ecom-fallback-icon {
            width: 44px;
            height: 44px;
            color: var(--gold-500);
            opacity: 0.75;
        }

        .ecom-badge-cat {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(4, 31, 20, 0.85);
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            z-index: 2;
        }

        .ecom-badge-stock {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: var(--radius-pill);
            z-index: 2;
        }

        .ecom-stock-ready {
            background: rgba(16, 185, 129, 0.95);
            color: #ffffff;
        }

        .ecom-stock-low {
            background: rgba(245, 184, 0, 0.95);
            color: var(--forest-950);
        }

        .ecom-stock-out {
            background: rgba(239, 68, 68, 0.9);
            color: #ffffff;
        }

        .ecom-card-body {
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .ecom-product-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ecom-desc-snip {
            font-size: 11.5px;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }

        .ecom-rating-row {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .star-icon {
            color: #f59e0b;
        }

        .ecom-price-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px solid var(--border-light);
        }

        .ecom-price-tag {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: var(--forest-900);
        }

        .ecom-price-tag span {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 10.5px;
            color: var(--text-muted);
            font-weight: normal;
        }

        .btn-quick-rent {
            background: var(--forest-900);
            color: var(--gold-500);
            border: none;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all var(--transition-fast);
        }

        .btn-quick-rent:hover {
            background: var(--forest-800);
            color: #ffffff;
        }

        /* 4 VALUE PROPOSITIONS */
        .values-grid-4col {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .value-card-box {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-subtle);
            transition: all var(--transition-fast);
            display: flex;
            flex-direction: column;
        }

        .value-card-box:hover {
            transform: translateY(-2px);
            border-color: #ccd7d0;
            box-shadow: var(--shadow-card);
        }

        .value-icon-circle {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: #eef4f0;
            border: 1px solid #d5e3da;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--forest-900);
            margin-bottom: 14px;
        }

        .value-card-box h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .value-card-box p {
            font-size: 12.5px;
            color: var(--text-body);
            line-height: 1.55;
        }

        /* CATEGORY SHOWCASE GRID */
        .category-showcase-section {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-subtle);
            margin-bottom: 30px;
        }

        .category-cards-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .cat-explore-card {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px;
            text-decoration: none;
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 100px;
            transition: all var(--transition-fast);
        }

        .cat-explore-card:hover {
            background: #ffffff;
            border-color: var(--forest-700);
            transform: translateY(-2px);
            box-shadow: var(--shadow-subtle);
        }

        .cat-explore-name {
            font-weight: 700;
            font-size: 13.5px;
            color: var(--forest-950);
        }

        .cat-explore-desc {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.35;
        }

        .cat-explore-action {
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--forest-700);
        }

        .cat-explore-card:hover .cat-explore-action {
            color: var(--forest-900);
        }

        /* RENTAL WORKFLOW (4 STEPS) */
        .workflow-guide-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-subtle);
            margin-bottom: 30px;
        }

        .workflow-steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .workflow-step-box {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
        }

        .step-badge-num {
            width: 26px;
            height: 26px;
            border-radius: var(--radius-pill);
            background: var(--forest-900);
            color: var(--gold-500);
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .workflow-step-box h4 {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-main);
        }

        .workflow-step-box p {
            font-size: 12px;
            color: var(--text-body);
            line-height: 1.5;
        }

        /* FAQS & RENTAL RULES */
        .rules-faq-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .rules-box-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow-subtle);
        }

        .rules-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 12px;
        }

        .rules-list-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12.5px;
            color: var(--text-body);
            line-height: 1.45;
        }

        .rules-list-item svg {
            width: 16px;
            height: 16px;
            color: var(--emerald-600);
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* BOTTOM CALL TO ACTION */
        .bottom-cta-banner {
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 100%);
            border-radius: var(--radius-lg);
            padding: 26px 30px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 16px rgba(4, 31, 20, 0.15);
            flex-wrap: wrap;
        }

        .cta-banner-text h3 {
            font-size: 18px;
            font-weight: 800;
            color: var(--gold-500);
        }

        .cta-banner-text p {
            font-size: 13px;
            color: #cbdad0;
            margin-top: 4px;
            max-width: 580px;
        }

        .btn-cta-explore {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gold-500);
            color: var(--forest-950);
            font-size: 13.5px;
            font-weight: 800;
            padding: 10px 22px;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all var(--transition-fast);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-cta-explore:hover {
            background: #ffc414;
            transform: translateY(-2px);
        }

        /* MODAL BOOKING DI HALAMAN BERANDA */
        .modal-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(4, 31, 20, 0.75) !important;
            -webkit-backdrop-filter: blur(6px) !important;
            backdrop-filter: blur(6px) !important;
            z-index: 99999 !important;
            display: none !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 16px !important;
            box-sizing: border-box !important;
        }

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

        .price-calc-summary-card {
            background: #f0f7f3;
            border: 1px solid #c7e2d3;
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
            border-top: 1px solid #c7e2d3;
            font-weight: 800;
            font-size: 14px;
            color: var(--forest-950);
        }

        .modal-footer-bar {
            padding: 12px 20px;
            background: var(--bg-canvas);
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal-cancel {
            background: #ffffff;
            color: var(--text-body);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-modal-submit {
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

        .btn-modal-submit:hover {
            background: var(--forest-800);
            color: #ffffff;
        }

        /* RESPONSIVE */
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
            .hero-brand-card {
                padding: 24px 20px;
            }
            .hero-brand-card h1 {
                font-size: 24px;
            }
            .rules-faq-grid {
                grid-template-columns: 1fr;
            }
            .products-showcase-grid {
                grid-template-columns: 1fr 1fr;
            }
            .form-grid-2col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .products-showcase-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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

        <!-- Hero Banner: Brand Introduction -->
        <section class="hero-brand-card">
            <div class="hero-pill-tag">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Partner Petualangan Terpercaya</span>
            </div>
            <h1>Jelajahi Puncak Impian Bersama <span>DANATRAIL</span></h1>
            <p class="hero-brand-desc">
                DANATRAIL hadir sebagai penyedia persewaan perlengkapan outdoor dan hiking premium. Kami memastikan setiap alat terawat, higienis, dan berstandar keamanan tinggi agar perjalanan alam bebas Anda lebih aman, nyaman, dan berkesan.
            </p>
            <div class="hero-cta-row">
                <a href="dashboard.php" class="btn-hero-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>Jelajahi Katalog Sewa</span>
                </a>
                <a href="#produk-unggulan" class="btn-hero-secondary">
                    <span>Lihat Produk Unggulan</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </a>
            </div>
        </section>

        <!-- Brand Metrics Strip -->
        <section class="brand-metrics-strip">
            <div class="brand-metric-item">
                <div class="metric-icon-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div>
                    <div class="metric-val-title"><?php echo $total_katalog; ?>+ Jenis</div>
                    <div class="metric-val-sub">Perlengkapan Outdoor</div>
                </div>
            </div>

            <div class="brand-metric-item">
                <div class="metric-icon-box gold">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="metric-val-title"><?php echo $total_unit; ?> Unit</div>
                    <div class="metric-val-sub">Stok Siap Ekspedisi</div>
                </div>
            </div>

            <div class="brand-metric-item">
                <div class="metric-icon-box forest">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="metric-val-title">08.00–21.00</div>
                    <div class="metric-val-sub">Buka Setiap Hari</div>
                </div>
            </div>

            <div class="brand-metric-item">
                <div class="metric-icon-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <div class="metric-val-title"><?php echo $total_sewa; ?>+ Trip</div>
                    <div class="metric-val-sub">Petualangan Terlayani</div>
                </div>
            </div>
        </section>

        <!-- ==========================================================================
             SHOPEE / E-COMMERCE STYLE FEATURED PRODUCTS SHOWCASE
             ========================================================================== -->
        <section class="featured-products-section" id="produk-unggulan">
            <div class="section-header-flex">
                <div class="section-header-title">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color: var(--gold-500);">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Rekomendasi Perlengkapan Populer</span>
                        <span class="flash-pill-badge">Favorit Pendaki</span>
                    </h2>
                    <p>Pilihan perlengkapan outdoor paling diminati untuk ekspedisi dan camping akhir pekan ini.</p>
                </div>
                <a href="dashboard.php" class="link-see-all-catalog">
                    <span>Lihat Semua di Katalog</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="products-showcase-grid">
                <?php if ($q_featured && mysqli_num_rows($q_featured) > 0): ?>
                    <?php while ($prod = mysqli_fetch_assoc($q_featured)): 
                        $stk = (int)($prod['stock'] ?? 0);
                    ?>
                        <div class="ecom-product-card">
                            <div class="ecom-media-wrap">
                                <?php if (!empty($prod['image']) && file_exists('../uploads/products/' . $prod['image'])): ?>
                                    <img src="../uploads/products/<?php echo htmlspecialchars($prod['image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="ecom-real-img" loading="lazy">
                                <?php else: ?>
                                    <svg class="ecom-fallback-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                <?php endif; ?>

                                <span class="ecom-badge-cat"><?php echo htmlspecialchars($prod['category']); ?></span>

                                <?php if ($stk > 3): ?>
                                    <span class="ecom-badge-stock ecom-stock-ready">Ready <?php echo $stk; ?></span>
                                <?php elseif ($stk > 0): ?>
                                    <span class="ecom-badge-stock ecom-stock-low">Sisa <?php echo $stk; ?></span>
                                <?php else: ?>
                                    <span class="ecom-badge-stock ecom-stock-out">Habis</span>
                                <?php endif; ?>
                            </div>

                            <div class="ecom-card-body">
                                <h3 class="ecom-product-title" title="<?php echo htmlspecialchars($prod['name']); ?>">
                                    <?php echo htmlspecialchars($prod['name']); ?>
                                </h3>
                                <p class="ecom-desc-snip">
                                    <?php echo htmlspecialchars($prod['description'] ?? 'Peralatan terawat dan siap pakai.'); ?>
                                </p>

                                <div class="ecom-rating-row">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 20 20" fill="#f59e0b" style="flex-shrink:0;">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                    <span><strong>4.9</strong> (Terverifikasi)</span>
                                </div>

                                <div class="ecom-price-row">
                                    <div class="ecom-price-tag">
                                        Rp <?php echo number_format($prod['price'], 0, ',', '.'); ?>
                                        <span>/ hari</span>
                                    </div>

                                    <?php if ($stk > 0): ?>
                                        <button type="button" class="btn-quick-rent" onclick="openSewaModal(<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <span>Sewa</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-quick-rent" style="background: #e2e8f0; color: #94a3b8; cursor: not-allowed;" disabled>
                                            <span>Habis</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; padding: 32px; text-align: center; color: var(--text-muted); background: #fff; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                        Belum ada data produk perlengkapan.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 4 Value Propositions -->
        <section>
            <div class="section-header-block">
                <h2>Mengapa Memilih DANATRAIL?</h2>
                <p>Standar layanan dan kualitas peralatan terbaik untuk kenyamanan pendakian Anda.</p>
            </div>

            <div class="values-grid-4col">
                <div class="value-card-box">
                    <div class="value-icon-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h3>Alat Steril & Siap Pakai</h3>
                    <p>Setiap tenda, carrier, dan sleeping bag melewati proses pencucian, pengeringan higienis, dan uji kelengkapan sebelum disewakan.</p>
                </div>

                <div class="value-card-box">
                    <div class="value-icon-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3>Tarif Transparan</h3>
                    <p>Sistem hitungan tarif per 24 jam dengan harga bersahabat bagi mahasiswa dan komunitas tanpa biaya tersembunyi.</p>
                </div>

                <div class="value-card-box">
                    <div class="value-icon-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                    </div>
                    <h3>Jaminan Fleksibel</h3>
                    <p>Cukup titipkan identitas asli yang masih berlaku seperti KTP, SIM, KTM, atau Paspor saat pengambilan alat.</p>
                </div>

                <div class="value-card-box">
                    <div class="value-icon-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h3>Konsultasi Gratis</h3>
                    <p>Bingung memilih kapasitas tenda atau ukuran carrier? Tim kami siap memberi saran alat yang tepat sesuai karakteristik gunung tujuan.</p>
                </div>
            </div>
        </section>

        <!-- Category Showcase -->
        <section class="category-showcase-section">
            <div class="section-header-block">
                <h2>Koleksi Kategori Perlengkapan</h2>
                <p>Temukan alat outdoor yang Anda butuhkan berdasarkan kategori pilihan.</p>
            </div>

            <div class="category-cards-wrapper">
                <?php foreach ($categories_list as $cat): 
                    $c_slug = $category_slug_map[$cat['name']] ?? strtolower(str_replace(' ', '-', $cat['name']));
                ?>
                    <a href="dashboard.php?kategori=<?php echo urlencode($cat['name']); ?>" class="cat-explore-card">
                        <div>
                            <div class="cat-explore-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                            <div class="cat-explore-desc"><?php echo htmlspecialchars($cat['description'] ?? 'Peralatan berkualitas.'); ?></div>
                        </div>
                        <div class="cat-explore-action">
                            <span>Lihat Katalog</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Rental Workflow Guide (4 Steps) -->
        <section class="workflow-guide-card" id="cara-sewa">
            <div class="section-header-block">
                <h2>Alur & Cara Menyewa di DANATRAIL</h2>
                <p>Hanya 4 langkah mudah untuk mempersiapkan perjalanan ekspedisi Anda.</p>
            </div>

            <div class="workflow-steps-grid">
                <div class="workflow-step-box">
                    <span class="step-badge-num">1</span>
                    <h4>Pilih Perlengkapan</h4>
                    <p>Pilih perlengkapan dari rekomendasi di atas atau buka menu <strong>Katalog Sewa</strong>, lalu klik tombol <em>Sewa</em>.</p>
                </div>

                <div class="workflow-step-box">
                    <span class="step-badge-num">2</span>
                    <h4>Isi Formulir Sewa</h4>
                    <p>Tentukan tanggal mulai sewa, durasi hari, jumlah unit, dan pilih dokumen identitas jaminan yang akan diserahkan.</p>
                </div>

                <div class="workflow-step-box">
                    <span class="step-badge-num">3</span>
                    <h4>Konfirmasi & Ambil Alat</h4>
                    <p>Admin akan memverifikasi pesanan Anda. Datang ke basecamp DANATRAIL untuk mengambil alat dan serahkan identitas jaminan.</p>
                </div>

                <div class="workflow-step-box">
                    <span class="step-badge-num">4</span>
                    <h4>Kembalikan Tepat Waktu</h4>
                    <p>Gunakan perlengkapan dengan baik dan kembalikan sesuai tanggal kembali. Identitas jaminan Anda akan dikembalikan utuh.</p>
                </div>
            </div>
        </section>

        <!-- Rules & Operational Info -->
        <section class="rules-faq-grid">
            <div class="rules-box-card">
                <div class="section-header-block">
                    <h2>Syarat & Ketentuan Sewa</h2>
                    <p>Kebijakan operasional penyewaan demi kenyamanan bersama.</p>
                </div>

                <ul class="rules-list">
                    <li class="rules-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Penyewa wajib menitipkan 1 identitas asli yang masih berlaku (KTP / SIM / KTM).</span>
                    </li>
                    <li class="rules-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Hitungan 1 hari sewa adalah 24 jam terhitung sejak waktu pengambilan alat.</span>
                    </li>
                    <li class="rules-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Pengecekan kondisi fisik dan kelengkapan alat dilakukan bersama saat serah terima.</span>
                    </li>
                    <li class="rules-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Kerusakan parah atau kehilangan alat menjadi tanggung jawab penyewa sesuai biaya penggantian.</span>
                    </li>
                </ul>
            </div>

            <div class="rules-box-card">
                <div class="section-header-block">
                    <h2>Kontak & Basecamp</h2>
                    <p>Informasi lokasi pengambilan dan bantuan operasional.</p>
                </div>

                <ul class="rules-list">
                    <li class="rules-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <div>
                            <strong>Basecamp DANATRAIL</strong><br>
                            Jl. Danew Trail No 18
                        </div>
                    </li>
                    <li class="rules-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <strong>Jam Operasional Rental</strong><br>
                            Senin – Minggu: 08.00 – 21.00 WIB
                        </div>
                    </li>
                    <li class="rules-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <div>
                            <strong>WhatsApp & Customer Service</strong><br>
                            <a href="https://wa.me/6288996016686?text=Halo%20Admin%20DANATRAIL,%20saya%20ingin%20bertanya%20seputar%20sewa%20dan%20pembelian%20alat%20outdoor" target="_blank" style="color: inherit; text-decoration: none;">
                                +62 889-9601-6686 (Fast Response)
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Bottom Call to Action -->
        <section class="bottom-cta-banner">
            <div class="cta-banner-text">
                <h3>Siap Menaklukkan Puncak Impian Anda?</h3>
                <p>Cek ketersediaan stok perlengkapan outdoor di katalog kami dan pesan lebih awal untuk mengamankan peralatan ekspedisi Anda.</p>
            </div>
            <a href="dashboard.php" class="btn-cta-explore">
                <span>Buka Katalog Lengkap</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </a>
        </section>

    </main>

    <!-- ==========================================================================
         MODAL SEWA PRODUK CEPAT DI HALAMAN BERANDA
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
                            <div style="font-size: 12.5px; color: var(--forest-800); font-weight: 800; margin-top: 3px;" id="modal_price_display">Rp 0 / hari</div>
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
                            <input type="number" name="quantity" id="modal_quantity" value="1" min="1" max="10" required onchange="recalculateTotal()" onkeyup="recalculateTotal()">
                        </div>
                        <div class="form-field-group">
                            <label for="modal_guarantee">Dokumen Jaminan *</label>
                            <select name="guarantee_type" id="modal_guarantee" required>
                                <option value="KTP Asli">KTP Asli</option>
                                <option value="SIM A / C Asli">SIM A / C Asli</option>
                                <option value="KTM (Kartu Mahasiswa)">KTM (Kartu Mahasiswa)</option>
                                <option value="Paspor Asli">Paspor Asli</option>
                            </select>
                        </div>
                    </div>

                    <!-- Data Informasi Pribadi Penyewa (Teleport / Auto-fill) -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <div style="font-size: 13px; font-weight: 700; color: var(--forest-950); display: flex; align-items: center; gap: 6px;">
                                <span>Data Kontak & Alamat Penyewa</span>
                            </div>
                            <a href="profile.php?wajib_isi=1&redirect_to=about.php" style="font-size: 11.5px; color: var(--forest-700); font-weight: 700; text-decoration: none;" title="Ubah di Pengaturan Profil">
                                Lengkapi di Profil →
                            </a>
                        </div>

                        <div class="form-grid-2col">
                            <div class="form-field-group" style="margin-bottom: 10px;">
                                <label for="modal_phone" style="font-size: 12px;">No. WhatsApp *</label>
                                <input type="tel" name="phone" id="modal_phone" required placeholder="Contoh: 081234567890" value="<?php echo htmlspecialchars($user_profile_data['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-field-group" style="margin-bottom: 10px;">
                                <label for="modal_name_static" style="font-size: 12px;">Nama Akun</label>
                                <input type="text" id="modal_name_static" readonly disabled value="<?php echo htmlspecialchars($user_profile_data['name'] ?? $_SESSION['nama']); ?>" style="background: #f1f5f9; color: #64748b;">
                            </div>
                        </div>

                        <div class="form-field-group" style="margin-bottom: 0;">
                            <label for="modal_address" style="font-size: 12px;">Alamat Lengkap Domisili *</label>
                            <textarea name="address" id="modal_address" rows="2" required placeholder="Alamat lengkap tempat tinggal sesuai KTP / domisili saat ini..."><?php echo htmlspecialchars($user_profile_data['address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Upload Foto Dokumen Jaminan Identitas (KTP/SIM/KTM) -->
                    <div class="form-field-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label for="modal_guarantee_photo_about" style="margin-bottom: 0;">Foto Identitas Jaminan (KTP / SIM / KTM)</label>
                            <span style="font-size: 10px; font-weight: 700; color: #059669; background: #ecfdf5; padding: 1px 7px; border-radius: 4px;">Opsional</span>
                        </div>
                        <div style="border: 1.5px dashed #cbd5e1; border-radius: var(--radius-md); padding: 10px 14px; text-align: center; background: #fafdfb; position: relative; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('modal_guarantee_photo_about').click()">
                            <input type="file" name="guarantee_photo" id="modal_guarantee_photo_about" accept="image/*,application/pdf" style="display: none;" onchange="previewGuaranteePhotoAbout(this)">
                            
                            <div id="guarantee_prompt_view_about">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color: var(--forest-700); margin: 0 auto 3px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <div style="font-size: 11.5px; font-weight: 700; color: var(--forest-950);">Klik jika ingin unggah foto KTP/SIM sekarang</div>
                                <div style="font-size: 10px; color: var(--text-muted); margin-top: 1px;">Bisa juga bawa & tunjukkan kartu fisik asli langsung saat ambil alat di basecamp.</div>
                            </div>

                            <div id="guarantee_selected_view_about" style="display: none; align-items: center; justify-content: center; gap: 10px;">
                                <img id="guarantee_img_thumb_about" src="" alt="Thumbnail" style="width: 44px; height: 32px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                <div style="text-align: left;">
                                    <div id="guarantee_file_title_about" style="font-size: 12px; font-weight: 700; color: var(--forest-950); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Dokumen Terpilih</div>
                                    <div style="font-size: 10.5px; color: var(--emerald-600); font-weight: 600;">Foto siap dilampirkan</div>
                                </div>
                            </div>
                        </div>
                    </div>

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

                    <!-- Price Summary Card -->
                    <div class="price-calc-summary-card">
                        <div class="calc-row">
                            <span>Durasi Sewa:</span>
                            <span id="modal_duration_text">1 Hari (24 Jam)</span>
                        </div>
                        <div class="calc-row">
                            <span>Tarif / Unit:</span>
                            <span id="modal_unit_rate_text">Rp 0</span>
                        </div>
                        <div class="calc-row total-row">
                            <span>Estimasi Total Biaya:</span>
                            <span id="modal_total_text" style="color: var(--forest-900);">Rp 0</span>
                        </div>
                    </div>
                </div>

                <div class="modal-footer-bar">
                    <button type="button" class="btn-modal-cancel" onclick="closeSewaModal()">Batal</button>
                    <button type="submit" name="sewa_produk" class="btn-modal-submit">Kirim Pemesanan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PROMPT LENGKAPI PROFIL (JIKA DATA BELUM LENGKAP) -->
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
                <a href="profile.php?wajib_isi=1&redirect_to=about.php" class="btn-modal-submit" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px; font-size: 13.5px; background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-900) 100%);">
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

        function previewGuaranteePhotoAbout(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const prompt = document.getElementById('guarantee_prompt_view_about');
                    const selected = document.getElementById('guarantee_selected_view_about');
                    const thumb = document.getElementById('guarantee_img_thumb_about');
                    const title = document.getElementById('guarantee_file_title_about');

                    if (thumb) thumb.src = e.target.result;
                    if (title) title.innerText = file.name;
                    if (prompt) prompt.style.display = 'none';
                    if (selected) selected.style.display = 'flex';
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

        function showActualSewaModal(product) {
            currentPricePerDay = parseFloat(product.price) || 0;
            maxStockAvailable = parseInt(product.stock) || 1;

            document.getElementById('modal_product_id').value = product.id;
            document.getElementById('modal_product_name').innerText = product.name;
            document.getElementById('modal_price_per_day').value = currentPricePerDay;
            document.getElementById('modal_price_display').innerText = 'Rp ' + Number(currentPricePerDay).toLocaleString('id-ID') + ' / hari';
            document.getElementById('modal_unit_rate_text').innerText = 'Rp ' + Number(currentPricePerDay).toLocaleString('id-ID');

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

        function recalculateTotal() {
            const startInput = document.getElementById('modal_rental_date').value;
            const returnInput = document.getElementById('modal_return_date').value;
            const qtyInput = parseInt(document.getElementById('modal_quantity').value) || 1;

            if (!startInput || !returnInput) return;

            const startDate = new Date(startInput);
            const returnDate = new Date(returnInput);

            let diffDays = Math.ceil((returnDate - startDate) / (1000 * 60 * 60 * 24));
            if (diffDays <= 0) diffDays = 1;

            const total = currentPricePerDay * diffDays * qtyInput;

            document.getElementById('modal_duration_text').innerText = diffDays + ' Hari (' + (diffDays * 24) + ' Jam)';
            document.getElementById('modal_total_text').innerText = 'Rp ' + Number(total).toLocaleString('id-ID');
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modalSewaOverlay');
            if (event.target === modal) closeSewaModal();
        });

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
                        <button type="button" onclick="copyTextToClipboard('731009876543', 'No. Rekening BCA disalin!')" style="padding:4px 9px; background:#10b981; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
                            📋 Salin
                        </button>
                    </div>
                    <div style="font-size:10.5px; color:#166534; margin-top:5px;">💡 Tenang, Anda dapat transfer setelah pesanan dikonfirmasi & struk bisa diunggah santai di riwayat sewa.</div>
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
                        <button type="button" onclick="copyTextToClipboard('1370019283741', 'No. Rekening Mandiri disalin!')" style="padding:4px 9px; background:#10b981; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
                            📋 Salin
                        </button>
                    </div>
                    <div style="font-size:10.5px; color:#166534; margin-top:5px;">💡 Transfer via Livin' by Mandiri atau ATM, bukti bayar bisa diunggah kapan saja.</div>
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
                        <button type="button" onclick="copyTextToClipboard('012301098765508', 'No. Rekening BRI disalin!')" style="padding:4px 9px; background:#10b981; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer;">
                            📋 Salin
                        </button>
                    </div>
                    <div style="font-size:10.5px; color:#166534; margin-top:5px;">💡 Transfer via BRImo atau ATM BRI dengan aman dan nyaman.</div>
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
                        <span style="font-size:14px;">🌿</span>
                        <strong style="color:#047857;">Sangat Santai: Bayar Tunai / Debit di Loket</strong>
                    </div>
                    <div style="font-size:10.5px; color:#065f46; margin-top:3px;">
                        Anda <strong>TIDAK PERLU transfer apapun sekarang</strong>! Silakan datang langsung ke Basecamp DANATRAIL saat tanggal sewa tiba dan bayar di kasir saat serah terima barang.
                    </div>
                `;
            }
        }
    </script>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>

</html>
