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
require_once __DIR__ . '/../config/midtrans.php';
require_once __DIR__ . '/../config/discount_fine_helper.php';
/** @var mysqli $conn */

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

// Ambil ID Rental
$rental_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($rental_id <= 0) {
    header("location:riwayat.php?pesan=" . urlencode("Pesanan sewa tidak ditemukan!"));
    exit;
}

// Query data rental
$q_rental = mysqli_query($conn, "
    SELECT r.*, 
           p.name AS product_name, 
           p.category AS product_category, 
           p.image AS product_image,
           u.name AS user_name,
           u.email AS user_email,
           u.phone AS user_phone
    FROM rentals r
    LEFT JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.id = '$rental_id' AND r.user_id = '$user_id'
    LIMIT 1
");

if (!$q_rental || mysqli_num_rows($q_rental) === 0) {
    header("location:riwayat.php?pesan=" . urlencode("Pesanan sewa tidak ditemukan atau bukan milik akun Anda!"));
    exit;
}

$rental = mysqli_fetch_assoc($q_rental);
$pay_status  = $rental['payment_status'] ?? 'Menunggu Pembayaran';
$pay_method  = $rental['payment_method'] ?? 'Transfer Bank BCA';
$pay_proof   = $rental['payment_proof'] ?? '';
$is_basecamp = (stripos($pay_method, 'Basecamp') !== false || stripos($pay_method, 'Cash') !== false || stripos($pay_method, 'COD') !== false);
$has_proof   = (!empty($pay_proof) && file_exists('../uploads/payments/' . $pay_proof));

// Hitung total denda jika ada
$fine_calc = calculate_rental_fine(
    $rental['rent_date'] ?? $rental['rental_date'],
    $rental['return_date'],
    $rental['actual_return_date'] ?? null,
    (float)$rental['total_price'],
    $rental['return_status'] ?? 'Sedang Disewa'
);
$late_days = (($rental['recorded_late_days'] ?? null) !== null) ? (int)$rental['recorded_late_days'] : $fine_calc['late_days'];
$fine_amt  = (($rental['recorded_fine_amount'] ?? null) !== null) ? (float)$rental['recorded_fine_amount'] : $fine_calc['fine_amount'];
$total_payable = (float)$rental['total_price'] + ($late_days > 0 ? $fine_amt : 0);

$wa_msg = urlencode("Halo Admin DANATRAIL, saya ingin konfirmasi pembayaran sewa " . ($rental['product_name'] ?? 'Alat') . " dengan Kode Booking: " . $rental['rental_code'] . " sebesar Rp " . number_format($total_payable, 0, ',', '.'));
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Pembayaran Sewa — DANATRAIL</title>
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

            --radius-xl: 16px;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-pill: 9999px;

            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
            --shadow-card: 0 4px 16px rgba(7, 53, 34, 0.06);
            --transition-fast: 0.15s ease;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            display: flex;
            height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
            color: var(--text-main);
            letter-spacing: -0.015em;
        }

        /* Sidebar */
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
        }

        .main-wrapper {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 24px 32px 100px 32px;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }

        .back-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
            transition: color var(--transition-fast);
        }

        .back-nav-link:hover {
            color: var(--forest-800);
        }

        .pay-grid-layout {
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            gap: 24px;
            align-items: start;
        }

        .pay-card {
            background: var(--surface-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow-card);
        }

        .pay-amount-banner {
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 100%);
            color: #ffffff;
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 18px rgba(4, 31, 20, 0.25);
        }

        .pay-amount-val {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: var(--gold-500);
            line-height: 1.1;
            margin-top: 4px;
        }

        .btn-copy-amount {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff;
            padding: 7px 14px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-copy-amount:hover {
            background: rgba(255, 255, 255, 0.22);
        }

        /* Tabs Metode Pembayaran */
        .pay-method-tabs {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            margin-bottom: 16px;
            padding-bottom: 4px;
        }

        .tab-btn {
            padding: 8px 14px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border-color);
            background: #ffffff;
            color: var(--text-body);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: all var(--transition-fast);
        }

        .tab-btn.active {
            background: var(--forest-900);
            color: #ffffff;
            border-color: var(--forest-900);
        }

        .bank-info-box {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 20px;
        }

        .bank-acc-number {
            font-family: monospace;
            font-size: 20px;
            font-weight: 800;
            color: var(--forest-950);
            letter-spacing: 1px;
            margin: 6px 0 2px 0;
            display: block;
        }

        .qris-code-box {
            background: #ffffff;
            border: 1.5px solid var(--emerald-border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .qris-img-frame {
            max-width: 240px;
            margin: 0 auto 12px auto;
            background: #ffffff;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .dropzone-upload-box {
            border: 2px dashed #94a3b8;
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            background: #fafdfb;
            cursor: pointer;
            transition: border-color var(--transition-fast);
            margin-bottom: 16px;
        }

        .dropzone-upload-box:hover {
            border-color: var(--forest-700);
            background: #f0fdf4;
        }

        .btn-submit-pay {
            width: 100%;
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-900) 100%);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(7, 53, 34, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--transition-fast);
        }

        .btn-submit-pay:hover {
            background: var(--forest-950);
            transform: translateY(-1px);
        }

        @media (max-width: 860px) {
            body {
                flex-direction: column;
                height: auto;
            }
            .sidebar {
                display: none;
            }
            .main-wrapper {
                padding: 16px;
                height: auto;
            }
            .pay-grid-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar Menu Component -->
    <?php include 'menu.php'; ?>

    <main class="main-wrapper">
        <a href="riwayat.php" class="back-nav-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>Kembali ke Riwayat Sewa</span>
        </a>

        <!-- Toast Feedback -->
        <?php if (isset($_GET['sukses'])): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; font-weight: 700; font-size: 13.5px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                <span><?php echo htmlspecialchars($_GET['sukses']); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($pay_status === 'Lunas'): ?>
            <!-- Status Lunas Full Card -->
            <div class="pay-card" style="text-align: center; padding: 36px 20px; margin-bottom: 24px;">
                <div style="width: 56px; height: 56px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px auto; color: #059669; border: 2px solid #a7f3d0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
                <h2 style="font-size: 22px; color: var(--forest-950); margin-bottom: 6px;">Pembayaran Sewa Ini Sudah Lunas!</h2>
                <p style="font-size: 13.5px; color: var(--text-muted); max-width: 500px; margin: 0 auto 20px auto;">
                    Pesanan sewa <strong>#<?php echo htmlspecialchars($rental['rental_code']); ?></strong> telah lunas terverifikasi. Silakan bawa bukti faktur saat pengambilan perlengkapan di basecamp.
                </p>
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <a href="../invoice.php?id=<?php echo $rental['id']; ?>" target="_blank" style="padding: 10px 20px; background: var(--forest-900); color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 13px;">
                        Cetak Faktur Sewa
                    </a>
                    <a href="riwayat.php" style="padding: 10px 20px; background: #fff; color: var(--forest-900); border: 1.5px solid var(--border-color); text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 13px;">
                        Lihat Daftar Riwayat
                    </a>
                </div>
            </div>
        <?php else: ?>

            <!-- Total Amount Header Banner -->
            <div class="pay-amount-banner">
                <div>
                    <span style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #a7f3d0;">Total Tagihan Pembayaran:</span>
                    <div class="pay-amount-val">Rp <?php echo number_format($total_payable, 0, ',', '.'); ?></div>
                    <div style="font-size: 12px; color: #d1fae5; margin-top: 2px;">
                        Kode Booking: <strong>#<?php echo htmlspecialchars($rental['rental_code']); ?></strong>
                    </div>
                </div>
                <button type="button" class="btn-copy-amount" onclick="copyText('<?php echo (int)$total_payable; ?>', 'Nominal Tagihan')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                    <span>Salin Nominal</span>
                </button>
            </div>

            <div class="pay-grid-layout">
                <!-- Kolom Kiri: Rincian Pesanan Sewa -->
                <div class="pay-card">
                    <h3 style="font-size: 16px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">
                        Rincian Pesanan Sewa
                    </h3>

                    <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 16px;">
                        <?php if (!empty($rental['product_image']) && file_exists('../uploads/products/' . $rental['product_image'])): ?>
                            <img src="../uploads/products/<?php echo htmlspecialchars($rental['product_image']); ?>" alt="Foto" style="width: 54px; height: 54px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border-color);">
                        <?php else: ?>
                            <img src="../assets/logo.png" alt="Logo" style="width: 54px; height: 54px; border-radius: 8px; object-fit: contain; border: 1px solid var(--border-color); background: #fff;">
                        <?php endif; ?>
                        <div>
                            <strong style="font-size: 14px; color: var(--forest-950); display: block;"><?php echo htmlspecialchars($rental['product_name'] ?? 'Alat Outdoor'); ?></strong>
                            <span style="font-size: 11.5px; color: var(--text-muted);"><?php echo htmlspecialchars($rental['product_category'] ?? 'Outdoor Gear'); ?> &bull; <?php echo $rental['quantity']; ?> Unit</span>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12.5px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Jadwal Sewa:</span>
                            <strong><?php echo date('d M Y', strtotime($rental['rental_date'])); ?> &rarr; <?php echo date('d M Y', strtotime($rental['return_date'])); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Durasi Sewa:</span>
                            <strong><?php echo $rental['duration_days']; ?> Hari</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Jaminan Identitas:</span>
                            <strong><?php echo htmlspecialchars($rental['guarantee_type'] ?? 'KTP Asli'); ?> (Titip Fisik)</strong>
                        </div>
                        <?php if ((float)($rental['discount_amount'] ?? 0) > 0): ?>
                            <div style="display: flex; justify-content: space-between; color: #166e4a;">
                                <span>Potongan Voucher:</span>
                                <strong>-Rp <?php echo number_format($rental['discount_amount'], 0, ',', '.'); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Bantuan WhatsApp CS -->
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px; font-size: 12px; color: #065f46;">
                        <strong>Perlu konfirmasi cepat?</strong>
                        <p style="margin: 2px 0 8px 0; font-size: 11.5px;">Kirim pesan langsung ke admin Basecamp DANATRAIL untuk proses verifikasi prioritas.</p>
                        <a href="https://wa.me/6288996016686?text=<?php echo $wa_msg; ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #25D366; color: #fff; font-weight: 700; border-radius: 6px; text-decoration: none; font-size: 12px;">
                            Chat Admin WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Kolom Kanan: Pilihan Pembayaran & Form Upload Bukti -->
                <div class="pay-card">
                    <h3 style="font-size: 16px; margin-bottom: 12px;">
                        Pilih Metode Pembayaran
                    </h3>

                    <!-- Pilihan Tab Metode -->
                    <div class="pay-method-tabs">
                        <button type="button" class="tab-btn <?php echo (stripos($pay_method, 'BCA') !== false || (!$is_basecamp && stripos($pay_method, 'QRIS') === false && stripos($pay_method, 'Mandiri') === false && stripos($pay_method, 'BRI') === false)) ? 'active' : ''; ?>" onclick="switchMethod('bca', this)">
                            Bank BCA
                        </button>
                        <button type="button" class="tab-btn <?php echo (stripos($pay_method, 'QRIS') !== false) ? 'active' : ''; ?>" onclick="switchMethod('qris', this)">
                            QRIS Instan
                        </button>
                        <button type="button" class="tab-btn <?php echo (stripos($pay_method, 'Mandiri') !== false) ? 'active' : ''; ?>" onclick="switchMethod('mandiri', this)">
                            Mandiri
                        </button>
                        <button type="button" class="tab-btn <?php echo (stripos($pay_method, 'BRI') !== false) ? 'active' : ''; ?>" onclick="switchMethod('bri', this)">
                            BRI
                        </button>
                        <button type="button" class="tab-btn <?php echo $is_basecamp ? 'active' : ''; ?>" onclick="switchMethod('basecamp', this)">
                            Bayar di Basecamp
                        </button>
                    </div>

                    <!-- Panel BCA -->
                    <div id="panel_bca" class="method-panel" style="<?php echo (stripos($pay_method, 'BCA') !== false || (!$is_basecamp && stripos($pay_method, 'QRIS') === false && stripos($pay_method, 'Mandiri') === false && stripos($pay_method, 'BRI') === false)) ? '' : 'display:none;'; ?>">
                        <div class="bank-info-box">
                            <span style="font-size: 12px; font-weight: 700; color: #0284c7; text-transform: uppercase;">Bank Central Asia (BCA)</span>
                            <span class="bank-acc-number">7310-0987-6543</span>
                            <div style="font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                                <span>a/n PT DANATRAIL INDONESIA</span>
                                <button type="button" onclick="copyText('731009876543', 'No. Rekening BCA')" style="padding: 4px 10px; background: #0284c7; color: #fff; border: none; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                    Salin
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel QRIS -->
                    <div id="panel_qris" class="method-panel" style="<?php echo (stripos($pay_method, 'QRIS') !== false) ? '' : 'display:none;'; ?>">
                        <div class="qris-code-box">
                            <div style="font-size: 12px; font-weight: 800; color: #166534; text-transform: uppercase; margin-bottom: 8px;">
                                QRIS Standar Pembayaran Nasional
                            </div>
                            <div class="qris-img-frame">
                                <!-- Clean SVG QR Representation for Instan Scan -->
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=https%3A%2F%2Fdanatrail.id%2Fpay%3Fcode%3D<?php echo urlencode($rental['rental_code']); ?>%26amount%3D<?php echo (int)$total_payable; ?>" alt="QRIS DANATRAIL" style="width: 100%; height: auto; display: block; margin: 0 auto;">
                            </div>
                            <span style="font-size: 12px; color: var(--text-muted); display: block;">
                                Scan dengan BCA, Mandiri, BRImo, GoPay, OVO, ShopeePay, atau DANA
                            </span>
                        </div>
                    </div>

                    <!-- Panel Mandiri -->
                    <div id="panel_mandiri" class="method-panel" style="<?php echo (stripos($pay_method, 'Mandiri') !== false) ? '' : 'display:none;'; ?>">
                        <div class="bank-info-box">
                            <span style="font-size: 12px; font-weight: 700; color: #d97706; text-transform: uppercase;">Bank Mandiri</span>
                            <span class="bank-acc-number">137-00-1928374-1</span>
                            <div style="font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                                <span>a/n PT DANATRAIL INDONESIA</span>
                                <button type="button" onclick="copyText('1370019283741', 'No. Rekening Mandiri')" style="padding: 4px 10px; background: #d97706; color: #fff; border: none; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                    Salin
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel BRI -->
                    <div id="panel_bri" class="method-panel" style="<?php echo (stripos($pay_method, 'BRI') !== false) ? '' : 'display:none;'; ?>">
                        <div class="bank-info-box">
                            <span style="font-size: 12px; font-weight: 700; color: #0284c7; text-transform: uppercase;">Bank Rakyat Indonesia (BRI)</span>
                            <span class="bank-acc-number">0123-01-098765-50-8</span>
                            <div style="font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                                <span>a/n PT DANATRAIL INDONESIA</span>
                                <button type="button" onclick="copyText('012301098765508', 'No. Rekening BRI')" style="padding: 4px 10px; background: #0284c7; color: #fff; border: none; border-radius: 4px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                    Salin
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Basecamp -->
                    <div id="panel_basecamp" class="method-panel" style="<?php echo $is_basecamp ? '' : 'display:none;'; ?>">
                        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border: 1.5px solid #a7f3d0; border-radius: var(--radius-md); padding: 20px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: #059669; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 800;">
                                    ✓
                                </div>
                                <div>
                                    <strong style="color: #064e3b; font-size: 14.5px; display: block;">Bayar Langsung di Kasir Basecamp</strong>
                                    <span style="font-size: 11.5px; color: #047857;">Tanpa Perlu Transfer Bank & Tanpa Perlu Upload Bukti</span>
                                </div>
                            </div>

                            <p style="font-size: 12.5px; color: #065f46; line-height: 1.55; margin-bottom: 14px;">
                                Pesanan sewa Anda sudah tercatat di sistem. Anda tidak perlu mentransfer dana sekarang. Cukup datang langsung ke kasir Basecamp DANATRAIL saat tanggal sewa tiba.
                            </p>

                            <div style="background: #ffffff; border: 1px solid #d1fae5; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                                <div style="font-size: 11.5px; font-weight: 700; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                                    Petunjuk Pengambilan & Pembayaran:
                                </div>
                                <ol style="margin: 0; padding-left: 18px; font-size: 12px; color: #1e293b; line-height: 1.6;">
                                    <li>Kunjungi <strong>Basecamp DANATRAIL Sidoarjo</strong> di <strong>Jl. Danatrail</strong> pada tanggal jadwal sewa.</li>
                                    <li>Tunjukkan <strong>Kode Booking: #<?php echo htmlspecialchars($rental['rental_code']); ?></strong> kepada petugas kasir.</li>
                                    <li>Serahkan dokumen jaminan fisik: <strong><?php echo htmlspecialchars($rental['guarantee_type'] ?? 'KTP Asli'); ?></strong>.</li>
                                    <li>Lakukan pembayaran sebesar <strong>Rp <?php echo number_format($total_payable, 0, ',', '.'); ?></strong> secara Tunai (Cash) atau QRIS/Debit di kasir saat serah terima alat.</li>
                                    <li>Cek kondisi fisik alat bersama petugas dan terima perlengkapan outdoor Anda.</li>
                                </ol>
                            </div>

                            <?php if ($has_proof): ?>
                                <!-- Bantuan Pembatalan Bukti Jika Sebelumnya Pernah Terunggah -->
                                <div style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 12px; color: #92400e;">
                                    <strong>Catatan Lampiran Bukti:</strong>
                                    <p style="margin: 4px 0 8px 0; font-size: 11.5px;">Pesanan ini memiliki lampiran bukti pembayaran. Jika ingin membatalkan bukti transfer dan tetap membayar penuh di kasir Basecamp, klik tombol di bawah ini:</p>
                                    <form action="sewa_aksi.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="batal_bukti_bayar" value="1">
                                        <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                                        <button type="submit" onclick="return confirm('Apakah Anda yakin ingin membatalkan bukti transfer dan membayar langsung di kasir Basecamp?');" style="padding: 6px 12px; background: #b45309; color: #ffffff; border: none; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                            Hapus Bukti Transfer & Tetap Bayar di Kasir
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="riwayat.php" style="padding: 10px 18px; background: var(--forest-900); color: #fff; text-decoration: none; border-radius: 6px; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                    <span>Lihat di Riwayat Sewa</span>
                                </a>
                                <a href="https://wa.me/6288996016686?text=<?php echo $wa_msg; ?>" target="_blank" style="padding: 10px 18px; background: #25D366; color: #fff; text-decoration: none; border-radius: 6px; font-size: 12.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                                    <span>Konfirmasi ke Kasir WhatsApp</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Form Upload Bukti Transfer (Khusus Transfer Bank / QRIS) -->
                    <div id="transferUploadSection" style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 8px; <?php echo $is_basecamp ? 'display: none;' : ''; ?>">
                        <h4 style="font-size: 13.5px; margin-bottom: 6px; color: var(--text-main);">
                            Unggah Foto / Screenshot Bukti Transfer
                        </h4>
                        <p style="font-size: 11.5px; color: var(--text-muted); margin-bottom: 12px;">
                            Setelah transfer bank / QRIS berhasil, lampirkan struk pembayaran agar pesananmu segera diverifikasi admin.
                        </p>

                        <?php if ($has_proof): ?>
                            <!-- Status Bukti Sudah Diunggah -->
                            <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="../uploads/payments/<?php echo htmlspecialchars($pay_proof); ?>" alt="Bukti" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid #10b981;">
                                    <div>
                                        <strong style="font-size: 12.5px; color: #065f46; display: block;">Bukti Pembayaran Terkirim</strong>
                                        <span style="font-size: 11px; color: #047857;">Status: Menunggu Verifikasi Admin</span>
                                    </div>
                                </div>
                                <a href="../uploads/payments/<?php echo htmlspecialchars($pay_proof); ?>" target="_blank" style="font-size: 12px; font-weight: 700; color: #047857; text-decoration: underline;">
                                    Lihat File
                                </a>
                            </div>
                        <?php endif; ?>

                        <form action="sewa_aksi.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="upload_bukti_bayar" value="1">
                            <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                            <input type="hidden" name="redirect_to" value="bayar">

                            <div class="dropzone-upload-box" onclick="document.getElementById('proofFileInput').click()">
                                <input type="file" name="payment_proof" id="proofFileInput" accept="image/*,.pdf" required style="display: none;" onchange="previewProof(this)">
                                
                                <div id="promptView">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" style="color: var(--forest-700); margin: 0 auto 6px auto;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--forest-950);">
                                        <?php echo $has_proof ? 'Klik jika ingin mengganti bukti transfer' : 'Pilih Foto Struk / Screenshot Bukti Bayar'; ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Format JPG, PNG, WEBP, atau PDF (Maksimal 5MB)</div>
                                </div>

                                <div id="selectedView" style="display: none; align-items: center; justify-content: center; gap: 12px;">
                                    <img id="selectedThumb" src="" alt="Thumbnail" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                    <div style="text-align: left;">
                                        <div id="selectedName" style="font-size: 12.5px; font-weight: 700; color: var(--forest-950); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Struk Pembayaran</div>
                                        <div style="font-size: 11px; color: #16a34a; font-weight: 600;">✓ Foto siap diunggah</div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit-pay">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span><?php echo $has_proof ? 'Kirim Pembaruan Bukti' : 'Kirim Bukti Pembayaran'; ?></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </main>

    <!-- Toast Notification Popup -->
    <div id="copyToast" style="display: none; position: fixed; bottom: 24px; right: 24px; z-index: 9999; background: #064e3b; color: #ffffff; padding: 12px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; box-shadow: 0 10px 25px rgba(0,0,0,0.2); align-items: center; gap: 8px;">
        <span>✓</span>
        <span id="copyToastTxt">Teks berhasil disalin!</span>
    </div>

    <script>
        function switchMethod(methodKey, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            document.querySelectorAll('.method-panel').forEach(p => p.style.display = 'none');
            const target = document.getElementById('panel_' + methodKey);
            if (target) target.style.display = 'block';

            const uploadSection = document.getElementById('transferUploadSection');
            if (uploadSection) {
                if (methodKey === 'basecamp') {
                    uploadSection.style.display = 'none';
                } else {
                    uploadSection.style.display = 'block';
                }
            }
        }

        function previewProof(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('selectedName').innerText = file.name;

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('selectedThumb').src = e.target.result;
                        document.getElementById('promptView').style.display = 'none';
                        document.getElementById('selectedView').style.display = 'flex';
                    }
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('selectedThumb').src = 'https://cdn-icons-png.flaticon.com/512/337/337946.png';
                    document.getElementById('promptView').style.display = 'none';
                    document.getElementById('selectedView').style.display = 'flex';
                }
            }
        }

        function copyText(text, label) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast(label + ' berhasil disalin!');
                });
            } else {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                textArea.style.position = "fixed";
                textArea.style.left = "-999999px";
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    showToast(label + ' berhasil disalin!');
                } catch (e) {}
                document.body.removeChild(textArea);
            }
        }

        function showToast(msg) {
            const toast = document.getElementById('copyToast');
            const txt = document.getElementById('copyToastTxt');
            if (!toast || !txt) return;
            txt.innerText = msg;
            toast.style.display = 'flex';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    </script>
</body>

</html>
