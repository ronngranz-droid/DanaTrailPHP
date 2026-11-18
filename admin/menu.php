<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['nama'] ?? 'Administrator';

if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/../config/config.php';
}
global $conn;

// Inisialisasi counter notifikasi / badge menu
$pending_count = 0;
$pending_orders_count = 0;
$overdue_count = 0;
$low_stock_count = 0;
$total_products_count = 0;

if (isset($conn) && $conn) {
    // Rental pending & telat kembali (overdue)
    $q_rentals = mysqli_query($conn, "
        SELECT 
            SUM(CASE WHEN status = 'Menunggu Konfirmasi' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'Disewa' AND return_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
        FROM rentals
    ");
    if ($q_rentals && ($row_rentals = mysqli_fetch_assoc($q_rentals))) {
        $pending_count = (int)($row_rentals['pending'] ?? 0);
        $overdue_count = (int)($row_rentals['overdue'] ?? 0);
    }

    // Pesanan toko baru
    $q_pend_ord = mysqli_query($conn, "
        SELECT COUNT(*) AS c 
        FROM orders 
        WHERE status = 'Menunggu Konfirmasi' 
           OR (status = 'Menunggu Pembayaran' AND payment_status = 'Menunggu Verifikasi Admin')
    ");
    if ($q_pend_ord) {
        $pending_orders_count = (int)(mysqli_fetch_assoc($q_pend_ord)['c'] ?? 0);
    }

    // Total perlengkapan
    $q_total_prod = mysqli_query($conn, "SELECT COUNT(*) AS c FROM products");
    if ($q_total_prod) {
        $total_products_count = (int)(mysqli_fetch_assoc($q_total_prod)['c'] ?? 0);
    }

    // Stok menipis / kritis (<= 2)
    $q_low_stock = mysqli_query($conn, "
        SELECT COUNT(*) AS c 
        FROM products 
        WHERE (product_type IN ('rental', 'both') AND stock <= 2) 
           OR (product_type IN ('sale', 'both') AND stock_sale <= 2)
    ");
    if ($q_low_stock) {
        $low_stock_count = (int)(mysqli_fetch_assoc($q_low_stock)['c'] ?? 0);
    }
}
?>

<aside class="sidebar" id="adminSidebar">
    <!-- Brand / Logo -->
    <div class="sidebar-header">
        <div class="sidebar-brand-wrapper">
            <div class="sidebar-logo">
                <img src="../assets/logo.png" alt="DANATRAIL" onerror="this.src='https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=80&auto=format&fit=crop&q=60'">
            </div>
            <div class="sidebar-brand-text">
                <span class="brand-title">DANATRAIL</span>
                <span class="brand-tagline">ADMINISTRATOR</span>
            </div>
        </div>
        <button type="button" class="sidebar-mobile-close" onclick="toggleAdminSidebar()" aria-label="Tutup Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Info Admin -->
    <a href="profile.php" class="user-profile-badge <?php echo ($current_page === 'profile.php') ? 'active-profile' : ''; ?>" title="Profil Admin">
        <div class="user-avatar-wrap">
            <div class="user-avatar-initial">
                <?php echo htmlspecialchars(strtoupper(substr($adminName, 0, 2))); ?>
            </div>
            <span class="status-indicator-dot" title="Online"></span>
        </div>
        <div class="user-details">
            <span class="user-display-name" title="<?php echo htmlspecialchars($adminName); ?>">
                <?php echo htmlspecialchars($adminName); ?>
            </span>
            <span class="user-role-chip">
                <span class="online-text-dot"></span> Administrator Basecamp
            </span>
        </div>
    </a>

    <!-- Menu Navigasi -->
    <nav class="sidebar-nav">
        <div class="nav-group-label">MENU UTAMA</div>

        <!-- Dashboard -->
        <a href="dashboard.php" class="nav-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <div class="nav-item-left">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                </div>
                <span class="nav-label">Dashboard</span>
            </div>
        </a>

        <!-- Perlengkapan -->
        <a href="perlengkapan.php" class="nav-item <?php echo ($current_page === 'perlengkapan.php') ? 'active' : ''; ?>">
            <div class="nav-item-left">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <span class="nav-label">Perlengkapan</span>
            </div>
            <div class="nav-item-right">
                <span class="nav-total-pill"><?php echo $total_products_count; ?></span>
            </div>
        </a>

        <!-- Manajemen Stok -->
        <a href="stok.php" class="nav-item <?php echo ($current_page === 'stok.php') ? 'active' : ''; ?>">
            <div class="nav-item-left">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <span class="nav-label">Manajemen Stok</span>
            </div>
            <?php if ($low_stock_count > 0): ?>
                <span class="nav-pending-badge badge-warning" title="<?php echo $low_stock_count; ?> stok menipis"><?php echo $low_stock_count; ?></span>
            <?php endif; ?>
        </a>

        <!-- Data Penyewaan -->
        <a href="transaksi.php" class="nav-item <?php echo ($current_page === 'transaksi.php') ? 'active' : ''; ?>">
            <div class="nav-item-left">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <span class="nav-label">Data Penyewaan</span>
            </div>
            <?php if ($pending_count > 0): ?>
                <span class="nav-pending-badge badge-gold" title="<?php echo $pending_count; ?> menunggu konfirmasi"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>

        <!-- Pesanan Toko -->
        <a href="pesanan.php" class="nav-item <?php echo ($current_page === 'pesanan.php') ? 'active' : ''; ?>">
            <div class="nav-item-left">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <span class="nav-label">Pesanan Toko</span>
            </div>
            <?php if ($pending_orders_count > 0): ?>
                <span class="nav-pending-badge badge-blue" title="<?php echo $pending_orders_count; ?> pesanan baru"><?php echo $pending_orders_count; ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-group-label" style="margin-top: 14px;">DENDA & KETERLAMBATAN</div>

        <!-- Pengaturan Denda -->
        <a href="denda.php" class="nav-item <?php echo ($current_page === 'denda.php') ? 'active' : ''; ?>">
            <div class="nav-item-left">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="nav-label">Pengaturan Denda</span>
            </div>
            <?php if ($overdue_count > 0): ?>
                <span class="nav-pending-badge badge-danger" title="<?php echo $overdue_count; ?> sewa terlambat"><?php echo $overdue_count; ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <!-- Logout -->
    <div class="sidebar-footer">
        <a href="../auth.php?logout=1" class="btn-sidebar-logout" onclick="return confirm('Apakah Anda yakin ingin logout?');">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>Keluar (Logout)</span>
        </a>
    </div>
</aside>

<!-- Backdrop mobile -->
<div class="sidebar-backdrop" id="adminSidebarBackdrop" onclick="toggleAdminSidebar()"></div>

<style>
    .sidebar {
        width: 260px !important;
        min-width: 260px !important;
        max-width: 260px !important;
        background: linear-gradient(180deg, #041f14 0%, #073522 100%) !important;
        color: #ffffff !important;
        display: flex !important;
        flex-direction: column !important;
        padding: 20px 14px !important;
        position: sticky !important;
        top: 0 !important;
        height: 100vh !important;
        border-right: 1px solid rgba(255, 255, 255, 0.08) !important;
        flex-shrink: 0 !important;
        z-index: 90 !important;
        box-sizing: border-box !important;
    }

    .sidebar-header {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding-bottom: 14px !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    }

    .sidebar-brand-wrapper {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
    }

    .sidebar-logo {
        width: 38px !important;
        min-width: 38px !important;
        max-width: 38px !important;
        height: 38px !important;
        max-height: 38px !important;
        border-radius: 9px !important;
        background: #ffffff !important;
        padding: 2px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border: 1.5px solid #f5b800 !important;
        overflow: hidden !important;
        flex-shrink: 0 !important;
    }

    .sidebar-logo img {
        width: 100% !important;
        height: 100% !important;
        object-fit: contain !important;
        display: block !important;
    }

    .sidebar-brand-text {
        display: flex !important;
        flex-direction: column !important;
    }

    .brand-title {
        font-family: 'Outfit', sans-serif !important;
        font-size: 16px !important;
        font-weight: 800 !important;
        letter-spacing: 0.8px !important;
        color: #ffffff !important;
        line-height: 1.1 !important;
    }

    .brand-tagline {
        font-size: 9px !important;
        font-weight: 800 !important;
        letter-spacing: 0.8px !important;
        text-transform: uppercase !important;
        color: #f5b800 !important;
        margin-top: 2px !important;
    }

    .sidebar-mobile-close {
        display: none !important;
        background: rgba(255, 255, 255, 0.08) !important;
        border: none !important;
        color: #ffffff !important;
        padding: 6px !important;
        border-radius: 6px !important;
        cursor: pointer !important;
    }

    .user-profile-badge {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        background: rgba(255, 255, 255, 0.04) !important;
        border: 1px solid rgba(255, 255, 255, 0.07) !important;
        padding: 10px 12px !important;
        border-radius: 10px !important;
        margin: 14px 0 12px 0 !important;
        text-decoration: none !important;
        transition: background 0.15s ease, border-color 0.15s ease !important;
        cursor: pointer !important;
    }

    .user-profile-badge:hover,
    .user-profile-badge.active-profile {
        background: rgba(255, 255, 255, 0.08) !important;
        border-color: rgba(245, 184, 0, 0.35) !important;
    }

    .user-avatar-wrap {
        position: relative !important;
        flex-shrink: 0 !important;
    }

    .user-avatar-initial {
        width: 32px !important;
        height: 32px !important;
        border-radius: 8px !important;
        background: #0e5638 !important;
        border: 1px solid #f5b800 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-family: 'Outfit', sans-serif !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        color: #ffffff !important;
    }

    .status-indicator-dot {
        position: absolute !important;
        bottom: -2px !important;
        right: -2px !important;
        width: 8px !important;
        height: 8px !important;
        background: #10b981 !important;
        border: 1.5px solid #041f14 !important;
        border-radius: 999px !important;
    }

    .user-details {
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
    }

    .user-display-name {
        font-size: 13px !important;
        font-weight: 700 !important;
        color: #ffffff !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    .user-role-chip {
        font-size: 10.5px !important;
        font-weight: 600 !important;
        color: #86efac !important;
    }

    .online-text-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        background: #10b981;
        border-radius: 50%;
        margin-right: 3px;
        vertical-align: middle;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 3px;
        flex: 1;
        overflow-y: auto;
        padding-right: 2px;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 4px;
    }

    .nav-group-label {
        font-size: 10px !important;
        text-transform: uppercase !important;
        letter-spacing: 1px !important;
        color: #7b8f84 !important;
        font-weight: 800 !important;
        margin: 8px 0 4px 6px !important;
    }

    .nav-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 9px 12px;
        border-radius: 8px;
        color: #b7c7be;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
        transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
        border-left: 3px solid transparent;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #ffffff;
    }

    .nav-item.active {
        background: rgba(245, 184, 0, 0.12);
        color: #ffffff;
        font-weight: 700;
        border-left-color: #f5b800;
    }

    .nav-item.active .nav-icon {
        color: #f5b800;
    }

    .nav-item-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .nav-item-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .nav-icon {
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #92a098;
        transition: color 0.15s ease;
    }

    .nav-icon svg {
        width: 100%;
        height: 100%;
    }

    .nav-label {
        line-height: 1.2;
    }

    .nav-total-pill {
        font-size: 10px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        color: #9bb0a4;
    }

    .nav-pending-badge {
        font-size: 10.5px;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 999px;
        line-height: 1.2;
        color: #ffffff;
    }

    .badge-gold {
        background: #f5b800;
        color: #041f14;
    }

    .badge-warning {
        background: #f59e0b;
    }

    .badge-blue {
        background: #3b82f6;
    }

    .badge-danger {
        background: #ef4444;
    }

    .sidebar-footer {
        padding-top: 10px !important;
        border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
        margin-top: auto !important;
    }

    .btn-sidebar-logout {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        width: 100% !important;
        padding: 8px 12px !important;
        border-radius: 8px !important;
        background: rgba(239, 68, 68, 0.08) !important;
        color: #fca5a5 !important;
        border: 1px solid rgba(239, 68, 68, 0.18) !important;
        text-decoration: none !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        transition: all 0.15s ease !important;
    }

    .btn-sidebar-logout:hover {
        background: #dc2626 !important;
        color: #ffffff !important;
        border-color: #dc2626 !important;
    }

    .sidebar-backdrop {
        display: none !important;
        position: fixed !important;
        inset: 0 !important;
        background: rgba(4, 31, 20, 0.6) !important;
        backdrop-filter: blur(3px) !important;
        z-index: 89 !important;
    }

    .sidebar-backdrop.active {
        display: block !important;
    }

    @media (max-width: 1024px) {
        .sidebar {
            position: fixed !important;
            left: -280px !important;
            transition: left 0.25s ease-in-out !important;
        }

        .sidebar.mobile-open {
            left: 0 !important;
        }

        .sidebar-mobile-close {
            display: inline-flex !important;
        }
    }
</style>

<script>
    function toggleAdminSidebar() {
        const sidebar = document.getElementById('adminSidebar');
        const backdrop = document.getElementById('adminSidebarBackdrop');
        if (sidebar && backdrop) {
            sidebar.classList.toggle('mobile-open');
            backdrop.classList.toggle('active');
        }
    }
</script>

<link rel="stylesheet" href="../assets/instant-nav.css">
<script src="../assets/instant-nav.js" defer></script>