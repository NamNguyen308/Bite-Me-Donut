# Database Design

## 1. Purpose

File này định nghĩa database structure cho các bảng phục vụ hệ thống bảo mật và thương mại điện tử.

Luồng bảo mật chính:

```txt
Password verification
→ Login challenge
→ OTP request
→ OTP verification
→ Complete login
→ Access token
→ Protected API access
```

Luồng thương mại điện tử chính:

```txt
Product listing
→ Cart
→ Order
→ Order items
```

Rule chi tiết không định nghĩa tại file này. Rule Engine được định nghĩa duy nhất tại:

```txt
docs/security-rules.md
```

---

## 2. Database Overview

Database chính:

```txt
ecommerce_security_platform
```

Nhóm bảng bảo mật:

```txt
users
login_challenges
otps
tokens
login_attempts
risk_logs
```

Nhóm bảng thương mại điện tử:

```txt
products
carts
cart_items
orders
order_items
```

Quan hệ tổng quát:

```txt
users
 ├── login_challenges
 │    ├── otps
 │    └── risk_logs
 ├── tokens
 ├── login_attempts
 ├── carts
 │    └── cart_items
 └── orders
      └── order_items

products
 ├── cart_items
 └── order_items
```

---

## 3. users

Bảng `users` lưu thông tin tài khoản người dùng và admin.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID user |
| name | VARCHAR(100) | NOT NULL | Tên người dùng |
| email | VARCHAR(150) | UNIQUE, NULL | Email |
| phone | VARCHAR(20) | UNIQUE, NOT NULL | Số điện thoại |
| password_hash | VARCHAR(255) | NOT NULL | Mật khẩu đã hash |
| role | ENUM('customer','admin') | NOT NULL, DEFAULT 'customer' | Vai trò |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | Trạng thái tài khoản |
| created_at | DATETIME | NOT NULL | Thời điểm tạo |
| updated_at | DATETIME | NULL | Thời điểm cập nhật |

Indexes:

```txt
PRIMARY KEY (id)
UNIQUE KEY unique_users_email (email)
UNIQUE KEY unique_users_phone (phone)
INDEX idx_users_role (role)
INDEX idx_users_is_active (is_active)
```

Notes:

```txt
- users là bảng trung tâm cho customer và admin.
- Admin dùng chung bảng users với role = 'admin'.
- Không lưu password plain text.
- password_hash được tạo bằng password_hash().
- Kiểm tra mật khẩu bằng password_verify().
- is_active = 0 nghĩa là tài khoản bị vô hiệu hóa.
```

Seed users dùng cho test:

```txt
Customer A:
phone = 0932660941
email = user@example.com
password = 123456

Customer B:
phone = 0911111111
email = userb@example.com
password = 123456

Admin:
phone = 0999999999
email = admin@example.com
password = admin123
role = admin
```

---

## 4. login_challenges

