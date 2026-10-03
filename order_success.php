<?php
// order_success.php — Displayed after a successful order placement

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order_id    = (int)($_GET['order_id'] ?? 0);
$customer_id = (int)$_SESSION['user_id'];

// Fetch the order and its items from DB
$order = null;
$items = [];

if ($order_id > 0) {
    $stmtOrder = $pdo->prepare("
        SELECT o.*, u.name AS customer_name
        FROM `orders` o
        JOIN `users` u ON u.id = o.customer_id
        WHERE o.id = :order_id AND o.customer_id = :customer_id
    ");
    $stmtOrder->execute([':order_id' => $order_id, ':customer_id' => $customer_id]);
    $order = $stmtOrder->fetch();

    if ($order) {
        $stmtItems = $pdo->prepare("
            SELECT oi.quantity, oi.unit_price, p.name AS product_name
            FROM `order_items` oi
            JOIN `products` p ON p.id = oi.product_id
            WHERE oi.order_id = :order_id
        ");
        $stmtItems->execute([':order_id' => $order_id]);
        $items = $stmtItems->fetchAll();
    }
}

// If no valid order found, redirect home
if (!$order) {
    header('Location: index.php');
    exit;
}

$page_title = 'Order Confirmed - HaatLink';
require_once __DIR__ . '/includes/header.php';
?>

<div class="order-success-wrap">

    <div class="order-success-card">

        <!-- Animated success icon -->
        <span class="success-icon">&#9989;</span>

        <h1 class="success-title">Order Placed Successfully!</h1>
        <p class="success-subtitle">
            Thank you, <strong><?php echo htmlspecialchars($order['shipping_name']); ?></strong>!
            Your order has been received and is now being processed.
        </p>

        <!-- Order ID Badge -->
        <div class="order-id-badge">
            Order #<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?>
        </div>

        <!-- Order Details Card -->
        <div class="success-details-card">
            <div class="order-summary-rows">

                <?php foreach ($items as $it): ?>
                    <div class="order-row">
                        <span style="font-weight: 600; color: var(--secondary-color);">
                            &#127807; <?php echo htmlspecialchars($it['product_name']); ?>
                            &times; <?php echo (int)$it['quantity']; ?>
                        </span>
                        <span style="font-weight: 700; color: var(--primary-color);">
                            &#2547; <?php echo number_format($it['unit_price'] * $it['quantity'], 2); ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <hr style="border: none; border-top: 1px dashed var(--border-color); margin: 8px 0;">

                <div class="order-row">
                    <span>Items Subtotal</span>
                    <strong>&#2547; <?php echo number_format($order['total_amount'] - $order['delivery_fee'], 2); ?></strong>
                </div>
                <div class="order-row">
                    <span>Delivery Fee</span>
                    <?php if ((float)$order['delivery_fee'] > 0): ?>
                        <strong>&#2547; <?php echo number_format($order['delivery_fee'], 2); ?></strong>
                    <?php else: ?>
                        <span style="color: var(--primary-color); font-weight: 700;">&#127881; Free</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grand Total Row -->
            <div class="order-total-row" style="margin-top: 14px;">
                <span class="order-total-label">Grand Total</span>
                <span class="order-total-amount">&#2547; <?php echo number_format($order['total_amount'], 2); ?></span>
            </div>

            <!-- Shipping & Payment Summary -->
            <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border-color);
                        font-size: 13px; color: var(--text-muted); line-height: 1.8; text-align: left;">
                <div><strong style="color: var(--secondary-color);">&#128230; Shipping to:</strong></div>
                <div><?php echo htmlspecialchars($order['shipping_name'] ?? ''); ?></div>
                <div><?php echo htmlspecialchars($order['shipping_phone'] ?? $order['phone'] ?? ''); ?></div>
                <div><?php echo htmlspecialchars($order['shipping_address'] ?? $order['address'] ?? ''); ?></div>

                <div style="margin-top: 10px;">
                    <strong style="color: var(--secondary-color);">&#128179; Payment:</strong>
                    <?php 
                    $pm = htmlspecialchars($order['payment_method'] ?? '');
                    if (strtolower($pm) === 'bkash') {
                        echo 'bKash Mobile Banking';
                    } elseif (strtolower($pm) === 'cod') {
                        echo 'Cash on Delivery (COD)';
                    } else {
                        echo $pm;
                    }
                    ?>
                </div>
                <?php if (!empty($order['transaction_id'])): ?>
                <div>
                    <strong style="color: var(--secondary-color);">&#128241; Trx ID / Sender:</strong>
                    <?php echo htmlspecialchars($order['transaction_id']); ?>
                </div>
                <?php endif; ?>
                <div>
                    <strong style="color: var(--secondary-color);">&#128197; Placed on:</strong>
                    <?php echo date('d M Y, g:i A', strtotime($order['created_at'])); ?>
                </div>
                <div>
                    <strong style="color: var(--secondary-color);">Status:</strong>
                    <span class="badge badge-pending" style="text-transform: capitalize; font-size: 11px;">
                        <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                    </span>
                </div>
            </div>
        </div><!-- /.success-details-card -->

        <!-- Action Buttons -->
        <div class="success-actions">
            <a href="shop.php" class="btn btn-primary">&#127807; Continue Shopping</a>
            <a href="index.php" class="btn btn-secondary">&#127968; Back to Home</a>
        </div>

        <p style="margin-top: 20px; font-size: 12px; color: var(--text-muted);">
            &#128274; Secure order &mdash; Expected delivery in 1&ndash;3 business days. COD payment on arrival.
        </p>

    </div><!-- /.order-success-card -->

</div><!-- /.order-success-wrap -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
