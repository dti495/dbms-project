<?php
// delete_product.php — Handles deletion of product listings safely for Admins and Producers

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only logged in admin or producer can delete products
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'producer'])) {
    header('Location: login.php?msg=access_denied');
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
$product_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

// Determine return destination
$redirect_url = ($user_role === 'admin') ? 'admin_dashboard.php' : 'producer_dashboard.php';

if ($product_id > 0) {
    try {
        if ($user_role === 'admin') {
            // Admin can delete any product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
        } else {
            // Producer can only delete their own product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND producer_id = ?");
            $stmt->execute([$product_id, $user_id]);
        }

        if ($stmt->rowCount() > 0) {
            $_SESSION['flash_message'] = "Product deleted successfully.";
            header("Location: {$redirect_url}?msg=deleted");
            exit;
        } else {
            $_SESSION['flash_error'] = "Product not found or permission denied.";
            header("Location: {$redirect_url}?msg=error");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database error while deleting product: " . htmlspecialchars($e->getMessage());
        header("Location: {$redirect_url}?msg=error");
        exit;
    }
}

header("Location: {$redirect_url}");
exit;
