<?php
session_start();
if (isset($_SESSION['role'])) {
    header("location:index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — DANATRAIL Outdoor Rental</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest-950: #0a1b12;
            --forest-900: #143322;
            --forest-800: #1c4530;
            --forest-700: #25593e;
            --sun-500: #f59e0b;
            --sun-400: #fbbf24;
            --sun-300: #fcd34d;
            --sand-50: #fbf9f5;
            --sand-100: #f4efe6;
            --sand-200: #e7ded0;
            --sand-300: #d5c8b4;
            --ink-900: #191c1a;
            --ink-800: #2c312d;
            --ink-500: #6b7280;
            --radius-lg: 18px;
            --radius-md: 12px;
            --radius-sm: 8px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--forest-950);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
            color: #ffffff;
        }

        /* Video Background */
        .video-bg {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            min-width: 100%;
            min-height: 100%;
            width: 100vw;
            height: 100vh;
            object-fit: cover;
            object-position: center;
            z-index: 0;
            pointer-events: none;
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, rgba(10, 27, 18, 0.78) 0%, rgba(20, 51, 34, 0.7) 100%);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            z-index: 1;
        }

        /* Topo Grid Overlay Decoration */
        .topo-grid {
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(245, 158, 11, 0.12) 1px, transparent 1px);
            background-size: 32px 32px;
            z-index: 1;
            pointer-events: none;
        }

        /* Card Container */
        .kotak-login {
            position: relative;
            z-index: 2;
            background: rgba(20, 51, 34, 0.55);
            -webkit-backdrop-filter: blur(20px);
            backdrop-filter: blur(20px);
            padding: 38px 32px;
            border-radius: var(--radius-lg);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(231, 222, 208, 0.18);
            width: 100%;
            max-width: 400px;
            color: #ffffff;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo-badge {
            width: 68px;
            height: 68px;
            margin: 0 auto 14px auto;
            border-radius: 50%;
            background: var(--sand-50);
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(245, 158, 11, 0.5);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
        }

        .logo-badge img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #ffffff;
            text-transform: uppercase;
        }

        .brand-tagline {
            font-size: 12px;
            color: var(--sand-200);
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .form-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--sun-400);
            margin-top: 10px;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 16px;
        }

        label {
            font-size: 13px;
            color: var(--sand-100);
            font-weight: 700;
            display: block;
            margin-bottom: 6px;
            letter-spacing: 0.2px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-field {
            width: 100%;
            padding: 13px 14px;
            background: rgba(10, 27, 18, 0.6);
            border: 1.5px solid rgba(231, 222, 208, 0.25);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            color: #ffffff;
            transition: all 0.2s ease;
        }

        .input-field::placeholder {
            color: rgba(244, 239, 230, 0.45);
        }

        .input-field:focus {
            outline: none;
            background: rgba(10, 27, 18, 0.85);
            border-color: var(--sun-500);
            box-shadow: 0 0 0 3.5px rgba(245, 158, 11, 0.25);
        }

        .password-box {
            position: relative;
            width: 100%;
        }

        .password-box .input-field {
            padding-right: 44px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--sand-200);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: color 0.2s ease;
            z-index: 5;
        }

        .toggle-password:hover {
            color: var(--sun-400);
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
            pointer-events: none;
        }

        /* Buttons */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--sun-500);
            color: var(--forest-950);
            border: none;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 900;
            font-family: inherit;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 4px 0px rgba(180, 83, 9, 0.8), 0 8px 20px rgba(245, 158, 11, 0.25);
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--sun-400);
            transform: translateY(-2px);
            box-shadow: 0 6px 0px rgba(180, 83, 9, 0.8), 0 12px 24px rgba(245, 158, 11, 0.35);
        }

        .btn-submit:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0px rgba(180, 83, 9, 0.8);
        }

        /* Alerts */
        .pesan-error {
            background-color: rgba(220, 38, 38, 0.25);
            color: #fecaca;
            border: 1px solid rgba(239, 68, 68, 0.4);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            padding: 11px 14px;
            border-radius: var(--radius-md);
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pesan-sukses {
            background-color: rgba(16, 185, 129, 0.2);
            color: #a7f3d0;
            border: 1px solid rgba(16, 185, 129, 0.4);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            padding: 11px 14px;
            border-radius: var(--radius-md);
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Footer Link */
        .auth-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid rgba(231, 222, 208, 0.15);
            font-size: 13px;
            color: var(--sand-200);
        }

        .auth-footer a {
            color: var(--sun-400);
            text-decoration: none;
            font-weight: 800;
            transition: all 0.2s;
        }

        .auth-footer a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        /* Feature Badge in bottom of login */
        .trust-badge {
            margin-top: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--sand-300);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .trust-badge svg {
            width: 14px;
            height: 14px;
            color: var(--sun-400);
        }

        /* Tombol Top About Us */
        .top-about-nav {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 10;
        }
        .btn-top-about {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(20, 51, 34, 0.75);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            color: #fff;
            padding: 8px 16px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            border: 1px solid rgba(251, 191, 36, 0.35);
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .btn-top-about svg {
            color: var(--sun-400);
            width: 16px;
            height: 16px;
        }
        .btn-top-about:hover {
            background: rgba(28, 69, 48, 0.95);
            border-color: var(--sun-400);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.35);
        }

        /* Modal About Us */
        .about-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 100;
            background: rgba(10, 27, 18, 0.82);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.25s ease forwards;
        }
        .about-modal.active {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .about-modal-content {
            background: #143322;
            background: linear-gradient(145deg, #143322 0%, #0a1b12 100%);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 680px;
            max-height: 88vh;
            overflow-y: auto;
            padding: 32px 28px;
            color: #fff;
            position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            animation: slideUp 0.3s ease forwards;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .btn-close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-close-modal:hover {
            background: #ef4444;
            border-color: #ef4444;
        }
        .about-modal-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .about-modal-logo {
            width: 50px;
            height: 50px;
            background: #fff;
            border-radius: 50%;
            padding: 4px;
            border: 2px solid var(--sun-400);
        }
        .about-modal-logo img { width: 100%; height: 100%; object-fit: contain; }
        .about-modal-title h2 { font-size: 20px; font-weight: 800; color: #fff; }
        .about-modal-title p { font-size: 13px; color: var(--sun-400); font-weight: 600; }
        
        .about-section {
            margin-bottom: 20px;
        }
        .about-section h3 {
            font-size: 15px;
            font-weight: 800;
            color: var(--sun-400);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .about-section p {
            font-size: 13.5px;
            color: #d1d5db;
            line-height: 1.6;
        }
        .about-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .feature-box {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
        }
        .feature-box h4 { font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 4px; }
        .feature-box p { font-size: 12px; color: #9ca3af; line-height: 1.4; }
        
        .modal-footer-contact {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .btn-wa-cs {
            background: #10b981;
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 12.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-wa-cs:hover { background: #059669; }
    </style>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</head>
<body>

    <!-- Tombol Navigasi Top About Us -->
    <div class="top-about-nav">
        <button type="button" class="btn-top-about" onclick="openAboutModal()" aria-label="Buka Tentang Kami">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Tentang Kami</span>
        </button>
    </div>

    <!-- Video Background MP4 -->
    <video autoplay muted loop playsinline class="video-bg">
        <source src="assets/montain.mp4" type="video/mp4">
    </video>
    <div class="overlay"></div>
    <div class="topo-grid"></div>

    <div class="kotak-login">
        <div class="brand-header">
            <div class="logo-badge">
                <img src="assets/logo.png" alt="Logo DANATRAIL">
            </div>
            <h1 class="brand-name">DANATRAIL</h1>
            <p class="brand-tagline">Sewa Perlengkapan Outdoor</p>
            <div class="form-title">Masuk ke Akun Anda</div>
        </div>

        <?php 
        if (isset($_GET['pesan'])) {
            echo '<div class="pesan-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>' . htmlspecialchars($_GET['pesan']) . '</span>
            </div>';
        }
        if (isset($_GET['sukses'])) {
            echo '<div class="pesan-sukses">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>' . htmlspecialchars($_GET['sukses']) . '</span>
            </div>';
        }
        ?>

        <form action="auth.php" method="POST">
            <div class="form-group">
                <label for="username_email">Email atau Username</label>
                <div class="input-wrapper">
                    <input type="text" id="username_email" name="username_email" class="input-field" placeholder="contoh@danatrail.id atau username" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <div class="password-box">
                    <input type="password" id="password" name="password" class="input-field" placeholder="Masukkan kata sandi Anda" required>
                    <button type="button" class="toggle-password" onclick="togglePass('password', this)" title="Lihat/Sembunyikan Kata Sandi" aria-label="Toggle password">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn-submit">
                <span>Masuk Sekarang</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
            </button>
        </form>

        <div class="auth-footer">
            Belum punya akun? <a href="register.php">Daftar Akun Baru</a>
        </div>

        <div class="trust-badge">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
            <span>Jaminan Stok Real-Time & Peralatan Terawat</span>
        </div>
    </div>

    <!-- Modal Popup About Us -->
    <div id="aboutModal" class="about-modal" onclick="closeAboutModalOnOutside(event)">
        <div class="about-modal-content">
            <button type="button" class="btn-close-modal" onclick="closeAboutModal()" aria-label="Tutup">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            
            <div class="about-modal-header">
                <div class="about-modal-logo">
                    <img src="assets/logo.png" alt="DANATRAIL">
                </div>
                <div class="about-modal-title">
                    <h2>Tentang DANATRAIL</h2>
                    <p>Sewa Perlengkapan Outdoor & Camping</p>
                </div>
            </div>

            <div class="about-section">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    Mengenal DANATRAIL
                </h3>
                <p>DANATRAIL adalah platform persewaan peralatan outdoor dan pendakian gunung berbasis web modern. Kami menyediakan tenda dome, tas carrier, sleeping bag, matras, kompor portable, hingga nesting set berkualitas prima untuk petualangan aman dan nyaman.</p>
            </div>

            <div class="about-section">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Kenapa Memilih Kami?
                </h3>
                <div class="about-features">
                    <div class="feature-box">
                        <h4>Alat Selalu Bersih</h4>
                        <p>Dibersihkan dan disterilkan secara rutin pasca pemakaian.</p>
                    </div>
                    <div class="feature-box">
                        <h4>Tarif Ramah Pelajar</h4>
                        <p>Biaya sewa harian transparan & bersahabat untuk siapa saja.</p>
                    </div>
                    <div class="feature-box">
                        <h4>Booking Online 24/7</h4>
                        <p>Cek ketersediaan stok alat secara akurat & real-time.</p>
                    </div>
                </div>
            </div>

            <div class="about-section">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    Alur Sewa Singkat
                </h3>
                <p style="font-size:12.5px;color:#cbd5e1;">
                    <strong>1. Daftar/Masuk</strong> &rarr; 
                    <strong>2. Pilih Alat & Jadwal</strong> &rarr; 
                    <strong>3. Konfirmasi Admin</strong> &rarr; 
                    <strong>4. Ambil & Serahkan Jaminan (KTP/SIM)</strong>.
                </p>
            </div>

            <div class="modal-footer-contact">
                <div>
                    <span style="font-size:11px;color:#94a3b8;display:block;">Ada Pertanyaan seputar sewa?</span>
                    <strong style="font-size:13px;color:#fff;">CS DANATRAIL: 0889-9601-6686</strong>
                </div>
                <a href="https://wa.me/6288996016686?text=Halo%20Admin%20DANATRAIL,%20saya%20ingin%20bertanya%20seputar%20sewa%20alat%20outdoor" target="_blank" class="btn-wa-cs">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                    <span>Chat WhatsApp</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePass(inputId, btn) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            
            if (isPassword) {
                // Ikon Mata Dicoret (Sedang Terlihat -> Klik untuk Sembunyikan)
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>`;
            } else {
                // Ikon Mata Terbuka (Sedang Tersembunyi -> Klik untuk Lihat)
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>`;
            }
        }

        function openAboutModal() {
            document.getElementById('aboutModal').classList.add('active');
        }

        function closeAboutModal() {
            document.getElementById('aboutModal').classList.remove('active');
        }

        function closeAboutModalOnOutside(e) {
            if (e.target.id === 'aboutModal') {
                closeAboutModal();
            }
        }

        // Tutup modal jika tombol ESC ditekan
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAboutModal();
            }
        });
    </script>
</body>
</html>
