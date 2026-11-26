    <?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Cek autentikasi user
if (!isset($_SESSION['role'])) {
    header("location:../login.php?pesan=Silakan login terlebih dahulu!");
    exit;
}

include '../config/config.php';
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

// Handle Form Submission: Update Profil
$pesan_sukses = $_GET['sukses'] ?? '';
$pesan_error  = $_GET['pesan'] ?? '';
$redirect_to  = $_GET['redirect_to'] ?? ($_POST['redirect_to'] ?? '');
$wajib_isi    = isset($_GET['wajib_isi']) || isset($_POST['wajib_isi']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name    = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
        $email   = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
        $phone   = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
        $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));

        if (empty($name) || empty($email)) {
            $pesan_error = "Nama dan Email tidak boleh kosong!";
        } elseif (empty($phone) || empty($address)) {
            $pesan_error = "Nomor WhatsApp dan Alamat lengkap wajib diisi!";
        } else {
            // Cek jika email sudah dipakai user lain
            $cek = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' AND id != '$user_id'");
            if ($cek && mysqli_num_rows($cek) > 0) {
                $pesan_error = "Email sudah digunakan oleh akun lain!";
            } else {
                // Upload Avatar jika ada
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
                        $new_avatar = 'user_' . $user_id . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($tmp, $up_dir . $new_avatar)) {
                            $avatar_sql = ", avatar='$new_avatar'";
                        }
                    }
                }

                $update = mysqli_query($conn, "UPDATE users SET name='$name', email='$email', phone='$phone', address='$address' $avatar_sql WHERE id='$user_id'");
                if ($update) {
                    $_SESSION['nama'] = $name;
                    $_SESSION['email'] = $email;
                    
                    // Jika ada redirect kembali ke halaman pemesanan sebelumnya
                    if (!empty($redirect_to)) {
                        $safe_redirect = ltrim($redirect_to, '/');
                        if (strpos($safe_redirect, 'dashboard.php') === 0 || strpos($safe_redirect, 'detail_produk.php') === 0 || strpos($safe_redirect, 'about.php') === 0 || strpos($safe_redirect, 'riwayat.php') === 0) {
                            $sep = (strpos($safe_redirect, '?') !== false) ? '&' : '?';
                            header("Location: " . $safe_redirect . $sep . "sukses=" . urlencode("Profil Anda berhasil dilengkapi! Silakan lanjutkan pemesanan."));
                            exit;
                        }
                    }
                    
                    $pesan_sukses = "Profil akun Anda berhasil diperbarui dan disimpan!";
                } else {
                    $pesan_error = "Gagal memperbarui profil: " . mysqli_error($conn);
                }
            }
        }
    }

    // Handle Ganti Password
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
            // Ambil hash password saat ini
            $q_user = mysqli_query($conn, "SELECT password FROM users WHERE id='$user_id'");
            $u_data = mysqli_fetch_assoc($q_user);

            if (password_verify($old_pass, $u_data['password']) || $old_pass === $u_data['password']) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $up_pass = mysqli_query($conn, "UPDATE users SET password='$new_hash' WHERE id='$user_id'");
                if ($up_pass) {
                    $pesan_sukses = "Password berhasil diubah!";
                } else {
                    $pesan_error = "Gagal memperbarui password: " . mysqli_error($conn);
                }
            } else {
                $pesan_error = "Password saat ini tidak sesuai!";
            }
        }
    }
}

// Ambil data user terkini
$q_user = mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'");
$user = mysqli_fetch_assoc($q_user);

