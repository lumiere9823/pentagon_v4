# 06 — Risk Register

| ID | Rủi ro | Mức độ | Giảm thiểu | Dấu hiệu cần theo dõi |
|---|---|---|---|---|
| R01 | Thiết kế editor quá rộng ngay MVP | Cao | Chốt component allowlist và non-goals | Scope creep, nhiều component chưa test |
| R02 | Document JSON độc hại hoặc quá lớn | Cao | Server schema validation, limits, sanitize | 4xx bất thường, payload tăng |
| R03 | XSS từ rich text/custom HTML | Cao | Sanitize, CSP, hạn chế HTML, không chạy user JS | Security test thất bại |
| R04 | User truy cập website/page ngoài quyền | Cao | Authorization policy ở mọi endpoint, matrix tests | IDOR/403 test thất bại |
| R05 | Autosave ghi đè thay đổi mới hơn | Cao | Optimistic locking, conflict UX | Stale writes, lost update |
| R06 | Publish làm public site lỗi/nửa phiên bản | Cao | Temporary release + validation + atomic switch | Publish failure hoặc broken assets |
| R07 | Static output lộ dữ liệu riêng tư | Cao | Tách static/dynamic; review output | Có PII/token trong HTML/assets |
| R08 | RAM 2GB bị vượt | Cao | Đo peak RSS, giới hạn worker, không thêm service vô cớ | OOM kill, swap, latency tăng |
| R09 | Hosting không hỗ trợ atomic symlink/swap | Cao | Xác minh Phase 00; chọn release directory/router phù hợp | Không thể switch an toàn |
| R10 | Schema component thay đổi làm hỏng page cũ | Trung bình/Cao | schemaVersion, migration, fixtures | Renderer lỗi với page cũ |
| R11 | Media upload chứa file nguy hiểm | Cao | MIME sniffing, size limits, storage isolation | File type mismatch |
| R12 | Slug rename làm hỏng SEO/link cũ | Trung bình | Redirect policy và uniqueness | 404 sau rename |
| R13 | Queue/cache làm tăng tải DB | Trung bình | Chỉ thêm khi đo đạc; monitor query/load | DB connections/latency tăng |
| R14 | Rollback chỉ đổi pointer nhưng asset cũ bị xóa | Cao | Giữ assets/release theo retention | Broken asset sau rollback |
| R15 | Thiếu backup/restore thực tế | Cao | Rehearsal định kỳ, kiểm tra restore | Backup tồn tại nhưng restore thất bại |

## Blocking questions
- PostgreSQL cùng host với PHP-FPM hay chạy riêng?
- Nginx/static output có thể được cấu hình trực tiếp không?
- Hosting cho phép atomic symlink hoặc release swap không?
- Website có chứa form, login, user-specific data hay chỉ landing pages?
- Ai có quyền publish, và có cần approval workflow?
