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
    <!-- Transparent Radiant Mountain Background Watermark -->
    <div class="sidebar-mountain-backdrop" aria-hidden="true">
        <svg viewBox="0 0 280 260" fill="none" xmlns="http://www.w3.org/2000/svg" class="sidebar-mountain-svg" preserveAspectRatio="none">
            <defs>
                <radialGradient id="summitRadiance" cx="140" cy="110" r="120" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#f5b800" stop-opacity="0.25"/>
                    <stop offset="0.45" stop-color="#10b981" stop-opacity="0.12"/>
                    <stop offset="1" stop-color="#041f14" stop-opacity="0"/>
                </radialGradient>
                <linearGradient id="transPeakBack" x1="140" y1="60" x2="140" y2="260" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#34d399" stop-opacity="0.28"/>
                    <stop offset="0.5" stop-color="#059669" stop-opacity="0.1"/>
                    <stop offset="1" stop-color="#041f14" stop-opacity="0"/>
                </linearGradient>
                <linearGradient id="transPeakMid" x1="140" y1="120" x2="140" y2="260" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#10b981" stop-opacity="0.32"/>
                    <stop offset="0.5" stop-color="#047857" stop-opacity="0.12"/>
                    <stop offset="1" stop-color="#022c22" stop-opacity="0.02"/>
                </linearGradient>
                <linearGradient id="transPeakFront" x1="140" y1="170" x2="140" y2="260" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#6ee7b7" stop-opacity="0.22"/>
                    <stop offset="0.4" stop-color="#065f46" stop-opacity="0.15"/>
                    <stop offset="1" stop-color="#041f14" stop-opacity="0.05"/>
                </linearGradient>
                <linearGradient id="contourGold" x1="0" y1="0" x2="280" y2="260" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#fbbf24" stop-opacity="0.4"/>
                    <stop offset="0.7" stop-color="#34d399" stop-opacity="0.2"/>
                    <stop offset="1" stop-color="#10b981" stop-opacity="0.05"/>
                </linearGradient>
            </defs>
            <circle cx="140" cy="110" r="100" fill="url(#summitRadiance)" />
            <path d="M-20 260L25 150L75 180L140 70L205 140L245 110L300 260H-20Z" fill="url(#transPeakBack)" />
            <path d="M-20 260L25 150L75 180L140 70L205 140L245 110L300 260" stroke="url(#contourGold)" stroke-width="1.2" stroke-dasharray="3 3" opacity="0.6"/>
            <path d="M140 70L132 88L140 84L148 90L140 70Z" fill="#ffffff" opacity="0.35" />
            <path d="M-30 260L35 180L95 120L160 175L215 120L270 185L310 260H-30Z" fill="url(#transPeakMid)" />
            <path d="M-20 260L20 205L70 175L130 215L185 160L240 210L300 185V260H-20Z" fill="url(#transPeakFront)" />
        </svg>
    </div>

    <!-- Sidebar Top Section -->
    <div class="sidebar-top-group">
        <!-- Brand Header -->
        <div class="sidebar-header">
            <div class="sidebar-brand-wrapper">
                <div class="sidebar-logo">
                    <img src="../assets/logo.png" alt="DANATRAIL" onerror="this.src='https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=80&auto=format&fit=crop&q=60'">
                </div>
                <div class="sidebar-brand-text">
                    <span class="brand-title">DANATRAIL</span>
                    <span class="brand-tagline">Outdoor Store & Rental</span>
                </div>
            </div>
            <button type="button" class="sidebar-mobile-close" onclick="toggleMobileSidebar()" aria-label="Tutup Navigasi">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- User Profile Badge -->
        <a href="profile.php" class="user-profile-badge" style="text-decoration: none; display: flex;" title="Buka Pengaturan Profil & Akun">
            <div class="user-avatar-wrap">
                <div class="user-avatar-initial">
                    <?php echo strtoupper(substr($userName, 0, 2)); ?>
                </div>
                <span class="status-indicator-dot" title="Member Aktif Online"></span>
            </div>
            <div class="user-details">
                <span class="user-display-name" title="<?php echo htmlspecialchars($userName); ?>">
                    <?php echo htmlspecialchars($userName); ?>
                </span>
                <span class="user-role-chip">
                    <span class="online-user-dot"></span> Pengaturan Profil
                </span>
            </div>
        </a>

        <!-- Sidebar Navigation -->
        <nav class="sidebar-nav">
            <div class="nav-group-label">Menu Navigasi</div>
            
            <!-- 1. Beranda (Landing Page) -->
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

            <!-- 3. Riwayat Sewa -->
            <a href="riwayat.php" class="nav-item <?php echo ($current_page == 'riwayat.php') ? 'active' : ''; ?>">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <span class="nav-label">Riwayat Sewa</span>
            </a>

            <!-- 5. Pesanan Toko -->
            <a href="pesanan.php" class="nav-item <?php echo ($current_page == 'pesanan.php') ? 'active' : ''; ?>">
                <div class="nav-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                </div>
                <span class="nav-label">Pesanan Belanja</span>
            </a>

            <!-- 6. Profil Akun -->
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
        <div class="sidebar-expedition-badge">
            <div class="badge-head">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21l7-14 7 14M9 21l3-6 3 6M12 7V3" />
                </svg>
                <span>Ekspedisi Gunung</span>
            </div>
            <p>Basecamp Buka 08.00 – 21.00 WIB</p>
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
       SIDEBAR REFINED MODERN STYLING
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
        padding: 20px 16px;
        box-sizing: border-box;
        overflow-y: auto;
        scrollbar-width: none;
        background: linear-gradient(180deg, #03180f 0%, #062618 55%, #02120a 100%) !important;
        border-right: 1px solid rgba(255, 255, 255, 0.08);
        flex-shrink: 0;
        z-index: 90;
    }
    
    .sidebar * {
        box-sizing: border-box;
    }

    .sidebar::-webkit-scrollbar {
        display: none;
    }

    /* Subtle Mountain Silhouette in Background */
    .sidebar-mountain-backdrop {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 260px;
        pointer-events: none;
        z-index: 0;
        opacity: 0.45;
        overflow: hidden;
    }

    .sidebar-mountain-svg {
        width: 100%;
        height: 100%;
        display: block;
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
        padding-bottom: 14px;
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
        padding: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid #f5b800;
        box-shadow: 0 0 12px rgba(245, 184, 0, 0.25);
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
        font-size: 16px;
        font-weight: 800;
        letter-spacing: 0.8px;
        color: #ffffff;
        line-height: 1.1;
    }

    .brand-tagline {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #f5b800;
        margin-top: 2px;
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
        gap: 10px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 9px 12px;
        border-radius: 12px;
        margin: 14px 0 14px 0;
        backdrop-filter: blur(6px);
    }

    .user-avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }

    .user-avatar-initial {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        background: #103824;
        border: 1px solid #f5b800;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Outfit', sans-serif;
        font-weight: 800;
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
        border: 1.5px solid #03180f;
        border-radius: 50%;
        box-shadow: 0 0 6px #10b981;
    }

    .user-details {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .user-display-name {
        font-size: 12.5px;
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
        display: flex;
        align-items: center;
    }

    .online-user-dot {
        display: inline-block;
        width: 5px;
        height: 5px;
        background: #10b981;
        border-radius: 50%;
        margin-right: 4px;
    }

    /* Navigation */
    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .nav-group-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6a8275;
        font-weight: 800;
        margin: 6px 0 4px 6px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border-radius: 10px;
        color: #a3b8ac;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.18s ease;
        border-left: 3px solid transparent;
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
        transform: translateX(2px);
    }

    .nav-item.active {
        background: linear-gradient(90deg, rgba(245, 184, 0, 0.16) 0%, rgba(16, 185, 129, 0.08) 100%);
        color: #ffffff;
        font-weight: 700;
        border-left-color: #f5b800;
        box-shadow: inset 0 0 10px rgba(245, 184, 0, 0.06);
    }

    .nav-item.active .nav-icon {
        color: #f5b800;
    }

    /* Basecamp Expedition Badge */
    .sidebar-expedition-badge {
        background: rgba(4, 28, 18, 0.7);
        border: 1px solid rgba(52, 211, 153, 0.2);
        border-radius: 10px;
        padding: 8px 11px;
        backdrop-filter: blur(8px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        margin-bottom: 10px;
    }

    .sidebar-expedition-badge .badge-head {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #f5b800;
        margin-bottom: 2px;
    }

    .sidebar-expedition-badge p {
        font-size: 10px;
        color: #9ec3af;
        margin: 0;
        font-weight: 600;
    }

    /* Sidebar Footer / Logout */
    .sidebar-footer {
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .btn-sidebar-logout {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 8px 12px;
        border-radius: 9px;
        background: rgba(239, 68, 68, 0.08);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.2);
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.18s ease;
    }

    .btn-sidebar-logout:hover {
        background: #dc2626;
        color: #ffffff;
        border-color: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
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