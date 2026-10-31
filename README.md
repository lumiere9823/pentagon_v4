# Pentagon Page Builder

Pentagon là ứng dụng page builder đa website, xây dựng bằng Laravel, Inertia và
Vue. Người dùng có thể tạo website/page, chỉnh sửa nội dung theo component, quản
lý media và template, preview, publish/unpublish, lưu revision và rollback.

## Yêu cầu local

- PHP 8.3 trở lên cùng các extension thông dụng của Laravel và `pdo_pgsql`.
- Composer 2.
- Node.js 22 và npm.
- PostgreSQL 18 được khuyến nghị; các kiểm thử concurrency của dự án được xác
  minh trên PostgreSQL 18.
- Git.

Kiểm tra nhanh môi trường:

```bash
php -v
php -m
composer --version
node --version
npm --version
psql --version
```

## Cài đặt lần đầu

### 1. Lấy mã nguồn và cài dependency

```bash
git clone <repository-url> pentagon
cd pentagon
composer install
npm ci
```

### 2. Tạo database PostgreSQL

Ví dụ với user quản trị mặc định `postgres`:

```bash
createdb -U postgres page_builder_dev
```

Hoặc chạy trong `psql`:

```sql
CREATE DATABASE page_builder_dev;
```

### 3. Tạo file môi trường

Linux/macOS/Git Bash:

```bash
cp .env.example .env
```

PowerShell:

```powershell
Copy-Item .env.example .env
```

Điền đúng thông tin PostgreSQL trong `.env`:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=page_builder_dev
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password
```

Nếu PostgreSQL local không dùng password, để trống `DB_PASSWORD`.

### 4. Khởi tạo ứng dụng

```bash
php artisan key:generate
php artisan migrate
php artisan templates:seed
php artisan storage:link
```

Lệnh `templates:seed` tạo năm starter template hệ thống. `storage:link` cần thiết
để trình duyệt truy cập các media đã upload.

### 5. Tạo tài khoản local

Cách nhanh nhất là seed tài khoản đã xác minh:

```bash
php artisan db:seed
```

Đăng nhập bằng:

```text
Email: test@example.com
Password: password
```

Bạn cũng có thể đăng ký tại `http://localhost:8000/register`. Vì local dùng
`MAIL_MAILER=log`, link xác minh email được ghi trong
`storage/logs/laravel.log`, không được gửi ra hộp thư thật.

> `php artisan db:seed` nên dùng cho database local mới. Chạy lại khi email
> `test@example.com` đã tồn tại có thể gây lỗi unique constraint.

## Chạy ứng dụng

Chạy toàn bộ development stack bằng một lệnh:

```bash
composer run dev
```

Lệnh này khởi động Laravel tại `http://localhost:8000`, Vite dev server, queue
listener và log viewer khi môi trường hỗ trợ. Dừng bằng `Ctrl+C`.

Nếu muốn chạy riêng từng process:

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
npm run dev
```

Mỗi lệnh trên cần một terminal riêng. Sau khi đăng nhập, luồng sử dụng cơ bản là:

1. Tạo một website.
2. Tạo page từ starter template.
3. Mở Builder để chỉnh sửa và preview.
4. Publish page.
5. Truy cập URL public dạng `/sites/{website-slug}/{page-slug}`.

## Cài đặt nhanh bằng Composer

Sau khi đã tạo database và cấu hình `.env`, có thể dùng:

```bash
composer run setup
```

Script này cài dependency, sinh `APP_KEY`, migrate database và build frontend.
Sau đó vẫn nên chạy hai lệnh dành riêng cho dự án:

```bash
php artisan templates:seed
php artisan storage:link
```

## Các lệnh thường dùng

```bash
# Xóa cache cấu hình sau khi sửa .env
php artisan optimize:clear

# Chạy migration mới
php artisan migrate

# Build frontend production
npm run build

# Kiểm tra health local/production-like
php artisan app:production-health-check

# Xem danh sách route
php artisan route:list --except-vendor
```

## Kiểm thử và kiểm tra chất lượng

Backend mặc định dùng SQLite in-memory, không tác động database local:

```bash
composer ci:check
```

Frontend unit/component tests:

```bash
npm run test:frontend
```

Browser E2E dùng database SQLite cô lập và tự build frontend:

```bash
npx playwright install chromium
npm run test:e2e
```

Trên Windows, cấu hình E2E dùng Microsoft Edge đã cài trên máy. Trên Linux CI,
để cài cả system dependencies dùng:

```bash
npx playwright install --with-deps chromium
```

PostgreSQL concurrency tests cần database dùng riêng tên `pentagon_test`:

```bash
createdb -U postgres pentagon_test
php artisan test -c phpunit.pgsql.xml
```

Đặt `DB_PASSWORD` phù hợp trong `.env` trước khi chạy. Không trỏ bộ test này vào
database chứa dữ liệu cần giữ.

Các lệnh kiểm tra riêng:

```bash
composer lint:check
composer types:check
npm run check
npm run types:check
```

## Xử lý lỗi thường gặp

### `could not find driver`

Bật/cài PHP extension `pdo_pgsql`, sau đó khởi động lại terminal hoặc PHP-FPM.

### `connection refused` hoặc `password authentication failed`

Kiểm tra PostgreSQL đang chạy và các biến `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD` trong `.env`. Sau khi sửa, chạy:

```bash
php artisan optimize:clear
```

### Frontend trắng hoặc báo thiếu Vite manifest

Chạy `npm ci`, sau đó dùng `npm run dev` trong lúc phát triển hoặc `npm run build`
để tạo asset tĩnh.

### Ảnh upload trả 404

Tạo lại public storage link:

```bash
php artisan storage:link
```

Trên Linux/macOS, bảo đảm process PHP có quyền ghi vào `storage` và
`bootstrap/cache`.

### Không vào được dashboard sau khi đăng ký

Dashboard yêu cầu email đã xác minh. Với mail driver local, mở link verification
trong `storage/logs/laravel.log`, hoặc dùng tài khoản seed đã xác minh ở trên.

## Tài liệu kỹ thuật

Kiến trúc, domain model, publishing, security và runbook đầy đủ nằm tại
[`page_builder_project_docs/PENTAGON_SYSTEM_REFERENCE.md`](page_builder_project_docs/PENTAGON_SYSTEM_REFERENCE.md).

