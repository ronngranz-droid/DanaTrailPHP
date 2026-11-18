<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek apakah admin sudah login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=Akses ditolak! Silakan login sebagai admin.");
    exit;
}

include '../config/config.php';
/** @var mysqli $conn */

// Sinkronisasi status keterlambatan rental otomatis
sync_rental_overdue_status($conn);

$fine_enabled = (int)get_system_setting('fine_enabled', '1');
$fine_method = get_system_setting('fine_method', 'fixed_daily');
$fine_rate = (float)get_system_setting('fine_rate', '20000');
$fine_grace_period = (int)get_system_setting('fine_grace_period', '1');
$fine_max_amount = (float)get_system_setting('fine_max_amount', '0');

// Ambil data filter status dan pencarian
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : '';
$search        = isset($_GET['q']) ? mysqli_real_escape_string($conn, trim($_GET['q'])) : '';

// Query data transaksi sewa (menggabungkan tabel rentals, users, products, dan rental_fines)
$query_sql = "SELECT rentals.*, 
              users.name AS user_name, users.email AS user_email, users.phone AS user_phone, users.address AS user_address, 
              products.name AS product_name, products.image AS product_image, products.stock AS product_stock,
              rf.fine_amount AS recorded_fine_amount, rf.payment_status AS fine_payment_status_db
              FROM rentals
              LEFT JOIN users ON rentals.user_id = users.id
              LEFT JOIN products ON rentals.product_id = products.id
              LEFT JOIN rental_fines rf ON rentals.id = rf.rental_id
              WHERE 1=1";

if (!empty($status_filter)) {
    if ($status_filter === 'Terlambat') {
        $query_sql .= " AND (rentals.return_status = 'Terlambat' OR rentals.status = 'Terlambat')";
    } else {
        $query_sql .= " AND rentals.status = '$status_filter'";
    }
}

if (!empty($search)) {
    $query_sql .= " AND (rentals.rental_code LIKE '%$search%' OR users.name LIKE '%$search%' OR products.name LIKE '%$search%')";
}

$query_sql .= " ORDER BY rentals.id DESC";
$data_transaksi = mysqli_query($conn, $query_sql);

// Hitung total statistik
$total_booking    = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM rentals"));
$total_pending    = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM rentals WHERE status='Menunggu Konfirmasi'"));
$total_disewa     = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM rentals WHERE status='Disewa'"));
$total_selesai    = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM rentals WHERE status='Selesai'"));

