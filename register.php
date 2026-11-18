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
    <title>Daftar Akun — DANATRAIL Outdoor Rental</title>
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
            padding: 30px 16px;
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
            background: linear-gradient(135deg, rgba(10, 27, 18, 0.8) 0%, rgba(20, 51, 34, 0.72) 100%);
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
            z-index: 1;
        }

        .topo-grid {
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(245, 158, 11, 0.12) 1px, transparent 1px);
            background-size: 32px 32px;
            z-index: 1;
            pointer-events: none;
        }

        /* Card Container */
        .kotak-register {
            position: relative;
            z-index: 2;
            background: rgba(20, 51, 34, 0.55);
            -webkit-backdrop-filter: blur(20px);
            backdrop-filter: blur(20px);
            padding: 38px 32px;
            border-radius: var(--radius-lg);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(231, 222, 208, 0.18);
            width: 100%;
            max-width: 420px;
            color: #ffffff;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-header {
            text-align: center;
            margin-bottom: 22px;
        }

        .logo-badge {
            width: 64px;
            height: 64px;
            margin: 0 auto 12px auto;
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
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #ffffff;
            text-transform: uppercase;
        }

        .form-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--sun-400);
            margin-top: 6px;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 14px;
        }

        label {
            font-size: 13px;
            color: var(--sand-100);
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
            letter-spacing: 0.2px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-field {
            width: 100%;
            padding: 12px 14px;
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
            width: 18px;
            height: 18px;
            pointer-events: none;
        }

        /* Submit Button */
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
            margin-bottom: 16px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Footer Link */
        .auth-footer {
            text-align: center;
            margin-top: 22px;
            padding-top: 16px;
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
    </style>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</head>
<body>

    <!-- Video Background MP4 -->
    <video autoplay muted loop playsinline class="video-bg">
        <source src="assets/montain.mp4" type="video/mp4">
    </video>
    <div class="overlay"></div>
    <div class="topo-grid"></div>

    <div class="kotak-register">
        <div class="brand-header">
            <div class="logo-badge">
                <img src="assets/logo.png" alt="Logo DANATRAIL">
            </div>
            <h1 class="brand-name">DANATRAIL</h1>
            <div class="form-title">Registrasi Akun Baru</div>
        </div>

        <?php 
        if (isset($_GET['pesan'])) {
            echo '<div class="pesan-error">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>' . htmlspecialchars($_GET['pesan']) . '</span>
            </div>';
        }
        ?>

        <form action="auth.php" method="POST">
            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <div class="input-wrapper">
                    <input type="text" id="name" name="name" class="input-field" placeholder="Nama lengkap Anda" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Alamat Email</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" class="input-field" placeholder="nama@email.com" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <div class="password-box">
                    <input type="password" id="password" name="password" class="input-field" placeholder="Minimal 6 karakter" required>
                    <button type="button" class="toggle-password" onclick="togglePass('password', this)" title="Lihat/Sembunyikan Kata Sandi" aria-label="Toggle password">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="konfirmasi_password">Konfirmasi Kata Sandi</label>
                <div class="password-box">
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="input-field" placeholder="Ulangi kata sandi" required>
                    <button type="button" class="toggle-password" onclick="togglePass('konfirmasi_password', this)" title="Lihat/Sembunyikan Kata Sandi" aria-label="Toggle password">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="register" class="btn-submit">
                <span>Daftar Sekarang</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
            </button>
        </form>

        <div class="auth-footer">
            Sudah memiliki akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>

    <script>
        function togglePass(inputId, btn) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            
            if (isPassword) {
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>`;
            } else {
                btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>`;
            }
        }
    </script>
</body>
</html>
