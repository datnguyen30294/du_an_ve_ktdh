## Giới thiệu

API dành cho bên thứ 3 tích hợp với hệ thống Residential Management. Xác thực bằng **JWT (HMAC-SHA256)** sử dụng `client_key` và `secret_key`.

## Xác thực (Authentication)

### Bước 1: Tạo API Client và nhận credentials

Truy cập trang quản trị Platform để tạo API client:

1. Đăng nhập vào **[https://service360.demego.vn](https://service360.demego.vn)** bằng tài khoản quản trị viên Platform (requester).
2. Vào mục **Platform → API Clients** (`/platform/api-clients`).
3. Nhấn **"+ Tạo mới"** → điền thông tin:
   - **Tổ chức**: tenant mà API client thuộc về.
   - **Dự án**: dự án mà API client được phép thao tác (API client luôn bị giới hạn trong 1 dự án).
   - **Tên ứng dụng**: ví dụ `ERP Connector`, `Mobile App`.
   - **Quyền truy cập (Scopes)**: tick các nhóm quyền cần thiết (xem bảng Scope bên dưới).
4. Nhấn **Lưu** — hệ thống tạo client và hiển thị:

| Thông tin | Mô tả | Ví dụ |
|-----------|--------|-------|
| `client_key` | Định danh client (công khai) | `ck_aBcDeFgHiJkLmNoPqRsT...` |
| `secret_key` | Khóa bí mật để ký JWT (chỉ hiển thị **1 lần** khi tạo) | `sk_xYzAbCdEfGhIjKlMnOpQ...` |
| `scopes` | Danh sách quyền đã chọn | `departments:read`, `work-schedules:write` |

> ⚠️ **Quan trọng:** `secret_key` chỉ hiển thị **1 lần duy nhất** ngay sau khi tạo. Hãy copy và lưu trữ an toàn (ví dụ vào vault/1Password). Nếu mất, vào chi tiết API client → nhấn **"Tạo lại secret"** (Regenerate) — secret cũ sẽ bị vô hiệu hóa ngay lập tức.

> 💡 Bạn có thể chỉnh sửa `scopes`, đổi tên hoặc tạm ngừng / kích hoạt lại API client bất cứ lúc nào qua UI mà không cần tạo mới key.

### Bước 2: Tạo JWT Token

Sử dụng `client_key` và `secret_key` để tạo JWT token với thuật toán **HS256**.

**JWT Payload (Claims):**

| Claim | Bắt buộc | Mô tả |
|-------|----------|--------|
| `sub` | ✅ | Giá trị `client_key` được cấp |
| `iat` | ✅ | Thời điểm tạo token (Unix timestamp) |
| `exp` | ✅ | Thời điểm hết hạn (Unix timestamp, tối đa 1 năm kể từ `iat`) |

**Ví dụ code tạo JWT:**

**Python:**
```python
import jwt, time

client_key = "ck_your_client_key_here"
secret_key = "sk_your_secret_key_here"

payload = {
    "sub": client_key,
    "iat": int(time.time()),
    "exp": int(time.time()) + 3600  # hết hạn sau 1 giờ
}

token = jwt.encode(payload, secret_key, algorithm="HS256")
```

**Node.js:**
```javascript
const jwt = require("jsonwebtoken");

const clientKey = "ck_your_client_key_here";
const secretKey = "sk_your_secret_key_here";

const token = jwt.sign(
  { sub: clientKey },
  secretKey,
  { algorithm: "HS256", expiresIn: "1h" }
);
```

**PHP:**
```php
use Firebase\JWT\JWT;

$clientKey = 'ck_your_client_key_here';
$secretKey = 'sk_your_secret_key_here';

$payload = [
    'sub' => $clientKey,
    'iat' => time(),
    'exp' => time() + 3600, // hết hạn sau 1 giờ
];

$token = JWT::encode($payload, $secretKey, 'HS256');
```

**cURL:**
```bash
# Tạo token bằng tool bên ngoài, sau đó gọi API:
curl -X GET "https://{tenant}.example.com/api/v1/ext/departments" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Accept: application/json"
```

### Bước 3: Gọi API

Gửi JWT token trong header `Authorization` với prefix `Bearer`:

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

> **Base URL:** `https://{tenant}.example.com/api/v1/ext`
>
> Mỗi tổ chức có subdomain (tenant) riêng. Liên hệ admin để biết subdomain của tổ chức.

## Phân quyền (Scopes)

API client chỉ có thể truy cập các resource được cấp quyền (scope). Mỗi domain có 2 scope: `:read` (chỉ đọc) và `:write` (thêm / sửa / xóa).

| Scope | Mô tả | Endpoints |
|-------|--------|-----------|
| `departments:read` | Xem danh sách & chi tiết phòng ban | `GET /departments`, `GET /departments/{id}` |
| `departments:write` | Thêm / sửa / xóa phòng ban | `POST`, `PUT`, `DELETE /departments` |
| `accounts:read` | Xem danh sách & chi tiết nhân viên | `GET /accounts`, `GET /accounts/{id}` |
| `accounts:write` | Thêm / sửa / xóa nhân viên | `POST`, `PUT`, `DELETE /accounts` |
| `job_titles:read` | Xem danh sách & chi tiết chức danh | `GET /job-titles`, `GET /job-titles/{id}` |
| `job_titles:write` | Thêm / sửa / xóa chức danh | `POST`, `PUT`, `DELETE /job-titles` |
| `projects:read` | Xem danh sách & chi tiết dự án | `GET /projects`, `GET /projects/{id}` |
| `projects:write` | Thêm / sửa / xóa dự án | `POST`, `PUT`, `DELETE /projects` |
| `shifts:read` | Xem danh sách & chi tiết ca làm việc | `GET /shifts`, `GET /shifts/{id}` |
| `shifts:write` | Thêm / sửa / xóa ca làm việc | `POST`, `PUT`, `DELETE /shifts` |
| `work-schedules:read` | Xem đăng ký ca làm việc | `GET /work-schedules`, `GET /work-schedules/{id}` |
| `work-schedules:write` | Ghi / bulk-upsert / xóa đăng ký ca | `POST`, `PUT`, `DELETE /work-schedules`, `POST /work-schedules/bulk-upsert` |

## Xử lý lỗi

| HTTP Status | Mô tả |
|-------------|--------|
| `401 Unauthorized` | Thiếu token, token hết hạn, chữ ký không hợp lệ, hoặc client không tồn tại |
| `403 Forbidden` | Client không có scope cần thiết, hoặc client bị vô hiệu hóa |
| `422 Unprocessable Entity` | Dữ liệu gửi lên không hợp lệ (validation error) |

**Ví dụ response lỗi:**
```json
{
  "message": "JWT không hợp lệ hoặc đã hết hạn."
}
```

## Giới hạn & Lưu ý

- **Token lifetime tối đa:** 1 năm (`exp - iat ≤ 365 ngày`)
- **Clock tolerance:** 30 giây (cho phép sai lệch thời gian giữa server và client)
- **Dữ liệu trả về:** Tất cả response thành công có format `{ "success": true, "data": {...} }`
- **Tenant isolation:** Mỗi API client chỉ truy cập được dữ liệu của tổ chức (tenant) mà nó thuộc về
- **Project scope:** API client được gắn với 1 dự án cụ thể — các thao tác đọc/ghi tự động filter theo dự án đó
