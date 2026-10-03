<?php
// login.php — Authenticates the user and redirects based on their role

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, redirect to the correct dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } elseif ($_SESSION['user_role'] === 'producer') {
        header('Location: producer_dashboard.php');
    } else {
        header('Location: shop.php');
    }
    exit;
}

$page_title    = 'Login - HaatLink';
$error_message = '';
$info_message  = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
    $info_message = 'You have been successfully logged out.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        try {
            // Look up the user by email address
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $is_valid = false;

                if (password_verify($password, $user['password'])) {
                    // Password matches a bcrypt hash
                    $is_valid = true;
                } elseif ($password === $user['password']) {
                    // Plaintext match from seed data — upgrade to bcrypt hash automatically
                    $new_hash = password_hash($password, PASSWORD_BCRYPT);
                    $upgrade  = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upgrade->execute([$new_hash, $user['id']]);
                    $is_valid = true;
                }

                if ($is_valid) {
                    if ($user['status'] === 'suspended') {
                        $error_message = 'Your account has been suspended. Please contact the administrator.';
                    } elseif ($user['role'] === 'producer' && $user['status'] === 'pending') {
                        $error_message = 'Your account is pending admin approval. Please wait for verification.';
                    } elseif ($user['role'] === 'producer' && $user['status'] !== 'verified') {
                        $error_message = ($user['status'] === 'rejected')
                            ? 'Your account has been rejected. Please contact the administrator.'
                            : 'Your account is pending admin approval. Please wait for verification.';
                    } else {
                        // Store user info in session
                        $_SESSION['user_id']     = $user['id'];
                        $_SESSION['user_name']   = $user['name'];
                        $_SESSION['user_email']  = $user['email'];
                        $_SESSION['user_role']   = $user['role'];
                        $_SESSION['role']        = $user['role'];
                        $_SESSION['user_status'] = $user['status'];
                        $_SESSION['status']      = $user['status'];

                        // Redirect to the appropriate dashboard based on role
                        if ($user['role'] === 'admin') {
                            header('Location: admin_dashboard.php');
                        } elseif ($user['role'] === 'producer') {
                            header('Location: producer_dashboard.php');
                        } else {
                            header('Location: shop.php');
                        }
                        exit;
                    }
                } else {
                    $error_message = 'Incorrect password. Please try again.';
                }
            } else {
                $error_message = 'No account found with that email address.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Demo accounts: fetch real emails + ensure passwords match '123456' ──
$demo_admin_email    = '';
$demo_producer_email = '';
$demo_customer_email = '';
try {
    // Fetch the first real email per role
    $r = $pdo->query("SELECT email FROM users WHERE role = 'admin'    LIMIT 1"); $demo_admin_email    = $r ? (string)$r->fetchColumn() : '';
    $r = $pdo->query("SELECT email FROM users WHERE role = 'producer'  LIMIT 1"); $demo_producer_email = $r ? (string)$r->fetchColumn() : '';
    $r = $pdo->query("SELECT email FROM users WHERE role = 'customer'  LIMIT 1"); $demo_customer_email = $r ? (string)$r->fetchColumn() : '';

    // Self-healing: ensure every demo account's password verifies as '123456'.
    // We generate ONE hash and reuse it for all accounts that need updating.
    $demo_emails = array_filter([$demo_admin_email, $demo_producer_email, $demo_customer_email]);
    if (!empty($demo_emails)) {
        $check_stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
        $upd_stmt   = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $new_hash   = null; // generated lazily — only if at least one account needs it

        foreach ($demo_emails as $demo_email) {
            $check_stmt->execute([$demo_email]);
            $row = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !password_verify('123456', $row['password'])) {
                if ($new_hash === null) {
                    $new_hash = password_hash('123456', PASSWORD_BCRYPT);
                }
                $upd_stmt->execute([$new_hash, $row['id']]);
            }
        }
    }
} catch (PDOException $e) { /* silently ignore — demo card is non-critical */ }

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">

        <div class="auth-header">
            <h1>Welcome Back</h1>
            <p>Sign in to access your HaatLink account</p>
        </div>

        <?php if (!empty($info_message)): ?>
            <div class="alert alert-info">
                <span>ℹ️</span>
                <div><?php echo htmlspecialchars($info_message); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <span>⚠️</span>
                <div><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="user@example.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                Sign In
            </button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </div>

        <!-- ── Demo Credentials Card ── -->
        <div class="demo-credentials">
            <div class="demo-credentials__header">
                <span class="demo-credentials__icon">🧪</span>
                <span>Demo Accounts <em>(For Testing)</em></span>
            </div>
            <div class="demo-credentials__badges">
                <?php if ($demo_admin_email): ?>
                <button type="button" class="demo-badge demo-badge--admin"
                        onclick="setLogin(this,'<?php echo htmlspecialchars($demo_admin_email, ENT_QUOTES); ?>','123456')"
                        title="<?php echo htmlspecialchars($demo_admin_email, ENT_QUOTES); ?> / 123456">
                    <span class="demo-badge__role">Admin</span>
                    <span class="demo-badge__email"><?php echo htmlspecialchars($demo_admin_email); ?></span>
                    <span class="demo-badge__hint">Click to fill</span>
                </button>
                <?php endif; ?>
                <?php if ($demo_producer_email): ?>
                <button type="button" class="demo-badge demo-badge--producer"
                        onclick="setLogin(this,'<?php echo htmlspecialchars($demo_producer_email, ENT_QUOTES); ?>','123456')"
                        title="<?php echo htmlspecialchars($demo_producer_email, ENT_QUOTES); ?> / 123456">
                    <span class="demo-badge__role">Producer</span>
                    <span class="demo-badge__email"><?php echo htmlspecialchars($demo_producer_email); ?></span>
                    <span class="demo-badge__hint">Click to fill</span>
                </button>
                <?php endif; ?>
                <?php if ($demo_customer_email): ?>
                <button type="button" class="demo-badge demo-badge--customer"
                        onclick="setLogin(this,'<?php echo htmlspecialchars($demo_customer_email, ENT_QUOTES); ?>','123456')"
                        title="<?php echo htmlspecialchars($demo_customer_email, ENT_QUOTES); ?> / 123456">
                    <span class="demo-badge__role">Customer</span>
                    <span class="demo-badge__email"><?php echo htmlspecialchars($demo_customer_email); ?></span>
                    <span class="demo-badge__hint">Click to fill</span>
                </button>
                <?php endif; ?>
            </div>
            <p class="demo-credentials__note">Password for all accounts: <code>123456</code></p>
        </div>

    </div>
