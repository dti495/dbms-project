<?php
// process_order.php — Handles POST from checkout.php
// Uses a PDO transaction to atomically insert order, items, and decrement stock.

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) && isset($_SESSION['user_role'])) {
    $_SESSION['role'] = $_SESSION['user_role'];
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'producer') {
    header("Location: producer_dashboard.php?error=producers_cannot_order");
    exit();
}

// ── Guard: must be a POST request ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

// ── Guard: user must be logged in ────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout');
    exit;
}

// ── Guard: cart must not be empty ────────────────────────────────────────────
$cart_items = $_SESSION['cart'] ?? [];
if (empty($cart_items)) {
    header('Location: cart.php?msg=empty_cart');
    exit;
}

// ── Collect & sanitize form fields ───────────────────────────────────────────
$customer_id   = (int)$_SESSION['user_id'];
$shipping_name = trim($_POST['shipping_name']    ?? '');
$shipping_phone= trim($_POST['shipping_phone']   ?? '');
$address_line  = trim($_POST['shipping_address'] ?? '');
$district      = trim($_POST['shipping_district']?? '');
$instructions  = trim($_POST['delivery_instructions'] ?? '');
$raw_payment   = trim($_POST['payment_method']   ?? 'cod');
$payment_method= (strtolower($raw_payment) === 'bkash') ? 'bKash' : 'Cash on Delivery';
$bkash_trx     = trim($_POST['bkash_trx'] ?? $_POST['bkash_transaction_id'] ?? '');

// Build full shipping address (combine address + district + optional notes)
$full_address = $address_line . ', ' . $district;
if ($instructions !== '') {
    $full_address .= ' | Note: ' . $instructions;
}
if ($payment_method === 'bKash' && $bkash_trx !== '') {
    $full_address .= ' | bKash Trx/Sender: ' . $bkash_trx;
}

// Basic server-side validation
$errors = [];
if ($shipping_name === '')  $errors[] = 'Full name is required.';
if ($shipping_phone === '') $errors[] = 'Phone number is required.';
if ($address_line === '')   $errors[] = 'Delivery address is required.';
if ($district === '')       $errors[] = 'District / City is required.';

if (!empty($errors)) {
    $error_msg = implode(' ', $errors);
    header('Location: checkout.php?error=' . urlencode($error_msg));
    exit;
}

// ── Recalculate totals server-side (never trust client-submitted totals) ─────
$items_subtotal = 0.0;
foreach ($cart_items as $item) {
    $qty             = (int)($item['quantity'] ?? 1);
    $price           = (float)($item['price']    ?? 0);
    $items_subtotal += ($qty * $price);
}
$delivery_fee = ($items_subtotal >= 500) ? 0.00 : 40.00;
$discount     = round($items_subtotal * 0.02, 2);  // 2% automatic discount
$grand_total  = ($items_subtotal - $discount) + $delivery_fee;

