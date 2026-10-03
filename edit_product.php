<?php
// edit_product.php — Allows a producer to edit their own product listing

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only logged in producers can edit products
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'producer') {
    header('Location: login.php?msg=access_denied');
    exit;
}

$producer_id = (int)$_SESSION['user_id'];
$product_id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($product_id <= 0) {
    header('Location: producer_dashboard.php');
    exit;
}

// Fetch the product ensuring it belongs to this producer
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND producer_id = ?");
    $stmt->execute([$product_id, $producer_id]);
    $product = $stmt->fetch();

    if (!$product) {
        header('Location: producer_dashboard.php');
        exit;
    }

    // Fetch categories for dropdown
    $cat_stmt   = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$page_title      = 'Edit Product - HaatLink';
$error_message   = '';
$success_message = '';

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $category_id    = (int)($_POST['category_id'] ?? 0);
    $price          = trim($_POST['price'] ?? '');
    $stock_quantity = trim($_POST['stock_quantity'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $image_url      = trim($_POST['image_url'] ?? '');

    if (empty($name) || empty($category_id) || $price === '' || $stock_quantity === '') {
        $error_message = 'Please fill in all required fields (Name, Category, Price, Stock).';
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        $error_message = 'Price must be a positive number.';
    } elseif (!is_numeric($stock_quantity) || (int)$stock_quantity < 0) {
        $error_message = 'Stock quantity cannot be negative.';
    } else {
        try {
            $status = $product['status'];
            if ((int)$stock_quantity === 0) {
                $status = 'out_of_stock';
            } elseif ($status === 'out_of_stock' && (int)$stock_quantity > 0) {
                // Return to active if was previously active before going out of stock
                $status = 'active';
            }

            $update_sql = "
                UPDATE products
                SET name = ?, category_id = ?, price = ?, stock_quantity = ?, status = ?, description = ?, image_url = ?
                WHERE id = ? AND producer_id = ?
            ";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                $name,
                $category_id,
                (float)$price,
                (int)$stock_quantity,
                $status,
                $description,
                !empty($image_url) ? $image_url : null,
                $product_id,
                $producer_id
            ]);

            header('Location: producer_dashboard.php?msg=updated');
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
        <h1 class="page-title">Edit Product #<?php echo $product_id; ?></h1>
        <p class="page-subtitle">Update item details, pricing, and stock quantity.</p>
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
        <h2 class="card-title">Product Information</h2>
        <span class="badge badge-<?php echo htmlspecialchars($product['status']); ?>">
            Status: <?php echo htmlspecialchars($product['status']); ?>
        </span>
    </div>

    <form action="edit_product.php?id=<?php echo $product_id; ?>" method="POST">
        <input type="hidden" name="id" value="<?php echo $product_id; ?>">

        <!-- Product Name -->
        <div class="form-group">
            <label for="name" class="form-label">Product Name *</label>
            <input type="text" id="name" name="name" class="form-control"
                   value="<?php echo htmlspecialchars($_POST['name'] ?? $product['name']); ?>"
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
                            <?php echo ((int)($_POST['category_id'] ?? $product['category_id']) === (int)$cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Price -->
            <div class="form-group">
                <label for="price" class="form-label">Price (BDT / ৳) *</label>
                <input type="number" id="price" name="price" step="0.01" min="1" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['price'] ?? $product['price']); ?>" required>
            </div>
        </div>

        <div class="form-grid">
            <!-- Stock Quantity -->
            <div class="form-group">
                <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" class="form-control"
                       value="<?php echo htmlspecialchars($_POST['stock_quantity'] ?? $product['stock_quantity']); ?>" required>
            </div>

            <!-- Current Status (Read-only / Managed by Admin) -->
            <div class="form-group">
                <label class="form-label">Approval Status</label>
                <input type="text" class="form-control"
                       value="<?php echo ucfirst(htmlspecialchars($product['status'])); ?>" disabled
                       style="background: var(--bg-hover); font-weight: 600;">
            </div>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label for="description" class="form-label">Product Description</label>
            <textarea id="description" name="description" class="form-control" rows="4"
                      placeholder="Describe origin, quality, freshness, or harvesting details..."><?php echo htmlspecialchars($_POST['description'] ?? $product['description']); ?></textarea>
        </div>

        <!-- Optional product image URL -->
        <div class="form-group">
            <label for="image_url" class="form-label">Product Image URL <span style="font-weight: 400; color: var(--text-muted);">(optional)</span></label>
            <input type="url" id="image_url" name="image_url" class="form-control"
                   placeholder="e.g. https://images.unsplash.com/photo-..."
                   value="<?php echo htmlspecialchars($_POST['image_url'] ?? $product['image_url']); ?>">
            <div class="form-help">Paste a direct image URL.</div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="producer_dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