</div>

<style>
/* ── Demo Credentials Card ── */
.demo-credentials {
    margin-top: 20px;
    padding: 16px 18px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    text-align: center;
}

.demo-credentials__header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #15803d;
    letter-spacing: 0.3px;
    margin-bottom: 12px;
    text-transform: uppercase;
}

.demo-credentials__header em {
    font-style: normal;
    font-weight: 500;
    text-transform: none;
    color: #4ade80;
}

.demo-credentials__icon {
    font-size: 1rem;
}

.demo-credentials__badges {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.demo-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 8px 14px;
    border-radius: 8px;
    border: 1.5px solid transparent;
    cursor: pointer;
    font-family: inherit;
    transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
    min-width: 88px;
}

.demo-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    filter: brightness(1.05);
}

.demo-badge:active {
    transform: translateY(0);
    box-shadow: none;
}

.demo-badge__role {
    font-size: 0.8rem;
    font-weight: 700;
}

.demo-badge__email {
    font-size: 0.62rem;
    opacity: 0.75;
    font-weight: 400;
    max-width: 130px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.demo-badge__hint {
    font-size: 0.6rem;
    opacity: 0.65;
    font-weight: 400;
    font-style: italic;
}

/* Role colour tokens */
.demo-badge--admin {
    background: #fef9c3;
    border-color: #fde047;
    color: #854d0e;
}

.demo-badge--producer {
    background: #dbeafe;
    border-color: #93c5fd;
    color: #1e3a8a;
}

.demo-badge--customer {
    background: #dcfce7;
    border-color: #86efac;
    color: #14532d;
}

.demo-credentials__note {
    margin: 10px 0 0;
    font-size: 0.72rem;
    color: #6b7280;
}

.demo-credentials__note code {
    background: #d1fae5;
    color: #065f46;
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 700;
}
</style>

<script>
/**
 * Auto-fills the login form with demo credentials.
 * @param {HTMLElement} btn  - The clicked button (passed via onclick="setLogin(this,...)").
 * @param {string}      email - Email address to fill.
 * @param {string}      pass  - Password to fill.
 */
function setLogin(btn, email, pass) {
    // Use stable IDs — inputs already have id="email" and id="password".
    var emailField = document.getElementById('email');
    var passField  = document.getElementById('password');

    if (emailField) { emailField.value = email; }
    if (passField)  { passField.value  = pass;  }

    // Brief mint-green pulse to confirm the fill.
    var origBg = btn.style.backgroundColor;
    btn.style.transition = 'background-color 0.15s ease';
    btn.style.backgroundColor = '#bbf7d0';
    setTimeout(function() { btn.style.backgroundColor = origBg; }, 450);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