// Hitung total akumulasi pendapatan (Disewa / Selesai)
$q_pendapatan     = mysqli_query($conn, "SELECT SUM(total_price) AS total FROM rentals WHERE status IN ('Disewa', 'Selesai')");
$total_pendapatan = (float)(mysqli_fetch_assoc($q_pendapatan)['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Transaksi Sewa — Admin DANATRAIL</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ==========================================================================
           DANATRAIL ADMIN DESIGN SYSTEM (UNIFIED TRANSACTIONS SPEC)
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

        /* SIDEBAR (260px UNIFIED) */
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
            justify-content: space-between;
            padding: 9px 12px;
            border-radius: var(--radius-md);
            color: #b7c7be;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: var(--transition-fast);
        }

        .nav-item > div {
            display: flex;
            align-items: center;
            gap: 10px;
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
            padding: 20px 20px 40px 20px;
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

        /* Header Title */
        .page-header-bar {
            margin-bottom: 20px;
        }

        .page-header-bar h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.4px;
        }

        .page-header-bar p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Toast Feedback */
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

        /* ==========================================================================
           TRANSACTION STATS CARDS (5 METRICS)
           ========================================================================== */
        .stats-rentals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(195px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .trans-stat-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            box-shadow: var(--shadow-subtle);
            position: relative;
            overflow: hidden;
            transition: transform var(--transition-fast);
        }

        .trans-stat-card:hover {
            transform: translateY(-2px);
            border-color: #ccd7d0;
        }

        .trans-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .card-income::before { background: var(--emerald-500); }
        .card-pending::before { background: var(--gold-500); }
        .card-active::before { background: #0284c7; }
        .card-done::before { background: #16a34a; }
        .card-total::before { background: var(--text-muted); }

        .stat-card-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card-num {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
        }

        .stat-card-num.income-accent {
            font-size: 19px;
            color: var(--forest-800);
        }

        .stat-card-num.pending-accent {
            color: #b45309;
        }

        .stat-card-num.active-accent {
            color: #0369a1;
        }

        /* ==========================================================================
           FILTER & SEARCH SINGLE TOOLBAR
           ========================================================================== */
        .controls-toolbar-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .status-chips-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .status-chips-wrap::-webkit-scrollbar {
            display: none;
        }

        .filter-status-chip {
            text-decoration: none;
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            font-size: 12.5px;
            font-weight: 600;
            background: var(--bg-canvas);
            color: var(--text-body);
            border: 1px solid var(--border-color);
            white-space: nowrap;
            transition: var(--transition-fast);
        }

        .filter-status-chip:hover {
            background: #e9eee8;
            color: var(--text-main);
        }

        .filter-status-chip.active {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
            font-weight: 700;
        }

        .search-trans-form {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 280px;
            max-width: 340px;
        }

        .search-input-box {
            position: relative;
            flex: 1;
        }

        .search-input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            color: var(--text-muted);
            pointer-events: none;
        }

        .search-field-ctrl {
            width: 100%;
            padding: 8px 12px 8px 34px;
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 12.5px;
            font-family: inherit;
            color: var(--text-main);
            outline: none;
            transition: border-color var(--transition-fast);
        }

        .search-field-ctrl:focus {
            background: #ffffff;
            border-color: var(--forest-800);
            box-shadow: 0 0 0 2px rgba(14, 86, 56, 0.1);
        }

        .btn-submit-search {
            padding: 8px 14px;
            background: var(--forest-900);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        .btn-submit-search:hover {
            background: var(--forest-800);
        }

                /* ==========================================================================
           TRANSACTION TABLE & FOOTER SUMMARY (DANATRAIL CLEAN SPEC)
           ========================================================================== */
        .transactions-table-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-subtle);
            margin-top: 10px;
        }

        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            position: relative;
            background: #ffffff;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f8fafc;
        }

        .table-scroll-wrapper::-webkit-scrollbar {
            height: 7px;
        }
        .table-scroll-wrapper::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        .table-scroll-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .table-scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .trans-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12.5px;
            text-align: left;
        }

        .trans-table th {
            background: #f8faf8;
            color: var(--forest-950);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 800;
            padding: 13px 16px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .trans-table td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
            color: var(--text-body);
            background: #ffffff;
        }

        .trans-table tr:hover td {
            background: #fbfdfb;
        }

        /* 1. RENTER & CODE CELL */
        .col-user-box {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 170px;
        }
        .user-code-badge {
            font-family: monospace;
            font-size: 11px;
            font-weight: 700;
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            padding: 2px 7px;
            border-radius: 4px;
            color: var(--forest-900);
            width: fit-content;
            margin-bottom: 3px;
            letter-spacing: 0.3px;
        }
        .user-main-name {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.3;
        }
        .user-sub-info {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            gap: 1.5px;
            margin-top: 2px;
        }
        .user-telp {
            color: var(--forest-700);
            font-weight: 700;
        }

        /* 2. GEAR OUTDOOR CELL */
        .col-gear-box {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 210px;
        }
        .gear-thumb-img {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border-color);
            background: #f8faf8;
            flex-shrink: 0;
        }
        .gear-thumb-fallback {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: var(--forest-950);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold-500);
            flex-shrink: 0;
        }
        .gear-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.35;
        }
        .gear-meta {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .gear-qty-tag {
            font-weight: 700;
            color: var(--forest-900);
            background: #eef5f0;
            padding: 1px 6px;
            border-radius: 4px;
            border: 1px solid #d1e5d7;
        }

        /* 3. SCHEDULE CELL - Super Clear & Readable */
        .col-schedule-box {
            display: flex;
            flex-direction: column;
            gap: 3.5px;
            white-space: nowrap;
            min-width: 165px;
        }
        .schedule-dates {
            font-size: 12px;
            font-weight: 700;
            color: var(--forest-950);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .schedule-arrow {
            color: #059669;
            font-weight: 700;
            font-size: 13px;
        }
        .schedule-badge {
            font-size: 10px;
            font-weight: 700;
            color: #065f46;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 1.5px 7px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            width: fit-content;
        }

        /* 4. TOTAL & PAYMENT CELL */
        .col-finance-box {
            display: flex;
            flex-direction: column;
            gap: 2.5px;
            min-width: 155px;
        }
        .finance-amount {
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            font-weight: 800;
            color: var(--forest-950);
        }
        .finance-disc {
            font-size: 11px;
            color: #166e4a;
            font-weight: 600;
        }
        .finance-fine {
            font-size: 11px;
            color: #dc2626;
            font-weight: 700;
            margin-top: 1px;
        }
        .fine-status-tag {
            font-size: 9.5px;
            font-weight: 700;
            padding: 1px 4px;
            border-radius: 3px;
        }
        .fine-paid { background: #dcfce7; color: #15803d; }
        .fine-free { background: #f3f4f6; color: #4b5563; }
        .fine-unpaid { background: #fee2e2; color: #b91c1c; }
        
        .pay-method-status {
            margin-top: 3px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            align-items: flex-start;
        }
        .pay-pill-lunas {
            font-size: 10.5px;
            font-weight: 700;
            color: #15803d;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            padding: 2px 7px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .pay-pill-cod {
            font-size: 10.5px;
            font-weight: 700;
            color: #0f766e;
            background: #f0fdfa;
            border: 1px solid #99f6e4;
            padding: 2px 7px;
            border-radius: 99px;
        }
        .pay-pill-verify {
            font-size: 10.5px;
            font-weight: 700;
            color: #0284c7;
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            padding: 2px 7px;
            border-radius: 99px;
        }
        .pay-pill-pending {
            font-size: 10.5px;
            font-weight: 700;
            color: #b45309;
            background: #fef3c7;
            border: 1px solid #fde68a;
            padding: 2px 7px;
            border-radius: 99px;
        }
        .btn-kasir-pay {
            background: #0f766e;
            color: #fff;
            border-radius: 4px;
            padding: 2px 7px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
            margin-top: 2px;
            display: inline-block;
        }
        .btn-proof-link {
            background: none;
            border: none;
            font-size: 11px;
            color: var(--forest-700);
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }
        .btn-mini-confirm {
            background: #16a34a;
            color: #fff;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
        }
        .btn-mini-reject {
            background: #ef4444;
            color: #fff;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            text-decoration: none;
        }

        /* 5. GUARANTEE CELL */
        .col-guarantee-box {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-start;
            min-width: 145px;
        }
        .id-badge-type {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .id-basecamp-label {
            font-size: 10.5px;
            color: var(--text-muted);
            background: #f8fafc;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px dashed #cbd5e1;
        }
        .btn-archive-photo {
            background: none;
            border: none;
            font-size: 10.5px;
            color: var(--forest-700);
            font-weight: 700;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }

        /* 6. STATUS & ACTION CELL */
        .col-status-action-box {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
            min-width: 165px;
        }
        .status-pill-done {
            font-size: 11px;
            font-weight: 700;
            color: #15803d;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            padding: 3px 9px;
            border-radius: 99px;
        }
        .status-pill-active {
            font-size: 11px;
            font-weight: 700;
            color: #0284c7;
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            padding: 3px 9px;
            border-radius: 99px;
        }
        .status-pill-pending {
            font-size: 11px;
            font-weight: 700;
            color: #b45309;
            background: #fef3c7;
            border: 1px solid #fde68a;
            padding: 3px 9px;
            border-radius: 99px;
        }
        .status-pill-late {
            font-size: 11px;
            font-weight: 800;
            color: #b91c1c;
            background: #fee2e2;
            border: 1px solid #fecaca;
            padding: 3px 9px;
            border-radius: 99px;
        }
        .status-pill-cancel {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 3px 9px;
            border-radius: 99px;
        }

        .primary-act-wrap {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .btn-act-approve {
            background: #10b981;
            color: #ffffff;
            padding: 4px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .btn-act-approve:hover { background: #059669; }
        .btn-act-reject {
            background: #ffffff;
            color: #dc2626;
            border: 1px solid #fca5a5;
            padding: 3px 7px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }
        .btn-act-reject:hover { background: #fef2f2; }
        .btn-act-return {
            background: #0284c7;
            color: #ffffff;
            padding: 4px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .btn-act-return:hover { background: #0369a1; }

        .sub-act-wrap {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .btn-mini-faktur {
            padding: 2.5px 7px;
            background: #ffffff;
            color: var(--forest-900);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-mini-faktur:hover { background: #f8faf8; }
        .btn-mini-wa {
            padding: 2.5px 7px;
            background: #25D366;
            color: #ffffff;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .btn-mini-wa:hover { background: #1eb855; }
        .btn-mini-del {
            font-size: 10.5px;
            color: #dc2626;
            text-decoration: none;
            padding: 2px 4px;
        }
        .btn-mini-del:hover { text-decoration: underline; }

        /* Footer Summary Table */
        .table-summary-footer {
            background: #f1f4f0;
            border-top: 2px solid var(--border-color);
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }
        .summary-count-txt {
            color: var(--text-main);
            font-weight: 600;
        }
        .summary-total-sum {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--forest-950);
        }
        .summary-total-sum b {
            font-family: 'Outfit', sans-serif;
            font-size: 17px;
            color: var(--forest-800);
            margin-left: 6px;
        }

        /* Empty State */
        .empty-table-cell {
            text-align: center;
            padding: 44px 20px;
            color: var(--text-muted);
        }
        .empty-table-cell h4 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 6px;
            margin-bottom: 2px;
        }

        /* Responsive Breakpoints */
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
            .stats-rentals-grid {
                grid-template-columns: 1fr 1fr;
            }
            .controls-toolbar-card {
                flex-direction: column;
                align-items: stretch;
            }
            .search-trans-form {
                max-width: 100%;
                min-width: 100%;
            }
        }
    </style>
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

    <!-- Sidebar Menu Component -->
    <?php include 'menu.php'; ?>

    <!-- Main Content -->
    <main class="main-wrapper">

        <!-- Page Header -->
        <div class="page-header-bar">
            <h1>Data Penyewaan & Konfirmasi Pesanan</h1>
            <p>Kelola verifikasi booking, konfirmasi pengembalian alat, dan rekapitulasi data transaksi pelanggan.</p>
        </div>

        <!-- Toast Notification Messages -->
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
        <?php endif; ?>

        <!-- 5 Compact Transaction Stats -->
        <section class="stats-rentals-grid">
            <div class="trans-stat-card card-income">
                <div class="stat-card-title">
                    <span>Total Pendapatan</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--emerald-600);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-card-num income-accent">
                    Rp <?php echo number_format($total_pendapatan, 0, ',', '.'); ?>
                </div>
            </div>

            <div class="trans-stat-card card-pending">
                <div class="stat-card-title">
                    <span>Menunggu Konfirmasi</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--gold-500);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-card-num pending-accent">
                    <?php echo $total_pending; ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Pesanan</span>
                </div>
            </div>

            <div class="trans-stat-card card-active">
                <div class="stat-card-title">
                    <span>Sedang Disewa</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #0284c7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="stat-card-num active-accent">
                    <?php echo $total_disewa; ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Aktif</span>
                </div>
            </div>

            <div class="trans-stat-card card-done">
                <div class="stat-card-title">
                    <span>Selesai / Kembali</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #16a34a;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-card-num" style="color: #15803d;">
                    <?php echo $total_selesai; ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Selesai</span>
                </div>
            </div>

            <div class="trans-stat-card card-total">
                <div class="stat-card-title">
                    <span>Total Semua Pesanan</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--text-muted);">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div class="stat-card-num">
                    <?php echo $total_booking; ?> <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Total</span>
                </div>
            </div>
        </section>

        <!-- Balanced Filter Status & Search Bar -->
        <section class="controls-toolbar-card">
            <div class="status-chips-wrap">
                <a href="transaksi.php" class="filter-status-chip <?php echo empty($status_filter) ? 'active' : ''; ?>">
                    Semua (<?php echo $total_booking; ?>)
                </a>
                <a href="transaksi.php?status=Menunggu Konfirmasi" class="filter-status-chip <?php echo ($status_filter == 'Menunggu Konfirmasi') ? 'active' : ''; ?>">
                    Menunggu Konfirmasi (<?php echo $total_pending; ?>)
                </a>
                <a href="transaksi.php?status=Disewa" class="filter-status-chip <?php echo ($status_filter == 'Disewa') ? 'active' : ''; ?>">
                    Sedang Disewa (<?php echo $total_disewa; ?>)
                </a>
                <a href="transaksi.php?status=Selesai" class="filter-status-chip <?php echo ($status_filter == 'Selesai') ? 'active' : ''; ?>">
                    Selesai (<?php echo $total_selesai; ?>)
                </a>
                <a href="transaksi.php?status=Dibatalkan" class="filter-status-chip <?php echo ($status_filter == 'Dibatalkan') ? 'active' : ''; ?>">
                    Dibatalkan
                </a>
            </div>

            <div class="search-trans-form" style="display: flex; align-items: center; max-width: 380px; width: 100%;">
                <div class="search-input-box" style="flex: 1;">
                    <svg class="search-input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" id="adminTransSearch" class="search-field-ctrl" placeholder="Live search kode, penyewa, alat, jaminan..." value="<?php echo htmlspecialchars($search); ?>" oninput="liveFilterTransaksi(this.value)" autocomplete="off">
                </div>
            </div>
        </section>

        <!-- Header Title Bar with Print/Report Button -->
        <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
            <div class="page-title-group">
                <h1 style="font-size: 24px; font-weight: 800; color: var(--text-main);">Data Transaksi & Penyewaan</h1>
                <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 2px;">Kelola status pesanan, verifikasi bukti pembayaran, cetak faktur sewa, dan pengembalian unit</p>
            </div>
            <button type="button" onclick="window.print()" style="background: #ffffff; color: var(--forest-900); border: 1.5px solid var(--border-color); padding: 9px 18px; border-radius: var(--radius-md); font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: var(--shadow-subtle);">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                <span>Cetak / Rekap Laporan</span>
            </button>
        </div>

        <!-- Transaction Data Table Card -->
        <section class="transactions-table-card">
            <div class="table-scroll-wrapper" id="mainTableScroll">
                <table class="trans-table" id="adminTransTable">
                    <thead>
                        <tr>
                            <th>Penyewa & Kode</th>
                            <th>Alat Outdoor</th>
                            <th>Jadwal Sewa</th>
                            <th>Total & Pembayaran</th>
                            <th>Jaminan Identitas</th>
                            <th style="text-align: right;">Status & Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal_tabel = 0;
                        $count_displayed = 0;
                        if ($data_transaksi && mysqli_num_rows($data_transaksi) > 0): 
                        ?>
                            <?php while ($row = mysqli_fetch_assoc($data_transaksi)): 
                                $count_displayed++;
                                if ($row['status'] != 'Dibatalkan') {
                                    $subtotal_tabel += (float)$row['total_price'] + (float)($row['fine_amount'] ?? 0);
                                }
                                $guarantee_photo = $row['guarantee_photo'] ?? '';
                                $payment_method = $row['payment_method'] ?? 'Transfer Bank BCA';
                                $pay_status = $row['payment_status'] ?? 'Menunggu Pembayaran';
                                $pay_proof  = $row['payment_proof'] ?? '';
                                $user_phone = preg_replace('/[^0-9]/', '', $row['user_phone'] ?? '');
                                if (!empty($user_phone) && substr($user_phone, 0, 1) === '0') {
                                    $user_phone = '62' . substr($user_phone, 1);
                                }
                                $wa_admin_to_user_msg = urlencode("Halo Kak " . ($row['user_name'] ?? 'Pelanggan') . ", ini dari Basecamp DANATRAIL terkait pesanan sewa Kode: " . $row['rental_code']);
                            ?>
                                <tr class="trans-row" data-search="<?php echo htmlspecialchars(strtolower($row['rental_code'] . ' ' . ($row['user_name'] ?? '') . ' ' . ($row['product_name'] ?? '') . ' ' . $row['guarantee_type'] . ' ' . $payment_method . ' ' . $pay_status . ' ' . $row['status'])); ?>">
                                    <!-- 1. Penyewa & Kode (Faktur dipindah ke sini) -->
                                    <td>
                                        <div class="col-user-box">
                                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 2px;">
                                                <span class="user-code-badge" style="margin-bottom: 0;">#<?php echo htmlspecialchars($row['rental_code']); ?></span>
                                                <a href="../invoice.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn-mini-faktur" title="Cetak Faktur Sewa">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                                    <span>Faktur</span>
                                                </a>
                                            </div>
                                            <div class="user-main-name"><?php echo htmlspecialchars($row['user_name'] ?? 'User'); ?></div>
                                            <div class="user-sub-info">
                                                <span><?php echo htmlspecialchars($row['user_email'] ?? '-'); ?></span>
                                                <?php if (!empty($row['user_phone'])): ?>
                                                    <span class="user-telp">Telp: <?php echo htmlspecialchars($row['user_phone']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 2. Alat Outdoor -->
                                    <td>
                                        <div class="col-gear-box">
                                            <?php 
                                            $g_img = $row['product_image'] ?? '';
                                            $g_img_path = (!empty($g_img) && file_exists('../uploads/products/' . $g_img)) 
                                                ? '../uploads/products/' . $g_img 
                                                : ((!empty($g_img) && file_exists('../../uploads/products/' . $g_img)) 
                                                    ? '../../uploads/products/' . $g_img 
                                                    : '');
                                            ?>
                                            <?php if (!empty($g_img_path)): ?>
                                                <img src="<?php echo htmlspecialchars($g_img_path); ?>" alt="<?php echo htmlspecialchars($row['product_name'] ?? 'Alat'); ?>" class="gear-thumb-img">
                                            <?php else: ?>
                                                <div class="gear-thumb-fallback">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="gear-name"><?php echo htmlspecialchars($row['product_name'] ?? 'Alat Outdoor'); ?></div>
                                                <div class="gear-meta">
                                                    <span class="gear-qty-tag"><?php echo (int)$row['quantity']; ?> Unit</span>
                                                    <span>(Sisa: <?php echo (int)($row['product_stock'] ?? 0); ?>)</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 3. Jadwal Sewa (Jelas & Nyaman Dibaca) -->
                                    <td>
                                        <?php 
                                            $t_start = strtotime($row['rental_date']);
                                            $t_end   = strtotime($row['return_date']);
                                            $bulan_id = [1=>'Jan', 2=>'Feb', 3=>'Mar', 4=>'Apr', 5=>'Mei', 6=>'Jun', 7=>'Jul', 8=>'Agu', 9=>'Sep', 10=>'Okt', 11=>'Nov', 12=>'Des'];
                                            $b_start = $bulan_id[(int)date('n', $t_start)] ?? date('M', $t_start);
                                            $b_end   = $bulan_id[(int)date('n', $t_end)] ?? date('M', $t_end);
                                            $format_jadwal_mulai = date('d', $t_start) . ' ' . $b_start;
                                            $format_jadwal_selesai = date('d', $t_end) . ' ' . $b_end . ' ' . date('Y', $t_end);
                                        ?>
                                        <div class="col-schedule-box">
                                            <div class="schedule-dates">
                                                <span><?php echo $format_jadwal_mulai; ?></span>
                                                <span class="schedule-arrow">&rarr;</span>
                                                <span><?php echo $format_jadwal_selesai; ?></span>
                                            </div>
                                            <div class="schedule-badge">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                                <span><?php echo (int)$row['duration_days']; ?> Hari Sewa</span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 4. Total & Pembayaran -->
                                    <td>
                                        <div class="col-finance-box">
                                            <div class="finance-amount">
                                                Rp <?php echo number_format($row['total_price'], 0, ',', '.'); ?>
                                            </div>
                                            <?php if ((float)($row['discount_amount'] ?? 0) > 0): ?>
                                                <div class="finance-disc">
                                                    Diskon: -Rp <?php echo number_format($row['discount_amount'], 0, ',', '.'); ?>
                                                    <?php if (!empty($row['discount_code'])): ?>
                                                        <span>(<?php echo htmlspecialchars($row['discount_code']); ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php 
                                            $f_amount = (float)($row['recorded_fine_amount'] ?? $row['fine_amount'] ?? 0);
                                            if ($f_amount > 0): 
                                                $f_status = $row['fine_payment_status_db'] ?? $row['fine_payment_status'] ?? 'Belum Dibayar';
                                            ?>
                                                <div class="finance-fine">
                                                    + Denda: Rp <?php echo number_format($f_amount, 0, ',', '.'); ?>
                                                    <span class="fine-status-tag fine-<?php echo ($f_status === 'Sudah Dibayar') ? 'paid' : (($f_status === 'Dibebaskan Admin') ? 'free' : 'unpaid'); ?>">
                                                        <?php echo htmlspecialchars($f_status); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="pay-method-status">
                                                <?php 
                                                $is_basecamp = (stripos($payment_method, 'Basecamp') !== false || stripos($payment_method, 'Cash') !== false || stripos($payment_method, 'COD') !== false);
                                                if ($is_basecamp): 
                                                ?>
                                                    <?php if ($pay_status === 'Lunas'): ?>
                                                        <span class="pay-pill-lunas">✓ Lunas (Basecamp)</span>
                                                    <?php else: ?>
                                                        <span class="pay-pill-cod">Bayar di Basecamp</span>
                                                        <a href="transaksi_aksi.php?verifikasi_bayar=1&id=<?php echo $row['id']; ?>&status_bayar=Lunas" onclick="return confirm('Tandai pembayaran tunai di kasir basecamp sebagai Lunas?');" class="btn-kasir-pay">
                                                            ✓ Terima Bayar
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if ($pay_status === 'Lunas'): ?>
                                                        <span class="pay-pill-lunas">✓ Lunas Online</span>
                                                    <?php elseif ($pay_status === 'Menunggu Verifikasi Admin'): ?>
                                                        <span class="pay-pill-verify">Menunggu Verifikasi</span>
                                                    <?php else: ?>
                                                        <span class="pay-pill-pending">Belum Bayar Online</span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($pay_proof) && file_exists('../uploads/payments/' . $pay_proof)): ?>
                                                        <button type="button" onclick="openAdminPhotoModal('../uploads/payments/<?php echo htmlspecialchars($pay_proof); ?>', 'Bukti Bayar <?php echo htmlspecialchars(addslashes($row['user_name'] ?? 'User')); ?> (<?php echo htmlspecialchars(addslashes($row['rental_code'])); ?>)')" class="btn-proof-link">
                                                            Lihat Bukti Transfer
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($pay_status !== 'Lunas'): ?>
                                                        <div style="display: flex; gap: 4px; margin-top: 2px;">
                                                            <a href="transaksi_aksi.php?verifikasi_bayar=1&id=<?php echo $row['id']; ?>&status_bayar=Lunas" onclick="return confirm('Konfirmasi pembayaran pesanan ini sebagai Lunas?');" class="btn-mini-confirm">
                                                                Set Lunas
                                                            </a>
                                                            <a href="transaksi_aksi.php?verifikasi_bayar=1&id=<?php echo $row['id']; ?>&status_bayar=Pembayaran Ditolak" onclick="return confirm('Tolak bukti pembayaran ini?');" class="btn-mini-reject">
                                                                Tolak
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 5. Jaminan Identitas -->
                                    <td>
                                        <div class="col-guarantee-box">
                                            <span class="id-badge-type"><?php echo htmlspecialchars($row['guarantee_type'] ?? 'KTP Asli'); ?></span>
                                            <span class="id-basecamp-label">Titip Fisik di Basecamp</span>
                                            <?php if (!empty($guarantee_photo) && file_exists('../uploads/guarantees/' . $guarantee_photo)): ?>
                                                <button type="button" onclick="openAdminPhotoModal('../uploads/guarantees/<?php echo htmlspecialchars($guarantee_photo); ?>', 'Kartu Identitas Jaminan: <?php echo htmlspecialchars(addslashes($row['user_name'] ?? 'User')); ?> (<?php echo htmlspecialchars(addslashes($row['guarantee_type'])); ?>)')" class="btn-archive-photo">
                                                    Lihat Foto Arsip
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 6. Status & Aksi -->
                                    <td style="text-align: right;">
                                        <div class="col-status-action-box">
                                            <div class="status-badge-wrap">
                                                <?php 
                                                $st = $row['status'];
                                                $ret_st = $row['return_status'] ?? '';
                                                if ($ret_st == 'Terlambat' || ($st == 'Disewa' && $row['return_date'] < date('Y-m-d'))) {
                                                    echo '<span class="status-pill-late">Terlambat</span>';
                                                } elseif ($st == 'Menunggu Konfirmasi') {
                                                    echo '<span class="status-pill-pending">Menunggu Konfirmasi</span>';
                                                } elseif ($st == 'Disewa') {
                                                    echo '<span class="status-pill-active">Sedang Disewa</span>';
                                                } elseif ($st == 'Selesai') {
                                                    echo '<span class="status-pill-done">Selesai</span>';
                                                } else {
                                                    echo '<span class="status-pill-cancel">' . htmlspecialchars($st) . '</span>';
                                                }
                                                ?>
                                            </div>

                                            <div class="primary-act-wrap">
                                                <?php if ($row['status'] == 'Menunggu Konfirmasi'): ?>
                                                    <a href="transaksi_aksi.php?konfirmasi=<?php echo $row['id']; ?>" class="btn-act-approve" onclick="return confirm('Setujui pesanan ini? Stok alat akan otomatis dipotong.');">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <span>Setujui</span>
                                                    </a>
                                                    <a href="transaksi_aksi.php?tolak=<?php echo $row['id']; ?>" class="btn-act-reject" onclick="return confirm('Tolak pesanan sewa ini?');">
                                                        Tolak
                                                    </a>
                                                <?php elseif ($row['status'] == 'Disewa'): ?>
                                                    <button type="button" onclick="openSelesaiModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['rental_code'])); ?>', '<?php echo htmlspecialchars(addslashes($row['user_name'] ?? 'User')); ?>', '<?php echo $row['return_date']; ?>', <?php echo (float)$row['total_price']; ?>, <?php echo $row['duration_days']; ?>)" class="btn-act-return">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        <span>Pengembalian</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <div class="sub-act-wrap">
                                                <?php if (!empty($user_phone)): ?>
                                                    <a href="https://wa.me/<?php echo $user_phone; ?>?text=<?php echo $wa_admin_to_user_msg; ?>" target="_blank" class="btn-mini-wa" title="Chat WhatsApp">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                                        <span>WA</span>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="transaksi_aksi.php?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Hapus permanen data transaksi ini? Jika sedang disewa, stok akan otomatis dikembalikan.');" class="btn-mini-del" title="Hapus Data">
                                                    Hapus
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-table-cell">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" style="margin: 0 auto; color: var(--text-muted);">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <h4>Tidak ada transaksi ditemukan</h4>
                                    <p style="font-size: 12.5px;">Coba sesuaikan filter status atau kata kunci pencarian Anda.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Summary Footer -->
            <?php if ($data_transaksi && mysqli_num_rows($data_transaksi) > 0): ?>
                <div class="table-summary-footer">
                    <div class="summary-count-txt">
                        Menampilkan <b id="adminTransCountTxt"><?php echo $count_displayed; ?></b> data transaksi
                    </div>
                    <div class="summary-total-sum">
                        Subtotal (Aktif & Selesai): <b>Rp <?php echo number_format($subtotal_tabel, 0, ',', '.'); ?></b>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Modal Pengembalian / Selesai Sewa -->
    <div class="modal-overlay" id="selesaiModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #ffffff; border-radius: 14px; max-width: 480px; width: 100%; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); background: var(--forest-950); color: #ffffff;">
                <div>
                    <h3 style="font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 800; color: #ffffff;">Pengembalian & Selesaikan Sewa</h3>
                    <p style="font-size: 12px; color: #a7f3d0;" id="selesaiModalCode">Kode: DNT-XXXX</p>
                </div>
                <button type="button" onclick="closeSelesaiModal()" style="background: none; border: none; font-size: 24px; line-height: 1; cursor: pointer; color: #ffffff;">&times;</button>
            </div>

            <form action="transaksi_aksi.php" method="POST" style="padding: 20px;">
                <input type="hidden" name="selesai_sewa" value="1">
                <input type="hidden" name="id" id="selesaiRentalId" value="">

                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: 12.5px;">
                    <div>Penyewa: <strong id="selesaiModalUser">-</strong></div>
                    <div>Jadwal Kembali: <strong id="selesaiModalReturnDate">-</strong></div>
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">
                        Tanggal Pengembalian Aktual
                    </label>
                    <input type="date" name="actual_return_date" id="actualReturnDate" value="<?php echo date('Y-m-d'); ?>" class="form-control" style="width: 100%; padding: 9px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 13px;" onchange="calculateFine()">
                </div>

                <div style="margin-bottom: 14px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">
                        Denda Keterlambatan (Rp)
                    </label>
                    <input type="number" name="fine_amount" id="fineAmountInput" value="0" min="0" step="1000" class="form-control" style="width: 100%; padding: 9px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 13px;">
                    <small id="fineHint" style="display: block; font-size: 11px; color: var(--text-muted); margin-top: 4px;">Isi 0 jika barang kembali tepat waktu dan tanpa denda.</small>
                </div>

                <div style="margin-bottom: 14px;" id="fineStatusWrap">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">
                        Status Pembayaran Denda
                    </label>
                    <select name="fine_payment_status" id="finePaymentStatus" style="width: 100%; padding: 9px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 13px;">
                        <option value="Belum Dibayar">Belum Dibayar</option>
                        <option value="Sudah Dibayar">Sudah Dibayar</option>
                        <option value="Dibebaskan Admin">Dibebaskan Admin</option>
                    </select>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">
                        Catatan Denda / Pengembalian (Opsional)
                    </label>
                    <textarea name="fine_notes" id="fineNotes" rows="2" placeholder="Catatan kondisi alat atau alasan toleransi..." style="width: 100%; padding: 8px; border: 1.5px solid var(--border-color); border-radius: 8px; font-size: 12.5px;"></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeSelesaiModal()" style="padding: 9px 16px; border-radius: 8px; border: 1.5px solid var(--border-color); background: #ffffff; font-weight: 700; font-size: 13px; cursor: pointer;">
                        Batal
                    </button>
                    <button type="submit" style="padding: 9px 18px; border-radius: 8px; border: none; background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-900) 100%); color: #ffffff; font-weight: 800; font-size: 13px; font-family: 'Outfit', sans-serif; cursor: pointer;">
                        ✓ Selesaikan & Kembalikan Stok
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Preview Foto Dokumen Jaminan / Bukti Transfer Admin -->
    <div class="modal-overlay" id="adminPhotoModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #ffffff; border-radius: 14px; max-width: 540px; width: 100%; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); background: var(--bg-canvas);">
                <strong id="adminPhotoModalTitle" style="font-size: 14px; color: var(--forest-950);">Verifikasi Foto</strong>
                <button type="button" onclick="closeAdminPhotoModal()" style="background: none; border: none; font-size: 20px; line-height: 1; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
            <div style="padding: 16px; text-align: center; background: #081a10;">
                <img id="adminPhotoModalImg" src="" alt="Foto" style="max-height: 420px; max-width: 100%; object-fit: contain; border-radius: 6px;">
            </div>
            <div style="padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; background: #ffffff; border-top: 1px solid var(--border-color);">
                <a id="adminPhotoDownloadBtn" href="" target="_blank" download style="font-size: 12px; font-weight: 700; color: var(--forest-800); text-decoration: none;">⬇ Buka Ukuran Penuh</a>
                <button type="button" onclick="closeAdminPhotoModal()" style="padding: 6px 16px; border-radius: 6px; background: var(--forest-900); color: #ffffff; font-weight: 700; border: none; font-size: 12.5px; cursor: pointer;">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        const FINE_SETTINGS = {
            enabled: <?php echo $fine_enabled ? 'true' : 'false'; ?>,
            method: '<?php echo $fine_method; ?>',
            rate: <?php echo $fine_rate; ?>,
            grace: <?php echo $fine_grace_period; ?>,
            max: <?php echo $fine_max_amount; ?>
        };

        // Global variables for Selesai Modal Calculation
        let currentReturnDueDate = '';
        let currentTotalPrice = 0;
        let currentDurationDays = 0;

        function openSelesaiModal(rentalId, rentalCode, userName, returnDate, totalPrice, durationDays) {
            document.getElementById('selesaiRentalId').value = rentalId;
            document.getElementById('selesaiModalCode').innerText = 'Kode: ' + rentalCode;
            document.getElementById('selesaiModalUser').innerText = userName;
            document.getElementById('selesaiModalReturnDate').innerText = returnDate;
            currentReturnDueDate = returnDate;
            currentTotalPrice = parseFloat(totalPrice) || 0;
            currentDurationDays = parseInt(durationDays) || 1;
            
            calculateFine();
            document.getElementById('selesaiModal').style.display = 'flex';
        }

        function closeSelesaiModal() {
            document.getElementById('selesaiModal').style.display = 'none';
        }

        function calculateFine() {
            const actualDateVal = document.getElementById('actualReturnDate').value;
            if (!actualDateVal || !currentReturnDueDate) return;

            const tActual = new Date(actualDateVal);
            const tDue = new Date(currentReturnDueDate);
            
            // Perhitungan keterlambatan dengan grace period
            const diffDays = Math.ceil((tActual - tDue) / (1000 * 60 * 60 * 24));
            let lateDays = 0;
            if (diffDays > FINE_SETTINGS.grace) {
                lateDays = diffDays - FINE_SETTINGS.grace;
            }

            const fineInput = document.getElementById('fineAmountInput');
            const hint = document.getElementById('fineHint');
            const statusSelect = document.getElementById('finePaymentStatus');

            if (!FINE_SETTINGS.enabled) {
                fineInput.value = 0;
                hint.innerText = 'Sistem denda sedang dinonaktifkan oleh administrator.';
                hint.style.color = '#64748b';
                statusSelect.value = 'Dibebaskan Admin';
                return;
            }

            if (lateDays > 0) {
                let estFine = 0;
                if (FINE_SETTINGS.method === 'percentage_daily') {
                    estFine = (currentTotalPrice * (FINE_SETTINGS.rate / 100)) * lateDays;
                } else {
                    estFine = FINE_SETTINGS.rate * lateDays;
                }

                if (FINE_SETTINGS.max > 0 && estFine > FINE_SETTINGS.max) {
                    estFine = FINE_SETTINGS.max;
                }

                fineInput.value = Math.round(estFine);
                hint.innerText = `Terlambat ${lateDays} hari (Toleransi ${FINE_SETTINGS.grace} hari). Estimasi denda: Rp ${new Intl.NumberFormat('id-ID').format(estFine)}`;
                hint.style.color = '#dc2626';
                if (statusSelect.value === 'Dibebaskan Admin') {
                    // keep
                } else {
                    statusSelect.value = 'Belum Dibayar';
                }
            } else {
                fineInput.value = 0;
                hint.innerText = 'Tepat waktu / dalam masa toleransi (grace period). Bebas denda.';
                hint.style.color = '#16a34a';
                statusSelect.value = 'Dibebaskan Admin';
            }
        }

        // Live Search Filter for Admin Transactions Table
        function liveFilterTransaksi(query) {
            const q = query.trim().toLowerCase();
            const rows = document.querySelectorAll('#adminTransTable tbody tr.trans-row');
            let visible = 0;

            rows.forEach(row => {
                const searchTxt = row.getAttribute('data-search') || '';
                if (q === '' || searchTxt.includes(q)) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            const countTxt = document.getElementById('adminTransCountTxt');
            if (countTxt) countTxt.innerText = visible;
        }

        // Photo Modal for Admin
        function openAdminPhotoModal(imgSrc, title) {
            document.getElementById('adminPhotoModalImg').src = imgSrc;
            document.getElementById('adminPhotoModalTitle').innerText = title;
            document.getElementById('adminPhotoDownloadBtn').href = imgSrc;
            const modal = document.getElementById('adminPhotoModal');
            modal.style.display = 'flex';
        }

        function closeAdminPhotoModal() {
            document.getElementById('adminPhotoModal').style.display = 'none';
        }

        function toggleAdminSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('adminSidebarBackdrop');
            if (sidebar && backdrop) {
                sidebar.classList.toggle('mobile-open');
                backdrop.classList.toggle('active');
            }
        }

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAdminPhotoModal();
                closeSelesaiModal();
                const sidebar = document.getElementById('adminSidebar');
                const backdrop = document.getElementById('adminSidebarBackdrop');
                if (sidebar && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    backdrop.classList.remove('active');
                }
            }
        });
        
    </script>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>

</html>
