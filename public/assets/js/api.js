/**
 * public/assets/js/api.js
 *
 * Frontend API client — Bite-Me-Donut
 * Bao phủ toàn bộ route trong architecture.md
 *
 * Cách dùng:
 *   <script src="/assets/js/api.js"></script>
 *
 *   const res = await Api.products.getAll();
 *   if (res.success) { ... }
 */

// ─────────────────────────────────────────────────────────────
// 1. CONFIG
// ─────────────────────────────────────────────────────────────

const API_BASE = '/api';

// Key lưu token trong localStorage
const TOKEN_KEY  = 'bmd_access_token';
const USER_KEY   = 'bmd_user';


// ─────────────────────────────────────────────────────────────
// 2. TOKEN HELPERS
//    Token được lưu ở localStorage phía client.
//    Không bao giờ expose plain token ra ngoài hàm này.
// ─────────────────────────────────────────────────────────────

const Auth = {
  /** Lưu token + thông tin user sau complete-login */
  saveSession(token, user) {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  },

  /** Xoá session khi logout hoặc token hết hạn */
  clearSession() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  },

  /** Lấy plain token để gửi trong Authorization header */
  getToken() {
    return localStorage.getItem(TOKEN_KEY) || null;
  },

  /** Lấy thông tin user đã lưu */
  getUser() {
    try {
      return JSON.parse(localStorage.getItem(USER_KEY)) || null;
    } catch {
      return null;
    }
  },

  /** Kiểm tra đã đăng nhập chưa (chỉ kiểm tra client-side) */
  isLoggedIn() {
    return !!this.getToken();
  },
};


// ─────────────────────────────────────────────────────────────
// 3. CORE REQUEST FUNCTION
// ─────────────────────────────────────────────────────────────

/**
 * Hàm gọi API chung.
 *
 * @param {string} endpoint   - Đường dẫn API, ví dụ '/auth/login'
 * @param {object} options    - Tuỳ chọn
 * @param {string} options.method     - HTTP method (mặc định 'GET')
 * @param {object} options.body       - Request body (tự động stringify)
 * @param {boolean} options.auth      - Có gắn Authorization Bearer không (mặc định false)
 * @param {object} options.headers    - Header bổ sung
 *
 * @returns {Promise<{success: boolean, data?: any, error_code?: string, message?: string, errors?: object}>}
 */
async function request(endpoint, options = {}) {
  const {
    method  = 'GET',
    body    = null,
    auth    = false,
    headers = {},
  } = options;

  // Build headers
  const requestHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...headers,
  };

  // Gắn Bearer token nếu yêu cầu
  if (auth) {
    const token = Auth.getToken();
    if (!token) {
      // Không có token → trả lỗi ngay, không gọi API
      return {
        success: false,
        error_code: 'TOKEN_MISSING',
        message: 'No access token found. Please log in.',
      };
    }
    requestHeaders['Authorization'] = `Bearer ${token}`;
  }

  // Build fetch options
  const fetchOptions = {
    method: method.toUpperCase(),
    headers: requestHeaders,
  };

  if (body && method.toUpperCase() !== 'GET') {
    fetchOptions.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(`${API_BASE}${endpoint}`, fetchOptions);
    const data = await response.json();

    // Token hết hạn hoặc bị thu hồi → tự động clear session
    if (
      data.error_code === 'TOKEN_EXPIRED' ||
      data.error_code === 'TOKEN_REVOKED' ||
      data.error_code === 'TOKEN_INVALID'
    ) {
      Auth.clearSession();
    }

    return data;

  } catch (err) {
    // Network error, JSON parse error, ...
    console.error(`[API] ${method} ${endpoint} failed:`, err);
    return {
      success: false,
      error_code: 'NETWORK_ERROR',
      message: 'Cannot connect to server. Please check your connection.',
    };
  }
}


// ─────────────────────────────────────────────────────────────
// 4. API MODULES
//    Mỗi module tương ứng với nhóm route trong architecture.md
// ─────────────────────────────────────────────────────────────

/**
 * Health check
 * GET /api/health
 */
const health = {
  check() {
    return request('/health');
  },
};


// ─────────────────────────────────────────────────────────────
// 4.1 AUTH — Login flow (4 bước) + Logout
// ─────────────────────────────────────────────────────────────

const auth = {
  /**
   * Bước 1 — Xác minh password
   * POST /api/auth/login
   * @param {string} credential - phone hoặc email
   * @param {string} password
   * Response trả login_challenge_id, KHÔNG trả token
   */
  login(credential, password) {
    // Phát hiện phone hay email để gửi đúng field
    const isPhone = /^\+?[\d\s\-()]{7,}$/.test(credential);
    const body = isPhone
      ? { phone: credential, password }
      : { email: credential, password };

    return request('/auth/login', { method: 'POST', body });
  },

  /**
   * Bước 4 — Hoàn tất đăng nhập, nhận access_token
   * POST /api/auth/complete-login
   * @param {string} loginChallengeId
   * Tự động lưu token + user vào localStorage nếu thành công
   */
  async completeLogin(loginChallengeId) {
    const res = await request('/auth/complete-login', {
      method: 'POST',
      body: { login_challenge_id: loginChallengeId },
    });

    if (res.success && res.data?.access_token) {
      Auth.saveSession(res.data.access_token, res.data.user);
    }

    return res;
  },

  /**
   * Logout — thu hồi token hiện tại
   * POST /api/auth/logout
   * Tự động xoá session khỏi localStorage
   */
  async logout() {
    const res = await request('/auth/logout', {
      method: 'POST',
      auth: true,
    });

    // Xoá session dù API có thành công hay không
    Auth.clearSession();
    return res;
  },
};


