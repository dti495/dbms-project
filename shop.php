<?php
// shop.php — Public product catalog with category filtering and keyword search

require_once __DIR__ . '/db_connect.php';

$page_title = 'Shop - HaatLink';

// Returns a relevant placeholder image URL using a smart switch-case on the product name
function get_product_placeholder_image($product_name) {
    $name = strtolower($product_name);

    switch (true) {
        case (strpos($name, 'tea') !== false):
            return 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=500&auto=format&fit=crop&q=80';

        case (strpos($name, 'mango') !== false):
            return 'https://images.unsplash.com/photo-1553279768-865429fa0078?w=500&auto=format&fit=crop&q=80';

        case (strpos($name, 'tomato') !== false):
            return 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=500&auto=format&fit=crop&q=80';

        case (strpos($name, 'spinach') !== false || strpos($name, 'shak') !== false || strpos($name, 'green') !== false):
            return 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=500&auto=format&fit=crop&q=80';

        case (strpos($name, 'milk') !== false || strpos($name, 'dairy') !== false):
            return 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=500&auto=format&fit=crop&q=80';

        case (strpos($name, 'egg') !== false || strpos($name, 'poultry') !== false):
            return 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?w=500&auto=format&fit=crop&q=80';

        case (strpos($name, 'jute') !== false || strpos($name, 'bag') !== false || strpos($name, 'craft') !== false):
            return 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=500&auto=format&fit=crop&q=80';

        case (strpos($name, 'rice') !== false || strpos($name, 'grain') !== false || strpos($name, 'wheat') !== false):
            return 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=500&auto=format&fit=crop&q=80';

        case (strpos($name, 'fruit') !== false || strpos($name, 'apple') !== false || strpos($name, 'banana') !== false):
            return 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=500&auto=format&fit=crop&q=80';

        default:
            return 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=500&auto=format&fit=crop&q=80';
    }
}