// ── Begin PDO Transaction ─────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // ── Step 1: Insert the master order record ────────────────────────────────
    // Inspect orders table columns dynamically for 100% schema compatibility
    $ordersCols = $pdo->query("SHOW COLUMNS FROM `orders`")->fetchAll(PDO::FETCH_COLUMN);

    $orderData = [];
    if (in_array('customer_id', $ordersCols))      $orderData['customer_id']      = $customer_id;
    if (in_array('total_amount', $ordersCols))     $orderData['total_amount']     = $grand_total;
    if (in_array('delivery_fee', $ordersCols))     $orderData['delivery_fee']     = $delivery_fee;
    if (in_array('platform_fee', $ordersCols))     $orderData['platform_fee']     = 0.00;
    if (in_array('shipping_name', $ordersCols))    $orderData['shipping_name']    = $shipping_name;
    if (in_array('shipping_phone', $ordersCols))   $orderData['shipping_phone']   = $shipping_phone;
    if (in_array('phone', $ordersCols))            $orderData['phone']            = $shipping_phone;
    if (in_array('shipping_address', $ordersCols)) $orderData['shipping_address'] = $full_address;
    elseif (in_array('address', $ordersCols))      $orderData['address']          = $full_address;
    if (in_array('payment_method', $ordersCols))   $orderData['payment_method']   = $payment_method;
    if (in_array('status', $ordersCols))           $orderData['status']           = 'pending';
    if (in_array('transaction_id', $ordersCols))   $orderData['transaction_id']   = $bkash_trx;
    elseif (in_array('bkash_transaction_id', $ordersCols)) $orderData['bkash_transaction_id'] = $bkash_trx;

    $colNames     = array_keys($orderData);
    $placeholders = array_fill(0, count($orderData), '?');
    $stmtOrder    = $pdo->prepare("INSERT INTO `orders` (" . implode(', ', $colNames) . ") VALUES (" . implode(', ', $placeholders) . ")");
    $stmtOrder->execute(array_values($orderData));

    $order_id = (int)$pdo->lastInsertId();

    // ── Step 2: Prepare statements for order items & stock update ─────────────
    $itemsCols = $pdo->query("SHOW COLUMNS FROM `order_items`")->fetchAll(PDO::FETCH_COLUMN);
    $itemCols  = ['order_id', 'product_id'];
    if (in_array('producer_id', $itemsCols))       $itemCols[] = 'producer_id';
    $itemCols[] = 'quantity';
    $itemCols[] = 'unit_price';
    if (in_array('platform_fee', $itemsCols))      $itemCols[] = 'platform_fee';
    if (in_array('producer_earnings', $itemsCols)) $itemCols[] = 'producer_earnings';

    $itemSql  = "INSERT INTO `order_items` (" . implode(', ', $itemCols) . ") VALUES (" . implode(', ', array_fill(0, count($itemCols), '?')) . ")";
    $stmtItem = $pdo->prepare($itemSql);

    $stmtStock = $pdo->prepare("
        UPDATE `products`
        SET `stock_quantity` = `stock_quantity` - ?
        WHERE `id` = ?
          AND `stock_quantity` >= ?
    ");

    // Platform fee rate (0% for now — reserved for Phase 2 revenue model)
    $platform_fee_rate = 0.00;

    foreach ($cart_items as $item) {
        $product_id  = (int)($item['id'] ?? $item['product_id'] ?? 0);
        $producer_id = (int)($item['producer_id'] ?? 0);
        $qty         = (int)($item['quantity'] ?? 1);
        $unit_price  = (float)($item['price'] ?? 0);

        if ($product_id === 0) {
            throw new Exception("Invalid product ID in cart.");
        }

        // ── Step 2a: Check live stock and fetch producer if not in session ────
        $stmtCheck = $pdo->prepare("
            SELECT `stock_quantity`, `producer_id`, `name`
            FROM `products`
            WHERE `id` = ?
              AND `status` = 'active'
        ");
        $stmtCheck->execute([$product_id]);
        $product = $stmtCheck->fetch();

        $prod_name = !empty($product['name']) ? $product['name'] : (!empty($item['name']) ? $item['name'] : "Product #$product_id");

        if (!$product || (int)$product['stock_quantity'] < $qty) {
            throw new Exception("Order failed: $prod_name is out of stock or requested quantity exceeds available stock.");
        }

        // Use DB producer_id as source of truth
        $producer_id = (int)$product['producer_id'];

        // ── Step 2b: Deduct stock ─────────────────────────────────────────────
        $stmtStock->execute([$qty, $product_id, $qty]);
        if ($stmtStock->rowCount() === 0) {
            // Row was not updated — race condition / stock just ran out
            throw new Exception("Order failed: $prod_name is out of stock or requested quantity exceeds available stock.");
        }

        // ── Step 2c: Calculate earnings ───────────────────────────────────────
        $item_total       = $unit_price * $qty;
        $platform_fee     = round($item_total * $platform_fee_rate, 2);
        $producer_earnings= round($item_total - $platform_fee, 2);

        // ── Step 2d: Insert order item ────────────────────────────────────────
        $itemValues = [$order_id, $product_id];
        if (in_array('producer_id', $itemsCols))       $itemValues[] = $producer_id;
        $itemValues[] = $qty;
        $itemValues[] = $unit_price;
        if (in_array('platform_fee', $itemsCols))      $itemValues[] = $platform_fee;
        if (in_array('producer_earnings', $itemsCols)) $itemValues[] = $producer_earnings;

        $stmtItem->execute($itemValues);
    }

    // ── Step 3: Commit transaction ────────────────────────────────────────────
    $pdo->commit();

    // ── Step 4: Clear cart from session ──────────────────────────────────────
    unset($_SESSION['cart']);

    // ── Step 5: Redirect to success page ─────────────────────────────────────
    header('Location: order_success.php?order_id=' . $order_id);
    exit;

} catch (Exception $e) {
    // ── Rollback on any failure ───────────────────────────────────────────────
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Redirect back to checkout with exact error message
    $safe_error = urlencode($e->getMessage());
    header('Location: checkout.php?error=' . $safe_error);
    exit;
}
