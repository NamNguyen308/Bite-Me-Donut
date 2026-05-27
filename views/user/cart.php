<?php
/**
 * cart.php — Trang giỏ hàng
 * Bite-Me-Donut E-commerce Security Platform
 *
 * API sử dụng:
 *   GET  /api/cart
 *   POST /api/cart/update
 *   POST /api/cart/remove
 *   POST /api/cart/clear
 *   POST /api/orders
 */

// ── Cấu hình ────────────────────────────────────────────────
define('API_BASE', 'http://ecommerce_security_platform.test/api');
define('ASSETS_BASE', '/assets');      // Chỉnh theo cấu trúc thực tế
define('ROOT_CSS',  '../../public/assets/css/root.css');  // Path đến root.css dùng chung
define('CART_CSS',  '../../public/assets/css/cart.css');  // Path đến cart.css riêng của trang này

// ── Helper: đọc Bearer token từ session / cookie ────────────
function getAuthToken(): string {
    // Ưu tiên session, fallback cookie
    if (!empty($_SESSION['access_token'])) {
        return $_SESSION['access_token'];
    }
    if (!empty($_COOKIE['access_token'])) {
        return $_COOKIE['access_token'];
    }
    return '';
}

// ── Helper: gọi API nội bộ ──────────────────────────────────
function callApi(string $method, string $endpoint, array $body = []): array {
    $token = getAuthToken();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init(API_BASE . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'error_code' => 'NETWORK_ERROR', 'message' => 'Không thể kết nối API.'];
    }

    $data = json_decode($raw, true) ?? [];
    $data['_http_status'] = $code;
    return $data;
}

// ── Lấy dữ liệu giỏ hàng ban đầu (server-side) ─────────────
session_start();

$cartData   = callApi('GET', '/cart');
$cartItems  = $cartData['data']['items']  ?? [];
$cartTotal  = $cartData['data']['total']  ?? 0;
$cartId     = $cartData['data']['cart_id'] ?? null;
$hasItems   = !empty($cartItems);
$authError  = isset($cartData['error_code']) &&
              in_array($cartData['error_code'], ['TOKEN_MISSING', 'TOKEN_INVALID', 'TOKEN_EXPIRED', 'TOKEN_REVOKED']);

