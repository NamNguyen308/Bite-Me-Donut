# Architecture Document

## 1. Architecture Overview

Dự án sử dụng kiến trúc kết hợp:

```txt
MVC + Service Layer + Repository Pattern + Middleware + Front Controller
```

Mục tiêu chính của kiến trúc là tách rõ trách nhiệm giữa các lớp xử lý, giúp hệ thống dễ mở rộng, dễ test và phù hợp với yêu cầu bảo mật trong thương mại điện tử.

| Layer | Responsibility |
|---|---|
| Front Controller | Điểm vào duy nhất của API, load config, autoload class, nạp route và dispatch request |
| Router | Map HTTP method + path đến controller action |
| Controller | Nhận request, gọi service, trả response |
| Service | Xử lý nghiệp vụ chính |
| Repository | Thao tác database |
| Middleware | Kiểm tra điều kiện truy cập trước khi vào controller |
| Core | Database, Router, Request, Response, Config |

Luồng bảo mật chính của hệ thống:

```txt
Password verification
→ Login challenge
→ OTP request
→ OTP verification
→ Complete login
→ Access token
```

Nguyên tắc bảo mật cốt lõi:

```txt
User nhập đúng password chưa được xem là đăng nhập thành công.
Access token chỉ được cấp sau khi OTP đã được xác minh thành công.
```

---

## 2. Runtime Environment

Project chạy local bằng Laragon.

Base URL:

```txt
http://ecommerce_security_platform.test
```

API prefix:

```txt
/api
```

Không dùng `/public` trong URL khi test trên Laragon virtual host.

Ví dụ endpoint đúng:

```txt
http://ecommerce_security_platform.test/api/health
```

Ví dụ endpoint không dùng:

```txt
http://ecommerce_security_platform.test/public/api/health
```

File entry point:

```txt
public/api.php
```

Front Controller `public/api.php` chịu trách nhiệm:

```txt
- Đăng ký autoload cho namespace App\
- Load .env thông qua Config
- Set timezone từ APP_TIMEZONE
- Khởi tạo Router
- Nạp routes/api.php
- Tạo Request
- Dispatch request đến route tương ứng
```

---

## 3. Implemented Scope

Các nhóm chức năng đã triển khai trong backend:

```txt
1. Health check
2. Password login
3. Login challenge
4. OTP request
5. OTP verify
6. Complete login
7. Token authentication
8. Logout / token revoke
9. User profile
10. IDOR protection
11. Admin dashboard
12. Product listing/detail
13. Cart management
14. Order creation/list/detail
15. Rule Engine
16. Risk logging
17. Mock OTP provider
18. Twilio Verify provider qua voice call
```

Các route chính:

```txt
GET  /api/health

POST /api/auth/login
POST /api/otp/request
POST /api/otp/verify
POST /api/auth/complete-login
POST /api/auth/logout

GET  /api/users/me
GET  /api/users/{id}

GET  /api/admin/dashboard

GET  /api/products
GET  /api/products/{id}

GET  /api/cart
POST /api/cart/add
POST /api/cart/update
POST /api/cart/remove
POST /api/cart/clear

GET  /api/orders
POST /api/orders
GET  /api/orders/{id}
```

---

## 4. High-Level API Flow

Luồng request tổng quát:

```txt
Client / Postman / Frontend
 ↓
public/api.php
 ↓
Router
 ↓
Middleware
 ↓
Controller
 ↓
Service
 ↓
Repository
 ↓
Database
```

Trong đó:

```txt
Client gửi HTTP request
Front Controller nhận request
Router tìm route phù hợp
Middleware kiểm tra token/role nếu cần
Controller gọi service
Service xử lý nghiệp vụ
Repository đọc/ghi database
Response trả JSON chuẩn cho client
```

---

## 5. Main Login Flow

Luồng đăng nhập đầy đủ gồm 4 bước:

```txt
Step 1: POST /api/auth/login
Step 2: POST /api/otp/request
Step 3: POST /api/otp/verify
Step 4: POST /api/auth/complete-login
```

Sơ đồ tổng quát:

```txt
User nhập phone/email + password
 ↓
POST /api/auth/login
 ↓
Password đúng
 ↓
Tạo login_challenge_id
 ↓
POST /api/otp/request
 ↓
Gửi OTP qua provider
 ↓
POST /api/otp/verify
 ↓
OTP đúng
 ↓
Challenge status = OTP_VERIFIED
 ↓
POST /api/auth/complete-login
 ↓
Sinh access_token
```