Bảng `login_challenges` lưu trạng thái đăng nhập tạm thời sau khi user nhập đúng password nhưng chưa hoàn tất OTP.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | CHAR(36) | PRIMARY KEY | ID challenge |
| user_id | BIGINT UNSIGNED | FOREIGN KEY users(id), NOT NULL | User sở hữu challenge |
| status | ENUM('PENDING_OTP','OTP_SENT','OTP_VERIFIED','AUTHENTICATED','EXPIRED','BLOCKED') | NOT NULL | Trạng thái challenge |
| otp_send_count | INT UNSIGNED | NOT NULL, DEFAULT 0 | Số lần request/gửi OTP |
| otp_wrong_count | INT UNSIGNED | NOT NULL, DEFAULT 0 | Số lần nhập sai OTP |
| risk_score | INT UNSIGNED | NOT NULL, DEFAULT 0 | Tổng điểm rủi ro |
| risk_level | ENUM('LOW','MEDIUM','HIGH') | NOT NULL, DEFAULT 'LOW' | Mức rủi ro |
| password_verified_at | DATETIME | NULL | Thời điểm password được xác minh |
| otp_verified_at | DATETIME | NULL | Thời điểm OTP được xác minh |
| authenticated_at | DATETIME | NULL | Thời điểm token được cấp |
| expires_at | DATETIME | NOT NULL | Thời điểm challenge hết hạn |
| blocked_until | DATETIME | NULL | Thời điểm hết block/timeout |
| ip_address | VARCHAR(45) | NULL | IPv4/IPv6 |
| user_agent | VARCHAR(255) | NULL | User-Agent |
| created_at | DATETIME | NOT NULL | Thời điểm tạo |
| updated_at | DATETIME | NULL | Thời điểm cập nhật |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_login_challenges_user_id (user_id)
INDEX idx_login_challenges_status (status)
INDEX idx_login_challenges_expires_at (expires_at)
INDEX idx_login_challenges_risk_level (risk_level)
```

Foreign keys:

```txt
FOREIGN KEY (user_id) REFERENCES users(id)
```

Status values:

| Status | Meaning |
|---|---|
| PENDING_OTP | Password đúng, đang chờ request OTP |
| OTP_SENT | OTP đã gửi qua mock hoặc Twilio Verify |
| OTP_VERIFIED | OTP đã xác minh thành công |
| AUTHENTICATED | Đã tạo access token, login hoàn tất |
| EXPIRED | Challenge hết hạn |
| BLOCKED | Challenge bị chặn do risk cao hoặc thao tác sai nhiều |

Notes:

```txt
- login_challenges là bảng trung tâm của luồng MFA.
- Mỗi lần password đúng sẽ tạo một challenge mới.
- /api/auth/login chỉ tạo challenge, không cấp token.
- /api/auth/complete-login chỉ cấp token nếu status = OTP_VERIFIED.
- otp_send_count dùng cho rule R2.
- otp_wrong_count dùng cho rule R1.
- risk_score và risk_level được cập nhật bởi RiskService.
- Nếu risk_level = HIGH thì status chuyển sang BLOCKED.
- blocked_until dùng để timeout tạm thời nếu có cấu hình.
```

---

## 5. otps

Bảng `otps` lưu từng OTP local/audit record theo login challenge.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID OTP |
| challenge_id | CHAR(36) | FOREIGN KEY login_challenges(id), NOT NULL | Login challenge tương ứng |
| user_id | BIGINT UNSIGNED | FOREIGN KEY users(id), NOT NULL | User sở hữu OTP |
| otp_hash | VARCHAR(255) | NOT NULL | OTP hash hoặc placeholder hash tùy provider |
| purpose | ENUM('LOGIN','CHANGE_PASSWORD') | NOT NULL, DEFAULT 'LOGIN' | Mục đích OTP |
| expires_at | DATETIME | NOT NULL | Thời điểm OTP local hết hạn |
| is_used | TINYINT(1) | NOT NULL, DEFAULT 0 | OTP/challenge verification record đã dùng hay chưa |
| used_at | DATETIME | NULL | Thời điểm OTP được dùng |
| created_at | DATETIME | NOT NULL | Thời điểm tạo |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_otps_challenge_id (challenge_id)
INDEX idx_otps_user_id (user_id)
INDEX idx_otps_expires_at (expires_at)
INDEX idx_otps_is_used (is_used)
```

Foreign keys:

```txt
FOREIGN KEY (challenge_id) REFERENCES login_challenges(id)
FOREIGN KEY (user_id) REFERENCES users(id)
```

Provider behavior:

```txt
SMS_PROVIDER=mock:
- Backend tự sinh OTP.
- otp_hash là hash của OTP thật.
- OtpService verify bằng password_verify().
- mock_otp_code có thể trả trong API response để test local.

SMS_PROVIDER=twilio_verify:
- Twilio Verify tự sinh OTP.
- User nhận OTP qua call hoặc SMS.
- otp_hash trong bảng otps chỉ là local audit placeholder.
- OtpService không dùng password_verify() để xác minh OTP Twilio.
- OtpService gọi SmsService::verifyTwilioOtp().
- SmsService gọi Twilio VerificationCheck.
- Nếu Twilio trả valid=true và status=approved thì OTP local được mark used.
```

Notes:

```txt
- Không lưu OTP plain text.
- Mỗi lần request/resend OTP có thể tạo một dòng mới.
- Số lần gửi OTP nằm ở login_challenges.otp_send_count.
- Số lần nhập sai OTP nằm ở login_challenges.otp_wrong_count.
- is_used giúp chống verify lại cùng OTP/challenge.
- expires_at vẫn được backend kiểm tra trước khi verify.
```

Không thêm cột provider vào bảng `otps` trong implementation hiện tại để giữ schema đơn giản. Provider được xác định qua `.env`:

