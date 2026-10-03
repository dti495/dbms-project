<?php
// producer_dashboard.php — Shows the producer's account status, aggregate stats, and listed products

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only producers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'producer') {
    header('Location: login.php?msg=access_denied');
    exit;
}

$producer_id = (int)$_SESSION['user_id'];
$page_title  = 'Producer Dashboard - HaatLink';

// Handle product deletion request if triggered directly
if ((isset($_GET['action']) && $_GET['action'] === 'delete_product') || (isset($_POST['action']) && $_POST['action'] === 'delete_product')) {
    $delete_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($delete_id > 0) {
        try {
            $del_stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND producer_id = ?");
            $del_stmt->execute([$delete_id, $producer_id]);
            header('Location: producer_dashboard.php?msg=deleted');
            exit;
        } catch (PDOException $e) {
            header('Location: producer_dashboard.php?msg=error');
            exit;
        }
    }
}

// Handle order status update request from producer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = strtolower(trim($_POST['status'] ?? ''));
    $allowed_statuses = ['pending', 'processing', 'delivered', 'cancelled'];

    if ($order_id > 0 && in_array($new_status, $allowed_statuses, true)) {
        try {
            // Verify this producer has items in this order
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ? AND (oi.producer_id = ? OR p.producer_id = ?)
            ");
            $checkStmt->execute([$order_id, $producer_id, $producer_id]);
            if ($checkStmt->fetchColumn() > 0) {
                $updStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $updStmt->execute([$new_status, $order_id]);
                $_SESSION['flash_message'] = "Order #" . sprintf('%05d', $order_id) . " status updated to " . ucfirst($new_status) . " successfully!";
                header('Location: producer_dashboard.php?msg=order_updated#received-orders');
                exit;
            } else {
                $_SESSION['flash_error'] = "You do not have permission to update this order.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Failed to update order status: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid order status specified.";
    }
}

try {
    // Fetch the producer's current account status directly from the database
    $user_stmt = $pdo->prepare("SELECT name, email, status FROM users WHERE id = ?");
    $user_stmt->execute([$producer_id]);
    $producer = $user_stmt->fetch();

    if (!$producer) {
        header('Location: logout.php');
        exit;
    }

    $account_status = $producer['status'];

    // Keep session statuses in sync with the database
    $_SESSION['user_status'] = $account_status;
    $_SESSION['status']      = $account_status;

    // DBMS Aggregates for Producer Overview Cards:
    // 1. Total Listed Products (COUNT(*))
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE producer_id = ?");
    $count_stmt->execute([$producer_id]);
    $total_listed_products = (int)$count_stmt->fetchColumn();

    // 2. Total Stock Quantity (SUM(stock))
    $stock_stmt = $pdo->prepare("SELECT COALESCE(SUM(stock_quantity), 0) FROM products WHERE producer_id = ?");
    $stock_stmt->execute([$producer_id]);
    $total_stock_quantity = (int)$stock_stmt->fetchColumn();

    // 3. Fetch all products added by the logged-in producer
    $prod_stmt = $pdo->prepare("
        SELECT products.*, categories.name AS category_name
        FROM products
        JOIN categories ON products.category_id = categories.id
        WHERE products.producer_id = ?
        ORDER BY products.id DESC
    ");
    $prod_stmt->execute([$producer_id]);
    $my_products = $prod_stmt->fetchAll();

    // 4. Fetch received customer orders for products sold by this producer
    $orders_stmt = $pdo->prepare("
        SELECT 
            oi.id AS item_id,
            oi.order_id,
            oi.quantity,
            oi.unit_price,
            (oi.quantity * oi.unit_price) AS item_subtotal,
            p.name AS product_name,
            o.status AS order_status,
            o.created_at AS order_date,
            o.total_amount AS order_grand_total,
            o.shipping_name,
            o.shipping_phone,
            o.shipping_address AS address,
            o.shipping_address,
            u.name AS customer_name,
            o.shipping_phone AS customer_phone
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        JOIN users u ON u.id = o.customer_id
        WHERE oi.producer_id = ? OR p.producer_id = ?
        ORDER BY o.created_at DESC, oi.id DESC
    ");
    $orders_stmt->execute([$producer_id, $producer_id]);
    $received_orders = $orders_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Unverified Account Alert Banner -->
<?php if (($_SESSION['status'] ?? '') === 'pending' || $account_status === 'pending'): ?>
    <div class="alert alert-warning" style="padding: 16px 20px; border-radius: var(--radius-md); margin-bottom: 25px; align-items: flex-start;">
        <span style="font-size: 24px; line-height: 1;">⏳</span>
        <div>
            <strong>Notice:</strong> Your account is pending admin verification. You can add products, but they will require admin approval before appearing in the marketplace.
        </div>
    </div>
<?php elseif ($account_status === 'suspended'): ?>
    <div class="alert alert-danger" style="margin-bottom: 25px;">
        <span>🚫</span>
        <div>
            <strong>Account Suspended:</strong> Your producer privileges have been revoked. Please contact platform support.
        </div>
    </div>
<?php endif; ?>

<!-- Flash Messages -->
<?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success">
        <span>✅</span>
        <div>Product submitted successfully! <?php echo ($account_status === 'pending') ? 'It will be visible in the shop once approved by an administrator.' : ''; ?></div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
    <div class="alert alert-success">
        <span>✅</span>
        <div>Product details updated successfully!</div>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="alert alert-success">
        <span>🗑️</span>
        <div><?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?></div>
    </div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="alert alert-success">
        <span>🗑️</span>
        <div>Product has been deleted from your inventory.</div>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    </div>
<?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'error'): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <div>An error occurred while processing your product request.</div>
    </div>
<?php elseif (isset($_GET['error']) && $_GET['error'] === 'producers_cannot_order'): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <div>Producers cannot access the shopping cart or place orders. Please switch to a customer account to purchase products.</div>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Producer Dashboard</h1>
        <p class="page-subtitle">Welcome back, <strong><?php echo htmlspecialchars($producer['name']); ?></strong>!</p>
    </div>
    <div>
        <?php if ($account_status !== 'suspended'): ?>
            <a href="add_product.php" class="btn btn-primary">+ Add New Product</a>
        <?php else: ?>
            <button class="btn btn-primary disabled" disabled title="Account suspended">
                + Add New Product (Locked)
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Producer Overview Cards (DBMS Aggregates) -->
<div class="stats-grid">
    <!-- Total Listed Products (COUNT(*)) -->
    <div class="stat-card">
        <div class="stat-icon blue">📦</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_listed_products; ?></div>
            <div class="stat-label">Total Listed Products</div>
        </div>
    </div>

    <!-- Total Stock Quantity (SUM(stock)) -->
    <div class="stat-card">
        <div class="stat-icon green">📊</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($total_stock_quantity); ?></div>
            <div class="stat-label">Total Stock Quantity</div>
        </div>
    </div>

    <!-- Total Customer Orders Received -->
    <div class="stat-card">
        <div class="stat-icon purple">📋</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo count($received_orders); ?></div>
            <div class="stat-label">Received Orders</div>
        </div>
    </div>

    <!-- Account Verification Status (Verified or Pending Approval) -->
    <div class="stat-card">
        <div class="stat-icon <?php echo ($account_status === 'verified') ? 'green' : (($account_status === 'pending') ? 'amber' : 'purple'); ?>">
            <?php echo ($account_status === 'verified') ? '🛡️' : (($account_status === 'pending') ? '⏳' : '🚫'); ?>
        </div>
        <div class="stat-info">
            <div class="stat-value" style="font-size: 20px;">
                <?php if ($account_status === 'verified'): ?>
                    <span style="color: var(--status-success); font-weight: 700;">Verified</span>
                <?php elseif ($account_status === 'pending'): ?>
                    <span style="color: var(--status-warning); font-weight: 700;">Pending Approval</span>
                <?php else: ?>
                    <span style="color: var(--status-danger); font-weight: 700;"><?php echo ucfirst(htmlspecialchars($account_status)); ?></span>
                <?php endif; ?>
            </div>
            <div class="stat-label">Account Verification Status</div>
        </div>
    </div>
</div>

<!-- Received Customer Orders Section -->
<div class="card" id="received-orders" style="margin-bottom: 30px;">
    <div class="card-header">
        <div>
            <h2 class="card-title">Received Customer Orders</h2>
            <span style="font-size: 13px; color: var(--text-muted);">
                Review incoming customer orders for your products and manage fulfillment statuses.
            </span>
        </div>
        <span class="badge badge-verified"><?php echo count($received_orders); ?> Orders</span>
    </div>

    <?php if (empty($received_orders)): ?>
        <div class="empty-state" style="padding: 45px 20px;">
            <div class="empty-icon">📋</div>
            <h3>No Customer Orders Received Yet</h3>
            <p style="color: var(--text-muted); max-width: 450px; margin: 0 auto;">
                When customers purchase items from your inventory, their order details and shipping instructions will appear right here.
            </p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="white-space: nowrap;">Order ID</th>
                        <th>Customer Name &amp; Phone</th>
                        <th>Delivery Address</th>
                        <th>Product Ordered &amp; Qty</th>
                        <th class="text-right">Total Amount</th>
                        <th style="white-space: nowrap;">Order Date</th>
                        <th style="min-width: 200px;">Current Status &amp; Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($received_orders as $ord):
                        $o_id     = (int)$ord['order_id'];
                        $c_name   = !empty($ord['shipping_name']) ? $ord['shipping_name'] : (!empty($ord['customer_name']) ? $ord['customer_name'] : 'Customer');
                        $c_phone  = !empty($ord['shipping_phone']) ? $ord['shipping_phone'] : (!empty($ord['customer_phone']) ? $ord['customer_phone'] : 'N/A');
                        $c_addr   = !empty($ord['shipping_address']) ? $ord['shipping_address'] : (!empty($ord['address']) ? $ord['address'] : 'N/A');
                        $p_name   = $ord['product_name'] ?? 'Product';
                        $p_qty    = (int)($ord['quantity'] ?? 1);
                        $p_price  = (float)($ord['unit_price'] ?? 0);
                        $p_total  = (float)($ord['item_subtotal'] ?? ($p_qty * $p_price));
                        $o_date   = !empty($ord['order_date']) ? date('d M Y, h:i A', strtotime($ord['order_date'])) : 'N/A';
                        $curr_stat= strtolower($ord['order_status'] ?? 'pending');

                        // Bill breakdown: item subtotal, 2% discount, grand total from DB
                        $o_grand_total   = (float)($ord['order_grand_total'] ?? 0);
                        $o_item_subtotal = $p_total;  // this order row's product subtotal
                        $o_discount      = round($o_grand_total / 0.98 * 0.02, 2); // approx. from stored total

                        if ($curr_stat === 'delivered') {
                            $badge_cls = 'badge-delivered';
                        } elseif ($curr_stat === 'processing') {
                            $badge_cls = 'badge-processing';
                        } elseif ($curr_stat === 'cancelled') {
                            $badge_cls = 'badge-cancelled';
                        } else {
                            $badge_cls = 'badge-pending';
                        }
                    ?>
                        <tr>
                            <!-- Order ID -->
                            <td style="vertical-align: middle;">
                                <strong style="font-family: monospace; font-size: 14px; color: var(--secondary-color); background: #f8fafc; padding: 3px 7px; border-radius: 4px; border: 1px solid var(--border-color);">
                                    <?php echo sprintf('#%05d', $o_id); ?>
                                </strong>
                            </td>

                            <!-- Customer Name & Phone Number -->
                            <td style="vertical-align: middle;">
                                <strong style="color: var(--secondary-color); font-size: 14px;"><?php echo htmlspecialchars($c_name); ?></strong>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                    📞 <?php echo htmlspecialchars($c_phone); ?>
                                </div>
                            </td>

                            <!-- Delivery Address -->
                            <td style="vertical-align: middle; max-width: 220px;">
                                <div style="font-size: 12.5px; color: var(--text-muted); line-height: 1.4;">
                                    📍 <?php echo htmlspecialchars($c_addr); ?>
                                </div>
                            </td>

                            <!-- Product Ordered & Quantity -->
                            <td style="vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>🌱</span>
                                    <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($p_name); ?></strong>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                    Qty: <strong><?php echo $p_qty; ?></strong> × ৳ <?php echo number_format($p_price, 2); ?>
                                </div>
                            </td>

                            <!-- Total Amount -->
                            <td class="text-right" style="vertical-align: middle; font-size: 13px;">
                                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 2px;">
                                    Subtotal: ৳ <?php echo number_format($p_total, 2); ?>
                                </div>
                                <div style="font-size: 11px; color: var(--status-success); font-weight: 600; margin-bottom: 2px;">
                                    &#127881; -2%: -৳ <?php echo number_format($o_discount, 2); ?>
                                </div>
                                <div style="font-weight: 800; color: var(--primary-color); font-size: 14px;">
                                    ৳ <?php echo number_format($o_grand_total, 2); ?>
                                </div>
                            </td>

                            <!-- Order Date -->
                            <td style="vertical-align: middle; font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                <?php echo htmlspecialchars($o_date); ?>
                            </td>

                            <!-- Current Status & Action -->
                            <td style="vertical-align: middle;">
                                <div style="margin-bottom: 6px;">
                                    <span class="badge <?php echo $badge_cls; ?>" style="font-size: 11px; padding: 3px 8px;">
                                        <?php echo ucfirst(htmlspecialchars($curr_stat)); ?>
                                    </span>
                                </div>
                                <form method="POST" action="producer_dashboard.php#received-orders" style="display: flex; gap: 4px; align-items: center;">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo $o_id; ?>">
                                    <select name="status" class="form-control" style="padding: 4px 6px; font-size: 12px; height: 30px; width: 115px; border-radius: 4px;">
                                        <option value="pending" <?php echo ($curr_stat === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo ($curr_stat === 'processing') ? 'selected' : ''; ?>>Processing</option>
                                        <option value="delivered" <?php echo ($curr_stat === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo ($curr_stat === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 3px 8px; font-size: 11px; height: 30px; font-weight: 600;">
                                        Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Product Inventory Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">My Product Inventory</h2>
            <span style="font-size: 13px; color: var(--text-muted);">
                Manage product details, monitor inventory stock levels, and review approval statuses.
            </span>
        </div>
        <span class="badge badge-role"><?php echo $total_listed_products; ?> Items</span>
    </div>

    <?php if (empty($my_products)): ?>
        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h3>No Products Listed Yet</h3>
            <p>
                <?php if ($account_status === 'pending'): ?>
                    Your account is pending verification, but you can start adding products now! They will be reviewed by an administrator.
                <?php else: ?>
                    Click the button below to add your first product to the marketplace.
                <?php endif; ?>
            </p>
            <?php if ($account_status !== 'suspended'): ?>
                <a href="add_product.php" class="btn btn-primary btn-sm">+ Add New Product</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price (৳)</th>
                        <th>Current Stock</th>
                        <th>Approval Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_products as $p): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                <?php if (!empty($p['description'])): ?>
                                    <div class="product-desc-cell">
                                        <?php echo htmlspecialchars($p['description']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-role">
                                    <?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td><strong>৳ <?php echo number_format($p['price'], 2); ?></strong></td>
                            <td>
                                <?php if ($p['stock_quantity'] > 0): ?>
                                    <span style="color: var(--status-success); font-weight: 600;">
                                        <?php echo (int)$p['stock_quantity']; ?> units
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--status-danger); font-weight: 600;">
                                        0 (Out of Stock)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status = strtolower($p['status'] ?? 'pending');
                                if ($status === 'active'): ?>
                                    <span class="badge badge-active" title="Visible in shop">Active</span>
                                <?php elseif ($status === 'pending'): ?>
                                    <span class="badge badge-pending" title="Awaiting admin approval">Pending</span>
                                <?php elseif ($status === 'rejected'): ?>
                                    <span class="badge badge-rejected" title="Rejected by admin">Rejected</span>
                                <?php else: ?>
                                    <span class="badge badge-<?php echo htmlspecialchars($status); ?>">
                                        <?php echo ucfirst(htmlspecialchars($status)); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <div class="action-group" style="justify-content: flex-end;">
                                    <a href="edit_product.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-outline btn-sm">
                                        ✏️ Edit
                                    </a>
                                    <a href="delete_product.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this item?');">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
