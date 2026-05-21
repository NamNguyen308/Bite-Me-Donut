# API Security Test Cases

## 1. Purpose

File này định nghĩa các test case bảo mật chính cho API đăng nhập, OTP, token, phân quyền và request validation.

Các test case này sẽ được triển khai và chạy bằng Postman.

---

## 2. Test Case Template

```txt
Test case ID:
Name:
Endpoint:
Method:
Precondition:
Request:
Steps:
Expected status:
Expected response:
Risk rule expected:
Postman notes:
```

---

## 3. Environment Variables

Các biến nên tạo trong Postman environment:

```txt
base_url = http://ecommerce_security_platform.test/public

user_phone = 0900000000
user_email = user@example.com
user_password = 123456
wrong_password = wrong123

user_a_token =
user_b_token =
access_token =
admin_token =

login_challenge_id =
otp_code =
user_id =
other_user_id =
invalid_token = invalid_token_abc123
```

---

## 4. General Preconditions

Trước khi chạy test, cần có dữ liệu mẫu:

```txt
User A đã tồn tại.
User B đã tồn tại.
Admin đã tồn tại.
Có ít nhất một sản phẩm trong database.
Postman environment đã có base_url.
```

Một số test case cần reset trạng thái trước khi chạy:

```txt
login_challenges
otps
tokens
login_attempts
risk_logs
```

---

# 5. Test Cases

## TC01 - Login sai mật khẩu nhiều lần

### Test case ID

```txt
TC01
```

### Name

```txt
Login sai mật khẩu nhiều lần
```

### Endpoint

```http
POST /api/auth/login
```

### Method

```txt
POST
```

### Precondition

```txt
User đã tồn tại trong database.
User chưa bị block.
Risk log trước đó đã được reset nếu cần.
```

### Request

```json
{
  "phone": "{{user_phone}}",
  "password": "{{wrong_password}}"
}
```

### Steps

```txt
1. Gửi request login với sai password lần 1.
2. Gửi request login với sai password lần 2.
3. Gửi request login với sai password lần 3.
4. Quan sát response và risk log.
```

### Expected status

```txt
401 Unauthorized
```

Hoặc nếu hệ thống đã chuyển sang high risk:

```txt
429 Too Many Requests
```

### Expected response

```json
{
  "success": false,
  "error_code": "INVALID_CREDENTIALS",
  "message": "Phone/email or password is incorrect"
}
```

Nếu bị block do risk cao:

```json
{
  "success": false,
  "error_code": "RISK_LEVEL_HIGH",
  "message": "Login temporarily blocked due to high risk"
}
```

### Risk rule expected

```txt
R3 - Sai mật khẩu > 2 lần
```

### Postman notes

```txt
Có thể chạy cùng request nhiều lần bằng Runner.
Kiểm tra bảng login_attempts có status = FAILED.
Kiểm tra risk_logs có rule_code = R3 nếu vượt ngưỡng.
```

---

## TC02 - Login đúng password nhưng chưa verify OTP

### Test case ID

```txt
TC02
```

### Name

```txt
Login đúng password nhưng chưa verify OTP
```

### Endpoint

```http
POST /api/auth/login
```

### Method

```txt
POST
```

### Precondition

```txt
User đã tồn tại.
Password đúng.
User chưa bị block.
```

### Request

```json
{
  "phone": "{{user_phone}}",
  "password": "{{user_password}}"
}
```

### Steps

```txt
1. Gửi request login với phone và password đúng.
2. Kiểm tra response có login_challenge_id.
3. Kiểm tra response không có access_token.
4. Lưu login_challenge_id vào Postman environment.
```

### Expected status

```txt
200 OK
```

### Expected response

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

### Risk rule expected

```txt
None
```

### Postman notes

```txt
Dùng test script để lưu login_challenge_id.
API này không được trả access_token.
```

Example Postman test script:

```javascript
const json = pm.response.json();
pm.environment.set("login_challenge_id", json.data.login_challenge_id);
```

---

## TC03 - Request OTP quá nhiều lần

### Test case ID

```txt
TC03
```

### Name

```txt
Request OTP quá nhiều lần
```

### Endpoint

```http
POST /api/otp/request
```

### Method

```txt
POST
```

### Precondition

```txt
Đã chạy TC02 và có login_challenge_id hợp lệ.
Challenge chưa hết hạn.
Challenge chưa bị block.
```