```txt
SMS_PROVIDER=mock
SMS_PROVIDER=twilio_verify
```

---

## 6. tokens

Bảng `tokens` lưu access token đã hash.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID token |
| user_id | BIGINT UNSIGNED | FOREIGN KEY users(id), NOT NULL | User sở hữu token |
| token_hash | VARCHAR(255) | NOT NULL | Access token đã hash |
| expires_at | DATETIME | NOT NULL | Thời điểm token hết hạn |
| revoked_at | DATETIME | NULL | Thời điểm token bị thu hồi |
| created_at | DATETIME | NOT NULL | Thời điểm tạo |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_tokens_user_id (user_id)
INDEX idx_tokens_expires_at (expires_at)
INDEX idx_tokens_revoked_at (revoked_at)
```

Foreign keys:

```txt
FOREIGN KEY (user_id) REFERENCES users(id)
```

Notes:

```txt
- Plain access token chỉ trả về một lần cho client.
- Database chỉ lưu token_hash.
- token_hash được tạo từ plain token bằng SHA-256.
- Khi logout, hệ thống set revoked_at.
- Token hết hạn nếu current time > expires_at.
- Token bị revoke nếu revoked_at IS NOT NULL.
```

Auth flow:

```txt
Client gửi:
Authorization: Bearer <plain_access_token>

AuthMiddleware:
- Hash token client gửi.
- Tìm token_hash trong database.
- Kiểm tra expires_at.
- Kiểm tra revoked_at.
- Load user tương ứng.
```

---

## 7. login_attempts

Bảng `login_attempts` ghi nhận các lần đăng nhập thành công hoặc thất bại.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID attempt |
| user_id | BIGINT UNSIGNED | FOREIGN KEY users(id), NULL | User nếu tìm thấy |
| phone | VARCHAR(20) | NULL | Phone được nhập khi login |
| email | VARCHAR(150) | NULL | Email được nhập khi login |
| ip_address | VARCHAR(45) | NULL | IPv4/IPv6 |
| user_agent | VARCHAR(255) | NULL | User-Agent |
| status | ENUM('SUCCESS','FAILED') | NOT NULL | Kết quả login |
| reason | VARCHAR(100) | NULL | Lý do |
| created_at | DATETIME | NOT NULL | Thời điểm ghi nhận |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_login_attempts_user_id (user_id)
INDEX idx_login_attempts_phone (phone)
INDEX idx_login_attempts_email (email)
INDEX idx_login_attempts_ip_address (ip_address)
INDEX idx_login_attempts_status (status)
INDEX idx_login_attempts_created_at (created_at)
```

Foreign keys:

```txt
FOREIGN KEY (user_id) REFERENCES users(id)
```

Common reason values:

```txt
INVALID_PASSWORD
USER_NOT_FOUND
ACCOUNT_INACTIVE
LOGIN_SUCCESS
```

Notes:

```txt
- Bảng này phục vụ kiểm soát sai mật khẩu nhiều lần.
- Rule R3 đọc dữ liệu từ bảng login_attempts.
- Có thể đếm số lần FAILED theo user_id, phone, email hoặc ip_address trong một khoảng thời gian.
- Khi password đúng, AuthService vẫn có thể ghi SUCCESS để phục vụ audit.
```

---

## 8. risk_logs