// ─────────────────────────────────────────────────────────────
// 4.2 OTP — Bước 2 và Bước 3 trong login flow
// ─────────────────────────────────────────────────────────────

const otp = {
  /**
   * Bước 2 — Yêu cầu gửi OTP
   * POST /api/otp/request
   * @param {string} loginChallengeId
   */
  request(loginChallengeId) {
    return request('/otp/request', {
      method: 'POST',
      body: { login_challenge_id: loginChallengeId },
    });
  },

  /**
   * Bước 3 — Xác minh OTP người dùng nhập
   * POST /api/otp/verify
   * @param {string} loginChallengeId
   * @param {string} otpCode - 6 chữ số
   */
  verify(loginChallengeId, otpCode) {
    return request('/otp/verify', {
      method: 'POST',
      body: {
        login_challenge_id: loginChallengeId,
        otp_code: otpCode,
      },
    });
  },
};


// ─────────────────────────────────────────────────────────────
// 4.3 USERS — Profile & IDOR protection
// ─────────────────────────────────────────────────────────────

const users = {
  /**
   * Lấy thông tin user đang đăng nhập
   * GET /api/users/me
   */
  me() {
    return request('/users/me', { auth: true });
  },

  /**
   * Lấy thông tin user theo ID (admin hoặc chính user đó)
   * GET /api/users/{id}
   * Backend tự chặn IDOR nếu customer truy cập user khác
   * @param {number|string} userId
   */
  getById(userId) {
    return request(`/users/${userId}`, { auth: true });
  },
};


// ─────────────────────────────────────────────────────────────
// 4.4 PRODUCTS — Public, không cần token
// ─────────────────────────────────────────────────────────────

const products = {
  /**
   * Lấy danh sách sản phẩm
   * GET /api/products
   * @param {object} params - Query params tuỳ chọn, ví dụ { page: 1, limit: 12 }
   */
  getAll(params = {}) {
    const query = new URLSearchParams(params).toString();
    const endpoint = query ? `/products?${query}` : '/products';
    return request(endpoint);
  },

  /**
   * Lấy chi tiết 1 sản phẩm
   * GET /api/products/{id}
   * @param {number|string} productId
   */
  getById(productId) {
    return request(`/products/${productId}`);
  },
};


// ─────────────────────────────────────────────────────────────
// 4.5 CART — Cần đăng nhập
// ─────────────────────────────────────────────────────────────

const cart = {
  /**
   * Xem giỏ hàng hiện tại
   * GET /api/cart
   */
  get() {
    return request('/cart', { auth: true });
  },

  /**
   * Thêm sản phẩm vào giỏ
   * POST /api/cart/add
   * @param {number|string} productId
   * @param {number} quantity
   */
  add(productId, quantity = 1) {
    return request('/cart/add', {
      method: 'POST',
      auth: true,
      body: { product_id: productId, quantity },
    });
  },

  /**
   * Cập nhật số lượng sản phẩm trong giỏ
   * POST /api/cart/update
   * @param {number|string} productId
   * @param {number} quantity - Số lượng mới (nếu = 0 thì xoá)
   */
  update(productId, quantity) {
    return request('/cart/update', {
      method: 'POST',
      auth: true,
      body: { product_id: productId, quantity },
    });
  },

  /**
   * Xoá 1 sản phẩm khỏi giỏ
   * POST /api/cart/remove
   * @param {number|string} productId
   */
  remove(productId) {
    return request('/cart/remove', {
      method: 'POST',
      auth: true,
      body: { product_id: productId },
    });
  },

  /**
   * Xoá toàn bộ giỏ hàng
   * POST /api/cart/clear
   */
  clear() {
    return request('/cart/clear', {
      method: 'POST',
      auth: true,
    });
  },
};


// ─────────────────────────────────────────────────────────────
// 4.6 ORDERS — Cần đăng nhập
// ─────────────────────────────────────────────────────────────

const orders = {
  /**
   * Lấy danh sách đơn hàng của user hiện tại
   * GET /api/orders
   */
  getAll() {
    return request('/orders', { auth: true });
  },

  /**
   * Tạo đơn hàng mới từ giỏ hàng hiện tại
   * POST /api/orders
   * @param {object} shippingInfo - Thông tin giao hàng
   * @param {string} shippingInfo.name
   * @param {string} shippingInfo.phone
   * @param {string} shippingInfo.address
   * @param {string} [shippingInfo.note]
   */
  create(shippingInfo) {
    return request('/orders', {
      method: 'POST',
      auth: true,
      body: shippingInfo,
    });
  },

  /**
   * Lấy chi tiết 1 đơn hàng
   * GET /api/orders/{id}
   * Backend tự chặn nếu customer truy cập order của user khác (IDOR)
   * @param {number|string} orderId
   */
  getById(orderId) {
    return request(`/orders/${orderId}`, { auth: true });
  },
};


// ─────────────────────────────────────────────────────────────
// 4.7 ADMIN — Cần token admin
// ─────────────────────────────────────────────────────────────

const admin = {
  /**
   * Lấy dữ liệu dashboard admin
   * GET /api/admin/dashboard
   * Middleware backend kiểm tra role = admin
   */
  getDashboard() {
    return request('/admin/dashboard', { auth: true });
  },
};


// ─────────────────────────────────────────────────────────────
// 5. PUBLIC API OBJECT
//    Gộp tất cả module và export ra global window.Api
// ─────────────────────────────────────────────────────────────

const Api = {
  // Token & session helpers
  Auth,

  // API modules
  health,
  auth,
  otp,
  users,
  products,
  cart,
  orders,
  admin,
};

// Expose ra global để dùng ở mọi trang HTML
window.Api = Api;