Nguyên tắc:

```txt
- /api/auth/login không trả access_token.
- /api/otp/request chỉ gửi OTP, chưa cấp token.
- /api/otp/verify chỉ xác minh OTP, chưa cấp token.
- /api/auth/complete-login mới tạo access_token.
```

---

## 6. Step 1: POST /api/auth/login

Input:

```txt
phone/email + password
```

Processing:

```txt
1. Validate request.
2. Tìm user theo phone hoặc email.
3. Kiểm tra user tồn tại.
4. Kiểm tra user đang active.
5. Kiểm tra password bằng password_verify().
6. Nếu sai password:
   - Ghi login_attempts với status FAILED.
   - Kích hoạt Rule Engine cho rule R3 nếu vượt ngưỡng.
   - Trả INVALID_CREDENTIALS hoặc RISK_LEVEL_HIGH.
7. Nếu đúng password:
   - Ghi login_attempts với status SUCCESS.
   - Tạo login_challenge.
   - Gắn thông tin IP, user agent, expires_at.
   - Nếu trước đó đã có rule R3 phù hợp, attach risk vào challenge mới.
8. Trả login_challenge_id.
9. Không tạo token ở bước này.
```

Output chính:

```txt
login_challenge_id
status = PENDING_OTP
```

Responsible components:

```txt
AuthController
AuthService
UserRepository
LoginAttemptRepository
LoginChallengeRepository
RuleEngineService
RiskService
```

Database tables:

```txt
users
login_attempts
login_challenges
risk_logs
```

Security notes:

```txt
- Password không bao giờ lưu plain text.
- password_hash lưu trong users.password_hash.
- Password được kiểm tra bằng password_verify().
- API login không cấp token để tránh bypass MFA.
```

---

## 7. Step 2: POST /api/otp/request

Input:

```txt
login_challenge_id
```

Processing:

```txt
1. Validate login_challenge_id.
2. Kiểm tra challenge tồn tại.
3. Kiểm tra challenge chưa hết hạn.
4. Kiểm tra challenge chưa bị BLOCKED.
5. Chỉ cho request OTP nếu status thuộc:
   - PENDING_OTP
   - OTP_SENT
6. Tăng otp_send_count.
7. Gọi RuleEngineService để đánh giá rule R2.
8. Nếu risk_level = HIGH:
   - Trả RISK_LEVEL_HIGH.
   - Không gửi OTP.
9. Kiểm tra OTP_MAX_RESEND.
10. Tìm user sở hữu challenge.
11. Tạo một dòng OTP local trong bảng otps để audit.
12. Gọi SmsService để gửi OTP qua provider.
13. Nếu gửi thành công:
   - Cập nhật challenge status = OTP_SENT.
   - Trả thông tin provider và transaction id nếu có.
14. Nếu gửi thất bại:
   - Trả SMS_SEND_FAILED.
```

Output chính:

```txt
status = OTP_SENT
expires_in
risk_score
risk_level
sms_provider
sms_transaction_id nếu provider có trả
```

Responsible components:

```txt
OtpController
OtpService
SmsService
LoginChallengeRepository
OtpRepository
UserRepository
RuleEngineService
RiskService
```

Database tables:

```txt
login_challenges
otps
users
risk_logs
```

Provider modes:

```txt
SMS_PROVIDER=mock
SMS_PROVIDER=twilio_verify
```

Mock mode:

```txt
- Backend tự sinh OTP.
- Backend hash OTP vào otps.otp_hash.
- Response trả mock_otp_code để test local.
- Verify OTP bằng password_verify().
```

Twilio Verify mode:

```txt
- Backend gọi Twilio Verify để gửi OTP.
- Channel hiện tại dùng call.
- Twilio tự sinh mã OTP.
- Twilio gọi điện đọc mã OTP cho user.
- Backend vẫn tạo OTP local để audit, giới hạn thời gian và mark used.
- OTP local không phải source of truth cho mã Twilio.
```

Important notes:

```txt
- Với twilio_verify, OTP không xuất hiện trong API response.
- sms_transaction_id là verification SID từ Twilio, ví dụ VE...
- OTP thật được user nhận qua cuộc gọi.
```

---

## 8. Twilio Verify Provider Architecture

Provider thực tế đang dùng:

```txt
Twilio Verify
Channel: call
```

Cấu hình `.env`:

```env
SMS_PROVIDER=twilio_verify

TWILIO_ACCOUNT_SID=...
TWILIO_AUTH_TOKEN=...
TWILIO_VERIFY_SERVICE_SID=...
TWILIO_VERIFY_CHANNEL=call
TWILIO_VERIFY_API_URL=https://verify.twilio.com/v2
```

Luồng request OTP với Twilio Verify:

```txt
OtpService
 ↓
SmsService::sendOtp()
 ↓
SmsService::sendViaTwilioVerify()
 ↓
POST https://verify.twilio.com/v2/Services/{SERVICE_SID}/Verifications
 ↓
Payload:
To=+84...
Channel=call
 ↓
Twilio gọi điện cho user và đọc OTP
```

Luồng verify OTP với Twilio Verify:

```txt
OtpService
 ↓
SmsService::verifyTwilioOtp()
 ↓
POST https://verify.twilio.com/v2/Services/{SERVICE_SID}/VerificationCheck
 ↓
Payload:
To=+84...
Code=OTP_USER_ENTERED
 ↓
Twilio trả:
status=approved
valid=true
 ↓
OtpService mark OTP local used
 ↓
login_challenges.status = OTP_VERIFIED
```

Lưu ý về `CustomCode`:

```txt
Twilio Verify Service hiện tại không cho phép CustomCode.
Do đó backend không tự gửi mã OTP sinh sẵn sang Twilio.
Twilio tự sinh OTP và tự xác minh OTP.
```

Vai trò của SmsService trong Twilio Verify mode:

```txt
SmsService được phép gọi API provider để:
- Start verification
- Check verification

Nhưng SmsService không được:
- Tự update login_challenges
- Tự mark OTP used
- Tự quyết định user đã login thành công
- Tự sinh access token
```

Quyết định nghiệp vụ vẫn nằm ở OtpService:

```txt
OtpService nhận kết quả provider.
OtpService quyết định OTP hợp lệ ở cấp domain.
OtpService cập nhật otps và login_challenges.
OtpService gọi RuleEngine khi OTP sai.
```

---

## 9. Step 3: POST /api/otp/verify

Input:

```txt
login_challenge_id + otp_code
```

Processing chung:

```txt
1. Validate login_challenge_id.
2. Validate otp_code phải có 6 chữ số.
3. Kiểm tra challenge tồn tại.
4. Kiểm tra challenge chưa hết hạn.
5. Kiểm tra challenge chưa bị BLOCKED.
6. Kiểm tra challenge status = OTP_SENT.
7. Lấy OTP local mới nhất theo challenge_id.
8. Kiểm tra OTP local tồn tại.
9. Kiểm tra OTP local chưa dùng.
10. Kiểm tra OTP local chưa hết hạn.
```

Processing với `SMS_PROVIDER=mock`:

```txt
1. So sánh otp_code với otps.otp_hash bằng password_verify().
2. Nếu sai:
   - Tăng otp_wrong_count.
   - Gọi RuleEngineService cho rule R1.
   - Nếu risk HIGH thì block.
   - Nếu vượt OTP_MAX_WRONG_ATTEMPTS thì expire challenge.
   - Trả OTP_INVALID hoặc OTP_TOO_MANY_ATTEMPTS.
3. Nếu đúng:
   - Mark OTP local is_used = 1.
   - Set used_at.
   - Update challenge status = OTP_VERIFIED.
```

Processing với `SMS_PROVIDER=twilio_verify`:

```txt
1. Không dùng password_verify() với otp_hash để xác minh mã Twilio.
2. Gọi SmsService::verifyTwilioOtp(phone, otp_code).
3. SmsService gọi Twilio VerificationCheck.
4. Nếu Twilio trả valid=true và status=approved:
   - Mark OTP local is_used = 1.
   - Set used_at.
   - Update challenge status = OTP_VERIFIED.
5. Nếu Twilio trả mã sai:
   - Tăng otp_wrong_count.
   - Gọi RuleEngineService cho rule R1.
   - Trả OTP_INVALID hoặc RISK_LEVEL_HIGH.
6. Nếu lỗi provider/cURL/config:
   - Trả OTP_PROVIDER_VERIFY_FAILED hoặc lỗi provider tương ứng.
```

Output chính:

```txt
status = OTP_VERIFIED
risk_score
risk_level
```

Responsible components:

```txt
OtpController
OtpService
SmsService
LoginChallengeRepository
OtpRepository
UserRepository
RuleEngineService
RiskService
```

Database tables:

```txt
login_challenges
otps
users
risk_logs
```

Security notes:

```txt
- Controller không tự verify OTP.
- OtpService là nơi điều phối nghiệp vụ verify OTP.
- SmsService chỉ là adapter giao tiếp provider.
- Với mock, OTP được verify bằng hash local.
- Với Twilio Verify, OTP được verify bởi Twilio VerificationCheck.
- Dù provider là Twilio Verify, backend vẫn kiểm soát challenge status, expiry, wrong count và risk score.
```

---

## 10. Step 4: POST /api/auth/complete-login

Input:

```txt
login_challenge_id
```

Processing:

```txt
1. Validate login_challenge_id.
2. Kiểm tra challenge tồn tại.
3. Kiểm tra challenge chưa hết hạn.
4. Kiểm tra challenge chưa bị BLOCKED.
5. Kiểm tra challenge status = OTP_VERIFIED.
6. Nếu chưa OTP_VERIFIED:
   - Trả OTP_REQUIRED.
7. Nếu đã OTP_VERIFIED:
   - Sinh plain access token bằng random bytes.
   - Hash token bằng SHA-256.
   - Lưu token_hash vào database.
   - Set expires_at theo TOKEN_EXPIRE_MINUTES.
   - Cập nhật challenge status = AUTHENTICATED.
   - Set authenticated_at.
   - Trả plain access token cho client đúng một lần.
```

Output chính:

```txt
access_token
token_type = Bearer
expires_in
user
```

Responsible components:

```txt
AuthController
AuthService
TokenService
LoginChallengeRepository
TokenRepository
UserRepository
```

Database tables:

```txt
login_challenges
tokens
users
```

Security notes:

```txt
- Plain access token chỉ trả về một lần.
- Database không lưu plain token.
- Database chỉ lưu token_hash.
- Client phải gửi token bằng Authorization Bearer.
```

---

## 11. Logout Flow

Endpoint:

```txt
POST /api/auth/logout
```

Processing:

```txt
1. AuthMiddleware kiểm tra Bearer token.
2. TokenService hash plain token từ request.
3. TokenRepository tìm token_hash.
4. Kiểm tra token chưa hết hạn.
5. Kiểm tra token chưa revoked.
6. AuthController gọi AuthService logout.
7. TokenService set revoked_at = NOW().
8. Các request sau bằng token này sẽ bị TOKEN_REVOKED.
```

Responsible components:

```txt
AuthMiddleware
AuthController
AuthService
TokenService
TokenRepository
```

Database tables:

```txt
tokens
```

---

## 12. Token Authentication Flow

Các API cần đăng nhập dùng header:

```http
Authorization: Bearer <access_token>
```

AuthMiddleware xử lý:

```txt
1. Đọc Authorization header.
2. Kiểm tra header có Bearer token.
3. Hash plain token bằng SHA-256.
4. Tìm token_hash trong bảng tokens.
5. Kiểm tra token tồn tại.
6. Kiểm tra token chưa hết hạn.
7. Kiểm tra token chưa revoked.
8. Lấy user tương ứng.
9. Gắn user vào request context.
10. Cho request đi tiếp vào controller.
```

Các lỗi có thể trả:

```txt
TOKEN_MISSING
TOKEN_INVALID
TOKEN_EXPIRED
TOKEN_REVOKED
```

---

## 13. Login Challenge Status

| Status | Meaning |
|---|---|
| PENDING_OTP | Password đúng, đang chờ request OTP |
| OTP_SENT | OTP đã gửi qua mock hoặc Twilio Verify |
| OTP_VERIFIED | OTP đã xác minh thành công |
| AUTHENTICATED | Đã tạo access token, login hoàn tất |
| EXPIRED | Challenge hết hạn |
| BLOCKED | Challenge bị chặn do risk cao hoặc sai quá nhiều |

---

## 14. Login Challenge State Transition

| Current status | Action | Next status |
|---|---|---|
| None | Password đúng | PENDING_OTP |
| PENDING_OTP | Request OTP thành công | OTP_SENT |
| PENDING_OTP | Challenge hết hạn | EXPIRED |
| PENDING_OTP | Risk level HIGH | BLOCKED |
| OTP_SENT | Verify OTP đúng | OTP_VERIFIED |
| OTP_SENT | OTP hết hạn | EXPIRED |
| OTP_SENT | Sai OTP vượt giới hạn | EXPIRED hoặc BLOCKED |
| OTP_SENT | Risk level HIGH | BLOCKED |
| OTP_VERIFIED | Complete login thành công | AUTHENTICATED |
| Any | Challenge hết hạn | EXPIRED |
| Any | Risk level HIGH | BLOCKED |

