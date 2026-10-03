<?php
// admin_dashboard.php — Lets the admin verify/reject pending producers and products, and view platform stats

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only administrators can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php?msg=access_denied');
    exit;
}

$page_title     = 'Admin Dashboard - HaatLink';
$action_message = '';
$action_error   = '';

// Auto-migrate enum columns if needed to support 'rejected' and 'pending' statuses smoothly
try {
    $colUser = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'status'")->fetch();
    if ($colUser && strpos($colUser['Type'], "'rejected'") === false) {
        $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('pending', 'verified', 'suspended', 'rejected') NOT NULL DEFAULT 'pending'");
    }
} catch (Throwable $e) {
    // Graceful fallback if user lacks ALTER privileges or column already updated
}

try {
    $colProd = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'status'")->fetch();
    if ($colProd && (strpos($colProd['Type'], "'rejected'") === false || strpos($colProd['Type'], "'pending'") === false)) {
        $pdo->exec("ALTER TABLE `products` MODIFY COLUMN `status` ENUM('pending', 'active', 'out_of_stock', 'rejected') NOT NULL DEFAULT 'pending'");
    }
} catch (Throwable $e) {
    // Graceful fallback if user lacks ALTER privileges or column already updated
}

// Action Handler Logic: Catch action requests (via GET or POST)
$action     = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id    = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? $_POST['id'] ?? $_GET['id'] ?? $_POST['producer_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);

// Backward compatibility for legacy action names
if ($action === 'approve' && $user_id) {
    $action = 'approve_producer';
} elseif ($action === 'reject' && $user_id) {
    $action = 'reject_producer';
}

if (!empty($action)) {
    try {
        if ($action === 'approve_producer' && $user_id) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'verified' WHERE id = ? AND role = 'producer'");
            $stmt->execute([$user_id]);
            $_SESSION['flash_message'] = "Producer (ID: #$user_id) has been approved successfully.";
            header("Location: admin_dashboard.php?msg=success");
            exit;
        } elseif ($action === 'reject_producer' && $user_id) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'producer'");
            $stmt->execute([$user_id]);
            $_SESSION['flash_message'] = "Producer (ID: #$user_id) has been rejected.";
            header("Location: admin_dashboard.php?msg=success");
            exit;
        } elseif ($action === 'approve_product' && $product_id) {
            $stmt = $pdo->prepare("UPDATE products SET status = 'active' WHERE id = ?");
            $stmt->execute([$product_id]);
            $_SESSION['flash_message'] = "Product (ID: #$product_id) has been approved and is now active.";
            header("Location: admin_dashboard.php?msg=success");
            exit;
        } elseif ($action === 'reject_product' && $product_id) {
            $stmt = $pdo->prepare("UPDATE products SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$product_id]);
            $_SESSION['flash_message'] = "Product (ID: #$product_id) has been rejected.";
            header("Location: admin_dashboard.php?msg=success");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database error: " . htmlspecialchars($e->getMessage());
        header("Location: admin_dashboard.php?msg=error");
        exit;
    }
}

// Flash Messages handling for dynamic success/error alert
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'success') {
        if (!empty($_SESSION['flash_message'])) {
            $action_message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
        } else {
            $action_message = "Action executed successfully! Database status has been updated.";
        }
    } elseif ($_GET['msg'] === 'deleted') {
        if (!empty($_SESSION['flash_message'])) {
            $action_message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
        } else {
            $action_message = "Item deleted successfully.";
        }
    } elseif ($_GET['msg'] === 'error') {
        if (!empty($_SESSION['flash_error'])) {
            $action_error = $_SESSION['flash_error'];
            unset($_SESSION['flash_error']);
        } else {
            $action_error = "An error occurred while executing the action.";
        }
    }
} elseif (!empty($_SESSION['flash_message'])) {
    $action_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
} elseif (!empty($_SESSION['flash_error'])) {
    $action_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Fetch platform-wide statistics for the KPI cards
try {
    $total_producers       = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'producer'")->fetchColumn();
    $pending_count         = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'producer' AND status = 'pending'")->fetchColumn();
    $verified_count        = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'producer' AND status = 'verified'")->fetchColumn();
    $total_products        = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $pending_product_count = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'pending'")->fetchColumn();

    // Calculate total 2% platform commission from all successfully delivered orders
    $platform_revenue_row  = $pdo->query("SELECT COALESCE(SUM(total_amount * 0.02), 0) AS total_revenue FROM orders WHERE status = 'Delivered'")->fetch();
    $platform_revenue      = (float)($platform_revenue_row['total_revenue'] ?? 0);

    // Fetch all producers currently waiting for verification
    $pending_stmt = $pdo->prepare("
        SELECT id, name, email, status, created_at
        FROM users
        WHERE role = 'producer' AND status = 'pending'
        ORDER BY created_at ASC
    ");
    $pending_stmt->execute();
    $pending_producers = $pending_stmt->fetchAll();

    // Fetch verified producers along with how many products each has listed
    $verified_stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.status, u.created_at, COUNT(p.id) AS product_count
        FROM users u
        LEFT JOIN products p ON u.id = p.producer_id
        WHERE u.role = 'producer' AND u.status = 'verified'
        GROUP BY u.id, u.name, u.email, u.status, u.created_at
        ORDER BY u.created_at DESC
    ");
    $verified_stmt->execute();
    $verified_producers = $verified_stmt->fetchAll();

    // Fetch pending products waiting for approval
    $pending_products_stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.stock_quantity, p.status, p.image_url, p.created_at,
               u.name AS producer_name,
               c.name AS category_name
        FROM products p
        LEFT JOIN users u ON p.producer_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'pending'
        ORDER BY p.created_at ASC
    ");
    $pending_products_stmt->execute();
    $pending_products = $pending_products_stmt->fetchAll();

    // Fetch all products for complete catalog management and approval status toggle
    $all_products_stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.stock_quantity, p.status, p.created_at,
               u.name AS producer_name,
               c.name AS category_name
        FROM products p
        LEFT JOIN users u ON p.producer_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.created_at DESC
    ");
    $all_products_stmt->execute();
    $all_products = $all_products_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Administrator Panel</h1>
        <p class="page-subtitle">Manage producer verification, review product listings, and monitor marketplace activity.</p>
    </div>
    <div class="page-header-actions">
        <a href="add_category.php" class="btn btn-outline btn-sm">Manage Categories</a>
        <a href="shop.php" class="btn btn-secondary btn-sm" target="_blank">View Live Shop ↗</a>
    </div>
</div>

<!-- Action Feedback / Dynamic Flash Alert -->
<?php if (!empty($action_message)): ?>
    <div class="alert alert-success">
        <span>✅</span>
        <div><?php echo htmlspecialchars($action_message); ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($action_error)): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?php echo htmlspecialchars($action_error); ?></div>
    </div>
<?php endif; ?>

<!-- KPI Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon amber">⏳</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo (int)$pending_count; ?></div>
            <div class="stat-label">Pending Producers</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber">📋</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo (int)$pending_product_count; ?></div>
            <div class="stat-label">Pending Products</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">🌾</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo (int)$verified_count; ?></div>
            <div class="stat-label">Verified Producers</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">📦</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo (int)$total_products; ?></div>
            <div class="stat-label">Total Listed Products</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">👥</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo (int)$total_producers; ?></div>
            <div class="stat-label">Total Producer Accounts</div>
        </div>
    </div>

    <div class="stat-card stat-card--revenue">
        <div class="stat-icon teal">💰</div>
        <div class="stat-info">
            <div class="stat-value stat-value--revenue">৳<?php echo number_format($platform_revenue, 2); ?></div>
            <div class="stat-label">Platform Revenue <span class="stat-badge">2% Commission</span></div>
        </div>
    </div>
</div>

<!-- Pending Producer Verification Queue -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Pending Producer Applications</h2>
            <span style="font-size: 13px; color: var(--text-muted);">
                Producers must be approved before they can list inventory.
            </span>
        </div>
        <span class="badge badge-pending"><?php echo count($pending_producers); ?> Pending</span>
    </div>

    <?php if (empty($pending_producers)): ?>
        <div class="empty-state">
            <div class="empty-icon">🎉</div>
            <h3>All Caught Up!</h3>
            <p>No producer accounts are currently awaiting verification.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Producer Name</th>
                        <th>Email Address</th>
                        <th>Registered Date</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_producers as $producer): ?>
                        <tr>
                            <td><strong>#<?php echo (int)$producer['id']; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($producer['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($producer['email']); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($producer['created_at'])); ?></td>
                            <td>
                                <span class="badge badge-pending">
                                    <?php echo htmlspecialchars($producer['status']); ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="action-group" style="justify-content: flex-end;">
                                    <!-- Approve this producer -->
                                    <a href="admin_dashboard.php?action=approve_producer&id=<?php echo (int)$producer['id']; ?>"
                                       class="btn btn-primary btn-sm"
                                       onclick="return confirm('Approve producer #<?php echo (int)$producer['id']; ?> (<?php echo htmlspecialchars(addslashes($producer['name'])); ?>)? They will be able to add products immediately.');">
                                        ✓ Approve
                                    </a>

                                    <!-- Reject this producer -->
                                    <a href="admin_dashboard.php?action=reject_producer&id=<?php echo (int)$producer['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Reject producer #<?php echo (int)$producer['id']; ?> (<?php echo htmlspecialchars(addslashes($producer['name'])); ?>)?');">
                                        ✕ Reject
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

<!-- Pending Product Approval Queue -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Pending Product Approvals</h2>
            <span style="font-size: 13px; color: var(--text-muted);">
                Review and approve newly submitted products before they appear in the public shop.
            </span>
        </div>
        <span class="badge badge-pending"><?php echo count($pending_products); ?> Pending</span>
    </div>

    <?php if (empty($pending_products)): ?>
        <div class="empty-state">
            <div class="empty-icon">✅</div>
            <h3>No Pending Products</h3>
            <p>All submitted products have been reviewed and processed.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Producer</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_products as $product): ?>
                        <tr>
                            <td><strong>#<?php echo (int)$product['id']; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['producer_name'] ?? 'Unknown'); ?></td>
                            <td>
                                <span class="badge badge-role">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td>৳ <?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo (int)$product['stock_quantity']; ?> units</td>
                            <td>
                                <span class="badge badge-pending"><?php echo htmlspecialchars($product['status']); ?></span>
                            </td>
                            <td class="text-right">
                                <div class="action-group" style="justify-content: flex-end;">
                                    <!-- Approve this product -->
                                    <a href="admin_dashboard.php?action=approve_product&id=<?php echo (int)$product['id']; ?>"
                                       class="btn btn-primary btn-sm"
                                       onclick="return confirm('Approve product #<?php echo (int)$product['id']; ?> (<?php echo htmlspecialchars(addslashes($product['name'])); ?>)? It will become active in the shop.');">
                                        ✓ Approve
                                    </a>

                                    <!-- Reject this product -->
                                    <a href="admin_dashboard.php?action=reject_product&id=<?php echo (int)$product['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Reject product #<?php echo (int)$product['id']; ?> (<?php echo htmlspecialchars(addslashes($product['name'])); ?>)?');">
                                        ✕ Reject
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

