<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek autentikasi admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != "admin") {
    header("location:../login.php?pesan=Akses ditolak! Silakan login sebagai admin.");
    exit;
}

include '../config/config.php';
/** @var mysqli $conn */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami — DANATRAIL Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-950: #0a1b12;
            --forest-900: #143322;
            --forest-800: #1c4530;
            --forest-700: #25593e;
            --sun-500: #f59e0b;
            --sun-400: #fbbf24;
            --sand-50: #fbf9f5;
            --sand-100: #f4efe6;
            --sand-200: #e7ded0;
            --sand-300: #d5c8b4;
            --ink-900: #191c1a;
            --ink-700: #374151;
            --ink-500: #6b7280;
            --radius-lg: 16px;
            --radius-md: 10px;
            --radius-sm: 6px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--sand-50);
            color: var(--ink-900);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        .sidebar { width: 280px; background: var(--forest-950); color: #fff; display: flex; flex-direction: column; padding: 26px 20px; position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; }
        .sidebar-header { display: flex; align-items: center; gap: 12px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-logo { width: 44px; height: 44px; background: #fff; border-radius: 50%; padding: 4px; border: 2px solid var(--sun-400); }
        .sidebar-logo img { width: 100%; height: 100%; object-fit: contain; }
        .sidebar-brand h2 { font-size: 17px; font-weight: 800; color: #fff; }
        .role-badge { font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 999px; background: rgba(239,68,68,0.18); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        .user-profile-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); border-radius: var(--radius-md); padding: 12px 14px; display: flex; align-items: center; gap: 10px; margin-bottom: 24px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--forest-800); border: 1.5px solid var(--sun-400); display: flex; align-items: center; justify-content: center; color: var(--sun-400); }
        .user-avatar svg { width: 18px; height: 18px; }
        .user-name { font-size: 13.5px; font-weight: 700; color: #fff; }
        .user-status { font-size: 11px; color: #94a3b8; display: flex; align-items: center; gap: 5px; }
        .status-dot { width: 6px; height: 6px; background: #10b981; border-radius: 50%; }
        .sidebar-nav { display: flex; flex-direction: column; gap: 6px; flex-grow: 1; }
        .nav-section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; font-weight: 800; padding: 0 10px 4px; }
        .nav-item { display: flex; align-items: center; justify-content: space-between; padding: 11px 14px; border-radius: var(--radius-md); color: #cbd5e1; text-decoration: none; font-size: 13.5px; font-weight: 600; transition: all 0.2s; }
        .nav-item svg { width: 18px; height: 18px; }
        .nav-item:hover, .nav-item.active { background: var(--forest-800); color: var(--sun-400); font-weight: 700; }
        .sidebar-footer { padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.08); margin-top: auto; }
        .btn-logout-sidebar { display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 8px 12px; background: rgba(239,68,68,0.08); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); border-radius: var(--radius-sm); text-decoration: none; font-size: 12px; font-weight: 700; }
        .btn-logout-sidebar svg { width: 16px !important; height: 16px !important; flex-shrink: 0; }
        .btn-logout-sidebar:hover { background: rgba(239,68,68,0.9); color: #fff; }

        /* Main Content Layout */
        .main-wrapper {
            margin-left: 280px;
            flex-grow: 1;
            padding: 32px 36px;
            max-width: 1200px;
        }

        .hero-banner {
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 60%, var(--forest-800) 100%);
            border-radius: var(--radius-lg);
            padding: 36px 40px;
            color: #fff;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(251, 191, 36, 0.25);
            box-shadow: 0 10px 30px rgba(10, 27, 18, 0.12);
            margin-bottom: 28px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(251, 191, 36, 0.15);
            color: var(--sun-400);
            border: 1px solid rgba(251, 191, 36, 0.3);
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 14px;
        }
        .hero-banner h1 {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 10px;
        }
        .hero-banner p {
            color: #d1d5db;
            font-size: 14.5px;
            max-width: 720px;
            line-height: 1.6;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .card {
            background: #fff;
            border: 1px solid var(--sand-200);
            border-radius: var(--radius-lg);
            padding: 24px 26px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .card-icon {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: var(--sand-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--forest-900);
        }
        .card-icon svg { width: 22px; height: 22px; }
        .card-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--forest-950);
        }
        .card p {
            font-size: 13.5px;
            color: var(--ink-700);
            line-height: 1.6;
        }

        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 14px;
        }
        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13.5px;
            color: var(--ink-700);
        }
        .feature-item svg {
            width: 18px;
            height: 18px;
            color: #10b981;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-top: 14px;
        }
        .team-card {
            background: var(--sand-100);
            border: 1px solid var(--sand-200);
            border-radius: var(--radius-md);
            padding: 16px 18px;
            text-align: center;
        }
        .team-avatar {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: var(--forest-900);
            color: var(--sun-400);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-weight: 800;
            font-size: 18px;
        }
        .team-name { font-size: 14px; font-weight: 800; color: var(--forest-950); }
        .team-role { font-size: 12px; color: var(--ink-500); margin-top: 2px; }

        @media(max-width:900px){
            body { flex-direction: column; }
            .sidebar { position: relative; width: 100%; height: auto; }
            .main-wrapper { margin-left: 0; padding: 20px 16px; }
        }
    </style>
</head>
<body>

    <!-- Sidebar Menu Component -->
    <?php include 'menu.php'; ?>

    <!-- Main Content -->
    <main class="main-wrapper">
        <div class="hero-banner">
            <div class="hero-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Sistem Informasi Persewaan</span>
            </div>
            <h1>Tentang Aplikasi DANATRAIL</h1>
            <p>DANATRAIL adalah platform manajemen persewaan perlengkapan outdoor & petualangan berbasis web modern. Membantu para petualang menikmati alam dengan alat berkualitas dan manajemen inventaris yang akurat secara real-time.</p>
        </div>

        <div class="grid-2">
            <!-- Visi & Misi -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <div class="card-title">Visi & Nilai Utama</div>
                        <small style="color:var(--ink-500);">Komitmen Pelayanan Outdoor</small>
                    </div>
                </div>
                <p>Menjadi mitra persewaan perlengkapan outdoor terpercaya dengan standar kebersihan, keamanan, dan ketersediaan peralatan yang selalu prima di setiap pendakian.</p>
                <ul class="feature-list">
                    <li class="feature-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Peralatan selalu diuji kelayakan & disterilkan berkala.</span>
                    </li>
                    <li class="feature-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Transparansi harga dan sistem jaminan yang mudah & aman.</span>
                    </li>
                    <li class="feature-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Sinkronisasi stok barang secara real-time saat sewa disetujui.</span>
                    </li>
                </ul>
            </div>

            <!-- Informasi Pengembang & Sistem -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </div>
                    <div>
                        <div class="card-title">Spesifikasi Sistem</div>
                        <small style="color:var(--ink-500);">Teknologi & Lingkungan Kerja</small>
                    </div>
                </div>
                <p>Aplikasi ini dibangun menggunakan arsitektur modular PHP native dengan antarmuka modern yang cepat dan responsif.</p>
                <ul class="feature-list">
                    <li class="feature-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span><strong>Backend:</strong> PHP 8.x, MySQL / MariaDB Database.</span>
                    </li>
                    <li class="feature-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span><strong>Frontend:</strong> Semantic HTML5, Vanilla CSS Design System, JS.</span>
                    </li>
                    <li class="feature-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span><strong>Fitur:</strong> Multi-Role Auth, Manajemen Stok Otomatis, Filter Transaksi.</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Kontak & Tim -->
        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <div class="card-title">Pusat Bantuan & Tim Manajemen</div>
                    <small style="color:var(--ink-500);">Layanan Administrasi DANATRAIL</small>
                </div>
            </div>
            
            <div class="team-grid">
                <div class="team-card">
                    <div class="team-avatar">DT</div>
                    <div class="team-name">DANATRAIL Gear Team</div>
                    <div class="team-role">Penyedia & Perawatan Alat</div>
                </div>
                <div class="team-card">
                    <div class="team-avatar">CS</div>
                    <div class="team-name">Customer Service</div>
                    <div class="team-role">cs@danatrail.id • 0889-9601-6686</div>
                </div>
                <div class="team-card">
                    <div class="team-avatar">HQ</div>
                    <div class="team-name">Kantor Operasional</div>
                    <div class="team-role">Basecamp Outdoor Indonesia</div>
                </div>
            </div>
        </div>
    </main>

    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>
</html>
