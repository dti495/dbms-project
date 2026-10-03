<?php
// add_to_cart.php — Adds a product to the session-based shopping cart

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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: shop.php');
    exit;
}

// Extract and sanitize POST data
$product_id   = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$posted_price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
$posted_name  = trim($_POST['product_name'] ?? '');

if ($product_id <= 0) {
    header('Location: shop.php');
    exit;
}

$product_name = $posted_name;
$unit_price   = $posted_price;

// Verify product details against the database to prevent price tampering
try {
    require_once __DIR__ . '/db_connect.php';

    // Fetch product status, name, and price from the database
    $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity, status FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        // Reject if the product is out of stock or inactive
        if ($product['status'] !== 'active' || (int)$product['stock_quantity'] <= 0) {
            header('Location: shop.php?msg=out_of_stock');
            exit;
        }

        // Use database values — ignore whatever the client sent
        $product_name = $product['name'];
        $unit_price   = (float)$product['price'];
    }
} catch (PDOException $e) {
    // If the DB check fails, fall back to POST data so the cart still works
    if (empty($product_name)) {
        $product_name = "Product #" . $product_id;
    }
}

// Fetch available stock limit from product data
$available_stock = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : 0;

// Initialize the cart session if it doesn't exist yet
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$current_cart_qty = isset($_SESSION['cart'][$product_id]) ? (int)$_SESSION['cart'][$product_id]['quantity'] : 0;
$incoming_qty     = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
$target_qty       = $current_cart_qty + $incoming_qty;

// Strict stock limit validation: check if requested quantity <= available stock
if ($target_qty > $available_stock) {
    // Cap quantity to available stock
    $capped_qty = max(1, $available_stock);
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] = $capped_qty;
    } else {
        $_SESSION['cart'][$product_id] = [
            'id'          => $product_id,
            'product_id'  => $product_id,
            'name'        => $product_name,
            'price'       => $unit_price,
            'quantity'    => $capped_qty,
            'producer_id' => (int)($product['producer_id'] ?? 0)
        ];
    }
    $warning = "Only {$available_stock} items available in stock!";
    header('Location: cart.php?warning=' . urlencode($warning));
    exit;
}

// Add or update cart with requested quantity within stock limit
if (isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id]['quantity'] = $target_qty;
} else {
    $_SESSION['cart'][$product_id] = [
        'id'          => $product_id,
        'product_id'  => $product_id,
        'name'        => $product_name,
        'price'       => $unit_price,
        'quantity'    => $target_qty,
        'producer_id' => (int)($product['producer_id'] ?? 0)
    ];
}

header('Location: cart.php?added=1');
exit;