// Fetch all categories with the count of active products in each
try {
    $cat_stmt = $pdo->query("
        SELECT c.id, c.name, COUNT(p.id) AS active_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY c.name ASC
    ");
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Read filter parameters from the URL
$selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search_query      = trim($_GET['q'] ?? '');

// Build a dynamic WHERE clause based on active filters
$where_clauses = ["p.status = 'active'"];
$params        = [];

if ($selected_category > 0) {
    $where_clauses[] = "p.category_id = ?";
    $params[]        = $selected_category;
}

if (!empty($search_query)) {
    $where_clauses[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[]        = "%" . $search_query . "%";
    $params[]        = "%" . $search_query . "%";
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch products matching the filters, joining categories and producer names
$sql = "
    SELECT
        p.id,
        p.name,
        p.description,
        p.price,
        p.stock_quantity,
        p.status,
        p.image_url,
        p.created_at,
        c.name AS category_name,
        u.name AS producer_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.producer_id = u.id
    WHERE $where_sql
    ORDER BY p.created_at DESC
";

try {
    $stmt     = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Marketplace Catalog</h1>
        <p class="page-subtitle">Fresh produce and handmade goods from verified local producers.</p>
    </div>
    <span class="badge badge-verified"><?php echo count($products); ?> Products Available</span>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
    <div class="alert alert-success" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <span>🛒 <strong>Item Added!</strong> Product has been added to your shopping cart.</span>
        <a href="cart.php" class="btn btn-primary btn-sm">View Cart &rarr;</a>
    </div>
<?php endif; ?>

<!-- Category Filter Bar and Search -->
<div class="filter-bar">
    <div class="filter-categories">
        <a href="shop.php<?php echo !empty($search_query) ? '?q=' . urlencode($search_query) : ''; ?>"
           class="filter-btn <?php echo ($selected_category === 0) ? 'active' : ''; ?>">
            All Products
        </a>
        <?php foreach ($categories as $category): ?>
            <a href="shop.php?category=<?php echo $category['id']; ?><?php echo !empty($search_query) ? '&q=' . urlencode($search_query) : ''; ?>"
               class="filter-btn <?php echo ($selected_category === (int)$category['id']) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($category['name']); ?> (<?php echo (int)$category['active_count']; ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <form action="shop.php" method="GET" class="filter-search">
        <?php if ($selected_category > 0): ?>
            <input type="hidden" name="category" value="<?php echo $selected_category; ?>">
        <?php endif; ?>
        <input type="text" name="q" class="form-control" placeholder="Search products..."
               value="<?php echo htmlspecialchars($search_query); ?>" style="padding: 7px 12px; font-size: 14px;">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if (!empty($search_query) || $selected_category > 0): ?>
            <a href="shop.php" class="btn btn-secondary btn-sm" title="Clear Filters">✕</a>
        <?php endif; ?>
    </form>
</div>

<!-- Product Grid -->
<?php if (empty($products)): ?>
    <div class="card empty-state" style="padding: 50px 20px;">
        <div style="font-size: 48px; margin-bottom: 12px;">🔍</div>
        <h3>No Products Found</h3>
        <p>No products match your current search or category filter.</p>
        <a href="shop.php" class="btn btn-outline btn-sm">Reset Filters</a>
    </div>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $product):
            // Fallback logic: if image_url is empty, use smart switch-case on product name
            if (empty($product['image_url'])) {
                $product['image_url'] = get_product_placeholder_image($product['name']);
            }
        ?>
            <div class="product-card">
                <!-- Product image -->
                <div style="position: relative;">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="product-image"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <!-- Emoji placeholder shown only if image fails to load -->
                    <div class="product-image-placeholder" style="display:none;">🌱</div>
                    <span class="product-category-tag">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </span>
                </div>

                <div class="product-body">
                    <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="product-desc">
                        <?php echo htmlspecialchars($product['description'] ?? 'Pure locally sourced product.'); ?>
                    </p>
                    <div class="product-meta">
                        <span>👨‍🌾 Producer: <strong><?php echo htmlspecialchars($product['producer_name']); ?></strong></span>
                    </div>

                    <div class="product-footer">
                        <div>
                            <div class="product-price">৳ <?php echo number_format($product['price'], 2); ?></div>
                            <div style="font-size: 11px; color: var(--text-muted);">
                                Listed: <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                            </div>
                        </div>

                        <div style="text-align: right;">
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <span class="badge badge-active" style="font-size: 11px;">In Stock</span>
                            <?php else: ?>
                                <span class="badge badge-out_of_stock" style="font-size: 11px;">Out of Stock</span>
                            <?php endif; ?>

                            <?php if ((isset($_SESSION['role']) && $_SESSION['role'] === 'producer') || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'producer')): ?>
                                <div style="margin-top: 6px;">
                                    <button type="button" class="btn btn-secondary btn-sm" disabled style="font-size: 12px; padding: 5px 10px; width: 100%; opacity: 0.65; cursor: not-allowed;" title="Producers cannot purchase products">
                                        🛒 Add to Cart
                                    </button>
                                    <div style="font-size: 10.5px; color: var(--text-muted); margin-top: 4px; line-height: 1.25; text-align: right;">
                                        Logged in as Producer (Switch to Customer account to buy products)
                                    </div>
                                </div>
                            <?php else: ?>
                                <form action="add_to_cart.php" method="POST" style="margin-top: 6px;">
                                    <input type="hidden" name="product_id"   value="<?php echo (int)$product['id']; ?>">
                                    <input type="hidden" name="price"        value="<?php echo htmlspecialchars($product['price']); ?>">
                                    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <button type="submit" class="btn btn-primary btn-sm" style="font-size: 12px; padding: 5px 10px; width: 100%;">
                                            🛒 Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled style="font-size: 12px; padding: 5px 10px; width: 100%;">
                                            Out of Stock
                                        </button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
