# 03 — Data Model Baseline

Đây là mô hình logic khởi điểm. Cần đối chiếu yêu cầu và database hiện có trước khi tạo migration.

## 1. Bảng chính

### users
- `id`
- `name`
- `email` (unique)
- `password`
- timestamps

Có thể dùng auth scaffold chính thức của Laravel thay vì tự triển khai.

### roles / permissions (nếu RBAC không dùng package)
- `roles`: `id`, `key`, `name`
- `permissions`: `id`, `key`, `name`
- `role_permissions`: `role_id`, `permission_id`

Không nhất thiết tự viết RBAC nếu dự án đã có package/chuẩn phù hợp.

### websites
- `id`
- `name`
- `slug` hoặc key nội bộ
- `primary_domain` nullable
- `settings_json`
- `created_by`
- timestamps
- `deleted_at` nếu cần soft delete

### website_memberships
- `website_id`
- `user_id`
- `role_id` hoặc membership role key
- `created_at`
- unique `(website_id, user_id)`

### pages
- `id`
- `website_id`
- `title`
- `slug`
- `status` (draft/published/archived theo state model đã chốt)
- `draft_revision_id` nullable
- `published_revision_id` nullable
- `created_by`
- `updated_by`
- timestamps
- `deleted_at` nullable

Index/constraint:
- unique `(website_id, slug)` theo chính sách slug.
- index `(website_id, status)`.
- Xác định chính sách slug khi rename và redirect URL cũ.

### page_revisions
- `id`
- `page_id`
- `revision_number`
- `schema_version`
- `document_json` JSONB
- `change_summary` nullable
- `created_by`
- `created_at`

Constraint/index:
- unique `(page_id, revision_number)`.
- index `(page_id, created_at desc)`.
- Revision đã publish nên bất biến; sửa nội dung tạo revision mới.

### page_templates
- `id`
- `website_id` nullable nếu template global được hỗ trợ
- `name`
- `description`
- `schema_version`
- `document_json` JSONB
- `created_by`
- timestamps

### media_assets
- `id`
- `website_id`
- `storage_disk`
- `storage_path`
- `original_filename`
- `mime_type`
- `size_bytes`
- `width` / `height` nullable
- `alt_text` nullable
- `uploaded_by`
- timestamps
- `deleted_at` nullable

Không lưu binary lớn trong PostgreSQL trừ khi có quyết định rõ ràng.

### media_usages (optional)
- `id`
- `media_asset_id`
- `page_revision_id`
- `node_id`
- `field_path`
- created_at

Có thể tính usage từ document nếu quy mô nhỏ; bảng này hữu ích cho tìm asset không còn được tham chiếu.

### publish_events
- `id`
- `page_id`
- `page_revision_id`
- `status`
- `release_id` nullable
- `idempotency_key` unique
- `started_at`
- `completed_at` nullable
- `error_code` nullable
- `error_summary` nullable
- `triggered_by`

Không lưu secret hoặc stack trace nhạy cảm trong `error_summary`.

### audit_logs
- `id`
- `actor_user_id` nullable
- `website_id` nullable
- `action`
- `subject_type`
- `subject_id`
- `metadata_json` JSONB
- `ip_address` nullable theo policy
- `created_at`

Cần quy định retention và dữ liệu nào được phép ghi vào metadata.

## 2. Quan hệ
- Website có nhiều pages, memberships, media assets.
- Page có nhiều revisions.
- Page giữ pointer tới draft revision và published revision.
- Publish event tham chiếu revision cụ thể.
- Revision tham chiếu asset qua document; media_usages là tùy chọn.

## 3. Quy tắc consistency
- `draft_revision_id` và `published_revision_id` phải thuộc cùng page.
- Không cho revision thuộc page A được gắn vào page B.
- Publish chỉ nhận revision đã validate.
- Update draft nên dùng optimistic locking/version token để ngăn ghi đè.
- Khi rollback, tạo revision mới bằng nội dung revision mục tiêu; không sửa revision lịch sử.
- Xóa page/website/media cần xác định hành vi với published output và references.
- Unique slug cần được enforce ở DB, không chỉ validate trong UI.

## 4. State model gợi ý
Page status và publish job status nên tách nhau:
- Page: `draft`, `published`, `archived` (có thể mở rộng sau).
- Publish event: `pending`, `running`, `succeeded`, `failed`, `cancelled`.

Một page có thể có published revision cũ trong khi draft revision mới hơn; tránh dùng một status đơn giản để đại diện cho mọi trạng thái.

## 5. Migration strategy
1. Khảo sát schema hiện có.
2. Chốt naming, foreign keys, delete policies và slug policy.
3. Tạo migration nhỏ, có constraints/indexes.
4. Test migration fresh và rollback trên môi trường dev.
5. Không chỉnh sửa migration đã chạy ở môi trường dùng chung nếu cần deployment an toàn; tạo migration mới.
