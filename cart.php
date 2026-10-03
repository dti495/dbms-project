<?php
// cart.php — Displays the session-based shopping cart and order summary

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

// Handle cart actions from the URL (remove, clear, increase, decrease, update)
if (isset($_REQUEST['action'])) {
    $action  = $_REQUEST['action'];
    $item_id = (int)($_REQUEST['id'] ?? 0);

    if ($action === 'remove' && $item_id > 0) {
        if (isset($_SESSION['cart'][$item_id])) {
            unset($_SESSION['cart'][$item_id]);
        }
        header('Location: cart.php?msg=removed');
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        header('Location: cart.php?msg=cleared');
        exit;
    }

    // Handle increasing or adjusting quantity with strict stock limit check
    if (($action === 'increase' || $action === 'decrease' || $action === 'update') && $item_id > 0 && isset($_SESSION['cart'][$item_id])) {
        $curr_qty = (int)$_SESSION['cart'][$item_id]['quantity'];

        // Query live database stock for this product
        $stk_stmt = $pdo->prepare("SELECT name, stock_quantity, status FROM products WHERE id = ?");
        $stk_stmt->execute([$item_id]);
        $prod_info = $stk_stmt->fetch();

        $live_stock = $prod_info ? (int)$prod_info['stock_quantity'] : 0;

        if ($action === 'increase') {
            $new_qty = $curr_qty + 1;
        } elseif ($action === 'decrease') {
            $new_qty = max(1, $curr_qty - 1);
        } else {
            $new_qty = max(1, (int)($_REQUEST['quantity'] ?? 1));
        }

        if ($new_qty > $live_stock) {
            // Cap quantity to available stock
            $_SESSION['cart'][$item_id]['quantity'] = max(1, $live_stock);
            $warning = "Only {$live_stock} items available in stock!";
            header('Location: cart.php?warning=' . urlencode($warning));
            exit;
        }

        $_SESSION['cart'][$item_id]['quantity'] = $new_qty;
        header('Location: cart.php?msg=updated');
        exit;
    }
}

$page_title = 'Shopping Cart - HaatLink';

require_once __DIR__ . '/includes/header.php';

// Check existing cart items against live database stock and cap if necessary
$stock_adjusted_warnings = [];
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cid => &$c_item) {
        $check = $pdo->prepare("SELECT name, stock_quantity, status FROM products WHERE id = ?");
        $check->execute([$cid]);
        $live_item = $check->fetch();
        if ($live_item) {
            $stock_max = (int)$live_item['stock_quantity'];
            if ((int)$c_item['quantity'] > $stock_max) {
                $c_item['quantity'] = max(1, $stock_max);
                $stock_adjusted_warnings[] = "Only {$stock_max} items available in stock for " . htmlspecialchars($live_item['name']) . "!";
            }
        }
    }
    unset($c_item);
}

// Calculate totals from the cart session
$cart_items     = $_SESSION['cart'] ?? [];
$items_subtotal = 0.0;
$total_items    = 0;

foreach ($cart_items as $item) {
    $qty            = (int)($item['quantity'] ?? 1);
    $price          = (float)($item['price'] ?? 0);
    $total_items   += $qty;
    $items_subtotal += ($qty * $price);
}

$delivery_fee = ($items_subtotal >= 500) ? 0.00 : 40.00;
$discount     = round($items_subtotal * 0.02, 2);  // 2% automatic discount
$grand_total  = ($items_subtotal - $discount) + $delivery_fee;

// Set feedback alert message
$alert_msg  = '';
$alert_type = 'success';

