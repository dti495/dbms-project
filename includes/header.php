<?php
// Common header include — initializes session and outputs HTML <head>

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sync role session variables for consistent RBAC checks
if (!isset($_SESSION['role']) && isset($_SESSION['user_role'])) {
    $_SESSION['role'] = $_SESSION['user_role'];
}
if (!isset($_SESSION['user_role']) && isset($_SESSION['role'])) {
    $_SESSION['user_role'] = $_SESSION['role'];
}

// Default page title if not set by the calling page
if (!isset($page_title)) {
    $page_title = 'HaatLink - Local Online Marketplace';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="HaatLink: Online marketplace connecting local producers and consumers.">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require_once __DIR__ . '/navbar.php'; ?>
<main class="main-content">
    <div class="container">
