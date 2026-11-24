<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek autentikasi user
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("location:../login.php?pesan=" . urlencode("Silakan login untuk melihat riwayat pesanan toko Anda."));
    exit;
}

include '../config/config.php';
require_once __DIR__ . '/../config/midtrans.php';
/** @var mysqli $conn */

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id && isset($_SESSION['nama'])) {
    $nama_s = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $u_res = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama_s' LIMIT 1");
    if ($u_res && mysqli_num_rows($u_res) > 0) {
        $user_id = (int)mysqli_fetch_assoc($u_res)['id'];
        $_SESSION['user_id'] = $user_id;
    }
}

if (!$user_id) {
    header("location:../login.php?pesan=" . urlencode("Sesi login berakhir. Silakan login kembali."));
    exit;
}

$userName = $_SESSION['nama'] ?? 'User';

// Filter status
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$query_orders = "SELECT * FROM orders WHERE user_id='$user_id'";
if (!empty($status_filter)) {
    $safe_st = mysqli_real_escape_string($conn, $status_filter);
    $query_orders .= " AND status='$safe_st'";
}
$query_orders .= " ORDER BY id DESC";

$res_orders = mysqli_query($conn, $query_orders);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Toko Saya — DANATRAIL Outdoor Store</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Midtrans Snap JS -->
    <script type="text/javascript" src="<?php echo htmlspecialchars(MIDTRANS_SNAP_JS); ?>" data-client-key="<?php echo htmlspecialchars(MIDTRANS_CLIENT_KEY); ?>"></script>

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
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .page-header-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-header-box h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--forest-950);
        }

        .page-header-box p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 3px;
        }

        /* Nav Pills Filter */
        .status-filter-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-nav-link {
            padding: 7px 14px;
            border-radius: var(--radius-pill);
            background: #ffffff;
            color: var(--text-body);
            border: 1.5px solid var(--border-color);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 700;
            transition: all var(--transition-fast);
        }

        .filter-nav-link:hover {
            border-color: var(--forest-700);
            background: #f7faf8;
        }

        .filter-nav-link.active {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
        }

        /* Order Cards */
        .order-card {
            background: var(--surface-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-card);
            margin-bottom: 20px;
            overflow: hidden;
            transition: box-shadow var(--transition-fast);
        }

        .order-card:hover {
            box-shadow: 0 4px 16px rgba(7, 53, 34, 0.08);
        }

        .order-header-bar {
            padding: 14px 20px;
            background: #fafbfa;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .order-code-badge {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 14px;
            color: var(--forest-950);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-body-content {
            padding: 20px;
        }

        .order-items-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }

        .order-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px dashed var(--border-color);
        }

        .order-item-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .item-row-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .item-thumb-sm {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--border-color);
            background: #f1f5f3;
        }

        .order-footer-bar {
            padding: 14px 20px;
            background: #fafbfa;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        /* Status Pills */
        .status-pill {
            font-size: 11.5px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-pending {
            background: #fefce8;
            color: #854d0e;
            border: 1px solid #fef08a;
        }
        .status-verif {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .status-process {
            background: #f0fdfa;
            color: #0f766e;
            border: 1px solid #99f6e4;
        }
        .status-shipping {
            background: #faf5ff;
            color: #6b21a8;
            border: 1px solid #e9d5ff;
        }
        .status-done {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .status-cancel {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .btn-act-outline {
            padding: 6px 12px;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            background: #ffffff;
            color: var(--text-main);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-act-outline:hover {
            border-color: var(--forest-700);
            background: #f0fdf4;
            color: var(--forest-900);
        }

        .btn-act-pay {
            padding: 6px 14px;
            border: none;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-950) 100%);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .proof-sent-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: var(--radius-pill);
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            font-size: 11.5px;
            font-weight: 700;
        }

        .btn-link-proof {
            background: none;
            border: none;
            color: var(--forest-700);
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
        }

        .btn-link-proof:hover {
            color: var(--forest-950);
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(4, 31, 20, 0.65);
            backdrop-filter: blur(4px);
            z-index: 999;
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
        }

        .modal-header {
            background: var(--forest-950);
            color: #ffffff;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-body {
            padding: 20px;
        }

        /* Toast Feedback */
        .alert-toast {
            padding: 10px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .alert-toast.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-toast.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 960px) {
            body {
                flex-direction: column;
            }
            .mobile-top-bar {
                display: flex;
            }
            .main-wrapper {
                padding: 18px 16px 36px 16px;
            }
        }
    </style>
</head>

<body>

    <!-- Mobile Top Header -->
    <header class="mobile-top-bar">
        <div class="mobile-brand">
            <img src="../assets/logo.png" alt="DANATRAIL" onerror="this.src='https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=80&auto=format&fit=crop&q=60'">
            <span>DANATRAIL Store</span>
        </div>
        <button type="button" class="btn-toggle-menu" onclick="toggleMobileSidebar()" aria-label="Buka Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16m-7 6h7" />
            </svg>
            <span>Menu</span>
        </button>
    </header>

    <!-- User Navigation Sidebar -->
    <?php include 'menu.php'; ?>

    <main class="main-wrapper">
        <div class="page-header-box">
            <div>
                <h1>Pesanan Belanja Toko</h1>
                <p>Pantau status pengiriman, nomor resi kurir, dan riwayat faktur pembelian produk outdoor Anda.</p>
            </div>
            <a href="dashboard.php?tab=toko" class="btn-act-outline" style="padding: 8px 16px;">
                <span>+ Beli Produk Toko</span>
            </a>
        </div>

        <?php if (isset($_GET['sukses'])): ?>
            <div class="alert-toast success">
                <span>✓ <?php echo htmlspecialchars($_GET['sukses']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['pesan'])): ?>
            <div class="alert-toast error">
                <span><?php echo htmlspecialchars($_GET['pesan']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Status Filter Nav -->
        <div class="status-filter-nav">
            <a href="pesanan.php" class="filter-nav-link <?php echo empty($status_filter) ? 'active' : ''; ?>">Semua Status</a>
            <a href="pesanan.php?status=Menunggu Pembayaran" class="filter-nav-link <?php echo ($status_filter === 'Menunggu Pembayaran') ? 'active' : ''; ?>">Menunggu Bayar</a>
            <a href="pesanan.php?status=Diproses" class="filter-nav-link <?php echo ($status_filter === 'Diproses') ? 'active' : ''; ?>">Diproses</a>
            <a href="pesanan.php?status=Dikirim" class="filter-nav-link <?php echo ($status_filter === 'Dikirim') ? 'active' : ''; ?>">Dikirim (Resi)</a>
            <a href="pesanan.php?status=Selesai" class="filter-nav-link <?php echo ($status_filter === 'Selesai') ? 'active' : ''; ?>">Selesai</a>
            <a href="pesanan.php?status=Dibatalkan" class="filter-nav-link <?php echo ($status_filter === 'Dibatalkan') ? 'active' : ''; ?>">Dibatalkan</a>
        </div>

        <!-- Orders List -->
        <?php if ($res_orders && mysqli_num_rows($res_orders) > 0): ?>
            <?php while ($ord = mysqli_fetch_assoc($res_orders)): 
                $oid = (int)$ord['id'];
                // Ambil items untuk order ini
                $q_items = mysqli_query($conn, "SELECT oi.*, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = '$oid'");
                $st = $ord['status'];
                $pay_st = $ord['payment_status'];
                $pay_proof = trim((string)($ord['payment_proof'] ?? ''));
                $has_proof = ($pay_proof !== '' && file_exists('../uploads/payments/' . $pay_proof));
            ?>
                <div class="order-card">
                    <!-- Order Header -->
                    <div class="order-header-bar">
                        <div class="order-code-badge">
                            <span>Kode: <?php echo htmlspecialchars($ord['order_code']); ?></span>
                            <span style="font-size: 11.5px; font-weight: 500; color: var(--text-muted);">
                                • <?php echo date('d M Y, H:i', strtotime($ord['created_at'])); ?> WIB
                            </span>
                        </div>
                        <div>
                            <?php if ($st === 'Menunggu Pembayaran'): ?>
                                <span class="status-pill status-pending">Menunggu Pembayaran</span>
                            <?php elseif ($st === 'Menunggu Konfirmasi'): ?>
                                <span class="status-pill status-verif">Menunggu Konfirmasi</span>
                            <?php elseif ($st === 'Diproses'): ?>
                                <span class="status-pill status-process">Sedang Diproses</span>
                            <?php elseif ($st === 'Dikirim'): ?>
                                <span class="status-pill status-shipping">Dalam Pengiriman</span>
                            <?php elseif ($st === 'Selesai'): ?>
                                <span class="status-pill status-done">✓ Selesai</span>
                            <?php else: ?>
                                <span class="status-pill status-cancel"><?php echo htmlspecialchars($st); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Order Body -->
                    <div class="order-body-content">
                        <div class="order-items-grid">
                            <?php if ($q_items && mysqli_num_rows($q_items) > 0): ?>
                                <?php while ($item = mysqli_fetch_assoc($q_items)): ?>
                                    <div class="order-item-row">
                                        <div class="item-row-left">
                                            <?php if (!empty($item['image']) && file_exists('../uploads/products/' . $item['image'])): ?>
                                                <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="Foto" class="item-thumb-sm">
                                            <?php else: ?>
                                                <div class="item-thumb-sm" style="display: flex; align-items: center; justify-content: center;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="font-size: 13.5px; color: var(--text-main);"><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                                    <?php echo $item['quantity']; ?> unit × Rp <?php echo number_format($item['price'], 0, ',', '.'); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="text-align: right; font-weight: 700; font-size: 13.5px; color: var(--forest-950);">
                                            Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Shipping & Tracking Info -->
                        <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 14px; font-size: 12.5px; margin-top: 14px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px;">
                            <div>
                                <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Tujuan Pengiriman:</span>
                                <strong style="color: var(--text-main);"><?php echo htmlspecialchars($ord['recipient_name']); ?> (<?php echo htmlspecialchars($ord['recipient_phone']); ?>)</strong>
                                <div style="color: var(--text-body); margin-top: 2px; font-size: 12px;"><?php echo htmlspecialchars($ord['shipping_address']); ?></div>
                            </div>
                            <div>
                                <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Metode & Ekspedisi:</span>
                                <div><?php echo htmlspecialchars($ord['courier']); ?></div>
                                <?php if (!empty($ord['tracking_number'])): ?>
                                    <div style="margin-top: 4px; font-weight: 700; color: #6b21a8; font-size: 12.5px;">
                                        No. Resi: <code><?php echo htmlspecialchars($ord['tracking_number']); ?></code>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; display: block;">Pembayaran:</span>
                                <div><?php echo htmlspecialchars($ord['payment_method']); ?></div>
                                <div style="margin-top: 2px; font-weight: 700; font-size: 11.5px; color: <?php echo ($pay_st === 'Lunas') ? '#059669' : '#d97706'; ?>;">
                                    <?php echo ($pay_st === 'Lunas') ? '✓ Lunas' : htmlspecialchars($pay_st); ?>
                                </div>
                                <?php if ($has_proof): ?>
                                    <div style="margin-top: 6px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <span class="proof-sent-pill" style="padding: 2px 8px; font-size: 11px;">
                                            ✓ Bukti Terkirim
                                        </span>
                                        <button type="button" class="btn-link-proof" onclick="openProofPreviewModal('../uploads/payments/<?php echo htmlspecialchars($pay_proof, ENT_QUOTES); ?>', 'Bukti Bayar — <?php echo htmlspecialchars($ord['order_code'], ENT_QUOTES); ?>')">
                                            Lihat Bukti
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Order Footer -->
                    <div class="order-footer-bar">
                        <div>
                            <div style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 2px;">
                                <?php if ((float)($ord['discount_amount'] ?? 0) > 0): ?>
                                    Subtotal: <span style="text-decoration: line-through;">Rp <?php echo number_format($ord['subtotal_price'] ?? ($ord['total_price'] + $ord['discount_amount']), 0, ',', '.'); ?></span>
                                    <span style="color: #166e4a; font-weight: 700; margin-left: 6px;">
                                        Diskon: -Rp <?php echo number_format($ord['discount_amount'], 0, ',', '.'); ?>
                                        <?php if (!empty($ord['discount_code'])): ?>
                                            (<?php echo htmlspecialchars($ord['discount_code']); ?>)
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ((float)($ord['shipping_cost'] ?? 0) > 0): ?>
                                    <span style="color: var(--text-muted); margin-left: <?php echo ((float)($ord['discount_amount'] ?? 0) > 0) ? '8px' : '0'; ?>;">
                                        Ongkir: <strong>Rp <?php echo number_format((float)$ord['shipping_cost'], 0, ',', '.'); ?></strong>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span style="font-size: 11.5px; color: var(--text-muted);">Total Belanja:</span>
                            <strong style="font-family: 'Outfit', sans-serif; font-size: 17px; color: var(--forest-900); margin-left: 6px;">
                                Rp <?php echo number_format($ord['total_price'], 0, ',', '.'); ?>
                            </strong>
                        </div>

                        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                            <!-- Bayar Online Midtrans & Upload Bukti Manual jika belum lunas -->
                            <?php if ($pay_st !== 'Lunas' && $st !== 'Dibatalkan'): ?>
                                <button type="button" onclick="payOrderOnline(<?php echo $ord['id']; ?>, this)" class="btn-act-pay" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); box-shadow: 0 2px 8px rgba(5, 150, 105, 0.28);">
                                    <span>Bayar Online</span>
                                </button>
                                <?php if ($has_proof): ?>
                                    <span class="proof-sent-pill">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Bukti Bayar Sudah Dikirim</span>
                                    </span>
                                    <button type="button" onclick="openUploadModal(<?php echo $ord['id']; ?>, '<?php echo htmlspecialchars(addslashes($ord['order_code'])); ?>', '<?php echo number_format($ord['total_price'], 0, ',', '.'); ?>')" class="btn-act-outline">
                                        <span>Ganti Bukti</span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" onclick="openUploadModal(<?php echo $ord['id']; ?>, '<?php echo htmlspecialchars(addslashes($ord['order_code'])); ?>', '<?php echo number_format($ord['total_price'], 0, ',', '.'); ?>')" class="btn-act-outline">
                                        <span>Upload Bukti Manual</span>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Invoice / Faktur -->
                            <a href="../invoice.php?order_id=<?php echo $ord['id']; ?>" target="_blank" class="btn-act-outline">
                                <span>Cetak Faktur</span>
                            </a>

                            <!-- WhatsApp Admin -->
                            <?php 
                                $wa_pesan_msg = urlencode("Halo Admin DANATRAIL, saya ingin konfirmasi pesanan belanja saya dengan Kode: " . $ord['order_code'] . " atas nama " . $ord['recipient_name']);
                            ?>
                            <a href="https://wa.me/6288996016686?text=<?php echo $wa_pesan_msg; ?>" target="_blank" style="padding: 7px 12px; background: #25D366; color: #ffffff; border-radius: var(--radius-md); font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                <span>WhatsApp Admin</span>
                            </a>

                            <!-- Batalkan jika masih menunggu bayar -->
                            <?php if ($st === 'Menunggu Pembayaran'): ?>
                                <a href="beli_aksi.php?batal=<?php echo $ord['id']; ?>" onclick="return confirm('Batalkan pesanan belanja ini? Stok produk akan dikembalikan.');" style="color: #dc2626; font-size: 11.5px; font-weight: 700; text-decoration: none; padding: 6px 10px;">
                                    Batalkan
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="color: var(--text-muted); margin-bottom: 12px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 18px; color: var(--forest-950); margin-bottom: 4px;">Belum Ada Pesanan Toko</h3>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">Semua pembelian perlengkapan outdoor baru akan tercatat di sini.</p>
                <a href="dashboard.php?tab=toko" class="btn-act-pay" style="display: inline-flex; padding: 9px 18px;">
                    Kunjungi Toko Outdoor
                </a>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Upload Bukti Transfer Santai & Ramah Pengguna -->
    <div class="modal-overlay" id="uploadModal" role="dialog" aria-modal="true">
        <div class="modal-dialog" style="max-width: 540px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="flex-shrink: 0; background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 100%);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.12); display: flex; align-items: center; justify-content: center; color: #a7f3d0;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 style="font-size: 15.5px; font-weight: 800; color: #ffffff; margin: 0; letter-spacing: -0.2px;">Pusat Pembayaran Pesanan</h3>
                        <p style="font-size: 12px; color: #a7f3d0; margin: 2px 0 0 0;" id="modalOrderCode">Kode: ORD-XXXX</p>
                    </div>
                </div>
                <button type="button" onclick="closeUploadModal()" style="background: none; border: none; font-size: 22px; color: #ffffff; cursor: pointer; padding: 4px; line-height: 1;">&times;</button>
            </div>

            <div style="flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 20px;">
                <!-- Peaceful Reassurance Banner -->
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 10px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#16a34a" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div style="font-size: 12px; color: #166534; line-height: 1.45;">
                        <strong style="display: block; color: #14532d; font-size: 12.5px; margin-bottom: 2px;">Proses Pembayaran Mudah & Tenang</strong>
                        Pesanan Anda sudah tersimpan aman di sistem. Silakan pilih rekening tujuan di bawah ini atau scan QRIS, lalu unggah bukti transfer tanpa rasa khawatir.
                    </div>
                </div>

                <!-- Total Amount Card with 1-Click Copy -->
                <div style="background: #fafaf9; border: 1.5px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Total Yang Harus Dibayar:</span>
                        <div id="modalTotalNominal" style="font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 800; color: var(--forest-900); margin-top: 2px;">Rp 0</div>
                    </div>
                    <button type="button" onclick="copyModalNominal()" style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #ffffff; border: 1.5px solid var(--forest-700); color: var(--forest-900); border-radius: var(--radius-md); font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                        <span>📋 Salin Nominal</span>
                    </button>
                </div>

                <!-- Bank Selection Tabs -->
                <div style="margin-bottom: 16px;">
                    <div style="font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 8px;">Pilih Rekening Tujuan Transfer:</div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">
                        <button type="button" class="tab-bank-btn active" onclick="switchModalBank('bca', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--forest-800); background: var(--forest-800); color: #fff; font-size: 12px; font-weight: 700; cursor: pointer;">Bank BCA</button>
                        <button type="button" class="tab-bank-btn" onclick="switchModalBank('mandiri', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-body); font-size: 12px; font-weight: 700; cursor: pointer;">Mandiri</button>
                        <button type="button" class="tab-bank-btn" onclick="switchModalBank('bri', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-body); font-size: 12px; font-weight: 700; cursor: pointer;">BRI</button>
                        <button type="button" class="tab-bank-btn" onclick="switchModalBank('qris', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-body); font-size: 12px; font-weight: 700; cursor: pointer;">QRIS Instan</button>
                        <button type="button" class="tab-bank-btn" onclick="switchModalBank('kasir', this)" style="padding: 6px 12px; border-radius: 6px; border: 1.5px solid var(--border-color); background: #fff; color: var(--text-body); font-size: 12px; font-weight: 700; cursor: pointer;">Bayar di Kasir</button>
                    </div>

                    <!-- Tab Panels -->
                    <div id="bankPanel_bca" class="bank-panel" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: var(--radius-md); padding: 12px 14px;">
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

                    <div id="bankPanel_mandiri" class="bank-panel" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: var(--radius-md); padding: 12px 14px;">
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

                    <div id="bankPanel_bri" class="bank-panel" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: var(--radius-md); padding: 12px 14px;">
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

                    <div id="bankPanel_qris" class="bank-panel" style="display: none; background: #f0fdf4; border: 1px solid #a7f3d0; border-radius: var(--radius-md); padding: 12px 14px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 52px; height: 52px; background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-weight: 900; color: #166534; font-size: 14px;">
                                QRIS
                            </div>
                            <div>
                                <strong style="font-size: 13px; color: #14532d; display: block;">QRIS Resmi DANATRAIL INDONESIA</strong>
                                <div style="font-size: 12px; color: #166534; margin-top: 2px;">Mendukung BCA Mobile, Livin, BRImo, GoPay, OVO, Dana, ShopeePay. Hubungi WhatsApp Admin untuk kirim kode QRIS langsung ke HP Anda.</div>
                            </div>
                        </div>
                    </div>

                    <div id="bankPanel_kasir" class="bank-panel" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-md); padding: 12px 14px;">
                        <strong style="font-size: 13px; color: #1e40af; display: block;">Bayar Tunai di Kasir Basecamp</strong>
                        <div style="font-size: 12px; color: #1e3a8a; margin-top: 2px;">
                            Pilihan sangat santai! Anda tidak perlu transfer atau upload bukti sekarang. Cukup datang ke Basecamp DANATRAIL dan bayar langsung di kasir saat mengambil perlengkapan.
                        </div>
                    </div>
                </div>

                <!-- Form Upload -->
                <form action="beli_aksi.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_bukti_bayar" value="1">
                    <input type="hidden" name="order_id" id="modalOrderId" value="">

                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">
                            Unggah Foto / Screenshot Bukti Transfer
                        </label>
                        <div style="border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 16px; text-align: center; background: #fafdfb; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('modalPaymentProofInput').click()">
                            <input type="file" name="payment_proof" id="modalPaymentProofInput" accept="image/*,.pdf" required style="display: none;" onchange="previewModalProof(this)">
                            
                            <div id="modalProofPrompt">
                                <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color: var(--forest-700); margin: 0 auto 6px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <div style="font-size: 12.5px; font-weight: 700; color: var(--forest-950);">Klik untuk Memilih Bukti Pembayaran</div>
                                <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Format JPG, PNG, WEBP, atau PDF (Maksimal 5MB)</div>
                            </div>

                            <div id="modalProofSelected" style="display: none; align-items: center; justify-content: center; gap: 12px;">
                                <img id="modalProofThumb" src="" alt="Thumbnail Bukti" style="width: 52px; height: 52px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                <div style="text-align: left;">
                                    <div id="modalProofFileName" style="font-size: 12.5px; font-weight: 700; color: var(--forest-950); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Bukti Transfer</div>
                                    <div style="font-size: 11.5px; color: #16a34a; font-weight: 600;">✓ Foto siap dikirim</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Helpful Assistant Notice -->
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; padding: 10px 12px; background: #f8fafc; border-radius: 8px;">
                        <span style="font-size: 12px; color: var(--text-muted);">Butuh bantuan langsung?</span>
                        <a id="modalWaHelpLink" href="https://wa.me/6288996016686" target="_blank" style="font-size: 12px; font-weight: 700; color: #16a34a; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            💬 Hubungi Admin via WhatsApp
                        </a>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" onclick="closeUploadModal()" class="btn-act-outline" style="padding: 9px 16px;">Tutup</button>
                        <button type="submit" class="btn-act-pay" style="padding: 9px 20px; font-size: 13px;">Kirim Bukti Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Preview Bukti Pembayaran -->
    <div class="modal-overlay" id="proofPreviewModal" role="dialog" aria-modal="true" onclick="if(event.target === this) closeProofPreviewModal()">
        <div class="modal-dialog" style="max-width: 520px;">
            <div class="modal-header">
                <strong id="proofPreviewTitle" style="font-family: 'Outfit', sans-serif; font-size: 15px;">Bukti Pembayaran</strong>
                <button type="button" onclick="closeProofPreviewModal()" style="background: none; border: none; color: #ffffff; cursor: pointer; font-size: 20px; line-height: 1;">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center; background: #f8fafc;">
                <img id="proofPreviewImg" src="" alt="Bukti Pembayaran" style="display: none; max-width: 100%; max-height: 65vh; border-radius: 8px; border: 1px solid var(--border-color); margin: 0 auto;">
                <div id="proofPreviewPdf" style="display: none; padding: 24px 12px;">
                    <p style="font-size: 13px; color: var(--text-body); margin-bottom: 12px;">Bukti pembayaran berformat PDF.</p>
                    <a id="proofPreviewPdfLink" href="#" target="_blank" class="btn-act-pay" style="text-decoration: none; padding: 8px 16px;">Buka Dokumen PDF</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Popup -->
    <div id="danatrailToast" style="display: none; position: fixed; bottom: 24px; right: 24px; z-index: 9999; background: #064e3b; color: #ffffff; padding: 12px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 10px 25px rgba(0,0,0,0.2); align-items: center; gap: 8px;">
        <span id="toastIcon">✓</span>
        <span id="toastMsg">Teks berhasil disalin!</span>
    </div>

    <script>
        let currentModalTotalDigits = "0";

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('appSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar) sidebar.classList.toggle('mobile-open');
            if (backdrop) backdrop.classList.toggle('active');
        }

        function openUploadModal(orderId, orderCode, totalNominal) {
            document.getElementById('modalOrderId').value = orderId;
            document.getElementById('modalOrderCode').innerText = 'Kode: ' + orderCode;
            document.getElementById('modalTotalNominal').innerText = 'Rp ' + totalNominal;
            currentModalTotalDigits = totalNominal.replace(/[^0-9]/g, '');

            const waMsg = encodeURIComponent("Halo Admin DANATRAIL, saya ingin konfirmasi pembayaran untuk pesanan toko dengan kode: " + orderCode + " sebesar Rp " + totalNominal);
            document.getElementById('modalWaHelpLink').href = "https://wa.me/6288996016686?text=" + waMsg;

            // Reset image preview
            document.getElementById('modalProofPrompt').style.display = 'block';
            document.getElementById('modalProofSelected').style.display = 'none';
            document.getElementById('modalPaymentProofInput').value = '';

            document.getElementById('uploadModal').style.display = 'flex';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }

        function switchModalBank(bankKey, btn) {
            document.querySelectorAll('.tab-bank-btn').forEach(b => {
                b.style.borderColor = 'var(--border-color)';
                b.style.background = '#ffffff';
                b.style.color = 'var(--text-body)';
            });
            btn.style.borderColor = 'var(--forest-800)';
            btn.style.background = 'var(--forest-800)';
            btn.style.color = '#ffffff';

            document.querySelectorAll('.bank-panel').forEach(panel => {
                panel.style.display = 'none';
            });

            const target = document.getElementById('bankPanel_' + bankKey);
            if (target) target.style.display = 'block';
        }

        function copyModalNominal() {
            if (currentModalTotalDigits) {
                copyTextToClipboard(currentModalTotalDigits, 'Nominal Pembayaran (Rp ' + document.getElementById('modalTotalNominal').innerText.replace('Rp ', '') + ')');
            }
        }

        function previewModalProof(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('modalProofFileName').innerText = file.name;

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('modalProofThumb').src = e.target.result;
                        document.getElementById('modalProofPrompt').style.display = 'none';
                        document.getElementById('modalProofSelected').style.display = 'flex';
                    }
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('modalProofThumb').src = 'https://cdn-icons-png.flaticon.com/512/337/337946.png';
                    document.getElementById('modalProofPrompt').style.display = 'none';
                    document.getElementById('modalProofSelected').style.display = 'flex';
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

        function openProofPreviewModal(fileUrl, title) {
            const titleEl = document.getElementById('proofPreviewTitle');
            const imgEl = document.getElementById('proofPreviewImg');
            const pdfBox = document.getElementById('proofPreviewPdf');
            const pdfLink = document.getElementById('proofPreviewPdfLink');

            if (titleEl) titleEl.innerText = title || 'Bukti Pembayaran';

            if (fileUrl.toLowerCase().endsWith('.pdf')) {
                imgEl.style.display = 'none';
                imgEl.src = '';
                pdfLink.href = fileUrl;
                pdfBox.style.display = 'block';
            } else {
                pdfBox.style.display = 'none';
                imgEl.src = fileUrl;
                imgEl.style.display = 'block';
            }

            document.getElementById('proofPreviewModal').style.display = 'flex';
        }

        function closeProofPreviewModal() {
            document.getElementById('proofPreviewModal').style.display = 'none';
        }

        function payOrderOnline(orderId, btnEl) {
            const origHtml = btnEl ? btnEl.innerHTML : '';
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.innerHTML = '<span>Memuat Midtrans...</span>';
            }

            const formData = new FormData();
            formData.append('order_id', orderId);

            fetch('../payment/get_snap_token.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = origHtml;
                }
                if (data.success && data.token && !data.simulator) {
                    if (typeof window.snap !== 'undefined' && typeof window.snap.pay === 'function') {
                        window.snap.pay(data.token, {
                            onSuccess: function(result) {
                                const oid = encodeURIComponent(result.order_id || data.order_id || '');
                                const ptype = encodeURIComponent(result.payment_type || 'snap');
                                window.location.href = '../payment/finish_payment.php?type=order&id=' + orderId + '&order_id=' + oid + '&status=success&payment_type=' + ptype;
                            },
                            onPending: function(result) {
                                const oid = encodeURIComponent(result.order_id || data.order_id || '');
                                window.location.href = '../payment/finish_payment.php?type=order&id=' + orderId + '&order_id=' + oid + '&status=pending';
                            },
                            onError: function() {
                                showToast('Pembayaran gagal atau dibatalkan.');
                            },
                            onClose: function() {
                                showToast('Popup pembayaran ditutup sebelum selesai.');
                            }
                        });
                    } else if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        alert('Snap Midtrans belum siap. Silakan muat ulang halaman.');
                    }
                } else {
                    alert(data.error || 'Gagal memulai pembayaran online Midtrans.');
                }
            })
            .catch(() => {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = origHtml;
                }
                alert('Terjadi gangguan koneksi ke server pembayaran Midtrans.');
            });
        }

        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUploadModal();
                closeProofPreviewModal();
            }
        });
    </script>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>

</html>