### Request

```json
{
  "login_challenge_id": "{{login_challenge_id}}"
}
```

### Steps

```txt
1. Gửi request OTP lần 1.
2. Gửi request OTP lần 2.
3. Gửi request OTP lần 3.
4. Gửi request OTP lần 4.
5. Quan sát response khi vượt giới hạn.
```

### Expected status

```txt
429 Too Many Requests
```

### Expected response

```json
{
  "success": false,
  "error_code": "OTP_RESEND_LIMIT_EXCEEDED",
  "message": "OTP request limit exceeded"
}
```

Nếu risk cao:

```json
{
  "success": false,
  "error_code": "RISK_LEVEL_HIGH",
  "message": "OTP request is temporarily blocked due to high risk"
}
```

### Risk rule expected

```txt
R2 - Request OTP > 2 lần
```

### Postman notes

```txt
Kiểm tra login_challenges.otp_send_count tăng sau mỗi lần request.
Kiểm tra risk_logs có rule_code = R2 khi vượt ngưỡng.
```

---

## TC04 - Nhập sai OTP nhiều lần

### Test case ID

```txt
TC04
```

### Name

```txt
Nhập sai OTP nhiều lần
```

### Endpoint

```http
POST /api/otp/verify
```

### Method

```txt
POST
```

### Precondition

```txt
Đã request OTP thành công.
login_challenge_id còn hiệu lực.
OTP chưa bị used.
```

### Request

```json
{
  "login_challenge_id": "{{login_challenge_id}}",
  "otp_code": "000000"
}
```

### Steps

```txt
1. Gửi verify OTP với mã sai lần 1.
2. Gửi verify OTP với mã sai lần 2.
3. Gửi verify OTP với mã sai lần 3.
4. Quan sát response khi vượt giới hạn.
```

### Expected status

```txt
400 Bad Request
```

Hoặc nếu vượt giới hạn:

```txt
429 Too Many Requests
```

### Expected response

```json
{
  "success": false,
  "error_code": "OTP_INVALID",
  "message": "OTP is invalid"
}
```

Nếu sai quá nhiều:

```json
{
  "success": false,
  "error_code": "OTP_TOO_MANY_ATTEMPTS",
  "message": "Too many invalid OTP attempts"
}
```

### Risk rule expected

```txt
R1 - Nhập sai OTP > 1 lần
```

### Postman notes

```txt
Kiểm tra login_challenges.otp_wrong_count tăng sau mỗi lần sai.
Kiểm tra risk_logs có rule_code = R1 khi vượt ngưỡng.
```

---

## TC05 - Nhập OTP hết hạn

### Test case ID

```txt
TC05
```

### Name

```txt
Nhập OTP hết hạn
```

### Endpoint

```http
POST /api/otp/verify
```

### Method

```txt
POST
```

### Precondition

```txt
Đã request OTP thành công.
OTP đã hết hạn.
```

### Request

```json
{
  "login_challenge_id": "{{login_challenge_id}}",
  "otp_code": "{{otp_code}}"
}
```

### Steps

```txt
1. Request OTP thành công.
2. Chờ quá thời gian OTP_EXPIRE_MINUTES.
3. Hoặc chỉnh expires_at trong database thành thời điểm quá khứ.
4. Gửi request verify OTP.
```

### Expected status

```txt
400 Bad Request
```

### Expected response

```json
{
  "success": false,
  "error_code": "OTP_EXPIRED",
  "message": "OTP has expired"
}
```

### Risk rule expected

```txt
None
```

### Postman notes

```txt
Để test nhanh trên local, có thể tạm set OTP_EXPIRE_MINUTES=1.
Hoặc update otps.expires_at về thời gian trong quá khứ.
```

---

## TC06 - Nhập OTP đã dùng

### Test case ID

```txt
TC06
```

### Name

```txt
Nhập OTP đã dùng
```

### Endpoint

```http
POST /api/otp/verify
```

### Method

```txt
POST
```

### Precondition

```txt
Đã request OTP thành công.
Đã verify OTP đúng một lần.
OTP đã có is_used = 1.
```

### Request

```json
{
  "login_challenge_id": "{{login_challenge_id}}",
  "otp_code": "{{otp_code}}"
}
```

### Steps

