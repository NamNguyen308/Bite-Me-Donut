# Security Rules

## 1. Purpose

File này là nơi duy nhất định nghĩa Rule Engine, risk score, risk level và action tương ứng trong hệ thống.

Các file khác chỉ tham chiếu rule code như:

```txt
R1
R2
R3
C1
C2
C3
C4
```

Không lặp lại toàn bộ rule mapping ở các file khác để tránh sai lệch tài liệu.

Rule Engine được dùng trong luồng bảo mật đăng nhập:

```txt
Password verification
→ Login challenge
→ OTP request
→ OTP verification
→ Complete login
→ Access token
```

Mục tiêu:

```txt
- Phát hiện hành vi đăng nhập bất thường.
- Phát hiện request OTP quá nhiều lần.
- Phát hiện nhập sai OTP nhiều lần.
- Kết hợp nhiều dấu hiệu rủi ro để tăng risk score.
- Tạm khóa challenge nếu rủi ro cao.
```

---

## 2. Rule Engine Scope

Rule Engine hiện áp dụng cho các hành vi sau:

```txt
1. Sai mật khẩu nhiều lần.
2. Request OTP nhiều lần.
3. Nhập sai OTP nhiều lần.
4. Kết hợp nhiều hành vi rủi ro trong cùng một login challenge.
```

Rule Engine không xử lý:

```txt
- Tạo token.
- Verify password.
- Verify OTP.
- Gửi OTP qua provider.
- Gọi Twilio Verify.
- Trả response trực tiếp cho client.
```

Các việc đó thuộc trách nhiệm của service tương ứng.

---

## 3. Single Rules

| Rule | Condition | Source field | Score |
|---|---|---|---:|
| R1 | Nhập sai OTP > 1 lần | `login_challenges.otp_wrong_count` | +10 |
| R2 | Request OTP > 2 lần | `login_challenges.otp_send_count` | +30 |
| R3 | Sai mật khẩu > 2 lần | `login_attempts` count by `user_id` / `phone` / `email` / `ip_address` | +10 |

Giải thích:

```txt
R1:
Kích hoạt khi user nhập sai OTP nhiều hơn 1 lần trong cùng login challenge.

R2:
Kích hoạt khi user request/resend OTP nhiều hơn 2 lần trong cùng login challenge.

R3:
Kích hoạt khi hệ thống ghi nhận sai password nhiều hơn 2 lần theo user, phone, email hoặc IP.
```

Ví dụ:

```txt
otp_wrong_count = 1 → chưa kích hoạt R1
otp_wrong_count = 2 → kích hoạt R1

otp_send_count = 2 → chưa kích hoạt R2
otp_send_count = 3 → kích hoạt R2

failed password attempts = 2 → chưa kích hoạt R3
failed password attempts = 3 → kích hoạt R3
```

---

## 4. Combination Rules

Combination rule được dùng để tăng risk score khi nhiều single rule cùng xuất hiện trong cùng một login challenge.

| Rule | Condition | Score |
|---|---|---:|
| C1 | R1 + R2 | +30 |
| C2 | R1 + R3 | +10 |
| C3 | R2 + R3 | +10 |
| C4 | R1 + R2 + R3 | +50 |

Giải thích:

```txt
C1:
User vừa request OTP nhiều lần vừa nhập sai OTP nhiều lần.

C2:
User vừa sai password nhiều lần vừa nhập sai OTP nhiều lần.

C3:
User vừa sai password nhiều lần vừa request OTP nhiều lần.

C4:
User kích hoạt đồng thời R1, R2 và R3.
```

---

## 5. Risk Score Formula

Công thức tính điểm rủi ro:

```txt
Risk Score = Tổng điểm Single Rules + Tổng điểm Combination Rules
```

Ví dụ 1:

```txt
User kích hoạt R1:

R1 = 10

Risk Score = 10
Risk Level = LOW
```

Ví dụ 2:

```txt
User kích hoạt R2:

R2 = 30

Risk Score = 30
Risk Level = MEDIUM
```

Ví dụ 3:

```txt
User kích hoạt R1 và R2:

R1 = 10
R2 = 30
C1 = 30

Risk Score = 10 + 30 + 30 = 70
Risk Level = HIGH
```

Ví dụ 4:

```txt
User kích hoạt R1, R2 và R3:

R1 = 10
R2 = 30
R3 = 10
C1 = 30
C2 = 10
C3 = 10
C4 = 50

Risk Score = 10 + 30 + 10 + 30 + 10 + 10 + 50 = 150
Risk Level = HIGH
```

---

## 6. Risk Level

| Level | Score range | Meaning |
|---|---:|---|
| LOW | 0–19 | Hành vi bình thường hoặc rủi ro thấp |
| MEDIUM | 20–50 | Có dấu hiệu bất thường, cần ghi nhận/cảnh báo |
| HIGH | >= 51 | Rủi ro cao, cần tạm khóa thao tác |

Ghi chú:

```txt
Trong project hiện tại, MEDIUM được dùng để ghi nhận trạng thái rủi ro và trả risk_level cho client.
CAPTCHA chưa được triển khai thực tế.
HIGH là mức kích hoạt block challenge.
```

---

## 7. Risk Action

| Risk level | System action |
|---|---|
| LOW | Cho phép user tiếp tục thao tác bình thường |
| MEDIUM | Cho phép tiếp tục nhưng ghi nhận risk_score/risk_level để cảnh báo hoặc mở rộng CAPTCHA sau này |
| HIGH | Chuyển `login_challenges.status` sang `BLOCKED`, set `blocked_until` nếu có cấu hình timeout |

Chi tiết action:

```txt
LOW:
- Request tiếp tục bình thường.
- Không block challenge.

MEDIUM:
- Request có thể tiếp tục.
- risk_score và risk_level được cập nhật vào login_challenges.
- Có thể dùng để hiển thị cảnh báo hoặc bổ sung CAPTCHA trong phiên bản sau.

HIGH:
- Challenge bị chặn.
- login_challenges.status = BLOCKED.
- Nếu có BLOCK_DURATION_MINUTES, set blocked_until.
- API trả RISK_LEVEL_HIGH hoặc LOGIN_CHALLENGE_BLOCKED.
```

---

## 8. Rule Trigger Source

| Rule | Trigger source | Update target |
|---|---|---|
| R1 | `POST /api/otp/verify` khi OTP sai | Tăng `login_challenges.otp_wrong_count` |
| R2 | `POST /api/otp/request` khi request/resend OTP | Tăng `login_challenges.otp_send_count` |
| R3 | `POST /api/auth/login` khi password sai | Ghi vào `login_attempts` |

Chi tiết theo endpoint:

```txt
POST /api/auth/login:
- Sai password → ghi login_attempts.
- Nếu số lần sai vượt ngưỡng → kích hoạt R3.

POST /api/otp/request:
- Mỗi lần request OTP → tăng otp_send_count.
- Nếu otp_send_count > 2 → kích hoạt R2.

POST /api/otp/verify:
- OTP sai → tăng otp_wrong_count.
- Nếu otp_wrong_count > 1 → kích hoạt R1.
```

---

## 9. OTP Provider and Rule Engine

Hệ thống hiện hỗ trợ:

```txt
SMS_PROVIDER=mock
SMS_PROVIDER=twilio_verify
```

Với `mock`:

```txt
- Backend tự sinh OTP.
- OTP được hash vào bảng otps.
- User nhập OTP từ mock_otp_code.
- OtpService verify bằng password_verify().
- Nếu sai OTP, kích hoạt luồng R1.
```

Với `twilio_verify`:

```txt
- Twilio Verify tự sinh OTP.
- Channel hiện tại: call.
- User nhận OTP qua cuộc gọi.
- OtpService gọi SmsService::verifyTwilioOtp().
- SmsService gọi Twilio VerificationCheck.
- Nếu Twilio trả valid=false hoặc status không approved, hệ thống xem là OTP sai và kích hoạt luồng R1.
```

Provider failure không được tính là OTP sai:

```txt
Nếu lỗi do config, cURL, Twilio API hoặc provider không phản hồi:
- Không tăng otp_wrong_count.
- Không kích hoạt R1.
- API trả OTP_PROVIDER_VERIFY_FAILED hoặc SMS_SEND_FAILED.
```

Ví dụ provider failure:

```txt
TWILIO_VERIFY_CONFIG_MISSING
TWILIO_VERIFY_CURL_ERROR
TWILIO_VERIFY_INVALID_RESPONSE
TWILIO_VERIFY_EXCEPTION
INVALID_PHONE_NUMBER
```

---

## 10. Rule Storage

Khi một rule được kích hoạt, hệ thống ghi vào bảng:

```txt
risk_logs
```