Bảng `risk_logs` ghi lại các rule rủi ro đã được kích hoạt.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID risk log |
| user_id | BIGINT UNSIGNED | FOREIGN KEY users(id), NULL | User liên quan |
| challenge_id | CHAR(36) | FOREIGN KEY login_challenges(id), NULL | Challenge liên quan |
| rule_code | VARCHAR(10) | NOT NULL | Mã rule, ví dụ R1, R2, R3, C1 |
| description | VARCHAR(255) | NOT NULL | Mô tả rule |
| score | INT UNSIGNED | NOT NULL | Điểm rủi ro được cộng |
| ip_address | VARCHAR(45) | NULL | IPv4/IPv6 |
| user_agent | VARCHAR(255) | NULL | User-Agent |
| created_at | DATETIME | NOT NULL | Thời điểm ghi nhận |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_risk_logs_user_id (user_id)
INDEX idx_risk_logs_challenge_id (challenge_id)
INDEX idx_risk_logs_rule_code (rule_code)
INDEX idx_risk_logs_created_at (created_at)
UNIQUE KEY unique_risk_rule_per_challenge (challenge_id, rule_code)
```

Foreign keys:

```txt
FOREIGN KEY (user_id) REFERENCES users(id)
FOREIGN KEY (challenge_id) REFERENCES login_challenges(id)
```

Notes:

```txt
- unique_risk_rule_per_challenge giúp tránh cộng cùng một rule nhiều lần trong cùng một challenge.
- RiskService ghi dữ liệu vào bảng này.
- RuleEngineService dùng bảng này để kiểm tra rule nào đã kích hoạt.
- risk_score của challenge được tính từ tổng score trong risk_logs theo challenge_id.
```

Rule codes:

```txt
R1: Nhập sai OTP > 1 lần
R2: Request OTP > 2 lần
R3: Sai mật khẩu > 2 lần
C1: R1 + R2
C2: R1 + R3
C3: R2 + R3
C4: R1 + R2 + R3
```

---

## 9. products

Bảng `products` lưu danh sách sản phẩm.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID sản phẩm |
| name | VARCHAR(150) | NOT NULL | Tên sản phẩm |
| description | TEXT | NULL | Mô tả sản phẩm |
| price | DECIMAL(12,2) | NOT NULL | Giá sản phẩm |
| image | VARCHAR(255) | NULL | Đường dẫn ảnh sản phẩm |
| stock | INT UNSIGNED | NOT NULL, DEFAULT 0 | Số lượng tồn kho |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | Trạng thái hiển thị |
| created_at | DATETIME | NOT NULL | Thời điểm tạo |
| updated_at | DATETIME | NULL | Thời điểm cập nhật |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_products_is_active (is_active)
INDEX idx_products_name (name)
```

Notes:

```txt
- API GET /api/products chỉ trả sản phẩm active.
- API GET /api/products/{id} trả PRODUCT_NOT_FOUND nếu sản phẩm không tồn tại hoặc không active.
- stock dùng để kiểm tra số lượng khi add cart hoặc tạo order nếu có áp dụng.
```

---

## 10. carts

Bảng `carts` lưu giỏ hàng của user.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID cart |
| user_id | BIGINT UNSIGNED | FOREIGN KEY users(id), NOT NULL | User sở hữu cart |
| status | ENUM('ACTIVE','ORDERED','ABANDONED') | NOT NULL, DEFAULT 'ACTIVE' | Trạng thái cart |
| created_at | DATETIME | NOT NULL | Thời điểm tạo |
| updated_at | DATETIME | NULL | Thời điểm cập nhật |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_carts_user_id (user_id)
INDEX idx_carts_status (status)
INDEX idx_carts_user_status (user_id, status)
```

Foreign keys:

```txt
FOREIGN KEY (user_id) REFERENCES users(id)
```

Notes:

```txt
- Mỗi user có thể có một cart ACTIVE tại một thời điểm.
- Khi user add sản phẩm, CartService tìm cart ACTIVE hoặc tạo mới.
- Khi tạo order thành công, cart có thể chuyển sang ORDERED hoặc được clear tùy implementation.
```

---

## 11. cart_items

Bảng `cart_items` lưu sản phẩm trong giỏ hàng.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID cart item |
| cart_id | BIGINT UNSIGNED | FOREIGN KEY carts(id), NOT NULL | Cart chứa item |
| product_id | BIGINT UNSIGNED | FOREIGN KEY products(id), NOT NULL | Sản phẩm |
| quantity | INT UNSIGNED | NOT NULL | Số lượng |
| price | DECIMAL(12,2) | NOT NULL | Giá tại thời điểm thêm vào cart |
| created_at | DATETIME | NOT NULL | Thời điểm tạo |
| updated_at | DATETIME | NULL | Thời điểm cập nhật |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_cart_items_cart_id (cart_id)
INDEX idx_cart_items_product_id (product_id)
UNIQUE KEY unique_cart_product (cart_id, product_id)
```

Foreign keys:

```txt
FOREIGN KEY (cart_id) REFERENCES carts(id)
FOREIGN KEY (product_id) REFERENCES products(id)
```

Notes:

```txt
- unique_cart_product giúp một sản phẩm chỉ xuất hiện một dòng trong cùng một cart.
- Nếu add cùng product lần nữa, hệ thống tăng quantity thay vì tạo dòng mới.
- price lưu giá tại thời điểm thêm vào cart để tránh phụ thuộc hoàn toàn vào products.price sau này.
- quantity phải >= 1.
```