Ghi chú:

```txt
Trường hợp sai OTP quá số lần cho phép nhưng risk chưa HIGH, hệ thống có thể chuyển challenge sang EXPIRED.
Trường hợp risk HIGH, hệ thống chuyển challenge sang BLOCKED.
```

---

## 15. Rule Engine Position

Rule Engine được gọi từ service nghiệp vụ khi có hành vi bất thường:

```txt
AuthService / OtpService
 ↓
RuleEngineService
 ↓
RiskService
 ↓
RiskLogRepository
 ↓
risk_logs
```

Các rule chính:

```txt
R1: Nhập sai OTP > 1 lần
R2: Request OTP > 2 lần
R3: Sai password > 2 lần
C1: R1 + R2
C2: R1 + R3
C3: R2 + R3
C4: R1 + R2 + R3
```

Risk level:

```txt
LOW: 0–19
MEDIUM: 20–50
HIGH: >= 51
```

Khi risk level HIGH:

```txt
- Challenge có thể chuyển sang BLOCKED.
- blocked_until có thể được set.
- API trả RISK_LEVEL_HIGH.
```

Chi tiết rule, điểm số và action được định nghĩa tại:

```txt
docs/security-rules.md
```

Các file khác chỉ tham chiếu rule code, không lặp lại toàn bộ rule mapping.

---

## 16. Risk Flow

Luồng risk khi sai password:

```txt
POST /api/auth/login
 ↓
Password sai
 ↓
AuthService ghi login_attempts
 ↓
RuleEngineService đánh giá R3
 ↓
RiskService ghi risk_logs
 ↓
Cập nhật risk nếu có challenge phù hợp
```

Luồng risk khi request OTP nhiều lần:

```txt
POST /api/otp/request
 ↓
OtpService tăng otp_send_count
 ↓
RuleEngineService đánh giá R2
 ↓
RiskService ghi risk_logs
 ↓
Cập nhật login_challenges.risk_score / risk_level
```

Luồng risk khi nhập sai OTP:

```txt
POST /api/otp/verify
 ↓
OtpService xác định OTP sai
 ↓
Tăng otp_wrong_count
 ↓
RuleEngineService đánh giá R1
 ↓
RiskService ghi risk_logs
 ↓
Cập nhật login_challenges.risk_score / risk_level / status
```

---

## 17. User and IDOR Protection

API user:

```txt
GET /api/users/me
GET /api/users/{id}
```

Nguyên tắc:

```txt
- Customer chỉ được xem thông tin của chính mình.
- Admin được xem thông tin user khác.
- Nếu customer truy cập user_id khác thì trả IDOR_BLOCKED.
```

Flow:

```txt
AuthMiddleware xác thực token
 ↓
UserController gọi UserService
 ↓
UserService kiểm tra current_user.id và requested id
 ↓
Nếu không khớp và role không phải admin
 ↓
Trả IDOR_BLOCKED
```

Security notes:

```txt
IDOR protection nằm ở service layer.
Controller không tự quyết định quyền truy cập dữ liệu user khác.
```

---

## 18. Admin Access Flow

Endpoint đã triển khai:

```txt
GET /api/admin/dashboard
```

Authentication:

```txt
Authorization: Bearer <admin_access_token>
```

Processing:

```txt
1. AuthMiddleware xác thực token.
2. AdminMiddleware kiểm tra role = admin.
3. AdminController gọi AdminService.
4. AdminService lấy thống kê users, products, orders, risk logs.
5. Trả dashboard data.
```

Nếu user thường truy cập admin dashboard:

```txt
ACCESS_DENIED
```

Responsible components:

```txt
AuthMiddleware
AdminMiddleware
AdminController
AdminService
UserRepository
ProductRepository
OrderRepository
RiskLogRepository
```

---

## 19. Product Flow

Endpoints:

```txt
GET /api/products
GET /api/products/{id}
```

Characteristics:

```txt
- Không yêu cầu đăng nhập.
- Chỉ trả sản phẩm active/available.
- ProductRepository đọc dữ liệu từ database.
- ProductService xử lý nghiệp vụ lấy danh sách/chi tiết.
```

Flow:

```txt
ProductController
 ↓
ProductService
 ↓
ProductRepository
 ↓
products
```

---

## 20. Cart Flow

Endpoints:

```txt
GET  /api/cart
POST /api/cart/add
POST /api/cart/update
POST /api/cart/remove
POST /api/cart/clear
```

