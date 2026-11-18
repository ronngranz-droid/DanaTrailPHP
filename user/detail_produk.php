<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek autentikasi user
if (!isset($_SESSION['role'])) {
    header("location:../login.php?pesan=" . urlencode("Silakan login terlebih dahulu!"));
    exit;
}

include '../config/config.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
/** @var mysqli $conn */

// Ambil Data Profil User yang Sedang Login
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id && isset($_SESSION['nama'])) {
    $nama_s = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $u_res = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama_s' LIMIT 1");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $user_id = mysqli_fetch_assoc($u_res)['id'];
        $_SESSION['user_id'] = $user_id;
    }
}
$user_profile_data = null;
if ($user_id) {
    $u_chk = mysqli_query($conn, "SELECT id, name, email, phone, address FROM users WHERE id='$user_id'");
    if ($u_chk && mysqli_num_rows($u_chk) > 0) {
        $user_profile_data = mysqli_fetch_assoc($u_chk);
    }
}
$is_profile_complete = !empty($user_profile_data['phone']) && !empty($user_profile_data['address']);

// Ambil ID produk dari URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header("location:dashboard.php");
    exit;
}

// Query detail produk
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    header("location:dashboard.php?pesan=" . urlencode("Produk tidak ditemukan!"));
    exit;
}

$product_type  = $product['product_type'] ?? 'rental';
$category_name = $product['category'] ?? 'Outdoor Gear';
$stock_rental  = (int)($product['stock'] ?? 0);
$raw_price_rental = (float)($product['price'] ?? 0);
$stock_sale    = (int)($product['stock_sale'] ?? 0);
$raw_price_sale   = (float)($product['price_sale'] ?? 0);
$discount_pct  = floatval($product['discount_percent'] ?? 0);
$price_rental  = get_discounted_price($raw_price_rental, $discount_pct);
$price_sale    = get_discounted_price($raw_price_sale, $discount_pct);
$stock_count   = ($product_type === 'sale') ? $stock_sale : $stock_rental;
$price_per_day = $price_rental;

