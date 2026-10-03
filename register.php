<?php
// register.php — Registers a new user as either a customer or a producer

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect already-logged-in users to home
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$page_title      = 'Register - HaatLink';
$error_message   = '';
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role             = trim($_POST['role'] ?? 'customer');

    // Validate all fields before touching the database
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (!in_array($role, ['customer', 'producer'])) {
        $error_message = 'Invalid role selected.';
    } else {
        try {
            // Check if this email is already registered
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);

            if ($check->rowCount() > 0) {
                $error_message = 'This email address is already registered. Please log in.';
            } else {
                // Customers are verified immediately; producers need admin approval
                $status          = ($role === 'producer') ? 'pending' : 'verified';
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insert the new user record
                $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$name, $email, $hashed_password, $role, $status]);

                if ($role === 'producer') {
                    $success_message = 'Producer account created! Your account is pending admin verification. You can log in to view your dashboard.';
                } else {
                    $success_message = 'Account created successfully! You can now log in.';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">

        <div class="auth-header">
            <h1>Create an Account</h1>
            <p>Join HaatLink as a Customer or Local Producer</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <div>
                    <?php echo htmlspecialchars($success_message); ?>
                    <div style="margin-top: 8px;">
                        <a href="login.php" class="btn btn-primary btn-sm">Proceed to Login</a>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control"
                           placeholder="e.g. Rohim Uddin"
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="e.g. rohim@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Min 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                               placeholder="Re-type password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="role" class="form-label">Register As</label>
                    <?php $active_role = $_POST['role'] ?? $_GET['role'] ?? 'customer'; ?>
                    <select id="role" name="role" class="form-control" required>
                        <option value="customer" <?php echo ($active_role === 'customer') ? 'selected' : ''; ?>>
                            Customer (Browse &amp; Purchase Products)
                        </option>
                        <option value="producer" <?php echo ($active_role === 'producer') ? 'selected' : ''; ?>>
                            Producer / Farmer (List &amp; Sell Products — Requires Approval)
                        </option>
                    </select>
                    <div class="form-help">Producer accounts require admin approval before listing products.</div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    Register Account
                </button>
            </form>

        <?php endif; ?>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Log In</a>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