<!-- All Marketplace Products Overview -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">All Marketplace Products</h2>
            <span style="font-size: 13px; color: var(--text-muted);">
                Monitor listed products and update approval status across the platform.
            </span>
        </div>
        <span class="badge badge-verified"><?php echo count($all_products); ?> Total</span>
    </div>

    <?php if (empty($all_products)): ?>
        <p style="color: var(--text-muted); font-size: 14px;">No products found in the catalog.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Producer</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_products as $p): ?>
                        <tr>
                            <td>#<?php echo (int)$p['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['producer_name'] ?? 'Unknown'); ?></td>
                            <td>
                                <span class="badge badge-role">
                                    <?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td>৳ <?php echo number_format($p['price'], 2); ?></td>
                            <td><?php echo (int)$p['stock_quantity']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($p['status']); ?>">
                                    <?php echo htmlspecialchars($p['status']); ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="action-group" style="justify-content: flex-end;">
                                    <?php if ($p['status'] !== 'active'): ?>
                                        <a href="admin_dashboard.php?action=approve_product&id=<?php echo (int)$p['id']; ?>"
                                           class="btn btn-primary btn-sm"
                                           onclick="return confirm('Approve product #<?php echo (int)$p['id']; ?> (<?php echo htmlspecialchars(addslashes($p['name'])); ?>)?');">
                                            ✓ Approve
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($p['status'] !== 'rejected'): ?>
                                        <a href="admin_dashboard.php?action=reject_product&id=<?php echo (int)$p['id']; ?>"
                                           class="btn btn-secondary btn-sm"
                                           onclick="return confirm('Reject product #<?php echo (int)$p['id']; ?> (<?php echo htmlspecialchars(addslashes($p['name'])); ?>)?');">
                                            ✕ Reject
                                        </a>
                                    <?php endif; ?>
                                    <a href="delete_product.php?id=<?php echo (int)$p['id']; ?>"
                                       class="btn btn-danger btn-sm"
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