// Query produk terkait di kategori yang sama
$stmt_related = mysqli_prepare($conn, "SELECT * FROM products WHERE category = ? AND id != ? ORDER BY id DESC LIMIT 4");
mysqli_stmt_bind_param($stmt_related, "si", $category_name, $product_id);
mysqli_stmt_execute($stmt_related);
$related_products = mysqli_stmt_get_result($stmt_related);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> — DANATRAIL Outdoor Rental</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ==========================================================================
           DANATRAIL OUTDOOR DESIGN SYSTEM - PRODUCT DETAIL PAGE (OSPREY SPEC)
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
            --surface-subtle: #f4efe6;
            --border-color: #e7ded0;
            --border-light: #f0ebe0;
            
            --text-main: #111a14;
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
            --shadow-hover: 0 12px 28px -6px rgba(10, 38, 24, 0.12);

            --transition-fast: 0.15s ease;
            --transition-normal: 0.2s ease;
        }

        * {
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
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        /* SIDEBAR (260px) */
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
            overflow-y: auto;
            scrollbar-width: none;
            border-right: 1px solid rgba(255, 255, 255, 0.07);
            flex-shrink: 0;
            z-index: 90;
            transition: transform 0.25s ease;
        }

        .sidebar::-webkit-scrollbar {
            display: none;
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
            font-size: 10.5px;
            color: #92a499;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .sidebar-mobile-close {
            display: none;
            background: transparent;
            border: none;
            color: #ffffff;
            cursor: pointer;
        }

        .user-profile-badge {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-md);
            padding: 10px 12px;
            margin: 14px 0 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .user-avatar-initial {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--gold-500);
            color: var(--forest-950);
            font-weight: 800;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .status-indicator-dot {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 8px;
            height: 8px;
            background: #22c55e;
            border: 1.5px solid var(--forest-950);
            border-radius: 50%;
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
            padding: 24px 36px 120px 36px;
            overflow-y: auto;
            overflow-x: hidden;
            max-width: 1380px;
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
           PRODUCT DETAIL (OSPREY / HIGH-END OUTDOOR SPEC)
           ========================================================================== */
        .pdp-breadcrumb-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .pdp-breadcrumb {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-muted);
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 5px 14px;
            border-radius: var(--radius-pill);
        }

        .pdp-breadcrumb a {
            color: var(--forest-800);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        .pdp-breadcrumb a:hover {
            color: var(--forest-950);
            text-decoration: underline;
        }

        .pdp-breadcrumb span.sep {
            color: #b5c2ba;
        }

        .btn-back-catalog {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--forest-900);
            text-decoration: none;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            transition: all var(--transition-fast);
        }

        .btn-back-catalog:hover {
            background: var(--surface-subtle);
            border-color: #cbd8cf;
            transform: translateX(-2px);
        }

        /* 2-Column Product Showcase Layout */
        .pdp-grid-showcase {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 36px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 32px;
            box-shadow: var(--shadow-card);
            margin-bottom: 36px;
            align-items: start;
        }

        /* LEFT: Product Gallery */
        .pdp-gallery-col {
            display: flex;
            gap: 16px;
        }

        .pdp-thumbnails-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-shrink: 0;
            width: 72px;
        }

        .thumb-btn {
            width: 72px;
            height: 72px;
            border-radius: 12px;
            border: 1.5px solid var(--border-color);
            background: #ffffff;
            cursor: pointer;
            overflow: hidden;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-fast);
        }

        .thumb-btn:hover {
            border-color: var(--forest-800);
            transform: scale(1.03);
        }

        .thumb-btn.active {
            border-color: var(--forest-900);
            box-shadow: 0 0 0 2px var(--gold-500);
        }

        .thumb-btn img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            mix-blend-mode: multiply;
        }

        .pdp-main-image-wrap {
            flex: 1;
            position: relative;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            height: 480px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(10, 38, 24, 0.03);
        }

        .pdp-main-image {
            width: 100%;
            height: 100%;
            max-width: 400px;
            max-height: 400px;
            object-fit: contain;
            mix-blend-mode: multiply;
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 1;
        }

        .pdp-main-image-wrap:hover .pdp-main-image {
            transform: scale(1.05);
        }

        .pdp-expedition-badge {
            position: absolute;
            top: 16px;
            left: 16px;
            z-index: 10;
            background: rgba(4, 31, 20, 0.9);
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            pointer-events: none;
        }

        .pdp-badge-dot {
            width: 7px;
            height: 7px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 6px #10b981;
        }

        /* RIGHT: Product Details & Booking */
        .pdp-info-col {
            display: flex;
            flex-direction: column;
        }

        .pdp-eyebrow-tag {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--earth-brown);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pdp-eyebrow-tag .tag-sep {
            color: #cbd8cf;
        }

        .pdp-title {
            font-size: 34px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.2;
            letter-spacing: -0.03em;
            margin-bottom: 10px;
        }

        .pdp-rating-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 13px;
        }

        .star-rating-box {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            color: var(--gold-500);
        }

        .review-count-txt {
            color: var(--text-muted);
            font-weight: 600;
        }

        .verified-badge-txt {
            color: var(--emerald-600);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Price & Stock Strip */
        .pdp-price-stock-box {
            background: #faf8f5;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pdp-rate-display {
            display: flex;
            flex-direction: column;
        }

        .pdp-rate-label {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--text-muted);
        }

        .pdp-price-amount {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: var(--forest-950);
            line-height: 1.1;
        }

        .pdp-price-amount span {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .stock-indicator-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            font-size: 12px;
            font-weight: 700;
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .stock-indicator-pill.low {
            background: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
        }

        .stock-indicator-pill.empty {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        /* Key Feature Highlights Grid */
        .pdp-specs-mini-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 24px;
        }

        .spec-mini-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
        }

        .spec-icon-box {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--surface-subtle);
            color: var(--forest-900);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .spec-txt-group {
            display: flex;
            flex-direction: column;
        }

        .spec-label {
            font-size: 10.5px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .spec-val {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
        }

        /* Direct Booking Form Card */
        .pdp-booking-box {
            background: #ffffff;
            border: 1.5px solid rgba(22, 78, 50, 0.18);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 4px 14px rgba(10, 38, 24, 0.04);
        }

        .booking-box-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--forest-950);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-grid-pdp {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .pdp-field-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 12px;
        }

        .pdp-field-group label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
        }

        .pdp-field-group input,
        .pdp-field-group select,
        .pdp-field-group textarea {
            width: 100%;
            padding: 10px 12px;
            background: #faf8f5;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 13px;
            color: var(--text-main);
            outline: none;
            transition: all var(--transition-fast);
        }

        .pdp-field-group input:focus,
        .pdp-field-group select:focus,
        .pdp-field-group textarea:focus {
            background: #ffffff;
            border-color: var(--forest-800);
            box-shadow: 0 0 0 3px rgba(16, 56, 36, 0.1);
        }

        .pdp-calc-summary {
            background: #f4efe6;
            border-radius: var(--radius-md);
            padding: 12px 14px;
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .calc-row-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
            color: var(--text-body);
        }

        .calc-row-flex.total {
            border-top: 1px solid #e0d8c8;
            padding-top: 8px;
            margin-top: 4px;
            font-size: 14.5px;
            font-weight: 800;
            color: var(--forest-950);
        }

        .btn-submit-rent-now {
            width: 100%;
            background: var(--forest-900);
            color: var(--gold-500);
            border: none;
            padding: 14px;
            border-radius: var(--radius-md);
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(10, 38, 24, 0.2);
            transition: all var(--transition-fast);
        }

        .btn-submit-rent-now:hover {
            background: var(--forest-800);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(10, 38, 24, 0.3);
        }

        .pdp-type-tabs {
            display: flex;
            background: #f1f5f9;
            border-radius: var(--radius-md);
            padding: 4px;
            gap: 4px;
            margin-bottom: 20px;
        }

        .pdp-type-tab-btn {
            flex: 1;
            padding: 11px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .pdp-type-tab-btn.active {
            background: #ffffff;
            color: var(--forest-950);
            box-shadow: var(--shadow-subtle);
            font-weight: 800;
        }

        .btn-pdp-cart {
            width: 100%;
            background: #ffffff;
            color: var(--forest-900);
            border: 2px solid var(--forest-900);
            padding: 13px;
            border-radius: var(--radius-md);
            font-family: 'Outfit', sans-serif;
            font-size: 14.5px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
            text-decoration: none;
        }

        .btn-pdp-cart:hover {
            background: var(--surface-subtle);
            color: var(--forest-950);
        }

        .pdp-buy-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 14px;
        }

        .badge-tag-rental {
            background: rgba(10, 38, 24, 0.1);
            color: var(--forest-800);
            border: 1px solid rgba(10, 38, 24, 0.25);
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .badge-tag-sale {
            background: rgba(217, 119, 6, 0.12);
            color: #b45309;
            border: 1px solid rgba(217, 119, 6, 0.3);
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .badge-tag-both {
            background: rgba(15, 118, 110, 0.12);
            color: #0f766e;
            border: 1px solid rgba(15, 118, 110, 0.3);
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        /* ==========================================================================
           TABBED SPECIFICATIONS & POLICIES SECTION
           ========================================================================== */
        .pdp-details-tabs-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 28px 32px;
            box-shadow: var(--shadow-card);
            margin-bottom: 36px;
        }

        .pdp-tab-headers {
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid var(--border-light);
            margin-bottom: 24px;
            overflow-x: auto;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 10px 16px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            white-space: nowrap;
            transition: all var(--transition-fast);
        }

        .tab-btn:hover {
            color: var(--forest-900);
        }

        .tab-btn.active {
            color: var(--forest-950);
            font-weight: 800;
            border-bottom-color: var(--forest-900);
        }

        .tab-pane {
            display: none;
            animation: fadeIn 0.25s ease;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .desc-text-content {
            font-size: 14.5px;
            color: var(--text-body);
            line-height: 1.7;
            max-width: 900px;
        }

        .specs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        .specs-table tr {
            border-bottom: 1px solid var(--border-light);
        }

        .specs-table td {
            padding: 12px 16px;
        }

        .specs-table td.spec-title {
            width: 240px;
            font-weight: 700;
            color: var(--text-main);
            background: #faf8f5;
        }

        /* RELATED GEAR CAROUSEL / GRID */
        .related-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .related-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--forest-950);
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }

        .gear-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            transition: all var(--transition-fast);
        }

        .gear-card:hover {
            transform: translateY(-3px);
            border-color: #cbd8cf;
            box-shadow: var(--shadow-hover);
        }

        .gear-img-wrap {
            height: 160px;
            background: #f4efe6;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .gear-photo {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 12px;
            transition: transform 0.3s ease;
        }

        .gear-card:hover .gear-photo {
            transform: scale(1.06);
        }

        .gear-card-body {
            padding: 14px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .gear-category-row {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--earth-brown);
            margin-bottom: 4px;
        }

        .gear-card-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 10px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .gear-card-price {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: var(--forest-950);
            margin-top: auto;
        }

        .gear-card-price span {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* RESPONSIVE */
        @media (max-width: 980px) {
            .pdp-grid-showcase {
                grid-template-columns: 1fr;
                padding: 24px;
            }
            .pdp-main-image-wrap {
                height: 360px;
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
                padding: 16px 14px 40px 14px;
            }
            .pdp-gallery-col {
                flex-direction: column-reverse;
            }
            .pdp-thumbnails-list {
                flex-direction: row;
                width: 100%;
                overflow-x: auto;
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

        <!-- Breadcrumb & Back Row -->
        <div class="pdp-breadcrumb-row">
            <div class="pdp-breadcrumb">
                <a href="about.php">Beranda</a>
                <span class="sep">/</span>
                <a href="dashboard.php">Katalog</a>
                <span class="sep">/</span>
                <a href="dashboard.php?kategori=<?php echo urlencode($category_name); ?>"><?php echo htmlspecialchars($category_name); ?></a>
                <span class="sep">/</span>
                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
            </div>

            <a href="dashboard.php" class="btn-back-catalog">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>Kembali ke Katalog</span>
            </a>
        </div>

        <!-- Main Product Showcase Grid (Osprey Style) -->
        <div class="pdp-grid-showcase">

            <!-- LEFT: Interactive Gallery -->
            <div class="pdp-gallery-col">
                <div class="pdp-thumbnails-list">
                    <?php 
                    $main_img_path = (!empty($product['image']) && file_exists('../uploads/products/' . $product['image'])) 
                                      ? '../uploads/products/' . htmlspecialchars($product['image']) 
                                      : '../assets/logo.png';
                    ?>
                    <button type="button" class="thumb-btn active" onclick="switchGalleryPhoto('<?php echo $main_img_path; ?>', this)" aria-label="Foto Utama">
                        <img src="<?php echo $main_img_path; ?>" alt="Thumb 1">
                    </button>
                    <button type="button" class="thumb-btn" onclick="switchGalleryPhoto('<?php echo $main_img_path; ?>', this)" aria-label="Foto Sudut 2">
                        <img src="<?php echo $main_img_path; ?>" alt="Thumb 2" style="transform: scaleX(-1);">
                    </button>
                </div>

                <div class="pdp-main-image-wrap">
                    <div class="pdp-expedition-badge">
                        <span class="pdp-badge-dot"></span>
                        <span>Expedition Grade</span>
                    </div>

                    <img src="<?php echo $main_img_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="pdp-main-image" id="pdpMainImageDisplay">
                </div>
            </div>

            <!-- RIGHT: Product Details & Booking -->
            <div class="pdp-info-col">
                <div class="pdp-eyebrow-tag">
                    <span>DANATRAIL OUTDOOR</span>
                    <span class="tag-sep">•</span>
                    <span><?php echo htmlspecialchars(strtoupper($category_name)); ?></span>
                    <span class="tag-sep">•</span>
                    <?php if ($product_type === 'sale'): ?>
                        <span class="badge-tag-sale">Dijual</span>
                    <?php elseif ($product_type === 'both'): ?>
                        <span class="badge-tag-both">Rental & Dijual</span>
                    <?php else: ?>
                        <span class="badge-tag-rental">Rental</span>
                    <?php endif; ?>
                </div>

                <h1 class="pdp-title"><?php echo htmlspecialchars($product['name']); ?></h1>

                <!-- Rating and Verified Badge -->
                <div class="pdp-rating-row">
                    <div class="star-rating-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <strong style="color: var(--text-main); margin-left: 4px;">4.9</strong>
                    </div>
                    <span class="review-count-txt">(120+ Pendaki Puas)</span>
                    <span class="verified-badge-txt">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Steril & Lolos Uji Fisik</span>
                    </span>
                </div>

                <?php if ($product_type === 'both'): ?>
                    <!-- Dual Mode Switcher (Sewa vs Beli) -->
                    <div class="pdp-type-tabs">
                        <button type="button" class="pdp-type-tab-btn active" id="btnModeRental" onclick="switchPdpMode('rental')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Sewa Perlengkapan</span>
                        </button>
                        <button type="button" class="pdp-type-tab-btn" id="btnModeSale" onclick="switchPdpMode('sale')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span>Beli Barang Baru</span>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- ==========================================================
                     1. RENTAL CONTAINER (SEWA)
                     ========================================================== -->
                <div id="pdpRentalContainer" style="<?php echo ($product_type === 'sale') ? 'display: none;' : ''; ?>">
                    <!-- Price and Live Stock -->
                    <div class="pdp-price-stock-box">
                        <div class="pdp-rate-display">
                            <span class="pdp-rate-label">Tarif Sewa</span>
                            <?php if ($discount_pct > 0): ?>
                                <div style="font-size: 13px; text-decoration: line-through; color: #94a3b8; font-weight: 500;">
                                    Rp <?php echo number_format($raw_price_rental, 0, ',', '.'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="pdp-price-amount">
                                Rp <?php echo number_format($price_rental, 0, ',', '.'); ?>
                                <span>/ hari (24 Jam)</span>
                                <?php if ($discount_pct > 0): ?>
                                    <span style="background: #fee2e2; color: #b91c1c; font-size: 11.5px; font-weight: 700; padding: 2px 7px; border-radius: 4px; border: 1px solid #fca5a5; margin-left: 6px;">-<?php echo rtrim(rtrim(number_format($discount_pct, 2), '0'), '.'); ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($stock_rental > 3): ?>
                            <div class="stock-indicator-pill">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Tersedia <?php echo $stock_rental; ?> Unit Rental Ready</span>
                            </div>
                        <?php elseif ($stock_rental > 0): ?>
                            <div class="stock-indicator-pill low">
                                <span>Sisa <?php echo $stock_rental; ?> Unit Terakhir</span>
                            </div>
                        <?php else: ?>
                            <div class="stock-indicator-pill empty">
                                <span>Stok Rental Habis</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Specs Quick Grid -->
                    <div class="pdp-specs-mini-grid">
                        <div class="spec-mini-item">
                            <div class="spec-icon-box">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <div class="spec-txt-group">
                                <span class="spec-label">Kategori Alat</span>
                                <span class="spec-val"><?php echo htmlspecialchars($category_name); ?></span>
                            </div>
                        </div>

                        <div class="spec-mini-item">
                            <div class="spec-icon-box">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="spec-txt-group">
                                <span class="spec-label">Lokasi Pengambilan</span>
                                <span class="spec-val">Basecamp DANATRAIL</span>
                            </div>
                        </div>

                        <div class="spec-mini-item">
                            <div class="spec-icon-box">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div class="spec-txt-group">
                                <span class="spec-label">Jaminan Kondisi</span>
                                <span class="spec-val">100% Siap Ekspedisi</span>
                            </div>
                        </div>

                        <div class="spec-mini-item">
                            <div class="spec-icon-box">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="spec-txt-group">
                                <span class="spec-label">Layanan</span>
                                <span class="spec-val">Booking Instan 24/7</span>
                            </div>
                        </div>
                    </div>

                    <!-- Direct Booking Form (Action Box) -->
                    <div class="pdp-booking-box">
                        <div class="booking-box-title">
                            <span>Formulir Pemesanan Sewa</span>
                            <span style="font-size: 11px; color: var(--emerald-600); font-weight: 700;">Konfirmasi Langsung</span>
                        </div>

                        <?php if (!$is_profile_complete): ?>
                            <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 10px;">
                                <div style="flex-shrink: 0; margin-top: 1px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #b45309;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div style="font-size: 12px; color: #92400e; line-height: 1.4;">
                                    <strong style="color: #78350f; display: block; margin-bottom: 2px;">Wajib Lengkapi Data Diri:</strong>
                                    Sebelum memesan, pastikan No. WhatsApp & Alamat terisi. 
                                    <a href="profile.php?wajib_isi=1&redirect_to=<?php echo urlencode('detail_produk.php?id=' . $product_id); ?>" style="color: #b45309; font-weight: 700; text-decoration: underline;">
                                        Lengkapi di Profil →
                                    </a> atau isi langsung pada formulir di bawah.
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="sewa_aksi.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" id="pdp_price_rate" value="<?php echo $price_rental; ?>">

                            <div class="form-grid-pdp">
                                <div class="pdp-field-group">
                                    <label for="pdp_rental_date">Tanggal Ambil Sewa *</label>
                                    <input type="date" name="rental_date" id="pdp_rental_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" onchange="recalculatePdpTotal()">
                                </div>
                                <div class="pdp-field-group">
                                    <label for="pdp_return_date">Tanggal Kembali *</label>
                                    <input type="date" name="return_date" id="pdp_return_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" onchange="recalculatePdpTotal()">
                                </div>
                            </div>

                            <div class="form-grid-pdp">
                                <div class="pdp-field-group">
                                    <label for="pdp_quantity">Jumlah Unit *</label>
                                    <input type="number" name="quantity" id="pdp_quantity" value="1" min="1" max="<?php echo max(1, $stock_rental); ?>" required onchange="recalculatePdpTotal()" onkeyup="recalculatePdpTotal()">
                                </div>
                                <div class="pdp-field-group">
                                    <label for="pdp_guarantee">Jenis Dokumen Jaminan *</label>
                                    <select name="guarantee_type" id="pdp_guarantee" required>
                                        <option value="KTP Asli">KTP Asli</option>
                                        <option value="SIM A / C Asli">SIM A / C Asli</option>
                                        <option value="KTM (Kartu Mahasiswa)">KTM (Kartu Mahasiswa)</option>
                                        <option value="Paspor Asli">Paspor Asli</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Data Informasi Pribadi Penyewa -->
                            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                    <div style="font-size: 13px; font-weight: 700; color: var(--forest-950); display: flex; align-items: center; gap: 6px;">
                                        <span>Data Kontak & Alamat Penyewa</span>
                                    </div>
                                    <a href="profile.php?wajib_isi=1&redirect_to=<?php echo urlencode('detail_produk.php?id=' . $product_id); ?>" style="font-size: 11.5px; color: var(--forest-700); font-weight: 700; text-decoration: none;" title="Ubah di Pengaturan Profil">
                                        Lengkapi di Profil →
                                    </a>
                                </div>

                                <div class="form-grid-pdp" style="margin-bottom: 10px;">
                                    <div class="pdp-field-group" style="margin-bottom: 0;">
                                        <label for="pdp_phone" style="font-size: 12px;">No. WhatsApp *</label>
                                        <input type="tel" name="phone" id="pdp_phone" required placeholder="Contoh: 081234567890" value="<?php echo htmlspecialchars($user_profile_data['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="pdp-field-group" style="margin-bottom: 0;">
                                        <label for="pdp_user_name_static" style="font-size: 12px;">Nama Akun</label>
                                        <input type="text" id="pdp_user_name_static" readonly disabled value="<?php echo htmlspecialchars($user_profile_data['name'] ?? $_SESSION['nama']); ?>" style="background: #f1f5f9; color: #64748b;">
                                    </div>
                                </div>

                                <div class="pdp-field-group" style="margin-bottom: 0;">
                                    <label for="pdp_address" style="font-size: 12px;">Alamat Lengkap Domisili *</label>
                                    <textarea name="address" id="pdp_address" rows="2" required placeholder="Alamat lengkap tempat tinggal sesuai KTP / domisili saat ini..."><?php echo htmlspecialchars($user_profile_data['address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Upload Foto Dokumen Jaminan Identitas (KTP/SIM/KTM) -->
                            <div class="pdp-field-group">
                                <label for="pdp_guarantee_photo">
                                    Unggah Foto Kartu Identitas Jaminan (KTP / SIM / KTM) 
                                    <span style="font-weight: normal; color: var(--text-muted); font-size: 11px;">(Opsional - Bisa bawa fisik saat ambil)</span>
                                </label>
                                <div style="border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 14px; text-align: center; background: #fafdfb; position: relative; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('pdp_guarantee_photo').click()">
                                    <input type="file" name="guarantee_photo" id="pdp_guarantee_photo" accept="image/*,application/pdf" style="display: none;" onchange="previewPdpGuarantee(this)">
                                    
                                    <div id="pdp_guarantee_prompt">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color: var(--forest-700); margin: 0 auto 6px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <div style="font-size: 12.5px; font-weight: 700; color: var(--forest-950);">Klik untuk Unggah Foto KTP / Identitas (Opsional)</div>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Format JPG, PNG, WEBP, atau PDF (Maksimal 5MB)</div>
                                    </div>

                                    <div id="pdp_guarantee_selected" style="display: none; align-items: center; justify-content: center; gap: 12px;">
                                        <img id="pdp_guarantee_img_thumb" src="" alt="Thumbnail" style="width: 48px; height: 36px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                        <div style="text-align: left;">
                                            <div id="pdp_guarantee_filename" style="font-size: 12.5px; font-weight: 700; color: var(--forest-950); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Dokumen Terpilih</div>
                                            <div style="font-size: 11px; color: var(--emerald-600); font-weight: 600;">Foto siap dikirim</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Informasi Jaminan Fisik Santai di Basecamp -->
                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 10px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div style="font-size: 12px; color: #166534; line-height: 1.45;">
                                    <strong style="color: #14532d; display: block; margin-bottom: 2px;">Jaminan Identitas Santai:</strong>
                                    Anda dapat mengunggah foto sekarang atau cukup bawa fisik kartu identitas asli (KTP/SIM/KTM) langsung saat serah terima barang di Basecamp DANATRAIL tanpa ribet.
                                </div>
                            </div>

                            <!-- Metode Pembayaran -->
                            <div class="pdp-field-group">
                                <label for="pdp_payment_method">Metode Pembayaran *</label>
                                <select name="payment_method" id="pdp_payment_method" required onchange="updatePdpPaymentInstruction(this.value)">
                                    <option value="Transfer Bank BCA">Transfer Bank BCA (7310-0987-6543)</option>
                                    <option value="Transfer Bank Mandiri">Transfer Bank Mandiri (137-00-1928374-1)</option>
                                    <option value="Transfer Bank BRI">Transfer Bank BRI (0123-01-098765-50-8)</option>
                                    <option value="QRIS / GoPay / OVO">QRIS / E-Wallet (GoPay, OVO, ShopeePay, DANA)</option>
                                    <option value="Bayar di Basecamp (Cash / Debit)">Bayar di Basecamp (Cash / Debit saat Ambil Alat)</option>
                                </select>
                            </div>

                            <!-- Kotak Panduan Rekening & Salin Cepat -->
                            <div id="pdpPaymentInstructionBox" style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                    <div>
                                        <div id="pdpPayBankTitle" style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Transfer Bank BCA:</div>
                                        <div id="pdpPayAccountNum" style="font-family: monospace; font-size: 15px; font-weight: 800; color: #0284c7; margin-top: 1px;">7310-0987-6543</div>
                                        <div id="pdpPayAccountName" style="font-size: 11px; color: var(--text-body);">a/n PT DANATRAIL INDONESIA</div>
                                    </div>
                                    <button type="button" id="pdpBtnCopyAcc" onclick="copyPdpAccount()" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #0284c7; color: #ffffff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                        <span>Salin No. Rek</span>
                                    </button>
                                </div>
                                <div id="pdpPayNote" style="margin-top: 8px; font-size: 11.5px; color: var(--text-muted); line-height: 1.4; border-top: 1px dashed var(--border-color); padding-top: 6px;">
                                    Simpan nomor rekening di atas untuk transfer santai setelah pemesanan diajukan.
                                </div>
                            </div>

                            <div class="pdp-field-group">
                                <label for="pdp_notes">Catatan Sewa (Opsional)</label>
                                <textarea name="notes" id="pdp_notes" rows="2" placeholder="Contoh: Estimasi ambil jam 08.30 pagi, rencana pendakian ke Gunung Lawu..."></textarea>
                            </div>

                            <!-- Diskon Otomatis Banner -->
                            <div id="pdpVoucherFeedback" style="display: none; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 10px 14px; margin-bottom: 16px; font-size: 12px; color: #065f46;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <strong id="pdpVoucherFeedbackText">Diskon otomatis diterapkan!</strong>
                                </div>
                            </div>

                            <!-- Realtime Price Calculation -->
                            <div class="pdp-calc-summary">
                                <div class="calc-row-flex">
                                    <span>Durasi Sewa:</span>
                                    <strong id="pdp_duration_text">1 Hari (24 Jam)</strong>
                                </div>
                                <div class="calc-row-flex">
                                    <span>Tarif Dasar / Hari:</span>
                                    <span>Rp <?php echo number_format($price_rental, 0, ',', '.'); ?></span>
                                </div>
                                <div class="calc-row-flex">
                                    <span>Subtotal Sewa:</span>
                                    <strong id="pdp_subtotal_display">Rp <?php echo number_format($price_rental, 0, ',', '.'); ?></strong>
                                </div>
                                <div class="calc-row-flex" id="pdp_discount_row" style="display: none; color: #059669; font-weight: 700;">
                                    <span id="pdp_discount_label">Potongan Diskon:</span>
                                    <span id="pdp_discount_display">-Rp 0</span>
                                </div>
                                <div class="calc-row-flex total">
                                    <span>Total Estimasi Biaya:</span>
                                    <span id="pdp_total_display" style="color: var(--forest-900);">Rp <?php echo number_format($price_rental, 0, ',', '.'); ?></span>
                                </div>
                            </div>

                            <?php if ($stock_rental > 0): ?>
                                <button type="submit" name="sewa_produk" class="btn-submit-rent-now">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>Sewa Sekarang</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-submit-rent-now" disabled style="opacity: 0.5; cursor: not-allowed;">
                                    <span>Stok Rental Sedang Habis</span>
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- ==========================================================
                     2. SALE CONTAINER (TOKO / BELI)
                     ========================================================== -->
                <div id="pdpSaleContainer" style="<?php echo ($product_type === 'rental') ? 'display: none;' : ''; ?>">
                    <!-- Price and Live Stock for Buying -->
                    <div class="pdp-price-stock-box">
                        <div class="pdp-rate-display">
                            <span class="pdp-rate-label" style="color: #b45309;">Harga Pembelian</span>
                            <?php if ($discount_pct > 0): ?>
                                <div style="font-size: 13px; text-decoration: line-through; color: #94a3b8; font-weight: 500;">
                                    Rp <?php echo number_format($raw_price_sale, 0, ',', '.'); ?>
                                </div>
                            <?php endif; ?>
                            <div class="pdp-price-amount" style="color: #b45309;">
                                Rp <?php echo number_format($price_sale, 0, ',', '.'); ?>
                                <span>/ Unit Baru</span>
                                <?php if ($discount_pct > 0): ?>
                                    <span style="background: #fee2e2; color: #b91c1c; font-size: 11.5px; font-weight: 700; padding: 2px 7px; border-radius: 4px; border: 1px solid #fca5a5; margin-left: 6px;">-<?php echo rtrim(rtrim(number_format($discount_pct, 2), '0'), '.'); ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($stock_sale > 3): ?>
                            <div class="stock-indicator-pill" style="background: #fef3c7; color: #92400e; border-color: #fde68a;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Stok Toko: <?php echo $stock_sale; ?> Unit Ready</span>
                            </div>
                        <?php elseif ($stock_sale > 0): ?>
                            <div class="stock-indicator-pill low">
                                <span>Sisa <?php echo $stock_sale; ?> Unit Terakhir</span>
                            </div>
                        <?php else: ?>
                            <div class="stock-indicator-pill empty">
                                <span>Stok Toko Sedang Habis</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Direct Purchase / Cart Form -->
                    <div class="pdp-booking-box">
                        <div class="booking-box-title">
                            <span>Beli Produk Baru</span>
                            <span style="font-size: 11px; color: #b45309; font-weight: 700;">100% Produk Original</span>
                        </div>

                        <form action="checkout.php" method="GET">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" id="pdp_sale_price_rate" value="<?php echo $price_sale; ?>">

                            <div class="pdp-field-group">
                                <label for="pdp_buy_quantity">Jumlah Pembelian (Unit) *</label>
                                <input type="number" name="quantity" id="pdp_buy_quantity" value="1" min="1" max="<?php echo max(1, $stock_sale); ?>" required oninput="recalculatePdpSaleTotal()">
                            </div>

                            <!-- Fast shipping & Basecamp pickup info -->
                            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 16px;">
                                <div style="font-size: 12px; color: var(--text-body); line-height: 1.5;">
                                    <strong style="color: var(--forest-950); display: block; margin-bottom: 2px;">Layanan Pengiriman & Garansi:</strong>
                                    Pengiriman ke seluruh Indonesia via JNE, J&T, SiCepat, atau ambil langsung di Basecamp DANATRAIL. Tidak memerlukan jaminan identitas fisik.
                                </div>
                            </div>

                            <!-- Realtime Sale Subtotal -->
                            <div class="pdp-calc-summary">
                                <div class="calc-row-flex">
                                    <span>Harga Satuan:</span>
                                    <span>Rp <?php echo number_format($price_sale, 0, ',', '.'); ?></span>
                                </div>
                                <div class="calc-row-flex total">
                                    <span>Subtotal Pembelian:</span>
                                    <span id="pdp_sale_total_display" style="color: #b45309;">Rp <?php echo number_format($price_sale, 0, ',', '.'); ?></span>
                                </div>
                            </div>

                            <?php if ($stock_sale > 0): ?>
                                <button type="submit" class="btn-submit-rent-now" style="background: linear-gradient(135deg, #d97706 0%, #b45309 100%); color: #ffffff;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                    <span>Beli Sekarang</span>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-submit-rent-now" disabled style="opacity: 0.5; cursor: not-allowed; background: #94a3b8; color: #ffffff;">
                                    <span>Stok Toko Sedang Habis</span>
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <!-- Tabbed Specifications & Descriptions (Osprey Layout) -->
        <section class="pdp-details-tabs-card">
            <div class="pdp-tab-headers">
                <button type="button" class="tab-btn active" onclick="switchTab('tab-desc', this)">Deskripsi & Fitur</button>
                <button type="button" class="tab-btn" onclick="switchTab('tab-specs', this)">Spesifikasi Teknis</button>
                <button type="button" class="tab-btn" onclick="switchTab('tab-terms', this)">Ketentuan & Jaminan</button>
            </div>

            <div class="tab-content-wrapper">
                <!-- Tab 1: Deskripsi -->
                <div class="tab-pane active" id="tab-desc">
                    <div class="desc-text-content">
                        <?php if (!empty($product['description'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        <?php else: ?>
                            <p>
                                <strong><?php echo htmlspecialchars($product['name']); ?></strong> dirancang khusus untuk memenuhi kebutuhan pendaki dan petualang yang menginginkan keandalan tertinggi di medan terbuka. Perlengkapan ini dibuat dari material bermutu tinggi yang ringan, tahan air, dan tahan cuaca ekstrem.
                            </p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 18px; padding: 14px 18px; background: #faf8f5; border-left: 4px solid var(--forest-800); border-radius: 6px;">
                            <strong style="color: var(--forest-950); display: block; margin-bottom: 4px;">Keunggulan Standar DANATRAIL:</strong>
                            <ul style="padding-left: 18px; color: var(--text-body); font-size: 13.5px; line-height: 1.6;">
                                <li>Pemeriksaan fisik menyeluruh sebelum disewa atau dikirim ke pembeli.</li>
                                <li>Pembersihan & sterilisasi anti-jamur standar petualangan outdoor.</li>
                                <li>Dukungan konsultasi peralatan langsung bersama tim basecamp DANATRAIL.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Spesifikasi Teknis -->
                <div class="tab-pane" id="tab-specs">
                    <table class="specs-table">
                        <tr>
                            <td class="spec-title">Nama Produk</td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                        </tr>
                        <tr>
                            <td class="spec-title">Kategori</td>
                            <td><?php echo htmlspecialchars($category_name); ?></td>
                        </tr>
                        <tr>
                            <td class="spec-title">Tipe Layanan</td>
                            <td>
                                <?php 
                                    if ($product_type === 'sale') echo 'Dijual (Baru)';
                                    elseif ($product_type === 'both') echo 'Rental (Sewa) & Dijual (Beli Baru)';
                                    else echo 'Rental (Sewa)';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="spec-title">Kondisi Alat</td>
                            <td>Sangat Baik / Terawat Berkala</td>
                        </tr>
                        <tr>
                            <td class="spec-title">Peruntukan Medan</td>
                            <td>Pendakian Gunung, Camping Ceria, Ekspedisi Hutan, Bushcraft</td>
                        </tr>
                        <tr>
                            <td class="spec-title">Lokasi Basecamp</td>
                            <td>Basecamp Utama DANATRAIL Outdoor</td>
                        </tr>
                    </table>
                </div>

                <!-- Tab 3: Ketentuan Sewa / Beli -->
                <div class="tab-pane" id="tab-terms">
                    <div class="desc-text-content">
                        <p><strong>Ketentuan Peminjaman & Pembelian:</strong></p>
                        <ol style="padding-left: 20px; line-height: 1.8; margin-top: 8px;">
                            <li><strong>Untuk Rental:</strong> Penyewa wajib meninggalkan 1 (satu) dokumen identitas fisik asli (KTP / SIM / KTM) saat serah terima barang di Basecamp.</li>
                            <li><strong>Untuk Pembelian:</strong> Barang dikirim via kurir ekspedisi resmi atau dapat diambil langsung di Basecamp tanpa dokumen jaminan.</li>
                            <li>Pengambilan dan pengembalian rental dilayani pada jam operasional (08.00 – 21.00 WIB).</li>
                            <li>Penyewa diharapkan menjaga keutuhan dan kebersihan perlengkapan selama penggunaan.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        <!-- Related Equipment Grid -->
        <?php if ($related_products && mysqli_num_rows($related_products) > 0): ?>
            <section class="related-gear-section">
                <div class="related-section-header">
                    <h2 class="related-title">Perlengkapan Lain di Kategori Ini</h2>
                    <a href="dashboard.php?kategori=<?php echo urlencode($category_name); ?>" style="font-size: 13px; font-weight: 700; color: var(--forest-800); text-decoration: none;">Lihat Semua &rarr;</a>
                </div>

                <div class="related-grid">
                    <?php while ($rp = mysqli_fetch_assoc($related_products)): 
                        $rp_img = (!empty($rp['image']) && file_exists('../uploads/products/' . $rp['image'])) 
                                  ? '../uploads/products/' . htmlspecialchars($rp['image']) 
                                  : '../assets/logo.png';
                        $rp_type = $rp['product_type'] ?? 'rental';
                    ?>
                        <a href="detail_produk.php?id=<?php echo $rp['id']; ?>" class="gear-card">
                            <div class="gear-img-wrap">
                                <img src="<?php echo $rp_img; ?>" alt="<?php echo htmlspecialchars($rp['name']); ?>" class="gear-photo" loading="lazy">
                            </div>
                            <div class="gear-card-body">
                                <span class="gear-category-row"><?php echo htmlspecialchars($rp['category']); ?></span>
                                <h3 class="gear-card-title"><?php echo htmlspecialchars($rp['name']); ?></h3>
                                <div class="gear-card-price">
                                    <?php if ($rp_type === 'sale'): ?>
                                        Rp <?php echo number_format($rp['price_sale'], 0, ',', '.'); ?>
                                        <span>beli</span>
                                    <?php else: ?>
                                        Rp <?php echo number_format($rp['price'], 0, ',', '.'); ?>
                                        <span>/hari</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- JavaScript Interactivity -->
    <script>
        function switchGalleryPhoto(src, btn) {
            document.getElementById('pdpMainImageDisplay').src = src;
            document.querySelectorAll('.thumb-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            const target = document.getElementById(tabId);
            if (target) target.classList.add('active');
            btn.classList.add('active');
        }

        function switchPdpMode(mode) {
            const rentalContainer = document.getElementById('pdpRentalContainer');
            const saleContainer = document.getElementById('pdpSaleContainer');
            const btnRental = document.getElementById('btnModeRental');
            const btnSale = document.getElementById('btnModeSale');

            if (mode === 'sale') {
                if (rentalContainer) rentalContainer.style.display = 'none';
                if (saleContainer) saleContainer.style.display = 'block';
                if (btnRental) btnRental.classList.remove('active');
                if (btnSale) btnSale.classList.add('active');
                recalculatePdpSaleTotal();
            } else {
                if (rentalContainer) rentalContainer.style.display = 'block';
                if (saleContainer) saleContainer.style.display = 'none';
                if (btnRental) btnRental.classList.add('active');
                if (btnSale) btnSale.classList.remove('active');
                recalculatePdpTotal();
            }
        }

        function previewPdpGuarantee(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const prompt = document.getElementById('pdp_guarantee_prompt');
                    const selected = document.getElementById('pdp_guarantee_selected');
                    const thumb = document.getElementById('pdp_guarantee_img_thumb');
                    const fname = document.getElementById('pdp_guarantee_filename');

                    if (thumb) thumb.src = e.target.result;
                    if (fname) fname.innerText = file.name;
                    if (prompt) prompt.style.display = 'none';
                    if (selected) selected.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        }

        const currentPdpProductId = <?php echo (int)($product['id'] ?? 0); ?>;
        const currentPdpCategoryName = <?php echo json_encode($category_name ?? ''); ?>;

        function applyPdpVoucher() {
            recalculatePdpTotal(true);
        }

        function recalculatePdpTotal(isExplicitVoucherClick = false) {
            const priceEl = document.getElementById('pdp_price_rate');
            if (!priceEl) return;
            const priceRate = parseFloat(priceEl.value) || 0;
            const startVal = document.getElementById('pdp_rental_date') ? document.getElementById('pdp_rental_date').value : '';
            const returnVal = document.getElementById('pdp_return_date') ? document.getElementById('pdp_return_date').value : '';
            const qty = document.getElementById('pdp_quantity') ? (parseInt(document.getElementById('pdp_quantity').value) || 1) : 1;

            if (!startVal || !returnVal) return;

            const start = new Date(startVal);
            const end = new Date(returnVal);

            let diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            if (diffDays <= 0) diffDays = 1;

            const subtotal = priceRate * diffDays * qty;

            const durEl = document.getElementById('pdp_duration_text');
            const subEl = document.getElementById('pdp_subtotal_display');
            const totEl = document.getElementById('pdp_total_display');
            if (durEl) durEl.innerText = diffDays + ' Hari (' + (diffDays * 24) + ' Jam)';
            if (subEl) subEl.innerText = 'Rp ' + Number(subtotal).toLocaleString('id-ID');

            // Cek AJAX diskon otomatis
            const formData = new FormData();
            formData.append('type', 'rental');
            formData.append('subtotal', subtotal);
            formData.append('rental_days', diffDays);
            formData.append('product_ids', currentPdpProductId);
            formData.append('category_names', currentPdpCategoryName);

            fetch('../ajax_voucher.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const feedbackBox = document.getElementById('pdpVoucherFeedback');
                const feedbackText = document.getElementById('pdpVoucherFeedbackText');
                const discountRow = document.getElementById('pdp_discount_row');
                const discountLabel = document.getElementById('pdp_discount_label');
                const discountDisplay = document.getElementById('pdp_discount_display');

                if (data.success && data.discount_amount > 0) {
                    if (discountRow) discountRow.style.display = 'flex';
                    if (discountLabel) discountLabel.innerText = 'Diskon Otomatis:';
                    if (discountDisplay) discountDisplay.innerText = '-' + data.discount_amount_formatted;
                    if (totEl) totEl.innerText = data.total_after_discount_formatted;

                    if (feedbackBox) {
                        feedbackBox.style.display = 'block';
                        feedbackBox.style.background = '#ecfdf5';
                        feedbackBox.style.color = '#065f46';
                        feedbackBox.style.border = '1px solid #a7f3d0';
                    }
                    if (feedbackText) feedbackText.innerText = data.message;
                } else {
                    if (discountRow) discountRow.style.display = 'none';
                    if (totEl) totEl.innerText = 'Rp ' + Number(subtotal).toLocaleString('id-ID');
                    if (feedbackBox) feedbackBox.style.display = 'none';
                }
            })
            .catch(err => {
                if (totEl) totEl.innerText = 'Rp ' + Number(subtotal).toLocaleString('id-ID');
            });
        }

        function recalculatePdpSaleTotal() {
            const priceEl = document.getElementById('pdp_sale_price_rate');
            const qtyEl = document.getElementById('pdp_buy_quantity');
            const totalEl = document.getElementById('pdp_sale_total_display');
            if (!priceEl || !qtyEl || !totalEl) return;

            const unitPrice = parseFloat(priceEl.value) || 0;
            const qty = parseInt(qtyEl.value) || 1;
            const total = unitPrice * qty;
            totalEl.innerText = 'Rp ' + Number(total).toLocaleString('id-ID');
        }

        // Dynamic Payment Instruction Handler for PDP
        let currentPdpAccNum = "731009876543";
        let currentPdpAccBank = "No. Rekening BCA";

        function updatePdpPaymentInstruction(method) {
            const titleEl = document.getElementById('pdpPayBankTitle');
            const numEl = document.getElementById('pdpPayAccountNum');
            const nameEl = document.getElementById('pdpPayAccountName');
            const btnEl = document.getElementById('pdpBtnCopyAcc');
            const noteEl = document.getElementById('pdpPayNote');

            if (!titleEl || !numEl) return;

            if (method.includes('BCA')) {
                titleEl.innerText = 'Transfer Bank BCA:';
                numEl.innerText = '7310-0987-6543';
                numEl.style.color = '#0284c7';
                nameEl.innerText = 'a/n PT DANATRAIL INDONESIA';
                nameEl.style.display = 'block';
                btnEl.style.display = 'inline-flex';
                btnEl.style.background = '#0284c7';
                noteEl.innerText = 'Simpan nomor rekening di atas untuk transfer santai setelah pemesanan diajukan.';
                currentPdpAccNum = '731009876543';
                currentPdpAccBank = 'No. Rekening BCA';
            } else if (method.includes('Mandiri')) {
                titleEl.innerText = 'Transfer Bank Mandiri:';
                numEl.innerText = '137-00-1928374-1';
                numEl.style.color = '#d97706';
                nameEl.innerText = 'a/n PT DANATRAIL INDONESIA';
                nameEl.style.display = 'block';
                btnEl.style.display = 'inline-flex';
                btnEl.style.background = '#d97706';
                noteEl.innerText = 'Simpan nomor rekening di atas untuk transfer santai via ATM atau Livin by Mandiri.';
                currentPdpAccNum = '1370019283741';
                currentPdpAccBank = 'No. Rekening Mandiri';
            } else if (method.includes('BRI')) {
                titleEl.innerText = 'Transfer Bank BRI:';
                numEl.innerText = '0123-01-098765-50-8';
                numEl.style.color = '#0284c7';
                nameEl.innerText = 'a/n PT DANATRAIL INDONESIA';
                nameEl.style.display = 'block';
                btnEl.style.display = 'inline-flex';
                btnEl.style.background = '#0284c7';
                noteEl.innerText = 'Simpan nomor rekening di atas untuk transfer santai via ATM BRI atau BRImo.';
                currentPdpAccNum = '012301098765508';
                currentPdpAccBank = 'No. Rekening BRI';
            } else if (method.includes('QRIS')) {
                titleEl.innerText = 'QRIS Instan Resmi:';
                numEl.innerText = 'Scan QRIS DANATRAIL';
                numEl.style.color = '#15803d';
                nameEl.innerText = 'Mendukung Semua E-Wallet & M-Banking';
                nameEl.style.display = 'block';
                btnEl.style.display = 'none';
                noteEl.innerText = 'Kode QRIS resmi akan tampil di riwayat pemesanan atau dapat dikirim langsung oleh Admin via WhatsApp.';
            } else if (method.includes('Basecamp')) {
                titleEl.innerText = 'Bayar di Kasir Basecamp:';
                numEl.innerText = 'Tidak Perlu Transfer Sekarang';
                numEl.style.color = '#166534';
                nameEl.style.display = 'none';
                btnEl.style.display = 'none';
                noteEl.innerText = 'Pilihan Santai! Pembayaran dilakukan langsung saat Anda mengambil alat di Basecamp DANATRAIL.';
            }
        }

        function copyPdpAccount() {
            if (currentPdpAccNum) {
                copyTextToClipboard(currentPdpAccNum, currentPdpAccBank);
            }
        }

        function showPdpToast(message) {
            let toast = document.getElementById('danatrailToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'danatrailToast';
                toast.style.cssText = 'display: none; position: fixed; bottom: 24px; right: 24px; z-index: 9999; background: #064e3b; color: #ffffff; padding: 12px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 10px 25px rgba(0,0,0,0.2); align-items: center; gap: 8px;';
                toast.innerHTML = '<span id="pdpToastIcon">✓</span> <span id="pdpToastMsg"></span>';
                document.body.appendChild(toast);
            }
            const msgEl = document.getElementById('pdpToastMsg') || toast;
            msgEl.innerText = message;
            toast.style.display = 'flex';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        function copyTextToClipboard(text, label) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showPdpToast('✓ ' + label + ' berhasil disalin!');
                }).catch(() => {
                    fallbackCopyText(text, label);
                });
            } else {
                fallbackCopyText(text, label);
            }
        }

        function fallbackCopyText(text, label) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showPdpToast('✓ ' + label + ' berhasil disalin!');
            } catch (err) {
                prompt("Salin teks berikut:", text);
            }
            document.body.removeChild(textArea);
        }

        // Jalankan kalkulasi inisial saat halaman dimuat
        window.addEventListener('DOMContentLoaded', function() {
            recalculatePdpTotal();
            recalculatePdpSaleTotal();
            const paySel = document.getElementById('pdp_payment_method');
            if (paySel) updatePdpPaymentInstruction(paySel.value);
        });
    </script>
</body>

</html>
