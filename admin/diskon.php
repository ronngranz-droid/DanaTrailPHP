<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek akses admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=" . urlencode("Akses ditolak! Anda bukan Administrator."));
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
global $conn;

$sukses = $_GET['sukses'] ?? '';
$pesan  = $_GET['pesan'] ?? '';

// ============================================================================
// 1. PROSES TAMBAH / EDIT DISKON
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_diskon'])) {
    $discount_id    = (int)($_POST['discount_id'] ?? 0);
    $name           = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $code           = !empty($_POST['code']) ? mysqli_real_escape_string($conn, strtoupper(trim($_POST['code']))) : null;
    $discount_type  = in_array($_POST['discount_type'] ?? '', ['percentage', 'fixed']) ? $_POST['discount_type'] : 'percentage';
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $min_tx         = (float)($_POST['minimum_transaction'] ?? 0);
    $max_disc       = (float)($_POST['maximum_discount'] ?? 0);
    $start_date     = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d 00:00:00');
    $end_date       = !empty($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d 23:59:59', strtotime('+30 days'));
    $usage_limit    = (int)($_POST['usage_limit'] ?? 0);
    $usage_per_user = max(1, (int)($_POST['usage_per_user'] ?? 1));
    $applicable     = in_array($_POST['applicable_type'] ?? '', ['rental', 'sale', 'both']) ? $_POST['applicable_type'] : 'both';
    $is_automatic   = isset($_POST['is_automatic']) ? 1 : 0;
    $min_days       = (int)($_POST['min_rental_days'] ?? 0);
    $is_active      = isset($_POST['is_active']) ? 1 : 0;

    $code_sql = $code !== null ? "'$code'" : "NULL";

    if (empty($name) || $discount_value <= 0) {
        header("location:diskon.php?pesan=" . urlencode("Nama promo dan nilai diskon wajib diisi dengan benar!"));
        exit;
    }

    if ($discount_id > 0) {
        // Update
        $sql = "UPDATE discounts SET 
                    name = '$name',
                    code = $code_sql,
                    discount_type = '$discount_type',
                    discount_value = '$discount_value',
                    minimum_transaction = '$min_tx',
                    maximum_discount = '$max_disc',
                    start_date = '$start_date',
                    end_date = '$end_date',
                    usage_limit = '$usage_limit',
                    usage_per_user = '$usage_per_user',
                    applicable_type = '$applicable',
                    is_automatic = '$is_automatic',
                    min_rental_days = '$min_days',
                    is_active = '$is_active'
                WHERE id = '$discount_id'";
        $msg = "Voucher / Promo diskon berhasil diperbarui!";
    } else {
        // Insert
        $sql = "INSERT INTO discounts (name, code, discount_type, discount_value, minimum_transaction, maximum_discount, start_date, end_date, usage_limit, usage_per_user, target_type, applicable_type, is_automatic, min_rental_days, is_active)
                VALUES ('$name', $code_sql, '$discount_type', '$discount_value', '$min_tx', '$max_disc', '$start_date', '$end_date', '$usage_limit', '$usage_per_user', 'all', '$applicable', '$is_automatic', '$min_days', '$is_active')";
        $msg = "Voucher / Promo diskon baru berhasil ditambahkan!";
    }

    if (mysqli_query($conn, $sql)) {
        header("location:diskon.php?sukses=" . urlencode($msg));
    } else {
        header("location:diskon.php?pesan=" . urlencode("Gagal menyimpan voucher: " . mysqli_error($conn)));
    }
    exit;
}

// ============================================================================
// 2. TOGGLE STATUS AKTIF
// ============================================================================
if (isset($_GET['toggle'])) {
    $did = (int)$_GET['toggle'];
    $act = (int)$_GET['status'] ? 1 : 0;
    mysqli_query($conn, "UPDATE discounts SET is_active = '$act' WHERE id = '$did'");
    header("location:diskon.php?sukses=" . urlencode("Status voucher berhasil diubah!"));
    exit;
}

// ============================================================================
// 3. HAPUS VOUCHER
// ============================================================================
if (isset($_GET['hapus'])) {
    $did = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM discount_usages WHERE discount_id = '$did'");
    mysqli_query($conn, "DELETE FROM discounts WHERE id = '$did'");
    header("location:diskon.php?sukses=" . urlencode("Voucher diskon berhasil dihapus dari sistem."));
    exit;
}