```txt
1. Request OTP thành công.
2. Verify OTP đúng lần 1.
3. Gửi lại request verify cùng OTP lần 2.
```

### Expected status

```txt
400 Bad Request
```

### Expected response

```json
{
  "success": false,
  "error_code": "OTP_USED",
  "message": "OTP has already been used"
}
```

### Risk rule expected

```txt
None
```

### Postman notes

```txt
Kiểm tra otps.is_used = 1 sau lần verify đầu tiên.
Kiểm tra otps.used_at có giá trị.
```

---

## TC07 - Complete login khi chưa verify OTP

### Test case ID

```txt
TC07
```

### Name

```txt
Complete login khi chưa verify OTP
```

### Endpoint

```http
POST /api/auth/complete-login
```

### Method

```txt
POST
```

### Precondition

```txt
Đã login đúng password và có login_challenge_id.
Chưa verify OTP.
Challenge status vẫn là PENDING_OTP hoặc OTP_SENT.
```

### Request

```json
{
  "login_challenge_id": "{{login_challenge_id}}"
}
```

### Steps

```txt
1. Gửi POST /api/auth/login với password đúng.
2. Không gọi /api/otp/verify.
3. Gọi /api/auth/complete-login bằng login_challenge_id đó.
```

### Expected status

```txt
403 Forbidden
```

### Expected response

```json
{
  "success": false,
  "error_code": "OTP_REQUIRED",
  "message": "OTP verification is required before completing login"
}
```

### Risk rule expected

```txt
None
```

### Postman notes

```txt
Test này chứng minh hệ thống không cho bypass OTP.
```

---

## TC08 - Gọi API users/me không có token

### Test case ID

```txt
TC08
```

### Name

```txt
Gọi API users/me không có token
```

### Endpoint

```http
GET /api/users/me
```

### Method

```txt
GET
```

### Precondition

```txt
Không gửi Authorization header.
```

### Request

```txt
No Authorization header
```

### Steps

```txt
1. Tạo request GET /api/users/me.
2. Không thêm Authorization header.
3. Gửi request.
```

### Expected status

```txt
401 Unauthorized
```

### Expected response

```json
{
  "success": false,
  "error_code": "TOKEN_MISSING",
  "message": "Authorization token is required"
}
```

### Risk rule expected

```txt
None
```

### Postman notes

```txt
Đảm bảo tab Authorization trong Postman đang chọn No Auth.
```

---

## TC09 - Gọi API users/me bằng token sai

### Test case ID

```txt
TC09
```

### Name

```txt
Gọi API users/me bằng token sai
```

### Endpoint

```http
GET /api/users/me
```

### Method

```txt
GET
```

### Precondition

```txt
Có token giả hoặc token không tồn tại trong database.
```

### Request header

```http
Authorization: Bearer {{invalid_token}}
```

### Steps

```txt
1. Tạo request GET /api/users/me.
2. Gửi Authorization header với invalid token.
3. Gửi request.
```

### Expected status

```txt
401 Unauthorized
```

### Expected response

```json
{
  "success": false,
  "error_code": "TOKEN_INVALID",
  "message": "Invalid token"
}
```

### Risk rule expected

```txt
None
```

### Postman notes

```txt
invalid_token có thể đặt trong environment.
Ví dụ: invalid_token = invalid_token_abc123.
```

---

## TC10 - User A truy cập dữ liệu User B

### Test case ID

```txt
TC10
```

### Name

```txt
User A truy cập dữ liệu User B
```

### Endpoint

```http
GET /api/users/{id}
```

### Method

```txt
GET
```

### Precondition

```txt
User A đã login thành công và có access token.
User B tồn tại trong database.
other_user_id là ID của User B.
User A không có quyền admin.
```

### Request header

```http
Authorization: Bearer {{user_a_token}}
```

### Example request

```txt
GET /api/users/{{other_user_id}}
```

### Steps

```txt
1. Login thành công bằng User A.
2. Lưu token của User A vào user_a_token.
3. Lấy ID của User B và lưu vào other_user_id.
4. Gọi GET /api/users/{{other_user_id}} bằng token của User A.
```

### Expected status

```txt
403 Forbidden
```

### Expected response

```json
{
  "success": false,
  "error_code": "IDOR_BLOCKED",
  "message": "You are not allowed to access another user's data"
}
```

### Risk rule expected

```txt
IDOR / Access Control Violation
```

