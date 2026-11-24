<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika pengguna sudah login, arahkan ke dashboard masing-masing
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === "admin") {
        header("location:admin/dashboard.php");
        exit;
    } else {
        header("location:user/dashboard.php");
        exit;
    }
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/discount_fine_helper.php';
global $conn;

// Ambil perlengkapan unggulan untuk showcase
$featured_products = [];
if ($conn) {
    $q_feat = mysqli_query($conn, "SELECT id, name, category, product_type, price, price_sale, stock, stock_sale, image, description 
                                   FROM products 
                                   WHERE stock > 0 OR stock_sale > 0 
                                   ORDER BY id DESC LIMIT 8");
    if ($q_feat) {
        while ($row = mysqli_fetch_assoc($q_feat)) {
            $featured_products[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DANATRAIL — Persewaan & Penjualan Perlengkapan Gunung Outdoor Premium</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest-950: #041f14;
            --forest-900: #073522;
            --forest-800: #0e5638;
            --forest-700: #166e4a;
            --forest-600: #23895e;
            --forest-500: #2ea371;
            --forest-100: #d6f2e4;
            --forest-50:  #f0fdf7;

            --gold-500: #f5b800;
            --gold-600: #d99b00;

            --bg-canvas: #f8fafc;
            --surface: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;

            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 25px rgba(4, 31, 20, 0.08);
            --shadow-xl: 0 20px 35px rgba(4, 31, 20, 0.12);

            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-body);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* NAVBAR */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .nav-container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--forest-900), var(--forest-700));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 4px 10px rgba(7, 53, 34, 0.25);
        }

        .brand-text h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: var(--forest-950);
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .brand-text span {
            font-size: 11px;
            font-weight: 700;
            color: var(--forest-600);
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .nav-links a:hover {
            color: var(--forest-700);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-outline {
            text-decoration: none;
            padding: 8px 18px;
            border-radius: var(--radius-md);
            border: 1.5px solid var(--border-color);
            color: var(--forest-900);
            font-size: 13.5px;
            font-weight: 700;
            background: #fff;
            transition: all 0.2s ease;
        }

        .btn-outline:hover {
            border-color: var(--forest-700);
            background: var(--forest-50);
            color: var(--forest-800);
        }

        .btn-primary {
            text-decoration: none;
            padding: 8px 20px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--forest-800), var(--forest-700));
            color: #fff;
            font-size: 13.5px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(14, 86, 56, 0.25);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(14, 86, 56, 0.35);
            background: linear-gradient(135deg, var(--forest-900), var(--forest-800));
        }

        /* HERO SECTION */
        .hero {
            position: relative;
            background: linear-gradient(180deg, #eefbf4 0%, #f8fafc 100%);
            padding: 80px 24px 60px;
            border-bottom: 1px solid var(--border-color);
        }

        .hero-container {
            max-width: 1240px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 48px;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(22, 110, 74, 0.1);
            border: 1px solid rgba(22, 110, 74, 0.2);
            border-radius: 50px;
            color: var(--forest-800);
            font-size: 12.5px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .hero-title {
            font-family: 'Outfit', sans-serif;
            font-size: 46px;
            font-weight: 800;
            line-height: 1.15;
            color: var(--forest-950);
            letter-spacing: -1px;
            margin-bottom: 18px;
        }

        .hero-title span {
            background: linear-gradient(135deg, var(--forest-700), #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 16px;
            color: var(--text-muted);
            line-height: 1.65;
            margin-bottom: 30px;
            max-width: 580px;
        }

        .hero-btns {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .hero-banner-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 24px;
            box-shadow: var(--shadow-xl);
            position: relative;
        }

        .hero-img-wrap {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            height: 320px;
            background: #0f172a;
        }

        .hero-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.92;
        }

        .hero-overlay-tag {
            position: absolute;
            bottom: 16px;
            left: 16px;
            right: 16px;
            background: rgba(4, 31, 20, 0.85);
            backdrop-filter: blur(8px);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stats-strip {
            max-width: 1240px;
            margin: -30px auto 60px;
            padding: 0 24px;
            position: relative;
            z-index: 10;
        }

        .stats-grid {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px 32px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            box-shadow: var(--shadow-md);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--forest-50);
            color: var(--forest-700);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-info h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: var(--forest-950);
        }

        .stat-info p {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* SECTION HEADINGS */
        .section-header {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 40px;
        }

        .section-tag {
            font-size: 12px;
            font-weight: 800;
            color: var(--forest-700);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
            display: inline-block;
        }

        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: var(--forest-950);
            letter-spacing: -0.5px;
        }

        .section-desc {
            font-size: 15px;
            color: var(--text-muted);
            margin-top: 8px;
        }

        /* SHOWCASE KATALOG */
        .katalog-section {
            max-width: 1240px;
            margin: 0 auto 80px;
            padding: 0 24px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 24px;
        }

        .product-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease;
            box-shadow: var(--shadow-sm);
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: #cbd5e1;
        }

        .product-img-box {
            position: relative;
            height: 190px;
            background: #f1f5f9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-img-box img {
            transform: scale(1.04);
        }

        .type-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #fff;
        }

        .badge-rental { background: #2563eb; }
        .badge-sale { background: #059669; }
        .badge-both { background: #7c3aed; }

        .product-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .product-cat {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .product-name {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: var(--forest-950);
            line-height: 1.3;
            margin-bottom: 10px;
        }

        .product-pricing {
            margin-top: auto;
            border-top: 1px dashed var(--border-color);
            padding-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 14px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
        }

        .price-val {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 15px;
            color: var(--forest-900);
        }

        .btn-card-act {
            text-decoration: none;
            padding: 9px 12px;
            border-radius: var(--radius-md);
            background: var(--forest-50);
            border: 1px solid var(--forest-100);
            color: var(--forest-800);
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            display: block;
            transition: all 0.2s ease;
        }

        .btn-card-act:hover {
            background: var(--forest-700);
            border-color: var(--forest-700);
            color: #fff;
        }

        /* FITUR & KEUNGGULAN */
        .features-section {
            background: #ffffff;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            padding: 80px 24px;
        }

        .features-container {
            max-width: 1240px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 28px;
        }

        .feature-card {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 28px 24px;
            transition: all 0.25s ease;
        }

        .feature-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: #cbd5e1;
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--forest-800), var(--forest-600));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
            box-shadow: 0 4px 12px rgba(14, 86, 56, 0.2);
        }

        .feature-card h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--forest-950);
            margin-bottom: 8px;
        }

        .feature-card p {
            font-size: 13.5px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* LOKASI BASECAMP */
        .basecamp-section {
            max-width: 1240px;
            margin: 80px auto;
            padding: 0 24px;
        }

        .basecamp-box {
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 100%);
            border-radius: var(--radius-xl);
            padding: 48px;
            color: #fff;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: center;
            box-shadow: var(--shadow-xl);
        }

        .basecamp-info h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 14px;
            line-height: 1.2;
        }

        .basecamp-info p {
            color: #a7f3d0;
            font-size: 15px;
            line-height: 1.65;
            margin-bottom: 24px;
        }

        .basecamp-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
            font-size: 13.5px;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .meta-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gold-500);
            flex-shrink: 0;
        }

        /* FOOTER */
        footer {
            background: #041f14;
            color: #94a3b8;
            padding: 60px 24px 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .footer-container {
            max-width: 1240px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-brand h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 10px;
        }

        .footer-brand p {
            font-size: 13.5px;
            line-height: 1.6;
            max-width: 400px;
        }

        .footer-col h4 {
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
        }

        .footer-col a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-col a:hover {
            color: #34d399;
        }

        .footer-bottom {
            max-width: 1240px;
            margin: 0 auto;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            text-align: center;
            font-size: 12.5px;
            color: #64748b;
        }

        @media (max-width: 900px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 36px;
            }
            .hero-title {
                font-size: 34px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .basecamp-box {
                grid-template-columns: 1fr;
                padding: 32px 24px;
            }
            .footer-container {
                grid-template-columns: 1fr;
            }
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <header class="navbar">
        <div class="nav-container">
            <a href="index.php" class="brand-logo">
                <div class="brand-icon-box">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div class="brand-text">
                    <h1>DANATRAIL</h1>
                    <span>Outdoor Gear & Rental</span>
                </div>
            </a>

            <ul class="nav-links">
                <li><a href="#katalog">Katalog Perlengkapan</a></li>
                <li><a href="#keunggulan">Keunggulan</a></li>
                <li><a href="#basecamp">Lokasi Basecamp</a></li>
                <li><a href="about.php">Tentang Kami</a></li>
            </ul>

            <div class="nav-actions">
                <a href="login.php" class="btn-outline">Masuk</a>
                <a href="register.php" class="btn-primary">
                    <span>Daftar Akun</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-container">
            <div>
                <div class="hero-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Standar Ekspedisi Gunung & Trekking</span>
                </div>
                <h1 class="hero-title">
                    Sewa & Beli Perlengkapan Gunung <span>Standar Ekspedisi</span>.
                </h1>
                <p class="hero-subtitle">
                    Persiapkan pendakianmu dengan perlengkapan outdoor berkualitas tinggi, steril, dan terawat. Booking sewa secara online dengan pembayaran otomatis Midtrans atau beli perlengkapan baru siap kirim ke seluruh Indonesia.
                </p>
                <div class="hero-btns">
                    <a href="#katalog" class="btn-primary" style="padding: 12px 24px; font-size: 15px;">
                        <span>Jelajahi Katalog</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </a>
                    <a href="login.php" class="btn-outline" style="padding: 12px 22px; font-size: 15px;">
                        Masuk ke Dashboard
                    </a>
                </div>
            </div>

            <div>
                <div class="hero-banner-card">
                    <div class="hero-img-wrap">
                        <img src="https://images.unsplash.com/photo-1510312305653-8ed496efae75?w=800&auto=format&fit=crop&q=80" alt="Pendakian Gunung Outdoor Danatrail">
                        <div class="hero-overlay-tag">
                            <div>
                                <strong style="display: block; font-size: 13.5px;">Basecamp Merapi & Merbabu</strong>
                                <span style="font-size: 11.5px; opacity: 0.85;">Siap Antar / Ambil Mandiri</span>
                            </div>
                            <span style="font-size: 11.5px; font-weight: 800; background: #059669; padding: 4px 10px; border-radius: 6px;">Buka 24 Jam</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS STRIP -->
    <div class="stats-strip">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div class="stat-info">
                    <h4>100% Terawat</h4>
                    <p>Steril & Diuji Layak Pakai</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="stat-info">
                    <h4>Midtrans Gateway</h4>
                    <p>QRIS, VA Bank & Kartu</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                    </svg>
                </div>
                <div class="stat-info">
                    <h4>Jaminan Fleksibel</h4>
                    <p>KTP / SIM / Kartu Resmi</p>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                </div>
                <div class="stat-info">
                    <h4>Pickup & Kurir</h4>
                    <p>Ambil Basecamp / JNE / J&T</p>
                </div>
            </div>
        </div>
    </div>

    <!-- SHOWCASE KATALOG -->
    <section class="katalog-section" id="katalog">
        <div class="section-header">
            <span class="section-tag">Pilihan Perlengkapan</span>
            <h2 class="section-title">Katalog Alat Unggulan DANATRAIL</h2>
            <p class="section-desc">Pilih perlengkapan sewa untuk pendakian akhir pekan atau beli perlengkapan baru untuk koleksi petualanganmu.</p>
        </div>

        <div class="products-grid">
            <?php if (!empty($featured_products)): ?>
                <?php foreach ($featured_products as $p): ?>
                    <?php
                    $is_rent = in_array($p['product_type'], ['rental', 'both']);
                    $is_sale = in_array($p['product_type'], ['sale', 'both']);
                    $badge_cls = ($p['product_type'] === 'both') ? 'badge-both' : (($p['product_type'] === 'sale') ? 'badge-sale' : 'badge-rental');
                    $badge_lbl = ($p['product_type'] === 'both') ? 'Sewa & Beli' : (($p['product_type'] === 'sale') ? 'Toko (Beli)' : 'Disewakan');

                    $img_src = !empty($p['image']) && file_exists(__DIR__ . '/uploads/products/' . $p['image']) 
                        ? 'uploads/products/' . htmlspecialchars($p['image']) 
                        : 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=600&auto=format&fit=crop&q=70';
                    ?>
                    <div class="product-card">
                        <div class="product-img-box">
                            <span class="type-badge <?php echo $badge_cls; ?>"><?php echo $badge_lbl; ?></span>
                            <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=600&auto=format&fit=crop&q=70'">
                        </div>
                        <div class="product-body">
                            <div class="product-cat"><?php echo htmlspecialchars($p['category']); ?></div>
                            <h3 class="product-name"><?php echo htmlspecialchars($p['name']); ?></h3>

                            <div class="product-pricing">
                                <?php if ($is_rent): ?>
                                    <div class="price-row">
                                        <span style="color: var(--text-muted);">Sewa:</span>
                                        <span class="price-val" style="color: #1d4ed8;">Rp <?php echo number_format($p['price'], 0, ',', '.'); ?><span style="font-size: 11px; font-weight: 500; color: var(--text-muted);">/hari</span></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($is_sale): ?>
                                    <div class="price-row">
                                        <span style="color: var(--text-muted);">Beli:</span>
                                        <span class="price-val" style="color: #047857;">Rp <?php echo number_format($p['price_sale'], 0, ',', '.'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <a href="login.php?pesan=<?php echo urlencode('Silakan login untuk menyewa atau membeli perlengkapan ini!'); ?>" class="btn-card-act">
                                <?php echo ($p['product_type'] === 'sale') ? 'Beli Sekarang' : 'Sewa / Booking'; ?> →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: #fff; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <p style="color: var(--text-muted);">Katalog sedang disiapkan oleh tim basecamp.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- FITUR & KEUNGGULAN -->
    <section class="features-section" id="keunggulan">
        <div class="features-container">
            <div class="section-header">
                <span class="section-tag">Mengapa DANATRAIL?</span>
                <h2 class="section-title">Standar Layanan Petualangan Terbaik</h2>
                <p class="section-desc">Kami memastikan setiap pendaki mendapatkan peralatan yang aman, higienis, dan siap menghadapi cuaca ekstrem.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3>Peralatan Grade Ekspedisi</h3>
                    <p>Seluruh unit tenda, carrier, matras, dan kompor berasal dari merk outdoor terkemuka yang teruji daya tahan serta ketahanannya di alam bebas.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3>Pembayaran Instan & Otomatis</h3>
                    <p>Terhubung langsung dengan Midtrans Payment Gateway. Bayar instan via QRIS Gopay/OVO/Dana, Virtual Account Bank, maupun transfer manual.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3>Denda Transparan & Grace Period</h3>
                    <p>Sistem penghitungan denda otomatis dengan batas toleransi waktu (grace period) yang transparan dan dapat dipantau di akun pendaki.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3>Layanan Basecamp 24 Jam</h3>
                    <p>Tim kami siap membantu pengambilan unit tengah malam sebelum pendakian dini hari, serta konsultasi rute dan perlengkapan gunung.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- LOKASI BASECAMP -->
    <section class="basecamp-section" id="basecamp">
        <div class="basecamp-box">
            <div class="basecamp-info">
                <h2>Kunjungi Basecamp DANATRAIL</h2>
                <p>Mau lihat unit langsung, cek kelayakan tenda, atau konsultasi sebelum menyewa? Pintu basecamp kami selalu terbuka untuk rekan pendaki.</p>
                
                <div class="basecamp-meta">
                    <div class="meta-row">
                        <span class="meta-dot"></span>
                        <span><strong>Alamat:</strong> Jl. Kaliurang KM 14.5, Umbulmartani, Sleman, D.I. Yogyakarta</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-dot"></span>
                        <span><strong>Jam Buka:</strong> Setiap Hari, 07.00 - 22.00 WIB (Pickup Dini Hari via Janji Temu)</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-dot"></span>
                        <span><strong>WhatsApp CS:</strong> +62 812-3456-7890 (Respon Cepat)</span>
                    </div>
                </div>
            </div>

            <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: var(--radius-lg); padding: 28px; text-align: center;">
                <h3 style="font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 700; margin-bottom: 12px; color: #fff;">Mulai Petualanganmu Sekarang</h3>
                <p style="font-size: 13.5px; color: #a7f3d0; margin-bottom: 22px;">Daftar akun gratis hanya dalam 30 detik dan nikmati voucher promo pengguna baru.</p>
                <a href="register.php" class="btn-primary" style="width: 100%; justify-content: center; padding: 12px 20px; font-size: 14.5px;">
                    Daftar Akun Pendaki Baru
                </a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer>
        <div class="footer-container">
            <div class="footer-brand">
                <h2>DANATRAIL</h2>
                <p>Penyedia persewaan dan penjualan perlengkapan kegiatan alam bebas terpercaya di Yogyakarta. Memastikan keselamatan dan kenyamanan pendakian Anda sejak 2026.</p>
            </div>
            <div class="footer-col">
                <h4>Navigasi</h4>
                <ul>
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="#katalog">Katalog Peralatan</a></li>
                    <li><a href="about.php">Tentang Kami</a></li>
                    <li><a href="login.php">Masuk Pengguna</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Metode Pembayaran</h4>
                <ul>
                    <li>Midtrans Snap Online</li>
                    <li>QRIS All Payment</li>
                    <li>BCA / Mandiri / BNI / BRI</li>
                    <li>Bayar Tunai di Basecamp</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> DANATRAIL Outdoor Rental & Equipment. Seluruh Hak Cipta Dilindungi Undang-Undang.</p>
        </div>
    </footer>

</body>
</html>
