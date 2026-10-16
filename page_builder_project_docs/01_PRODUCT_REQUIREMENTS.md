# 01 — Product Requirements Document (PRD)

## 1. Tầm nhìn
Xây dựng một Page Builder Admin cho phép người quản trị tạo và quản lý website/page bằng giao diện trực quan, không cần viết code cho các thao tác nội dung phổ biến. Public pages cần tải nhanh, chi phí vận hành thấp, an toàn và có khả năng rollback.

## 2. Người dùng và vai trò
- **Owner/Admin:** quản lý website, thành viên, cấu hình, publish và rollback.
- **Editor:** tạo/sửa nội dung, preview, lưu bản nháp; quyền publish có thể được cấp riêng.
- **Viewer/Reviewer (tùy chọn):** xem bản nháp/preview, không sửa.

MVP có thể triển khai RBAC tối thiểu; không cần xây dựng hệ thống workflow phức tạp ngay từ đầu.

## 3. Mục tiêu MVP
1. Đăng nhập và phân quyền cơ bản.
2. Tạo website và page, quản lý slug/status.
3. Thêm, xóa, sắp xếp và lồng component trong giới hạn schema.
4. Chỉnh sửa content và style qua Inspector.
5. Undo/redo các thao tác trong phiên editor.
6. Preview desktop/tablet/mobile.
7. Lưu draft có trạng thái saving/saved/error rõ ràng.
8. Tạo preview URL an toàn, không để draft bị truy cập công khai.
9. Publish page ra public URL; ưu tiên HTML tĩnh.
10. Lưu revision và rollback theo cách tạo revision mới, không sửa lịch sử cũ.
11. SEO cơ bản: title, description, canonical/slug và sitemap ở mức phù hợp.
12. Có kiểm thử, logging, backup và quy trình deploy/rollback.

## 4. Ngoài phạm vi MVP
- Website builder kiểu pixel-perfect tự do không giới hạn.
- Chạy JavaScript tùy ý do người dùng nhập.
- Marketplace plugin bên thứ ba.
- Collaboration realtime nhiều người cùng sửa một canvas.
- A/B testing, personalization phức tạp, commerce/CMS đa kênh đầy đủ.
- SSR riêng, microservices, Kubernetes, Elasticsearch hoặc Redis nếu chưa có số liệu chứng minh cần thiết.

## 5. Component ban đầu
- Section
- Container
- Heading
- Rich Text
- Image
- Button
- Hero
- Columns
- Card Grid
- FAQ
- Spacer
- Divider

Mỗi component phải có schema có version, defaults, validation, renderer, inspector metadata và test. Không cho client tự định nghĩa component tùy ý trong MVP.

## 6. Luồng người dùng chính
1. Đăng nhập.
2. Chọn/tạo website.
3. Tạo page và nhập title/slug.
4. Mở editor, thêm component từ panel.
5. Sắp xếp component trên canvas.
6. Chọn component và chỉnh content/style ở Inspector.
7. Undo/redo, preview responsive.
8. Autosave draft và xác nhận trạng thái lưu.
9. Preview bản draft qua URL có kiểm soát quyền và thời hạn.
10. Publish.
11. Kiểm tra public URL.
12. Khi cần, rollback về revision trước đó; rollback tạo revision mới.

## 7. Yêu cầu phi chức năng
### Hiệu năng
- Canvas chỉ render lại vùng cần thiết; tránh render toàn cây sau mọi thao tác.
- Lazy-load thư viện editor nặng khi vào editor.
- Asset public dùng tên file có hash và cache dài hạn.
- Public HTML tĩnh được phục vụ trực tiếp bởi Nginx/CDN nếu có.
- Thiết lập ngân sách hiệu năng sau khi có baseline đo đạc thật.

### Tính tin cậy
- Autosave có debounce, trạng thái rõ ràng và xử lý lỗi mạng.
- Không ghi đè revision đã publish.
- Publish phải có thể retry và idempotent theo publish/revision key.
- Build/publish lỗi không được làm mất bản public đang chạy.

### Bảo mật
- Validate document JSON ở server.
- Authorization theo website/page trên mọi endpoint.
- Không thực thi JavaScript từ document JSON.
- Sanitize rich text/HTML.
- Giới hạn MIME, dung lượng và quyền truy cập media.
- Preview token có thời hạn, có thể thu hồi và không đoán được.
- Audit các hành động publish, rollback và thay đổi quyền.

### Khả năng truy cập
- Hỗ trợ keyboard cơ bản, focus rõ ràng và label cho form controls.
- Không chỉ dựa vào màu để truyền đạt trạng thái.
- Component cần có cấu trúc heading/alt text phù hợp.

## 8. Tiêu chí chấp nhận MVP
- Một editor được phân quyền có thể tạo page, thêm và sửa các component được hỗ trợ, lưu draft và mở lại mà dữ liệu vẫn chính xác.
- Undo/redo không làm mất nội dung đã lưu.
- Preview không làm lộ draft cho người chưa được cấp quyền.
- Publish tạo public page đúng revision đã chọn.
- Nếu publish thất bại, bản public hiện tại vẫn hoạt động.
- Rollback tạo revision mới và có thể publish lại.
- Có automated tests cho authorization, schema validation, revision và publish pipeline.
- Có hướng dẫn backup/restore và deploy/rollback.

## 9. Câu hỏi cần xác nhận ở Phase 00
- Một máy chủ hay tách DB/app? Dung lượng RAM thực tế dành riêng cho ứng dụng là bao nhiêu?
- Public site phục vụ một domain hay nhiều custom domain?
- Có cần multi-tenant ngay MVP không?
- Những phần nào cần động (form, search, account, API data)?
- Rich text cho phép HTML đến mức nào?
- Có yêu cầu preview theo quyền, lịch publish, hoặc approval workflow không?
- Hosting có quyền cấu hình Nginx và thư mục publish không?