// Statistik User
$count_rentals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rentals WHERE user_id='$user_id'"))['c'] ?? 0;
$count_active  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rentals WHERE user_id='$user_id' AND status='Disewa'"))['c'] ?? 0;
$count_done    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM rentals WHERE user_id='$user_id' AND status='Selesai'"))['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil & Pengaturan Akun — DANATRAIL</title>
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
            --text-subtle: #92a098;
            --radius-xl: 16px;
            --radius-lg: 12px;
            --radius-md: 8px;
            --radius-sm: 6px;
            --radius-pill: 9999px;
            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
            --shadow-card: 0 2px 10px rgba(7, 53, 34, 0.05);
            --shadow-hover: 0 6px 20px rgba(7, 53, 34, 0.09);
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
            min-height: 100vh;
            line-height: 1.5;
        }

        .app-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            min-width: 0;
        }

        .main-header {
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

        .header-title-group h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: var(--forest-950);
        }
        .header-title-group p {
            font-size: 12px;
            color: var(--text-muted);
        }

        .mobile-nav-toggle {
            display: none;
            background: none;
            border: 1px solid var(--border-color);
            padding: 8px;
            border-radius: 8px;
            cursor: pointer;
        }

        .content-container {
            padding: 24px 28px;
            max-width: 1000px;
            width: 100%;
        }

        /* Alert notifications */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: var(--emerald-50);
            color: var(--emerald-600);
            border: 1px solid var(--emerald-border);
        }
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Profile Banner Hero */
        .profile-hero {
            background: linear-gradient(135deg, var(--forest-950) 0%, var(--forest-900) 60%, var(--forest-800) 100%);
            border-radius: var(--radius-xl);
            padding: 28px;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(7, 53, 34, 0.15);
            position: relative;
            overflow: hidden;
        }

        .hero-user-info {
            display: flex;
            align-items: center;
            gap: 18px;
            position: relative;
            z-index: 2;
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
        .hero-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-text h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
        }
        .hero-text p {
            font-size: 13px;
            color: #a7f3d0;
            margin-top: 2px;
        }

        .hero-stats {
            display: flex;
            gap: 14px;
            position: relative;
            z-index: 2;
        }

        .stat-pill {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            padding: 10px 16px;
            border-radius: 12px;
            text-align: center;
            min-width: 90px;
        }
        .stat-pill .num {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--gold-500);
        }
        .stat-pill .lbl {
            font-size: 10.5px;
            font-weight: 600;
            color: #d1fae5;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Form Grid */
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
            display: flex;
            align-items: center;
            gap: 8px;
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
            background: #ffffff;
            transition: border-color 0.2s;
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
            box-shadow: 0 4px 14px rgba(7, 53, 34, 0.2);
        }

        @media (max-width: 860px) {
            .mobile-nav-toggle { display: block; }
            .profile-hero { flex-direction: column; align-items: flex-start; }
            .profile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Unified User Sidebar -->
    <?php include 'menu.php'; ?>

    <main class="app-main">
        <!-- Top Navbar -->
        <header class="main-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button type="button" class="mobile-nav-toggle" onclick="toggleMobileSidebar()" aria-label="Toggle Menu">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="header-title-group">
                    <h1>Pengaturan Profil & Akun</h1>
                    <p>Kelola identitas, nomor kontak WhatsApp, alamat, dan keamanan akun</p>
                </div>
            </div>
            <div>
                <a href="dashboard.php" style="font-size: 12.5px; font-weight: 700; color: var(--forest-800); text-decoration: none;">
                    ← Kembali ke Katalog
                </a>
            </div>
        </header>

        <div class="content-container">
            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert alert-success">
                    <span>✓</span> <?php echo htmlspecialchars($pesan_sukses); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($pesan_error)): ?>
                <div class="alert alert-error">
                    <span>!</span> <?php echo htmlspecialchars($pesan_error); ?>
                </div>
            <?php endif; ?>

            <?php if ($wajib_isi || (empty($user['phone']) || empty($user['address']))): ?>
                <div class="alert" style="background: #fffbeb; color: #92400e; border: 1.5px solid #fde68a; border-radius: 12px; padding: 16px 20px; display: flex; align-items: flex-start; gap: 14px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(245, 184, 0, 0.12);">
                    <div style="flex-shrink: 0; margin-top: 2px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color: #b45309;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <strong style="color: #78350f; font-size: 14.5px; display: block; margin-bottom: 4px; font-family: 'Outfit', sans-serif;">Pemberitahuan: Lengkapi Nomor WhatsApp & Alamat Domisili</strong>
                        <div style="font-size: 13px; color: #92400e; line-height: 1.5;">
                            Sebelum membuat pesanan atau menyewa perlengkapan outdoor di <strong>DANATRAIL</strong>, Anda diwajibkan melengkapi <strong>Nomor WhatsApp</strong> dan <strong>Alamat Lengkap</strong> untuk keperluan verifikasi dan serah terima alat.
                        </div>
                        <?php if (!empty($redirect_to)): ?>
                            <div style="margin-top: 8px; font-size: 12px; color: #b45309; font-weight: 600;">
                                Setelah menekan tombol "Simpan Perubahan Profil", Anda akan otomatis diarahkan kembali ke halaman pemesanan sebelumnya.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Hero Banner -->
            <div class="profile-hero">
                <div class="hero-user-info">
                    <div class="hero-avatar">
                        <?php if (!empty($user['avatar']) && file_exists('../uploads/avatars/' . $user['avatar'])): ?>
                            <img src="../uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="hero-text">
                        <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p><?php echo htmlspecialchars($user['email']); ?> • Member Terdaftar sejak <?php echo date('d M Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>

                <div class="hero-stats">
                    <div class="stat-pill">
                        <div class="num"><?php echo $count_rentals; ?></div>
                        <div class="lbl">Total Sewa</div>
                    </div>
                    <div class="stat-pill">
                        <div class="num"><?php echo $count_active; ?></div>
                        <div class="lbl">Sedang Disewa</div>
                    </div>
                    <div class="stat-pill">
                        <div class="num"><?php echo $count_done; ?></div>
                        <div class="lbl">Selesai</div>
                    </div>
                </div>
            </div>

            <!-- Profile & Security Grid -->
            <div class="profile-grid">
                <!-- Form Biodata -->
                <div class="card" id="biodataSection" style="<?php echo ($wajib_isi && (empty($user['phone']) || empty($user['address']))) ? 'border: 2px solid var(--forest-600); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);' : ''; ?>">
                    <h3 class="card-title">
                        <span>Biodata & Kontak Lengkap</span>
                    </h3>
                    <p class="card-desc">Pastikan nomor WhatsApp dan alamat akurat untuk kemudahan verifikasi jaminan.</p>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        <?php if (!empty($redirect_to)): ?>
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to); ?>">
                        <?php endif; ?>
                        <?php if ($wajib_isi): ?>
                            <input type="hidden" name="wajib_isi" value="1">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Nama Lengkap *</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Alamat Email *</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">No. WhatsApp / Handphone * <span style="color: #dc2626; font-size: 11px;">(Wajib untuk Pemesanan)</span></label>
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder="Contoh: 081234567890" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required style="<?php echo empty($user['phone']) ? 'border-color: #f59e0b; background: #fffdf5;' : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label for="address">Alamat Lengkap Domisili * <span style="color: #dc2626; font-size: 11px;">(Wajib untuk Pemesanan)</span></label>
                            <textarea id="address" name="address" rows="3" class="form-control" placeholder="Alamat lengkap tempat tinggal / domisili saat ini (Contoh: Jl. Danatrail, Sidoarjo)" required style="<?php echo empty($user['address']) ? 'border-color: #f59e0b; background: #fffdf5;' : ''; ?>"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="avatar">Foto Profil (Opsional - Max 3MB)</label>
                            <input type="file" id="avatar" name="avatar" class="form-control" accept="image/*">
                        </div>

                        <button type="submit" class="btn-submit">
                            <span>Simpan Perubahan Profil</span>
                            <?php if (!empty($redirect_to)): ?>
                                <span>& Lanjutkan Pemesanan →</span>
                            <?php endif; ?>
                        </button>
                    </form>
                </div>

                <!-- Form Ganti Password -->
                <div class="card">
                    <h3 class="card-title">
                        <span>Keamanan & Ganti Password</span>
                    </h3>
                    <p class="card-desc">Gunakan password yang kuat dengan minimal 6 karakter kombinasi.</p>

                    <form action="" method="POST">
                        <input type="hidden" name="change_password" value="1">

                        <div class="form-group">
                            <label for="old_password">Password Saat Ini</label>
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
                            Perbarui Password
                        </button>
                    </form>

                    <div style="margin-top: 24px; padding: 14px; background: #f8fafc; border-radius: 8px; border: 1px solid var(--border-color); font-size: 11.5px; color: var(--text-muted);">
                        <strong style="color: var(--forest-900);">Tips Keamanan:</strong> Jangan berikan password atau kode OTP kepada siapa pun, termasuk pihak yang mengatasnamakan DANATRAIL.
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const wajibIsi = <?php echo ($wajib_isi || empty($user['phone']) || empty($user['address'])) ? 'true' : 'false'; ?>;
            if (wajibIsi) {
                const phoneInput = document.getElementById('phone');
                const addrInput = document.getElementById('address');
                if (phoneInput && !phoneInput.value.trim()) {
                    phoneInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    phoneInput.focus();
                } else if (addrInput && !addrInput.value.trim()) {
                    addrInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    addrInput.focus();
                }
            }
        });
    </script>
    <?php if (isset($_GET["desktop_1024"])): ?><style>html,body{width:1440px!important;max-width:1440px!important;height:1024px!important;max-height:1024px!important;min-height:1024px!important;overflow:hidden!important;}</style><?php endif; ?>
    <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
</body>
</html>
