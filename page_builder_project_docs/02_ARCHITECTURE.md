# 02 — Architecture Baseline

## 1. Nguyên tắc
- Modular monolith trước microservices.
- Laravel là source of truth cho identity, permissions, website/page metadata, revisions, media metadata và publish jobs.
- Vue 3 + TypeScript cho admin/editor.
- PostgreSQL là database chính.
- Public pages ưu tiên HTML tĩnh; chỉ phần cần dữ liệu/request-time mới gọi Laravel/API.
- Đơn giản hóa vận hành: không thêm Redis/Octane/SSR/Kubernetes cho đến khi số liệu chứng minh cần thiết.
- Hợp đồng dữ liệu có version; schema validation nằm ở server.

## 2. Sơ đồ logic

```text
Admin Browser
  └── Vue 3 + TypeScript Editor
       ├── Component Palette
       ├── Canvas / Selection / Drag-Drop
       ├── Inspector
       ├── Command History (undo/redo)
       └── Draft API
             └── Laravel Modular Monolith
                  ├── Identity & Access
                  ├── Website / Page
                  ├── Document & Component Schema
                  ├── Draft / Revision
                  ├── Media Library
                  ├── Preview
                  └── Publish Orchestrator
                        ├── PostgreSQL
                        ├── Media Storage
                        └── Static Output Directory / Object Storage

Public Visitor
  ├── Nginx/CDN → Published HTML + hashed assets
  └── Laravel/API → Dynamic endpoints only
```

## 3. Document schema
Document JSON là dữ liệu có cấu trúc, không phải code executable. Định dạng sơ bộ:

```json
{
  "schemaVersion": 1,
  "root": {
    "id": "stable-node-id",
    "type": "Page",
    "children": [
      {
        "id": "stable-node-id-2",
        "type": "Hero",
        "props": {
          "title": "Example title",
          "subtitle": "Example subtitle"
        },
        "styles": {
          "desktop": {},
          "tablet": {},
          "mobile": {}
        },
        "children": []
      }
    ]
  },
  "pageSettings": {
    "title": "Example page",
    "description": ""
  }
}
```

Quy tắc:
- Node ID ổn định, duy nhất trong document.
- `type` phải nằm trong registry server/client đã được cho phép.
- Props/styles phải validate theo schema của component.
- Giới hạn độ sâu, tổng số node, kích thước document và độ dài chuỗi.
- Không chấp nhận event handler, script, executable expression hay URL không hợp lệ.
- Có `schemaVersion` và migration rõ ràng khi schema thay đổi.
- Không dựa vào JSON gửi từ client như nguồn tin cậy.

## 4. Phân lớp backend gợi ý
- `Domain/Identity`
- `Domain/Websites`
- `Domain/Pages`
- `Domain/PageDocuments`
- `Domain/Components`
- `Domain/Media`
- `Domain/Revisions`
- `Domain/Publishing`
- `Domain/Seo`
- `Domain/Audit`

Có thể dùng namespace/feature folders phù hợp cấu trúc Laravel hiện tại; không tạo abstraction chỉ để làm đẹp kiến trúc.

## 5. Luồng lưu draft
1. Client tạo document state mới.
2. Validate cấu trúc sơ bộ ở client để UX nhanh.
3. Gửi bản cập nhật với revision/version hiện tại.
4. Server authorize user trên website/page.
5. Server validate schema, giới hạn kích thước và kiểm tra optimistic locking.
6. Lưu draft và cập nhật trạng thái.
7. Client chỉ báo Saved khi server xác nhận.
8. Nếu conflict, không âm thầm ghi đè; hiển thị lựa chọn reload/compare/resolve.

Autosave cần debounce; tránh gửi request cho mỗi keystroke. Có thể cân nhắc patch-based updates sau khi có baseline, nhưng MVP nên chọn cách dễ kiểm thử trước.

## 6. Luồng publish tĩnh
1. Xác định revision bất biến cần publish.
2. Validate document và các asset reference.
3. Render HTML/assets từ renderer đã version hóa.
4. Ghi output vào thư mục release tạm riêng.
5. Kiểm tra output, links và metadata cần thiết.
6. Atomic switch/symlink hoặc cơ chế release swap tương đương.
7. Chỉ sau khi switch thành công mới đánh dấu publish thành công và cập nhật `published_revision_id`.
8. Giữ release trước để rollback nhanh.
9. Khi lỗi, giữ nguyên public release đang chạy và ghi log/audit.

Không ghi đè trực tiếp các file public đang phục vụ trong lúc build. Nếu hosting không hỗ trợ atomic symlink/swap, Phase 00 phải xác định chiến lược an toàn tương đương.

## 7. Hybrid dynamic content
HTML tĩnh phù hợp với nội dung public ít thay đổi. Các tính năng như form submit, đăng nhập, dữ liệu cá nhân, search hoặc dữ liệu cập nhật theo request cần endpoint động riêng.

- Không nhúng secret hoặc dữ liệu cá nhân vào HTML public.
- Không cache response có dữ liệu user-specific như public static.
- Form endpoint cần CSRF/rate limit/validation phù hợp.
- Định nghĩa rõ phần static và dynamic trong component contract.
- Không để public renderer gọi backend cho mọi component mặc định.

## 8. Media
- Lưu file binary ở storage phù hợp; DB chỉ giữ metadata.
- Kiểm tra MIME thực tế, dung lượng, quyền truy cập và tên file.
- Tạo thumbnail/variant theo nhu cầu, tránh xử lý đồng bộ quá nặng trong request.
- Dùng URL/asset path ổn định có thể version hóa.
- Nếu cần xóa asset, kiểm tra usage/reference trước.

## 9. Hiệu năng và RAM
Ưu tiên số liệu thật hơn con số lý thuyết:
- Đo peak RSS của PHP-FPM worker và memory của PostgreSQL trên môi trường gần production.
- Bắt đầu với số worker PHP-FPM thấp, ví dụ 2, rồi đo tải và latency trước khi tăng.
- `shared_buffers=128MB` chỉ là điểm bắt đầu tham khảo nếu PostgreSQL cùng máy; phải điều chỉnh theo workload và RAM còn lại.
- Tính cả OS, Nginx, PHP-FPM, PostgreSQL, cron/queue, file cache và headroom.
- Không chạy queue worker hoặc process Node/SSR thường trực nếu không có nhu cầu rõ ràng.
- Nếu DB chạy chung máy 2GB, cần giới hạn bộ nhớ cẩn trọng hơn; nếu DB managed/tách máy, budget ứng dụng khác.

## 10. Observability
- Log structured cho publish, preview, authorization failures và schema validation failures.
- Có request/correlation ID.
- Theo dõi HTTP 5xx, latency, failed jobs, disk usage và publish failure.
- Không ghi token, mật khẩu, session cookie hay nội dung nhạy cảm vào log.
- Có health check không làm lộ thông tin hệ thống.

## 11. Những điểm chưa chốt
- Static output nằm trên local disk, object storage hay CDN.
- Cơ chế queue: sync trong MVP hay database queue.
- Domain routing/custom domains.
- Rich text editor và policy sanitize.
- Có cần multi-tenant/website memberships ngay từ đầu không.
