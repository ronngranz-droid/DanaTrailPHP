<?php
// Standalone About Us Page
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami — DANATRAIL Outdoor</title>
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
            min-height: 100vh;
            padding: 30px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 860px;
            background: #fff;
            border-radius: var(--radius-lg);
            border: 1px solid var(--sand-200);
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .hero {
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 60%, var(--forest-800) 100%);
            padding: 36px 32px;
            color: #fff;
            position: relative;
        }
        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-logo {
            width: 44px;
            height: 44px;
            background: #fff;
            border-radius: 50%;
            padding: 3px;
            border: 2px solid var(--sun-400);
        }
        .brand-logo img { width: 100%; height: 100%; object-fit: contain; }
        .brand h2 { font-size: 18px; font-weight: 800; color: #fff; }
        .btn-back {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.25);
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: var(--sun-500);
            color: var(--forest-950);
            border-color: var(--sun-500);
        }

        .hero h1 { font-size: 26px; font-weight: 800; margin-bottom: 8px; }
        .hero p { color: #d1d5db; font-size: 14px; line-height: 1.6; }

        .content {
            padding: 32px;
        }
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .card-val {
            background: var(--sand-50);
            border: 1px solid var(--sand-200);
            border-radius: var(--radius-md);
            padding: 16px;
        }
        .card-val h4 { font-size: 14px; font-weight: 800; color: var(--forest-950); margin-bottom: 6px; }
        .card-val p { font-size: 12.5px; color: var(--ink-700); line-height: 1.5; }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--forest-950);
            margin-bottom: 12px;
        }

        .footer-action {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--sand-200);
            padding-top: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .btn-login-now {
            background: var(--forest-900);
            color: #fff;
            text-decoration: none;
            padding: 10px 22px;
            border-radius: var(--radius-sm);
            font-size: 13.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-login-now:hover { background: var(--forest-800); }
    </style>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</head>
<body>
    <div class="container">
        <div class="hero">
            <div class="hero-top">
                <div class="brand">
                    <div class="brand-logo">
                        <img src="assets/logo.png" alt="DANATRAIL">
                    </div>
                    <h2>DANATRAIL</h2>
                </div>
                <a href="login.php" class="btn-back">
                    &larr; Masuk ke Login
                </a>
            </div>
            <h1>Sewa Perlengkapan Outdoor & Petualangan</h1>
            <p>DANATRAIL menyediakan layanan persewaan peralatan camping & pendakian gunung berkualitas dengan sistem booking online yang cepat, mudah, dan transparan.</p>
        </div>

        <div class="content">
            <div class="section-title">Keunggulan Layanan Kami</div>
            <div class="grid-3">
                <div class="card-val">
                    <h4>Alat Terawat & Bersih</h4>
                    <p>Semua perlengkapan dicek kelayakannya dan disterilkan secara berkala setelah digunakan.</p>
                </div>
                <div class="card-val">
                    <h4>Tarif Ramah Pelajar</h4>
                    <p>Biaya sewa harian yang transparan dan terjangkau untuk mahasiswa & komunitas penggiat alam.</p>
                </div>
                <div class="card-val">
                    <h4>Booking Online 24/7</h4>
                    <p>Cek ketersediaan alat outdoor secara akurat dan real-time tanpa takut kehabisan stok di basecamp.</p>
                </div>
            </div>

            <div class="section-title">Alur Penyewaan</div>
            <p style="font-size:13.5px;color:var(--ink-700);line-height:1.6;margin-bottom:16px;">
                1. <strong>Pilih Alat:</strong> Jelajahi katalog alat outdoor yang tersedia.<br>
                2. <strong>Tentukan Jadwal:</strong> Pilih tanggal mulai sewa s/d tanggal kembali serta jenis jaminan (KTP/SIM).<br>
                3. <strong>Konfirmasi Admin:</strong> Tunggu persetujuan pesanan dari pihak admin.<br>
                4. <strong>Serah Terima:</strong> Ambil alat di basecamp dan nikmati petualangan alam Anda!
            </p>

            <div class="footer-action">
                <div>
                    <span style="font-size:12px;color:var(--ink-500);display:block;">Pertanyaan atau konsultasi sewa:</span>
                    <strong style="font-size:13.5px;color:var(--forest-950);">WhatsApp CS: 0889-9601-6686</strong>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="https://wa.me/6288996016686?text=Halo%20Admin%20DANATRAIL,%20saya%20ingin%20tanya%20sewa%20alat%20outdoor" target="_blank" style="background:#10b981;color:#fff;text-decoration:none;padding:10px 18px;border-radius:var(--radius-sm);font-size:13.5px;font-weight:700;display:inline-flex;align-items:center;gap:6px;">
                        <span>Chat WhatsApp</span>
                    </a>
                    <a href="login.php" class="btn-login-now">
                        <span>Masuk / Daftar</span>
                        &rarr;
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
