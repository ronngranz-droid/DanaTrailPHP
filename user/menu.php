<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$userName     = $_SESSION['nama'] ?? 'User';

if (!isset($conn) || !$conn) {
    require_once __DIR__ . '/../config/config.php';
}
global $conn;
?>

<aside class="sidebar" id="appSidebar">
    <!-- Sidebar Top Section -->
    <div class="sidebar-top-group">
        <!-- Brand Header -->
        <div class="sidebar-header">
            <a href="about.php" class="sidebar-brand-wrapper" style="text-decoration: none;">
                <div class="sidebar-logo">
                    <img src="../assets/logo.png" alt="DANATRAIL">
                </div>
                <div class="sidebar-brand-text">
                    <span class="brand-title">DANATRAIL</span>
                    <span class="brand-tagline">Outdoor Equipment & Rental</span>
                </div>
            </a>
            <button type="button" class="sidebar-mobile-close" onclick="toggleMobileSidebar()" aria-label="Tutup Navigasi">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- User Profile Card -->
        <a href="profile.php" class="user-profile-badge" title="Buka Pengaturan Profil & Akun">
            <div class="user-avatar-wrap">
                <div class="user-avatar-initial">
                    <?php echo strtoupper(substr($userName, 0, 2)); ?>
                </div>
                <span class="status-indicator-dot" title="Member Aktif"></span>
            </div>
            <div class="user-details">
                <span class="user-display-name" title="<?php echo htmlspecialchars($userName); ?>">
                    <?php echo htmlspecialchars($userName); ?>
                </span>
                <span class="user-role-chip">
                    <span class="online-user-dot"></span> Member Basecamp
                </span>
            </div>
        </a>

        <!-- Sidebar Navigation -->
        <nav class="sidebar-nav">
            <div class="nav-group-label">Menu Utama</div>
            
            <!-- 1. Beranda -->
            <a href="about.php" class="nav-item <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                </div>
                <span class="nav-label">Beranda</span>
            </a>

            <!-- 2. Katalog Rental & Toko -->
            <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                </div>
                <span class="nav-label">Katalog Perlengkapan</span>
            </a>

            <div class="nav-group-label" style="margin-top: 14px;">Aktivitas Saya</div>

            <!-- 3. Riwayat Sewa -->
            <a href="riwayat.php" class="nav-item <?php echo ($current_page == 'riwayat.php') ? 'active' : ''; ?>">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <span class="nav-label">Riwayat Sewa</span>
            </a>

            <!-- 4. Pesanan Toko -->
            <a href="pesanan.php" class="nav-item <?php echo ($current_page == 'pesanan.php') ? 'active' : ''; ?>">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <span class="nav-label">Pesanan Belanja</span>
            </a>

            <!-- 5. Profil Akun -->
            <a href="profile.php" class="nav-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <span class="nav-label">Profil Saya</span>
            </a>
        </nav>
    </div>

    <!-- Sidebar Bottom Section -->
    <div class="sidebar-bottom-group">
        <!-- Basecamp Info Badge -->
        <div class="sidebar-basecamp-card">
            <div class="basecamp-card-head">
                <span class="basecamp-live-dot"></span>
                <span>Basecamp Merapi</span>
            </div>
            <p class="basecamp-card-hours">Buka 08.00 – 21.00 WIB</p>
            <a href="https://wa.me/6288996016686?text=Halo%20Admin%20DANATRAIL,%20saya%20ingin%20bertanya%20seputar%20sewa%20alat" target="_blank" class="basecamp-wa-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span>Chat Admin Basecamp</span>
            </a>
        </div>

        <!-- Sidebar Footer / Logout -->
        <div class="sidebar-footer">
            <a href="../auth.php?logout=1" class="btn-sidebar-logout" onclick="return confirm('Apakah Anda yakin ingin logout dari DANATRAIL?');">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Keluar Akun</span>
            </a>
        </div>
    </div>
</aside>

<!-- Mobile Overlay -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

