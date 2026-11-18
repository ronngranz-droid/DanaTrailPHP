<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek akses admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=Akses ditolak! Silakan login sebagai admin.");
    exit;
}

include '../config/config.php';
/** @var mysqli $conn */

$admin_id = $_SESSION['user_id'] ?? null;
if (!$admin_id) {
    $nama = mysqli_real_escape_string($conn, $_SESSION['nama']);
    $user_check = mysqli_query($conn, "SELECT id FROM users WHERE name='$nama' AND role='admin' LIMIT 1");
    if ($user_check && mysqli_num_rows($user_check) > 0) {
        $u = mysqli_fetch_assoc($user_check);
        $admin_id = $u['id'];
        $_SESSION['user_id'] = $admin_id;
    }
}

$pesan_sukses = $_GET['sukses'] ?? '';
$pesan_error  = $_GET['pesan'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name    = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
        $email   = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
        $phone   = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));

        if (empty($name) || empty($email)) {
            $pesan_error = "Nama dan Email admin tidak boleh kosong!";
        } else {
            $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != '$admin_id'");
            if ($cek && mysqli_num_rows($cek) > 0) {
                $pesan_error = "Email sudah digunakan oleh akun lain!";
            } else {
                $avatar_sql = "";
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $tmp  = $_FILES['avatar']['tmp_name'];
                    $name_f = $_FILES['avatar']['name'];
                    $ext  = strtolower(pathinfo($name_f, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                    if (in_array($ext, $allowed) && $_FILES['avatar']['size'] <= 3 * 1024 * 1024) {
                        $up_dir = '../uploads/avatars/';
                        if (!is_dir($up_dir)) {
                            mkdir($up_dir, 0777, true);
                        }
                        $new_avatar = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($tmp, $up_dir . $new_avatar)) {
                            $avatar_sql = ", avatar='$new_avatar'";
                        }
                    }
                }

                $update = mysqli_query($conn, "UPDATE users SET name='$name', email='$email', phone='$phone' $avatar_sql WHERE id='$admin_id'");
                if ($update) {
                    $_SESSION['nama'] = $name;
                    $_SESSION['email'] = $email;
                    $pesan_sukses = "Profil Administrator berhasil diperbarui!";
                } else {
                    $pesan_error = "Gagal memperbarui profil: " . mysqli_error($conn);
                }
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $cfm_pass = $_POST['confirm_password'] ?? '';

        if (empty($old_pass) || empty($new_pass) || empty($cfm_pass)) {
            $pesan_error = "Semua kolom password wajib diisi!";
        } elseif ($new_pass !== $cfm_pass) {
            $pesan_error = "Konfirmasi password baru tidak cocok!";
        } elseif (strlen($new_pass) < 6) {
            $pesan_error = "Password baru minimal 6 karakter!";
        } else {
            $q_user = mysqli_query($conn, "SELECT password FROM users WHERE id='$admin_id'");
            $u_data = mysqli_fetch_assoc($q_user);

            if (password_verify($old_pass, $u_data['password']) || $old_pass === $u_data['password']) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $up_pass = mysqli_query($conn, "UPDATE users SET password='$new_hash' WHERE id='$admin_id'");
                if ($up_pass) {
                    $pesan_sukses = "Password Admin berhasil diperbarui!";
                } else {
                    $pesan_error = "Gagal memperbarui password: " . mysqli_error($conn);
                }
            } else {
                $pesan_error = "Password admin saat ini tidak sesuai!";
            }
        }
    }
}

// Ambil data admin
$q_admin = mysqli_query($conn, "SELECT * FROM users WHERE id='$admin_id'");
$admin = mysqli_fetch_assoc($q_admin);

