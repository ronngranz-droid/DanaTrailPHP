<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek autentikasi user
if (!isset($_SESSION['role'])) {
    header("location:../login.php?pesan=Silakan login terlebih dahulu!");
    exit;
}

include '../config/config.php';
require_once __DIR__ . '/../config/midtrans.php';
/** @var mysqli $conn */

// Dapatkan User ID
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    $nama = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $user_check = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama' LIMIT 1");
    if ($user_check && mysqli_num_rows($user_check) > 0) {
        $u = mysqli_fetch_assoc($user_check);
        $user_id = $u['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

// Filter Status
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, trim($_GET['status'])) : '';

// Sinkronisasi status keterlambatan rental otomatis
sync_rental_overdue_status($conn);

// Query data riwayat sewa user
$sql = "SELECT r.*, p.name AS product_name, p.category AS product_category, p.image AS product_image,
        rf.fine_amount AS recorded_fine_amount, 
        rf.late_days AS recorded_late_days, 
        rf.fine_rate AS recorded_fine_rate, 
        rf.fine_type AS recorded_fine_type, 
        rf.payment_status AS recorded_fine_status
        FROM rentals r 
        LEFT JOIN products p ON r.product_id = p.id 
        LEFT JOIN rental_fines rf ON r.id = rf.rental_id
        WHERE r.user_id = '$user_id'";

if (!empty($status_filter)) {
    $sql .= " AND r.status = '$status_filter'";
}
$sql .= " ORDER BY r.id DESC";
$rentals = mysqli_query($conn, $sql);

// Hitung statistik ringkasan
$count_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rentals WHERE user_id='$user_id'"))['c'] ?? 0;
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rentals WHERE user_id='$user_id' AND status='Menunggu Konfirmasi'"))['c'] ?? 0;
$count_active  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rentals WHERE user_id='$user_id' AND status='Disewa'"))['c'] ?? 0;
$count_done    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rentals WHERE user_id='$user_id' AND status='Selesai'"))['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Sewa & Transaksi — DANATRAIL</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Midtrans Snap JS -->
    <script type="text/javascript" src="<?php echo htmlspecialchars(MIDTRANS_SNAP_JS); ?>" data-client-key="<?php echo htmlspecialchars(MIDTRANS_CLIENT_KEY); ?>"></script>
    
    <style>
        /* ==========================================================================
           DANATRAIL DESIGN SYSTEM TOKENS (UNIFIED SPEC)
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
            --shadow-hover: 0 6px 20px rgba(7, 53, 34, 0.09);

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

        /* ==========================================================================
           SIDEBAR (UNIFIED COMPACT 260px)
           ========================================================================== */
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

        /* ==========================================================================
           MAIN CONTENT LAYOUT
           ========================================================================== */
        .main-wrapper {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px 32px 120px 32px;
            max-width: 1440px;
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

        /* Header Title Bar */
        .page-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 14px;
        }

        .page-title-group h1 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.4px;
        }

        .page-title-group p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .btn-rent-new {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--gold-500);
            color: var(--forest-950);
            padding: 9px 18px;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            transition: background var(--transition-fast);
        }

        .btn-rent-new:hover {
            background: #eab000;
        }

        /* Toast Notifications */
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
           STATS SUMMARY GRID (COMPACT CARDS)
           ========================================================================== */
        .stats-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-metric-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-subtle);
            transition: transform var(--transition-fast);
        }

        .stat-metric-card:hover {
            transform: translateY(-2px);
            border-color: #ccd7d0;
        }

        .stat-icon-wrapper {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon-total { background: #f1f5f9; color: var(--forest-900); }
        .stat-icon-pending { background: #fef3c7; color: #b45309; }
        .stat-icon-active { background: #e0f2fe; color: #0369a1; }
        .stat-icon-done { background: #dcfce7; color: #15803d; }

        .stat-icon-wrapper svg {
            width: 20px;
            height: 20px;
        }

        .stat-metric-info {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
        }

        .stat-label-text {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 3px;
        }

        /* ==========================================================================
           FILTER STATUS TABS (HORIZONTAL CHIPS)
           ========================================================================== */
        .status-tabs-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .status-tabs-card::-webkit-scrollbar {
            display: none;
        }

        .filter-header-label {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            white-space: nowrap;
            margin-right: 4px;
        }

        .status-tab-pill {
            text-decoration: none;
            padding: 6px 14px;
            border-radius: var(--radius-pill);
            font-size: 12.5px;
            font-weight: 600;
            background: var(--bg-canvas);
            color: var(--text-body);
            border: 1px solid var(--border-color);
            white-space: nowrap;
            transition: var(--transition-fast);
        }

        .status-tab-pill:hover {
            background: #e9eee8;
            color: var(--text-main);
        }

        .status-tab-pill.active {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
            font-weight: 700;
        }

        /* ==========================================================================
           RENTALS DATA TABLE & CARD VIEW
           ========================================================================== */
        .table-container-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-subtle);
        }

        .table-responsive-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .rentals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .rentals-table th {
            background: #f1f4f0;
            color: var(--forest-950);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 800;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .rentals-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
            color: var(--text-body);
        }

        .rentals-table tr:last-child td {
            border-bottom: none;
        }

        .rentals-table tr:hover td {
            background: #fafbfa;
        }

        .rental-code-tag {
            font-family: monospace;
            font-size: 11.5px;
            font-weight: 700;
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            padding: 3px 6px;
            border-radius: 4px;
            color: var(--forest-900);
            white-space: nowrap;
        }

        .gear-identity-box {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        }

        .gear-thumb-img {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .gear-title-text {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.3;
        }

        .gear-category-text {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .schedule-date-box {
            white-space: nowrap;
        }

        .schedule-date-box b {
            color: var(--text-main);
            font-size: 12.5px;
        }

        .schedule-date-box small {
            display: block;
            color: var(--text-muted);
            font-size: 11px;
            margin-top: 1px;
        }

        .price-tag-value {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 800;
            color: var(--forest-900);
            white-space: nowrap;
        }

        /* Status Badges */
        .status-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pill-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .status-pill-active {
            background: #e0f2fe;
            color: #075985;
            border: 1px solid #bae6fd;
        }

        .status-pill-done {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status-pill-cancel {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .btn-cancel-rental {
            color: #dc2626;
            background: #fff;
            border: 1px solid #fca5a5;
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            transition: var(--transition-fast);
            white-space: nowrap;
        }

        .btn-cancel-rental:hover {
            background: #dc2626;
            color: #ffffff;
        }

        .empty-history-cell {
            text-align: center;
            padding: 48px 20px;
            color: var(--text-muted);
        }

        .empty-history-cell h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 8px;
            margin-bottom: 4px;
        }

        /* ==========================================================================
           RESPONSIVE BREAKPOINTS
           ========================================================================== */
        @media (max-width: 992px) {
            .main-wrapper {
                padding: 20px 20px 36px 20px;
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
            .stats-summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-summary-grid {
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
            <span>DANATRAIL</span>
        </div>
        <button type="button" class="btn-toggle-menu" onclick="toggleMobileSidebar()" aria-label="Buka Menu">
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
        
        <!-- Page Title & CTA -->
        <div class="page-header-bar">
            <div class="page-title-group">
                <h1>Riwayat Sewa & Transaksi</h1>
                <p>Pantau status konfirmasi alat, jadwal rental, dan rincian transaksi pemesananmu.</p>
            </div>
            <a href="dashboard.php" class="btn-rent-new">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>Sewa Alat Baru</span>
            </a>
        </div>

        <!-- Alert Toast Feedback -->
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

        <!-- KPI Metrics Grid -->
        <section class="stats-summary-grid">
            <div class="stat-metric-card">
                <div class="stat-icon-wrapper stat-icon-total">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div class="stat-metric-info">
                    <span class="stat-number"><?php echo $count_total; ?></span>
                    <span class="stat-label-text">Total Pemesanan</span>
                </div>
            </div>

            <div class="stat-metric-card">
                <div class="stat-icon-wrapper stat-icon-pending">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-metric-info">
                    <span class="stat-number" style="color: #b45309;"><?php echo $count_pending; ?></span>
                    <span class="stat-label-text">Menunggu Konfirmasi</span>
                </div>
            </div>

            <div class="stat-metric-card">
                <div class="stat-icon-wrapper stat-icon-active">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div class="stat-metric-info">
                    <span class="stat-number" style="color: #0369a1;"><?php echo $count_active; ?></span>
                    <span class="stat-label-text">Sedang Disewa (Aktif)</span>
                </div>
            </div>

            <div class="stat-metric-card">
                <div class="stat-icon-wrapper stat-icon-done">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-metric-info">
                    <span class="stat-number" style="color: #15803d;"><?php echo $count_done; ?></span>
                    <span class="stat-label-text">Selesai / Dikembalikan</span>
                </div>
            </div>
        </section>

        <!-- Status Filter Chips & Live Search Bar -->
        <section class="status-tabs-card" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                <span class="filter-header-label">Filter Status:</span>
                <a href="riwayat.php" class="status-tab-pill <?php echo empty($status_filter) ? 'active' : ''; ?>">
                    <span>Semua</span>
                </a>
                <a href="riwayat.php?status=Menunggu Konfirmasi" class="status-tab-pill <?php echo ($status_filter === 'Menunggu Konfirmasi') ? 'active' : ''; ?>">
                    <span>Menunggu (<?php echo $count_pending; ?>)</span>
                </a>
                <a href="riwayat.php?status=Disewa" class="status-tab-pill <?php echo ($status_filter === 'Disewa') ? 'active' : ''; ?>">
                    <span>Disewa (<?php echo $count_active; ?>)</span>
                </a>
                <a href="riwayat.php?status=Selesai" class="status-tab-pill <?php echo ($status_filter === 'Selesai') ? 'active' : ''; ?>">
                    <span>Selesai (<?php echo $count_done; ?>)</span>
                </a>
                <a href="riwayat.php?status=Dibatalkan" class="status-tab-pill <?php echo ($status_filter === 'Dibatalkan') ? 'active' : ''; ?>">
                    <span>Dibatalkan</span>
                </a>
            </div>

            <!-- Instant Live Search Bar -->
            <div style="display: flex; align-items: center; background: #ffffff; border: 1.5px solid var(--border-color); border-radius: var(--radius-pill); padding: 4px 12px; width: 100%; max-width: 320px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: var(--text-muted); margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" id="riwayatLiveSearch" placeholder="Live search kode, alat, jaminan..." oninput="liveFilterRiwayat(this.value)" autocomplete="off" style="border: none; outline: none; background: transparent; font-size: 13px; color: var(--text-main); width: 100%;">
            </div>
        </section>

        <!-- Rentals Data Table -->
        <section class="table-container-card">
            <div class="table-responsive-wrapper">
                <table class="rentals-table" id="riwayatTable">
                    <thead>
                        <tr>
                            <th>Kode Booking</th>
                            <th>Perlengkapan Outdoor</th>
                            <th>Jadwal Sewa</th>
                            <th>Durasi & Qty</th>
                            <th>Total Biaya</th>
                            <th>Status Pembayaran</th>
                            <th>Jaminan & Foto</th>
                            <th>Status Sewa</th>
                            <th style="text-align: right; min-width: 170px;">Aksi & Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rentals && mysqli_num_rows($rentals) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($rentals)): 
                                $guarantee_photo = $r['guarantee_photo'] ?? '';
                                $pay_method = $r['payment_method'] ?? 'Transfer Bank BCA';
                                $pay_status = $r['payment_status'] ?? 'Menunggu Pembayaran';
                                $pay_proof  = $r['payment_proof'] ?? '';
                                $is_basecamp = (stripos($pay_method, 'Basecamp') !== false || stripos($pay_method, 'Cash') !== false || stripos($pay_method, 'COD') !== false);
                                $wa_msg = urlencode("Halo Admin DANATRAIL, saya ingin konfirmasi pesanan sewa " . ($r['product_name'] ?? 'Alat') . " dengan Kode Booking: " . $r['rental_code']);
                            ?>
                                <tr class="riwayat-row" data-search="<?php echo htmlspecialchars(strtolower($r['rental_code'] . ' ' . ($r['product_name'] ?? '') . ' ' . $r['guarantee_type'] . ' ' . $pay_method . ' ' . $pay_status . ' ' . $r['status'])); ?>">
                                    <td>
                                        <span class="rental-code-tag"><?php echo htmlspecialchars($r['rental_code']); ?></span>
                                    </td>
                                    <td>
                                        <div class="gear-identity-box">
                                            <?php if (!empty($r['product_image']) && file_exists('../uploads/products/' . $r['product_image'])): ?>
                                                <img src="../uploads/products/<?php echo htmlspecialchars($r['product_image']); ?>" alt="Foto" class="gear-thumb-img">
                                            <?php else: ?>
                                                <img src="../assets/logo.png" alt="Foto" class="gear-thumb-img">
                                            <?php endif; ?>
                                            <div>
                                                <div class="gear-title-text"><?php echo htmlspecialchars($r['product_name'] ?? 'Alat Outdoor'); ?></div>
                                                <div class="gear-category-text"><?php echo htmlspecialchars($r['product_category'] ?? 'Outdoor Gear'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="schedule-date-box">
                                            <div style="font-size: 11.5px; color: var(--text-muted);">Sewa: <b><?php echo date('d M Y', strtotime($r['rental_date'])); ?></b></div>
                                            <div style="font-size: 11.5px; color: var(--forest-950);">Batas: <b><?php echo date('d M Y', strtotime($r['return_date'])); ?></b></div>
                                            <?php if (!empty($r['actual_return_date'])): ?>
                                                <div style="font-size: 11px; color: #166e4a; font-weight: 700; margin-top: 2px;">
                                                    Dikembalikan: <?php echo date('d M Y', strtotime($r['actual_return_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php 
                                        // Hitung denda & keterlambatan real-time
                                        $fine_calc = calculate_rental_fine(
                                            $r['rent_date'] ?? $r['rental_date'],
                                            $r['return_date'],
                                            $r['actual_return_date'],
                                            (float)$r['total_price'],
                                            $r['return_status']
                                        );
                                        $late_days = ($r['recorded_late_days'] !== null) ? (int)$r['recorded_late_days'] : $fine_calc['late_days'];
                                        $fine_amt  = ($r['recorded_fine_amount'] !== null) ? (float)$r['recorded_fine_amount'] : $fine_calc['fine_amount'];
                                        $fine_stat = $r['recorded_fine_status'] ?: ($late_days > 0 ? 'Belum Dibayar' : 'Bebas Denda');
                                        
                                        if ($late_days > 0 || $fine_amt > 0): 
                                        ?>
                                            <div style="margin-top: 6px; padding: 6px 8px; border-radius: 6px; background: #fef2f2; border: 1px solid #fecaca; font-size: 11px;">
                                                <div style="color: #b91c1c; font-weight: 700;">Terlambat: <?php echo $late_days; ?> Hari</div>
                                                <div style="color: #7f1d1d;">
                                                    Denda: <?php echo (($fine_calc['fine_method'] ?? $fine_calc['fine_type'] ?? '') === 'percentage_daily') ? ($fine_calc['fine_rate'] ?? 0) . '%/hari' : format_rupiah($fine_calc['fine_rate'] ?? 20000) . '/hari'; ?>
                                                </div>
                                                <div style="color: #991b1b; font-weight: 800; margin-top: 2px;">
                                                    Total Denda: <?php echo format_rupiah($fine_amt); ?>
                                                </div>
                                                <div style="margin-top: 3px;">
                                                    <span style="font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 999px; background: <?php echo ($fine_stat === 'Sudah Dibayar') ? '#dcfce7; color: #166e4a;' : (($fine_stat === 'Dibebaskan Admin') ? '#f3f4f6; color: #374151;' : '#fee2e2; color: #991b1b;'); ?>">
                                                        Denda: <?php echo htmlspecialchars($fine_stat); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--text-main);"><?php echo $r['duration_days']; ?> Hari</div>
                                        <div style="font-size: 11.5px; color: var(--text-muted);"><?php echo $r['quantity']; ?> Unit</div>
                                    </td>
                                    <td>
                                        <?php if ((float)($r['discount_amount'] ?? 0) > 0): ?>
                                            <div style="font-size: 11.5px; color: var(--text-muted); text-decoration: line-through;">
                                                Subtotal: <?php echo format_rupiah($r['subtotal_price'] ?? ($r['total_price'] + $r['discount_amount'])); ?>
                                            </div>
                                            <div style="font-size: 11px; color: #166e4a; font-weight: 700;">
                                                Diskon: -<?php echo format_rupiah($r['discount_amount']); ?>
                                                <?php if (!empty($r['discount_code'])): ?>
                                                    (<?php echo htmlspecialchars($r['discount_code']); ?>)
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="price-tag-value">
                                            Rp <?php echo number_format($r['total_price'], 0, ',', '.'); ?>
                                        </div>
                                        <?php if ($fine_amt > 0): ?>
                                            <div style="font-size: 11px; color: #dc2626; font-weight: 700; margin-top: 2px;">
                                                + Denda: <?php echo format_rupiah($fine_amt); ?>
                                            </div>
                                            <div style="font-size: 11.5px; font-weight: 800; color: var(--forest-950); margin-top: 2px; padding-top: 2px; border-top: 1px dashed var(--border-color);">
                                                Total Akhir: <?php echo format_rupiah($r['total_price'] + $fine_amt); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 2px;">
                                            <?php echo htmlspecialchars($pay_method); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                            <?php if ($is_basecamp): ?>
                                                <?php if ($pay_status === 'Lunas'): ?>
                                                    <span class="status-pill-badge status-pill-done" style="font-size: 11px;">✓ Sewa: Lunas (Basecamp)</span>
                                                <?php else: ?>
                                                    <span class="status-pill-badge" style="background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; font-size: 11px;">Sewa: Bayar di Tempat</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($pay_status === 'Lunas'): ?>
                                                    <span class="status-pill-badge status-pill-done" style="font-size: 11px;">✓ Sewa: Lunas Online</span>
                                                <?php elseif ($pay_status === 'Menunggu Verifikasi Admin'): ?>
                                                    <span class="status-pill-badge status-pill-active" style="font-size: 11px;">Sewa: Menunggu Verifikasi</span>
                                                <?php else: ?>
                                                    <span class="status-pill-badge status-pill-pending" style="font-size: 11px;">Sewa: Belum Bayar</span>
                                                <?php endif; ?>

                                                <?php $has_rental_proof = (!empty($pay_proof) && file_exists('../uploads/payments/' . $pay_proof)); ?>
                                                <?php if ($has_rental_proof): ?>
                                                    <div style="display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                        <span style="display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; font-weight: 700; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 1px 7px; border-radius: 9999px;">
                                                            ✓ Bukti Terkirim
                                                        </span>
                                                        <button type="button" onclick="openPhotoModal('../uploads/payments/<?php echo htmlspecialchars($pay_proof); ?>', 'Bukti Transfer (<?php echo htmlspecialchars(addslashes($r['rental_code'])); ?>)')" style="background: none; border: none; font-size: 11px; color: var(--forest-700); font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0;">
                                                            Lihat Bukti
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- Status Pembayaran Denda Keterlambatan Terpisah -->
                                            <?php if ($fine_amt > 0): ?>
                                                <?php if ($fine_stat === 'Sudah Dibayar'): ?>
                                                    <span class="status-pill-badge status-pill-done" style="font-size: 11px; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">✓ Denda: Sudah Lunas</span>
                                                <?php elseif ($fine_stat === 'Dibebaskan Admin'): ?>
                                                    <span class="status-pill-badge" style="font-size: 11px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">Denda: Dibebaskan Admin</span>
                                                <?php else: ?>
                                                    <span class="status-pill-badge" style="font-size: 11px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 700;">Denda: Belum Dibayar</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                            <span style="font-size: 11.5px; font-weight: 600; color: var(--text-body); background: var(--bg-canvas); padding: 2px 7px; border-radius: 4px; border: 1px solid var(--border-color);">
                                                <?php echo htmlspecialchars($r['guarantee_type'] ?? 'KTP Asli'); ?>
                                            </span>
                                            <span style="font-size: 10.5px; color: var(--text-muted); background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px dashed #cbd5e1; display: inline-flex; align-items: center; gap: 3px;">
                                                <span>Titip Fisik di Basecamp</span>
                                            </span>
                                            <?php if (!empty($guarantee_photo) && file_exists('../uploads/guarantees/' . $guarantee_photo)): ?>
                                                <button type="button" onclick="openPhotoModal('../uploads/guarantees/<?php echo htmlspecialchars($guarantee_photo); ?>', 'Foto Dokumen Jaminan: <?php echo htmlspecialchars(addslashes($r['guarantee_type'])); ?>')" style="background: none; border: none; font-size: 10.5px; color: var(--forest-700); font-weight: 700; cursor: pointer; text-decoration: underline; padding: 0;">
                                                    Lihat Foto Arsip
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $st = $r['status'];
                                        $ret_st = $r['return_status'] ?? '';
                                        if ($ret_st === 'Terlambat' || ($st === 'Disewa' && $r['return_date'] < date('Y-m-d'))) {
                                            echo '<span class="status-pill-badge" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 800;">Terlambat</span>';
                                        } elseif ($st === 'Menunggu Konfirmasi') {
                                            echo '<span class="status-pill-badge status-pill-pending">Menunggu Konfirmasi</span>';
                                        } elseif ($st === 'Disewa') {
                                            echo '<span class="status-pill-badge status-pill-active">Sedang Disewa</span>';
                                        } elseif ($st === 'Selesai') {
                                            echo '<span class="status-pill-badge status-pill-done">Selesai</span>';
                                        } else {
                                            echo '<span class="status-pill-badge status-pill-cancel">' . htmlspecialchars($st) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-end;">
                                            <!-- Tombol Bayar Online jika pembayaran online & belum lunas -->
                                            <?php if (!$is_basecamp && $pay_status !== 'Lunas' && $r['status'] !== 'Dibatalkan'): ?>
                                                <button type="button" onclick="payOnline(<?php echo $r['id']; ?>, <?php echo (int)$r['total_price']; ?>, '<?php echo htmlspecialchars(addslashes($r['rental_code'])); ?>', '<?php echo htmlspecialchars(addslashes($r['product_name'] ?? 'Alat Outdoor')); ?>')" style="padding: 6px 12px; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff; border: none; border-radius: 6px; font-size: 11.5px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(5,150,105,0.25); white-space: nowrap;">
                                                    <span>Bayar Online</span>
                                                </button>

                                                <button type="button" onclick="openPaymentUploadModal(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(addslashes($r['rental_code'])); ?>', '<?php echo number_format($r['total_price'], 0, ',', '.'); ?>')" style="background: none; border: none; font-size: 10.5px; color: var(--text-muted); font-weight: 600; cursor: pointer; text-decoration: underline; padding: 0;">
                                                    <?php echo !empty($has_rental_proof) ? 'Ganti Bukti Manual' : 'Upload Bukti Manual'; ?>
                                                 </button>
                                            <?php elseif ($is_basecamp && $pay_status !== 'Lunas' && $r['status'] !== 'Dibatalkan'): ?>
                                                <span style="font-size: 11px; color: #0f766e; font-weight: 700; text-align: right; display: inline-flex; align-items: center; gap: 4px;">
                                                    <span>🏠 Bayar di Kasir Basecamp</span>
                                                </span>
                                                <button type="button" onclick="openPaymentUploadModal(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(addslashes($r['rental_code'])); ?>', '<?php echo number_format($r['total_price'], 0, ',', '.'); ?>')" style="background: none; border: none; font-size: 10.5px; color: var(--text-muted); font-weight: 600; cursor: pointer; text-decoration: underline; padding: 0;">
                                                    Ingin Transfer Sekarang?
                                                </button>
                                            <?php endif; ?>

                                            <!-- Tombol Cetak Invoice -->
                                            <a href="../invoice.php?id=<?php echo $r['id']; ?>" target="_blank" style="padding: 4px 9px; background: #ffffff; color: var(--forest-800); border: 1.5px solid var(--border-color); border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                                                <span>Cetak Faktur</span>
                                            </a>

                                            <!-- Tombol WhatsApp CS -->
                                            <a href="https://wa.me/6288996016686?text=<?php echo $wa_msg; ?>" target="_blank" style="padding: 4px 9px; background: #25D366; color: #ffffff; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                                                <span>WhatsApp</span>
                                            </a>

                                            <!-- Tombol Batalkan -->
                                            <?php if ($r['status'] === 'Menunggu Konfirmasi'): ?>
                                                <a href="sewa_aksi.php?batal=<?php echo $r['id']; ?>" class="btn-cancel-rental" style="padding: 3px 8px; font-size: 11px;" onclick="return confirm('Apakah Anda yakin ingin membatalkan pengajuan sewa ini?');">
                                                    Batalkan
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-history-cell">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" style="color: var(--text-muted); margin: 0 auto 8px auto;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    <h4>Belum Ada Riwayat Sewa</h4>
                                    <p>Pemesanan sewa perlengkapan outdoor kamu akan tampil di sini.</p>
                                    <a href="dashboard.php" class="btn-rent-new" style="margin-top: 10px; display: inline-flex;">Jelajahi Katalog Alat</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Modal Simulasi / Gateway Pembayaran Online Instan -->
    <div class="modal-overlay" id="gatewayModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #ffffff; border-radius: 14px; max-width: 480px; width: 100%; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 100%); color: #ffffff;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #6ee7b7;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                    <div>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 800; color: #ffffff;">Pembayaran Online DANATRAIL</h3>
                        <p style="font-size: 11.5px; color: #a7f3d0;" id="gatewayCodeTxt">Kode: DNT-XXXX</p>
                    </div>
                </div>
                <button type="button" onclick="closeGatewayModal()" style="background: none; border: none; font-size: 24px; line-height: 1; cursor: pointer; color: #ffffff;">&times;</button>
            </div>

            <div style="padding: 20px;">
                <!-- Total Amount Card -->
                <div style="background: #f0fdf4; border: 1.5px solid #a7f3d0; border-radius: 10px; padding: 14px; text-align: center; margin-bottom: 18px;">
                    <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: #065f46; letter-spacing: 0.5px;">Total Tagihan Pembayaran:</div>
                    <div id="gatewayTotalTxt" style="font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 800; color: var(--forest-950); margin-top: 2px;">Rp 0</div>
                    <div id="gatewayProductTxt" style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Perlengkapan Outdoor</div>
                </div>

                <!-- Payment Channels -->
                <div style="margin-bottom: 18px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Pilih Metode Pembayaran Otomatis:</div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border: 1.5px solid var(--forest-700); background: #f8fafc; border-radius: 8px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="radio" name="pay_channel" value="BCA Virtual Account" checked>
                                <div>
                                    <strong style="font-size: 13px; color: var(--text-main);">BCA Virtual Account</strong>
                                    <div style="font-size: 11px; color: var(--text-muted);">Verifikasi otomatis 24 Jam</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: var(--forest-800);">INSTAN</span>
                        </label>

                        <label style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border: 1px solid var(--border-color); background: #ffffff; border-radius: 8px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="radio" name="pay_channel" value="QRIS">
                                <div>
                                    <strong style="font-size: 13px; color: var(--text-main);">QRIS (GoPay, OVO, ShopeePay, DANA)</strong>
                                    <div style="font-size: 11px; color: var(--text-muted);">Scan langsung via aplikasi e-wallet</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: #0284c7;">QRIS</span>
                        </label>

                        <label style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border: 1px solid var(--border-color); background: #ffffff; border-radius: 8px; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="radio" name="pay_channel" value="Mandiri / BRI Virtual Account">
                                <div>
                                    <strong style="font-size: 13px; color: var(--text-main);">Bank Mandiri / BRI VA</strong>
                                    <div style="font-size: 11px; color: var(--text-muted);">ATM / Mobile Banking</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 800; color: var(--forest-800);">VA</span>
                        </label>
                    </div>
                </div>

                <!-- Action Button -->
                <button type="button" id="btnExecutePay" onclick="executeAutoPayment()" style="width: 100%; padding: 12px 16px; background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-900) 100%); color: #ffffff; border: none; border-radius: 9px; font-size: 14px; font-weight: 800; font-family: 'Outfit', sans-serif; cursor: pointer; box-shadow: 0 4px 12px rgba(7,53,34,0.25); display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>Selesaikan Pembayaran</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Upload Bukti Transfer Santai & Ramah Pengguna -->
    <div class="modal-overlay" id="paymentUploadModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #ffffff; border-radius: 14px; max-width: 540px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 45px rgba(0,0,0,0.3);">
            <div style="flex-shrink: 0; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 100%); color: #ffffff;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.12); display: flex; align-items: center; justify-content: center; color: #a7f3d0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 800; color: #ffffff; margin: 0;">Pusat Pembayaran Sewa</h3>
                        <p style="font-size: 12px; color: #a7f3d0; margin: 2px 0 0 0;" id="uploadModalCode">Kode Booking: DNT-XXXX</p>
                    </div>
                </div>
                <button type="button" onclick="closePaymentUploadModal()" style="background: none; border: none; font-size: 24px; line-height: 1; cursor: pointer; color: #ffffff; padding: 4px;">&times;</button>
            </div>

            <div style="flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 20px;">
                <!-- Peaceful Reassurance Banner -->
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 10px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div style="font-size: 12px; color: #166534; line-height: 1.45;">
                        <strong style="display: block; color: #14532d; font-size: 12.5px; margin-bottom: 2px;">Penyewaan Tenang & Terjadwal</strong>
                        Peralatan yang Anda pesan sudah kami alokasikan. Anda dapat memilih rekening transfer atau jika memilih bayar di kasir, cukup datang langsung saat jadwal pengambilan.
                    </div>
                </div>

                <!-- Total Amount Card with 1-Click Copy -->
                <div style="background: #fafaf9; border: 1.5px solid var(--border-color); border-radius: 10px; padding: 14px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Nominal Yang Harus Dibayar:</span>
                        <div id="uploadModalNominal" style="font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 800; color: var(--forest-900); margin-top: 2px;">Rp 0</div>
                    </div>
                    <button type="button" onclick="copyRentalUploadNominal()" style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #ffffff; border: 1.5px solid var(--forest-700); color: var(--forest-900); border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                        <span>📋 Salin Nominal</span>
                    </button>
                </div>

                <!-- Bank Selection Tabs -->
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Pilih Rekening Tujuan Transfer:</div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">
                        <button type="button" class="tab-sewa-bank-btn active" onclick="switchSewaBank('bca', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--forest-800); background: var(--forest-800); color: #fff; font-size: 12px; font-weight: 700; cursor: pointer;">Bank BCA</button>
                        <button type="button" class="tab-sewa-bank-btn" onclick="switchSewaBank('mandiri', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-body); font-size: 12px; font-weight: 700; cursor: pointer;">Mandiri</button>
                        <button type="button" class="tab-sewa-bank-btn" onclick="switchSewaBank('bri', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-body); font-size: 12px; font-weight: 700; cursor: pointer;">BRI</button>
                        <button type="button" class="tab-sewa-bank-btn" onclick="switchSewaBank('qris', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-body); font-size: 12px; font-weight: 700; cursor: pointer;">QRIS Instan</button>
                        <button type="button" class="tab-sewa-bank-btn" onclick="switchSewaBank('basecamp', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-body); font-size: 12px; font-weight: 700; cursor: pointer;">Bayar di Basecamp</button>
                    </div>

                    <!-- Panels -->
                    <div id="sewaPanel_bca" class="sewa-bank-panel" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 13.5px; color: #1e293b; display: block;">Bank Central Asia (BCA)</strong>
                                <span style="font-family: monospace; font-size: 15px; font-weight: 800; color: #0284c7;">7310-0987-6543</span>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;">a/n PT DANATRAIL INDONESIA</div>
                            </div>
                            <button type="button" onclick="copyTextToClipboard('731009876543', 'No. Rekening BCA')" style="padding: 6px 12px; background: #0284c7; color: #ffffff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                📋 Salin
                            </button>
                        </div>
                    </div>

                    <div id="sewaPanel_mandiri" class="sewa-bank-panel" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 13.5px; color: #1e293b; display: block;">Bank Mandiri</strong>
                                <span style="font-family: monospace; font-size: 15px; font-weight: 800; color: #d97706;">137-00-1928374-1</span>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;">a/n PT DANATRAIL INDONESIA</div>
                            </div>
                            <button type="button" onclick="copyTextToClipboard('1370019283741', 'No. Rekening Mandiri')" style="padding: 6px 12px; background: #d97706; color: #ffffff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                📋 Salin
                            </button>
                        </div>
                    </div>

                    <div id="sewaPanel_bri" class="sewa-bank-panel" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 13.5px; color: #1e293b; display: block;">Bank Rakyat Indonesia (BRI)</strong>
                                <span style="font-family: monospace; font-size: 15px; font-weight: 800; color: #0284c7;">0123-01-098765-50-8</span>
                                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 1px;">a/n PT DANATRAIL INDONESIA</div>
                            </div>
                            <button type="button" onclick="copyTextToClipboard('012301098765508', 'No. Rekening BRI')" style="padding: 6px 12px; background: #0284c7; color: #ffffff; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                📋 Salin
                            </button>
                        </div>
                    </div>

                    <div id="sewaPanel_qris" class="sewa-bank-panel" style="display: none; background: #f0fdf4; border: 1px solid #a7f3d0; border-radius: 10px; padding: 12px 14px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 50px; height: 50px; background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 900; color: #166534; font-size: 14px;">
                                QRIS
                            </div>
                            <div>
                                <strong style="font-size: 13px; color: #14532d; display: block;">QRIS Resmi DANATRAIL INDONESIA</strong>
                                <div style="font-size: 12px; color: #166534; margin-top: 2px;">Dapat di-scan dengan semua aplikasi m-Banking dan e-Wallet (BCA, Livin, BRImo, GoPay, OVO, ShopeePay, DANA). Hubungi WhatsApp Admin untuk bantuan instan.</div>
                            </div>
                        </div>
                    </div>

                    <div id="sewaPanel_basecamp" class="sewa-bank-panel" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px 14px;">
                        <strong style="font-size: 13px; color: #1e40af; display: block;">Bayar Langsung di Basecamp (Cash / Debit)</strong>
                        <div style="font-size: 12px; color: #1e3a8a; margin-top: 2px;">
                            Sangat santai! Jika Anda memilih opsi ini, Anda tidak perlu transfer atau upload bukti sekarang. Cukup bayar saat serah terima peralatan di Basecamp DANATRAIL.
                        </div>
                    </div>
                </div>

                <form action="sewa_aksi.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_bukti_bayar" value="1">
                    <input type="hidden" name="rental_id" id="uploadRentalId" value="">

                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">
                            Unggah Foto / Screenshot Struk Bukti Transfer
                        </label>
                        <div style="border: 2px dashed var(--border-color); border-radius: 10px; padding: 16px; text-align: center; background: #fafdfb; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('paymentProofFileInput').click()">
                            <input type="file" name="payment_proof" id="paymentProofFileInput" accept="image/*,.pdf" required style="display: none;" onchange="previewRentalProof(this)">
                            
                            <div id="sewaProofPrompt">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color: var(--forest-700); margin: 0 auto 6px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <div style="font-size: 12.5px; font-weight: 700; color: var(--forest-950);">Klik untuk Memilih Bukti Pembayaran</div>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Format JPG, PNG, WEBP, atau PDF (Maksimal 5MB)</div>
                            </div>

                            <div id="sewaProofSelected" style="display: none; align-items: center; justify-content: center; gap: 12px;">
                                <img id="sewaProofThumb" src="" alt="Thumbnail Bukti" style="width: 52px; height: 52px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                <div style="text-align: left;">
                                    <div id="sewaProofFileName" style="font-size: 12.5px; font-weight: 700; color: var(--forest-950); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Bukti Transfer</div>
                                    <div style="font-size: 11.5px; color: #16a34a; font-weight: 600;">✓ Foto siap dikirim</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Helpful Assistant Notice -->
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; padding: 10px 12px; background: #f8fafc; border-radius: 8px;">
                        <span style="font-size: 12px; color: var(--text-muted);">Ada kendala transfer?</span>
                        <a id="sewaModalWaHelpLink" href="https://wa.me/6288996016686" target="_blank" style="font-size: 12px; font-weight: 700; color: #16a34a; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            💬 Hubungi Admin via WhatsApp
                        </a>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="closePaymentUploadModal()" style="padding: 9px 16px; border-radius: 8px; border: 1.5px solid var(--border-color); background: #ffffff; font-weight: 700; font-size: 13px; cursor: pointer;">
                            Batal
                        </button>
                        <button type="submit" style="padding: 9px 20px; border-radius: 8px; border: none; background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-900) 100%); color: #ffffff; font-weight: 800; font-size: 13px; font-family: 'Outfit', sans-serif; cursor: pointer;">
                            Kirim Bukti Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Preview Foto Dokumen Jaminan / Bukti Transfer -->
    <div class="modal-overlay" id="photoPreviewModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #ffffff; border-radius: 14px; max-width: 520px; width: 100%; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
            <div style="padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); background: var(--bg-canvas);">
                <strong id="photoModalTitle" style="font-size: 14px; color: var(--forest-950);">Dokumen Jaminan</strong>
                <button type="button" onclick="closePhotoModal()" style="background: none; border: none; font-size: 20px; line-height: 1; cursor: pointer; color: var(--text-muted);">&times;</button>
            </div>
            <div style="padding: 16px; text-align: center; background: #0b1c13;">
                <img id="photoModalImg" src="" alt="Foto" style="max-height: 380px; max-width: 100%; object-fit: contain; border-radius: 6px;">
            </div>
            <div style="padding: 12px 18px; text-align: right; background: #ffffff; border-top: 1px solid var(--border-color);">
                <button type="button" onclick="closePhotoModal()" style="padding: 6px 16px; border-radius: 6px; background: var(--forest-900); color: #ffffff; font-weight: 700; border: none; font-size: 12.5px; cursor: pointer;">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Popup -->
    <div id="danatrailToast" style="display: none; position: fixed; bottom: 24px; right: 24px; z-index: 9999; background: #064e3b; color: #ffffff; padding: 12px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 10px 25px rgba(0,0,0,0.2); align-items: center; gap: 8px;">
        <span id="toastIcon">✓</span>
        <span id="toastMsg">Teks berhasil disalin!</span>
    </div>

    <script>
        let currentActiveRentalId = 0;
        let currentRentalUploadDigits = "0";

        // Payment Online Gateway Handler (Midtrans Snap & Instant Gateway)
        function payOnline(rentalId, totalAmount, rentalCode, productName) {
            currentActiveRentalId = rentalId;

            // Fetch Snap Token from Backend
            const formData = new FormData();
            formData.append('rental_id', rentalId);

            fetch('../payment/get_snap_token.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.token && !data.simulator && typeof window.snap !== 'undefined' && typeof window.snap.pay === 'function') {
                    // Panggil Midtrans Snap Popup
                    window.snap.pay(data.token, {
                        onSuccess: function(result) {
                            const oid = encodeURIComponent(result.order_id || data.order_id || '');
                            const ptype = encodeURIComponent(result.payment_type || 'snap');
                            window.location.href = '../payment/finish_payment.php?id=' + rentalId + '&order_id=' + oid + '&status=success&payment_type=' + ptype;
                        },
                        onPending: function(result) {
                            const oid = encodeURIComponent(result.order_id || data.order_id || '');
                            window.location.href = '../payment/finish_payment.php?id=' + rentalId + '&order_id=' + oid + '&status=pending';
                        },
                        onError: function(result) {
                            alert('Pembayaran gagal atau dibatalkan.');
                        },
                        onClose: function() {
                            console.log('Customer closed the payment popup');
                        }
                    });
                } else if (data.success && !data.simulator && data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else if (data.success && data.simulator) {
                    // Buka Gateway Modal Simulator Otomatis (hanya jika key belum diisi)
                    document.getElementById('gatewayCodeTxt').innerText = 'Kode: ' + rentalCode;
                    document.getElementById('gatewayTotalTxt').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalAmount);
                    document.getElementById('gatewayProductTxt').innerText = productName;
                    document.getElementById('gatewayModal').style.display = 'flex';
                } else {
                    alert(data.error || 'Gagal memulai pembayaran Midtrans.');
                }
            })
            .catch(err => {
                // Fallback Modal
                document.getElementById('gatewayCodeTxt').innerText = 'Kode: ' + rentalCode;
                document.getElementById('gatewayTotalTxt').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(totalAmount);
                document.getElementById('gatewayProductTxt').innerText = productName;
                document.getElementById('gatewayModal').style.display = 'flex';
            });
        }

        function closeGatewayModal() {
            document.getElementById('gatewayModal').style.display = 'none';
        }

        function executeAutoPayment() {
            const btn = document.getElementById('btnExecutePay');
            btn.innerText = 'Memproses Pembayaran Otomatis...';
            btn.disabled = true;

            const selectedChannel = document.querySelector('input[name="pay_channel"]:checked')?.value || 'Virtual Account';

            setTimeout(function() {
                window.location.href = '../payment/finish_payment.php?id=' + currentActiveRentalId + '&status=success&payment_type=' + encodeURIComponent(selectedChannel);
            }, 800);
        }

        // Live Search Filter for Riwayat Table
        function liveFilterRiwayat(query) {
            const q = query.trim().toLowerCase();
            const rows = document.querySelectorAll('#riwayatTable tbody tr.riwayat-row');

            rows.forEach(row => {
                const searchTxt = row.getAttribute('data-search') || '';
                if (q === '' || searchTxt.includes(q)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Photo Preview Modal
        function openPhotoModal(imgSrc, title) {
            document.getElementById('photoModalImg').src = imgSrc;
            document.getElementById('photoModalTitle').innerText = title;
            const modal = document.getElementById('photoPreviewModal');
            modal.style.display = 'flex';
        }

        function closePhotoModal() {
            document.getElementById('photoPreviewModal').style.display = 'none';
        }

        // Payment Upload Modal
        function openPaymentUploadModal(rentalId, rentalCode, nominal) {
            document.getElementById('uploadRentalId').value = rentalId;
            document.getElementById('uploadModalCode').innerText = 'Kode Booking: ' + rentalCode;
            document.getElementById('uploadModalNominal').innerText = 'Rp ' + nominal;
            currentRentalUploadDigits = nominal.replace(/[^0-9]/g, '');

            const waMsg = encodeURIComponent("Halo Admin DANATRAIL, saya ingin konfirmasi pembayaran sewa dengan kode: " + rentalCode + " sebesar Rp " + nominal);
            document.getElementById('sewaModalWaHelpLink').href = "https://wa.me/6288996016686?text=" + waMsg;

            // Reset upload preview
            document.getElementById('sewaProofPrompt').style.display = 'block';
            document.getElementById('sewaProofSelected').style.display = 'none';
            document.getElementById('paymentProofFileInput').value = '';

            document.getElementById('paymentUploadModal').style.display = 'flex';
        }

        function closePaymentUploadModal() {
            document.getElementById('paymentUploadModal').style.display = 'none';
        }

        function switchSewaBank(bankKey, btn) {
            document.querySelectorAll('.tab-sewa-bank-btn').forEach(b => {
                b.style.borderColor = 'var(--border-color)';
                b.style.background = '#ffffff';
                b.style.color = 'var(--text-body)';
            });
            btn.style.borderColor = 'var(--forest-800)';
            btn.style.background = 'var(--forest-800)';
            btn.style.color = '#ffffff';

            document.querySelectorAll('.sewa-bank-panel').forEach(panel => {
                panel.style.display = 'none';
            });

            const target = document.getElementById('sewaPanel_' + bankKey);
            if (target) target.style.display = 'block';
        }

        function copyRentalUploadNominal() {
            if (currentRentalUploadDigits) {
                copyTextToClipboard(currentRentalUploadDigits, 'Nominal Pembayaran (Rp ' + document.getElementById('uploadModalNominal').innerText.replace('Rp ', '') + ')');
            }
        }

        function previewRentalProof(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('sewaProofFileName').innerText = file.name;

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('sewaProofThumb').src = e.target.result;
                        document.getElementById('sewaProofPrompt').style.display = 'none';
                        document.getElementById('sewaProofSelected').style.display = 'flex';
                    }
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('sewaProofThumb').src = 'https://cdn-icons-png.flaticon.com/512/337/337946.png';
                    document.getElementById('sewaProofPrompt').style.display = 'none';
                    document.getElementById('sewaProofSelected').style.display = 'flex';
                }
            }
        }

        function showToast(message) {
            const toast = document.getElementById('danatrailToast');
            const msgEl = document.getElementById('toastMsg');
            if (!toast || !msgEl) return;
            msgEl.innerText = message;
            toast.style.display = 'flex';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        function copyTextToClipboard(text, label) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('✓ ' + label + ' berhasil disalin!');
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
                showToast('✓ ' + label + ' berhasil disalin!');
            } catch (err) {
                prompt("Salin teks berikut:", text);
            }
            document.body.removeChild(textArea);
        }

        // Toggle Mobile Sidebar Drawer
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('appSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar && backdrop) {
                sidebar.classList.toggle('mobile-open');
                backdrop.classList.toggle('active');
            }
        }

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePhotoModal();
                closePaymentUploadModal();
                closeGatewayModal();
                const sidebar = document.getElementById('appSidebar');
                const backdrop = document.getElementById('sidebarBackdrop');
                if (sidebar && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    backdrop.classList.remove('active');
                }
            }
        });
    </script>
</body>

</html>