---

## 12. orders

Bảng `orders` lưu đơn hàng.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID order |
| user_id | BIGINT UNSIGNED | FOREIGN KEY users(id), NOT NULL | User tạo order |
| status | ENUM('PENDING','PAID','SHIPPING','COMPLETED','CANCELLED') | NOT NULL, DEFAULT 'PENDING' | Trạng thái order |
| total | DECIMAL(12,2) | NOT NULL, DEFAULT 0 | Tổng tiền |
| shipping_name | VARCHAR(100) | NOT NULL | Tên người nhận |
| shipping_phone | VARCHAR(20) | NOT NULL | Số điện thoại nhận hàng |
| shipping_address | VARCHAR(255) | NOT NULL | Địa chỉ nhận hàng |
| payment_method | ENUM('COD','BANK_TRANSFER','E_WALLET') | NOT NULL, DEFAULT 'COD' | Phương thức thanh toán |
| created_at | DATETIME | NOT NULL | Thời điểm tạo |
| updated_at | DATETIME | NULL | Thời điểm cập nhật |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_orders_user_id (user_id)
INDEX idx_orders_status (status)
INDEX idx_orders_created_at (created_at)
```

Foreign keys:

```txt
FOREIGN KEY (user_id) REFERENCES users(id)
```

Notes:

```txt
- Customer chỉ được xem order của chính mình.
- Admin có thể xem tất cả order.
- OrderService phải kiểm tra quyền trước khi trả chi tiết order.
- Nếu customer truy cập order của user khác thì trả IDOR_BLOCKED hoặc ORDER_ACCESS_DENIED.
```

---

## 13. order_items

Bảng `order_items` lưu chi tiết sản phẩm trong đơn hàng.

| Column | Type | Constraint | Description |
|---|---|---|---|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | ID order item |
| order_id | BIGINT UNSIGNED | FOREIGN KEY orders(id), NOT NULL | Order chứa item |
| product_id | BIGINT UNSIGNED | FOREIGN KEY products(id), NOT NULL | Sản phẩm |
| product_name | VARCHAR(150) | NOT NULL | Tên sản phẩm tại thời điểm đặt |
| price | DECIMAL(12,2) | NOT NULL | Giá tại thời điểm đặt |
| quantity | INT UNSIGNED | NOT NULL | Số lượng |
| subtotal | DECIMAL(12,2) | NOT NULL | Thành tiền |

Indexes:

```txt
PRIMARY KEY (id)
INDEX idx_order_items_order_id (order_id)
INDEX idx_order_items_product_id (product_id)
```

Foreign keys:

```txt
FOREIGN KEY (order_id) REFERENCES orders(id)
FOREIGN KEY (product_id) REFERENCES products(id)
```

Notes:

```txt
- product_name và price được snapshot tại thời điểm đặt hàng.
- subtotal = price * quantity.
- Việc snapshot giúp order không bị thay đổi khi product name/price thay đổi sau này.
```

---

## 14. Twilio Verify Storage Notes

Hệ thống hiện không tạo bảng riêng cho Twilio Verify.

Lý do:

```txt
- Twilio Verify đã lưu verification session ở phía Twilio.
- Backend chỉ cần lưu login challenge, OTP local audit record và risk state.
- Verification SID được trả về API response dưới dạng sms_transaction_id.
- Verification SID hiện không bắt buộc lưu database vì verify check dùng To + Code + Service SID.
```

Cấu hình Twilio nằm trong `.env`:

```env
SMS_PROVIDER=twilio_verify
TWILIO_ACCOUNT_SID=...
TWILIO_AUTH_TOKEN=...
TWILIO_VERIFY_SERVICE_SID=...
TWILIO_VERIFY_CHANNEL=call
TWILIO_VERIFY_API_URL=https://verify.twilio.com/v2
```

Security notes:

```txt
- Không lưu Twilio Auth Token trong database.
- Không commit Twilio Auth Token lên GitHub.
- Không đưa Twilio Auth Token thật vào báo cáo.
- Nếu token bị lộ, cần reset trong Twilio Console.
```

---

## 15. Required Repository Files

Repository cho nhóm bảo mật:

```txt
app/Repositories/UserRepository.php
app/Repositories/LoginChallengeRepository.php
app/Repositories/OtpRepository.php
app/Repositories/TokenRepository.php
app/Repositories/LoginAttemptRepository.php
app/Repositories/RiskLogRepository.php
```

Repository cho nhóm thương mại điện tử:

```txt
app/Repositories/ProductRepository.php
app/Repositories/CartRepository.php
app/Repositories/OrderRepository.php
```

Mapping:

| Table | Repository |
|---|---|
| users | UserRepository |
| login_challenges | LoginChallengeRepository |
| otps | OtpRepository |
| tokens | TokenRepository |
| login_attempts | LoginAttemptRepository |
| risk_logs | RiskLogRepository |
| products | ProductRepository |
| carts | CartRepository |
| cart_items | CartRepository |
| orders | OrderRepository |
| order_items | OrderRepository |

---

## 16. Security Implementation Notes

```txt
- users là bảng trung tâm cho customer và admin.
- login_challenges là bảng trung tâm của luồng MFA.
- otps chỉ lưu hash/local audit, không lưu OTP plain text.
- tokens chỉ lưu token_hash, không lưu plain token.
- login_attempts dùng để kiểm soát sai mật khẩu nhiều lần.
- risk_logs dùng để ghi rule đã kích hoạt.
- otp_send_count và otp_wrong_count đặt ở login_challenges, không đặt ở otps.
- RuleEngineService đọc trạng thái từ login_challenges, login_attempts và risk_logs.
- RiskService cập nhật risk_score, risk_level và status của login_challenges.
- AuthMiddleware xác thực bằng tokens.token_hash.
- UserService và OrderService kiểm soát IDOR.
- AdminMiddleware kiểm tra users.role = admin.
```

---

## 17. Data Integrity Notes

```txt
- Không xóa vật lý user nếu đã có order, token, risk log hoặc login attempt liên quan.
- Có thể dùng is_active = 0 để vô hiệu hóa user.
- Không xóa order đã tạo.
- order_items phải snapshot product_name và price.
- cart_items có thể xóa khi user clear cart hoặc sau khi tạo order.
- tokens có thể revoke bằng revoked_at thay vì xóa.
- risk_logs giữ lại để phục vụ audit bảo mật.
```

---

## 18. Suggested Reset SQL for Testing

Khi test lại toàn bộ flow local, có thể reset các bảng nghiệp vụ tạm thời theo thứ tự phụ thuộc khóa ngoại.

```sql
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE risk_logs;
TRUNCATE TABLE otps;
TRUNCATE TABLE tokens;
TRUNCATE TABLE login_challenges;
TRUNCATE TABLE login_attempts;

