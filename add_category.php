<?php
// add_category.php — Lets the admin create new product categories

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only administrators can manage categories
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php?msg=access_denied');
    exit;
}

$page_title      = 'Manage Categories - HaatLink';
$error_message   = '';
$success_message = '';

// Flash message handling for dynamic success/error banner on refresh
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $success_message = 'Category deleted successfully!';
}
if (!empty($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (!empty($_SESSION['flash_error'])) {
    $error_message = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle category deletion
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$cat_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if (($action === 'delete' || $action === 'delete_category') && $cat_id > 0) {
    try {
        // Check if category has existing linked products
        $check_prod = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $check_prod->execute([$cat_id]);
        $linked_products = (int)$check_prod->fetchColumn();

        if ($linked_products > 0) {
            $_SESSION['flash_error'] = "Cannot delete category with existing products.";
        } else {
            // Delete the category if no products are linked
            $del = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $del->execute([$cat_id]);

            if ($del->rowCount() > 0) {
                $_SESSION['flash_message'] = "Category deleted successfully!";
            } else {
                $_SESSION['flash_error'] = "Category not found or already deleted.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database error: " . htmlspecialchars($e->getMessage());
    }
    header('Location: add_category.php');
    exit;
}

// Handle new category form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_name'])) {
    $category_name = trim($_POST['category_name'] ?? '');

    if (empty($category_name)) {
        $error_message = 'Please enter a category name.';
    } elseif (strlen($category_name) < 3) {
        $error_message = 'Category name must be at least 3 characters.';
    } else {
        try {
            // Check if a category with this name already exists (case-insensitive)
            $check = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
            $check->execute([$category_name]);

            if ($check->rowCount() > 0) {
                $error_message = 'A category with this name already exists.';
            } else {
                // Insert the new category record
                $insert = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $insert->execute([$category_name]);

                $success_message = "Category '" . htmlspecialchars($category_name) . "' added successfully!";
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Fetch all categories with a count of how many products belong to each
try {
    $categories = $pdo->query("
        SELECT c.id, c.name, c.created_at, COUNT(p.id) AS total_products
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        GROUP BY c.id, c.name, c.created_at
        ORDER BY c.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Product Categories</h1>
        <p class="page-subtitle">Define marketplace categories for organizing producer inventory.</p>
    </div>
    <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">&larr; Back to Admin Dashboard</a>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <span>✅</span>
        <div><?php echo htmlspecialchars($success_message); ?></div>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?php echo htmlspecialchars($error_message); ?></div>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1.8fr; gap: 25px; align-items: start;">

    <!-- Add Category Form -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">+ Add New Category</h2>
        </div>

        <form action="add_category.php" method="POST">
            <div class="form-group">
                <label for="category_name" class="form-label">Category Name</label>
                <input type="text" id="category_name" name="category_name" class="form-control"
                       placeholder="e.g. Spices &amp; Organic Herbs" required autofocus>
                <div class="form-help">Must be unique across all marketplace categories.</div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                Save Category
            </button>
        </form>
    </div>

    <!-- Existing Categories Table -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Existing Categories</h2>
            <span class="badge badge-role"><?php echo count($categories); ?> Categories</span>
        </div>

        <?php if (empty($categories)): ?>
            <p style="color: var(--text-muted); font-size: 14px;">No categories created yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Total Products</th>
                            <th>Created Date</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($cat['id']); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo ($cat['total_products'] > 0) ? 'badge-verified' : 'badge-role'; ?>">
                                        <?php echo (int)$cat['total_products']; ?> products
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($cat['created_at'])); ?></td>
                                <td class="text-right">
                                    <?php if ($cat['total_products'] > 0): ?>
                                        <button type="button" class="btn btn-danger btn-sm"
                                                onclick="alert('Cannot delete category with existing products.');"
                                                style="opacity: 0.55; cursor: not-allowed;"
                                                title="Cannot delete category with existing products.">
                                            🗑️ Delete
                                        </button>
                                    <?php else: ?>
                                        <a href="add_category.php?action=delete&id=<?php echo (int)$cat['id']; ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this item?');">
                                            🗑️ Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