Authentication:

```txt
Required
```

Processing:

```txt
1. AuthMiddleware xác thực token.
2. CartController nhận request.
3. CartService xử lý nghiệp vụ giỏ hàng.
4. ProductService/ProductRepository kiểm tra sản phẩm nếu cần.
5. CartRepository đọc/ghi cart và cart_items.
6. Trả cart data.
```

Nguyên tắc:

```txt
- Mỗi user thao tác trên cart của chính mình.
- Không cho thao tác cart của user khác.
- product_id phải tồn tại.
- quantity phải hợp lệ.
```

---

## 21. Order Flow

Endpoints:

```txt
GET  /api/orders
POST /api/orders
GET  /api/orders/{id}
```

Authentication:

```txt
Required
```

POST /api/orders processing:

```txt
1. AuthMiddleware xác thực token.
2. OrderController nhận shipping info.
3. OrderService kiểm tra cart hiện tại.
4. Nếu cart rỗng, trả CART_EMPTY.
5. Tạo order.
6. Tạo order_items từ cart_items.
7. Tính total.
8. Clear hoặc close cart sau khi tạo order.
9. Trả ORDER_CREATED.
```

GET /api/orders processing:

```txt
- Customer chỉ xem order của mình.
- Admin có thể xem tất cả order.
```

GET /api/orders/{id} processing:

```txt
- Customer chỉ xem order do mình sở hữu.
- Admin có thể xem order của user khác.
- Nếu customer truy cập order của user khác, trả IDOR_BLOCKED hoặc ORDER_ACCESS_DENIED.
```

Responsible components:

```txt
OrderController
OrderService
CartService
OrderRepository
CartRepository
ProductRepository
TokenRepository
```

---

## 22. Layer Responsibility Rules

### 22.1. Controller

Controller chỉ được:

```txt
- Nhận request.
- Lấy input từ Request.
- Gọi Service tương ứng.
- Chuyển kết quả service thành Response.
```

Controller không được:

```txt
- Viết SQL.
- Tự xử lý business logic.
- Tự hash password.
- Tự sinh OTP.
- Tự verify OTP.
- Tự tạo token.
- Tự tính risk score.
- Tự gọi trực tiếp database.
```

Ví dụ:

```txt
AuthController không tự kiểm tra password.
OtpController không tự gọi Twilio.
UserController không tự xử lý IDOR.
```

---

### 22.2. Service

Service xử lý nghiệp vụ chính.

| Service | Responsibility |
|---|---|
| AuthService | Login, complete login, logout |
| OtpService | Request OTP, verify OTP, xử lý OTP sai/hết hạn/đã dùng, điều phối provider result |
| SmsService | Adapter gửi OTP và check OTP với provider như mock/Twilio Verify |
| TokenService | Tạo token, verify token, revoke token |
| RuleEngineService | Kiểm tra các rule bảo mật |
| RiskService | Ghi risk log, tính risk score, risk level, quyết định action |
| UserService | Lấy thông tin user, kiểm tra IDOR user |
| ProductService | Lấy danh sách và chi tiết sản phẩm |
| CartService | Quản lý giỏ hàng |
| OrderService | Tạo và truy vấn đơn hàng, kiểm tra quyền order |
| AdminService | Dashboard admin |

Service không được:

```txt
- Echo JSON response trực tiếp.
- Nhận raw HTTP request trực tiếp.
- Viết SQL nếu đã có Repository tương ứng.
- Bỏ qua Repository để thao tác database.
```

---

### 22.3. Repository

Repository chỉ thao tác database.

| Repository | Responsibility |
|---|---|
| UserRepository | Truy vấn user/admin |
| LoginChallengeRepository | Lưu và cập nhật trạng thái login challenge |
| OtpRepository | Lưu OTP local, lấy OTP mới nhất, mark OTP used |
| TokenRepository | Lưu token hash, tìm token hợp lệ, revoke token |
| LoginAttemptRepository | Ghi nhận login attempt |
| RiskLogRepository | Ghi risk log, lấy rule đã kích hoạt |
| ProductRepository | Truy vấn sản phẩm |
| CartRepository | Truy vấn và cập nhật giỏ hàng |
| OrderRepository | Tạo và truy vấn đơn hàng |

Repository không được:

```txt
- Xử lý nghiệp vụ.
- Tự tính risk score.
- Tự quyết định block user.
- Tự tạo response JSON.
- Tự gọi external provider.
```

---

### 22.4. Middleware