// Format tiền VND hoặc USD tuỳ cấu hình — hiện dùng $
function formatPrice(float $price): string {
    return '$' . number_format($price, 0);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Your Cart — Bite-Me-Donut</title>

    <!-- Google Fonts (giống root.css) -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Design System + Cart styles -->
    <link rel="stylesheet" href="<?= htmlspecialchars(ROOT_CSS) ?>" />
    <link rel="stylesheet" href="<?= htmlspecialchars(CART_CSS) ?>" />
</head>
<body>

<!-- ════════════════════════════════════════════════════════════
     HEADER — include từ folder dùng chung
     ════════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/../layouts/header.php'; ?>


<!-- ════════════════════════════════════════════════════════════
     MAIN CONTENT
     ════════════════════════════════════════════════════════════ -->
<main class="cart-page">
<div class="container">

    <!-- Tiêu đề trang -->
    <h1 class="cart-page__title">Your cart</h1>

    <?php if ($authError): ?>
    <!-- ── Chưa đăng nhập ── -->
    <div class="alert alert--warning">
        You need to <a href="/login.php">login</a> to view your cart.
    </div>

    <?php elseif (!$hasItems): ?>
    <!-- ══════════════════════════════════════════════════════════
         STATE: GIỎ HÀNG TRỐNG
         ══════════════════════════════════════════════════════════ -->
    <div class="cart-empty">
        <!-- <span class="cart-empty__donut" role="img" aria-label="donut">🍩</span> -->
        <h2 class="cart-empty__title">Your cart is empty!</h2>
        <p class="cart-empty__desc">
            Oops! It looks like your cart is still empty. <br>Let's explore our yummy menu together!
        </p>
        <a href="../../views/user/products.php" class="btn btn--primary btn--lg">
            Shop Now
        </a>
    </div>

    <?php else: ?>
    <!-- ══════════════════════════════════════════════════════════
         STATE: GIỎ HÀNG CÓ SẢN PHẨM
         ══════════════════════════════════════════════════════════ -->

    <!-- Top bar: Continue Shopping -->
    <div class="cart-page__topbar">
        <a href="../../views/user/products.php" class="cart-page__continue">Continue Shopping</a>
    </div>

    <div class="cart-layout">

        <!-- ── LEFT: Danh sách sản phẩm ── -->
        <div>
            <div class="cart-items-panel" id="cartItemsPanel">

                <?php foreach ($cartItems as $item):
                    $itemId   = (int)($item['id']         ?? 0);
                    $productId = (int)($item['product_id'] ?? 0);
                    $name     = htmlspecialchars($item['product_name'] ?? 'Sản phẩm');
                    $desc     = htmlspecialchars($item['description']  ?? '');
                    $qty      = (int)($item['quantity']   ?? 1);
                    $price    = (float)($item['price']    ?? 0);
                    $imgSrc   = htmlspecialchars($item['image_url'] ?? '/assets/images/placeholder-donut.png');
                    $lineTotal = $price * $qty;
                ?>
                <div class="cart-item" id="cartItem-<?= $itemId ?>">

                    <!-- Hình ảnh -->
                    <div class="cart-item__image-wrap">
                        <img
                            src="<?= $imgSrc ?>"
                            alt="<?= $name ?>"
                            class="cart-item__image"
                            onerror="this.src='/assets/images/placeholder-donut.png'"
                        />
                    </div>

                    <!-- Thông tin sản phẩm -->
                    <div class="cart-item__info">
                        <div class="cart-item__name"><?= $name ?></div>
                        <?php if ($desc): ?>
                        <div class="cart-item__desc"><?= $desc ?></div>
                        <?php endif; ?>

                        <div class="cart-item__qty-row">
                            <!-- Label QUANTITY -->
                            <span class="cart-item__qty-label">Quantity:</span>

                            <!-- Quantity selector -->
                            <div class="cart-qty" data-item-id="<?= $itemId ?>">
                                <button
                                    type="button"
                                    class="cart-qty__btn"
                                    data-action="decrease"
                                    <?= $qty <= 1 ? 'disabled' : '' ?>
                                    aria-label="Giảm số lượng"
                                >−</button>
                                <span class="cart-qty__val"><?= $qty ?></span>
                                <button
                                    type="button"
                                    class="cart-qty__btn"
                                    data-action="increase"
                                    aria-label="Tăng số lượng"
                                >+</button>
                            </div>

                            <!-- Label PRICE -->
                            <span class="cart-item__qty-label">Price:</span>
                            <span class="cart-item__price" data-unit-price="<?= $price ?>">
                                <?= formatPrice($lineTotal) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Nút xoá -->
                    <div class="cart-item__right">
                        <button
                            type="button"
                            class="cart-item__remove"
                            data-item-id="<?= $itemId ?>"
                            aria-label="Xoá <?= $name ?> khỏi giỏ"
                            title="Xoá sản phẩm"
                        >×</button>
                    </div>

                </div>
                <?php endforeach; ?>

            </div><!-- /cart-items-panel -->

            <!-- Nút Clear Cart -->
            <div class="cart-actions">
                <button type="button" class="cart-actions__clear" id="btnClearCart">
                    Clear Cart
                </button>
            </div>
        </div><!-- /LEFT -->

        <!-- ── RIGHT: Summary ── -->
        <aside class="cart-summary" id="cartSummary">
            <div class="cart-summary__header">
                <div class="cart-summary__title">Summary</div>
            </div>

            <div class="cart-summary__body">
                <!-- Subtotal -->
                <div class="cart-summary__row">
                    <span class="cart-summary__label">Subtotal:</span>
                    <span class="cart-summary__value" id="summarySubtotal">
                        <?= formatPrice($cartTotal) ?>
                    </span>
                </div>

                <!-- Delivery -->
                <div class="cart-summary__row">
                    <span class="cart-summary__label">Delivery:</span>
                    <span class="cart-summary__value--free">FREE</span>
                </div>

                <hr class="cart-summary__divider" />

                <!-- Promo code toggle -->
                <!-- <div>
                    <button type="button" class="cart-summary__promo" id="btnTogglePromo">
                        I have a promo code
                    </button>
                    <div class="cart-summary__promo-input-wrap" id="promoWrap">
                        <input
                            type="text"
                            class="cart-summary__promo-input"
                            id="promoCodeInput"
                            placeholder="Nhập mã giảm giá..."
                        />
                        <button type="button" class="btn btn--outline btn--sm" id="btnApplyPromo">
                            Áp dụng
                        </button>
                    </div>
                </div> -->

                <hr class="cart-summary__divider" />

                <!-- Total -->
                <div class="cart-summary__row">
                    <span class="cart-summary__label">Total:</span>
                    <span class="cart-summary__value" id="summaryTotal">
                        <?= formatPrice($cartTotal) ?>
                    </span>
                </div>
            </div>

            <div class="cart-summary__checkout-wrap">
                <button type="button" class="cart-summary__checkout" id="btnCheckout">
                    Checkout
                </button>
            </div>
        </aside><!-- /RIGHT -->

    </div><!-- /cart-layout -->
    <?php endif; ?>

</div><!-- /container -->
</main>


<!-- ════════════════════════════════════════════════════════════
     MODAL: Shipping Information
     ════════════════════════════════════════════════════════════ -->
<div class="modal-overlay shipping-modal" id="shippingModal" role="dialog" aria-modal="true" aria-labelledby="shippingModalTitle">
    <div class="modal">
        <div class="modal__header">
            <h2 class="modal__title" id="shippingModalTitle">Thông tin giao hàng</h2>
            <button type="button" class="modal__close" id="btnCloseShipping" aria-label="Đóng">×</button>
        </div>

        <div class="shipping-form" id="shippingForm">
            <div class="shipping-form__row">
                <div class="form-group">
                    <label class="form-label" for="shipFullName">Họ tên *</label>
                    <input type="text" id="shipFullName" class="form-input" placeholder="Nguyễn Văn A" required />
                </div>
                <div class="form-group">
                    <label class="form-label" for="shipPhone">Số điện thoại *</label>
                    <input type="tel" id="shipPhone" class="form-input" placeholder="+84..." required />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="shipAddress">Địa chỉ *</label>
                <input type="text" id="shipAddress" class="form-input" placeholder="Số nhà, tên đường..." required />
            </div>

            <div class="shipping-form__row">
                <div class="form-group">
                    <label class="form-label" for="shipCity">Thành phố *</label>
                    <input type="text" id="shipCity" class="form-input" placeholder="TP. Hồ Chí Minh" required />
                </div>
                <div class="form-group">
                    <label class="form-label" for="shipDistrict">Quận / Huyện</label>
                    <input type="text" id="shipDistrict" class="form-input" placeholder="Quận 1" />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="shipNote">Ghi chú</label>
                <textarea id="shipNote" class="form-textarea" rows="3" placeholder="Giao giờ hành chính, gọi trước..."></textarea>
            </div>

            <div id="shippingError" class="alert alert--danger" style="display:none;"></div>

            <div class="shipping-form__actions">
                <button type="button" class="btn btn--outline" id="btnCancelShipping">Huỷ</button>
                <button type="button" class="btn btn--primary" id="btnPlaceOrder">
                    <span id="placeOrderLabel">Đặt hàng</span>
                    <span id="placeOrderSpinner" class="spinner" style="display:none; width:16px; height:16px;"></span>
                </button>
            </div>
        </div>
    </div>
</div>


<!-- ════════════════════════════════════════════════════════════
     TOAST CONTAINER
     ════════════════════════════════════════════════════════════ -->
<div class="toast-container" id="toastContainer" aria-live="polite"></div>


<!-- ════════════════════════════════════════════════════════════
     FOOTER — include từ folder dùng chung
     ════════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/../layouts/footer.php'; ?>


<!-- ════════════════════════════════════════════════════════════
     JAVASCRIPT
     ════════════════════════════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    /* ── Config ─────────────────────────────────────────────── */
    const API_BASE = '<?= API_BASE ?>';

    /* ── Helpers ─────────────────────────────────────────────── */

    /** Gọi API backend qua fetch */
    async function api(method, endpoint, body = null) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        };

        // Đọc token từ meta hoặc cookie (tuỳ cách frontend lưu token)
        const token = getToken();
        if (token) opts.headers['Authorization'] = 'Bearer ' + token;

        if (body) opts.body = JSON.stringify(body);

        const res  = await fetch(API_BASE + endpoint, opts);
        const data = await res.json().catch(() => ({}));
        data._status = res.status;
        return data;
    }

    /** Lấy token — ưu tiên localStorage, fallback sessionStorage */
    function getToken() {
        return localStorage.getItem('access_token') || sessionStorage.getItem('access_token') || '';
    }

    /** Format số thành chuỗi tiền */
    function fmt(n) {
        return '$' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
    }

    /* ── Toast ───────────────────────────────────────────────── */
    function toast(msg, type = 'success') {
        const el = document.createElement('div');
        el.className = 'toast' + (type !== 'success' ? ' toast--' + type : '');
        el.textContent = msg;
        document.getElementById('toastContainer').appendChild(el);
        setTimeout(() => {
            el.classList.add('is-leaving');
            el.addEventListener('animationend', () => el.remove());
        }, 3500);
    }

    /* ── Modal ───────────────────────────────────────────────── */
    const shippingModal = document.getElementById('shippingModal');

    function openModal() {
        if (!shippingModal) return;
        shippingModal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!shippingModal) return;
        shippingModal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    /* ── Recalculate summary totals from DOM ─────────────────── */
    function recalcTotal() {
        let total = 0;
        document.querySelectorAll('.cart-item').forEach(item => {
            const qty       = parseInt(item.querySelector('.cart-qty__val')?.textContent || '0');
            const unitPrice = parseFloat(item.querySelector('.cart-item__price')?.dataset.unitPrice || '0');
            const lineTotal = qty * unitPrice;
            // Cập nhật giá dòng
            if (item.querySelector('.cart-item__price')) {
                item.querySelector('.cart-item__price').textContent = fmt(lineTotal);
            }
            total += lineTotal;
        });
        const subtotalEl = document.getElementById('summarySubtotal');
        const totalEl    = document.getElementById('summaryTotal');
        if (subtotalEl) subtotalEl.textContent = fmt(total);
        if (totalEl)    totalEl.textContent    = fmt(total);
    }

    /* ── Check nếu giỏ hàng trống sau thay đổi ──────────────── */
    function checkEmptyCart() {
        const panel = document.getElementById('cartItemsPanel');
        if (!panel) return;
        if (panel.querySelectorAll('.cart-item').length === 0) {
            // Reload trang để hiển thị trạng thái trống đúng cách
            window.location.reload();
        }
    }

    /* ── UPDATE QUANTITY ─────────────────────────────────────── */
    document.querySelectorAll('.cart-qty').forEach(qtyEl => {
        const itemId = parseInt(qtyEl.dataset.itemId);

        qtyEl.querySelectorAll('.cart-qty__btn').forEach(btn => {
            btn.addEventListener('click', async function () {
                const valEl   = qtyEl.querySelector('.cart-qty__val');
                const current = parseInt(valEl.textContent);
                const action  = this.dataset.action;
                const newQty  = action === 'increase' ? current + 1 : Math.max(1, current - 1);

                if (newQty === current) return;

                this.disabled = true;

                try {
                    const res = await api('POST', '/cart/update', { cart_item_id: itemId, quantity: newQty });
                    if (res.success) {
                        valEl.textContent = newQty;
                        // Cập nhật nút giảm
                        qtyEl.querySelector('[data-action="decrease"]').disabled = newQty <= 1;
                        // Cập nhật unit price nếu API trả về
                        if (res.data?.unit_price !== undefined) {
                            const priceEl = document.querySelector(`#cartItem-${itemId} .cart-item__price`);
                            if (priceEl) priceEl.dataset.unitPrice = res.data.unit_price;
                        }
                        recalcTotal();
                    } else {
                        toast(res.message || 'Không thể cập nhật số lượng.', 'error');
                    }
                } catch {
                    toast('Lỗi kết nối. Vui lòng thử lại.', 'error');
                } finally {
                    this.disabled = false;
                }
            });
        });
    });

    /* ── REMOVE ITEM ─────────────────────────────────────────── */
    document.querySelectorAll('.cart-item__remove').forEach(btn => {
        btn.addEventListener('click', async function () {
            const itemId  = parseInt(this.dataset.itemId);
            const itemEl  = document.getElementById('cartItem-' + itemId);
            if (!itemEl) return;

            const confirmed = confirm('Bạn muốn xoá sản phẩm này khỏi giỏ hàng?');
            if (!confirmed) return;

            this.disabled = true;

            try {
                const res = await api('POST', '/cart/remove', { cart_item_id: itemId });
                if (res.success) {
                    itemEl.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    itemEl.style.opacity    = '0';
                    itemEl.style.transform  = 'translateX(30px)';
                    setTimeout(() => {
                        itemEl.remove();
                        recalcTotal();
                        checkEmptyCart();
                    }, 300);
                    toast('Đã xoá sản phẩm khỏi giỏ hàng.');
                } else {
                    toast(res.message || 'Không thể xoá sản phẩm.', 'error');
                    this.disabled = false;
                }
            } catch {
                toast('Lỗi kết nối. Vui lòng thử lại.', 'error');
                this.disabled = false;
            }
        });
    });

    /* ── CLEAR CART ──────────────────────────────────────────── */
    const btnClear = document.getElementById('btnClearCart');
    if (btnClear) {
        btnClear.addEventListener('click', async function () {
            const confirmed = confirm('Bạn chắc chắn muốn xoá toàn bộ giỏ hàng?');
            if (!confirmed) return;

            btnClear.disabled    = true;
            btnClear.textContent = 'Đang xoá...';

            try {
                const res = await api('POST', '/cart/clear');
                if (res.success) {
                    toast('Giỏ hàng đã được xoá.');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    toast(res.message || 'Không thể xoá giỏ hàng.', 'error');
                    btnClear.disabled    = false;
                    btnClear.textContent = '🗑 Clear Cart';
                }
            } catch {
                toast('Lỗi kết nối. Vui lòng thử lại.', 'error');
                btnClear.disabled    = false;
                btnClear.textContent = '🗑 Clear Cart';
            }
        });
    }

    /* ── PROMO CODE TOGGLE ───────────────────────────────────── */
    const btnTogglePromo = document.getElementById('btnTogglePromo');
    const promoWrap      = document.getElementById('promoWrap');
    if (btnTogglePromo && promoWrap) {
        btnTogglePromo.addEventListener('click', () => {
            promoWrap.classList.toggle('is-open');
            if (promoWrap.classList.contains('is-open')) {
                document.getElementById('promoCodeInput')?.focus();
            }
        });
    }

    const btnApplyPromo = document.getElementById('btnApplyPromo');
    if (btnApplyPromo) {
        btnApplyPromo.addEventListener('click', () => {
            const code = document.getElementById('promoCodeInput')?.value.trim();
            if (!code) {
                toast('Vui lòng nhập mã giảm giá.', 'warning');
                return;
            }
            // Chức năng promo chưa có API — thông báo tạm
            toast('Tính năng mã giảm giá đang được phát triển.', 'warning');
        });
    }

    /* ── CHECKOUT → Mở modal shipping ───────────────────────── */
    const btnCheckout = document.getElementById('btnCheckout');
    if (btnCheckout) {
        btnCheckout.addEventListener('click', () => {
            const token = getToken();
            if (!token) {
                toast('Vui lòng đăng nhập để tiếp tục đặt hàng.', 'error');
                setTimeout(() => window.location.href = '/login.php', 1200);
                return;
            }
            openModal();
        });
    }

    /* ── Đóng modal ──────────────────────────────────────────── */
    document.getElementById('btnCloseShipping')?.addEventListener('click', closeModal);
    document.getElementById('btnCancelShipping')?.addEventListener('click', closeModal);

    // Click overlay để đóng
    shippingModal?.addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

    // ESC để đóng
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });

    /* ── PLACE ORDER ─────────────────────────────────────────── */
    const btnPlaceOrder = document.getElementById('btnPlaceOrder');
    if (btnPlaceOrder) {
        btnPlaceOrder.addEventListener('click', async function () {

            // Lấy giá trị form
            const fullName = document.getElementById('shipFullName')?.value.trim();
            const phone    = document.getElementById('shipPhone')?.value.trim();
            const address  = document.getElementById('shipAddress')?.value.trim();
            const city     = document.getElementById('shipCity')?.value.trim();
            const district = document.getElementById('shipDistrict')?.value.trim();
            const note     = document.getElementById('shipNote')?.value.trim();
            const errEl    = document.getElementById('shippingError');

            // Validate đơn giản phía client
            const missing = [];
            if (!fullName) missing.push('Họ tên');
            if (!phone)    missing.push('Số điện thoại');
            if (!address)  missing.push('Địa chỉ');
            if (!city)     missing.push('Thành phố');

            if (missing.length > 0) {
                errEl.textContent = 'Vui lòng nhập: ' + missing.join(', ') + '.';
                errEl.style.display = 'block';
                return;
            }
            errEl.style.display = 'none';

            // Bắt đầu loading
            btnPlaceOrder.disabled = true;
            document.getElementById('placeOrderLabel').textContent = 'Đang xử lý...';
            document.getElementById('placeOrderSpinner').style.display = 'inline-block';

            const payload = {
                shipping_name:     fullName,
                shipping_phone:    phone,
                shipping_address:  address,
                shipping_city:     city,
                shipping_district: district,
                note:              note,
            };

            try {
                const res = await api('POST', '/orders', payload);

                if (res.success) {
                    closeModal();
                    toast('🎉 Đặt hàng thành công! Chuyển đến trang đơn hàng...', 'success');
                    const orderId = res.data?.order?.id ?? res.data?.id ?? '';
                    setTimeout(() => {
                        window.location.href = orderId
                            ? '/orders.php?id=' + orderId
                            : '/orders.php';
                    }, 1200);
                } else {
                    // Hiển thị lỗi từ API
                    let msg = res.message || 'Đặt hàng thất bại.';
                    if (res.error_code === 'CART_EMPTY')     msg = 'Giỏ hàng của bạn đang trống.';
                    if (res.error_code === 'TOKEN_EXPIRED')  msg = 'Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại.';
                    if (res.error_code === 'ORDER_CREATE_FAILED') msg = 'Tạo đơn hàng thất bại. Vui lòng thử lại.';

                    errEl.textContent   = msg;
                    errEl.style.display = 'block';
                }
            } catch {
                errEl.textContent   = 'Lỗi kết nối. Vui lòng thử lại.';
                errEl.style.display = 'block';
            } finally {
                btnPlaceOrder.disabled = false;
                document.getElementById('placeOrderLabel').textContent = 'Đặt hàng';
                document.getElementById('placeOrderSpinner').style.display = 'none';
            }
        });
    }

})();
</script>

</body>
</html>