### Postman notes

```txt
Test này kiểm tra lỗi IDOR.
Admin có thể truy cập user khác, customer thì không.
```

---

## TC11 - Request thiếu field

### Test case ID

```txt
TC11
```

### Name

```txt
Request thiếu field
```

### Endpoint

```http
POST /api/auth/login
```

### Method

```txt
POST
```

### Precondition

```txt
Không cần login.
```

### Request

Thiếu field `password`:

```json
{
  "phone": "{{user_phone}}"
}
```

### Steps

```txt
1. Tạo request POST /api/auth/login.
2. Chỉ gửi phone, không gửi password.
3. Gửi request.
```

### Expected status

```txt
400 Bad Request
```

### Expected response

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

### Risk rule expected

```txt
None
```

### Postman notes

```txt
Có thể áp dụng test tương tự cho các API khác có required fields.
```

---

## TC12 - Request sai format

### Test case ID

```txt
TC12
```

### Name

```txt
Request sai format
```

### Endpoint

```http
POST /api/auth/login
```

### Method

```txt
POST
```

### Precondition

```txt
Không cần login.
```

### Request

Phone sai format:

```json
{
  "phone": "abc",
  "password": "{{user_password}}"
}
```

### Steps

```txt
1. Tạo request POST /api/auth/login.
2. Gửi phone sai format.
3. Gửi request.
```

### Expected status

```txt
400 Bad Request
```

### Expected response

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {
    "phone": "Phone format is invalid"
  }
}
```

### Risk rule expected

```txt
None
```

### Postman notes

```txt
Có thể test thêm email sai format, otp_code không đủ 6 số, quantity < 1.
```

---

## TC13 - User thường truy cập API admin

### Test case ID

```txt
TC13
```

### Name

```txt
User thường truy cập API admin
```

### Endpoint

```http
GET /api/admin/dashboard
```

### Method

```txt
GET
```

### Precondition

```txt
User thường đã login thành công.
Token thuộc về user có role = customer.
```

### Request header

```http
Authorization: Bearer {{access_token}}
```

### Steps

```txt
1. Login thành công bằng user thường.
2. Lưu access token vào access_token.
3. Gọi GET /api/admin/dashboard bằng token user thường.
```

### Expected status

```txt
403 Forbidden
```

### Expected response

```json
{
  "success": false,
  "error_code": "ACCESS_DENIED",
  "message": "Admin permission is required"
}
```

### Risk rule expected

```txt
None
```

### Postman notes

```txt
Test này kiểm tra AdminMiddleware.
Nếu token là admin_token thì request phải thành công.
```

---

# 6. Test Execution Order

Thứ tự chạy đề xuất trong Postman:

```txt
1. TC02 - Login đúng password để lấy login_challenge_id
2. TC03 - Request OTP quá nhiều lần
3. TC04 - Nhập sai OTP nhiều lần
4. Reset challenge hoặc tạo challenge mới
5. TC05 - OTP hết hạn
6. Reset challenge hoặc tạo challenge mới
7. TC06 - OTP đã dùng
8. TC07 - Complete login khi chưa verify OTP
9. Login + request OTP + verify OTP đúng + complete login để lấy access_token
10. TC08 - Missing token
11. TC09 - Invalid token
12. TC10 - IDOR
13. TC11 - Missing field
14. TC12 - Invalid format
15. TC13 - User thường truy cập admin
16. TC01 - Sai password nhiều lần
```

---

# 7. Postman Collection Folders

Nên chia collection thành các folder:

```txt
Auth API
OTP API
User API
Access Control Test
Token Security Test
Validation Test
Rule Engine Test
Admin API
```

---

# 8. Expected Database Checks

Sau khi test, kiểm tra các bảng:

```txt
login_attempts
login_challenges
otps
tokens
risk_logs
```

Mapping kiểm tra nhanh:

| Test case | Table cần kiểm tra |
|---|---|
| TC01 | login_attempts, risk_logs |
| TC02 | login_challenges |
| TC03 | login_challenges, risk_logs |
| TC04 | login_challenges, risk_logs |
| TC05 | otps |
| TC06 | otps |
| TC07 | login_challenges |
| TC08 | tokens |
| TC09 | tokens |
| TC10 | users |
| TC11 | Không bắt buộc |
| TC12 | Không bắt buộc |
| TC13 | users, tokens |