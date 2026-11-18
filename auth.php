<?php
session_start();
include 'config/config.php';
/** @var mysqli $conn */

// Jika tombol login ditekan
if (isset($_POST['login'])) {
    $username_email = isset($_POST['username_email']) ? trim($_POST['username_email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');
    $username_email = mysqli_real_escape_string($conn, $username_email);
    $password       = $_POST['password'];

    // Cari user berdasarkan email ATAU nama (username)
    $query = mysqli_query($conn, "SELECT * FROM users WHERE email='$username_email' OR name='$username_email'");
    
    if ($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);

        // Verifikasi password hash atau fallback plain text (untuk akun lama)
        $password_cocok = false;
        if (password_verify($password, $data['password'])) {
            $password_cocok = true;
        } else if ($password === $data['password']) {
            // Jika akun lama masih plain text, cocokkan dan langsung konversi ke hash
            $password_cocok = true;
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $user_id = $data['id'];
            mysqli_query($conn, "UPDATE users SET password='$new_hash' WHERE id='$user_id'");
        }

        if ($password_cocok) {
            // Simpan data ke session login
            $_SESSION['user_id'] = $data['id'];
            $_SESSION['nama']    = $data['name'];
            $_SESSION['email']   = $data['email'];
            $_SESSION['role']    = $data['role'];

            // Cek role
            if ($data['role'] == "admin") {
                header("location:admin/dashboard.php");
                exit;
            } else if ($data['role'] == "user") {
                header("location:user/dashboard.php");
                exit;
            }
        } else {
            header("location:login.php?pesan=Email/Username atau Password salah!");
            exit;
        }
    } else {
        header("location:login.php?pesan=Email/Username atau Password salah!");
        exit;
    }
}

// Proses Registrasi User Baru
if (isset($_POST['register'])) {
    $name                = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email               = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password            = $_POST['password'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    // Validasi apakah password dan konfirmasi cocok
    if ($password !== $konfirmasi_password) {
        header("location:register.php?pesan=Password dan Konfirmasi Password tidak cocok!");
        exit;
    }

    // Cek apakah email sudah terdaftar sebelumnya
    $cek_email = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
    if (mysqli_num_rows($cek_email) > 0) {
        header("location:register.php?pesan=Email sudah terdaftar, gunakan email lain!");
        exit;
    }

    // Enkripsi / Hash password secara aman
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Simpan data user baru dengan role default 'user'
    $insert = mysqli_query($conn, "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$password_hash', 'user')");

    if ($insert) {
        header("location:login.php?sukses=Registrasi berhasil! Silakan masuk dengan akun baru Anda.");
        exit;
    } else {
        header("location:register.php?pesan=Gagal mendaftar: " . mysqli_error($conn));
        exit;
    }
}

// Proses Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("location:login.php");
    exit;
}
?>
