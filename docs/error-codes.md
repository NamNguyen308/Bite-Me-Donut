# Error Codes

## 1. Purpose

File này định nghĩa các `error_code` chuẩn dùng trong toàn bộ API.

Mọi API khi trả lỗi phải dùng format:

```json
{
  "success": false,
  "error_code": "VALIDATION_ERROR",
  "message": "Invalid request data",
  "errors": {}
}
```

---

## 2. General Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| VALIDATION_ERROR | 400 | Request thiếu field hoặc sai format |
| INTERNAL_SERVER_ERROR | 500 | Lỗi server |
| NOT_FOUND | 404 | Không tìm thấy tài nguyên |
| METHOD_NOT_ALLOWED | 405 | HTTP method không được hỗ trợ |

---

## 3. Auth Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| INVALID_CREDENTIALS | 401 | Phone/email hoặc password sai |
| PASSWORD_NOT_VERIFIED | 403 | Password chưa được xác minh |
| LOGIN_CHALLENGE_NOT_FOUND | 404 | Không tìm thấy login challenge |
| LOGIN_CHALLENGE_EXPIRED | 400 | Login challenge hết hạn |
| LOGIN_CHALLENGE_BLOCKED | 429 | Login challenge bị block |
| PHONE_ALREADY_EXISTS | 409 | Số điện thoại đã tồn tại |
| EMAIL_ALREADY_EXISTS | 409 | Email đã tồn tại |
| ACCOUNT_INACTIVE | 403 | Tài khoản bị vô hiệu hóa |

---

## 4. OTP Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| OTP_REQUIRED | 403 | Cần verify OTP trước |
| OTP_NOT_FOUND | 404 | Không tìm thấy OTP |
| OTP_INVALID | 400 | OTP sai |
| OTP_EXPIRED | 400 | OTP hết hạn |
| OTP_USED | 400 | OTP đã được sử dụng |
| OTP_TOO_MANY_ATTEMPTS | 429 | Nhập sai OTP quá nhiều |
| OTP_RESEND_LIMIT_EXCEEDED | 429 | Request OTP quá nhiều |

---

## 5. Token Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| TOKEN_MISSING | 401 | Thiếu token |
| TOKEN_INVALID | 401 | Token sai |
| TOKEN_EXPIRED | 401 | Token hết hạn |
| TOKEN_REVOKED | 401 | Token đã bị thu hồi |

---

## 6. Authorization Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| ACCESS_DENIED | 403 | Không có quyền |
| ADMIN_REQUIRED | 403 | Cần quyền admin |
| IDOR_BLOCKED | 403 | User A truy cập dữ liệu User B |

---

## 7. Risk Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| RISK_LEVEL_MEDIUM | 403 | Cần kiểm tra bổ sung |
| RISK_LEVEL_HIGH | 429 | Tạm khóa thao tác |

---

## 8. User Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| USER_NOT_FOUND | 404 | Không tìm thấy user |
| USER_UPDATE_FAILED | 500 | Cập nhật user thất bại |

---

## 9. Product Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| PRODUCT_NOT_FOUND | 404 | Không tìm thấy sản phẩm |
| PRODUCT_INACTIVE | 404 | Sản phẩm không khả dụng |
| PRODUCT_OUT_OF_STOCK | 400 | Sản phẩm hết hàng |

---

## 10. Cart Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| CART_NOT_FOUND | 404 | Không tìm thấy giỏ hàng |
| CART_EMPTY | 400 | Giỏ hàng rỗng |
| CART_ITEM_NOT_FOUND | 404 | Không tìm thấy sản phẩm trong giỏ |

---

## 11. Order Error Codes

| Error code | HTTP status | Meaning |
|---|---:|---|
| ORDER_NOT_FOUND | 404 | Không tìm thấy đơn hàng |
| ORDER_CREATE_FAILED | 500 | Tạo đơn hàng thất bại |
| ORDER_ACCESS_DENIED | 403 | Không có quyền xem đơn hàng này |

---

## 12. Standard Usage Notes

```txt
- Controller không tự tạo error format thủ công.
- Response lỗi phải đi qua Response class/helper.
- error_code phải dùng đúng danh sách trong file này.
- HTTP status phải khớp với error_code.
- message có thể viết ngắn gọn, dễ hiểu.
- errors chỉ dùng cho validation chi tiết theo field.
```

---

## 13. Example Responses

### Validation error

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

### Token missing

```json
{
  "success": false,
  "error_code": "TOKEN_MISSING",
  "message": "Authorization token is required"
}
```

### OTP required

```json
{
  "success": false,
  "error_code": "OTP_REQUIRED",
  "message": "OTP verification is required before completing login"
}
```

### IDOR blocked

```json
{
  "success": false,
  "error_code": "IDOR_BLOCKED",
  "message": "You are not allowed to access another user's data"
}
```