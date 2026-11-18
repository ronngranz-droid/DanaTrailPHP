<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek akses admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=" . urlencode("Akses ditolak! Anda bukan Administrator."));
    exit;
}

require_once '../config/config.php';
/** @var mysqli $conn */

// Jalankan sinkronisasi keterlambatan otomatis
sync_rental_overdue_status($conn);

$pesan = $_GET['pesan'] ?? '';
$pesan_type = $_GET['type'] ?? 'info';
$filter = $_GET['filter'] ?? 'semua';
$search = trim($_GET['search'] ?? '');

// Ambil pengaturan sistem denda
$fine_enabled = (int)get_system_setting('fine_enabled', '1');
$fine_method = get_system_setting('fine_method', 'fixed_daily');
$fine_rate = (float)get_system_setting('fine_rate', '20000');
$fine_grace_period = (int)get_system_setting('fine_grace_period', '1');
$fine_max_amount = (float)get_system_setting('fine_max_amount', '0');

// Query ringkasan statistik denda
$q_stats = mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN r.return_status = 'Terlambat' OR (r.status = 'Disewa' AND r.return_date < CURDATE()) THEN 1 END) as total_late_active,
    COUNT(CASE WHEN rf.payment_status = 'Belum Dibayar' THEN 1 END) as total_unpaid_fines,
    COUNT(CASE WHEN rf.payment_status = 'Sudah Dibayar' THEN 1 END) as total_paid_fines,
    COALESCE(SUM(CASE WHEN rf.payment_status = 'Sudah Dibayar' THEN rf.fine_amount ELSE 0 END), 0) as total_revenue_fines
FROM rentals r
LEFT JOIN rental_fines rf ON r.id = rf.rental_id");
$stats = $q_stats ? mysqli_fetch_assoc($q_stats) : [];

// Query pending rentals count
$q_pending = mysqli_query($conn, "SELECT COUNT(*) FROM rentals WHERE status = 'Menunggu Konfirmasi'");
$total_pending_rentals = $q_pending ? (int)mysqli_fetch_row($q_pending)[0] : 0;

// Query data rental dengan denda
$query_rentals = "SELECT r.*, 
    u.name as penyewa_nama, 
    u.email as penyewa_email, 
    u.phone as penyewa_phone, 
    p.name as product_name, 
    p.category as product_category,
    rf.id as fine_id,
    rf.late_days as recorded_late_days,
    rf.fine_amount as recorded_fine_amount,
    rf.payment_status as fine_status,
    rf.payment_method as fine_payment_method,
    rf.paid_at as fine_paid_at,
    rf.notes as fine_notes
FROM rentals r
LEFT JOIN users u ON r.user_id = u.id
LEFT JOIN products p ON r.product_id = p.id
LEFT JOIN rental_fines rf ON r.id = rf.rental_id
WHERE r.status != 'Dibatalkan'";

if ($filter === 'terlambat') {
    $query_rentals .= " AND (r.return_status = 'Terlambat' OR (r.status = 'Disewa' AND r.return_date < CURDATE()) OR (r.actual_return_date IS NOT NULL AND r.actual_return_date > r.return_date))";
} elseif ($filter === 'sudah_dikembalikan') {
    $query_rentals .= " AND (r.status = 'Selesai' OR (r.actual_return_date IS NOT NULL AND r.actual_return_date != '0000-00-00'))";
} elseif ($filter === 'belum_dikembalikan') {
    $query_rentals .= " AND (r.status = 'Disewa' AND (r.actual_return_date IS NULL OR r.actual_return_date = '0000-00-00'))";
} elseif ($filter === 'denda_belum_dibayar') {
    $query_rentals .= " AND (rf.payment_status = 'Belum Dibayar' OR (rf.payment_status IS NULL AND (r.return_status = 'Terlambat' OR (r.status = 'Disewa' AND r.return_date < CURDATE()))))";
} elseif ($filter === 'denda_sudah_dibayar') {
    $query_rentals .= " AND (rf.payment_status = 'Sudah Dibayar' OR r.fine_payment_status = 'Sudah Dibayar')";
} else {
    // Filter 'semua': tampilkan semua yang aktif disewa, selesai, atau memiliki catatan denda
    $query_rentals .= " AND (r.status IN ('Disewa', 'Selesai', 'Terlambat') OR rf.id IS NOT NULL)";
}

