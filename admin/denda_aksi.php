<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek akses admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== "admin") {
    header("location:../login.php?pesan=" . urlencode("Akses ditolak! Anda bukan Administrator."));
    exit;
}

require_once '../config/config.php';
/** @var mysqli $conn */

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$admin_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$admin_name = $_SESSION['nama'] ?? 'Admin';

if ($action === 'save_settings') {
    $fine_enabled = isset($_POST['fine_enabled']) && $_POST['fine_enabled'] == '1' ? '1' : '0';
    $fine_method = in_array($_POST['fine_method'] ?? '', ['fixed_daily', 'percentage_daily']) ? $_POST['fine_method'] : 'fixed_daily';
    $fine_rate = (float)($_POST['fine_rate'] ?? 20000);
    $fine_grace_period = max(0, (int)($_POST['fine_grace_period'] ?? 0));
    $fine_max_amount = max(0, (float)($_POST['fine_max_amount'] ?? 0));

    set_system_setting('fine_enabled', $fine_enabled);
    set_system_setting('fine_method', $fine_method);
    set_system_setting('fine_rate', (string)$fine_rate);
    set_system_setting('fine_grace_period', (string)$fine_grace_period);
    set_system_setting('fine_max_amount', (string)$fine_max_amount);

    // Sync all active rentals with updated settings
    sync_rental_overdue_status($conn);

    header("location:denda.php?pesan=" . urlencode("Pengaturan denda keterlambatan rental berhasil diperbarui.") . "&type=success");
    exit;
}

if ($action === 'update_fine_status') {
    $rental_id = (int)($_POST['rental_id'] ?? 0);
    $status_denda = $_POST['status_denda'] ?? '';
    $payment_method = trim($_POST['payment_method'] ?? 'Tunai di Basecamp');
    $notes = trim($_POST['notes'] ?? '');

    $allowed = ['Belum Dibayar', 'Sudah Dibayar', 'Dibebaskan Admin'];
    if (!in_array($status_denda, $allowed)) {
        header("location:denda.php?pesan=" . urlencode("Status denda tidak valid.") . "&type=error");
        exit;
    }

    if ($rental_id <= 0) {
        header("location:denda.php?pesan=" . urlencode("ID Rental tidak valid.") . "&type=error");
        exit;
    }

    // Ambil data rental saat ini
    $q_rent = mysqli_query($conn, "SELECT * FROM rentals WHERE id = $rental_id LIMIT 1");
    $rental = $q_rent ? mysqli_fetch_assoc($q_rent) : null;

    if (!$rental) {
        header("location:denda.php?pesan=" . urlencode("Data rental tidak ditemukan.") . "&type=error");
        exit;
    }

    // Hitung denda terkini
    $fine_calc = calculate_rental_fine($rental);

    // Cek apakah sudah ada record di rental_fines
    $q_chk = mysqli_query($conn, "SELECT id FROM rental_fines WHERE rental_id = $rental_id LIMIT 1");
    $has_record = ($q_chk && mysqli_num_rows($q_chk) > 0);

    $paid_at = ($status_denda === 'Sudah Dibayar') ? date('Y-m-d H:i:s') : null;
    $full_notes = $notes ? "$notes (Dikonfirmasi oleh: $admin_name)" : "Dikonfirmasi oleh: $admin_name";

    mysqli_begin_transaction($conn);
    try {
        if ($has_record) {
            $stmt = mysqli_prepare($conn, "UPDATE rental_fines SET 
                payment_status = ?,
                payment_method = ?,
                paid_at = ?,
                confirmed_by = ?,
                notes = ?,
                updated_at = NOW()
                WHERE rental_id = ?");
            mysqli_stmt_bind_param($stmt, "sssssi", $status_denda, $payment_method, $paid_at, $admin_name, $full_notes, $rental_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO rental_fines (
                rental_id, user_id, late_days, fine_type, fine_rate, fine_amount,
                payment_status, payment_method, paid_at, confirmed_by, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            $late_days = (int)($fine_calc['late_days'] ?? 0);
            $fine_type = $fine_calc['fine_method'] ?? $fine_calc['fine_type'] ?? 'fixed_daily';
            $fine_rate = (float)($fine_calc['fine_rate'] ?? 20000);
            $fine_amount = (float)($fine_calc['fine_amount'] ?? 0);
            $user_id = (int)$rental['user_id'];

            mysqli_stmt_bind_param(
                $stmt,
                "iiisddsssss",
                $rental_id,
                $user_id,
                $late_days,
                $fine_type,
                $fine_rate,
                $fine_amount,
                $status_denda,
                $payment_method,
                $paid_at,
                $admin_name,
                $full_notes
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Update di rentals
        $stmt_r = mysqli_prepare($conn, "UPDATE rentals SET fine_payment_status = ?, fine_amount = ? WHERE id = ?");
        $fine_amt_save = (float)($fine_calc['fine_amount'] ?? 0);
        mysqli_stmt_bind_param($stmt_r, "sdi", $status_denda, $fine_amt_save, $rental_id);
        mysqli_stmt_execute($stmt_r);
        mysqli_stmt_close($stmt_r);

        mysqli_commit($conn);
        header("location:denda.php?pesan=" . urlencode("Status denda Rental #$rental_id berhasil diubah menjadi '$status_denda'.") . "&type=success");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("location:denda.php?pesan=" . urlencode("Gagal memperbarui status denda: " . $e->getMessage()) . "&type=error");
        exit;
    }
}

if ($action === 'sync') {
    sync_rental_overdue_status($conn);
    header("location:denda.php?pesan=" . urlencode("Sinkronisasi status keterlambatan rental selesai dilakukan.") . "&type=success");
    exit;
}

header("location:denda.php");
exit;
