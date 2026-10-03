<?php
// my_orders.php — Customer Order History
// Displays all past orders placed by the logged-in customer

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect guests to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode('my_orders.php'));
    exit;
}

$customer_id   = (int)$_SESSION['user_id'];
$customer_name = $_SESSION['user_name'] ?? 'Customer';
$page_title    = 'My Orders - HaatLink';

$orders         = [];
$items_by_order = [];

try {
    // 1. Fetch customer's orders in reverse chronological order
    $stmt = $pdo->prepare("
        SELECT *
        FROM `orders`
        WHERE `customer_id` = ?
        ORDER BY `created_at` DESC
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll();

    // 2. Efficiently fetch items for all retrieved orders in a single query
    if (!empty($orders)) {
        $order_ids = array_column($orders, 'id');
        $in_clause = implode(',', array_fill(0, count($order_ids), '?'));

        $stmtItems = $pdo->prepare("
            SELECT oi.*, COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS product_name
            FROM `order_items` oi
            LEFT JOIN `products` p ON p.id = oi.product_id
            WHERE oi.order_id IN ($in_clause)
            ORDER BY oi.id ASC
        ");
        $stmtItems->execute($order_ids);
        $all_items = $stmtItems->fetchAll();

        foreach ($all_items as $item) {
            $items_by_order[$item['order_id']][] = $item;
        }
    }
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">My Orders</h1>
        <p class="page-subtitle">Track your current orders and review your past purchases.</p>
    </div>
    <span class="badge badge-verified">
        <?php echo count($orders); ?> Order<?php echo count($orders) === 1 ? '' : 's'; ?> Placed
    </span>
</div>

<?php if (isset($db_error)): ?>
    <div class="alert alert-danger">
        <span>⚠️ Unable to load your orders right now. Please try again later.</span>
    </div>
<?php endif; ?>

<?php if (empty($orders)): ?>
    <!-- Empty State -->
    <div class="card empty-state" style="padding: 60px 20px; text-align: center;">
        <div style="font-size: 54px; margin-bottom: 16px;">📦</div>
        <h3 style="font-size: 20px; color: var(--secondary-color); margin-bottom: 8px;">No Orders Placed Yet</h3>
        <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 24px auto; line-height: 1.6;">
            You haven't placed any orders yet. Discover fresh local crops, dairy, and artisanal goods directly from verified producers!
        </p>
        <a href="shop.php" class="btn btn-primary">🌾 Explore Marketplace</a>
    </div>

<?php else: ?>

    <div style="display: flex; flex-direction: column; gap: 24px;">
        <?php foreach ($orders as $order):
            $order_id   = (int)$order['id'];
            $status     = strtolower($order['status'] ?? 'pending');
            $created_at = !empty($order['created_at']) ? date('d M Y, h:i A', strtotime($order['created_at'])) : 'N/A';
            $items      = $items_by_order[$order_id] ?? [];
            $formatted_id = sprintf('#%05d', $order_id);

            // Determine status badge class
            if ($status === 'delivered') {
                $badge_class = 'badge-delivered';
            } elseif ($status === 'processing') {
                $badge_class = 'badge-processing';
            } elseif ($status === 'cancelled') {
                $badge_class = 'badge-cancelled';
            } else {
                $badge_class = 'badge-pending';
            }

            // Shipping & Phone fallback
            $phone   = $order['shipping_phone'] ?? $order['phone'] ?? 'N/A';
            $address = $order['shipping_address'] ?? $order['address'] ?? 'N/A';
            $payment = $order['payment_method'] ?? 'Cash on Delivery';

            // Recalculate bill breakdown from order items
            $o_items_subtotal = 0.0;
            foreach ($items as $oi) {
                $o_items_subtotal += (int)($oi['quantity'] ?? 1) * (float)($oi['unit_price'] ?? 0);
            }
            $o_discount     = round($o_items_subtotal * 0.02, 2);
            $o_grand_total  = (float)($order['total_amount'] ?? 0);
            $o_delivery_fee = $o_grand_total - ($o_items_subtotal - $o_discount);
        ?>
            <div class="card" style="padding: 0; overflow: hidden; border: 1px solid var(--border-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);">

                <!-- Order Card Header -->
                <div style="background-color: #f8fafc; padding: 16px 22px; border-bottom: 1px solid var(--border-color); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <span style="font-family: monospace; font-size: 17px; font-weight: 800; color: var(--secondary-color); background: #ffffff; padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border-color);">
                            <?php echo htmlspecialchars($formatted_id); ?>
                        </span>
                        <span style="font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 4px;">
                            📅 <?php echo htmlspecialchars($created_at); ?>
                        </span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="badge <?php echo $badge_class; ?>" style="padding: 5px 12px; font-size: 12px;">
                            <?php echo ucfirst(htmlspecialchars($status)); ?>
                        </span>
                    </div>
                </div>

                <!-- Order Items Body -->
                <div style="padding: 20px 22px;">
                    <div class="table-responsive" style="margin: 0;">
                        <table class="table" style="margin-bottom: 0;">
                            <thead>
                                <tr style="border-bottom: 1.5px solid var(--border-color);">
                                    <th style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); padding: 8px 12px;">Product Name</th>
                                    <th class="text-center" style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); padding: 8px 12px;">Quantity</th>
                                    <th class="text-right" style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); padding: 8px 12px;">Unit Price</th>
                                    <th class="text-right" style="font-size: 12px; text-transform: uppercase; color: var(--text-muted); padding: 8px 12px;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($items)): ?>
                                    <?php foreach ($items as $item):
                                        $qty        = (int)($item['quantity'] ?? 1);
                                        $unit_price = (float)($item['unit_price'] ?? 0);
                                        $subtotal   = $qty * $unit_price;
                                        $name       = $item['product_name'] ?? ('Product #' . ($item['product_id'] ?? ''));
                                    ?>
                                        <tr>
                                            <td style="padding: 12px; vertical-align: middle;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-size: 18px;">🌱</span>
                                                    <strong style="color: var(--secondary-color); font-size: 14px;">
                                                        <?php echo htmlspecialchars($name); ?>
                                                    </strong>
                                                </div>
                                            </td>
                                            <td class="text-center" style="padding: 12px; vertical-align: middle;">
                                                <span class="badge badge-verified" style="font-size: 12px; padding: 2px 8px;">
                                                    × <?php echo $qty; ?>
                                                </span>
                                            </td>
                                            <td class="text-right" style="padding: 12px; vertical-align: middle; color: var(--text-muted);">
                                                ৳ <?php echo number_format($unit_price, 2); ?>
                                            </td>
                                            <td class="text-right" style="padding: 12px; vertical-align: middle; font-weight: 700; color: var(--secondary-color);">
                                                ৳ <?php echo number_format($subtotal, 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 14px;">
                                            Order items details recorded.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order Card Footer: Shipping, Phone & Grand Total -->
                <div style="background-color: #f0fdf4; padding: 16px 22px; border-top: 1px solid #dcfce7; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px;">
                    <div style="font-size: 13px; color: var(--text-muted); line-height: 1.6; max-width: 600px;">
                        <div>
                            <strong style="color: var(--secondary-color);">📍 Delivery Address:</strong>
                            <?php echo htmlspecialchars($address); ?>
                        </div>
                        <div style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 16px;">
                            <span>
                                <strong style="color: var(--secondary-color);">📞 Phone:</strong>
                                <?php echo htmlspecialchars($phone); ?>
                            </span>
                            <span>
                                <strong style="color: var(--secondary-color);">💳 Payment:</strong>
                                <?php echo htmlspecialchars($payment); ?>
                            </span>
                        </div>
                    </div>

                    <div style="text-align: right; min-width: 180px;">
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">
                            <span style="display: flex; justify-content: space-between; gap: 12px; margin-bottom: 2px;">
                                <span>Items Subtotal:</span>
                                <span>৳ <?php echo number_format($o_items_subtotal, 2); ?></span>
                            </span>
                            <span style="display: flex; justify-content: space-between; gap: 12px; margin-bottom: 2px; color: var(--status-success); font-weight: 600;">
                                <span>🎉 2% Special Discount:</span>
                                <span>-৳ <?php echo number_format($o_discount, 2); ?></span>
                            </span>
                            <?php if ($o_delivery_fee > 0.01): ?>
                            <span style="display: flex; justify-content: space-between; gap: 12px; margin-bottom: 2px;">
                                <span>Delivery Charge:</span>
                                <span>৳ <?php echo number_format($o_delivery_fee, 2); ?></span>
                            </span>
                            <?php else: ?>
                            <span style="display: flex; justify-content: space-between; gap: 12px; margin-bottom: 2px;">
                                <span>Delivery Charge:</span>
                                <span style="color: var(--primary-color); font-weight: 600;">Free</span>
                            </span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size: 11px; color: var(--text-muted); display: block; text-transform: uppercase; letter-spacing: 0.5px;">Grand Total</span>
                        <span style="font-size: 20px; font-weight: 800; color: var(--primary-color);">
                            ৳ <?php echo number_format($o_grand_total, 2); ?>
                        </span>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
