<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek akses admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=" . urlencode("Akses ditolak! Anda bukan Administrator."));
    exit;
}

include '../config/config.php';
/** @var mysqli $conn */

// Filter status & pencarian
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search        = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$query_orders = "SELECT o.*, u.email as user_email
                 FROM orders o
                 LEFT JOIN users u ON o.user_id = u.id
                 WHERE 1=1";

if (!empty($status_filter)) {
    $safe_st = mysqli_real_escape_string($conn, $status_filter);
    $query_orders .= " AND o.status = '$safe_st'";
}

if (!empty($search)) {
    $query_orders .= " AND (o.order_code LIKE '%$search%' OR o.recipient_name LIKE '%$search%' OR o.recipient_phone LIKE '%$search%' OR o.shipping_address LIKE '%$search%' OR o.tracking_number LIKE '%$search%')";
}

$query_orders .= " ORDER BY o.id DESC";
$res_orders = mysqli_query($conn, $query_orders);
$total_count = $res_orders ? mysqli_num_rows($res_orders) : 0;

// Stats
$q_stats = mysqli_query($conn, "SELECT 
    COUNT(*) as total_all,
    SUM(CASE WHEN status IN ('Diproses', 'Dikirim', 'Selesai') THEN total_price ELSE 0 END) as omset_toko,
    SUM(CASE WHEN status = 'Menunggu Konfirmasi' OR payment_status = 'Menunggu Verifikasi Admin' THEN 1 ELSE 0 END) as pending_verif,
    SUM(CASE WHEN status = 'Diproses' THEN 1 ELSE 0 END) as diproses_count,
    SUM(CASE WHEN status = 'Dikirim' THEN 1 ELSE 0 END) as dikirim_count
FROM orders");
$stats = $q_stats ? mysqli_fetch_assoc($q_stats) : [];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan Toko — Admin DANATRAIL</title>
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

        .mobile-brand img {
            width: 28px;
            height: 28px;
            border-radius: 6px;
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

        .main-wrapper {
            flex: 1;
            padding: 28px 32px 48px 32px;
            overflow-y: auto;
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header-box h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
        }

        .page-header-box p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 16px;
            box-shadow: var(--shadow-subtle);
        }

        .stat-label {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: var(--text-muted);
        }

        .stat-value {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: var(--forest-950);
            margin-top: 4px;
        }

        /* Table Card */
        .orders-table-card {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .table-scroll {
            overflow-x: auto;
            width: 100%;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .order-table th {
            background: #fafbfa;
            padding: 12px 16px;
            text-align: left;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            white-space: nowrap;
        }

        .order-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }

        .order-table tr:last-child td {
            border-bottom: none;
        }

        .order-table tr:hover {
            background: #fbfdfc;
        }

        .code-tag {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            color: var(--forest-900);
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 2px 7px;
            border-radius: 4px;
            display: inline-block;
        }

        /* Status Pills */
        .status-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        .status-pending { background: #fefce8; color: #854d0e; border: 1px solid #fef08a; }
        .status-verif   { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .status-process { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
        .status-shipping{ background: #faf5ff; color: #6b21a8; border: 1px solid #e9d5ff; }
        .status-done    { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .status-cancel  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .btn-sm-act {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            border: none;
        }
        .btn-green { background: #16a34a; color: #ffffff; }
        .btn-blue  { background: #2563eb; color: #ffffff; }
        .btn-purple{ background: #7c3aed; color: #ffffff; }
        .btn-amber { background: #d97706; color: #ffffff; }
        .btn-outline{ background: #ffffff; border: 1px solid var(--border-color); color: var(--text-body); }
        .btn-outline:hover { background: #f8fafc; }

        /* Filter Tabs */
        .filter-tabs-row {
            display: flex;
            gap: 6px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 6px 12px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            background: #ffffff;
            color: var(--text-body);
            border: 1px solid var(--border-color);
        }
        .filter-tab.active {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
        }

        /* Modal */
        .modal-overlay {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background: rgba(4, 31, 20, 0.72) !important;
            backdrop-filter: blur(5px) !important;
            -webkit-backdrop-filter: blur(5px) !important;
            z-index: 99999 !important;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-dialog {
            background: #ffffff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 45px rgba(4, 31, 20, 0.35);
            overflow: hidden;
            animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(6px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-header {
            background: var(--forest-950);
            color: #ffffff;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Bukti Transfer Thumbnail Button */
        .btn-proof-preview {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 9px;
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-fast);
            text-align: left;
            text-decoration: none;
        }
        .btn-proof-preview:hover {
            background: #dcfce7;
            border-color: #4ade80;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2);
            transform: translateY(-1px);
        }

        /* Alerts */
        .toast-box {
            padding: 10px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .toast-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .toast-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        @media (max-width: 860px) {
            body { flex-direction: column; }
            .mobile-top-bar { display: flex; }
            .main-wrapper { padding: 18px 14px 36px 14px; }
        }
    </style>
</head>

<body>

    <!-- Mobile Header -->
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

    <!-- Admin Navigation Sidebar -->
    <?php include 'menu.php'; ?>

    <main class="main-wrapper">
        <div class="page-header-box">
            <div>
                <h1>Data Pesanan Toko (Penjualan)</h1>
                <p>Kelola pesanan belanja produk outdoor, verifikasi transfer, input nomor resi kurir, dan pengiriman.</p>
            </div>
            <button type="button" onclick="window.print()" class="btn-sm-act btn-outline" style="padding: 8px 16px; font-size: 12.5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                <span>Cetak / Rekap Penjualan</span>
            </button>
        </div>

        <?php if (isset($_GET['sukses'])): ?>
            <div class="toast-box toast-success">
                <span>✓ <?php echo htmlspecialchars($_GET['sukses']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['pesan'])): ?>
            <div class="toast-box toast-error">
                <span><?php echo htmlspecialchars($_GET['pesan']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Omset Penjualan</div>
                <div class="stat-value" style="color: var(--forest-900);">
                    Rp <?php echo number_format($stats['omset_toko'] ?? 0, 0, ',', '.'); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Menunggu Verifikasi</div>
                <div class="stat-value" style="color: #d97706;">
                    <?php echo (int)($stats['pending_verif'] ?? 0); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Sedang Diproses</div>
                <div class="stat-value" style="color: #0f766e;">
                    <?php echo (int)($stats['diproses_count'] ?? 0); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Dalam Pengiriman</div>
                <div class="stat-value" style="color: #6b21a8;">
                    <?php echo (int)($stats['dikirim_count'] ?? 0); ?>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <div class="filter-tabs-row" style="margin-bottom: 0;">
                <a href="pesanan.php" class="filter-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">Semua (<?php echo (int)($stats['total_all'] ?? 0); ?>)</a>
                <a href="pesanan.php?status=Menunggu Pembayaran" class="filter-tab <?php echo ($status_filter === 'Menunggu Pembayaran') ? 'active' : ''; ?>">Menunggu Bayar</a>
                <a href="pesanan.php?status=Menunggu Konfirmasi" class="filter-tab <?php echo ($status_filter === 'Menunggu Konfirmasi') ? 'active' : ''; ?>">Menunggu Konfirmasi</a>
                <a href="pesanan.php?status=Diproses" class="filter-tab <?php echo ($status_filter === 'Diproses') ? 'active' : ''; ?>">Diproses</a>
                <a href="pesanan.php?status=Dikirim" class="filter-tab <?php echo ($status_filter === 'Dikirim') ? 'active' : ''; ?>">Dikirim</a>
                <a href="pesanan.php?status=Selesai" class="filter-tab <?php echo ($status_filter === 'Selesai') ? 'active' : ''; ?>">Selesai</a>
                <a href="pesanan.php?status=Dibatalkan" class="filter-tab <?php echo ($status_filter === 'Dibatalkan') ? 'active' : ''; ?>">Dibatalkan</a>
            </div>

            <form action="pesanan.php" method="GET" style="display: flex; gap: 6px;">
                <?php if (!empty($status_filter)): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Cari kode, nama, resi..." value="<?php echo htmlspecialchars($search); ?>" style="padding: 7px 12px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 12.5px; width: 220px;">
                <button type="submit" class="btn-sm-act btn-green" style="padding: 7px 12px;">Cari</button>
            </form>
        </div>

        <!-- Table Card -->
        <section class="orders-table-card">
            <div class="table-scroll">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Pembeli & Kontak</th>
                            <th>Barang Dipesan</th>
                            <th>Total Tagihan</th>
                            <th>Pembayaran</th>
                            <th>Ekspedisi & Status</th>
                            <th style="text-align: right; min-width: 170px;">Aksi & Kelola</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_orders && mysqli_num_rows($res_orders) > 0): ?>
                            <?php while ($ord = mysqli_fetch_assoc($res_orders)): 
                                $oid = (int)$ord['id'];
                                $q_items = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id='$oid'");
                                $pay_st = $ord['payment_status'];
                                $st = $ord['status'];
                                $pay_proof = $ord['payment_proof'];

                                $phone_clean = preg_replace('/[^0-9]/', '', $ord['recipient_phone'] ?? '');
                                if (!empty($phone_clean) && substr($phone_clean, 0, 1) === '0') {
                                    $phone_clean = '62' . substr($phone_clean, 1);
                                }
                                $wa_msg = urlencode("Halo Kak " . $ord['recipient_name'] . ", ini dari Basecamp DANATRAIL terkait pesanan toko Kode: " . $ord['order_code']);
                            ?>
                                <tr>
                                    <td>
                                        <span class="code-tag"><?php echo htmlspecialchars($ord['order_code']); ?></span>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;">
                                            <?php echo date('d/m/Y H:i', strtotime($ord['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--text-main); font-size: 13.5px;"><?php echo htmlspecialchars($ord['recipient_name']); ?></strong>
                                        <div style="font-size: 11.5px; color: var(--forest-800); font-weight: 700; margin-top: 1px;">
                                            Telp: <?php echo htmlspecialchars($ord['recipient_phone']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-muted); max-width: 200px; margin-top: 2px;">
                                            <?php echo htmlspecialchars($ord['shipping_address']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 3px;">
                                            <?php if ($q_items): ?>
                                                <?php while ($it = mysqli_fetch_assoc($q_items)): ?>
                                                    <div style="font-size: 12.5px;">
                                                        • <strong><?php echo htmlspecialchars($it['product_name']); ?></strong> 
                                                        <span style="color: var(--text-muted);">(<?php echo $it['quantity']; ?>x @ Rp <?php echo number_format($it['price'], 0, ',', '.'); ?>)</span>
                                                    </div>
                                                <?php endwhile; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($ord['notes'])): ?>
                                            <div style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-top: 4px;">
                                                Catatan: "<?php echo htmlspecialchars($ord['notes']); ?>"
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-family: 'Outfit', sans-serif; font-size: 15px; font-weight: 800; color: var(--forest-950);">
                                            Rp <?php echo number_format($ord['total_price'], 0, ',', '.'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 5px; align-items: flex-start;">
                                            <span style="font-size: 11.5px; font-weight: 600; color: var(--text-body);"><?php echo htmlspecialchars($ord['payment_method'] ?? 'Transfer Bank'); ?></span>
                                            
                                            <?php if ($pay_st === 'Lunas'): ?>
                                                <span class="status-pill status-done">✓ Lunas</span>
                                            <?php elseif ($pay_st === 'Menunggu Verifikasi Admin'): ?>
                                                <span class="status-pill status-verif">Menunggu Verifikasi</span>
                                            <?php else: ?>
                                                <span class="status-pill status-pending"><?php echo htmlspecialchars($pay_st); ?></span>
                                            <?php endif; ?>

                                            <!-- Lihat Bukti Bayar jika ada -->
                                            <?php if (!empty($pay_proof)): ?>
                                                <div style="margin-top: 4px;">
                                                    <button type="button" 
                                                            class="btn-proof-preview" 
                                                            onclick="openPhotoModal('../uploads/payments/<?php echo htmlspecialchars($pay_proof); ?>', 'Bukti Bayar: <?php echo htmlspecialchars(addslashes($ord['order_code'])); ?>', '<?php echo htmlspecialchars(addslashes($ord['recipient_name'])); ?>', <?php echo $ord['id']; ?>, '<?php echo $pay_st; ?>')"
                                                            title="Klik untuk melihat bukti transfer">
                                                        <img src="../uploads/payments/<?php echo htmlspecialchars($pay_proof); ?>" 
                                                             alt="Bukti Transfer" 
                                                             onerror="this.style.display='none';" 
                                                             style="width: 28px; height: 28px; object-fit: cover; border-radius: 4px; border: 1px solid #bbf7d0;">
                                                        <div style="text-align: left;">
                                                            <span style="display: block; font-weight: 700; color: var(--forest-800); font-size: 11px;">👁 Bukti Transfer</span>
                                                            <span style="display: block; font-size: 9.5px; color: var(--text-muted);">Klik perbesar</span>
                                                        </div>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 10.5px; color: var(--text-muted); font-style: italic; margin-top: 2px;">
                                                    (Belum upload bukti)
                                                </span>
                                            <?php endif; ?>

                                            <!-- Quick verify payment button -->
                                            <?php if ($pay_st !== 'Lunas'): ?>
                                                <div style="margin-top: 3px;">
                                                    <a href="pesanan_aksi.php?verifikasi_bayar=1&id=<?php echo $ord['id']; ?>&status_bayar=Lunas" onclick="return confirm('Tandai pembayaran pesanan <?php echo htmlspecialchars($ord['order_code']); ?> sebagai Lunas?');" class="btn-sm-act btn-green" style="font-size: 11px; padding: 4px 8px;">
                                                        ✓ Set Lunas
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                            <?php if ($st === 'Menunggu Pembayaran'): ?>
                                                <span class="status-pill status-pending">Menunggu Bayar</span>
                                            <?php elseif ($st === 'Menunggu Konfirmasi'): ?>
                                                <span class="status-pill status-verif">Menunggu Konfirmasi</span>
                                            <?php elseif ($st === 'Diproses'): ?>
                                                <span class="status-pill status-process">Diproses (Packing)</span>
                                            <?php elseif ($st === 'Dikirim'): ?>
                                                <span class="status-pill status-shipping">Dalam Pengiriman</span>
                                            <?php elseif ($st === 'Selesai'): ?>
                                                <span class="status-pill status-done">✓ Selesai</span>
                                            <?php else: ?>
                                                <span class="status-pill status-cancel"><?php echo htmlspecialchars($st); ?></span>
                                            <?php endif; ?>

                                            <div style="font-size: 11px; color: var(--text-muted);">
                                                <?php echo htmlspecialchars($ord['courier']); ?>
                                            </div>

                                            <?php if (!empty($ord['tracking_number'])): ?>
                                                <div style="font-size: 11.5px; font-weight: 700; color: #6b21a8;">
                                                    Resi: <code><?php echo htmlspecialchars($ord['tracking_number']); ?></code>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                                            <!-- Aksi Proses & Kirim -->
                                            <?php if ($st === 'Menunggu Konfirmasi' || ($st === 'Menunggu Pembayaran' && $pay_st === 'Lunas')): ?>
                                                <a href="pesanan_aksi.php?update_status=1&id=<?php echo $ord['id']; ?>&status=Diproses" class="btn-sm-act btn-green" onclick="return confirm('Proses pesanan ini?');">
                                                    Proses Pesanan
                                                </a>
                                            <?php elseif ($st === 'Diproses'): ?>
                                                <a href="pesanan_aksi.php?update_status=1&id=<?php echo $ord['id']; ?>&status=Selesai" class="btn-sm-act btn-green" onclick="return confirm('Selesaikan pesanan ini langsung tanpa input resi?');" style="background: #16a34a; color: #fff; font-size: 11px; font-weight: 700; text-decoration: none; border-radius: 6px; padding: 3px 8px;">
                                                    ✓ Selesaikan Langsung
                                                </a>
                                                <button type="button" onclick="openResiModal(<?php echo $ord['id']; ?>, '<?php echo htmlspecialchars(addslashes($ord['order_code'])); ?>', '<?php echo htmlspecialchars(addslashes($ord['recipient_name'])); ?>', '<?php echo htmlspecialchars(addslashes($ord['courier'])); ?>')" class="btn-sm-act btn-purple" style="font-size: 10.5px; opacity: 0.85;">
                                                    Kirim (Input Resi)
                                                </button>
                                            <?php elseif ($st === 'Dikirim'): ?>
                                                <a href="pesanan_aksi.php?update_status=1&id=<?php echo $ord['id']; ?>&status=Selesai" class="btn-sm-act btn-green" onclick="return confirm('Tandai pesanan telah sampai dan Selesai?');">
                                                    Tandai Selesai
                                                </a>
                                            <?php endif; ?>

                                            <!-- Tombol Cetak Faktur -->
                                            <a href="../invoice.php?order_id=<?php echo $ord['id']; ?>" target="_blank" class="btn-sm-act btn-outline">
                                                <span>Cetak Faktur</span>
                                            </a>

                                            <!-- Tombol WhatsApp Pembeli -->
                                            <?php if (!empty($phone_clean)): ?>
                                                <a href="https://wa.me/<?php echo $phone_clean; ?>?text=<?php echo $wa_msg; ?>" target="_blank" class="btn-sm-act" style="background: #25D366; color: #ffffff;">
                                                    <span>WA Pembeli</span>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Batalkan / Hapus -->
                                            <?php if ($st !== 'Selesai' && $st !== 'Dibatalkan'): ?>
                                                <a href="pesanan_aksi.php?batalkan_pesanan=1&id=<?php echo $ord['id']; ?>" onclick="return confirm('Batalkan pesanan ini dan kembalikan stok produk?');" style="font-size: 10.5px; color: #dc2626; text-decoration: none; margin-top: 2px;">
                                                    Batalkan Pesanan
                                                </a>
                                            <?php else: ?>
                                                <a href="pesanan_aksi.php?hapus=<?php echo $ord['id']; ?>" onclick="return confirm('Hapus permanen data pesanan ini?');" style="font-size: 10.5px; color: #6b7280; text-decoration: none; margin-top: 2px;">
                                                    Hapus Riwayat
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 48px 20px; color: var(--text-muted);">
                                    <p>Belum ada data transaksi penjualan toko.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Modal Input Resi Pengiriman -->
    <div class="modal-overlay" id="resiModal" role="dialog" aria-modal="true">
        <div class="modal-dialog">
            <div class="modal-header">
                <div>
                    <h3 style="font-size: 15px; font-weight: 800; color: #ffffff;">Kirim Pesanan & Input Resi</h3>
                    <p style="font-size: 11.5px; color: #a7f3d0;" id="resiModalOrderCode">Kode: ORD-XXXX</p>
                </div>
                <button type="button" onclick="closeResiModal()" style="background: none; border: none; font-size: 20px; color: #ffffff; cursor: pointer;">&times;</button>
            </div>

            <form action="pesanan_aksi.php" method="POST">
                <input type="hidden" name="kirim_pesanan" value="1">
                <input type="hidden" name="order_id" id="resiModalOrderId" value="">

                <div style="padding: 20px;">
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Penerima</label>
                        <input type="text" id="resiModalRecipient" readonly style="width: 100%; padding: 8px 12px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px;">
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Nama Ekspedisi / Kurir</label>
                        <input type="text" name="courier" id="resiModalCourier" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px;">
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 4px;">Nomor Resi Pengiriman *</label>
                        <input type="text" name="tracking_number" id="resiModalTracking" placeholder="Contoh: JNE88291039482 atau AMBIL-LOKET-01" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 13px;">
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" onclick="closeResiModal()" class="btn-sm-act btn-outline">Batal</button>
                        <button type="submit" class="btn-sm-act btn-purple" style="padding: 8px 16px;">Konfirmasi Pengiriman</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Preview Foto Bukti Transfer -->
    <div class="modal-overlay" id="photoPreviewModal" role="dialog" aria-modal="true" style="display: none;">
        <div class="modal-dialog" style="max-width: 580px;">
            <div class="modal-header">
                <div>
                    <h3 style="font-size: 15px; font-weight: 700; color: #ffffff;" id="photoModalTitle">Bukti Transfer Pembayaran</h3>
                    <p style="font-size: 11.5px; color: #a7f3d0; margin-top: 2px;" id="photoModalSubtitle">Pesanan Pelanggan</p>
                </div>
                <button type="button" onclick="closePhotoModal()" style="background: none; border: none; font-size: 22px; color: #ffffff; cursor: pointer; line-height: 1;" aria-label="Tutup">&times;</button>
            </div>
            
            <div style="padding: 16px; text-align: center; background: #071910; min-height: 250px; display: flex; align-items: center; justify-content: center;">
                <img id="photoModalImg" src="" alt="Bukti Transfer" style="max-height: 480px; max-width: 100%; object-fit: contain; border-radius: 6px; box-shadow: 0 4px 20px rgba(0,0,0,0.4);">
            </div>
            
            <div style="padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-top: 1px solid var(--border-color);">
                <a id="photoModalOpenTab" href="#" target="_blank" class="btn-sm-act btn-outline" style="font-size: 11.5px; text-decoration: none;">
                    🔍 Buka Ukuran Asli
                </a>
                
                <div style="display: flex; gap: 8px;">
                    <a id="photoModalVerifyBtn" href="#" class="btn-sm-act btn-green" style="display: none; font-size: 12px;" onclick="return confirm('Verifikasi bukti transfer ini dan tandai status pembayaran Lunas?');">
                        ✓ Verifikasi & Set Lunas
                    </a>
                    <button type="button" onclick="closePhotoModal()" class="btn-sm-act btn-outline" style="font-size: 12px;">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAdminSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('adminSidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('mobile-open');
            if (backdrop) backdrop.classList.toggle('active');
        }

        function openResiModal(orderId, orderCode, recipient, courier) {
            document.getElementById('resiModalOrderId').value = orderId;
            document.getElementById('resiModalOrderCode').innerText = 'Kode: ' + orderCode;
            document.getElementById('resiModalRecipient').value = recipient;
            document.getElementById('resiModalCourier').value = courier;
            document.getElementById('resiModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeResiModal() {
            document.getElementById('resiModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openPhotoModal(imgSrc, title, buyerName, orderId, payStatus) {
            document.getElementById('photoModalImg').src = imgSrc;
            document.getElementById('photoModalTitle').innerText = title || 'Bukti Pembayaran';
            document.getElementById('photoModalSubtitle').innerText = 'Pembeli: ' + (buyerName || '-');
            document.getElementById('photoModalOpenTab').href = imgSrc;
            
            const verifyBtn = document.getElementById('photoModalVerifyBtn');
            if (verifyBtn) {
                if (payStatus !== 'Lunas' && orderId) {
                    verifyBtn.href = 'pesanan_aksi.php?verifikasi_bayar=1&id=' + orderId + '&status_bayar=Lunas';
                    verifyBtn.style.display = 'inline-flex';
                } else {
                    verifyBtn.style.display = 'none';
                }
            }
            document.getElementById('photoPreviewModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closePhotoModal() {
            document.getElementById('photoPreviewModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close Modals on Backdrop Click & ESC
        window.addEventListener('click', function(event) {
            const resiM = document.getElementById('resiModal');
            const photoM = document.getElementById('photoPreviewModal');
            if (event.target === resiM) closeResiModal();
            if (event.target === photoM) closePhotoModal();
        });

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeResiModal();
                closePhotoModal();
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
