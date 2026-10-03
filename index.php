<?php
// index.php — Public homepage showing featured active products

require_once __DIR__ . '/db_connect.php';

$page_title = 'HaatLink - Local Online Marketplace';

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

// Fetch the 4 most recent active products for the homepage showcase
try {
    $sql = "
        SELECT
            p.id,
            p.name,
            p.description,
            p.price,
            p.stock_quantity,
            p.image_url,
            c.name AS category_name,
            u.name AS producer_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.producer_id = u.id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 4
    ";
    $featured_products = $pdo->query($sql)->fetchAll();

    // Total count of active products for display
    $total_active = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
} catch (PDOException $e) {
    $featured_products = [];
    $total_active      = 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <span class="hero-badge">🌱 Online Local Marketplace</span>
        <h1>Welcome to HaatLink</h1>
        <p>
            Connecting local farmers, cottage producers, and artisans directly with customers.
            Fresh produce, handmade goods, and fair pricing — no middlemen.
        </p>

        <div class="hero-actions">
            <a href="shop.php" class="btn btn-primary" style="font-size: 16px; padding: 12px 24px;">
                🛒 Browse Shop
            </a>

            <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] === 'customer'): ?>
                <a href="register.php?role=producer" class="btn btn-secondary" style="font-size: 16px; padding: 12px 24px;">
                    🌾 Join as Producer
                </a>
            <?php elseif ($_SESSION['user_role'] === 'producer'): ?>
                <a href="producer_dashboard.php" class="btn btn-secondary" style="font-size: 16px; padding: 12px 24px;">
                    Go to My Dashboard &rarr;
                </a>
            <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
                <a href="admin_dashboard.php" class="btn btn-secondary" style="font-size: 16px; padding: 12px 24px;">
                    Open Admin Panel &rarr;
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Platform Feature Highlights -->
<section class="features-grid">
    <div class="feature-card">
        <div class="feature-icon">🛡️</div>
        <h3>Admin Verification</h3>
        <p>New producer registrations require administrator approval before products can be listed.</p>
    </div>

    <div class="feature-card">
        <div class="feature-icon">🌾</div>
        <h3>Producer Dashboard</h3>
        <p>Verified producers manage their inventory, pricing, and stock levels in a dedicated dashboard.</p>
    </div>

    <div class="feature-card">
        <div class="feature-icon">🗄️</div>
        <h3>Relational Database</h3>
        <p>Built on MySQL with normalized tables, foreign key constraints, and prepared statements.</p>
    </div>
</section>

<!-- Featured Active Products -->
<section style="margin-top: 40px;">
    <div class="section-header">
        <div>
            <h2 class="section-title">Featured Fresh Produce</h2>
            <p class="section-subtitle">Active products currently listed in the marketplace.</p>
        </div>
        <a href="shop.php" class="btn btn-outline btn-sm">View All in Shop &rarr;</a>
    </div>

    <?php if (empty($featured_products)): ?>
        <div class="card empty-state">
            <p>No active products found. Add products via a verified producer account.</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($featured_products as $product):
                // Fallback logic: if image_url is empty, use smart switch-case on product name
                if (empty($product['image_url'])) {
                    $product['image_url'] = get_product_placeholder_image($product['name']);
                }
            ?>
                <div class="product-card">
                    <!-- Product image: actual URL if set, smart product placeholder otherwise -->
                    <div style="position: relative;">
                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="product-image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <!-- Emoji fallback shown only if image fails to load -->
                        <div class="product-image-placeholder" style="display:none;">🍃</div>
                        <span class="product-category-tag">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </span>
                    </div>

                    <div class="product-body">
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-desc"><?php echo htmlspecialchars($product['description'] ?? 'Fresh local product.'); ?></p>
                        <div class="product-meta">
                            <span>👨‍🌾 Sold by: <strong><?php echo htmlspecialchars($product['producer_name']); ?></strong></span>
                        </div>
                        <div class="product-footer">
                            <div>
                                <span class="product-price">৳ <?php echo number_format($product['price'], 2); ?></span>
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
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
