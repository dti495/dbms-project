<?php
// Navbar include — adapts links based on user session role

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user_id']);
$user_role    = $_SESSION['role'] ?? $_SESSION['user_role'] ?? null;
$user_name    = $_SESSION['user_name'] ?? '';
?>
<header class="navbar">
    <div class="container">
        <div class="nav-wrapper">

            <!-- Brand Logo -->
            <a href="index.php" class="brand-logo">
                <span class="leaf-icon">🌾</span>
                Haat<span>Link</span>
            </a>

            <!-- Navigation Links -->
            <nav>
                <ul class="nav-links">
                    <li>
                        <a href="index.php" class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">Home</a>
                    </li>
                    <li>
                        <a href="shop.php" class="<?php echo ($current_page === 'shop.php') ? 'active' : ''; ?>">Shop</a>
                    </li>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'customer'): ?>
                    <li>
                        <a href="cart.php" class="<?php echo ($current_page === 'cart.php') ? 'active' : ''; ?>">
                            🛒 Cart
                            <?php
                                $cart_count = isset($_SESSION['cart']) && is_array($_SESSION['cart'])
                                    ? array_sum(array_column($_SESSION['cart'], 'quantity'))
                                    : 0;
                                if ($cart_count > 0):
                            ?>
                                <span class="badge badge-active" style="padding: 2px 7px; font-size: 11px; margin-left: 4px;"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="my_orders.php" class="<?php echo ($current_page === 'my_orders.php') ? 'active' : ''; ?>">
                            📦 My Orders
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($is_logged_in): ?>
                        <?php if ($user_role === 'admin'): ?>
                            <!-- Admin links -->
                            <li>
                                <a href="admin_dashboard.php" class="<?php echo ($current_page === 'admin_dashboard.php') ? 'active' : ''; ?>">Admin Dashboard</a>
                            </li>
                            <li>
                                <a href="add_category.php" class="<?php echo ($current_page === 'add_category.php') ? 'active' : ''; ?>">Categories</a>
                            </li>
                        <?php elseif ($user_role === 'producer'): ?>
                            <!-- Producer links -->
                            <li>
                                <a href="producer_dashboard.php" class="<?php echo ($current_page === 'producer_dashboard.php') ? 'active' : ''; ?>">My Dashboard</a>
                            </li>
                            <li>
                                <a href="add_product.php" class="<?php echo ($current_page === 'add_product.php') ? 'active' : ''; ?>">+ Add Product</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- User Session / Auth Buttons -->
            <div class="nav-auth-group">
                <?php if ($is_logged_in): ?>
                    <span class="badge badge-role" title="Logged in as <?php echo htmlspecialchars($user_name); ?>">
                        <?php echo ucfirst(htmlspecialchars($user_role)); ?>: <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>
                    </span>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary btn-sm <?php echo ($current_page === 'login.php') ? 'active' : ''; ?>">Login</a>
                    <a href="register.php" class="btn btn-primary btn-sm <?php echo ($current_page === 'register.php') ? 'active' : ''; ?>">Register</a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</header>