<!-- Verified Producers Directory -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Verified Producers Directory</h2>
            <span style="font-size: 13px; color: var(--text-muted);">
                Active producers currently verified to trade on HaatLink.
            </span>
        </div>
        <span class="badge badge-verified"><?php echo count($verified_producers); ?> Verified</span>
    </div>

    <?php if (empty($verified_producers)): ?>
        <p style="color: var(--text-muted); font-size: 14px;">No approved producers found yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producer Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Products Listed</th>
                        <th>Joined On</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verified_producers as $vp): ?>
                        <tr>
                            <td>#<?php echo (int)$vp['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($vp['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($vp['email']); ?></td>
                            <td>
                                <span class="badge badge-verified"><?php echo htmlspecialchars($vp['status']); ?></span>
                            </td>
                            <td><strong><?php echo (int)$vp['product_count']; ?></strong> items</td>
                            <td><?php echo date('M d, Y', strtotime($vp['created_at'])); ?></td>
                            <td class="text-right">
                                <div class="action-group" style="justify-content: flex-end;">
                                    <a href="admin_dashboard.php?action=reject_producer&id=<?php echo (int)$vp['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Reject/suspend producer #<?php echo (int)$vp['id']; ?> (<?php echo htmlspecialchars(addslashes($vp['name'])); ?>)?');">
                                        ✕ Suspend/Reject
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
