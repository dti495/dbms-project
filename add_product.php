<?php
// add_product.php — Lets a verified producer add a new product to the marketplace

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

// Check current account status — only 'verified' producers can add products
try {
    $status_stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $status_stmt->execute([$producer_id]);
    $current_status = $status_stmt->fetchColumn();

    if ($current_status === 'suspended') {
        header('Location: producer_dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$page_title      = 'Add New Product - HaatLink';
$error_message   = '';
$success_message = '';

// Fetch all categories for the dropdown
try {
    $cat_stmt   = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

// Handle product form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $category_id    = (int)($_POST['category_id'] ?? 0);
    $price          = trim($_POST['price'] ?? '');
    $stock_quantity = trim($_POST['stock_quantity'] ?? '');
    $status         = trim($_POST['status'] ?? 'active');
    $description    = trim($_POST['description'] ?? '');
    $image_url      = trim($_POST['image_url'] ?? '');  // Optional product image URL


    // Validate required fields
    if (empty($name) || empty($category_id) || $price === '' || $stock_quantity === '') {
        $error_message = 'Please fill in all required fields (Name, Category, Price, Stock).';
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        $error_message = 'Price must be a positive number.';
    } elseif (!is_numeric($stock_quantity) || (int)$stock_quantity < 0) {
        $error_message = 'Stock quantity cannot be negative.';
    } elseif (!in_array($status, ['active', 'out_of_stock', 'pending'])) {
        $error_message = 'Invalid product status.';
    } else {
        try {
            // If producer is pending verification, products require admin approval
            if ($current_status === 'pending') {
                $status = 'pending';
            } elseif ((int)$stock_quantity === 0) {
                // If stock is zero, force the status to 'out_of_stock'
                $status = 'out_of_stock';
            }

            // Insert the new product linked to this producer and the chosen category
            $insert_sql = "
                INSERT INTO products (producer_id, category_id, name, description, price, stock_quantity, status, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $pdo->prepare($insert_sql);
            $stmt->execute([
                $producer_id,
                $category_id,
                $name,
                $description,
                (float)$price,
                (int)$stock_quantity,
                $status,
                !empty($image_url) ? $image_url : null
            ]);

            header('Location: producer_dashboard.php?added=1');
            exit;

        } catch (PDOException $e) {
            $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Add New Product</h1>
        <p class="page-subtitle">List your agricultural produce or craft item for marketplace buyers.</p>
    </div>
    <a href="producer_dashboard.php" class="btn btn-secondary btn-sm">&larr; Back to Dashboard</a>
</div>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?php echo htmlspecialchars($error_message); ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header">
        <h2 class="card-title">Product Details</h2>
        <span class="badge badge-verified">Verified Producer</span>
    </div>

    <form action="add_product.php" method="POST">
        <!-- Product Name -->
        <div class="form-group">
            <label for="name" class="form-label">Product Name *</label>
            <input type="text" id="name" name="name" class="form-control"
                   placeholder="e.g. Fresh Organic Carrots (1kg)"
                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                   required autofocus>
        </div>

        <div class="form-grid">
            <!-- Category Dropdown -->
            <div class="form-group">
                <label for="category_id" class="form-label">Category *</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"
                            <?php echo (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Price -->
            <div class="form-group">
                <label for="price" class="form-label">Price (BDT / ৳) *</label>
                <input type="number" id="price" name="price" step="0.01" min="1" class="form-control"
                       placeholder="e.g. 85.00"
                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="form-grid">
            <!-- Stock Quantity -->
            <div class="form-group">
                <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" class="form-control"
                       placeholder="e.g. 50"
                       value="<?php echo htmlspecialchars($_POST['stock_quantity'] ?? '10'); ?>" required>
            </div>

            <!-- Status -->
            <div class="form-group">
                <label for="status" class="form-label">Listing Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="active"        <?php echo (isset($_POST['status']) && $_POST['status'] === 'active')        ? 'selected' : ''; ?>>Active (Visible in Shop)</option>
                    <option value="out_of_stock"  <?php echo (isset($_POST['status']) && $_POST['status'] === 'out_of_stock')  ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label for="description" class="form-label">Product Description</label>
            <textarea id="description" name="description" class="form-control" rows="4"
                      placeholder="Describe origin, quality, freshness, or harvesting details..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <!-- Optional product image URL -->
        <div class="form-group">
            <label for="image_url" class="form-label">Product Image URL <span style="font-weight: 400; color: var(--text-muted);">(optional)</span></label>
            <input type="url" id="image_url" name="image_url" class="form-control"
                   placeholder="e.g. https://images.unsplash.com/photo-..."
                   value="<?php echo htmlspecialchars($_POST['image_url'] ?? ''); ?>">
            <div class="form-help">Paste a direct image link (Unsplash, Imgur, etc.). Leave blank to use a category-based default image.</div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save &amp; Publish Product</button>
            <a href="producer_dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