if (isset($_GET['warning'])) {
    $alert_msg  = $_GET['warning'];
    $alert_type = 'warning';
} elseif (!empty($stock_adjusted_warnings)) {
    $alert_msg  = implode(' ', $stock_adjusted_warnings);
    $alert_type = 'warning';
} elseif (isset($_GET['added'])) {
    $alert_msg  = 'Item successfully added to your cart!';
    $alert_type = 'success';
} elseif (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'removed') {
        $alert_msg  = 'Item has been removed from your cart.';
        $alert_type = 'info';
    } elseif ($_GET['msg'] === 'cleared') {
        $alert_msg  = 'Your shopping cart has been cleared.';
        $alert_type = 'info';
    } elseif ($_GET['msg'] === 'updated') {
        $alert_msg  = 'Cart updated successfully.';
        $alert_type = 'success';
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Shopping Cart</h1>
        <p class="page-subtitle">Review your selected items before placing an order.</p>
    </div>
    <span class="badge badge-verified">
        <?php echo $total_items; ?> Item<?php echo $total_items === 1 ? '' : 's'; ?> in Cart
    </span>
</div>

<?php if (!empty($alert_msg)): ?>
    <div class="alert alert-<?php echo $alert_type; ?>">
        <span>ℹ️ <?php echo htmlspecialchars($alert_msg); ?></span>
    </div>
<?php endif; ?>

<?php if (empty($cart_items)): ?>
    <!-- Empty Cart -->
    <div class="card empty-state" style="padding: 60px 20px;">
        <div style="font-size: 54px; margin-bottom: 15px;">🛒</div>
        <h3 style="font-size: 20px; margin-bottom: 8px;">Your Cart is Empty</h3>
        <p style="max-width: 460px; margin: 0 auto 24px auto;">
            Browse the marketplace to find fresh produce, dairy, and handmade crafts from local producers.
        </p>
        <a href="shop.php" class="btn btn-primary">🌾 Explore Marketplace</a>
    </div>

<?php else: ?>
    <div class="cart-layout">

        <!-- Cart Items Table -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="card-header" style="padding: 18px 24px; margin-bottom: 0;">
                <h2 class="card-title">Cart Items</h2>
                <a href="cart.php?action=clear" class="btn btn-secondary btn-sm"
                   style="color: var(--status-danger);"
                   onclick="return confirm('Clear your entire cart?');">
                    Clear Cart
                </a>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-right">Subtotal</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item):
                            $item_qty      = (int)($item['quantity'] ?? 1);
                            $item_price    = (float)($item['price'] ?? 0);
                            $item_subtotal = $item_qty * $item_price;
                            $item_id       = (int)($item['id'] ?? $item['product_id'] ?? 0);
                        ?>
                            <tr>
                                <td>
                                    <div class="cart-item-cell">
                                        <div class="cart-item-icon">🌱</div>
                                        <div>
                                            <strong style="color: var(--secondary-color); font-size: 15px;">
                                                <?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>
                                            </strong>
                                            <div class="cart-item-id">Item ID: #<?php echo $item_id; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-right" style="font-weight: 600;">
                                    ৳ <?php echo number_format($item_price, 2); ?>
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    <div style="display: inline-flex; align-items: center; border: 1.5px solid var(--border-color); border-radius: 6px; overflow: hidden; background: #ffffff;">
                                        <a href="cart.php?action=decrease&id=<?php echo $item_id; ?>" 
                                           title="Decrease quantity"
                                           style="padding: 4px 10px; background: #f8fafc; color: var(--text-muted); text-decoration: none; font-weight: 700; border-right: 1px solid var(--border-color);">&minus;</a>
                                        <span style="padding: 4px 14px; font-weight: 700; font-size: 13.5px; min-width: 32px; text-align: center; color: var(--secondary-color);">
                                            <?php echo $item_qty; ?>
                                        </span>
                                        <a href="cart.php?action=increase&id=<?php echo $item_id; ?>" 
                                           title="Increase quantity"
                                           style="padding: 4px 10px; background: #f8fafc; color: var(--primary-color); text-decoration: none; font-weight: 700; border-left: 1px solid var(--border-color);">&plus;</a>
                                    </div>
                                </td>
                                <td class="text-right" style="font-weight: 700; color: var(--primary-color);">
                                    ৳ <?php echo number_format($item_subtotal, 2); ?>
                                </td>
                                <td class="text-center">
                                    <a href="cart.php?action=remove&id=<?php echo $item_id; ?>"
                                       class="btn btn-secondary btn-sm"
                                       style="color: var(--status-danger); padding: 4px 8px; font-size: 12px;"
                                       onclick="return confirm('Remove this item from your cart?');">
                                        ✕ Remove
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="cart-table-footer">
                <a href="shop.php" class="btn btn-outline btn-sm">&larr; Continue Shopping</a>
                <span>Prices are at standard local market rates.</span>
            </div>
        </div>

        <!-- Order Summary Panel -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Order Summary</h2>
            </div>

            <div class="order-summary-rows">
                <div class="order-row">
                    <span>Total Items:</span>
                    <strong><?php echo $total_items; ?></strong>
                </div>
                <div class="order-row">
                    <span>Subtotal:</span>
                    <strong>৳ <?php echo number_format($items_subtotal, 2); ?></strong>
                </div>
                <div class="order-row" style="color: var(--status-success);">
                    <span>&#127881; 2% Special Discount:</span>
                    <strong style="color: var(--status-success);">-৳ <?php echo number_format($discount, 2); ?></strong>
                </div>
                <div class="order-row">
                    <span>Delivery:</span>
                    <?php if ($delivery_fee > 0): ?>
                        <strong>৳ <?php echo number_format($delivery_fee, 2); ?></strong>
                    <?php else: ?>
                        <span style="color: var(--primary-color); font-weight: 600;">Free</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-total-row">
                <span class="order-total-label">Total:</span>
                <span class="order-total-amount">৳ <?php echo number_format($grand_total, 2); ?></span>
            </div>

            <!-- Checkout button — active when cart has items -->
            <a href="checkout.php" class="btn btn-primary" id="checkoutBtn"
               style="width: 100%; padding: 12px 18px; font-size: 15px; font-weight: 700; margin-top: 20px; text-align: center;">
                Proceed to Checkout &rarr;
            </a>

            <div class="checkout-note">
                🔒 Secure checkout &mdash; Cash on Delivery available
            </div>
        </div>

    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
