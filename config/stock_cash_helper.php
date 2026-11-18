<?php
/**
 * DANATRAIL - Stock & Store Cash Balance Helper
 * Mengelola sistem stok inventaris, restock barang, dan arus kas/saldo modal toko
 */

/**
 * Mengambil total omset pendapatan riil dari seluruh transaksi penjualan toko & sewa alat
 * @param mysqli $conn
 * @return array
 */
function get_store_revenue_summary($conn) {
    if (!$conn) {
        return [
            'omset_rental' => 0.00,
            'omset_orders' => 0.00,
            'total_omset'  => 0.00,
            'rental_count' => 0,
            'orders_count' => 0
        ];
    }

    // Pendapatan rental yang disetujui / aktif / selesai
    $q_rent = mysqli_query($conn, "SELECT COALESCE(SUM(total_price), 0) as total, COUNT(*) as cnt 
                                   FROM rentals 
                                   WHERE status IN ('Disewa', 'Selesai', 'Diterima', 'Sedang Disewa')");
    $r_rent = $q_rent ? mysqli_fetch_assoc($q_rent) : ['total' => 0, 'cnt' => 0];

    // Pendapatan pesanan barang toko yang berstatus lunas / diproses
    $q_order = mysqli_query($conn, "SELECT COALESCE(SUM(total_price), 0) as total, COUNT(*) as cnt 
                                    FROM orders 
                                    WHERE payment_status = 'Lunas' OR status IN ('Selesai', 'Diproses', 'Dikirim')");
    $r_order = $q_order ? mysqli_fetch_assoc($q_order) : ['total' => 0, 'cnt' => 0];

    $omset_rent  = (float)($r_rent['total'] ?? 0);
    $omset_order = (float)($r_order['total'] ?? 0);

    return [
        'omset_rental' => $omset_rent,
        'omset_orders' => $omset_order,
        'total_omset'  => $omset_rent + $omset_order,
        'rental_count' => (int)($r_rent['cnt'] ?? 0),
        'orders_count' => (int)($r_order['cnt'] ?? 0)
    ];
}

/**
 * Mengambil total saldo uang kas toko saat ini.
 * Saldo kas toko = (Modal Awal + Total Penjualan Toko + Total Sewa Alat + Mutasi Kas Masuk) - (Total Biaya Restock + Mutasi Kas Keluar)
 * @param mysqli $conn
 * @return float
 */
function get_store_cash_balance($conn) {
    if (!$conn) return 0.00;

    // 1. Ambil modal kas awal (default Rp 10.000.000 jika belum ada)
    $res_init = mysqli_query($conn, "SELECT setting_value FROM system_settings WHERE setting_key = 'initial_store_capital'");
    if (!$res_init || mysqli_num_rows($res_init) == 0) {
        $initial_capital = 10000000.00;
        mysqli_query($conn, "INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('initial_store_capital', '10000000')");
    } else {
        $initial_capital = (float)mysqli_fetch_assoc($res_init)['setting_value'];
    }

    // 2. Total omset pendapatan riil penjualan & rental
    $rev = get_store_revenue_summary($conn);
    $total_omset = $rev['total_omset'];

    // 3. Total pengeluaran restock pengadaan barang (dari stock_logs)
    $q_restock = mysqli_query($conn, "SELECT COALESCE(SUM(total_cost), 0) as total FROM stock_logs WHERE total_cost > 0");
    $total_restock_spent = $q_restock ? (float)mysqli_fetch_assoc($q_restock)['total'] : 0.00;

    // 4. Mutasi manual di store_cash_flow kategori di luar pesanan & restock
    $q_cf_in = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM store_cash_flow WHERE type='in' AND category NOT IN ('order', 'rental')");
    $cf_in = $q_cf_in ? (float)mysqli_fetch_assoc($q_cf_in)['total'] : 0.00;

    $q_cf_out = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM store_cash_flow WHERE type='out' AND category NOT IN ('restock', 'new_product')");
    $cf_out = $q_cf_out ? (float)mysqli_fetch_assoc($q_cf_out)['total'] : 0.00;

    // Kalkulasi saldo uang kas riil
    $current_balance = ($initial_capital + $total_omset + $cf_in) - ($total_restock_spent + $cf_out);

    // Sinkronkan ke system_settings untuk caching
    mysqli_query($conn, "INSERT INTO system_settings (setting_key, setting_value) 
        VALUES ('store_cash_balance', '$current_balance') 
        ON DUPLICATE KEY UPDATE setting_value = '$current_balance'");

    return $current_balance;
}

/**
 * Mengambil ringkasan total nilai seluruh produk dan total unit stok
 * @param mysqli $conn
 * @return array
 */
function get_inventory_financial_summary($conn) {
    if (!$conn) {
        return [
            'total_items' => 0,
            'total_units' => 0,
            'total_units_rent' => 0,
            'total_units_sale' => 0,
            'total_value' => 0.00,
            'total_value_rent' => 0.00,
            'total_value_sale' => 0.00
        ];
    }

    $q = mysqli_query($conn, "SELECT 
        COUNT(*) as total_items,
        COALESCE(SUM(stock), 0) as total_units_rent,
        COALESCE(SUM(stock_sale), 0) as total_units_sale,
        COALESCE(SUM(stock + stock_sale), 0) as total_units,
        COALESCE(SUM(stock * price), 0) as val_rent,
        COALESCE(SUM(stock_sale * price_sale), 0) as val_sale,
        COALESCE(SUM((stock * price) + (stock_sale * price_sale)), 0) as total_val
        FROM products");

    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        return [
            'total_items'       => (int)($row['total_items'] ?? 0),
            'total_units'       => (int)($row['total_units'] ?? 0),
            'total_units_rent'  => (int)($row['total_units_rent'] ?? 0),
            'total_units_sale'  => (int)($row['total_units_sale'] ?? 0),
            'total_value'       => (float)($row['total_val'] ?? 0),
            'total_value_rent'  => (float)($row['val_rent'] ?? 0),
            'total_value_sale'  => (float)($row['val_sale'] ?? 0)
        ];
    }

    return [
        'total_items' => 0,
        'total_units' => 0,
        'total_units_rent' => 0,
        'total_units_sale' => 0,
        'total_value' => 0.00,
        'total_value_rent' => 0.00,
        'total_value_sale' => 0.00
    ];
}

/**
 * Mencatat mutasi kas masuk / keluar di tabel store_cash_flow
 * @param mysqli $conn
 * @param string $type 'in'|'out'
 * @param string $category
 * @param float $amount
 * @param string $description
 * @param int|null $reference_id
 * @return array
 */
function record_cash_flow($conn, $type, $category, $amount, $description, $reference_id = null) {
    $amount = (float)$amount;
    if ($amount < 0) {
        return ['success' => false, 'message' => 'Nominal tidak boleh negatif.'];
    }

    $current_balance = get_store_cash_balance($conn);
    $balance_after   = ($type === 'in') ? ($current_balance + $amount) : ($current_balance - $amount);

    $safe_cat   = mysqli_real_escape_string($conn, $category);
    $safe_desc  = mysqli_real_escape_string($conn, $description);
    $ref_val    = $reference_id !== null ? (int)$reference_id : "NULL";

    // Catat riwayat mutasi
    $sql_flow = "INSERT INTO store_cash_flow (type, category, amount, balance_before, balance_after, reference_id, description) 
                 VALUES ('$type', '$safe_cat', '$amount', '$current_balance', '$balance_after', $ref_val, '$safe_desc')";
    $ins = mysqli_query($conn, $sql_flow);

    // Perbarui setting balance
    mysqli_query($conn, "INSERT INTO system_settings (setting_key, setting_value) 
        VALUES ('store_cash_balance', '$balance_after') 
        ON DUPLICATE KEY UPDATE setting_value = '$balance_after'");

    return [
        'success'        => $ins ? true : false,
        'balance_before' => $current_balance,
        'balance_after'  => $balance_after,
        'amount'         => $amount
    ];
}

/**
 * Menambah stok unit barang dan memotong total saldo uang kas toko
 * @param mysqli $conn
 * @param int $product_id
 * @param string $stock_type 'rental'|'sale'|'both'
 * @param int $qty_rental
 * @param int $qty_sale
 * @param float $cost_per_unit
 * @param string $action_type 'new_product'|'restock'
 * @param string $notes
 * @return array
 */
function process_stock_addition_and_deduct_cash($conn, $product_id, $stock_type, $qty_rental, $qty_sale = 0, $cost_per_unit = 0, $action_type = 'restock', $notes = '') {
    $product_id    = (int)$product_id;
    $qty_rental    = max(0, (int)$qty_rental);
    $qty_sale      = max(0, (int)$qty_sale);
    $cost_per_unit = max(0, (float)$cost_per_unit);
    $action_type   = in_array($action_type, ['new_product', 'restock']) ? $action_type : 'restock';

    $total_qty = $qty_rental + $qty_sale;
    if ($product_id <= 0 || $total_qty <= 0) {
        return ['success' => false, 'message' => 'ID Produk atau Jumlah penambahan unit tidak valid.'];
    }

    $q_prod = mysqli_query($conn, "SELECT name, price, price_sale, stock, stock_sale, cost_price FROM products WHERE id = '$product_id'");
    if (!$q_prod || mysqli_num_rows($q_prod) == 0) {
        return ['success' => false, 'message' => 'Data produk tidak ditemukan di inventaris.'];
    }
    $prod = mysqli_fetch_assoc($q_prod);
    $prod_name = $prod['name'];

    // Jika cost_per_unit tidak dikirim (>0), gunakan cost_price produk jika ada
    if ($cost_per_unit <= 0 && !empty($prod['cost_price']) && (float)$prod['cost_price'] > 0) {
        $cost_per_unit = (float)$prod['cost_price'];
    }

    $total_cost   = $total_qty * $cost_per_unit;
    $current_cash = get_store_cash_balance($conn);

    // Cek apakah saldo kas mencukupi jika total_cost > 0
    if ($total_cost > 0 && $current_cash < $total_cost) {
        return [
            'success' => false, 
            'message' => 'Saldo uang kas toko tidak mencukupi! Saldo saat ini: Rp ' . number_format($current_cash, 0, ',', '.') . ', dibutuhkan biaya restock: Rp ' . number_format($total_cost, 0, ',', '.') . '.'
        ];
    }

    // 1. Update stok di tabel products & cost_price
    $upd_parts = [];
    if ($qty_rental > 0) $upd_parts[] = "stock = stock + $qty_rental";
    if ($qty_sale > 0) $upd_parts[] = "stock_sale = stock_sale + $qty_sale";
    if ($cost_per_unit > 0) $upd_parts[] = "cost_price = '$cost_per_unit'";

    $upd_sql = "UPDATE products SET " . implode(', ', $upd_parts) . " WHERE id = '$product_id'";
    if (!mysqli_query($conn, $upd_sql)) {
        return ['success' => false, 'message' => 'Gagal mengupdate stok barang: ' . mysqli_error($conn)];
    }

    $cash_after = $current_cash - $total_cost;

    // 2. Catat mutasi kas keluar di store_cash_flow jika total_cost > 0
    if ($total_cost > 0) {
        $label_action = ($action_type === 'new_product') ? 'Pengadaan Awal' : 'Restock';
        $desc = "{$label_action} {$total_qty} unit '{$prod_name}' @ Rp " . number_format($cost_per_unit, 0, ',', '.');
        if (!empty($notes)) {
            $desc .= " (" . $notes . ")";
        }
        record_cash_flow($conn, 'out', $action_type, $total_cost, $desc, $product_id);
    }

    // 3. Catat di tabel stock_logs
    $safe_notes = mysqli_real_escape_string($conn, $notes);
    if ($qty_rental > 0) {
        $rent_cost = $qty_rental * $cost_per_unit;
        mysqli_query($conn, "INSERT INTO stock_logs (product_id, type, action_type, quantity, cost_per_unit, total_cost, cash_before, cash_after, notes) 
            VALUES ('$product_id', 'rental', '$action_type', '$qty_rental', '$cost_per_unit', '$rent_cost', '$current_cash', '$cash_after', '$safe_notes')");
    }
    if ($qty_sale > 0) {
        $sale_cost = $qty_sale * $cost_per_unit;
        mysqli_query($conn, "INSERT INTO stock_logs (product_id, type, action_type, quantity, cost_per_unit, total_cost, cash_before, cash_after, notes) 
            VALUES ('$product_id', 'sale', '$action_type', '$qty_sale', '$cost_per_unit', '$sale_cost', '$current_cash', '$cash_after', '$safe_notes')");
    }

    // Refresh saldo terkini
    $final_cash = get_store_cash_balance($conn);
    $inv_summary = get_inventory_financial_summary($conn);

    return [
        'success'           => true,
        'message'           => 'Stok berhasil ditambahkan dan uang kas toko berhasil dipotong.',
        'product_name'      => $prod_name,
        'qty_added'         => $total_qty,
        'qty_rental'        => $qty_rental,
        'qty_sale'          => $qty_sale,
        'cost_per_unit'     => $cost_per_unit,
        'total_cost'        => $total_cost,
        'cash_before'       => $current_cash,
        'cash_after'        => $final_cash,
        'new_total_value'   => $inv_summary['total_value']
    ];
}