TRUNCATE TABLE order_items;
TRUNCATE TABLE orders;
TRUNCATE TABLE cart_items;
TRUNCATE TABLE carts;

SET FOREIGN_KEY_CHECKS = 1;
```

Không truncate bảng này nếu muốn giữ dữ liệu seed:

```txt
users
products
```

---

## 19. Suggested Dev Tokens

Có thể tạo token dev để test nhanh API protected.

Customer token:

```sql
DELETE FROM tokens WHERE token_hash = SHA2('TEST', 256);

INSERT INTO tokens (user_id, token_hash, expires_at, revoked_at, created_at)
VALUES (1, SHA2('TEST', 256), DATE_ADD(NOW(), INTERVAL 30 DAY), NULL, NOW());
```

Admin token:

```sql
DELETE FROM tokens WHERE token_hash = SHA2('TEST_ADMIN', 256);

INSERT INTO tokens (user_id, token_hash, expires_at, revoked_at, created_at)
VALUES (3, SHA2('TEST_ADMIN', 256), DATE_ADD(NOW(), INTERVAL 30 DAY), NULL, NOW());
```

Postman headers:

```http
Authorization: Bearer TEST
Authorization: Bearer TEST_ADMIN
```

Notes:

```txt
- Token dev chỉ dùng local.
- Không dùng token cố định trong production.
- Logout sẽ revoke token bằng cách set revoked_at.
```

---

## 20. Final Notes

```txt
Database design hiện tại hỗ trợ đầy đủ:
- Password login.
- MFA bằng OTP.
- Mock OTP provider.
- Twilio Verify call provider.
- Rule Engine.
- Risk logging.
- Token authentication.
- Token revoke/logout.
- IDOR protection.
- Admin authorization.
- Product listing/detail.
- Cart management.
- Order creation/list/detail.
```