Required fields:

```txt
user_id
challenge_id
rule_code
description
score
ip_address
user_agent
created_at
```

Ví dụ risk log cho R1:

```txt
rule_code = R1
description = Invalid OTP attempts exceeded threshold
score = 10
```

Ví dụ risk log cho R2:

```txt
rule_code = R2
description = OTP request limit warning
score = 30
```

Ví dụ risk log cho C1:

```txt
rule_code = C1
description = Invalid OTP attempts combined with OTP resend abuse
score = 30
```

---

## 11. Duplicate Rule Prevention

Mỗi rule chỉ được cộng một lần trong cùng một `login_challenge_id`.

Database nên có unique constraint:

```txt
UNIQUE(challenge_id, rule_code)
```

Mục đích:

```txt
Tránh cộng điểm R1 nhiều lần trong cùng một challenge.
Tránh cộng điểm R2 nhiều lần trong cùng một challenge.
Tránh cộng điểm C1/C2/C3/C4 nhiều lần trong cùng một challenge.
```

Ví dụ:

```txt
User nhập sai OTP lần 2:
- R1 được ghi.
- risk_score cộng +10.

User nhập sai OTP lần 3:
- R1 đã tồn tại trong risk_logs.
- Không cộng +10 lần nữa.
- otp_wrong_count vẫn tăng để phục vụ giới hạn OTP_MAX_WRONG_ATTEMPTS.
```

---

## 12. Risk Score Update

Sau khi Rule Engine xác định rule được kích hoạt:

```txt
RuleEngineService
 ↓
RiskService
 ↓
RiskLogRepository
 ↓
risk_logs
 ↓
login_challenges.risk_score
login_challenges.risk_level
login_challenges.status nếu HIGH
```

RiskService chịu trách nhiệm:

```txt
1. Ghi rule vào risk_logs.
2. Tính lại tổng risk_score theo challenge_id.
3. Xác định risk_level.
4. Cập nhật login_challenges.risk_score.
5. Cập nhật login_challenges.risk_level.
6. Nếu risk_level = HIGH, cập nhật status = BLOCKED.
```

---

## 13. Challenge Status Interaction

Rule Engine có thể ảnh hưởng đến `login_challenges.status`.

Các status liên quan:

```txt
PENDING_OTP
OTP_SENT
OTP_VERIFIED
AUTHENTICATED
EXPIRED
BLOCKED
```

Tương tác với risk:

```txt
RISK LOW:
- Không đổi status.

RISK MEDIUM:
- Không bắt buộc đổi status.
- Giữ nguyên PENDING_OTP hoặc OTP_SENT.
- Cập nhật risk_score/risk_level.

RISK HIGH:
- Đổi status sang BLOCKED.
```

Tương tác với số lần sai OTP:

```txt
Nếu OTP sai nhưng risk chưa HIGH:
- Tăng otp_wrong_count.
- Nếu otp_wrong_count >= OTP_MAX_WRONG_ATTEMPTS, challenge có thể chuyển sang EXPIRED.

Nếu OTP sai và risk HIGH:
- Challenge chuyển sang BLOCKED.
```

---

## 14. Error Codes Related to Risk

Các error code liên quan:

| Error code | HTTP status | Meaning |
|---|---:|---|
| RISK_LEVEL_MEDIUM | 403 | Có rủi ro trung bình, dùng cho cảnh báo hoặc xác minh bổ sung nếu triển khai |
| RISK_LEVEL_HIGH | 429 | Thao tác bị chặn do rủi ro cao |
| LOGIN_CHALLENGE_BLOCKED | 429 | Login challenge đang bị block |
| OTP_TOO_MANY_ATTEMPTS | 429 | Nhập sai OTP quá nhiều |
| OTP_RESEND_LIMIT_EXCEEDED | 429 | Request OTP quá nhiều |
| INVALID_CREDENTIALS | 401 | Sai phone/email hoặc password |
| OTP_INVALID | 400 | OTP sai |

Ghi chú:

```txt
Trong implementation hiện tại, MEDIUM chủ yếu được dùng để ghi nhận và trả risk_level.
HIGH mới là mức chặn thao tác.
```

---

## 15. Rule Examples by Scenario

### Scenario 1: User nhập sai OTP một lần

```txt
otp_wrong_count = 1
No rule triggered
Risk Score = 0
Risk Level = LOW
Action = Allow retry
```

