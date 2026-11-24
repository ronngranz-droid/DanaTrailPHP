<?php
/**
 * DANATRAIL Discount & Rental Fine Helper Library
 * Enterprise-grade validation, calculation, and safety utilities.
 */

if (!function_exists('get_system_setting')) {
    function get_system_setting($key, $default = null) {
        global $conn;
        $k_esc = mysqli_real_escape_string($conn, $key);
        $res = mysqli_query($conn, "SELECT setting_value FROM system_settings WHERE setting_key='$k_esc' LIMIT 1");
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
         return $row['setting_value'];
        }
        return $default;
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting($key, $value) {
        global $conn;
        $k_esc = mysqli_real_escape_string($conn, $key);
        $v_esc = mysqli_real_escape_string($conn, $value);
        return mysqli_query($conn, "INSERT INTO system_settings (setting_key, setting_value) 
                                    VALUES ('$k_esc', '$v_esc') 
                                    ON DUPLICATE KEY UPDATE setting_value='$v_esc'");
    }
}

if (!function_exists('format_rupiah')) {
    function format_rupiah($amount) {
        return 'Rp' . number_format((float)$amount, 0, ',', '.');
    }
}

if (!function_exists('get_discounted_price')) {
    function get_discounted_price($price, $discount_percent) {
        $price = (float)$price;
        $disc  = (float)$discount_percent;
        if ($disc <= 0) return $price;
        $potongan = $price * ($disc / 100);
        return max(0, $price - $potongan);
    }
}

/**
 * Calculate rental fine with strict grace period and cap logic
 */
if (!function_exists('calculate_rental_fine')) {
    function calculate_rental_fine($rental_data, $ref_or_return_date = null, $actual_return_date = null, $total_price = 0, $status = 'Sedang Disewa') {
        global $conn;

        $fine_enabled = get_system_setting('fine_enabled', '1');
        $fine_method = get_system_setting('fine_method', 'fixed_daily');
        $fine_rate = (float)get_system_setting('fine_rate', '20000');
        $fine_grace_period = (int)get_system_setting('fine_grace_period', '1');
        $fine_max_amount = (float)get_system_setting('fine_max_amount', '0');

        if (is_array($rental_data)) {
            $return_date_str = $rental_data['return_date'] ?? date('Y-m-d');
            $actual_return_str = $rental_data['actual_return_date'] ?? null;
            $status = $rental_data['status'] ?? $rental_data['return_status'] ?? 'Sedang Disewa';
            $base_price = (float)($rental_data['subtotal_price'] ?? $rental_data['total_price'] ?? 0);
            $reference_date = $ref_or_return_date;
        } else {
            // Called with individual arguments: ($rent_date, $return_date, $actual_return_date, $total_price, $status)
            $return_date_str = $ref_or_return_date ?: date('Y-m-d');
            $actual_return_str = $actual_return_date ?: null;
            $base_price = (float)$total_price;
            $reference_date = null;
        }

        // Tentukan tanggal perbandingan akhir
        // Jika sudah dikembalikan, gunakan actual_return_date
        // Jika belum, gunakan hari ini (atau reference_date yang ditentukan)
        if (!empty($actual_return_str) && $actual_return_str !== '0000-00-00') {
            $compare_date_str = $actual_return_str;
            $is_already_returned = true;
        } else {
            $compare_date_str = $reference_date ?: date('Y-m-d');
            $is_already_returned = in_array($status, ['Selesai', 'Dikembalikan']);
        }

        $time_return = strtotime($return_date_str);
        $time_compare = strtotime($compare_date_str);
        $tolerance_timestamp = strtotime("+$fine_grace_period days", $time_return);
        $tolerance_date_str = date('Y-m-d', $tolerance_timestamp);

        // Keterlambatan pengembalian HANYA berlaku untuk barang yang sudah disetujui/disewa (Disewa, Terlambat, Selesai)
        // Jika masih 'Menunggu Konfirmasi' atau 'Dibatalkan', barang belum diserahkan ke user sehingga bebas denda
        if (in_array($status, ['Menunggu Konfirmasi', 'Dibatalkan'])) {
            return [
                'is_late' => false,
                'late_days' => 0,
                'fine_enabled' => ($fine_enabled == '1'),
                'fine_type' => $fine_method,
                'fine_method' => $fine_method,
                'fine_rate' => $fine_rate,
                'fine_amount' => 0.00,
                'calculated_status' => $status,
                'grace_period' => $fine_grace_period,
                'tolerance_date' => $tolerance_date_str,
                'is_already_returned' => false
            ];
        }

        $late_days = 0;
        $is_late = false;

        // Cek keterlambatan terhadap tanggal pengembalian seharusnya
        if ($time_compare > $time_return) {
            $is_late = true;
            if ($time_compare > $tolerance_timestamp) {
                // Hari keterlambatan dihitung dari setelah masa toleransi
                $diff_seconds = $time_compare - $tolerance_timestamp;
                $late_days = (int)ceil($diff_seconds / (60 * 60 * 24));
                if ($late_days < 0) {
                    $late_days = 0;
                }
            } else {
                // Masih dalam masa toleransi grace period
                $late_days = 0;
            }
        }

        // Jika denda dinonaktifkan oleh admin
        if ($fine_enabled != '1') {
            $fine_amount = 0.00;
        } else {
            if ($late_days > 0) {
                if ($fine_method === 'percentage_daily') {
                    // Persentase dari harga rental total
                    $base_price = (float)($rental_data['subtotal_price'] ?? $rental_data['total_price'] ?? 0);
                    $daily_fine = $base_price * ($fine_rate / 100);
                    $fine_amount = $daily_fine * $late_days;
                } else {
                    // Denda flat per hari
                    $fine_amount = $fine_rate * $late_days;
                }

                // Cek maksimal denda jika diatur
                if ($fine_max_amount > 0 && $fine_amount > $fine_max_amount) {
                    $fine_amount = $fine_max_amount;
                }
            } else {
                $fine_amount = 0.00;
            }
        }

        // Tentukan status pengembalian
        $calculated_return_status = 'Sedang Disewa';
        if ($status === 'Dibatalkan') {
            $calculated_return_status = 'Dibatalkan';
        } elseif ($is_already_returned) {
            $calculated_return_status = 'Selesai';
        } elseif ($is_late) {
            $calculated_return_status = 'Terlambat';
        } else {
            $calculated_return_status = 'Sedang Disewa';
        }

        return [
            'is_late' => $is_late,
            'late_days' => $late_days,
            'fine_enabled' => ($fine_enabled == '1'),
            'fine_type' => $fine_method,
            'fine_method' => $fine_method,
            'fine_rate' => $fine_rate,
            'fine_amount' => round($fine_amount, 2),
            'calculated_status' => $calculated_return_status,
            'grace_period' => $fine_grace_period,
            'tolerance_date' => $tolerance_date_str,
            'is_already_returned' => $is_already_returned
        ];
    }
}

/**
 * Sinkronisasi otomatis status rental terlambat di database
 */
if (!function_exists('sync_rental_overdue_status')) {
    function sync_rental_overdue_status() {
        global $conn;
        $today = date('Y-m-d');
        // Update rental yang status 'Disewa' dan return_date < today menjadi Terlambat
        mysqli_query($conn, "UPDATE rentals 
                             SET return_status='Terlambat' 
                             WHERE status='Disewa' 
                               AND return_date < '$today' 
                               AND (actual_return_date IS NULL OR actual_return_date = '0000-00-00')");
    }
}

/**
 * Validasi dan hitung diskon (Voucher dan/atau Diskon Otomatis)
 */
if (!function_exists('validate_and_calculate_discount')) {
    function validate_and_calculate_discount($params) {
        global $conn;

        $user_id         = (int)($params['user_id'] ?? 0);
        $type            = $params['type'] ?? 'both'; // 'rental' or 'sale'
        $subtotal        = (float)($params['subtotal'] ?? 0);
        $rental_days     = (int)($params['rental_days'] ?? 1);
        $product_ids     = (array)($params['product_ids'] ?? []);
        $category_names  = (array)($params['category_names'] ?? []);
        $voucher_code    = trim($params['voucher_code'] ?? '');

        $now_str = date('Y-m-d H:i:s');
        $stackable = (get_system_setting('discount_stackable', '0') == '1');

        $auto_discount_applied = null;
        $auto_discount_amount = 0.00;

        $voucher_discount_applied = null;
        $voucher_discount_amount = 0.00;
        $voucher_error = null;

        // 1. CEK SEMUA DISKON OTOMATIS YANG BERLAKU
        $q_auto = mysqli_query($conn, "SELECT * FROM discounts 
                                       WHERE is_active = 1 
                                         AND (start_date IS NULL OR start_date <= '$now_str') 
                                         AND (end_date IS NULL OR end_date >= '$now_str') 
                                         AND (applicable_type = '$type' OR applicable_type = 'both')
                                       ORDER BY discount_value DESC");

        $eligible_discounts = [];

        if ($q_auto && mysqli_num_rows($q_auto) > 0) {
            while ($rule = mysqli_fetch_assoc($q_auto)) {
                // Cek syarat minimal transaksi
                if ((float)$rule['minimum_transaction'] > 0 && $subtotal < (float)$rule['minimum_transaction']) {
                    continue;
                }

                // Cek syarat minimal hari sewa (untuk rental)
                if ($type === 'rental' && (int)$rule['min_rental_days'] > 0 && $rental_days < (int)$rule['min_rental_days']) {
                    continue;
                }

                // Cek target produk atau kategori jika ada
                if ($rule['target_type'] === 'product' && !empty($rule['target_id'])) {
                    $allowed_pids = array_map('trim', explode(',', $rule['target_id']));
                    $matches = array_intersect($product_ids, $allowed_pids);
                    if (empty($matches)) continue;
                } elseif ($rule['target_type'] === 'category' && !empty($rule['target_id'])) {
                    $allowed_cats = array_map('trim', explode(',', strtolower($rule['target_id'])));
                    $current_cats = array_map('strtolower', $category_names);
                    $matches = array_intersect($current_cats, $allowed_cats);
                    if (empty($matches)) continue;
                }

                // Validasi batas pemakaian total (usage_limit)
                if ((int)$rule['usage_limit'] > 0) {
                    $q_count = mysqli_query($conn, "SELECT COUNT(*) FROM discount_usages WHERE discount_id = '{$rule['id']}'");
                    $total_used = $q_count ? (int)mysqli_fetch_row($q_count)[0] : 0;
                    if ($total_used >= (int)$rule['usage_limit']) {
                        continue;
                    }
                }

                // Validasi batas pemakaian per pengguna (usage_per_user)
                if ($user_id > 0 && (int)$rule['usage_per_user'] > 0) {
                    $q_user_count = mysqli_query($conn, "SELECT COUNT(*) FROM discount_usages WHERE discount_id = '{$rule['id']}' AND user_id = '$user_id'");
                    $user_used = $q_user_count ? (int)mysqli_fetch_row($q_user_count)[0] : 0;
                    if ($user_used >= (int)$rule['usage_per_user']) {
                        continue;
                    }
                }

                // Hitung potongan
                $potongan = 0.00;
                if ($rule['discount_type'] === 'percentage') {
                    $potongan = $subtotal * ((float)$rule['discount_value'] / 100);
                    if ((float)$rule['maximum_discount'] > 0 && $potongan > (float)$rule['maximum_discount']) {
                        $potongan = (float)$rule['maximum_discount'];
                    }
                } else {
                    $potongan = min($subtotal, (float)$rule['discount_value']);
                }

                if ($potongan > 0) {
                    $eligible_discounts[] = [
                        'rule' => $rule,
                        'amount' => $potongan
                    ];
                }
            }
        }

        // 2. TENTUKAN DISKON YANG DIGUNAKAN (PILIH TERBESAR ATAU TUMPANG TINDIH)
        $final_discount_amount = 0.00;
        $primary_discount_id = null;
        $primary_discount_code = null;
        $discount_name = '';
        $message = '';

        if (!empty($eligible_discounts)) {
            if ($stackable && count($eligible_discounts) > 1) {
                // Jika boleh digabung: akumulasikan semua diskon yang memenuhi syarat
                $total_stacked = 0;
                $names = [];
                $first_id = null;
                foreach ($eligible_discounts as $ed) {
                    $total_stacked += $ed['amount'];
                    $names[] = $ed['rule']['name'];
                    if (!$first_id) $first_id = $ed['rule']['id'];
                }
                $final_discount_amount = min($subtotal, $total_stacked);
                $primary_discount_id = $first_id;
                $discount_name = implode(' + ', array_slice($names, 0, 2));
                $message = "Diskon otomatis diterapkan! Anda menghemat " . format_rupiah($final_discount_amount) . " (" . $discount_name . ").";
            } else {
                // Ambil diskon terbaik / potongan terbesar yang paling menguntungkan pelanggan
                usort($eligible_discounts, function($a, $b) {
                    return $b['amount'] <=> $a['amount'];
                });
                $best = $eligible_discounts[0];
                $final_discount_amount = $best['amount'];
                $primary_discount_id = $best['rule']['id'];
                $discount_name = $best['rule']['name'];
                $message = "Diskon otomatis '" . $discount_name . "' diterapkan! Hemat " . format_rupiah($final_discount_amount) . ".";
            }
        }

        $total_after_discount = max(0, $subtotal - $final_discount_amount);

        return [
            'valid' => ($final_discount_amount > 0),
            'has_voucher' => false,
            'voucher_error' => null,
            'discount_id' => $primary_discount_id,
            'discount_code' => $primary_discount_code,
            'discount_name' => $discount_name,
            'discount_amount' => round($final_discount_amount, 2),
            'subtotal' => round($subtotal, 2),
            'total_after_discount' => round($total_after_discount, 2),
            'message' => $message,
            'auto_rule' => (!empty($eligible_discounts) ? $eligible_discounts[0]['rule'] : null),
            'voucher_rule' => null
        ];
    }
}

/**
 * Catat riwayat penggunaan diskon ke tabel discount_usages
 */
if (!function_exists('record_discount_usage')) {
    function record_discount_usage($discount_id, $user_id, $transaction_type, $transaction_id, $amount) {
        global $conn;
        if (!$discount_id || $discount_id <= 0 || $amount <= 0) return false;

        $did = (int)$discount_id;
        $uid = (int)$user_id;
        $ttype = mysqli_real_escape_string($conn, $transaction_type);
        $tid = (int)$transaction_id;
        $damt = (float)$amount;

        return mysqli_query($conn, "INSERT INTO discount_usages (discount_id, user_id, transaction_type, transaction_id, discount_amount)
                                    VALUES ('$did', '$uid', '$ttype', '$tid', '$damt')");
    }
}

if (!function_exists('revert_discount_usage')) {
    /**
     * Mengembalikan kuota penggunaan diskon saat pesanan atau sewa dibatalkan/dihapus
     * @param string $transaction_type 'order'|'rental'
     * @param int $transaction_id
     * @return bool
     */
    function revert_discount_usage($transaction_type, $transaction_id) {
        global $conn;
        if (!$transaction_id || $transaction_id <= 0) return false;
        $ttype = mysqli_real_escape_string($conn, $transaction_type);
        $tid   = (int)$transaction_id;
        return (bool)mysqli_query($conn, "DELETE FROM discount_usages WHERE transaction_type='$ttype' AND transaction_id='$tid'");
    }
}

/**
 * Catat atau sinkronisasi data denda rental ke tabel rental_fines
 */
if (!function_exists('sync_rental_fine_record')) {
    function sync_rental_fine_record($rental_id, $actual_return_date = null, $status_pembayaran = null, $catatan = null, $admin_name = null) {
        global $conn;
        $rid = (int)$rental_id;

        $q = mysqli_query($conn, "SELECT r.*, p.price as product_price 
                                  FROM rentals r 
                                  LEFT JOIN products p ON r.product_id = p.id 
                                  WHERE r.id='$rid' LIMIT 1");
        if (!$q || mysqli_num_rows($q) == 0) return false;
        $rental = mysqli_fetch_assoc($q);

        if (!empty($actual_return_date)) {
            $rental['actual_return_date'] = $actual_return_date;
        }

        $fine_info = calculate_rental_fine($rental);
        $uid = (int)$rental['user_id'];
        $late_days = (int)$fine_info['late_days'];
        $fine_type = mysqli_real_escape_string($conn, $fine_info['fine_type']);
        $fine_rate = (float)$fine_info['fine_rate'];
        $fine_amount = (float)$fine_info['fine_amount'];

        // Cek record fine yang sudah ada
        $cek = mysqli_query($conn, "SELECT id, payment_status FROM rental_fines WHERE rental_id='$rid' LIMIT 1");
        if ($cek && mysqli_num_rows($cek) > 0) {
            $existing = mysqli_fetch_assoc($cek);
            $pay_st = $status_pembayaran ?: $existing['payment_status'];
            $pay_st_esc = mysqli_real_escape_string($conn, $pay_st);

            $admin_part = $admin_name ? ", confirmed_by='" . mysqli_real_escape_string($conn, $admin_name) . "'" : "";
            $notes_part = $catatan ? ", notes='" . mysqli_real_escape_string($conn, $catatan) . "'" : "";
            $paid_part = ($pay_st === 'Sudah Dibayar') ? ", paid_at=NOW()" : "";

            mysqli_query($conn, "UPDATE rental_fines SET 
                                    late_days='$late_days', 
                                    fine_type='$fine_type', 
                                    fine_rate='$fine_rate', 
                                    fine_amount='$fine_amount', 
                                    payment_status='$pay_st_esc'
                                    $admin_part $notes_part $paid_part 
                                 WHERE id='{$existing['id']}'");
        } else {
            if ($late_days > 0 || $fine_amount > 0) {
                $pay_st = $status_pembayaran ?: 'Belum Dibayar';
                $pay_st_esc = mysqli_real_escape_string($conn, $pay_st);
                $admin_esc = $admin_name ? "'" . mysqli_real_escape_string($conn, $admin_name) . "'" : "NULL";
                $notes_esc = $catatan ? "'" . mysqli_real_escape_string($conn, $catatan) . "'" : "NULL";
                $paid_sql = ($pay_st === 'Sudah Dibayar') ? "NOW()" : "NULL";

                mysqli_query($conn, "INSERT INTO rental_fines (rental_id, user_id, late_days, fine_type, fine_rate, fine_amount, payment_status, paid_at, confirmed_by, notes)
                                     VALUES ('$rid', '$uid', '$late_days', '$fine_type', '$fine_rate', '$fine_amount', '$pay_st_esc', $paid_sql, $admin_esc, $notes_esc)");
            }
        }

        // Sinkronisasi kolom fine_amount & fine_payment_status di rentals
        $pay_st_final = $status_pembayaran ?: ($rental['fine_payment_status'] ?? 'Belum Dibayar');
        $ret_st = $fine_info['calculated_status'];
        mysqli_query($conn, "UPDATE rentals SET 
                                fine_amount='$fine_amount', 
                                fine_payment_status='$pay_st_final', 
                                return_status='$ret_st' 
                             WHERE id='$rid'");

        return $fine_info;
    }
}
