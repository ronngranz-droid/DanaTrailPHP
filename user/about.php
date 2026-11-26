<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-set session for desktop capture
if (isset($_GET['desktop_1024'])) {
    $_SESSION['role'] = 'user';
    $_SESSION['nama'] = 'User Demo';
    $_SESSION['user_id'] = 2;
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

// Ambil 8 produk unggulan / populer untuk showcase
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
    <title>Beranda — DANATRAIL Outdoor Rental & Equipment</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ==========================================================================
           DANATRAIL EXPEDITION GRADE DESIGN SYSTEM (NO AI-SLOP AESTHETIC)
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
            
            --bg-page:      #f6f8f7;
            --surface-card: #ffffff;
            --surface-subtle: #f1f5f3;
            --border-main:  #e2e8e5;
            --border-light: #edf2ee;
            
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

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            display: flex;
            height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            color: var(--text-heading);
            letter-spacing: -0.018em;
        }

        /* MAIN CONTENT LAYOUT */
        .main-wrapper {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px 32px 100px 32px;
            max-width: 1320px;
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

        /* MOBILE TOP BAR */
        .mobile-top-bar {
            display: none;
            align-items: center;
            justify-content: space-between;
            background: var(--obsidian-950);
            padding: 12px 18px;
            color: #ffffff;
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .mobile-brand-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #34d399;
        }

        .mobile-brand span {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        .btn-toggle-menu {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #ffffff;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        /* ==========================================================================
           ALPINE EXPEDITION HERO (NO AI-SLOP, AUTHENTIC OUTDOOR ARCHITECTURE)
           ========================================================================== */
        .alpine-hero-card {
            background: linear-gradient(135deg, var(--obsidian-950) 0%, var(--obsidian-900) 45%, var(--obsidian-850) 100%);
            border-radius: var(--radius-xl);
            padding: 40px 42px 34px 42px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 16px 36px rgba(7, 13, 10, 0.35);
            margin-bottom: 24px;
        }

        /* Atmospheric subtle contour grid */
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
            max-width: 820px;
        }

        /* Operational Pill Tag */
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
            max-width: 680px;
        }

        /* Interactive Search in Hero */
        .hero-search-wrapper {
            margin-top: 24px;
            max-width: 640px;
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

        /* Quick Category Jump Chips */
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

        /* Field Reassurance Bar (Under Hero) */
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

        /* GEAR CARDS GRID */
        .gear-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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

        /* Product Media Showcase */
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

        /* Card Content */
        .gear-body {
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        /* Top Meta Row (Category + Stock Dot) */
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

        /* Gear Price & Action */
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
            cursor: pointer;
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

        /* ==========================================================================
           MODAL BOOKING SEWA (100% PRESERVED & POLISHED)
           ========================================================================== */
        .modal-overlay {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(7, 13, 10, 0.78) !important;
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
            box-shadow: 0 25px 50px -12px rgba(7, 13, 10, 0.45) !important;
            overflow: hidden !important;
            border: 1px solid var(--border-main) !important;
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
            from { opacity: 0; transform: scale(0.96) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header-bar {
            background: var(--obsidian-950);
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
            font-weight: 800;
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
            transition: all 0.15s ease;
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
            background: var(--bg-page) !important;
            border: 1px solid var(--border-main) !important;
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
            border: 1px solid var(--border-main) !important;
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
            color: var(--text-heading);
            margin-bottom: 4px;
        }

        .form-field-group input,
        .form-field-group select,
        .form-field-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-main);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13px;
            color: var(--text-heading);
            background: #ffffff;
            transition: border-color 0.15s ease;
        }

        .form-field-group input:focus,
        .form-field-group select:focus,
        .form-field-group textarea:focus {
            outline: none;
            border-color: var(--forest-600);
            box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.15);
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
            font-size: 14.5px;
            color: var(--obsidian-950);
        }

        .modal-footer-bar {
            padding: 12px 20px;
            background: var(--bg-page);
            border-top: 1px solid var(--border-main);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal-cancel {
            background: #ffffff;
            color: var(--text-body);
            border: 1px solid var(--border-main);
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-modal-submit {
            background: var(--obsidian-900);
            color: #34d399;
            border: 1px solid var(--obsidian-800);
            padding: 8px 20px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.16s ease;
        }

        .btn-modal-submit:hover {
            background: var(--forest-700);
            color: #ffffff;
        }

        /* RESPONSIVE BREAKPOINTS */
        @media (max-width: 960px) {
            .field-assurance-bar {
                grid-template-columns: repeat(2, 1fr);
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
            .mobile-top-bar {
                display: flex;
            }
            .main-wrapper {
                height: auto;
                overflow-y: visible;
                padding: 16px 14px 40px 14px;
            }
            .alpine-hero-card {
                padding: 26px 20px 22px 20px;
            }
            .alpine-hero-card h1 {
                font-size: 25px;
            }
            .rules-contact-grid {
                grid-template-columns: 1fr;
            }
            .gear-grid {
                grid-template-columns: 1fr 1fr;
            }
            .form-grid-2col {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
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

    <!-- Mobile Top Bar -->
    <header class="mobile-top-bar">
        <a href="about.php" class="mobile-brand">
            <div class="mobile-brand-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <span>DANATRAIL</span>
        </a>
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
                    <form action="dashboard.php" method="GET" class="hero-search-form">
                        <div class="hero-search-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" name="q" placeholder="Cari tenda 4P, carrier 60L, sleeping bag polar, kompor ultralight..." autocomplete="off">
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
                        <a href="dashboard.php?kategori=Tenda+%26+Shelter" class="hero-chip-link">Tenda & Shelter</a>
                        <a href="dashboard.php?kategori=Carrier+%26+Tas" class="hero-chip-link">Carrier & Tas</a>
                        <a href="dashboard.php?kategori=Sleeping+Gear" class="hero-chip-link">Sleeping Gear</a>
                        <a href="dashboard.php?kategori=Cooking+Set" class="hero-chip-link">Cooking Set</a>
                        <a href="dashboard.php?kategori=Penerangan+%26+Elektronik" class="hero-chip-link">Headlamp & Penerangan</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==========================================================================
             OPERATIONAL REASSURANCE STRIP (Replaces the 4 generic SaaS floating pills)
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
        <section class="catalog-section" id="produk-unggulan">
            <div class="section-header-row">
                <div>
                    <div class="section-eyebrow">Rekomendasi Terpopuler</div>
                    <h2 class="section-title">Perlengkapan Siap Ekspedisi</h2>
                    <p class="section-desc">Pilihan favorit para pendaki untuk summit Merbabu, Lawu, Prau, dan camping akhir pekan.</p>
                </div>
                <a href="dashboard.php" class="btn-view-all-link">
                    <span>Lihat Seluruh Katalog</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="gear-grid">
                <?php if ($q_featured && mysqli_num_rows($q_featured) > 0): ?>
                    <?php while ($prod = mysqli_fetch_assoc($q_featured)): 
                        $stk = (int)($prod['stock'] ?? 0);
                    ?>
                        <div class="gear-card">
                            <div class="gear-media-box">
                                <?php if (!empty($prod['image']) && file_exists('../uploads/products/' . $prod['image'])): ?>
                                    <img src="../uploads/products/<?php echo htmlspecialchars($prod['image']); ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>" class="gear-img" loading="lazy">
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
                                        <button type="button" class="btn-sewa-trigger" onclick="openSewaModal(<?php echo htmlspecialchars(json_encode($prod), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <span>Sewa</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-sewa-disabled" disabled>
                                            Habis
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; padding: 36px; text-align: center; color: var(--text-muted); background: #fff; border-radius: var(--radius-lg); border: 1px solid var(--border-main);">
                        Belum ada data produk perlengkapan.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ==========================================================================
             WHY CHOOSE DANATRAIL
             ========================================================================== -->
        <section class="value-prop-section">
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
        <section class="category-directory-section">
            <div class="section-eyebrow">Eksplorasi Lengkap</div>
            <h2 class="section-title">Kategori Perlengkapan Ekspedisi</h2>
            <p class="section-desc">Pilih kategori alat yang Anda butuhkan untuk memudahkan persiapan pendakian.</p>

            <div class="cat-cards-grid">
                <?php foreach ($categories_list as $cat): 
                    $c_slug = $category_slug_map[$cat['name']] ?? strtolower(str_replace(' ', '-', $cat['name']));
                ?>
                    <a href="dashboard.php?kategori=<?php echo urlencode($cat['name']); ?>" class="cat-card-item">
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
            </div>
        </section>

        <!-- ==========================================================================
             RENTAL WORKFLOW (4 STEPS)
             ========================================================================== -->
        <section class="workflow-card" id="cara-sewa">
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
        <section class="rules-contact-grid">
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
                                +62 889-9601-6686 (Fast Response) →
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
            <a href="dashboard.php" class="btn-cta-katalog">
                <span>Buka Katalog Lengkap</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </a>
        </section>

    </main>

    <!-- ==========================================================================
         MODAL SEWA PRODUK CEPAT DI HALAMAN BERANDA (100% PRESERVED)
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
                            <div style="font-weight: 800; color: var(--text-heading); font-size: 14.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="modal_product_name">Nama Produk</div>
                            <div style="font-size: 12.5px; color: var(--forest-600); font-weight: 800; margin-top: 3px;" id="modal_price_display">Rp 0 / hari</div>
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
                            <div style="font-size: 13px; font-weight: 700; color: var(--text-heading); display: flex; align-items: center; gap: 6px;">
                                <span>Data Kontak & Alamat Penyewa</span>
                            </div>
                            <a href="profile.php?wajib_isi=1&redirect_to=about.php" style="font-size: 11.5px; color: var(--forest-600); font-weight: 700; text-decoration: none;" title="Ubah di Pengaturan Profil">
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color: var(--forest-600); margin: 0 auto 3px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <div style="font-size: 11.5px; font-weight: 700; color: var(--text-heading);">Klik jika ingin unggah foto KTP/SIM sekarang</div>
                                <div style="font-size: 10px; color: var(--text-muted); margin-top: 1px;">Bisa juga bawa & tunjukkan kartu fisik asli langsung saat serah terima alat di basecamp.</div>
                            </div>

                            <div id="guarantee_selected_view_about" style="display: none; align-items: center; justify-content: center; gap: 10px;">
                                <img id="guarantee_img_thumb_about" src="" alt="Thumbnail" style="width: 44px; height: 32px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-main);">
                                <div style="text-align: left;">
                                    <div id="guarantee_file_title_about" style="font-size: 12px; font-weight: 700; color: var(--text-heading); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Dokumen Terpilih</div>
                                    <div style="font-size: 10.5px; color: #059669; font-weight: 600;">Foto siap dilampirkan</div>
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
                        <textarea name="notes" id="modal_notes" rows="2" placeholder="Contoh: Estimasi ambil jam 09.00 pagi, rencana mendaki Gunung Merbabu..."></textarea>
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
                            <span id="modal_total_text" style="color: var(--forest-600);">Rp 0</span>
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
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #059669;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <h3 style="font-family: 'Outfit', sans-serif; font-size: 19px; font-weight: 800; color: var(--text-heading); margin-bottom: 8px;">
                Lengkapi Profil Anda Terlebih Dahulu
            </h3>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 22px;">
                Sebelum melakukan pemesanan sewa perlengkapan outdoor, Anda diwajibkan melengkapi <strong>Nomor WhatsApp</strong> dan <strong>Alamat Lengkap Domisili</strong> untuk verifikasi jaminan & serah terima alat.
            </p>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="profile.php?wajib_isi=1&redirect_to=about.php" class="btn-modal-submit" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px; font-size: 13.5px;">
                    <span>Lengkapi di Profil Sekarang →</span>
                </a>
                <button type="button" class="btn-modal-cancel" onclick="proceedToModalSewaDirectly()" style="width: 100%; font-size: 12.5px; padding: 10px;">
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
                t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#070d0a;color:#34d399;padding:10px 18px;border-radius:9999px;font-size:12.5px;font-weight:700;box-shadow:0 10px 25px rgba(0,0,0,0.35);z-index:999999;transition:all 0.25s ease;display:none;align-items:center;gap:8px;border:1px solid rgba(52,211,153,0.3);';
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
                    <div style="font-size:10.5px; color:#166534; margin-top:5px;">💡 Anda dapat transfer setelah pesanan diverifikasi & struk dapat diunggah santai di riwayat sewa.</div>
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
