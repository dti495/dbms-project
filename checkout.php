<?php
// checkout.php — Checkout page: Delivery details, payment method, and order summary

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) && isset($_SESSION['user_role'])) {
    $_SESSION['role'] = $_SESSION['user_role'];
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'producer') {
    header("Location: producer_dashboard.php?error=producers_cannot_order");
    exit();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout');
    exit;
}

// Redirect to cart if cart is empty
$cart_items = $_SESSION['cart'] ?? [];
if (empty($cart_items)) {
    header('Location: cart.php?msg=empty_cart');
    exit;
}

// ── Calculate order totals ────────────────────────────────────────────────────
$items_subtotal = 0.0;
$total_qty      = 0;

foreach ($cart_items as $item) {
    $qty             = (int)($item['quantity'] ?? 1);
    $price           = (float)($item['price'] ?? 0);
    $total_qty      += $qty;
    $items_subtotal += ($qty * $price);
}

// Delivery fee: free for orders >= 500, else 40
$delivery_fee = ($items_subtotal >= 500) ? 0.00 : 40.00;
$discount     = round($items_subtotal * 0.02, 2);  // 2% automatic discount
$grand_total  = ($items_subtotal - $discount) + $delivery_fee;

// Pre-fill customer name from session
$customer_name = $_SESSION['user_name'] ?? '';

$page_title = 'Checkout - HaatLink';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title">Checkout</h1>
        <p class="page-subtitle">Complete your delivery details and confirm your order.</p>
    </div>
    <span class="badge badge-verified"><?php echo $total_qty; ?> Item<?php echo $total_qty === 1 ? '' : 's'; ?></span>
</div>

<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger" role="alert">
        &#9888;&#65039; <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
    </div>
<?php endif; ?>