<style>
    /* ==========================================================================
       SIDEBAR REFINED MODERN STYLING (ELEVATED OUTDOOR AESTHETIC)
       ========================================================================== */
    .sidebar {
        width: 260px;
        min-width: 260px;
        height: 100vh;
        max-height: 100vh;
        position: sticky;
        top: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 22px 16px;
        box-sizing: border-box;
        overflow-y: auto;
        scrollbar-width: none;
        background: #09120e !important;
        border-right: 1px solid rgba(255, 255, 255, 0.07);
        flex-shrink: 0;
        z-index: 90;
    }
    
    .sidebar * {
        box-sizing: border-box;
    }

    .sidebar::-webkit-scrollbar {
        display: none;
    }

    .sidebar-top-group,
    .sidebar-bottom-group {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
    }

    .sidebar-top-group {
        flex: 1;
    }

    .sidebar-bottom-group {
        margin-top: auto;
        padding-top: 14px;
    }

    /* Header & Brand */
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
        border-radius: 50%;
        background: #ffffff;
        padding: 2px;
        border: 1.5px solid var(--gold-500, #f59e0b);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        flex-shrink: 0;
        overflow: hidden;
    }

    .sidebar-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 50%;
    }

    .sidebar-brand-text {
        display: flex;
        flex-direction: column;
    }

    .brand-title {
        font-family: 'Outfit', sans-serif;
        font-size: 17px;
        font-weight: 800;
        letter-spacing: 0.5px;
        color: #ffffff;
        line-height: 1.1;
    }

    .brand-tagline {
        font-size: 9px;
        font-weight: 600;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        color: #6ee7b7;
        margin-top: 3px;
    }

    .sidebar-mobile-close {
        display: none;
        background: rgba(255, 255, 255, 0.08);
        border: none;
        color: #ffffff;
        padding: 6px;
        border-radius: 6px;
        cursor: pointer;
    }

    /* User Profile Card */
    .user-profile-badge {
        display: flex;
        align-items: center;
        gap: 11px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 10px 12px;
        border-radius: 12px;
        margin: 16px 0 16px 0;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .user-profile-badge:hover {
        background: rgba(255, 255, 255, 0.07);
        border-color: rgba(255, 255, 255, 0.15);
    }

    .user-avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .user-avatar-initial {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #132a1e;
        border: 1px solid rgba(52, 211, 153, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
        font-size: 13px;
        color: #a7f3d0;
    }

    .status-indicator-dot {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 8px;
        height: 8px;
        background: #10b981;
        border: 1.5px solid #09120e;
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
        color: #f1f5f9;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-role-chip {
        font-size: 10px;
        font-weight: 600;
        color: #94a3b8;
        display: flex;
        align-items: center;
        margin-top: 1px;
    }

    .online-user-dot {
        display: inline-block;
        width: 5px;
        height: 5px;
        background: #10b981;
        border-radius: 50%;
        margin-right: 5px;
    }

    /* Navigation */
    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .nav-group-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #475569;
        font-weight: 800;
        margin: 8px 0 4px 8px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 12px;
        border-radius: 9px;
        color: #94a3b8;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.16s ease;
        border-left: 3px solid transparent;
    }

    .nav-icon {
        width: 17px;
        height: 17px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #64748b;
        transition: color 0.16s ease;
    }

    .nav-icon svg {
        width: 100%;
        height: 100%;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #ffffff;
    }

    .nav-item:hover .nav-icon {
        color: #ffffff;
    }

    .nav-item.active {
        background: rgba(16, 185, 129, 0.12);
        color: #34d399;
        font-weight: 700;
        border-left-color: #10b981;
    }

    .nav-item.active .nav-icon {
        color: #34d399;
    }

    /* Basecamp Info Card */
    .sidebar-basecamp-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 11px;
        padding: 12px 14px;
        margin-bottom: 12px;
    }

    .basecamp-card-head {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 11px;
        font-weight: 700;
        color: #f1f5f9;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .basecamp-live-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 6px rgba(16, 185, 129, 0.8);
    }

    .basecamp-card-hours {
        font-size: 11px;
        color: #94a3b8;
        margin: 3px 0 10px 0;
    }

    .basecamp-wa-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 700;
        color: #34d399;
        text-decoration: none;
        transition: color 0.15s ease;
    }

    .basecamp-wa-link:hover {
        color: #6ee7b7;
        text-decoration: underline;
    }

    /* Sidebar Footer / Logout */
    .sidebar-footer {
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .btn-sidebar-logout {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 8px 12px;
        border-radius: 8px;
        background: rgba(239, 68, 68, 0.06);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.18);
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.16s ease;
    }

    .btn-sidebar-logout:hover {
        background: #ef4444;
        color: #ffffff;
        border-color: #ef4444;
    }

    @media (max-width: 860px) {
        .sidebar {
            position: fixed !important;
            top: 0;
            left: 0;
            bottom: 0;
            height: 100vh;
            transform: translateX(-100%);
            transition: transform 0.25s ease;
            z-index: 999;
        }
        .sidebar.mobile-open {
            transform: translateX(0);
        }
        .sidebar-mobile-close {
            display: block;
        }
    }
</style>

<script>
    function toggleMobileSidebar() {
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar && backdrop) {
            sidebar.classList.toggle('mobile-open');
            backdrop.classList.toggle('active');
        }
    }
</script>

<link rel="stylesheet" href="../assets/instant-nav.css">
<script src="../assets/instant-nav.js" defer></script>