// ============================================================================
// 4. STATISTIK VOUCHER
// ============================================================================
$q_stats = mysqli_query($conn, "SELECT 
    COUNT(CASE WHEN is_active = 1 AND end_date >= NOW() THEN 1 END) as total_active,
    COUNT(CASE WHEN is_automatic = 1 AND is_active = 1 THEN 1 END) as total_auto,
    COUNT(CASE WHEN code IS NOT NULL AND code != '' THEN 1 END) as total_coded
FROM discounts");
$stats = $q_stats ? mysqli_fetch_assoc($q_stats) : [];

$q_usage = mysqli_query($conn, "SELECT COUNT(*) as total_used, COALESCE(SUM(discount_amount), 0) as total_saved FROM discount_usages");
$usage_stats = $q_usage ? mysqli_fetch_assoc($q_usage) : ['total_used' => 0, 'total_saved' => 0];

// ============================================================================
// 5. QUERY DAFTAR DISKON
// ============================================================================
$filter = $_GET['filter'] ?? 'semua';
$search = trim($_GET['search'] ?? '');

$where_parts = ["1=1"];
if ($filter === 'aktif') {
    $where_parts[] = "d.is_active = 1 AND d.end_date >= NOW()";
} elseif ($filter === 'otomatis') {
    $where_parts[] = "d.is_automatic = 1";
} elseif ($filter === 'voucher') {
    $where_parts[] = "d.code IS NOT NULL AND d.code != ''";
} elseif ($filter === 'nonaktif') {
    $where_parts[] = "(d.is_active = 0 OR d.end_date < NOW())";
}

if (!empty($search)) {
    $s_esc = mysqli_real_escape_string($conn, $search);
    $where_parts[] = "(d.name LIKE '%$s_esc%' OR d.code LIKE '%$s_esc%')";
}

$where_sql = implode(' AND ', $where_parts);
$query_discounts = "SELECT d.*, 
    COUNT(u.id) as times_used,
    COALESCE(SUM(u.discount_amount), 0) as total_savings
FROM discounts d
LEFT JOIN discount_usages u ON d.id = u.discount_id
WHERE $where_sql
GROUP BY d.id
ORDER BY d.is_active DESC, d.id DESC";

$res_discounts = mysqli_query($conn, $query_discounts);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Voucher & Promo Diskon — DANATRAIL Admin</title>
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

            --shadow-card: 0 2px 10px rgba(7, 53, 34, 0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
        }

        .main-wrapper {
            flex-grow: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .content-area {
            padding: 32px 36px 60px;
            max-width: 1300px;
            width: 100%;
        }

        /* HEADER */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title-group h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--forest-950);
            letter-spacing: -0.5px;
        }

        .page-title-group p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .btn-add-promo {
            background: linear-gradient(135deg, var(--forest-800), var(--forest-700));
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            padding: 10px 20px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(14, 86, 56, 0.25);
            transition: all 0.2s ease;
        }

        .btn-add-promo:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(14, 86, 56, 0.35);
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-card);
        }

        .stat-card span {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .stat-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--forest-950);
            margin-top: 4px;
        }

        /* TOOLBAR */
        .toolbar {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tab-btn {
            text-decoration: none;
            padding: 7px 14px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            background: #f1f5f9;
            transition: all 0.2s ease;
        }

        .tab-btn.active, .tab-btn:hover {
            background: var(--forest-700);
            color: #fff;
        }

        .search-form {
            display: flex;
            gap: 8px;
        }

        .search-input {
            padding: 8px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 13px;
            outline: none;
            width: 220px;
        }

        /* TABLE */
        .table-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-body);
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #fbfcfb;
        }

        .promo-name {
            font-weight: 700;
            color: var(--forest-950);
            font-size: 14px;
        }

        .voucher-code-chip {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-weight: 800;
            font-size: 12px;
            background: #f3e8ff;
            color: #6b21a8;
            border: 1px solid #e9d5ff;
        }

        .badge-auto {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
        }

        .status-active {
            background: #dcfce7;
            color: #15803d;
        }

        .status-inactive {
            background: #f1f5f9;
            color: #64748b;
        }

        .status-expired {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-act {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid var(--border-color);
            background: #fff;
            color: var(--text-main);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
        }

        .btn-act:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .btn-act-danger:hover {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #dc2626;
        }

        /* MODAL */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-box {
            background: #fff;
            border-radius: var(--radius-xl);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--forest-950);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-muted);
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 13px;
            outline: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .alert-bar {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 13.5px;
            font-weight: 600;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        @media (max-width: 900px) {
            .main-wrapper {
                margin-left: 0;
            }
            .content-area {
                padding: 20px 16px;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Admin -->
    <?php include __DIR__ . '/menu.php'; ?>

    <div class="main-wrapper">
        <div class="content-area">
            <!-- Header -->
            <div class="page-header">
                <div class="page-title-group">
                    <h1>Manajemen Voucher & Promo Diskon</h1>
                    <p>Atur kupon voucher, promo otomatis hari pendakian, dan potongan belanja perlengkapan.</p>
                </div>
                <button type="button" class="btn-add-promo" onclick="openAddModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Buat Voucher Baru</span>
                </button>
            </div>

            <!-- Alerts -->
            <?php if (!empty($sukses)): ?>
                <div class="alert-bar alert-success">✓ <?php echo htmlspecialchars($sukses); ?></div>
            <?php endif; ?>
            <?php if (!empty($pesan)): ?>
                <div class="alert-bar alert-error">✕ <?php echo htmlspecialchars($pesan); ?></div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span>Voucher Aktif</span>
                    <h3><?php echo (int)($stats['total_active'] ?? 0); ?> Unit</h3>
                </div>
                <div class="stat-card">
                    <span>Promo Otomatis</span>
                    <h3><?php echo (int)($stats['total_auto'] ?? 0); ?> Promo</h3>
                </div>
                <div class="stat-card">
                    <span>Total Digunakan</span>
                    <h3><?php echo (int)($usage_stats['total_used'] ?? 0); ?> Kali</h3>
                </div>
                <div class="stat-card">
                    <span>Hemat Pelanggan</span>
                    <h3 style="color: #059669;">Rp <?php echo number_format((float)($usage_stats['total_saved'] ?? 0), 0, ',', '.'); ?></h3>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="toolbar">
                <div class="filter-tabs">
                    <a href="diskon.php?filter=semua" class="tab-btn <?php echo ($filter === 'semua') ? 'active' : ''; ?>">Semua</a>
                    <a href="diskon.php?filter=aktif" class="tab-btn <?php echo ($filter === 'aktif') ? 'active' : ''; ?>">Aktif</a>
                    <a href="diskon.php?filter=voucher" class="tab-btn <?php echo ($filter === 'voucher') ? 'active' : ''; ?>">Kode Voucher</a>
                    <a href="diskon.php?filter=otomatis" class="tab-btn <?php echo ($filter === 'otomatis') ? 'active' : ''; ?>">Otomatis</a>
                    <a href="diskon.php?filter=nonaktif" class="tab-btn <?php echo ($filter === 'nonaktif') ? 'active' : ''; ?>">Kedaluwarsa</a>
                </div>

                <form method="GET" action="diskon.php" class="search-form">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="text" name="search" class="search-input" placeholder="Cari nama atau kode..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-act">Cari</button>
                </form>
            </div>

            <!-- Table -->
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th>Nama & Kode Promo</th>
                            <th>Tipe & Nilai Diskon</th>
                            <th>Syarat Transaksi</th>
                            <th>Berlaku Untuk</th>
                            <th>Masa Berlaku</th>
                            <th>Penggunaan</th>
                            <th>Status</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_discounts && mysqli_num_rows($res_discounts) > 0): ?>
                            <?php while ($d = mysqli_fetch_assoc($res_discounts)): ?>
                                <?php
                                $is_expired = strtotime($d['end_date']) < time();
                                if ($is_expired) {
                                    $st_badge = '<span class="status-pill status-expired">Kedaluwarsa</span>';
                                } elseif ($d['is_active']) {
                                    $st_badge = '<span class="status-pill status-active">Aktif</span>';
                                } else {
                                    $st_badge = '<span class="status-pill status-inactive">Nonaktif</span>';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="promo-name"><?php echo htmlspecialchars($d['name']); ?></div>
                                        <div style="margin-top: 4px; display: flex; gap: 6px; align-items: center;">
                                            <?php if (!empty($d['code'])): ?>
                                                <span class="voucher-code-chip"><?php echo htmlspecialchars($d['code']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($d['is_automatic']): ?>
                                                <span class="badge-auto">Otomatis</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--forest-900); font-size: 14px;">
                                            <?php echo ($d['discount_type'] === 'percentage') ? ((float)$d['discount_value'] . '%') : ('Rp ' . number_format($d['discount_value'], 0, ',', '.')); ?>
                                        </strong>
                                        <?php if ((float)$d['maximum_discount'] > 0): ?>
                                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                                Maks. Rp <?php echo number_format($d['maximum_discount'], 0, ',', '.'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>Min. Rp <?php echo number_format($d['minimum_transaction'], 0, ',', '.'); ?></div>
                                        <?php if ((int)$d['min_rental_days'] > 0): ?>
                                            <div style="font-size: 11px; color: var(--text-muted);">Min. Sewa <?php echo (int)$d['min_rental_days']; ?> Hari</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($d['applicable_type'] === 'rental') echo 'Sewa Alat';
                                        elseif ($d['applicable_type'] === 'sale') echo 'Toko (Beli)';
                                        else echo 'Sewa & Beli';
                                        ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px;">
                                            <?php echo date('d M Y', strtotime($d['start_date'])); ?> s/d<br>
                                            <strong><?php echo date('d M Y', strtotime($d['end_date'])); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo (int)$d['times_used']; ?>x</strong> dipakai
                                        <?php if ((int)$d['usage_limit'] > 0): ?>
                                            <span style="font-size: 11px; color: var(--text-muted);">/ limit <?php echo (int)$d['usage_limit']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $st_badge; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                            <!-- Toggle Aktif/Nonaktif -->
                                            <a href="diskon.php?toggle=<?php echo $d['id']; ?>&status=<?php echo $d['is_active'] ? 0 : 1; ?>" class="btn-act" title="Ubah status">
                                                <?php echo $d['is_active'] ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                            </a>
                                            <!-- Hapus -->
                                            <a href="diskon.php?hapus=<?php echo $d['id']; ?>" class="btn-act btn-act-danger" onclick="return confirm('Hapus voucher <?php echo htmlspecialchars(addslashes($d['name'])); ?>?')" title="Hapus">
                                                Hapus
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    Belum ada data voucher / diskon promo yang sesuai filter.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH PROMO -->
    <div class="modal-overlay" id="addPromoModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Tambah Voucher / Promo Baru</h3>
                <button type="button" class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" action="diskon.php" class="modal-body">
                <input type="hidden" name="discount_id" id="modal_discount_id" value="0">

                <div class="form-group">
                    <label>Nama Promo / Diskon *</label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: Diskon Ekspedisi Merbabu 20%" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Kode Kupon (Opsional)</label>
                        <input type="text" name="code" class="form-control" placeholder="Contoh: DANATRAIL20">
                    </div>
                    <div class="form-group">
                        <label>Berlaku Untuk</label>
                        <select name="applicable_type" class="form-control">
                            <option value="both" selected>Sewa Alat & Beli Toko</option>
                            <option value="rental">Khusus Sewa Alat</option>
                            <option value="sale">Khusus Pembelian Toko</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipe Diskon *</label>
                        <select name="discount_type" class="form-control">
                            <option value="percentage" selected>Persentase (%)</option>
                            <option value="fixed">Nominal Tetap (Rp)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nilai Diskon *</label>
                        <input type="number" step="0.01" name="discount_value" class="form-control" placeholder="Contoh: 15 (jika %) atau 25000" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Minimal Transaksi (Rp)</label>
                        <input type="number" name="minimum_transaction" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Maksimal Potongan Diskon (Rp)</label>
                        <input type="number" name="maximum_discount" class="form-control" value="0" placeholder="0 = Tanpa batas">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Tanggal Berakhir</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Batas Kuota Pemakaian (0 = Tanpa Kuota)</label>
                        <input type="number" name="usage_limit" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Batas Kuota Per Pengguna</label>
                        <input type="number" name="usage_per_user" class="form-control" value="1">
                    </div>
                </div>

                <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" name="is_automatic" value="1">
                        <span>Aktifkan sebagai <strong>Promo Otomatis</strong> (langsung terpotong tanpa memasukkan kode)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>Langsung aktifkan voucher ini</span>
                    </label>
                </div>

                <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-act" onclick="closeAddModal()">Batal</button>
                    <button type="submit" name="simpan_diskon" class="btn-add-promo">Simpan Voucher</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addPromoModal').style.display = 'flex';
        }
        function closeAddModal() {
            document.getElementById('addPromoModal').style.display = 'none';
        }
    </script>
</body>
</html>