if (!empty($search)) {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $query_rentals .= " AND (u.name LIKE '%$safe_search%' OR p.name LIKE '%$safe_search%' OR r.id = '$safe_search' OR r.rental_code LIKE '%$safe_search%')";
}

$query_rentals .= " ORDER BY r.id DESC";
$res_rentals = mysqli_query($conn, $query_rentals);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Denda Keterlambatan — Admin DANATRAIL</title>
    <!-- Google Fonts -->
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

            --gold-500: #f5b800;
            --gold-600: #d99b00;

            --bg-canvas: #f7f8f5;
            --surface-card: #ffffff;
            --border-color: #e4e9e5;
            --text-main: #101914;
            --text-body: #33443b;
            --text-muted: #66746c;

            --radius-xl: 16px;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
            --radius-pill: 9999px;

            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04);
            --shadow-card: 0 2px 10px rgba(7, 53, 34, 0.05);
            --transition-fast: 0.15s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
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
            width: 100%;
        }

        .mobile-brand {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mobile-brand-title {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: #ffffff;
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            color: #ffffff;
            padding: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            padding: 24px 28px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .header-left h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--forest-950);
            letter-spacing: -0.3px;
        }

        .header-left p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .btn-header-sync {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #ffffff;
            color: var(--forest-800);
            font-size: 13px;
            font-weight: 700;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            cursor: pointer;
            text-decoration: none;
            box-shadow: var(--shadow-subtle);
            transition: var(--transition-fast);
        }

        .btn-header-sync:hover {
            background: #f0fdf4;
            border-color: #86efac;
        }

        .alert-banner {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Settings Card */
        .settings-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-subtle);
        }

        .settings-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: var(--forest-950);
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr) auto;
            gap: 16px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--forest-950);
        }

        .form-input, .form-select {
            padding: 8px 12px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            font-size: 13px;
            font-family: inherit;
            color: var(--text-body);
            background: #ffffff;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--forest-600);
            box-shadow: 0 0 0 3px rgba(35, 137, 94, 0.15);
        }

        .btn-save-settings {
            padding: 9px 20px;
            background: var(--forest-800);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-fast);
            white-space: nowrap;
            height: 38px;
        }

        .btn-save-settings:hover {
            background: var(--forest-900);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 16px;
            box-shadow: var(--shadow-subtle);
        }

        .stat-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--forest-950);
            margin-top: 6px;
        }

        .stat-sub {
            font-size: 11.5px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 4px;
        }

        /* Filter Controls */
        .filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .filter-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 6px 12px;
            border-radius: var(--radius-pill);
            background: #ffffff;
            border: 1px solid var(--border-color);
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition-fast);
        }

        .filter-pill:hover {
            background: #f1f4f2;
            color: var(--forest-900);
        }

        .filter-pill.active {
            background: var(--forest-800);
            color: #ffffff;
            border-color: var(--forest-800);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 4px 10px;
        }

        .search-input {
            border: none;
            outline: none;
            font-size: 12.5px;
            font-family: inherit;
            color: var(--text-body);
            width: 180px;
        }

        /* Table Card */
        .card-container {
            background: var(--surface-card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        thead th {
            background: #f9faf8;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition-fast);
        }

        tbody tr:hover {
            background-color: #fafbfa;
        }

        tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            color: var(--text-body);
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .badge-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .badge-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .badge-neutral {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
        }

        .btn-fine-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            background: #ffffff;
            color: var(--forest-800);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .btn-fine-action:hover {
            background: #f0fdf4;
            border-color: #86efac;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(4, 31, 20, 0.65);
            backdrop-filter: blur(4px);
            z-index: 100;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: var(--radius-xl);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.2s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: var(--forest-950);
        }

        .modal-close-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--border-color);
            background: #fafbfa;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-cancel {
            padding: 8px 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-body);
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
        }

        .btn-submit {
            padding: 8px 16px;
            border-radius: var(--radius-md);
            border: none;
            background: var(--forest-800);
            color: #ffffff;
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: var(--forest-900);
        }

        @media (max-width: 1100px) {
            .settings-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .mobile-top-bar { display: flex; }
            .content-area { padding: 16px; }
            .settings-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <!-- Mobile Top Bar -->
    <div class="mobile-top-bar">
        <div class="mobile-brand">
            <span class="mobile-brand-title">DANATRAIL ADMIN</span>
        </div>
        <button type="button" class="mobile-menu-btn" onclick="toggleAdminSidebar()" aria-label="Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Sidebar Inclusion -->
    <?php include 'menu.php'; ?>

    <div class="main-wrapper">
        <div class="content-area">
            <!-- Header -->
            <div class="page-header">
                <div class="header-left">
                    <h1>Pengaturan Denda Keterlambatan</h1>
                    <p>Kelola metode kalkulasi denda, grace period toleransi, dan status pembayaran denda rental.</p>
                </div>
                <a href="denda_aksi.php?action=sync" class="btn-header-sync" title="Perbarui status rental terlambat sekarang">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Sinkronisasi Status Terlambat</span>
                </a>
            </div>

            <!-- Pesan Notifikasi -->
            <?php if (!empty($pesan)): ?>
                <div class="alert-banner <?php echo ($pesan_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
                    <span><?php echo htmlspecialchars($pesan); ?></span>
                    <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;cursor:pointer;color:inherit;font-weight:700;">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($total_pending_rentals > 0): ?>
                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-lg); padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#d97706" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div style="font-size: 13px; color: #92400e;">
                            Ada <strong><?php echo $total_pending_rentals; ?> pesanan rental baru</strong> yang berstatus <strong>Menunggu Konfirmasi</strong>. Denda keterlambatan baru akan aktif dihitung setelah pesanan disetujui / status menjadi <em>Sedang Disewa</em>.
                        </div>
                    </div>
                    <a href="transaksi.php?status=Menunggu+Konfirmasi" style="padding: 6px 14px; background: #d97706; color: #ffffff; text-decoration: none; border-radius: var(--radius-sm); font-size: 12px; font-weight: 700; white-space: nowrap;">Periksa di Data Penyewaan &rarr;</a>
                </div>
            <?php endif; ?>

            <!-- Settings Form Card -->
            <div class="settings-card">
                <div class="settings-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Konfigurasi Aturan Denda</span>
                </div>
                <form action="denda_aksi.php" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="settings-grid">
                        <div class="form-group">
                            <label class="form-label">Sistem Denda</label>
                            <select name="fine_enabled" class="form-select">
                                <option value="1" <?php echo $fine_enabled ? 'selected' : ''; ?>>Aktif (Hitung Denda Otomatis)</option>
                                <option value="0" <?php echo !$fine_enabled ? 'selected' : ''; ?>>Nonaktif (Bebas Denda)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Metode Perhitungan</label>
                            <select name="fine_method" id="fineMethodSelect" class="form-select" onchange="updateMethodLabel(this.value)">
                                <option value="fixed_daily" <?php echo ($fine_method === 'fixed_daily') ? 'selected' : ''; ?>>Nominal Tetap / Hari (Rp)</option>
                                <option value="percentage_daily" <?php echo ($fine_method === 'percentage_daily') ? 'selected' : ''; ?>>Persentase / Hari (%)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" id="fineRateLabel"><?php echo ($fine_method === 'percentage_daily') ? 'Persentase Denda (%/hari)' : 'Nominal Denda (Rp/hari)'; ?></label>
                            <input type="number" step="any" min="0" name="fine_rate" class="form-input" value="<?php echo $fine_rate; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Grace Period (Toleransi Hari)</label>
                            <input type="number" min="0" name="fine_grace_period" class="form-input" value="<?php echo $fine_grace_period; ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Maksimal Denda (Rp)</label>
                            <input type="number" min="0" name="fine_max_amount" class="form-input" value="<?php echo $fine_max_amount; ?>">
                        </div>
                        <div>
                            <button type="submit" class="btn-save-settings">Simpan Pengaturan</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Rental Terlambat Aktif</div>
                    <div class="stat-value" style="color: #dc2626;"><?php echo (int)($stats['total_late_active'] ?? 0); ?> Unit</div>
                    <div class="stat-sub">Belum dikembalikan ke basecamp</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Denda Belum Dibayar</div>
                    <div class="stat-value" style="color: #d97706;"><?php echo (int)($stats['total_unpaid_fines'] ?? 0); ?> Transaksi</div>
                    <div class="stat-sub">Menunggu pelunasan denda</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Denda Sudah Dilunasi</div>
                    <div class="stat-value" style="color: #166e4a;"><?php echo (int)($stats['total_paid_fines'] ?? 0); ?> Transaksi</div>
                    <div class="stat-sub">Telah dikonfirmasi admin</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Penerimaan Denda</div>
                    <div class="stat-value" style="font-size: 19px; color: var(--forest-950);"><?php echo format_rupiah($stats['total_revenue_fines'] ?? 0); ?></div>
                    <div class="stat-sub">Kas masuk dari denda</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div class="filter-pills">
                    <a href="?filter=semua" class="filter-pill <?php echo ($filter === 'semua') ? 'active' : ''; ?>">Semua</a>
                    <a href="?filter=terlambat" class="filter-pill <?php echo ($filter === 'terlambat') ? 'active' : ''; ?>">Terlambat</a>
                    <a href="?filter=belum_dikembalikan" class="filter-pill <?php echo ($filter === 'belum_dikembalikan') ? 'active' : ''; ?>">Belum Dikembalikan</a>
                    <a href="?filter=sudah_dikembalikan" class="filter-pill <?php echo ($filter === 'sudah_dikembalikan') ? 'active' : ''; ?>">Sudah Dikembalikan</a>
                    <a href="?filter=denda_belum_dibayar" class="filter-pill <?php echo ($filter === 'denda_belum_dibayar') ? 'active' : ''; ?>">Denda Belum Dibayar</a>
                    <a href="?filter=denda_sudah_dibayar" class="filter-pill <?php echo ($filter === 'denda_sudah_dibayar') ? 'active' : ''; ?>">Denda Sudah Dibayar</a>
                </div>
                <form action="denda.php" method="GET" class="search-box">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" name="search" class="search-input" placeholder="Cari penyewa / produk..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>

            <!-- Table Container -->
            <div class="card-container">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Penyewa</th>
                                <th>Produk Rental</th>
                                <th>Batas Pengembalian</th>
                                <th>Status Pengembalian</th>
                                <th>Durasi Telat</th>
                                <th>Perhitungan Denda</th>
                                <th>Status Rental</th>
                                <th>Status Denda</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_rentals && mysqli_num_rows($res_rentals) > 0): ?>
                                <?php while ($r = mysqli_fetch_assoc($res_rentals)): 
                                    // Hitung denda real-time jika belum tercatat di rental_fines atau masih aktif
                                    $calc = calculate_rental_fine($r);
                                    
                                    // Jika sudah ada record permanen denda yang dikonfirmasi, pakai yang tercatat
                                    $late_days = ($r['fine_id'] && $r['recorded_late_days'] !== null) ? (int)$r['recorded_late_days'] : $calc['late_days'];
                                    $fine_amount = ($r['fine_id'] && $r['recorded_fine_amount'] !== null) ? (float)$r['recorded_fine_amount'] : $calc['fine_amount'];
                                    $fine_status = $r['fine_status'] ?: ($late_days > 0 ? 'Belum Dibayar' : 'Tanpa Denda');
                                    $is_returned = (!empty($r['actual_return_date']) && $r['actual_return_date'] != '0000-00-00');
                                    $is_past_due = (strtotime($r['return_date']) < strtotime(date('Y-m-d')));
                                ?>
                                    <tr>
                                        <td>
                                            <strong style="color: var(--forest-950);"><?php echo htmlspecialchars($r['penyewa_nama'] ?? 'User #' . $r['user_id']); ?></strong>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($r['penyewa_phone'] ?: $r['penyewa_email'] ?: '-'); ?></div>
                                            <div style="font-size: 11px; font-weight: 700; color: var(--forest-700);">Rental #<?php echo $r['id']; ?></div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['product_name']); ?></strong>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($r['product_category']); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo date('d M Y', strtotime($r['return_date'])); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted);">Sewa: <?php echo date('d M Y', strtotime($r['rental_date'] ?? $r['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($is_returned): ?>
                                                <div><?php echo date('d M Y', strtotime($r['actual_return_date'])); ?></div>
                                                <div style="font-size: 11px; color: #166e4a; font-weight: 600;">Sudah Kembali</div>
                                            <?php elseif ($is_past_due): ?>
                                                <span style="color: #dc2626; font-weight: 700; font-size: 12px;">Lewat Batas Kembali</span>
                                                <div style="font-size: 10.5px; color: #b91c1c;">Belum dikembalikan</div>
                                            <?php else: ?>
                                                <span style="color: var(--forest-800); font-weight: 600; font-size: 12px;">Masa Sewa Berjalan</span>
                                                <div style="font-size: 10.5px; color: var(--text-muted);">Belum jatuh tempo</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($late_days > 0): ?>
                                                <strong style="color: #dc2626; font-size: 14px;"><?php echo $late_days; ?> Hari</strong>
                                                <?php if (!$is_returned): ?>
                                                    <div style="font-size: 10.5px; color: var(--text-muted);">Masih bertambah</div>
                                                <?php endif; ?>
                                            <?php elseif ($is_returned): ?>
                                                <span style="color: #166e4a; font-weight: 600;">Tepat Waktu</span>
                                            <?php else: ?>
                                                <span style="color: #166e4a; font-weight: 600;">0 Hari</span>
                                                <div style="font-size: 10.5px; color: var(--text-muted);">Belum Terlambat</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($fine_amount > 0): ?>
                                                <strong style="color: #b91c1c; font-size: 13.5px;"><?php echo format_rupiah($fine_amount); ?></strong>
                                                <div style="font-size: 11px; color: var(--text-muted);">
                                                    <?php echo ($calc['fine_method'] === 'percentage_daily') ? $calc['fine_rate'] . '%/hari' : format_rupiah($calc['fine_rate']) . '/hari'; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $rs = $r['return_status'] ?: $r['status'];
                                                if ($rs === 'Terlambat') {
                                                    echo '<span class="badge-pill badge-danger">Terlambat</span>';
                                                } elseif ($rs === 'Selesai' || $rs === 'Dikembalikan') {
                                                    echo '<span class="badge-pill badge-success">Dikembalikan</span>';
                                                } elseif ($rs === 'Disewa') {
                                                    echo '<span class="badge-pill badge-warning">Sedang Disewa</span>';
                                                } else {
                                                    echo '<span class="badge-pill badge-neutral">' . htmlspecialchars($rs) . '</span>';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                if ($fine_status === 'Sudah Dibayar') {
                                                    echo '<span class="badge-pill badge-success">Sudah Dibayar</span>';
                                                    if (!empty($r['fine_paid_at'])) {
                                                        echo '<div style="font-size: 10.5px; color: var(--text-muted);">' . date('d M Y', strtotime($r['fine_paid_at'])) . '</div>';
                                                    }
                                                } elseif ($fine_status === 'Dibebaskan Admin') {
                                                    echo '<span class="badge-pill badge-neutral">Dibebaskan Admin</span>';
                                                } elseif ($fine_status === 'Belum Dibayar') {
                                                    echo '<span class="badge-pill badge-danger">Belum Dibayar</span>';
                                                } else {
                                                    echo '<span class="badge-pill badge-neutral">Tanpa Denda</span>';
                                                }
                                            ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php if ($late_days > 0 || $fine_amount > 0): ?>
                                                <button type="button" class="btn-fine-action" onclick='openFineModal(<?php echo $r['id']; ?>, "<?php echo addslashes($r['penyewa_nama'] ?? 'User'); ?>", <?php echo $fine_amount; ?>, "<?php echo $fine_status; ?>", "<?php echo addslashes($r['fine_payment_method'] ?? 'Tunai di Basecamp'); ?>", "<?php echo addslashes($r['fine_notes'] ?? ''); ?>")'>
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    <span>Ubah Status</span>
                                                </button>
                                            <?php else: ?>
                                                <span style="font-size: 11.5px; color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        Tidak ada data rental atau keterlambatan untuk filter ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Kelola Status Pembayaran Denda -->
    <div class="modal-overlay" id="fineModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="fineModalTitle">Status Pembayaran Denda</h3>
                <button type="button" class="modal-close-btn" onclick="closeFineModal()" aria-label="Tutup">&times;</button>
            </div>
            <form action="denda_aksi.php" method="POST">
                <input type="hidden" name="action" value="update_fine_status">
                <input type="hidden" name="rental_id" id="modalRentalId" value="0">

                <div class="modal-body">
                    <div style="background: #f8faf9; padding: 12px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 16px;">
                        <div style="font-size: 12px; color: var(--text-muted);">Penyewa: <strong id="modalPenyewa" style="color: var(--forest-950);">-</strong></div>
                        <div style="font-size: 14px; margin-top: 4px;">Total Denda: <strong id="modalFineAmount" style="color: #b91c1c; font-size: 16px;">Rp0</strong></div>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" for="statusDendaSelect">Status Pembayaran Denda</label>
                        <select name="status_denda" id="statusDendaSelect" class="form-select">
                            <option value="Belum Dibayar">Belum Dibayar</option>
                            <option value="Sudah Dibayar">Sudah Dibayar</option>
                            <option value="Dibebaskan Admin">Dibebaskan Admin (Kompensasi / Toleransi Khusus)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;" id="paymentMethodWrap">
                        <label class="form-label" for="paymentMethodInput">Metode Pembayaran</label>
                        <select name="payment_method" id="paymentMethodInput" class="form-select">
                            <option value="Tunai di Basecamp">Tunai di Kasir Basecamp</option>
                            <option value="Transfer BCA / QRIS">Transfer BCA / QRIS</option>
                            <option value="Potong Uang Jaminan / Deposit">Potong Uang Jaminan / Deposit</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="notesInput">Catatan Tambahan (Opsional)</label>
                        <textarea name="notes" id="notesInput" class="form-input" rows="2" placeholder="Contoh: Diterima tunai oleh admin jaga..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeFineModal()">Batal</button>
                    <button type="submit" class="btn-submit">Simpan Status Denda</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateMethodLabel(method) {
            const label = document.getElementById('fineRateLabel');
            if (method === 'percentage_daily') {
                label.innerText = 'Persentase Denda (%/hari)';
            } else {
                label.innerText = 'Nominal Denda (Rp/hari)';
            }
        }

        function openFineModal(rentalId, penyewa, fineAmount, status, method, notes) {
            document.getElementById('modalRentalId').value = rentalId;
            document.getElementById('modalPenyewa').innerText = penyewa;
            document.getElementById('modalFineAmount').innerText = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(fineAmount);
            document.getElementById('statusDendaSelect').value = status;
            document.getElementById('paymentMethodInput').value = method || 'Tunai di Basecamp';
            document.getElementById('notesInput').value = notes || '';
            document.getElementById('fineModal').classList.add('active');
        }

        function closeFineModal() {
            document.getElementById('fineModal').classList.remove('active');
        }

        window.addEventListener('click', function(e) {
            const modal = document.getElementById('fineModal');
            if (e.target === modal) {
                closeFineModal();
            }
        });
    </script>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>

</html>