### Scenario 2: User nhập sai OTP hai lần

```txt
otp_wrong_count = 2
Triggered: R1
Risk Score = 10
Risk Level = LOW
Action = Allow retry nếu chưa vượt OTP_MAX_WRONG_ATTEMPTS
```

### Scenario 3: User request OTP lần thứ ba

```txt
otp_send_count = 3
Triggered: R2
Risk Score = 30
Risk Level = MEDIUM
Action = Allow tiếp tục nhưng ghi nhận risk
```

### Scenario 4: User request OTP nhiều lần rồi nhập sai OTP

```txt
Triggered: R1 + R2 + C1

R1 = 10
R2 = 30
C1 = 30

Risk Score = 70
Risk Level = HIGH
Action = BLOCKED
```

### Scenario 5: User sai password nhiều lần rồi login đúng và request OTP nhiều lần

```txt
Triggered: R3 + R2 + C3

R3 = 10
R2 = 30
C3 = 10

Risk Score = 50
Risk Level = MEDIUM
Action = Allow tiếp tục nhưng ghi nhận risk
```

### Scenario 6: User sai password nhiều lần, request OTP nhiều lần, rồi nhập sai OTP

```txt
Triggered: R1 + R2 + R3 + C1 + C2 + C3 + C4

Risk Score = 150
Risk Level = HIGH
Action = BLOCKED
```

---

## 16. Config Values

Các giá trị cấu hình đề xuất trong `.env`:

```env
LOGIN_MAX_FAILED_ATTEMPTS=3

OTP_MAX_RESEND=3
OTP_MAX_WRONG_ATTEMPTS=3

RISK_MEDIUM_MIN=20
RISK_HIGH_MIN=51

BLOCK_DURATION_MINUTES=10
```

Các giá trị đang dùng trong project:

```env
LOGIN_CHALLENGE_EXPIRE_MINUTES=10
LOGIN_MAX_FAILED_ATTEMPTS=3

OTP_EXPIRE_MINUTES=5
OTP_MAX_WRONG_ATTEMPTS=3
OTP_MAX_RESEND=3

TOKEN_EXPIRE_MINUTES=60
```

Ghi chú:

```txt
RISK_MEDIUM_MIN và RISK_HIGH_MIN có thể hard-code trong RiskService hoặc đưa vào .env/config.
Nếu đưa vào .env thì RiskService nên đọc qua Config::get().
```

---

## 17. Security Notes

```txt
- Rule đã kích hoạt phải được lưu vào risk_logs.
- risk_score và risk_level phải được cập nhật vào login_challenges.
- Nếu risk_level = HIGH, challenge phải chuyển sang BLOCKED.
- RuleEngineService chịu trách nhiệm xác định rule nào được kích hoạt.
- RiskService chịu trách nhiệm ghi risk log, tính risk score, risk level và cập nhật challenge.
- Không cộng cùng một rule nhiều lần trong cùng một challenge.
- OTP provider không được bypass Rule Engine.
- Provider failure không được tính là OTP sai.
- Không cấp access token nếu challenge chưa OTP_VERIFIED.
- Không lưu password plain text.
- Không lưu OTP plain text.
- Không lưu token plain text.
```

---

## 18. Responsible Components

| Component | Responsibility |
|---|---|
| AuthService | Gọi RuleEngineService khi sai password, tạo login challenge khi password đúng |
| OtpService | Gọi RuleEngineService khi request OTP nhiều lần hoặc OTP sai |
| SmsService | Adapter provider, gửi/check OTP với mock hoặc Twilio Verify |
| RuleEngineService | Xác định rule đơn và rule kết hợp cần kích hoạt |
| RiskService | Ghi risk log, tính score, cập nhật risk level và status |
| RiskLogRepository | Đọc/ghi bảng risk_logs |
| LoginChallengeRepository | Cập nhật otp_send_count, otp_wrong_count, risk_score, risk_level, status |

---

## 19. Related Files

```txt
docs/architecture.md
docs/api-endpoints.md
docs/database-design.md
docs/error-codes.md
docs/test-cases.md

app/Services/AuthService.php
app/Services/OtpService.php
app/Services/SmsService.php
app/Services/RuleEngineService.php
app/Services/RiskService.php

app/Repositories/LoginChallengeRepository.php
app/Repositories/LoginAttemptRepository.php
app/Repositories/RiskLogRepository.php
app/Repositories/OtpRepository.php
```