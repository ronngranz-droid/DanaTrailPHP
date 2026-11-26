<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika pengguna sudah login, arahkan ke dashboard masing-masing
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === "admin") {
        header("location:admin/dashboard.php");
        exit;
    } else {
        header("location:user/dashboard.php");
        exit;
    }
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/discount_fine_helper.php';
global $conn;

// Ambil data statistik ringkas untuk social proof / brand counters
$total_katalog = 20;
$total_unit    = 149;
if ($conn) {
    $q_prod = mysqli_query($conn, "SELECT COUNT(*) as c_prod, SUM(stock) as c_stock FROM products");
    if ($q_prod) {
        $d_prod = mysqli_fetch_assoc($q_prod);
        $total_katalog = (int)($d_prod['c_prod'] ?? 20);
        $total_unit    = (int)($d_prod['c_stock'] ?? 149);
    }
}

// Filter pencarian & kategori
$search_q   = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat_filter = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';

// Ambil list kategori untuk showcase
$categories_list = [];
if ($conn) {
    $q_cats = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
    if ($q_cats) {
        while ($c = mysqli_fetch_assoc($q_cats)) {
            $categories_list[] = $c;
        }
    }
}

// Ambil produk unggulan / hasil filter untuk showcase
$featured_products = [];
if ($conn) {
    $where_clauses = ["(stock > 0 OR stock_sale > 0)"];
    if (!empty($search_q)) {
        $safe_q = mysqli_real_escape_string($conn, $search_q);
        $where_clauses[] = "(name LIKE '%$safe_q%' OR description LIKE '%$safe_q%' OR category LIKE '%$safe_q%')";
    }
    if (!empty($cat_filter)) {
        $safe_cat = mysqli_real_escape_string($conn, $cat_filter);
        $where_clauses[] = "category = '$safe_cat'";
    }

    $where_sql = implode(' AND ', $where_clauses);
    $q_feat = mysqli_query($conn, "SELECT id, name, category, product_type, price, price_sale, stock, stock_sale, image, description 
                                   FROM products 
                                   WHERE $where_sql 
                                   ORDER BY id DESC LIMIT 8");
    if ($q_feat) {
        while ($row = mysqli_fetch_assoc($q_feat)) {
            $featured_products[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DANATRAIL — Persewaan Alat Gunung & Camping Berstandar Profesional</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
    <?php if (isset($_GET["desktop_1024"])): ?>
    <style>
        html, body {
            width: 1440px !important;
            max-width: 1440px !important;
            height: 1024px !important;
            max-height: 1024px !important;
            min-height: 1024px !important;
            overflow: hidden !important;
        }
        .landing-hero {
            padding: 32px 28px 24px !important;
            margin-bottom: 20px !important;
        }
        .stats-strip {
            margin-bottom: 24px !important;
        }
        .popular-section {
            padding-bottom: 0 !important;
        }
        .catalog-grid {
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 16px !important;
        }
    </style>
    <?php endif; ?>
    
    <style>
        /* ==========================================================================
           DANATRAIL EXPEDITION GRADE DESIGN SYSTEM (AUTHENTIC OUTDOOR AESTHETIC)
           ========================================================================== */
        :root {
            --obsidian-950: #070d0a;
            --obsidian-900: #0a130f;
            --obsidian-850: #0e1c15;
            --obsidian-800: #12241c;
            --forest-700:   #164e33;
            --forest-600:   #059669;
            --forest-500:   #10b981;
            
            --sand-500:     #d97706;
            --sand-400:     #f59e0b;
            --sand-100:     #fef3c7;
            
            --bg-page:        #f6f8f7;
            --surface-card:   #ffffff;
            --surface-subtle: #f1f5f3;
            --border-main:    #e2e8e5;
            --border-light:   #edf2ee;
            
            --text-heading: #09130e;
            --text-body:    #334139;
            --text-muted:   #64746d;
            --text-subtle:  #94a39b;

            --radius-xl: 18px;
            --radius-lg: 14px;
            --radius-md: 10px;
            --radius-sm: 6px;
            --radius-pill: 9999px;

            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
            --shadow-card:   0 4px 16px rgba(7, 13, 10, 0.06);
            --shadow-hover:  0 10px 28px rgba(7, 13, 10, 0.09);
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            line-height: 1.5;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            color: var(--text-heading);
            letter-spacing: -0.018em;
        }

        /* NAVBAR */
        .site-nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid var(--border-main);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .nav-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-emblem {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid var(--forest-700);
            background: #ffffff;
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .brand-emblem img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .brand-title-wrap h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 19px;
            font-weight: 800;
            color: var(--obsidian-950);
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .brand-title-wrap span {
            font-size: 10.5px;
            font-weight: 700;
            color: var(--forest-600);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 22px;
            list-style: none;
        }

        .nav-menu a {
            text-decoration: none;
            color: var(--text-body);
            font-size: 13.5px;
            font-weight: 600;
            transition: color 0.15s ease;
        }

        .nav-menu a:hover {
            color: var(--forest-600);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-nav-outline {
            background: #ffffff;
            border: 1px solid var(--border-main);
            color: var(--obsidian-950);
            padding: 8px 18px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.16s ease;
        }

        .btn-nav-outline:hover {
            background: var(--surface-subtle);
            border-color: #cbdad0;
            color: var(--forest-700);
        }

        .btn-nav-primary {
            background: var(--obsidian-900);
            color: #ffffff;
            border: 1px solid var(--obsidian-800);
            padding: 8px 18px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.16s ease;
        }

        .btn-nav-primary:hover {
            background: var(--forest-700);
            border-color: var(--forest-600);
            transform: translateY(-1px);
        }

        /* MAIN CONTENT LAYOUT */
        .main-wrapper {
            max-width: 1280px;
            margin: 0 auto;
            padding: 28px 24px 80px 24px;
            width: 100%;
        }

        /* ==========================================================================
           ALPINE EXPEDITION HERO (NO AI-SLOP, AUTHENTIC OUTDOOR ARCHITECTURE)
           ========================================================================== */
        .alpine-hero-card {
            background: linear-gradient(135deg, var(--obsidian-950) 0%, var(--obsidian-900) 45%, var(--obsidian-850) 100%);
            border-radius: var(--radius-xl);
            padding: 42px 44px 36px 44px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 16px 36px rgba(7, 13, 10, 0.35);
            margin-bottom: 24px;
        }

        .alpine-hero-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(ellipse 65% 60% at 85% 15%, rgba(16, 185, 129, 0.14) 0%, transparent 65%),
                radial-gradient(circle at 10% 90%, rgba(217, 119, 6, 0.08) 0%, transparent 50%);
            pointer-events: none;
        }

        .alpine-hero-inner {
            position: relative;
            z-index: 2;
            max-width: 840px;
        }

        .hero-field-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #6ee7b7;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(52, 211, 153, 0.25);
            padding: 5px 13px;
            border-radius: var(--radius-pill);
            margin-bottom: 16px;
        }

        .hero-pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.9);
        }

        .alpine-hero-card h1 {
            font-size: 34px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.22;
            letter-spacing: -0.02em;
        }

        .alpine-hero-desc {
            color: #9cb3a5;
            font-size: 14.5px;
            margin-top: 12px;
            line-height: 1.6;
            max-width: 700px;
        }

        .hero-search-wrapper {
            margin-top: 24px;
            max-width: 650px;
        }

        .hero-search-form {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: var(--radius-md);
            padding: 5px 6px 5px 14px;
            transition: all 0.2s ease;
            backdrop-filter: blur(8px);
        }

        .hero-search-form:focus-within {
            background: rgba(255, 255, 255, 0.12);
            border-color: #34d399;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        .hero-search-icon {
            color: #94a3b8;
            margin-right: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }

        .hero-search-form input {
            flex: 1;
            background: transparent;
            border: none;
            color: #ffffff;
            font-size: 13.5px;
            font-family: inherit;
            outline: none;
            min-width: 0;
        }

        .hero-search-form input::placeholder {
            color: #64748b;
        }

        .hero-search-btn {
            background: #10b981;
            color: #041f14;
            border: none;
            padding: 8px 18px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.18s ease;
            white-space: nowrap;
        }

        .hero-search-btn:hover {
            background: #34d399;
            transform: translateY(-1px);
        }

        .hero-quick-chips {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .hero-chips-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hero-chip-link {
            font-size: 11.5px;
            color: #d1e2d7;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.15s ease;
        }

        .hero-chip-link:hover {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border-color: rgba(52, 211, 153, 0.3);
        }

        /* FIELD REASSURANCE BAR */
        .field-assurance-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 30px;
        }

        .assurance-card {
            background: var(--surface-card);
            border: 1px solid var(--border-main);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
            box-shadow: var(--shadow-subtle);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.18s ease;
        }

        .assurance-card:hover {
            border-color: #cbdad0;
            box-shadow: var(--shadow-card);
            transform: translateY(-2px);
        }

        .assurance-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #059669;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .assurance-icon-box.amber {
            background: #fef3c7;
            border-color: #fde68a;
            color: #b45309;
        }

        .assurance-icon-box.slate {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #475569;
        }

        .assurance-content {
            display: flex;
            flex-direction: column;
        }

        .assurance-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.25;
        }

        .assurance-sub {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 3px;
            line-height: 1.35;
        }

        /* ==========================================================================
           FEATURED GEAR SHOWCASE (CLEAN TECHNICAL CATALOG SPEC)
           ========================================================================== */
        .catalog-section {
            margin-bottom: 36px;
        }

        .section-header-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .section-eyebrow {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--forest-600);
            margin-bottom: 2px;
        }

        .section-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.02em;
        }

        .section-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .btn-view-all-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--forest-700);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            transition: all 0.18s ease;
        }

        .btn-view-all-link:hover {
            background: #d1fae5;
            color: #065f46;
            transform: translateX(2px);
        }

        /* Filter Feedback Banner */
        .filter-status-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-md);
            padding: 10px 16px;
            margin-bottom: 18px;
            font-size: 13px;
            color: var(--forest-700);
        }

        .filter-status-banner a {
            color: #b91c1c;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
        }

        .filter-status-banner a:hover {
            text-decoration: underline;
        }

        /* GEAR CARDS GRID */
        .gear-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 18px;
        }

        .gear-card {
            background: var(--surface-card);
            border: 1px solid var(--border-main);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .gear-card:hover {
            transform: translateY(-4px);
            border-color: #cbdad0;
            box-shadow: var(--shadow-hover);
        }

        .gear-media-box {
            height: 180px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 14px;
            overflow: hidden;
        }

        .gear-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.25s ease;
        }

        .gear-card:hover .gear-img {
            transform: scale(1.06);
        }

        .gear-fallback-icon {
            width: 48px;
            height: 48px;
            color: #94a3b8;
            opacity: 0.6;
        }

        .gear-body {
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .gear-meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .gear-category-eyebrow {
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
        }

        .gear-stock-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 700;
        }

        .stock-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .stock-dot.ready {
            background: #10b981;
            box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
        }
        .stock-dot.low {
            background: #f59e0b;
            box-shadow: 0 0 6px rgba(245, 158, 11, 0.6);
        }
        .stock-dot.empty {
            background: #94a3b8;
        }

        .gear-title {
            font-size: 14.5px;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.35;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .gear-snippet {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.45;
            margin-bottom: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }

        .gear-footer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid var(--border-light);
            margin-top: auto;
        }

        .gear-rate {
            display: flex;
            flex-direction: column;
        }

        .rate-amount {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: var(--obsidian-950);
            line-height: 1.1;
        }

        .rate-period {
            font-size: 10.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .btn-sewa-trigger {
            background: var(--obsidian-900);
            color: #ffffff;
            border: 1px solid var(--obsidian-800);
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.16s ease;
        }

        .btn-sewa-trigger:hover {
            background: var(--forest-700);
            border-color: var(--forest-600);
            transform: translateY(-1px);
        }

        .btn-sewa-disabled {
            background: #f1f5f9;
            color: #94a3b8;
            border: 1px solid #e2e8f0;
            padding: 7px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            cursor: not-allowed;
        }

        /* ==========================================================================
           WHY DANATRAIL (AUTHENTIC OUTDOOR VALUE PROPOSITIONS)
           ========================================================================== */
        .value-prop-section {
            background: var(--surface-card);
            border: 1px solid var(--border-main);
            border-radius: var(--radius-xl);
            padding: 28px 30px;
            box-shadow: var(--shadow-subtle);
            margin-bottom: 30px;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .value-box {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .value-badge {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #059669;
        }

        .value-box h3 {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-heading);
        }

        .value-box p {
            font-size: 12.5px;
            color: var(--text-body);
            line-height: 1.55;
        }

        /* ==========================================================================
           CATEGORY DIRECTORY SHOWCASE
           ========================================================================== */
        .category-directory-section {
            background: var(--surface-card);
            border: 1px solid var(--border-main);
            border-radius: var(--radius-xl);
            padding: 26px 30px;
            box-shadow: var(--shadow-subtle);
            margin-bottom: 30px;
        }

        .cat-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 18px;
        }

        .cat-card-item {
            background: var(--bg-page);
            border: 1px solid var(--border-main);
            border-radius: var(--radius-md);
            padding: 16px 14px;
            text-decoration: none;
            color: var(--text-heading);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 105px;
            transition: all 0.18s ease;
        }

        .cat-card-item:hover {
            background: #ffffff;
            border-color: #10b981;
            transform: translateY(-2px);
            box-shadow: var(--shadow-subtle);
        }

        .cat-item-title {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--obsidian-950);
        }

        .cat-item-desc {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 4px;
            line-height: 1.35;
        }

        .cat-item-action {
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--forest-600);
        }

        .cat-card-item:hover .cat-item-action {
            color: #047857;
            transform: translateX(2px);
        }

        /* ==========================================================================
           RENTAL WORKFLOW (4 STEPS)
           ========================================================================== */
        .workflow-card {
            background: var(--surface-card);
            border: 1px solid var(--border-main);
            border-radius: var(--radius-xl);
            padding: 26px 30px;
            box-shadow: var(--shadow-subtle);
            margin-bottom: 30px;
        }

        .workflow-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 18px;
        }

        .workflow-box {
            background: var(--bg-page);
            border: 1px solid var(--border-main);
            border-radius: var(--radius-md);
            padding: 18px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            position: relative;
        }

        .workflow-num {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--obsidian-950);
            color: #34d399;
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .workflow-box h4 {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-heading);
        }

        .workflow-box p {
            font-size: 12px;
            color: var(--text-body);
            line-height: 1.5;
        }

        /* ==========================================================================
           RULES & BASECAMP SPLIT GRID
           ========================================================================== */
        .rules-contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-panel-box {
            background: var(--surface-card);
            border: 1px solid var(--border-main);
            border-radius: var(--radius-xl);
            padding: 24px;
            box-shadow: var(--shadow-subtle);
        }

        .panel-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 16px;
        }

        .panel-list-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12.5px;
            color: var(--text-body);
            line-height: 1.45;
        }

        .panel-list-item svg {
            width: 17px;
            height: 17px;
            color: var(--forest-600);
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* ==========================================================================
           BOTTOM EXPEDITION CTA BANNER
           ========================================================================== */
        .bottom-cta-card {
            background: linear-gradient(135deg, var(--obsidian-950) 0%, var(--obsidian-900) 100%);
            border-radius: var(--radius-xl);
            padding: 30px 36px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 10px 25px rgba(7, 13, 10, 0.25);
            flex-wrap: wrap;
        }

        .bottom-cta-text h3 {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
        }

        .bottom-cta-text p {
            font-size: 13.5px;
            color: #9cb3a5;
            margin-top: 4px;
            max-width: 620px;
        }

        .btn-cta-katalog {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #10b981;
            color: #041f14;
            font-size: 13.5px;
            font-weight: 800;
            padding: 11px 24px;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: all 0.18s ease;
            white-space: nowrap;
        }

        .btn-cta-katalog:hover {
            background: #34d399;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.3);
        }

        /* FOOTER */
        .site-footer {
            background: #ffffff;
            border-top: 1px solid var(--border-main);
            padding: 48px 24px 28px;
            margin-top: 60px;
        }

        .footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.8fr 1fr 1fr;
            gap: 36px;
            padding-bottom: 36px;
            border-bottom: 1px solid var(--border-main);
        }

        .footer-brand h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--obsidian-950);
            margin-bottom: 8px;
        }

        .footer-brand p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
            max-width: 400px;
        }

        .footer-col h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--obsidian-950);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
        }

        .footer-col a {
            color: var(--text-body);
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .footer-col a:hover {
            color: var(--forest-600);
        }

        .footer-bottom {
            max-width: 1280px;
            margin: 20px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 10px;
        }

        /* RESPONSIVE BREAKPOINTS */
        @media (max-width: 960px) {
            .field-assurance-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            .rules-contact-grid {
                grid-template-columns: 1fr;
            }
            .footer-inner {
                grid-template-columns: 1fr;
            }
            .nav-menu {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .main-wrapper {
                padding: 18px 14px 50px 14px;
            }
            .alpine-hero-card {
                padding: 28px 20px 24px 20px;
            }
            .alpine-hero-card h1 {
                font-size: 25px;
            }
            .field-assurance-bar {
                grid-template-columns: 1fr;
            }
            .gear-grid {
                grid-template-columns: 1fr;
            }
            .hero-search-form {
                flex-direction: column;
                align-items: stretch;
                padding: 10px;
                gap: 8px;
            }
            .hero-search-btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <header class="site-nav">
        <div class="nav-inner">
            <a href="index.php" class="brand-link">
                <div class="brand-emblem">
                    <img src="assets/logo.png" alt="Logo DANATRAIL">
                </div>
                <div class="brand-title-wrap">
                    <h1>DANATRAIL</h1>
                    <span>Basecamp & Outdoor Gear</span>
                </div>
            </a>

            <ul class="nav-menu">
                <li><a href="#katalog">Katalog Gear</a></li>
                <li><a href="#keunggulan">Standar Layanan</a></li>
                <li><a href="#kategori">Kategori</a></li>
                <li><a href="#alur-sewa">Alur Sewa</a></li>
                <li><a href="#basecamp">Lokasi Basecamp</a></li>
            </ul>

            <div class="nav-actions">
                <a href="login.php" class="btn-nav-outline">Masuk</a>
                <a href="register.php" class="btn-nav-primary">
                    <span>Daftar Akun</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN WRAPPER -->
    <main class="main-wrapper">

        <!-- ==========================================================================
             ALPINE EXPEDITION HERO SECTION
             ========================================================================== -->
        <section class="alpine-hero-card">
            <div class="alpine-hero-inner">
                <div class="hero-field-tag">
                    <span class="hero-pulse-dot"></span>
                    <span>Basecamp Danatrail • Standar Ekspedisi Gunung</span>
                </div>

                <h1>Persewaan Alat Gunung & Camping Berstandar Profesional</h1>
                
                <p class="alpine-hero-desc">
                    Tenda tahan badai, carrier ergonomis, sleeping bag higienis, dan perlengkapan mendaki steril siap ekspedisi. Sistem sewa transparan per 24 jam dengan jaminan KTP/SIM fleksibel di Basecamp Danatrail.
                </p>

                <!-- Interactive Search Bar -->
                <div class="hero-search-wrapper">
                    <form action="index.php#katalog" method="GET" class="hero-search-form">
                        <div class="hero-search-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Cari tenda 4P, carrier 60L, sleeping bag polar, kompor ultralight..." autocomplete="off">
                        <button type="submit" class="hero-search-btn">
                            <span>Cari Gear</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </form>

                    <!-- Quick Filter Chips -->
                    <div class="hero-quick-chips">
                        <span class="hero-chips-label">Paling Dicari:</span>
                        <a href="index.php?kategori=Tenda+%26+Shelter#katalog" class="hero-chip-link">Tenda & Shelter</a>
                        <a href="index.php?kategori=Carrier+%26+Tas#katalog" class="hero-chip-link">Carrier & Tas</a>
                        <a href="index.php?kategori=Sleeping+Gear#katalog" class="hero-chip-link">Sleeping Gear</a>
                        <a href="index.php?kategori=Cooking+Set#katalog" class="hero-chip-link">Cooking Set</a>
                        <a href="index.php?kategori=Penerangan+%26+Elektronik#katalog" class="hero-chip-link">Headlamp & Penerangan</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==========================================================================
             OPERATIONAL REASSURANCE STRIP (AUTHENTIC COUNTERS & GUARANTEES)
             ========================================================================== -->
        <section class="field-assurance-bar">
            <div class="assurance-card">
                <div class="assurance-icon-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="assurance-content">
                    <span class="assurance-title"><?php echo $total_katalog; ?>+ Varian Teruji</span>
                    <span class="assurance-sub">Tenda badai, carrier alloy & gear hiking pilihan</span>
                </div>
            </div>

            <div class="assurance-card">
                <div class="assurance-icon-box amber">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="assurance-content">
                    <span class="assurance-title"><?php echo $total_unit; ?> Unit Inventaris</span>
                    <span class="assurance-sub">Stok terawat & selalu siap berangkat kapan saja</span>
                </div>
            </div>

            <div class="assurance-card">
                <div class="assurance-icon-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <div class="assurance-content">
                    <span class="assurance-title">100% Bersih & Steril</span>
                    <span class="assurance-sub">Dicuci higienis & dicek kelengkapannya pasca-sewa</span>
                </div>
            </div>

            <div class="assurance-card">
                <div class="assurance-icon-box slate">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="assurance-content">
                    <span class="assurance-title">Hitungan 24 Jam</span>
                    <span class="assurance-sub">Tarif harian transparan, jaminan KTP/SIM mudah</span>
                </div>
            </div>
        </section>

        <!-- ==========================================================================
             FEATURED GEAR SHOWCASE
             ========================================================================== -->
        <section class="catalog-section" id="katalog">
            <div class="section-header-row">
                <div>
                    <div class="section-eyebrow">Rekomendasi Terpopuler</div>
                    <h2 class="section-title">Perlengkapan Siap Ekspedisi</h2>
                    <p class="section-desc">Pilihan favorit para pendaki untuk summit Merbabu, Lawu, Prau, dan camping akhir pekan.</p>
                </div>
                <a href="login.php?pesan=<?php echo urlencode('Silakan masuk untuk menjelajahi seluruh katalog lengkap DANATRAIL.'); ?>" class="btn-view-all-link">
                    <span>Lihat Seluruh Katalog</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <?php if (!empty($search_q) || !empty($cat_filter)): ?>
                <div class="filter-status-banner">
                    <div>
                        Menampilkan hasil filter untuk: 
                        <?php if (!empty($search_q)): ?>
                            <strong>"<?php echo htmlspecialchars($search_q); ?>"</strong>
                        <?php endif; ?>
                        <?php if (!empty($cat_filter)): ?>
                            Kategori <strong><?php echo htmlspecialchars($cat_filter); ?></strong>
                        <?php endif; ?>
                    </div>
                    <a href="index.php#katalog">&times; Reset Filter</a>
                </div>
            <?php endif; ?>

            <div class="gear-grid">
                <?php if (!empty($featured_products)): ?>
                    <?php foreach ($featured_products as $prod): 
                        $stk = (int)($prod['stock'] ?? 0);
                    ?>
                        <div class="gear-card">
                            <div class="gear-media-box">
                                <?php if (!empty($prod['image']) && file_exists(__DIR__ . '/uploads/products/' . $prod['image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($prod['image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="gear-img" loading="lazy">
                                <?php else: ?>
                                    <svg class="gear-fallback-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                <?php endif; ?>
                            </div>

                            <div class="gear-body">
                                <div class="gear-meta-row">
                                    <span class="gear-category-eyebrow"><?php echo htmlspecialchars($prod['category']); ?></span>

                                    <?php if ($stk > 3): ?>
                                        <span class="gear-stock-indicator" style="color: #059669;">
                                            <span class="stock-dot ready"></span>
                                            Ready <?php echo $stk; ?>
                                        </span>
                                    <?php elseif ($stk > 0): ?>
                                        <span class="gear-stock-indicator" style="color: #b45309;">
                                            <span class="stock-dot low"></span>
                                            Sisa <?php echo $stk; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="gear-stock-indicator" style="color: #64748b;">
                                            <span class="stock-dot empty"></span>
                                            Habis
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="gear-title" title="<?php echo htmlspecialchars($prod['name']); ?>">
                                    <?php echo htmlspecialchars($prod['name']); ?>
                                </h3>

                                <p class="gear-snippet">
                                    <?php echo htmlspecialchars($prod['description'] ?? 'Peralatan terawat dan siap pakai.'); ?>
                                </p>

                                <div class="gear-footer-row">
                                    <div class="gear-rate">
                                        <span class="rate-amount">Rp <?php echo number_format($prod['price'], 0, ',', '.'); ?></span>
                                        <span class="rate-period">/ 24 jam</span>
                                    </div>

                                    <?php if ($stk > 0): ?>
                                        <a href="login.php?pesan=<?php echo urlencode('Silakan masuk untuk menyewa ' . $prod['name']); ?>" class="btn-sewa-trigger">
                                            <span>Sewa</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn-sewa-disabled">
                                            Habis
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; padding: 36px; text-align: center; color: var(--text-muted); background: #fff; border-radius: var(--radius-lg); border: 1px solid var(--border-main);">
                        Tidak ada perlengkapan yang cocok dengan pencarian Anda. <a href="index.php#katalog" style="color: var(--forest-600); font-weight: 700;">Lihat semua perlengkapan</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ==========================================================================
             WHY CHOOSE DANATRAIL
             ========================================================================== -->
        <section class="value-prop-section" id="keunggulan">
            <div class="section-eyebrow">Standar Layanan</div>
            <h2 class="section-title">Mengapa Memilih Persewaan DANATRAIL?</h2>
            <p class="section-desc">Kualitas peralatan dan kenyamanan pendakian adalah prioritas utama setiap trip Anda.</p>

            <div class="values-grid">
                <div class="value-box">
                    <div class="value-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <h3>Alat Steril & Siap Pakai</h3>
                    <p>Setiap tenda, carrier, dan sleeping bag melewati proses pencucian, pengeringan higienis, dan uji kelengkapan frame sebelum disewakan.</p>
                </div>

                <div class="value-box">
                    <div class="value-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3>Tarif Transparan 24 Jam</h3>
                    <p>Sistem hitungan tarif per 24 jam penuh sejak serah terima alat. Harga bersahabat bagi mahasiswa dan komunitas tanpa biaya tersembunyi.</p>
                </div>

                <div class="value-box">
                    <div class="value-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                    </div>
                    <h3>Jaminan Fleksibel</h3>
                    <p>Cukup titipkan 1 dokumen identitas asli yang masih berlaku (KTP / SIM / KTM) saat serah terima peralatan di Basecamp.</p>
                </div>

                <div class="value-box">
                    <div class="value-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h3>Konsultasi Gear & Rute</h3>
                    <p>Bingung memilih kapasitas tenda atau beban carrier? Tim basecamp kami siap memberi saran alat yang tepat sesuai karakteristik gunung tujuan.</p>
                </div>
            </div>
        </section>

        <!-- ==========================================================================
             CATEGORY DIRECTORY
             ========================================================================== -->
        <section class="category-directory-section" id="kategori">
            <div class="section-eyebrow">Eksplorasi Lengkap</div>
            <h2 class="section-title">Kategori Perlengkapan Ekspedisi</h2>
            <p class="section-desc">Pilih kategori alat yang Anda butuhkan untuk memudahkan persiapan pendakian.</p>

            <div class="cat-cards-grid">
                <?php if (!empty($categories_list)): ?>
                    <?php foreach ($categories_list as $cat): ?>
                        <a href="index.php?kategori=<?php echo urlencode($cat['name']); ?>#katalog" class="cat-card-item">
                            <div>
                                <div class="cat-item-title"><?php echo htmlspecialchars($cat['name']); ?></div>
                                <div class="cat-item-desc"><?php echo htmlspecialchars($cat['description'] ?? 'Peralatan berkualitas.'); ?></div>
                            </div>
                            <div class="cat-item-action">
                                <span>Buka Kategori</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="index.php?kategori=Tenda+%26+Shelter#katalog" class="cat-card-item">
                        <div>
                            <div class="cat-item-title">Tenda & Shelter</div>
                            <div class="cat-item-desc">Tenda dome double layer tahan angin & hujan badai.</div>
                        </div>
                        <div class="cat-item-action"><span>Buka Kategori &rarr;</span></div>
                    </a>
                    <a href="index.php?kategori=Carrier+%26+Tas#katalog" class="cat-card-item">
                        <div>
                            <div class="cat-item-title">Carrier & Tas</div>
                            <div class="cat-item-desc">Carrier ergonomis 45L-75L dengan backsystem nyaman.</div>
                        </div>
                        <div class="cat-item-action"><span>Buka Kategori &rarr;</span></div>
                    </a>
                    <a href="index.php?kategori=Sleeping+Gear#katalog" class="cat-card-item">
                        <div>
                            <div class="cat-item-title">Sleeping Gear</div>
                            <div class="cat-item-desc">Sleeping bag polar hangat dan matras aluminium foil.</div>
                        </div>
                        <div class="cat-item-action"><span>Buka Kategori &rarr;</span></div>
                    </a>
                    <a href="index.php?kategori=Cooking+Set#katalog" class="cat-card-item">
                        <div>
                            <div class="cat-item-title">Cooking Set</div>
                            <div class="cat-item-desc">Kompor ultralight, nesting aluminium, dan gas kaleng.</div>
                        </div>
                        <div class="cat-item-action"><span>Buka Kategori &rarr;</span></div>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- ==========================================================================
             RENTAL WORKFLOW (4 STEPS)
             ========================================================================== -->
        <section class="workflow-card" id="alur-sewa">
            <div class="section-eyebrow">Panduan Praktis</div>
            <h2 class="section-title">Alur & Cara Menyewa di DANATRAIL</h2>
            <p class="section-desc">Hanya 4 langkah mudah untuk mempersiapkan perlengkapan pendakian Anda.</p>

            <div class="workflow-grid">
                <div class="workflow-box">
                    <span class="workflow-num">01</span>
                    <h4>Pilih Perlengkapan</h4>
                    <p>Pilih perlengkapan dari rekomendasi di atas atau buka <strong>Katalog Sewa</strong>, lalu klik tombol <em>Sewa</em>.</p>
                </div>

                <div class="workflow-box">
                    <span class="workflow-num">02</span>
                    <h4>Atur Jadwal & Jaminan</h4>
                    <p>Tentukan tanggal mulai sewa, durasi hari, jumlah unit, dan tentukan jenis dokumen identitas jaminan (KTP/SIM/KTM).</p>
                </div>

                <div class="workflow-box">
                    <span class="workflow-num">03</span>
                    <h4>Ambil Alat di Basecamp</h4>
                    <p>Pesanan diverifikasi instan. Datang ke Basecamp Danatrail untuk serah terima alat dan titipkan identitas jaminan.</p>
                </div>

                <div class="workflow-box">
                    <span class="workflow-num">04</span>
                    <h4>Kembalikan Pasca-Trip</h4>
                    <p>Selesaikan petualangan Anda dan kembalikan alat tepat waktu. Identitas jaminan Anda akan dikembalikan utuh 100%.</p>
                </div>
            </div>
        </section>

        <!-- ==========================================================================
             RULES & BASECAMP SPLIT PANELS
             ========================================================================== -->
        <section class="rules-contact-grid" id="basecamp">
            <div class="info-panel-box">
                <div class="section-eyebrow">Ketentuan Rental</div>
                <h2 style="font-size: 18px; font-weight: 800; color: var(--text-heading);">Syarat & Ketentuan Sewa</h2>
                <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;">Kebijakan operasional penyewaan demi keamanan dan kenyamanan bersama.</p>

                <ul class="panel-list">
                    <li class="panel-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Penyewa wajib menitipkan 1 identitas asli yang masih berlaku (KTP / SIM / KTM).</span>
                    </li>
                    <li class="panel-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Hitungan 1 hari sewa adalah 24 jam penuh terhitung sejak waktu pengambilan alat.</span>
                    </li>
                    <li class="panel-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Pengecekan kondisi fisik dan kelengkapan pasak/frame dilakukan bersama saat serah terima.</span>
                    </li>
                    <li class="panel-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Kerusakan parah atau kehilangan alat menjadi tanggung jawab penyewa sesuai biaya penggantian part.</span>
                    </li>
                </ul>
            </div>

            <div class="info-panel-box">
                <div class="section-eyebrow">Lokasi & Bantuan</div>
                <h2 style="font-size: 18px; font-weight: 800; color: var(--text-heading);">Basecamp DANATRAIL</h2>
                <p style="font-size: 12.5px; color: var(--text-muted); margin-top: 2px;">Pusat serah terima alat dan konsultasi peralatan outdoor.</p>

                <ul class="panel-list">
                    <li class="panel-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <div>
                            <strong style="color: var(--text-heading);">Basecamp DANATRAIL Sidoarjo</strong><br>
                            Jl. Danatrail
                        </div>
                    </li>
                    <li class="panel-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <strong style="color: var(--text-heading);">Jam Operasional Pengambilan</strong><br>
                            Buka Setiap Hari: 08.00 – 21.00 WIB
                        </div>
                    </li>
                    <li class="panel-list-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <div>
                            <strong style="color: var(--text-heading);">WhatsApp Konsultasi Cepat</strong><br>
                            <a href="https://wa.me/6288996016686?text=Halo%20Admin%20DANATRAIL,%20saya%20ingin%20bertanya%20seputar%20sewa%20dan%20pembelian%20alat%20outdoor" target="_blank" style="color: var(--forest-600); text-decoration: none; font-weight: 700;">
                                +62 889-9601-6686 (Fast Response) &rarr;
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- ==========================================================================
             BOTTOM EXPEDITION CTA BANNER
             ========================================================================== -->
        <section class="bottom-cta-card">
            <div class="bottom-cta-text">
                <h3>Siap Menaklukkan Puncak Impian Anda?</h3>
                <p>Cek ketersediaan stok seluruh alat outdoor di katalog lengkap kami dan amankan tanggal ekspedisi Anda lebih awal.</p>
            </div>
            <a href="register.php" class="btn-cta-katalog">
                <span>Daftar Akun Sekarang</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </a>
        </section>

    </main>

    <!-- FOOTER -->
    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <div style="width: 38px; height: 38px; border-radius: 50%; border: 2px solid var(--forest-700); padding: 2px; background: #ffffff;">
                        <img src="assets/logo.png" alt="DANATRAIL" style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%;">
                    </div>
                    <h3 style="margin: 0;">DANATRAIL</h3>
                </div>
                <p>
                    Pusat persewaan dan penjualan perlengkapan ekspedisi alam bebas di Sidoarjo. Mengutamakan kesiapan fisik alat, kebersihan unit higienis, dan keamanan setiap pendaki.
                </p>
            </div>

            <div class="footer-col">
                <h4>Navigasi</h4>
                <ul>
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="#katalog">Katalog Perlengkapan</a></li>
                    <li><a href="#keunggulan">Standar Layanan</a></li>
                    <li><a href="#alur-sewa">Alur Sewa & Jaminan</a></li>
                    <li><a href="#basecamp">Lokasi Basecamp</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Layanan & Transaksi</h4>
                <ul>
                    <li>Hitungan Sewa Per 24 Jam Penuh</li>
                    <li>Jaminan Fleksibel (KTP / SIM / KTM)</li>
                    <li>Midtrans Snap QRIS & Transfer Bank</li>
                    <li>Pengambilan Mandiri di Basecamp</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> DANATRAIL Outdoor Equipment & Rental. Hak cipta dilindungi.</span>
            <span>Jl. Danatrail, Sidoarjo</span>
        </div>
    </footer>

</body>
</html>