Middleware chỉ kiểm tra điều kiện trước khi request vào Controller.

| Middleware | Responsibility |
|---|---|
| AuthMiddleware | Kiểm tra thiếu token, token sai, token hết hạn, token revoked |
| AdminMiddleware | Kiểm tra role admin |
| RequestValidationMiddleware | Kiểm tra thiếu field, sai format nếu được dùng |
| RateLimitMiddleware | Kiểm tra tần suất request cơ bản nếu được bổ sung |

Middleware không được:

```txt
- Tự login user.
- Tự sinh OTP.
- Tự verify OTP.
- Tự tạo token.
- Tự tính toàn bộ risk score.
- Tự ghi order/cart/user business data.
```

---

## 23. Core Components

| Core class | Responsibility |
|---|---|
| Database | Quản lý kết nối PDO |
| Router | Map method + path đến controller action |
| Request | Đọc method, path, body, query, header |
| Response | Chuẩn hóa JSON response |
| Config | Đọc config từ .env |
| Controller | Base class cho controller nếu cần |

Core rules:

```txt
- Response JSON phải đi qua Response helper/core.
- Config phải đọc từ .env hoặc config file.
- Router không xử lý business logic.
- Request chỉ đọc dữ liệu request, không validate nghiệp vụ.
```

---

## 24. Database-Centric Security Design

Các bảng bảo mật chính:

```txt
users
login_challenges
otps
tokens
login_attempts
risk_logs
```

Vai trò:

```txt
users:
- Lưu user, admin, password_hash, role, is_active.

login_challenges:
- Lưu trạng thái MFA tạm thời.
- Lưu otp_send_count, otp_wrong_count.
- Lưu risk_score, risk_level, blocked_until.

otps:
- Lưu OTP local/audit record.
- Với mock: otp_hash là hash của OTP thật do backend sinh.
- Với twilio_verify: otp_hash là placeholder local, không phải source of truth của OTP Twilio.
- Dù provider nào, is_used và used_at vẫn dùng để chống verify lại cùng challenge.

tokens:
- Lưu access token đã hash.
- Không lưu plain token.

login_attempts:
- Ghi login success/failed.
- Là nguồn dữ liệu cho rule R3.

risk_logs:
- Ghi các rule đã kích hoạt.
- Có unique constraint theo challenge_id + rule_code để tránh cộng điểm lặp.
```

---

## 25. Provider Abstraction

SmsService đóng vai trò provider adapter.

Provider hiện hỗ trợ:

```txt
mock
twilio_verify
```

Mock provider:

```txt
- Không gọi external API.
- Dùng cho local test.
- Trả mock_otp_code trong response.
```

Twilio Verify provider:

```txt
- Gọi Twilio Verify API.
- Channel hiện dùng call.
- Không dùng CustomCode.
- Twilio tự sinh OTP.
- Twilio tự kiểm tra OTP qua VerificationCheck.
```

Mục tiêu abstraction:

```txt
- Có thể thay provider mà ít ảnh hưởng Controller.
- OtpService không cần biết chi tiết URL/cURL của provider.
- SmsService gom logic giao tiếp external API.
- Rule Engine không phụ thuộc provider.
```

---

## 26. Error Handling Architecture

Format lỗi chuẩn:

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {}
}
```

Các nhóm lỗi chính:

```txt
Validation:
- VALIDATION_ERROR

Authentication:
- INVALID_CREDENTIALS
- OTP_REQUIRED
- TOKEN_MISSING
- TOKEN_INVALID
- TOKEN_EXPIRED
- TOKEN_REVOKED

Challenge:
- LOGIN_CHALLENGE_NOT_FOUND
- LOGIN_CHALLENGE_EXPIRED
- LOGIN_CHALLENGE_BLOCKED

OTP:
- OTP_NOT_FOUND
- OTP_INVALID
- OTP_EXPIRED
- OTP_USED
- OTP_TOO_MANY_ATTEMPTS
- OTP_RESEND_LIMIT_EXCEEDED
- SMS_SEND_FAILED
- OTP_PROVIDER_VERIFY_FAILED

Authorization:
- ACCESS_DENIED
- ADMIN_REQUIRED
- IDOR_BLOCKED

Risk:
- RISK_LEVEL_HIGH
- RISK_LEVEL_MEDIUM