// Statistik Sistem
$total_trans = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rentals"))['c'] ?? 0;
$total_prods = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products"))['c'] ?? 0;
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='user'"))['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Administrator — DANATRAIL</title>
    <!-- Google Fonts: Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --forest-950: #041f14;
            --forest-900: #073522;
            --forest-800: #0e5638;
            --forest-700: #166e4a;
            --forest-600: #23895e;
            --gold-500: #f5b800;
            --gold-600: #d99b00;
            --emerald-600: #059669;
            --emerald-500: #10b981;
            --bg-canvas: #f7f8f5;
            --surface-card: #ffffff;
            --border-color: #e4e9e5;
            --text-main: #101914;
            --text-body: #33443b;
            --text-muted: #66746c;
            --radius-xl: 16px;
            --radius-lg: 12px;
            --radius-md: 8px;
            --shadow-card: 0 2px 10px rgba(7, 53, 34, 0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-body);
            display: flex;
            min-height: 100vh;
            line-height: 1.5;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            min-width: 0;
        }

        .top-navbar {
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .nav-title h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: var(--forest-950);
        }
        .nav-title p {
            font-size: 12px;
            color: var(--text-muted);
        }

        .content-area {
            padding: 24px 28px;
            max-width: 1000px;
            width: 100%;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .alert-success { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .profile-hero {
            background: linear-gradient(135deg, #02160d 0%, #062b1a 60%, #0d4a2e 100%);
            border-radius: var(--radius-xl);
            padding: 28px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(7, 53, 34, 0.15);
        }

        .hero-info {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .hero-avatar {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.12);
            border: 2px solid var(--gold-500);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            overflow: hidden;
            box-shadow: 0 0 15px rgba(245, 184, 0, 0.3);
        }
        .hero-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .hero-text h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
        }
        .hero-text p { font-size: 13px; color: #86efac; margin-top: 2px; }

        .hero-stats {
            display: flex;
            gap: 12px;
        }

        .stat-badge {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 10px 16px;
            border-radius: 12px;
            text-align: center;
            min-width: 90px;
        }
        .stat-badge .num {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--gold-500);
        }
        .stat-badge .lbl {
            font-size: 10px;
            font-weight: 700;
            color: #d1fae5;
            text-transform: uppercase;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
        }

        .card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow-card);
        }

        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: var(--forest-950);
            margin-bottom: 6px;
        }
        .card-desc {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            font-family: inherit;
            color: var(--text-main);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--forest-700);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 11px 16px;
            background: linear-gradient(135deg, var(--forest-800) 0%, var(--forest-900) 100%);
            color: #ffffff;
            border: none;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            background: var(--forest-700);
            transform: translateY(-1px);
        }

        @media (max-width: 860px) {
            .profile-hero { flex-direction: column; align-items: flex-start; }
            .profile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Unified Admin Sidebar -->
    <?php include 'menu.php'; ?>

    <div class="main-wrapper">
        <header class="top-navbar">
            <div class="nav-title">
                <h1>Profil Administrator</h1>
                <p>Pengaturan akun, kontak operasional, dan keamanan sistem DANATRAIL</p>
            </div>
            <div>
                <a href="dashboard.php" style="font-size: 12.5px; font-weight: 700; color: var(--forest-800); text-decoration: none;">
                    ← Kembali ke Dashboard
                </a>
            </div>
        </header>

        <div class="content-area">
            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert alert-success">✓ <?php echo htmlspecialchars($pesan_sukses); ?></div>
            <?php endif; ?>
            <?php if (!empty($pesan_error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($pesan_error); ?></div>
            <?php endif; ?>

            <!-- Hero -->
            <div class="profile-hero">
                <div class="hero-info">
                    <div class="hero-avatar">
                        <?php if (!empty($admin['avatar']) && file_exists('../uploads/avatars/' . $admin['avatar'])): ?>
                            <img src="../uploads/avatars/<?php echo htmlspecialchars($admin['avatar']); ?>" alt="Admin Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($admin['name'], 0, 2)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="hero-text">
                        <h2><?php echo htmlspecialchars($admin['name']); ?></h2>
                        <p>Super Administrator DANATRAIL Basecamp • <?php echo htmlspecialchars($admin['email']); ?></p>
                    </div>
                </div>

                <div class="hero-stats">
                    <div class="stat-badge">
                        <div class="num"><?php echo $total_trans; ?></div>
                        <div class="lbl">Transaksi</div>
                    </div>
                    <div class="stat-badge">
                        <div class="num"><?php echo $total_prods; ?></div>
                        <div class="lbl">Inventaris</div>
                    </div>
                    <div class="stat-badge">
                        <div class="num"><?php echo $total_users; ?></div>
                        <div class="lbl">Pelanggan</div>
                    </div>
                </div>
            </div>

            <!-- Forms -->
            <div class="profile-grid">
                <div class="card">
                    <h3 class="card-title">Data Akun Admin</h3>
                    <p class="card-desc">Informasi kontak dan nama identitas login admin.</p>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="form-group">
                            <label for="name">Nama Administrator</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Login</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">No. WhatsApp / Kontak Petugas</label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="081234567890" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="avatar">Foto Profil Admin (Opsional)</label>
                            <input type="file" id="avatar" name="avatar" class="form-control" accept="image/*">
                        </div>

                        <button type="submit" class="btn-submit">
                            Simpan Profil Admin
                        </button>
                    </form>
                </div>

                <div class="card">
                    <h3 class="card-title">Ganti Password Admin</h3>
                    <p class="card-desc">Pastikan menggunakan password yang rumit untuk melindungi data transaksi.</p>

                    <form action="" method="POST">
                        <input type="hidden" name="change_password" value="1">

                        <div class="form-group">
                            <label for="old_password">Password Admin Saat Ini</label>
                            <input type="password" id="old_password" name="old_password" class="form-control" required placeholder="••••••••">
                        </div>

                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Ulangi Password Baru</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6" placeholder="••••••••">
                        </div>

                        <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);">
                            Perbarui Password Admin
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>
</html>
