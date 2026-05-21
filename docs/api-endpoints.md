# API Endpoints

## 1. Base URL

Local Laragon:

```txt
http://ecommerce_security_platform.test/public
```

Local XAMPP fallback:

```txt
http://localhost/ecommerce_security_platform/public
```

API prefix:

```txt
/api
```

---

## 2. Standard Response Format

### Success Response

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

### Error Response

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {}
}
```

---

## 3. Authentication Header

Các API cần đăng nhập phải gửi access token qua header:

```http
Authorization: Bearer <access_token>
```

Token chỉ được tạo sau khi user hoàn tất đầy đủ luồng:

```txt
Password verified → OTP requested → OTP verified → Complete login → Access token issued
```

`POST /api/auth/login` chỉ kiểm tra mật khẩu và tạo `login_challenge_id`. API này không trả token.

---

## 4. HTTP Status Code Convention

| Status | Meaning | Use case |
|---|---|---|
| 200 | OK | Request thành công |
| 201 | Created | Tạo tài nguyên thành công |
| 400 | Bad Request | Thiếu field, sai format, request không hợp lệ |
| 401 | Unauthorized | Thiếu token, token sai, token hết hạn, sai thông tin đăng nhập |
| 403 | Forbidden | Không có quyền, chưa verify OTP, truy cập dữ liệu user khác |
| 404 | Not Found | Không tìm thấy dữ liệu |
| 409 | Conflict | Dữ liệu đã tồn tại |
| 429 | Too Many Requests | Request OTP quá nhiều, nhập sai OTP quá nhiều, bị timeout |
| 500 | Internal Server Error | Lỗi server |

---

## 5. API Summary

### Auth API

| Method | Endpoint | Purpose | Auth required |
|---|---|---|---|
| POST | `/api/auth/register` | Đăng ký tài khoản user | No |
| POST | `/api/auth/login` | Kiểm tra phone/email + password, tạo login challenge | No |
| POST | `/api/auth/complete-login` | Tạo access token sau khi OTP verified | No |
| POST | `/api/auth/logout` | Thu hồi token hiện tại | Yes |

### OTP API

| Method | Endpoint | Purpose | Auth required |
|---|---|---|---|
| POST | `/api/otp/request` | Gửi OTP theo login challenge | No |
| POST | `/api/otp/verify` | Xác minh OTP | No |

### User API

| Method | Endpoint | Purpose | Auth required |
|---|---|---|---|
| GET | `/api/users/me` | Lấy thông tin user hiện tại | Yes |
| GET | `/api/users/{id}` | Lấy thông tin user theo ID | Yes |
| PUT | `/api/users/me` | Cập nhật thông tin user hiện tại | Yes |

### Product API

| Method | Endpoint | Purpose | Auth required |
|---|---|---|---|
| GET | `/api/products` | Lấy danh sách sản phẩm | No |
| GET | `/api/products/{id}` | Lấy chi tiết sản phẩm | No |

### Cart API

| Method | Endpoint | Purpose | Auth required |
|---|---|---|---|
| POST | `/api/cart/add` | Thêm sản phẩm vào giỏ hàng | Yes |
| GET | `/api/cart` | Lấy giỏ hàng hiện tại | Yes |

### Order API

| Method | Endpoint | Purpose | Auth required |
|---|---|---|---|
| POST | `/api/orders` | Tạo đơn hàng | Yes |

### Admin API

| Method | Endpoint | Purpose | Auth required |
|---|---|---|---|
| POST | `/api/admin/login` | Đăng nhập admin | No |
| GET | `/api/admin/dashboard` | Lấy dữ liệu dashboard admin | Admin |
| GET | `/api/admin/users` | Lấy danh sách user | Admin |
| GET | `/api/admin/orders` | Lấy danh sách đơn hàng | Admin |

---

# 6. Auth API

## 6.1. POST /api/auth/register

### Purpose

Tạo tài khoản người dùng mới.

### Auth required

No.

### Middleware

```txt
RequestValidationMiddleware
```

### Request body

```json
{
  "name": "Nguyen Van A",
  "email": "user@example.com",
  "phone": "0900000000",
  "password": "123456"
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| name | Yes | String, 2–100 characters |
| email | No | Valid email format, unique if provided |
| phone | Yes | Valid phone format, unique |
| password | Yes | Minimum 6 characters |

### Success response

```json
{
  "success": true,
  "message": "REGISTER_SUCCESS",
  "data": {
    "user_id": 1
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "phone": "Phone is required",
    "password": "Password must be at least 6 characters"
  }
}
```

```json
{
  "success": false,
  "error_code": "PHONE_ALREADY_EXISTS",
  "message": "Phone number already exists"
}
```

```json
{
  "success": false,
  "error_code": "EMAIL_ALREADY_EXISTS",
  "message": "Email already exists"
}
```

### Status codes

```txt
201 Created
400 Bad Request
409 Conflict
500 Internal Server Error
```

### Internal handling

```txt
Service: AuthService, ValidationService
Repository: UserRepository
Risk rule: None
```

---

## 6.2. POST /api/auth/login

### Purpose

Kiểm tra phone/email + password. Nếu hợp lệ, tạo `login_challenge_id`.

API này không tạo token và chưa được xem là đăng nhập thành công.

### Auth required

No.

### Middleware

```txt
RequestValidationMiddleware
```

### Request body

Dùng phone:

```json
{
  "phone": "0900000000",
  "password": "123456"
}
```

Hoặc dùng email:

```json
{
  "email": "user@example.com",
  "password": "123456"
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| phone/email | Yes | Cần có phone hoặc email |
| password | Yes | Không được rỗng |

### Success response

```json
{
  "success": true,
  "message": "PASSWORD_VERIFIED",
  "data": {
    "login_challenge_id": "challenge_abc123",
    "status": "PENDING_OTP"
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "password": "Password is required"
  }
}
```

```json
{
  "success": false,
  "error_code": "INVALID_CREDENTIALS",
  "message": "Phone/email or password is incorrect"
}
```

```json
{
  "success": false,
  "error_code": "RISK_LEVEL_HIGH",
  "message": "Login temporarily blocked due to high risk"
}
```

### Status codes

```txt
200 OK
400 Bad Request
401 Unauthorized
429 Too Many Requests
500 Internal Server Error
```

### Internal handling

```txt
Service: AuthService, ValidationService, RuleEngineService, RiskService
Repository: UserRepository, LoginAttemptRepository, LoginChallengeRepository, RiskLogRepository
Risk rule: R3
```

---

## 6.3. POST /api/auth/complete-login

### Purpose

Tạo access token sau khi user đã verify OTP thành công.

### Auth required

No.

### Middleware

```txt
RequestValidationMiddleware
```

### Request body

```json
{
  "login_challenge_id": "challenge_abc123"
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| login_challenge_id | Yes | Phải tồn tại và chưa hết hạn |

### Success response

```json
{
  "success": true,
  "message": "LOGIN_SUCCESS",
  "data": {
    "access_token": "plain_access_token_return_once",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "name": "Nguyen Van A",
      "email": "user@example.com",
      "phone": "0900000000",
      "role": "customer"
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "OTP_REQUIRED",
  "message": "OTP verification is required before completing login"
}
```

```json
{
  "success": false,
  "error_code": "LOGIN_CHALLENGE_EXPIRED",
  "message": "Login challenge has expired"
}
```

```json
{
  "success": false,
  "error_code": "LOGIN_CHALLENGE_BLOCKED",
  "message": "Login challenge is blocked"
}
```

### Status codes

```txt
200 OK
400 Bad Request
403 Forbidden
404 Not Found
429 Too Many Requests
500 Internal Server Error
```

### Internal handling

```txt
Service: AuthService, TokenService
Repository: LoginChallengeRepository, TokenRepository, UserRepository
Risk rule: None
```

---

## 6.4. POST /api/auth/logout

### Purpose

Thu hồi access token hiện tại.

### Auth required

Yes.

### Middleware

```txt
AuthMiddleware
```

### Header

```http
Authorization: Bearer <access_token>
```

### Request body

```json
{}
```

### Success response

```json
{
  "success": true,
  "message": "LOGOUT_SUCCESS",
  "data": {}
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "TOKEN_MISSING",
  "message": "Authorization token is required"
}
```

```json
{
  "success": false,
  "error_code": "TOKEN_INVALID",
  "message": "Invalid token"
}
```

```json
{
  "success": false,
  "error_code": "TOKEN_EXPIRED",
  "message": "Token has expired"
}
```

### Status codes

```txt
200 OK
401 Unauthorized
500 Internal Server Error
```

### Internal handling

```txt
Service: TokenService
Repository: TokenRepository
Risk rule: None
```

---

# 7. OTP API

## 7.1. POST /api/otp/request

### Purpose

Sinh OTP và gửi OTP cho user dựa trên `login_challenge_id`.

### Auth required

No.

### Middleware

```txt
RequestValidationMiddleware
```

### Request body

```json
{
  "login_challenge_id": "challenge_abc123"
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| login_challenge_id | Yes | Phải tồn tại, chưa hết hạn, chưa bị block |

### Success response

```json
{
  "success": true,
  "message": "OTP_SENT",
  "data": {
    "login_challenge_id": "challenge_abc123",
    "status": "OTP_SENT",
    "expires_in": 300
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "LOGIN_CHALLENGE_EXPIRED",
  "message": "Login challenge has expired"
}
```

```json
{
  "success": false,
  "error_code": "LOGIN_CHALLENGE_BLOCKED",
  "message": "Login challenge is blocked"
}
```

```json
{
  "success": false,
  "error_code": "OTP_RESEND_LIMIT_EXCEEDED",
  "message": "OTP request limit exceeded"
}
```

```json
{
  "success": false,
  "error_code": "RISK_LEVEL_MEDIUM",
  "message": "Additional verification is required before requesting OTP"
}
```

```json
{
  "success": false,
  "error_code": "RISK_LEVEL_HIGH",
  "message": "OTP request is temporarily blocked due to high risk"
}
```

### Status codes

```txt
200 OK
400 Bad Request
403 Forbidden
404 Not Found
429 Too Many Requests
500 Internal Server Error
```

### Internal handling

```txt
Service: OtpService, SmsService, ValidationService, RuleEngineService, RiskService
Repository: LoginChallengeRepository, OtpRepository, RiskLogRepository
Risk rule: R2
```

### Notes

```txt
SmsService chỉ gửi OTP hoặc mock SMS.
SmsService không verify OTP.
OTP phải được hash trước khi lưu database.
```

---

## 7.2. POST /api/otp/verify

### Purpose

Xác minh OTP theo `login_challenge_id`.

### Auth required

No.

### Middleware

```txt
RequestValidationMiddleware
```

### Request body

```json
{
  "login_challenge_id": "challenge_abc123",
  "otp_code": "123456"
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| login_challenge_id | Yes | Phải tồn tại, chưa hết hạn, chưa bị block |
| otp_code | Yes | 6 digits |

### Success response

```json
{
  "success": true,
  "message": "OTP_VERIFIED",
  "data": {
    "login_challenge_id": "challenge_abc123",
    "status": "OTP_VERIFIED"
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "OTP_NOT_FOUND",
  "message": "OTP not found"
}
```

```json
{
  "success": false,
  "error_code": "OTP_INVALID",
  "message": "OTP is invalid"
}
```

```json
{
  "success": false,
  "error_code": "OTP_EXPIRED",
  "message": "OTP has expired"
}
```

```json
{
  "success": false,
  "error_code": "OTP_USED",
  "message": "OTP has already been used"
}
```

```json
{
  "success": false,
  "error_code": "OTP_TOO_MANY_ATTEMPTS",
  "message": "Too many invalid OTP attempts"
}
```

```json
{
  "success": false,
  "error_code": "LOGIN_CHALLENGE_BLOCKED",
  "message": "Login challenge is blocked"
}
```

### Status codes

```txt
200 OK
400 Bad Request
404 Not Found
429 Too Many Requests
500 Internal Server Error
```

### Internal handling

```txt
Service: OtpService, ValidationService, RuleEngineService, RiskService
Repository: LoginChallengeRepository, OtpRepository, RiskLogRepository
Risk rule: R1
```

---

# 8. User API

## 8.1. GET /api/users/me

### Purpose

Lấy thông tin user hiện tại dựa trên access token.

### Auth required

Yes.

### Middleware

```txt
AuthMiddleware
```

### Header

```http
Authorization: Bearer <access_token>
```

### Success response

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "user": {
      "id": 1,
      "name": "Nguyen Van A",
      "email": "user@example.com",
      "phone": "0900000000",
      "role": "customer"
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "TOKEN_MISSING",
  "message": "Authorization token is required"
}
```

```json
{
  "success": false,
  "error_code": "TOKEN_INVALID",
  "message": "Invalid token"
}
```

```json
{
  "success": false,
  "error_code": "TOKEN_EXPIRED",
  "message": "Token has expired"
}
```

### Status codes

```txt
200 OK
401 Unauthorized
500 Internal Server Error
```

### Internal handling

```txt
Service: UserService, TokenService
Repository: UserRepository, TokenRepository
Risk rule: None
```

---

## 8.2. GET /api/users/{id}

### Purpose

Lấy thông tin user theo ID.

User thường chỉ được xem chính mình. Admin được xem user khác.

### Auth required

Yes.

### Middleware

```txt
AuthMiddleware
```

### Header

```http
Authorization: Bearer <access_token>
```

### Path parameter

| Parameter | Required | Rule |
|---|---|---|
| id | Yes | Numeric user ID |

### Success response

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "user": {
      "id": 1,
      "name": "Nguyen Van A",
      "email": "user@example.com",
      "phone": "0900000000",
      "role": "customer"
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "TOKEN_MISSING",
  "message": "Authorization token is required"
}
```

```json
{
  "success": false,
  "error_code": "USER_NOT_FOUND",
  "message": "User not found"
}
```

```json
{
  "success": false,
  "error_code": "IDOR_BLOCKED",
  "message": "You are not allowed to access another user's data"
}
```

### Status codes

```txt
200 OK
401 Unauthorized
403 Forbidden
404 Not Found
500 Internal Server Error
```

### Internal handling

```txt
Service: UserService, TokenService
Repository: UserRepository, TokenRepository
Risk rule: IDOR / Access Control Violation
```

---

## 8.3. PUT /api/users/me

### Purpose

Cập nhật thông tin user hiện tại.

### Auth required

Yes.

### Middleware

```txt
AuthMiddleware
RequestValidationMiddleware
```

### Header

```http
Authorization: Bearer <access_token>
```

### Request body

```json
{
  "name": "Nguyen Van A Updated",
  "email": "updated@example.com"
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| name | No | String, 2–100 characters |
| email | No | Valid email format, unique |
| phone | No | Không cho đổi trực tiếp nếu chưa có OTP riêng |

### Success response

```json
{
  "success": true,
  "message": "USER_UPDATED",
  "data": {
    "user": {
      "id": 1,
      "name": "Nguyen Van A Updated",
      "email": "updated@example.com",
      "phone": "0900000000",
      "role": "customer"
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "email": "Email format is invalid"
  }
}
```

```json
{
  "success": false,
  "error_code": "EMAIL_ALREADY_EXISTS",
  "message": "Email already exists"
}
```

### Status codes

```txt
200 OK
400 Bad Request
401 Unauthorized
409 Conflict
500 Internal Server Error
```

### Internal handling

```txt
Service: UserService, ValidationService
Repository: UserRepository, TokenRepository
Risk rule: None
```

---

# 9. Product API

## 9.1. GET /api/products

### Purpose

Lấy danh sách sản phẩm.

### Auth required

No.

### Middleware

```txt
None
```

### Query parameters

| Parameter | Required | Rule |
|---|---|---|
| page | No | Numeric, default 1 |
| limit | No | Numeric, default 10 |
| keyword | No | String |
| category | No | String or numeric category ID |

### Example request

```txt
GET /api/products?page=1&limit=10&keyword=shoes
```

### Success response

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "products": [
      {
        "id": 1,
        "name": "Product A",
        "price": 150000,
        "image": "product-a.jpg",
        "stock": 20
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 10,
      "total": 1
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "page": "Page must be numeric"
  }
}
```

### Status codes

```txt
200 OK
400 Bad Request
500 Internal Server Error
```

### Internal handling

```txt
Service: ProductService, ValidationService
Repository: ProductRepository
Risk rule: None
```

---

## 9.2. GET /api/products/{id}

### Purpose

Lấy chi tiết sản phẩm.

### Auth required

No.

### Middleware

```txt
None
```

### Path parameter

| Parameter | Required | Rule |
|---|---|---|
| id | Yes | Numeric product ID |

### Success response

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "product": {
      "id": 1,
      "name": "Product A",
      "description": "Product description",
      "price": 150000,
      "image": "product-a.jpg",
      "stock": 20
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "PRODUCT_NOT_FOUND",
  "message": "Product not found"
}
```

### Status codes

```txt
200 OK
404 Not Found
500 Internal Server Error
```

### Internal handling

```txt
Service: ProductService
Repository: ProductRepository
Risk rule: None
```

---

# 10. Cart API

## 10.1. POST /api/cart/add

### Purpose

Thêm sản phẩm vào giỏ hàng của user hiện tại.

### Auth required

Yes.

### Middleware

```txt
AuthMiddleware
RequestValidationMiddleware
```

### Header

```http
Authorization: Bearer <access_token>
```

### Request body

```json
{
  "product_id": 1,
  "quantity": 2
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| product_id | Yes | Numeric, product phải tồn tại |
| quantity | Yes | Numeric, minimum 1 |

### Success response

```json
{
  "success": true,
  "message": "CART_ITEM_ADDED",
  "data": {
    "cart_item": {
      "product_id": 1,
      "quantity": 2
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "TOKEN_MISSING",
  "message": "Authorization token is required"
}
```

```json
{
  "success": false,
  "error_code": "PRODUCT_NOT_FOUND",
  "message": "Product not found"
}
```

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "quantity": "Quantity must be at least 1"
  }
}
```

### Status codes

```txt
200 OK
400 Bad Request
401 Unauthorized
404 Not Found
500 Internal Server Error
```

### Internal handling

```txt
Service: CartService, ProductService, ValidationService
Repository: CartRepository, ProductRepository, TokenRepository
Risk rule: None
```

---

## 10.2. GET /api/cart

### Purpose

Lấy giỏ hàng của user hiện tại.

### Auth required

Yes.

### Middleware

```txt
AuthMiddleware
```

### Header

```http
Authorization: Bearer <access_token>
```

### Success response

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "cart": {
      "items": [
        {
          "product_id": 1,
          "name": "Product A",
          "price": 150000,
          "quantity": 2,
          "subtotal": 300000
        }
      ],
      "total": 300000
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "TOKEN_MISSING",
  "message": "Authorization token is required"
}
```

```json
{
  "success": false,
  "error_code": "TOKEN_INVALID",
  "message": "Invalid token"
}
```

### Status codes

```txt
200 OK
401 Unauthorized
500 Internal Server Error
```

### Internal handling

```txt
Service: CartService, TokenService
Repository: CartRepository, TokenRepository
Risk rule: None
```

---

# 11. Order API

## 11.1. POST /api/orders

### Purpose

Tạo đơn hàng từ giỏ hàng của user hiện tại.

### Auth required

Yes.

### Middleware

```txt
AuthMiddleware
RequestValidationMiddleware
```

### Header

```http
Authorization: Bearer <access_token>
```

### Request body

```json
{
  "shipping_name": "Nguyen Van A",
  "shipping_phone": "0900000000",
  "shipping_address": "123 Nguyen Trai, HCMC",
  "payment_method": "COD"
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| shipping_name | Yes | String |
| shipping_phone | Yes | Valid phone format |
| shipping_address | Yes | String |
| payment_method | Yes | COD, BANK_TRANSFER, E_WALLET |

### Success response

```json
{
  "success": true,
  "message": "ORDER_CREATED",
  "data": {
    "order": {
      "id": 1001,
      "status": "PENDING",
      "total": 300000
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "shipping_address": "Shipping address is required"
  }
}
```

```json
{
  "success": false,
  "error_code": "CART_EMPTY",
  "message": "Cart is empty"
}
```

### Status codes

```txt
201 Created
400 Bad Request
401 Unauthorized
500 Internal Server Error
```

### Internal handling

```txt
Service: OrderService, CartService, ValidationService
Repository: OrderRepository, CartRepository, ProductRepository, TokenRepository
Risk rule: None
```

---

# 12. Admin API

## 12.1. POST /api/admin/login

### Purpose

Đăng nhập admin và tạo admin access token.

### Auth required

No.

### Middleware

```txt
RequestValidationMiddleware
```

### Request body

```json
{
  "email": "admin@example.com",
  "password": "admin123"
}
```

### Validation rules

| Field | Required | Rule |
|---|---|---|
| email | Yes | Valid email format |
| password | Yes | Không được rỗng |

### Success response

```json
{
  "success": true,
  "message": "ADMIN_LOGIN_SUCCESS",
  "data": {
    "access_token": "plain_admin_access_token_return_once",
    "token_type": "Bearer",
    "expires_in": 3600,
    "admin": {
      "id": 1,
      "name": "Admin",
      "email": "admin@example.com",
      "role": "admin"
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "INVALID_CREDENTIALS",
  "message": "Email or password is incorrect"
}
```

```json
{
  "success": false,
  "error_code": "ACCESS_DENIED",
  "message": "Admin permission is required"
}
```

### Status codes

```txt
200 OK
400 Bad Request
401 Unauthorized
403 Forbidden
500 Internal Server Error
```

### Internal handling

```txt
Service: AdminService, TokenService, ValidationService
Repository: UserRepository, TokenRepository
Risk rule: R3 if applied to admin login
```

### Notes

Admin có thể dùng chung bảng `users` với `role = admin`.

---

## 12.2. GET /api/admin/dashboard

### Purpose

Lấy dữ liệu tổng quan cho dashboard admin.

### Auth required

Admin.

### Middleware

```txt
AuthMiddleware
AdminMiddleware
```

### Header

```http
Authorization: Bearer <admin_access_token>
```

### Success response

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "dashboard": {
      "total_users": 120,
      "total_products": 50,
      "total_orders": 35,
      "total_revenue": 15000000,
      "risk_events_today": 8
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "TOKEN_MISSING",
  "message": "Authorization token is required"
}
```

```json
{
  "success": false,
  "error_code": "ACCESS_DENIED",
  "message": "Admin permission is required"
}
```

### Status codes

```txt
200 OK
401 Unauthorized
403 Forbidden
500 Internal Server Error
```

### Internal handling

```txt
Service: AdminService, TokenService
Repository: UserRepository, ProductRepository, OrderRepository, RiskLogRepository, TokenRepository
Risk rule: None
```

---

## 12.3. GET /api/admin/users

### Purpose

Lấy danh sách user cho admin.

### Auth required

Admin.

### Middleware

```txt
AuthMiddleware
AdminMiddleware
```

### Header

```http
Authorization: Bearer <admin_access_token>
```

### Query parameters

| Parameter | Required | Rule |
|---|---|---|
| page | No | Numeric, default 1 |
| limit | No | Numeric, default 10 |
| keyword | No | Search by name, email, phone |
| role | No | customer, admin |

### Example request

```txt
GET /api/admin/users?page=1&limit=10&keyword=nguyen
```

### Success response

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "users": [
      {
        "id": 1,
        "name": "Nguyen Van A",
        "email": "user@example.com",
        "phone": "0900000000",
        "role": "customer",
        "is_active": 1,
        "created_at": "2026-05-10 10:00:00"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 10,
      "total": 1
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "ACCESS_DENIED",
  "message": "Admin permission is required"
}
```

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "page": "Page must be numeric"
  }
}
```

### Status codes

```txt
200 OK
400 Bad Request
401 Unauthorized
403 Forbidden
500 Internal Server Error
```

### Internal handling

```txt
Service: AdminService, UserService, ValidationService
Repository: UserRepository, TokenRepository
Risk rule: None
```

---

## 12.4. GET /api/admin/orders

### Purpose

Lấy danh sách đơn hàng cho admin.

### Auth required

Admin.

### Middleware

```txt
AuthMiddleware
AdminMiddleware
```

### Header

```http
Authorization: Bearer <admin_access_token>
```

### Query parameters

| Parameter | Required | Rule |
|---|---|---|
| page | No | Numeric, default 1 |
| limit | No | Numeric, default 10 |
| status | No | PENDING, PAID, SHIPPING, COMPLETED, CANCELLED |
| keyword | No | Search by customer name, phone, order ID |

### Example request

```txt
GET /api/admin/orders?page=1&limit=10&status=PENDING
```

### Success response

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "orders": [
      {
        "id": 1001,
        "user_id": 1,
        "customer_name": "Nguyen Van A",
        "total": 300000,
        "status": "PENDING",
        "created_at": "2026-05-10 10:00:00"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 10,
      "total": 1
    }
  }
}
```

### Error responses

```json
{
  "success": false,
  "error_code": "ACCESS_DENIED",
  "message": "Admin permission is required"
}
```

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "status": "Invalid order status"
  }
}
```

### Status codes

```txt
200 OK
400 Bad Request
401 Unauthorized
403 Forbidden
500 Internal Server Error
```

### Internal handling

```txt
Service: AdminService, OrderService, ValidationService
Repository: OrderRepository, UserRepository, TokenRepository
Risk rule: None
```