Resource:
- USER_NOT_FOUND
- PRODUCT_NOT_FOUND
- CART_EMPTY
- ORDER_NOT_FOUND
```

Provider failure handling:

```txt
Nếu Twilio Verify lỗi config/cURL/API:
- request OTP trả SMS_SEND_FAILED.
- verify OTP trả OTP_PROVIDER_VERIFY_FAILED.
- Không tự đánh dấu OTP đúng nếu provider không xác nhận valid=true.
```

---

## 27. Security Rules Summary

Global security rules:

```txt
1. Controller không chứa business logic.
2. Service không viết SQL trực tiếp nếu đã có Repository.
3. Repository không xử lý nghiệp vụ.
4. Middleware chỉ kiểm tra điều kiện truy cập.
5. OTP flow do OtpService điều phối.
6. SmsService chỉ là provider adapter, không cập nhật trạng thái domain.
7. Token chỉ xử lý trong TokenService.
8. Risk score chỉ xử lý trong RuleEngineService và RiskService.
9. Response JSON chỉ tạo qua Response helper/core.
10. Mọi config ngưỡng OTP/risk/token đặt trong .env.
11. AuthService là nơi tạo login challenge và complete login.
12. TokenService là nơi tạo, verify, revoke token.
13. Không lưu password plain text.
14. Không lưu OTP plain text.
15. Không lưu token plain text.
16. Không cấp token trước khi OTP_VERIFIED.
17. Không cho customer truy cập dữ liệu user/order của user khác.
18. Provider external không được bypass Rule Engine.
```

---

## 28. Configuration Values

Các config chính trong `.env`:

```env
APP_ENV=local
APP_URL=http://ecommerce_security_platform.test
APP_TIMEZONE=Asia/Ho_Chi_Minh

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=ecommerce_security_platform
DB_USER=root
DB_PASS=123456

LOGIN_CHALLENGE_EXPIRE_MINUTES=10
LOGIN_MAX_FAILED_ATTEMPTS=3

OTP_EXPIRE_MINUTES=5
OTP_MAX_WRONG_ATTEMPTS=3
OTP_MAX_RESEND=3

TOKEN_EXPIRE_MINUTES=60

SMS_PROVIDER=twilio_verify

TWILIO_ACCOUNT_SID=...
TWILIO_AUTH_TOKEN=...
TWILIO_VERIFY_SERVICE_SID=...
TWILIO_VERIFY_CHANNEL=call
TWILIO_VERIFY_API_URL=https://verify.twilio.com/v2
```

Security note:

```txt
Twilio Auth Token là secret.
Không commit token thật lên GitHub.
Không đưa token thật vào báo cáo.
Nếu token đã lộ trong chat/log, cần reset token trên Twilio Console.
```

---

## 29. Implementation Order

Thứ tự triển khai backend:

```txt
1. Config và .env
2. Core classes: Database, Router, Request, Response, Config
3. Database schema
4. Repositories
5. Services
6. Controllers
7. Middlewares
8. Routes
9. Mock OTP test
10. Rule Engine test
11. Token auth test
12. IDOR/Admin test
13. Product/Cart/Order test
14. Twilio Verify integration
15. Postman end-to-end test
16. Frontend HTML/CSS/JS
```

---

## 30. Final End-to-End Flow

End-to-end login thành công với Twilio Verify call:

```txt
1. User gọi POST /api/auth/login với phone/password.
2. Backend verify password.
3. Backend tạo login_challenge với status PENDING_OTP.
4. User gọi POST /api/otp/request với login_challenge_id.
5. Backend tăng otp_send_count.
6. Rule Engine đánh giá R2 nếu cần.
7. Backend tạo OTP local audit record.
8. Backend gọi Twilio Verify Verifications API.
9. Twilio gọi điện đọc OTP cho user.
10. User nhập OTP vào POST /api/otp/verify.
11. Backend gọi Twilio Verify VerificationCheck.
12. Twilio trả valid=true, status=approved.
13. Backend mark OTP local used.
14. Backend update challenge status OTP_VERIFIED.
15. User gọi POST /api/auth/complete-login.
16. Backend tạo access token.
17. Backend lưu token_hash.
18. Backend trả plain access token cho client.
19. Client dùng Authorization Bearer token cho API cần đăng nhập.
```

Kết luận kiến trúc:

```txt
Hệ thống đảm bảo password login không đủ để truy cập tài khoản.
MFA là bước bắt buộc trước khi cấp token.
Rule Engine theo dõi hành vi rủi ro trong login và OTP.
Access token được hash trong database.
Twilio Verify được tích hợp như provider thực tế để gửi/xác minh OTP qua cuộc gọi.
```