<!-- Checkout 2-Column Layout -->
<form method="POST" action="process_order.php" id="checkoutForm">
<div class="checkout-layout">

    <!-- LEFT COLUMN: Delivery Details & Payment -->
    <div>

        <!-- Shipping Information Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">&#128230; Delivery Details</h2>
            </div>

            <h3 class="checkout-section-title">&#127968; Shipping Information</h3>

            <!-- Customer Name -->
            <div class="form-group">
                <label for="shipping_name" class="form-label">Full Name <span style="color: var(--status-danger);">*</span></label>
                <input
                    type="text"
                    id="shipping_name"
                    name="shipping_name"
                    class="form-control"
                    value="<?php echo htmlspecialchars($customer_name); ?>"
                    placeholder="Your full name"
                    required
                >
            </div>

            <!-- Phone Number -->
            <div class="form-group">
                <label for="shipping_phone" class="form-label">Phone Number <span style="color: var(--status-danger);">*</span></label>
                <input
                    type="tel"
                    id="shipping_phone"
                    name="shipping_phone"
                    class="form-control"
                    placeholder="e.g. 01XXXXXXXXX"
                    pattern="[0-9+\-\s]{7,20}"
                    required
                >
                <p class="form-help">Enter a valid Bangladeshi mobile number.</p>
            </div>

            <!-- Delivery Address -->
            <div class="form-group">
                <label for="shipping_address" class="form-label">Delivery Address <span style="color: var(--status-danger);">*</span></label>
                <textarea
                    id="shipping_address"
                    name="shipping_address"
                    class="form-control"
                    rows="3"
                    placeholder="House/flat number, road, area..."
                    required
                ></textarea>
            </div>

            <!-- District Dropdown -->
            <div class="form-group">
                <label for="shipping_district" class="form-label">District / City <span style="color: var(--status-danger);">*</span></label>
                <select id="shipping_district" name="shipping_district" class="form-control" required>
                    <option value="" disabled selected>-- Select District --</option>
                    <option value="Bogura">Bogura</option>
                    <option value="Dhaka">Dhaka</option>
                    <option value="Rajshahi">Rajshahi</option>
                    <option value="Chittagong">Chittagong</option>
                    <option value="Sylhet">Sylhet</option>
                    <option value="Khulna">Khulna</option>
                    <option value="Barisal">Barisal</option>
                    <option value="Rangpur">Rangpur</option>
                    <option value="Mymensingh">Mymensingh</option>
                    <option value="Comilla">Comilla</option>
                    <option value="Narayanganj">Narayanganj</option>
                    <option value="Gazipur">Gazipur</option>
                    <option value="Jessore">Jessore</option>
                    <option value="Noakhali">Noakhali</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <!-- Delivery Instructions (optional) -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="delivery_instructions" class="form-label">Delivery Instructions <span style="color: var(--text-muted); font-weight: 400;">(Optional)</span></label>
                <textarea
                    id="delivery_instructions"
                    name="delivery_instructions"
                    class="form-control"
                    rows="2"
                    placeholder="e.g. Call before arriving, leave at gate, ring bell twice..."
                ></textarea>
            </div>
        </div>

        <!-- Payment Method Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">&#128179; Payment Method</h2>
            </div>

            <div class="payment-options" role="radiogroup" aria-label="Payment method">

                <!-- Cash on Delivery (default) -->
                <label class="payment-option" for="pay_cod" id="cod-option">
                    <input
                        type="radio"
                        id="pay_cod"
                        name="payment_method"
                        value="cod"
                        checked
                    >
                    <span class="payment-icon">&#128181;</span>
                    <div class="payment-label">
                        <div class="payment-name">Cash on Delivery (COD)</div>
                        <div class="payment-desc">Pay with cash upon physical delivery at your doorstep.</div>
                    </div>
                </label>

                <!-- bKash Mobile Banking -->
                <label class="payment-option" for="pay_bkash" id="bkash-option">
                    <input
                        type="radio"
                        id="pay_bkash"
                        name="payment_method"
                        value="bkash"
                    >
                    <span class="payment-icon">&#128241;</span>
                    <div class="payment-label">
                        <div class="payment-name">bKash Mobile Banking</div>
                        <div class="payment-desc">Pay seamlessly via your bKash mobile account.</div>
                    </div>
                </label>

            </div>

            <!-- Conditional bKash Payment Field -->
            <div id="bkash-details" style="display: none; margin-top: 14px; padding: 16px; background: #fdf2f8; border: 1.5px dashed #f472b6; border-radius: var(--radius-md);">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; color: #be185d; font-weight: 700; font-size: 13.5px;">
                    <span>&#128241;</span>
                    <span>bKash Payment Instructions</span>
                </div>
                <p style="font-size: 12.5px; color: var(--text-muted); margin-bottom: 12px; line-height: 1.5;">
                    Please send total <strong>&#2547;<?php echo number_format($grand_total, 2); ?></strong> to Merchant/Personal bKash Number: <strong style="color: #be185d; font-family: monospace; font-size: 13.5px; background: #fce7f3; padding: 2px 6px; border-radius: 4px;">01700-000000</strong>. Enter your Transaction ID or sender number below:
                </p>
                <label for="bkash_transaction_id" class="form-label" style="font-size: 13px; font-weight: 600;">
                    bKash Transaction ID / Sender Number <span style="font-weight: 400; color: var(--text-muted);">(Optional)</span>
                </label>
                <input
                    type="text"
                    id="bkash_transaction_id"
                    name="bkash_transaction_id"
                    class="form-control"
                    placeholder="e.g. 9J87X6YZ or 017XXXXXXXX"
                    style="background: #ffffff;"
                >
            </div>

            <!-- Security note -->
            <p style="margin-top: 16px; font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
                &#128274; Your order information is securely processed.
            </p>
        </div>

    </div><!-- /.left-column -->

    <!-- RIGHT COLUMN: Order Summary -->
    <div>
        <div class="card" style="position: sticky; top: 90px;">
            <div class="card-header">
                <h2 class="card-title">&#129534; Order Summary</h2>
                <span class="badge badge-pending"><?php echo $total_qty; ?> item<?php echo $total_qty !== 1 ? 's' : ''; ?></span>
            </div>

            <!-- Item List -->
            <div class="checkout-items-list">
                <?php foreach ($cart_items as $item):
                    $i_qty      = (int)($item['quantity'] ?? 1);
                    $i_price    = (float)($item['price'] ?? 0);
                    $i_subtotal = $i_qty * $i_price;
                    $i_name     = $item['name'] ?? 'Product';
                ?>
                    <div class="checkout-item-row">
                        <div class="checkout-item-icon">&#127807;</div>
                        <div class="checkout-item-details">
                            <div class="checkout-item-name"><?php echo htmlspecialchars($i_name); ?></div>
                            <div class="checkout-item-qty">
                                &#2547; <?php echo number_format($i_price, 2); ?> &times; <?php echo $i_qty; ?>
                            </div>
                        </div>
                        <div class="checkout-item-subtotal">&#2547; <?php echo number_format($i_subtotal, 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Divider -->
            <hr style="border: none; border-top: 1px dashed var(--border-color); margin: 4px 0 16px;">

            <!-- Financial Breakdown -->
            <div class="order-summary-rows">
                <div class="order-row">
                    <span>Items Subtotal:</span>
                    <strong>&#2547; <?php echo number_format($items_subtotal, 2); ?></strong>
                </div>
                <div class="order-row" style="color: var(--status-success);">
                    <span>&#127881; 2% Special Discount:</span>
                    <strong style="color: var(--status-success);">-&#2547; <?php echo number_format($discount, 2); ?></strong>
                </div>
                <div class="order-row">
                    <span>Delivery Charge:</span>
                    <?php if ($delivery_fee > 0): ?>
                        <strong>&#2547; <?php echo number_format($delivery_fee, 2); ?></strong>
                    <?php else: ?>
                        <span style="color: var(--primary-color); font-weight: 700;">&#127881; Free</span>
                    <?php endif; ?>
                </div>
                <?php if ($delivery_fee === 0.00): ?>
                    <div class="order-row" style="font-size: 12px;">
                        <span style="color: var(--primary-color);">&#10003; Free delivery on orders &ge; &#2547;500</span>
                    </div>
                <?php else: ?>
                    <div class="order-row" style="font-size: 12px;">
                        <span style="color: var(--text-muted);">Add &#2547; <?php echo number_format(500 - $items_subtotal, 2); ?> more for free delivery</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Grand Total -->
            <div class="order-total-row">
                <span class="order-total-label">Grand Total:</span>
                <span class="order-total-amount" style="color: var(--primary-color);">&#2547; <?php echo number_format($grand_total, 2); ?></span>
            </div>

            <!-- Confirm Button -->
            <button
                type="submit"
                id="confirmOrderBtn"
                class="btn btn-primary"
                style="width: 100%; padding: 14px 18px; font-size: 15px; font-weight: 700; margin-top: 22px;"
            >
                &#10003; Confirm &amp; Place Order
            </button>

            <!-- Return to Cart -->
            <div style="text-align: center; margin-top: 14px;">
                <a href="cart.php" class="btn btn-outline btn-sm" style="font-size: 13px;">
                    &larr; Return to Cart
                </a>
            </div>

            <!-- Trust note -->
            <div class="checkout-note" style="margin-top: 16px; font-size: 12px; line-height: 1.7; color: var(--text-muted);">
                &#128274; Secure &mdash; &#128665; COD Available &mdash; &#9851; Local Produce
            </div>
        </div>
    </div><!-- /.right-column -->

</div><!-- /.checkout-layout -->
</form>

<script>
// Toggle conditional bKash payment details input box
function togglePaymentMethodDetails() {
    var bkashRadio   = document.getElementById('pay_bkash');
    var bkashDetails = document.getElementById('bkash-details');
    if (bkashDetails && bkashRadio) {
        bkashDetails.style.display = bkashRadio.checked ? 'block' : 'none';
    }
}

var paymentRadios = document.querySelectorAll('input[name="payment_method"]');
paymentRadios.forEach(function (radio) {
    radio.addEventListener('change', togglePaymentMethodDetails);
});
togglePaymentMethodDetails();

// Prevent double-submit on the confirm button
document.getElementById('checkoutForm').addEventListener('submit', function () {
    var btn = document.getElementById('confirmOrderBtn');
    btn.disabled  = true;
    btn.innerHTML = '&#9203; Placing Order...';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
