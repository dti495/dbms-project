<?php
// Database connection using PDO (PHP Data Objects)
// Uses prepared statements and throws exceptions on error

$db_host = 'localhost';
$db_name = 'haatlink_db';
$db_user = 'root';
$db_pass = ''; // Default XAMPP password is empty

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";

$options = [
    // Throw PDOException on errors instead of silent failures
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Return rows as associative arrays (e.g. $row['email'])
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Use real server-side prepared statements
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $conn = $pdo; // Alias for mysqli/pdo compatibility

    // Auto-migration to ensure orders & order_items tables support Phase 2 fields
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `orders` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `customer_id` INT NOT NULL,
                `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                `delivery_fee` DECIMAL(10, 2) NOT NULL DEFAULT 50.00,
                `platform_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                `status` ENUM('pending', 'processing', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
                `shipping_name` VARCHAR(100) NOT NULL,
                `shipping_phone` VARCHAR(30) NOT NULL,
                `shipping_address` TEXT NOT NULL,
                `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash on Delivery',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $ordersCols = $pdo->query("SHOW COLUMNS FROM `orders`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('delivery_fee', $ordersCols)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 50.00 AFTER `total_amount`");
        }
        if (!in_array('platform_fee', $ordersCols)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `platform_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `delivery_fee`");
        }
        if (!in_array('shipping_name', $ordersCols)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `shipping_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `status`");
        }
        if (!in_array('shipping_phone', $ordersCols)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `shipping_phone` VARCHAR(30) NOT NULL DEFAULT '' AFTER `shipping_name`");
        }
        if (!in_array('phone', $ordersCols)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `phone` VARCHAR(30) NOT NULL DEFAULT '' AFTER `shipping_phone`");
        }
        if (!in_array('shipping_address', $ordersCols)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `shipping_address` TEXT NULL AFTER `phone`");
        }
        if (!in_array('payment_method', $ordersCols)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash on Delivery' AFTER `shipping_address`");
        }
        if (!in_array('transaction_id', $ordersCols)) {
            $pdo->exec("ALTER TABLE `orders` ADD COLUMN `transaction_id` VARCHAR(100) NULL DEFAULT NULL AFTER `payment_method`");
        }

        $pdo->exec("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending', 'processing', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `order_items` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT NOT NULL,
                `product_id` INT NOT NULL,
                `producer_id` INT NOT NULL DEFAULT 1,
                `quantity` INT NOT NULL DEFAULT 1,
                `unit_price` DECIMAL(10, 2) NOT NULL,
                `platform_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                `producer_earnings` DECIMAL(10, 2) NOT NULL DEFAULT 0.00
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $itemsCols = $pdo->query("SHOW COLUMNS FROM `order_items`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('producer_id', $itemsCols)) {
            $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `producer_id` INT NOT NULL DEFAULT 1 AFTER `product_id`");
        }
        if (!in_array('platform_fee', $itemsCols)) {
            $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `platform_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `unit_price`");
        }
        if (!in_array('producer_earnings', $itemsCols)) {
            $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `producer_earnings` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `platform_fee`");
        }
    } catch (Exception $migrationEx) {
        // Schema already migrated or restricted permissions
    }
} catch (PDOException $e) {
    // Show a helpful setup page if the database hasn't been imported yet
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Setup Required - HaatLink</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; color: #1e293b; padding: 40px 20px; }
            .setup-card { max-width: 620px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-top: 5px solid #ef4444; }
            h2 { color: #b91c1c; margin-top: 0; }
            pre { background: #0f172a; color: #38bdf8; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 14px; }
            ol { line-height: 1.8; }
            .btn { display: inline-block; margin-top: 15px; background: #16a34a; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="setup-card">
            <h2>Database Not Found: <code>haatlink_db</code></h2>
            <p>The connection to <strong>haatlink_db</strong> on <code>localhost</code> failed.</p>
            <p><strong>Error:</strong></p>
            <pre><?php echo htmlspecialchars($e->getMessage()); ?></pre>
            <h3>Setup Steps:</h3>
            <ol>
                <li>Open <a href="http://localhost/phpmyadmin" target="_blank"><strong>phpMyAdmin</strong></a>.</li>
                <li>Click <strong>Import</strong>, select <code>database.sql</code> from the <code>Haatlink</code> folder, and click <strong>Go</strong>.</li>
            </ol>
            <a href="index.php" class="btn">Retry Connection</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
